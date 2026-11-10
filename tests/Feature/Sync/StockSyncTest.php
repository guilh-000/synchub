<?php

use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Sync\Stock\StockSync;
use App\Sync\SyncResult;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

beforeEach(function () {
    LegacyErp::boot();
    config(['synchub.stock.batch_size' => 100, 'synchub.stock.overlap_seconds' => 5]);
});

function syncStock(bool $full = false): SyncResult
{
    return app(StockSync::class)->run($full);
}

function mapProduct(int $code, int $storeId): void
{
    SyncMapping::query()->create(['entity' => SyncMapping::PRODUCT, 'legacy_key' => (string) $code, 'store_id' => $storeId]);
}

test('the total of all warehouses is sent as the product stock', function () {
    $store = FakeStore::fake();
    mapProduct(1, 501);
    LegacyErp::stock(1, 1, 10);
    LegacyErp::stock(1, 2, 5.5);

    $result = syncStock();

    expect($result->updated)->toBe(1)
        ->and($store->batches[0]['update'])->toBe([['id' => 501, 'manage_stock' => true, 'stock_quantity' => 15]]);
});

test('negative stock is shown as zero', function () {
    $store = FakeStore::fake();
    mapProduct(1, 501);
    LegacyErp::stock(1, 1, -2);

    syncStock();

    expect($store->batches[0]['update'][0]['stock_quantity'])->toBe(0);
});

test('an unchanged quantity is not sent again', function () {
    $store = FakeStore::fake();
    mapProduct(1, 501);
    LegacyErp::stock(1, 1, 10, '2026-01-01 08:00:00');
    syncStock();

    // Same total, moved between warehouses: rows changed, the store's number did not.
    LegacyErp::stock(1, 1, 7, '2026-01-02 08:00:00');
    LegacyErp::stock(1, 2, 3, '2026-01-02 08:00:00');
    $result = syncStock();

    expect($result->skipped)->toBe(1)
        ->and($store->batches)->toHaveCount(1);
});

test('only stock changed after the watermark is read', function () {
    FakeStore::fake();
    mapProduct(1, 501);
    mapProduct(2, 502);
    LegacyErp::stock(1, 1, 10, '2026-01-01 08:00:00');
    LegacyErp::stock(2, 1, 10, '2026-01-01 09:00:00');
    syncStock();

    LegacyErp::stock(1, 2, 4, '2026-01-03 08:00:00');
    $result = syncStock();

    expect($result->updated)->toBe(1)
        ->and(SyncCursor::named(StockSync::KEY)->last_key)->toBe('1');
});

test('stock of a product not in the store yet is reported for a retry', function () {
    $store = FakeStore::fake();
    LegacyErp::stock(9, 1, 3);

    $result = syncStock();

    expect($result->failures)->toBe([['item_key' => '9', 'message' => 'Product is not in the store yet. Retry after the product sync.']])
        ->and($store->batches)->toBe([]);

    mapProduct(9, 509);
    $retry = app(StockSync::class)->retry(['9']);

    expect($retry->updated)->toBe(1);
});
