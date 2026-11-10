<?php

namespace App\Sync\Products;

use App\Integrations\LegacyErp\LegacyProduct;
use App\Integrations\LegacyErp\LegacyProductReader;
use App\Integrations\WooCommerce\BatchItemResult;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Sync\Sync;
use App\Sync\SyncResult;
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
class ProductSync implements Sync
{
    public const string KEY = 'products';

    public function __construct(
        private readonly LegacyProductReader $reader,
        private readonly WooCommerceClient $store,
        private readonly ProductPayloadMapper $mapper,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'Products';
    }

    public function direction(): string
    {
        return 'ERP → store';
    }

    /**
     * @throws WooCommerceException when the store is unreachable after the retries
     */
    public function run(bool $full = false, SyncResult $result = new SyncResult): SyncResult
    {
        $cursor = SyncCursor::named(self::KEY);
        $start = $full ? null : $cursor->startingPoint((int) config('synchub.products.overlap_seconds'));

        $this->process($this->reader->changedSince($start), $result, $cursor);

        return $result;
    }

    /**
     * @param  list<string>  $itemKeys  ERP product codes
     */
    public function retry(array $itemKeys, SyncResult $result = new SyncResult): SyncResult
    {
        $this->process($this->reader->byCodes(array_map(intval(...), $itemKeys)), $result);

        return $result;
    }

    /**
     * @param  LazyCollection<int, LegacyProduct>  $products
     */
    private function process(LazyCollection $products, SyncResult $result, ?SyncCursor $cursor = null): void
    {
        $batchSize = max(1, min(100, (int) config('synchub.products.batch_size')));

        $products->chunk($batchSize)->each(function (LazyCollection $chunk) use ($result, $cursor): void {
            /** @var Collection<int, LegacyProduct> $batch */
            $batch = $chunk->collect()->values();
            $this->syncBatch($batch, $result);

            /** @var LegacyProduct $last */
            $last = $batch->last();
            $cursor?->advanceTo($last->changedAt, (string) $last->code);
        });
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
}
