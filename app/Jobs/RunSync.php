<?php

namespace App\Jobs;

use App\Enums\SyncTrigger;
use App\Sync\SyncRegistry;
use App\Sync\SyncResult;
use App\Sync\SyncRunRecorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs one sync on the queue and records it as a SyncRun. Unique per sync:
 * a sync never starts while another run of it is queued or running, so two
 * workers cannot send the same records at once.
 */
class RunSync implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Attempts before the job is marked as failed (the store may be down). */
    public int $tries = 3;

    /** Seconds before an attempt is abandoned. */
    public int $timeout = 600;

    /** Seconds the uniqueness lock lasts if a worker dies mid-run. */
    public int $uniqueFor = 900;

    /**
     * @param  list<string>|null  $itemKeys  replay only these items (a retry)
     */
    public function __construct(
        public string $sync,
        public bool $full = false,
        public SyncTrigger $trigger = SyncTrigger::Schedule,
        public ?array $itemKeys = null,
        public ?int $retryOfId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->sync;
    }

    /**
     * Seconds between attempts: give a store that is down time to recover.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SyncRegistry $syncs, SyncRunRecorder $recorder): void
    {
        $sync = $syncs->get($this->sync);

        $recorder->record(
            $sync->key(),
            $this->trigger,
            $this->full,
            fn (SyncResult $result): SyncResult => $this->itemKeys === null
                ? $sync->run($this->full, $result)
                : $sync->retry($this->itemKeys, $result),
            $this->retryOfId,
        );
    }
}
