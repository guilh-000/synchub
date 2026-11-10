<?php

namespace App\Integrations\LegacyErp;

use Carbon\CarbonImmutable;

/**
 * A product's stock in the ERP: the sum over its warehouses.
 */
final readonly class LegacyStock
{
    public function __construct(
        public int $code,
        public float $quantity,
        public CarbonImmutable $changedAt,
    ) {}

    /**
     * @param  object{CODPROD: int|string, TOTAL: string|float|int, CHANGED_AT: string}  $row
     */
    public static function fromRow(object $row): self
    {
        return new self(
            code: (int) $row->CODPROD,
            quantity: (float) $row->TOTAL,
            changedAt: CarbonImmutable::parse($row->CHANGED_AT),
        );
    }

    /**
     * What the store can show: whole units, never below zero (the ERP lets
     * stock go negative; a store cannot sell -2 items).
     */
    public function sellableQuantity(): int
    {
        return max(0, (int) floor($this->quantity));
    }
}
