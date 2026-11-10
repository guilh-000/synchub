<?php

namespace App\Sync\Orders;

use App\Integrations\LegacyErp\LegacyOrderWriter;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Sync\Sync;
use App\Sync\SyncResult;
use Carbon\CarbonImmutable;

/**
 * Brings paid store orders into the legacy ERP.
 *
 * - Incremental by the store's modification date, with an overlap window.
 * - Never twice: an order already mapped is skipped, and before writing, the
 *   ERP is checked for its store number too (the ERP has no unique key, and
 *   the mapping is saved in another database after the ERP commit).
 * - All or nothing per order: an order with a product SyncHub does not know
 *   is not written at all, and is reported so it can be replayed.
 */
class OrderImport implements Sync
{
    public const string KEY = 'orders';

    public function __construct(
        private readonly WooCommerceClient $store,
        private readonly LegacyOrderWriter $erp,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'Orders';
    }

    public function direction(): string
    {
        return 'store → ERP';
    }

    public function run(bool $full = false, SyncResult $result = new SyncResult): SyncResult
    {
        $cursor = SyncCursor::named(self::KEY);
        $after = $full ? null : $cursor->startingPoint((int) config('synchub.orders.overlap_seconds'));
        $pageSize = max(1, min(100, (int) config('synchub.orders.page_size')));
        /** @var list<string> $statuses */
        $statuses = config('synchub.orders.statuses');
        $page = 1;

        do {
            $orders = $this->store->ordersModifiedSince($after, $statuses, $page++, $pageSize);

            foreach ($orders as $order) {
                $this->import($order, $result);
                $cursor->advanceTo(
                    CarbonImmutable::parse((string) $order['date_modified_gmt'], 'UTC'),
                    (string) $order['id'],
                );
            }
        } while (count($orders) === $pageSize);

        return $result;
    }

    /**
     * @param  list<string>  $itemKeys  store order IDs
     */
    public function retry(array $itemKeys, SyncResult $result = new SyncResult): SyncResult
    {
        foreach ($itemKeys as $orderId) {
            $this->import($this->store->order((int) $orderId), $result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function import(array $order, SyncResult $result): void
    {
        $result->read++;
        $storeId = (int) $order['id'];
        $storeNumber = (string) ($order['number'] ?? $storeId);

        if (SyncMapping::query()->where('entity', SyncMapping::ORDER)->where('store_id', $storeId)->exists()) {
            $result->skipped++;

            return;
        }

        /** @var list<string> $paid */
        $paid = config('synchub.orders.statuses');

        if (! in_array($order['status'] ?? null, $paid, true)) {
            $result->fail((string) $storeId, "Order is not paid (status: {$order['status']}).");

            return;
        }

        $items = $this->items($order, $storeId, $result);

        if ($items === null) {
            return;
        }

        $erpNumber = $this->erp->findByStoreNumber($storeNumber);

        if ($erpNumber !== null) {
            $this->remember($erpNumber, $storeId);
            $result->skipped++;

            return;
        }

        $erpNumber = $this->erp->create([
            'store_number' => $storeNumber,
            'customer_name' => $this->customerName($order),
            'issued_at' => CarbonImmutable::parse((string) $order['date_created'])->format('Y-m-d H:i:s'),
            'total' => number_format((float) $order['total'], 2, '.', ''),
        ], $items);

        $this->remember($erpNumber, $storeId);
        $result->created++;
    }

    /**
     * The order's items as ERP products, or null (and a failure) when one of
     * them is not a product SyncHub created.
     *
     * @param  array<string, mixed>  $order
     * @return list<array{code: int, quantity: float, unit_price: string}>|null
     */
    private function items(array $order, int $storeId, SyncResult $result): ?array
    {
        /** @var list<array{product_id: int, name?: string, quantity: int|float, price: int|float|string}> $lines */
        $lines = $order['line_items'] ?? [];

        if ($lines === []) {
            $result->fail((string) $storeId, 'Order has no items.');

            return null;
        }

        $codes = SyncMapping::query()
            ->where('entity', SyncMapping::PRODUCT)
            ->whereIn('store_id', array_column($lines, 'product_id'))
            ->pluck('legacy_key', 'store_id');

        $items = [];

        foreach ($lines as $line) {
            $code = $codes->get($line['product_id']);

            if ($code === null) {
                $name = $line['name'] ?? 'unnamed';
                $result->fail((string) $storeId, "Item \"{$name}\" (product #{$line['product_id']}) did not come from the ERP.");

                return null;
            }

            $items[] = [
                'code' => (int) $code,
                'quantity' => (float) $line['quantity'],
                'unit_price' => number_format((float) $line['price'], 2, '.', ''),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function customerName(array $order): string
    {
        /** @var array{first_name?: string, last_name?: string, company?: string} $billing */
        $billing = $order['billing'] ?? [];
        $name = trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? ''));

        return $name !== '' ? $name : (($billing['company'] ?? '') ?: 'Online customer');
    }

    private function remember(int $erpNumber, int $storeId): void
    {
        SyncMapping::query()->updateOrCreate(
            ['entity' => SyncMapping::ORDER, 'store_id' => $storeId],
            ['legacy_key' => (string) $erpNumber, 'synced_at' => now()],
        );
    }
}
