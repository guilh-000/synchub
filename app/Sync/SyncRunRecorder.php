<?php

namespace App\Sync;

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Models\SyncRun;
use Closure;
use Throwable;

/**
 * Wraps a sync in a SyncRun row: created as "running" before the work starts
 * and closed with its counters, rejected items and error when it ends, so
 * the dashboard shows runs in progress and runs that died halfway.
 */
class SyncRunRecorder
{
    /**
     * @param  Closure(SyncResult): mixed  $work
     *
     * @throws Throwable whatever the work threw, after recording it
     */
    public function record(string $sync, SyncTrigger $trigger, bool $full, Closure $work, ?int $retryOfId = null): SyncRun
    {
        $run = SyncRun::query()->create([
            'sync' => $sync,
            'trigger' => $trigger,
            'full' => $full,
            'retry_of_id' => $retryOfId,
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
        ]);

        $result = new SyncResult;

        try {
            $work($result);
        } catch (Throwable $exception) {
            $this->finish($run, $result, SyncRunStatus::Failed, $exception->getMessage());

            throw $exception;
        }

        $this->finish($run, $result, $result->failed() > 0 ? SyncRunStatus::Partial : SyncRunStatus::Succeeded);

        return $run;
    }

    private function finish(SyncRun $run, SyncResult $result, SyncRunStatus $status, ?string $error = null): void
    {
        $run->update([
            ...$result->counters(),
            'status' => $status,
            'error' => $error,
            'finished_at' => now(),
        ]);

        $run->failures()->createMany($result->failures);
    }
}
