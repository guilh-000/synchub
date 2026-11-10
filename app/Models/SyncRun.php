<?php

namespace App\Models;

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One execution of a sync: who started it, how it ended and its counters.
 *
 * @property int $id
 * @property string $sync
 * @property SyncTrigger $trigger
 * @property bool $full
 * @property int|null $retry_of_id
 * @property SyncRunStatus $status
 * @property int $read
 * @property int $created
 * @property int $updated
 * @property int $skipped
 * @property int $failed
 * @property string|null $error
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sync', 'trigger', 'full', 'retry_of_id', 'status', 'read', 'created', 'updated', 'skipped', 'failed', 'error', 'started_at', 'finished_at'])]
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory;

    /**
     * @return HasMany<SyncRunFailure, $this>
     */
    public function failures(): HasMany
    {
        return $this->hasMany(SyncRunFailure::class);
    }

    /**
     * The run whose failures this one replayed.
     *
     * @return BelongsTo<SyncRun, $this>
     */
    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class, 'retry_of_id');
    }

    /**
     * Runs that replayed this one's failures.
     *
     * @return HasMany<SyncRun, $this>
     */
    public function retries(): HasMany
    {
        return $this->hasMany(SyncRun::class, 'retry_of_id');
    }

    public function durationInSeconds(): ?float
    {
        return $this->finished_at === null
            ? null
            : round($this->started_at->diffInMilliseconds($this->finished_at) / 1000, 1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => SyncTrigger::class,
            'status' => SyncRunStatus::class,
            'full' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
