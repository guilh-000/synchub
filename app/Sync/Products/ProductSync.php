<?php

namespace App\Sync\Products;

use App\Integrations\LegacyErp\LegacyProduct;
use App\Integrations\LegacyErp\LegacyProductReader;
use App\Integrations\WooCommerce\BatchItemResult;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Sends products changed in the legacy ERP to the store.
 *
 * - Incremental: reads only what changed after the watermark (SyncCursor),
 *   rereading a small overlap window so nothing changed in the same second
 *   is lost.
 * - Idempotent: SyncMapping links each ERP product to its store ID, so a
 *   product is created once and updated afterwards; a payload identical to
 *   the last one sent is skipped.
 * - Batched: one request per batch of products, not one per product.
 * - Safe to interrupt: the watermark only moves after a batch is accepted
 *   by the store, so a run that dies resumes from the last good batch.
 */
class ProductSync
{
    public const string CURSOR = 'products';

    public function __construct(
        private readonly LegacyProductReader $reader,
        private readonly WooCommerceClient $store,
        private readonly ProductPayloadMapper $mapper,
    ) {}

    /**
     * @param  bool  $full  ignore the watermark and compare every product
     *
     * @throws WooCommerceException when the store is unreachable after the retries
     */
    public function run(bool $full = false): SyncResult
    {
        $result = new SyncResult;
        $cursor = SyncCursor::named(self::CURSOR);
        $batchSize = max(1, min(100, (int) config('synchub.products.batch_size')));

        $this->reader
            ->changedSince($full ? null : $this->startOf($cursor))
            ->chunk($batchSize)
            ->each(function (LazyCollection $chunk) use ($result, $cursor): void {
                /** @var Collection<int, LegacyProduct> $products */
                $products = $chunk->collect()->values();
                $this->syncBatch($products, $result);
                $this->advance($cursor, $products->last());
            });

        return $result;
    }

    /**
     * @param  Collection<int, LegacyProduct>  $products
     */
    private function syncBatch(Collection $products, SyncResult $result): void
    {
        $result->read += $products->count();

        $mappings = SyncMapping::query()
            ->where('entity', SyncMapping::PRODUCT)
            ->whereIn('legacy_key', $products->map(fn (LegacyProduct $product): string => (string) $product->code))
            ->get()
            ->keyBy('legacy_key');

        /** @var list<array{product: LegacyProduct, payload: array<string, mixed>, hash: string}> $toCreate */
        $toCreate = [];
        /** @var list<array{product: LegacyProduct, payload: array<string, mixed>, hash: string, store_id: int}> $toUpdate */
        $toUpdate = [];

        foreach ($products as $product) {
            $payload = $this->mapper->toStorePayload($product);
            $hash = $this->mapper->hash($payload);
            $mapping = $mappings->get((string) $product->code);

            if ($mapping === null) {
                $toCreate[] = compact('product', 'payload', 'hash');
            } elseif ($mapping->payload_hash === $hash) {
                $result->skipped++;
            } else {
                $toUpdate[] = ['product' => $product, 'payload' => $payload, 'hash' => $hash, 'store_id' => $mapping->store_id];
            }
        }

        if ($toCreate === [] && $toUpdate === []) {
            return;
        }

        $response = $this->store->batchProducts(
            array_map(fn (array $item): array => $item['payload'], $toCreate),
            array_map(fn (array $item): array => ['id' => $item['store_id']] + $item['payload'], $toUpdate),
        );

        $adopted = [];

        foreach ($toCreate as $index => $item) {
            $outcome = $response['create'][$index] ?? new BatchItemResult(null, 'missing', 'No result returned for this item.');

            if ($outcome->succeeded()) {
                $this->remember($item['product'], (int) $outcome->id, $item['hash']);
                $result->created++;
            } elseif (($existingId = $outcome->existingProductId()) !== null) {
                // The SKU is already in the store (created by hand, or the
                // mapping was lost): adopt that product instead of failing.
                $adopted[] = $item + ['store_id' => $existingId];
            } else {
                $result->fail((string) $item['product']->code, (string) $outcome->errorMessage);
            }
        }

        foreach ($toUpdate as $index => $item) {
            $this->recordUpdate($item, $response['update'][$index] ?? null, $result);
        }

        if ($adopted !== []) {
            $adoptResponse = $this->store->batchProducts(
                [],
                array_map(fn (array $item): array => ['id' => $item['store_id']] + $item['payload'], $adopted),
            );

            foreach ($adopted as $index => $item) {
                $this->recordUpdate($item, $adoptResponse['update'][$index] ?? null, $result);
            }
        }
    }

    /**
     * @param  array{product: LegacyProduct, payload: array<string, mixed>, hash: string, store_id: int}  $item
     */
    private function recordUpdate(array $item, ?BatchItemResult $outcome, SyncResult $result): void
    {
        if ($outcome?->succeeded()) {
            $this->remember($item['product'], $item['store_id'], $item['hash']);
            $result->updated++;

            return;
        }

        $result->fail((string) $item['product']->code, $outcome->errorMessage ?? 'No result returned for this item.');
    }

    private function remember(LegacyProduct $product, int $storeId, string $hash): void
    {
        SyncMapping::query()->updateOrCreate(
            ['entity' => SyncMapping::PRODUCT, 'legacy_key' => (string) $product->code],
            ['store_id' => $storeId, 'payload_hash' => $hash, 'synced_at' => now()],
        );
    }

    /**
     * Where this run starts reading: the watermark minus the overlap window.
     */
    private function startOf(SyncCursor $cursor): ?CarbonImmutable
    {
        return $cursor->changed_at === null
            ? null
            : CarbonImmutable::instance($cursor->changed_at)->subSeconds((int) config('synchub.products.overlap_seconds'));
    }

    /**
     * Moves the watermark to the last product of a finished batch. Never
     * backwards: batches inside the overlap window are older than it.
     */
    private function advance(SyncCursor $cursor, LegacyProduct $last): void
    {
        $current = $cursor->changed_at;
        $isAhead = $current === null
            || $last->changedAt->greaterThan($current)
            || ($last->changedAt->equalTo($current) && $last->code > (int) $cursor->last_key);

        if ($isAhead) {
            $cursor->update(['changed_at' => $last->changedAt, 'last_key' => (string) $last->code]);
        }
    }
}
