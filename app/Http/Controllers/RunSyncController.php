<?php

namespace App\Http\Controllers;

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Jobs\SyncProducts;
use App\Models\SyncRun;
use App\Sync\Products\ProductSync;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The dashboard's "Run now" button: queues a sync unless one is running.
 */
class RunSyncController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $isRunning = SyncRun::query()
            ->where('sync', ProductSync::CURSOR)
            ->where('status', SyncRunStatus::Running)
            ->exists();

        if ($isRunning) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'A product sync is already running.']);

            return back();
        }

        SyncProducts::dispatch(trigger: SyncTrigger::Manual);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product sync queued.']);

        return back();
    }
}
