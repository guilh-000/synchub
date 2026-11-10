<?php

use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Sync\Orders\OrderImport;
use App\Sync\SyncResult;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

beforeEach(function () {
    LegacyErp::boot();
    config(['synchub.orders.page_size' => 50, 'synchub.orders.overlap_seconds' => 60]);

    foreach ([1 => 501, 2 => 502] as $code => $storeId) {
        SyncMapping::query()->create(['entity' => SyncMapping::PRODUCT, 'legacy_key' => (string) $code, 'store_id' => $storeId]);
    }
});

function importOrders(bool $full = false): SyncResult
{
    return app(OrderImport::class)->run($full);
}

test('a paid store order is written to the ERP with its items', function () {
    $store = FakeStore::fake();
    $store->order(10, [501 => 2, 502 => 1], ['total' => '30.00']);

    $result = importOrders();

    $order = LegacyErp::orders()[0];
    expect($result->created)->toBe(1)
        ->and($order)->toMatchArray(['ORIGEM' => 'LOJA', 'NUM_EXTERNO' => '10', 'CLIENTE_NOME' => 'Ana Souza', 'SITUACAO' => 'A'])
        ->and((float) $order->VL_TOTAL)->toBe(30.0)
        ->and(array_map(fn (object $item): array => [(int) $item->CODPROD, (float) $item->QTD], LegacyErp::orderItems((int) $order->NUMPED)))
        ->toBe([[1, 2.0], [2, 1.0]])
        ->and(SyncMapping::query()->where('entity', SyncMapping::ORDER)->value('store_id'))->toBe(10);
});

test('only paid orders are requested from the store', function () {
    $store = FakeStore::fake();

    importOrders();

    expect($store->orderQueries[0])->toMatchArray(['status' => 'processing,completed', 'orderby' => 'modified', 'order' => 'asc']);
});

test('an order is never written twice', function () {
    $store = FakeStore::fake();
    $store->order(10, [501 => 1]);
    importOrders();

    $result = importOrders(full: true);

    expect($result->skipped)->toBe(1)
        ->and(LegacyErp::orders())->toHaveCount(1);
});

test('an order already in the ERP is recognised even without its mapping', function () {
    $store = FakeStore::fake();
    $store->order(10, [501 => 1]);
    importOrders();
    SyncMapping::query()->where('entity', SyncMapping::ORDER)->delete();

    $result = importOrders(full: true);

    expect($result->skipped)->toBe(1)
        ->and(LegacyErp::orders())->toHaveCount(1)
        ->and(SyncMapping::query()->where('entity', SyncMapping::ORDER)->exists())->toBeTrue();
});

test('an order with a product that did not come from the ERP is not written at all', function () {
    $store = FakeStore::fake();
    $store->order(11, [501 => 1, 999 => 1]);

    $result = importOrders();

    expect($result->failures)->toBe([['item_key' => '11', 'message' => 'Item "Product #999" (product #999) did not come from the ERP.']])
        ->and(LegacyErp::orders())->toBe([]);
});

test('the next run asks only for orders changed since the last one, minus the overlap', function () {
    $store = FakeStore::fake();
    $store->order(10, [501 => 1], ['date_modified_gmt' => '2026-02-01T13:10:00']);
    importOrders();

    importOrders();

    expect($store->orderQueries[1]['modified_after'])->toBe('2026-02-01T13:09:00')
        ->and(SyncCursor::named(OrderImport::KEY)->last_key)->toBe('10');
});

test('orders are read page by page', function () {
    $store = FakeStore::fake();
    config(['synchub.orders.page_size' => 2]);
    foreach (range(10, 14) as $id) {
        $store->order($id, [501 => 1]);
    }

    $result = importOrders();

    expect($result->created)->toBe(5)
        ->and(array_column($store->orderQueries, 'page'))->toBe(['1', '2', '3']);
});

test('a retry fetches the given orders again', function () {
    $store = FakeStore::fake();
    $store->order(11, [503 => 1]);
    importOrders();
    SyncMapping::query()->create(['entity' => SyncMapping::PRODUCT, 'legacy_key' => '3', 'store_id' => 503]);

    $result = app(OrderImport::class)->retry(['11']);

    expect($result->created)->toBe(1)
        ->and(LegacyErp::orders())->toHaveCount(1);
});

test('an unpaid order is not imported on a retry', function () {
    $store = FakeStore::fake();
    $store->order(12, [501 => 1], ['status' => 'pending']);

    $result = app(OrderImport::class)->retry(['12']);

    expect($result->failures[0]['message'])->toBe('Order is not paid (status: pending).');
});
