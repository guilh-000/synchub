<?php

namespace App\Sync\Stock;

use App\Integrations\LegacyErp\LegacyStock;
use App\Integrations\LegacyErp\LegacyStockReader;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Sync\Sync;
use App\Sync\SyncResult;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Sends each product's total stock (all warehouses) to the store.
 *
 * Stock changes do not touch PRODUTO.DT_ALT, so this is its own sync with
 * its own watermark on ESTOQUE. It only updates products the product sync
 * already created; a stock for a product not in the store yet is reported
 * and can be replayed once the product exists.
 */
class StockSync implements Sync
{
    public const string KEY = 'stock';

    public function __construct(
        private readonly LegacyStockReader $reader,
        private readonly WooCommerceClient $store,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'Stock';
    }

    public function direction(): string
    {
        return 'ERP → store';
    }

    public function run(bool $full = false, SyncResult $result = new SyncResult): SyncResult
    {
        $cursor = SyncCursor::named(self::KEY);
        $start = $full ? null : $cursor->startingPoint((int) config('synchub.stock.overlap_seconds'));

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
     * @param  LazyCollection<int, LegacyStock>  $stocks
     */
    private function process(LazyCollection $stocks, SyncResult $result, ?SyncCursor $cursor = null): void
    {
        $batchSize = max(1, min(100, (int) config('synchub.stock.batch_size')));

        $stocks->chunk($batchSize)->each(function (LazyCollection $chunk) use ($result, $cursor): void {
            /** @var Collection<int, LegacyStock> $batch */
            $batch = $chunk->collect()->values();
            $this->syncBatch($batch, $result);

            /** @var LegacyStock $last */
            $last = $batch->last();
            $cursor?->advanceTo($last->changedAt, (string) $last->code);
        });
    }

    /**
     * @param  Collection<int, LegacyStock>  $stocks
     */
    private function syncBatch(Collection $stocks, SyncResult $result): void
    {
        $result->read += $stocks->count();
        /** @var list<string> $codes */
        $codes = $stocks->map(fn (LegacyStock $stock): string => (string) $stock->code)->all();

        $products = $this->mappings(SyncMapping::PRODUCT, $codes);
        $sent = $this->mappings(SyncMapping::STOCK, $codes);

        /** @var list<array{code: string, store_id: int, quantity: int, hash: string}> $toUpdate */
        $toUpdate = [];

        foreach ($stocks as $stock) {
            $code = (string) $stock->code;
            $product = $products->get($code);

            if ($product === null) {
                $result->fail($code, 'Product is not in the store yet. Retry after the product sync.');

                continue;
            }

            $quantity = $stock->sellableQuantity();
            $hash = hash('sha256', (string) $quantity);

            if ($sent->get($code)?->payload_hash === $hash) {
                $result->skipped++;

                continue;
            }

            $toUpdate[] = ['code' => $code, 'store_id' => $product->store_id, 'quantity' => $quantity, 'hash' => $hash];
        }

        if ($toUpdate === []) {
            return;
        }

        $response = $this->store->batchProducts([], array_map(fn (array $item): array => [
            'id' => $item['store_id'],
            'manage_stock' => true,
            'stock_quantity' => $item['quantity'],
        ], $toUpdate));

        foreach ($toUpdate as $index => $item) {
            $outcome = $response['update'][$index] ?? null;

            if ($outcome?->succeeded()) {
                SyncMapping::query()->updateOrCreate(
                    ['entity' => SyncMapping::STOCK, 'legacy_key' => $item['code']],
                    ['store_id' => $item['store_id'], 'payload_hash' => $item['hash'], 'synced_at' => now()],
                );
                $result->updated++;
            } else {
                $result->fail($item['code'], $outcome->errorMessage ?? 'No result returned for this item.');
            }
        }
    }

    /**
     * @param  list<string>  $codes
     * @return Collection<string, SyncMapping>
     */
    private function mappings(string $entity, array $codes): Collection
    {
        /** @var Collection<string, SyncMapping> */
        return SyncMapping::query()
            ->where('entity', $entity)
            ->whereIn('legacy_key', $codes)
            ->get()
            ->keyBy('legacy_key');
    }
}
