<?php

namespace App\Jobs;

use App\Sync\Products\ProductSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queued product sync. Unique: a run never starts while another is queued or
 * running, so two workers cannot send the same products at once.
 */
class SyncProducts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Attempts before the job is marked as failed (the store may be down). */
    public int $tries = 3;

    /** Seconds before an attempt is abandoned. */
    public int $timeout = 600;

    /** Seconds the uniqueness lock lasts if a worker dies mid-run. */
    public int $uniqueFor = 900;

    public function __construct(public bool $full = false) {}

    /**
     * Seconds between attempts: give a store that is down time to recover.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ProductSync $sync): void
    {
        $result = $sync->run($this->full);

        Log::info('Product sync finished.', $result->counters());

        foreach ($result->failures as $failure) {
            Log::warning('Product not synced.', $failure);
        }
    }
}
