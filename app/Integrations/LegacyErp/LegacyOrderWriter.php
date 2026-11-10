<?php

namespace App\Integrations\LegacyErp;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes store orders into the legacy ERP (PEDIDO + PEDIDO_ITEM).
 */
class LegacyOrderWriter
{
    public const string ORIGIN = 'LOJA';

    /**
     * The ERP order already holding this store order number, if any. The
     * ERP has no unique key on NUM_EXTERNO, so this is the last guard
     * against writing the same order twice.
     */
    public function findByStoreNumber(string $storeNumber): ?int
    {
        $number = $this->db()->table('PEDIDO')
            ->where('ORIGEM', self::ORIGIN)
            ->where('NUM_EXTERNO', $storeNumber)
            ->value('NUMPED');

        return $number === null ? null : (int) $number;
    }

    /**
     * Header and items in one transaction: the ERP never sees half an order.
     *
     * @param  array{store_number: string, customer_name: string, issued_at: string, total: string}  $header
     * @param  list<array{code: int, quantity: float, unit_price: string}>  $items
     * @return int the new NUMPED
     */
    public function create(array $header, array $items): int
    {
        return $this->db()->transaction(function (ConnectionInterface $db) use ($header, $items): int {
            $number = (int) $db->table('PEDIDO')->insertGetId([
                'ORIGEM' => self::ORIGIN,
                'NUM_EXTERNO' => $header['store_number'],
                'CLIENTE_NOME' => mb_substr($header['customer_name'], 0, 100),
                'CLIENTE_DOC' => null,
                'DT_EMISSAO' => $header['issued_at'],
                'VL_TOTAL' => $header['total'],
                'SITUACAO' => 'A',
                'DT_ALT' => now()->format('Y-m-d H:i:s'),
            ], 'NUMPED');

            $db->table('PEDIDO_ITEM')->insert(array_map(fn (array $item, int $index): array => [
                'NUMPED' => $number,
                'SEQ' => $index + 1,
                'CODPROD' => $item['code'],
                'QTD' => $item['quantity'],
                'VL_UNIT' => $item['unit_price'],
            ], $items, array_keys($items)));

            return $number;
        });
    }

    private function db(): ConnectionInterface
    {
        return DB::connection('legacy');
    }
}
