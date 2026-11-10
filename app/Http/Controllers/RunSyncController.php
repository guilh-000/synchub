<?php

namespace App\Http\Controllers;

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Jobs\RunSync;
use App\Models\SyncRun;
use App\Sync\SyncRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The dashboard's "Run now" button: queues a sync unless one is running.
 */
class RunSyncController extends Controller
{
    public function __invoke(string $sync, SyncRegistry $syncs): RedirectResponse
    {
        abort_unless($syncs->has($sync), 404);
        $title = $syncs->get($sync)->title();

        if (SyncRun::query()->where('sync', $sync)->where('status', SyncRunStatus::Running)->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "{$title} sync is already running."]);

            return back();
        }

        RunSync::dispatch($sync, trigger: SyncTrigger::Manual);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$title} sync queued."]);

        return back();
    }
}
