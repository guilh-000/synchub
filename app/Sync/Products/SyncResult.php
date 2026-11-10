<?php

namespace App\Sync\Products;

/**
 * Counters of one sync run, plus the items that failed and why.
 */
final class SyncResult
{
    public int $read = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var list<array{legacy_key: string, message: string}> */
    public array $failures = [];

    public function fail(string $legacyKey, string $message): void
    {
        $this->failures[] = ['legacy_key' => $legacyKey, 'message' => $message];
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
