<?php

namespace App\Integrations\LegacyErp;

use Carbon\CarbonImmutable;

/**
 * A product as the legacy ERP stores it, already read into PHP types.
 * Cleaning up the data (case, spaces) is the mapper's job, not this class's.
 */
final readonly class LegacyProduct
{
    public function __construct(
        public int $code,
        public string $reference,
        public string $description,
        public string $price,
        public ?string $weightKg,
        public bool $active,
        public CarbonImmutable $changedAt,
    ) {}

    /**
     * @param  object{CODPROD: int|string, REFERENCIA: string, DESCRICAO: string, PRECO_VENDA: string|float, PESO_KG: string|float|null, ATIVO: string, DT_ALT: string}  $row
     */
    public static function fromRow(object $row): self
    {
        return new self(
            code: (int) $row->CODPROD,
            reference: $row->REFERENCIA,
            description: $row->DESCRICAO,
            price: number_format((float) $row->PRECO_VENDA, 2, '.', ''),
            weightKg: $row->PESO_KG === null ? null : number_format((float) $row->PESO_KG, 3, '.', ''),
            active: $row->ATIVO === 'S',
            changedAt: CarbonImmutable::parse($row->DT_ALT),
        );
    }
}
