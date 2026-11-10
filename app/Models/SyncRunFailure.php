<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item the store rejected during a run, and why.
 *
 * @property int $id
 * @property int $sync_run_id
 * @property string $item_key
 * @property string $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['item_key', 'message'])]
class SyncRunFailure extends Model
{
    /**
     * @return BelongsTo<SyncRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class, 'sync_run_id');
    }
}
