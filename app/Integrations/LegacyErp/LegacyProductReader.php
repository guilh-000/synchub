<?php

namespace App\Integrations\LegacyErp;

use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Reads products from the legacy ERP in change order.
 *
 * The ERP has no "changed since" API, only the DT_ALT column refreshed on
 * every update. Records are read ordered by (DT_ALT, CODPROD) and paged by
 * that pair (keyset pagination), so a page boundary never skips or repeats a
 * row, even when many rows share the same timestamp.
 */
class LegacyProductReader
{
    /**
     * Products changed after the given position, oldest change first.
     *
     * @return LazyCollection<int, LegacyProduct>
     */
    public function changedSince(?CarbonInterface $changedAt, int $afterCode = 0, int $pageSize = 100): LazyCollection
    {
        return LazyCollection::make(function () use ($changedAt, $afterCode, $pageSize) {
            $position = [$changedAt?->format('Y-m-d H:i:s'), $afterCode];

            do {
                $rows = $this->page($position[0], $position[1], $pageSize);

                foreach ($rows as $row) {
                    $product = LegacyProduct::fromRow($row);
                    $position = [$product->changedAt->format('Y-m-d H:i:s'), $product->code];

                    yield $product;
                }
            } while (count($rows) === $pageSize);
        });
    }

    /**
     * @return list<object{CODPROD: int|string, REFERENCIA: string, DESCRICAO: string, PRECO_VENDA: string|float, PESO_KG: string|float|null, ATIVO: string, DT_ALT: string}>
     */
    private function page(?string $changedAt, int $afterCode, int $pageSize): array
    {
        $query = $this->db()->table('PRODUTO')
            ->select(['CODPROD', 'REFERENCIA', 'DESCRICAO', 'PRECO_VENDA', 'PESO_KG', 'ATIVO', 'DT_ALT'])
            ->orderBy('DT_ALT')
            ->orderBy('CODPROD')
            ->limit($pageSize);

        if ($changedAt !== null) {
            $query->where(function ($where) use ($changedAt, $afterCode) {
                $where->where('DT_ALT', '>', $changedAt)
                    ->orWhere(fn ($tie) => $tie->where('DT_ALT', $changedAt)->where('CODPROD', '>', $afterCode));
            });
        }

        /** @var list<object{CODPROD: int|string, REFERENCIA: string, DESCRICAO: string, PRECO_VENDA: string|float, PESO_KG: string|float|null, ATIVO: string, DT_ALT: string}> */
        return $query->get()->all();
    }

    private function db(): ConnectionInterface
    {
        return DB::connection('legacy');
    }
}
