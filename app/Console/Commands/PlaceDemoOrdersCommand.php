<?php

namespace App\Console\Commands;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\SyncMapping;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:place-orders {count=3 : How many orders} {--foreign : Also place one order with a product that did not come from the ERP}')]
#[Description('Place paid orders in the demo store, to watch them reach the ERP')]
class PlaceDemoOrdersCommand extends Command
{
    private const array CUSTOMERS = [
        ['Ana', 'Souza'], ['Bruno', 'Lima'], ['Carla', 'Mendes'], ['Diego', 'Rocha'], ['Elisa', 'Prado'],
    ];

    public function handle(WooCommerceClient $store): int
    {
        if (app()->isProduction()) {
            $this->components->error('Demo data is not placed in production.');

            return self::FAILURE;
        }

        $productIds = SyncMapping::query()->where('entity', SyncMapping::PRODUCT)->pluck('store_id');

        if ($productIds->isEmpty()) {
            $this->components->error('No products in the store yet: run `php artisan sync:run products` first.');

            return self::FAILURE;
        }

        foreach (range(1, (int) $this->argument('count')) as $index) {
            $lines = array_values($productIds->random(min(3, $productIds->count()))
                ->map(fn (int $id): array => ['product_id' => $id, 'quantity' => random_int(1, 4)])
                ->all());

            $order = $store->createOrder($this->order($lines));
            $this->components->twoColumnDetail("Order #{$order['number']}", count($lines).' items');
        }

        if ($this->option('foreign')) {
            $foreign = $store->batchProducts([[
                'name' => 'Gift card (sold only online)',
                'sku' => 'ONLINE-GIFT-'.random_int(1000, 9999),
                'regular_price' => '50.00',
            ]], [])['create'][0];

            $order = $store->createOrder($this->order([['product_id' => (int) $foreign->id, 'quantity' => 1]]));
            $this->components->twoColumnDetail("Order #{$order['number']}", '<fg=yellow>product unknown to the ERP</>');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return array<string, mixed>
     */
    private function order(array $lines): array
    {
        [$first, $last] = self::CUSTOMERS[array_rand(self::CUSTOMERS)];

        return [
            'status' => 'processing',
            'set_paid' => true,
            'billing' => ['first_name' => $first, 'last_name' => $last, 'email' => strtolower($first).'@example.test'],
            'line_items' => $lines,
        ];
    }
}
