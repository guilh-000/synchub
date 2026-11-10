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
                'legacy_key' => $failure->legacy_key,
                'message' => $failure->message,
            ])),
        ];
    }
}
