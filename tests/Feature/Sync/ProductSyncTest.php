<?php

use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Sync\Products\ProductSync;
use App\Sync\SyncResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

beforeEach(function () {
    LegacyErp::boot();
    config(['synchub.products.batch_size' => 100, 'synchub.products.overlap_seconds' => 5]);
});

function syncProducts(bool $full = false): SyncResult
{
    return app(ProductSync::class)->run($full);
}

test('new products are created in the store and mapped', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1);
    LegacyErp::product(2);

    $result = syncProducts();

    expect($result->counters())->toMatchArray(['read' => 2, 'created' => 2, 'failed' => 0])
        ->and(SyncMapping::query()->pluck('store_id', 'legacy_key')->all())
        ->toBe(['1' => $store->products['SKU-00001'], '2' => $store->products['SKU-00002']]);
});

test('products are sent in batches instead of one request each', function () {
    $store = FakeStore::fake();
    config(['synchub.products.batch_size' => 2]);
    foreach (range(1, 5) as $code) {
        LegacyErp::product($code, ['DT_ALT' => "2026-01-01 08:0{$code}:00"]);
    }

    syncProducts();

    expect($store->batches)->toHaveCount(3)
        ->and(array_map(fn (array $batch): int => count($batch['create']), $store->batches))->toBe([2, 2, 1]);
});

test('a second run with nothing changed sends nothing', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1);
    syncProducts();

    $result = syncProducts();

    expect($store->batches)->toHaveCount(1)
        ->and($result->created + $result->updated)->toBe(0);
});

test('only products changed after the watermark are read', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1, ['DT_ALT' => '2026-01-01 08:00:00']);
    LegacyErp::product(2, ['DT_ALT' => '2026-01-01 09:00:00']);
    syncProducts();

    LegacyErp::change(1, ['PRECO_VENDA' => '12.00'], '2026-01-02 10:00:00');
    $result = syncProducts();

    // Product 2 is reread because it sits inside the overlap window, and
    // skipped by its hash; product 1 is the only one sent.
    expect($result->read)->toBe(2)
        ->and($result->skipped)->toBe(1)
        ->and($result->updated)->toBe(1)
        ->and($store->batches[1]['update'][0])->toMatchArray(['id' => $store->products['SKU-00001'], 'regular_price' => '12.00'])
        ->and(SyncCursor::named(ProductSync::KEY)->last_key)->toBe('1');
});

test('a product changed in the same second as the watermark is not lost', function () {
    FakeStore::fake();
    LegacyErp::product(5, ['DT_ALT' => '2026-01-01 08:00:00']);
    syncProducts();

    // Same timestamp as the watermark, lower code: a plain "> last key" would skip it.
    LegacyErp::product(3, ['DT_ALT' => '2026-01-01 08:00:00']);
    $result = syncProducts();

    expect($result->created)->toBe(1)
        ->and(SyncMapping::query()->where('legacy_key', '3')->exists())->toBeTrue();
});

test('a touched row whose data did not change is skipped', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1);
    syncProducts();

    LegacyErp::change(1, [], '2026-01-05 08:00:00');
    $result = syncProducts();

    expect($result->skipped)->toBe(1)
        ->and($store->batches)->toHaveCount(1);
});

test('legacy data is cleaned up before it reaches the store', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1, ['DESCRICAO' => 'RETENTOR DE ÓLEO   20MM   ', 'PESO_KG' => null]);

    syncProducts();

    expect($store->batches[0]['create'][0])
        ->toMatchArray(['name' => 'Retentor de óleo 20mm', 'sku' => 'SKU-00001', 'status' => 'publish'])
        ->not->toHaveKey('weight');
});

test('inactive products are hidden in the store, not deleted', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1, ['ATIVO' => 'N']);

    syncProducts();

    expect($store->batches[0]['create'][0]['status'])->toBe('draft');
});

test('a product whose SKU is already in the store is adopted, not duplicated', function () {
    $store = FakeStore::fake();
    $existingId = $store->seed('SKU-00001');
    LegacyErp::product(1);

    $result = syncProducts();

    expect($result->counters())->toMatchArray(['created' => 0, 'updated' => 1, 'failed' => 0])
        ->and($store->batches[1]['update'][0]['id'])->toBe($existingId)
        ->and(SyncMapping::query()->value('store_id'))->toBe($existingId);
});

test('items the store rejects are reported without stopping the batch', function () {
    FakeStore::configure();
    Http::fake(['store.test/*' => Http::response(['create' => [
        ['id' => 0, 'error' => ['code' => 'woocommerce_rest_invalid_price', 'message' => 'Invalid price.']],
        ['id' => 77],
    ]])]);
    LegacyErp::product(1);
    LegacyErp::product(2);

    $result = syncProducts();

    expect($result->created)->toBe(1)
        ->and($result->failures)->toBe([['item_key' => '1', 'message' => 'Invalid price.']]);
});

test('transient errors are retried', function () {
    FakeStore::configure();
    Http::fake(['store.test/*' => Http::sequence()
        ->push('busy', 503)
        ->push(['create' => [['id' => 50]]]),
    ]);
    Sleep::fake();
    LegacyErp::product(1);

    $result = syncProducts();

    expect($result->created)->toBe(1);
    Http::assertSentCount(2);
});

test('the watermark does not move past a batch the store never accepted', function () {
    FakeStore::configure();
    config(['synchub.products.batch_size' => 1]);
    Http::fake(['store.test/*' => Http::sequence()
        ->push(['create' => [['id' => 50]]])
        ->push('down', 500)->push('down', 500)->push('down', 500)->push('down', 500),
    ]);
    Sleep::fake();
    LegacyErp::product(1, ['DT_ALT' => '2026-01-01 08:00:00']);
    LegacyErp::product(2, ['DT_ALT' => '2026-01-01 09:00:00']);

    expect(fn () => syncProducts())->toThrow(WooCommerceException::class);

    $cursor = SyncCursor::named(ProductSync::KEY);
    expect($cursor->last_key)->toBe('1')
        ->and($cursor->changed_at?->format('Y-m-d H:i:s'))->toBe('2026-01-01 08:00:00');
});

test('a full run compares every product again', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1);
    syncProducts();

    $result = syncProducts(full: true);

    expect($result->read)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($store->sentProducts())->toBe(1);
});

test('a retry sends only the given products, whatever the watermark', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1);
    LegacyErp::product(2);
    LegacyErp::product(3);

    $result = app(ProductSync::class)->retry(['2', '3']);

    expect($result->created)->toBe(2)
        ->and(array_column($store->batches[0]['create'], 'sku'))->toBe(['SKU-00002', 'SKU-00003'])
        ->and(SyncCursor::named(ProductSync::KEY)->changed_at)->toBeNull();
});
