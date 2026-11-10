<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Links a record of the legacy ERP to the same record in the store, so a sync
 * that runs twice updates instead of creating a duplicate.
 *
 * @property int $id
 * @property string $entity
 * @property string $legacy_key
 * @property int $store_id
 * @property string|null $payload_hash
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['entity', 'legacy_key', 'store_id', 'payload_hash', 'synced_at'])]
class SyncMapping extends Model
{
    public const string PRODUCT = 'product';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
