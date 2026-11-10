<?php

namespace App\Models;

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
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public static function named(string $name): self
    {
        return self::query()->firstOrCreate(['name' => $name]);
    }
}
