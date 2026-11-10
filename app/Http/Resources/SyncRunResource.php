<?php

namespace App\Http\Resources;

use App\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyncRun
 */
class SyncRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sync' => $this->sync,
            'trigger' => $this->trigger->value,
            'full' => $this->full,
            'retry_of_id' => $this->retry_of_id,
            'retries' => $this->whenLoaded('retries', fn () => $this->retries->map(fn (SyncRun $retry): array => [
                'id' => $retry->id,
                'status' => $retry->status->value,
            ])),
            'status' => $this->status->value,
            'read' => $this->read,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'error' => $this->error,
            'started_at' => $this->started_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_seconds' => $this->durationInSeconds(),
            'failures' => $this->whenLoaded('failures', fn () => $this->failures->map(fn ($failure): array => [
                'id' => $failure->id,
                'item_key' => $failure->item_key,
                'message' => $failure->message,
            ])),
        ];
    }
}
