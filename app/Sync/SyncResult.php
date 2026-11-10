<?php

namespace App\Sync;

/**
 * Counters of one sync run, plus the items that failed and why.
 *
 * An item key is the record's key on the side it is read from: the ERP code
 * for products and stock, the store's order ID for orders.
 */
final class SyncResult
{
    public int $read = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var list<array{item_key: string, message: string}> */
    public array $failures = [];

    public function fail(string $itemKey, string $message): void
    {
        $this->failures[] = ['item_key' => $itemKey, 'message' => $message];
    }

    public function failed(): int
    {
        return count($this->failures);
    }

    /**
     * @return array{read: int, created: int, updated: int, skipped: int, failed: int}
     */
    public function counters(): array
    {
        return [
            'read' => $this->read,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed(),
        ];
    }
}
