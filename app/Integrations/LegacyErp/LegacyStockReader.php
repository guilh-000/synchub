<?php

namespace App\Integrations\LegacyErp;

use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Reads stock from the legacy ERP, one row per product.
 *
 * ESTOQUE has a row per product and warehouse; the store only knows the
 * total. Rows are grouped by product, the group's newest DT_ALT is its change
 * time, and groups are paged by (change time, code) like the products.
 */
class LegacyStockReader
{
    /**
     * @return LazyCollection<int, LegacyStock>
     */
    public function changedSince(?CarbonInterface $changedAt, int $pageSize = 100): LazyCollection
    {
        return LazyCollection::make(function () use ($changedAt, $pageSize) {
            $position = [$changedAt?->format('Y-m-d H:i:s'), 0];

            do {
                $query = $this->totals()->orderBy('CHANGED_AT')->orderBy('CODPROD')->limit($pageSize);

                if ($position[0] !== null) {
                    [$at, $code] = $position;
                    $query->havingRaw('MAX(DT_ALT) > ? OR (MAX(DT_ALT) = ? AND CODPROD > ?)', [$at, $at, $code]);
                }

                $rows = $query->get()->all();

                foreach ($rows as $row) {
                    /** @var object{CODPROD: int|string, TOTAL: string|float|int, CHANGED_AT: string} $row */
                    $stock = LegacyStock::fromRow($row);
                    $position = [$stock->changedAt->format('Y-m-d H:i:s'), $stock->code];

                    yield $stock;
                }
            } while (count($rows) === $pageSize);
        });
    }

    /**
     * @param  list<int>  $codes
     * @return LazyCollection<int, LegacyStock>
     */
    public function byCodes(array $codes): LazyCollection
    {
        return LazyCollection::make(function () use ($codes) {
            foreach (array_chunk($codes, 500) as $chunk) {
                foreach ($this->totals()->whereIn('CODPROD', $chunk)->orderBy('CODPROD')->get() as $row) {
                    /** @var object{CODPROD: int|string, TOTAL: string|float|int, CHANGED_AT: string} $row */
                    yield LegacyStock::fromRow($row);
                }
            }
        });
    }

    private function totals(): Builder
    {
        return $this->db()->table('ESTOQUE')
            ->select('CODPROD')
            ->selectRaw('SUM(QTD) AS TOTAL')
            ->selectRaw('MAX(DT_ALT) AS CHANGED_AT')
            ->groupBy('CODPROD');
    }

    private function db(): ConnectionInterface
    {
        return DB::connection('legacy');
    }
}
