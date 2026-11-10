<?php

namespace App\Sync\Products;

use App\Integrations\LegacyErp\LegacyProduct;

/**
 * Turns a legacy product into the WooCommerce product payload, cleaning up
 * what legacy data usually gets wrong on the way.
 */
class ProductPayloadMapper
{
    /**
     * @return array{sku: string, name: string, regular_price: string, status: string, weight?: string, meta_data: list<array{key: string, value: string}>}
     */
    public function toStorePayload(LegacyProduct $product): array
    {
        $payload = [
            'sku' => trim($product->reference),
            'name' => $this->cleanName($product->description),
            'regular_price' => $product->price,
            // Inactive products are hidden, never deleted: the store keeps
            // their history (orders, reviews, links).
            'status' => $product->active ? 'publish' : 'draft',
            'meta_data' => [
                ['key' => '_synchub_legacy_code', 'value' => (string) $product->code],
            ],
        ];

        if ($product->weightKg !== null) {
            $payload['weight'] = $product->weightKg;
        }

        return $payload;
    }

    /**
     * Fingerprint of a payload: equal hashes mean nothing to send.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Trims and collapses spaces; a name typed in all caps becomes sentence
     * case ("RETENTOR DE ÓLEO 20MM" -> "Retentor de óleo 20mm").
     */
    private function cleanName(string $description): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $description));

        if ($name === mb_strtoupper($name) && preg_match('/\p{L}/u', $name) === 1) {
            $lower = mb_strtolower($name);
            $name = mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
        }

        return $name;
    }
}
