<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The incremental sync's watermark: how far into the source a sync has read.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $changed_at
 * @property string|null $last_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'changed_at', 'last_key'])]
class SyncCursor extends Model
{
    public static function named(string $name): self
    {
        return self::query()->firstOrCreate(['name' => $name]);
    }

    /**
     * Where the next run starts reading: the watermark minus an overlap
     * window, so a record changed within the same second as the last one
     * read is not lost. Null when nothing was read yet.
     */
    public function startingPoint(int $overlapSeconds): ?CarbonImmutable
    {
        return $this->changed_at === null
            ? null
            : CarbonImmutable::instance($this->changed_at)->subSeconds($overlapSeconds);
    }

    /**
     * Moves the watermark to a processed record. Never backwards: records in
     * the overlap window are older than the watermark.
     */
    public function advanceTo(CarbonInterface $changedAt, string $key): void
    {
        $isAhead = $this->changed_at === null
            || $changedAt->greaterThan($this->changed_at)
            || ($changedAt->equalTo($this->changed_at) && strnatcmp($key, (string) $this->last_key) > 0);

        if ($isAhead) {
            $this->update(['changed_at' => $changedAt, 'last_key' => $key]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }
}
