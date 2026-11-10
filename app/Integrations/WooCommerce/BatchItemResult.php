<?php

namespace App\Integrations\WooCommerce;

/**
 * The outcome of one item of a /products/batch request.
 */
final readonly class BatchItemResult
{
    /**
     * @param  array<string, mixed>  $errorData
     */
    public function __construct(
        public ?int $id,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $errorData = [],
    ) {}

    /**
     * @param  array{id?: int, error?: array{code?: string, message?: string, data?: array<string, mixed>}}  $item
     */
    public static function fromResponse(array $item): self
    {
        if (isset($item['error'])) {
            return new self(
                id: null,
                errorCode: $item['error']['code'] ?? 'unknown',
                errorMessage: $item['error']['message'] ?? '',
                errorData: $item['error']['data'] ?? [],
            );
        }

        return new self(id: $item['id'] ?? null);
    }

    public function succeeded(): bool
    {
        return $this->errorCode === null && $this->id !== null;
    }

    /**
     * The store's ID of the product that already uses the SKU, when the
     * error is a duplicated SKU.
     */
    public function existingProductId(): ?int
    {
        $resourceId = $this->errorData['resource_id'] ?? null;

        return $this->errorCode === 'product_invalid_sku' && is_numeric($resourceId) ? (int) $resourceId : null;
    }
}
