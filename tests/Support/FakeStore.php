<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A WooCommerce /products/batch endpoint in memory: gives IDs to created
 * products, rejects duplicated SKUs like the real one, and keeps every
 * request for assertions.
 */
final class FakeStore
{
    /** @var array<string, int> SKU => product ID */
    public array $products = [];

    /** @var list<array{create: list<array<string, mixed>>, update: list<array<string, mixed>>}> */
    public array $batches = [];

    private int $nextId = 1000;

    public static function fake(): self
    {
        self::configure();
        $store = new self;

        Http::fake(['store.test/wp-json/wc/v3/products/batch' => fn (Request $request) => Http::response($store->handle($request))]);

        return $store;
    }

    /**
     * Points SyncHub at store.test without faking it: for tests that script
     * the responses themselves with Http::fake().
     */
    public static function configure(): void
    {
        config([
            'synchub.woocommerce.base_url' => 'http://store.test',
            'synchub.woocommerce.username' => 'admin',
            'synchub.woocommerce.password' => 'secret',
        ]);
    }

    /**
     * A product that already exists in the store, unknown to SyncHub.
     */
    public function seed(string $sku): int
    {
        return $this->products[$sku] = $this->nextId++;
    }

    public function sentProducts(): int
    {
        return array_sum(array_map(fn (array $batch): int => count($batch['create']) + count($batch['update']), $this->batches));
    }

    /**
     * @return array{create: list<array<string, mixed>>, update: list<array<string, mixed>>}
     */
    private function handle(Request $request): array
    {
        /** @var list<array<string, mixed>> $create */
        $create = $request['create'] ?? [];
        /** @var list<array<string, mixed>> $update */
        $update = $request['update'] ?? [];
        $this->batches[] = ['create' => $create, 'update' => $update];

        return [
            'create' => array_map(function (array $product): array {
                $sku = (string) $product['sku'];

                if (isset($this->products[$sku])) {
                    return ['id' => 0, 'error' => [
                        'code' => 'product_invalid_sku',
                        'message' => 'Invalid or duplicated SKU.',
                        'data' => ['status' => 400, 'resource_id' => $this->products[$sku], 'unique_sku' => $sku],
                    ]];
                }

                return ['id' => $this->products[$sku] = $this->nextId++] + $product;
            }, $create),
            'update' => array_map(fn (array $product): array => $product, $update),
        ];
    }
}
