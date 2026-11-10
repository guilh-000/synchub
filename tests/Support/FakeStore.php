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

    /** @var list<array<string, mixed>> orders in the store, oldest change first */
    public array $orders = [];

    /** @var list<array<string, mixed>> query strings of every orders listing */
    public array $orderQueries = [];

    /** @var list<array{create: list<array<string, mixed>>, update: list<array<string, mixed>>}> */
    public array $batches = [];

    private int $nextId = 1000;

    public static function fake(): self
    {
        self::configure();
        $store = new self;

        Http::fake([
            'store.test/wp-json/wc/v3/products/batch' => fn (Request $request) => Http::response($store->handle($request)),
            'store.test/wp-json/wc/v3/orders/*' => fn (Request $request) => Http::response($store->findOrder($request)),
            'store.test/wp-json/wc/v3/orders*' => fn (Request $request) => Http::response($store->listOrders($request)),
        ]);

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

    /**
     * A paid order with the given items (product ID => quantity).
     *
     * @param  array<int, int>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function order(int $id, array $items, array $overrides = []): array
    {
        $order = $overrides + [
            'id' => $id,
            'number' => (string) $id,
            'status' => 'processing',
            'date_created' => '2026-02-01T10:00:00',
            'date_modified_gmt' => sprintf('2026-02-01T13:%02d:00', $id % 60),
            'total' => '0.00',
            'billing' => ['first_name' => 'Ana', 'last_name' => 'Souza', 'company' => ''],
            'line_items' => array_map(fn (int $productId, int $quantity): array => [
                'product_id' => $productId,
                'name' => "Product #{$productId}",
                'quantity' => $quantity,
                'price' => 10,
            ], array_keys($items), $items),
        ];

        return $this->orders[] = $order;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOrders(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $this->orderQueries[] = $query;
        $perPage = (int) ($query['per_page'] ?? 10);
        $page = (int) ($query['page'] ?? 1);

        $orders = array_values(array_filter($this->orders, fn (array $order): bool => ! isset($query['modified_after'])
            || $order['date_modified_gmt'] > $query['modified_after']));

        return array_slice($orders, ($page - 1) * $perPage, $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrder(Request $request): array
    {
        $id = (int) basename((string) parse_url($request->url(), PHP_URL_PATH));

        foreach ($this->orders as $order) {
            if ($order['id'] === $id) {
                return $order;
            }
        }

        return [];
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
