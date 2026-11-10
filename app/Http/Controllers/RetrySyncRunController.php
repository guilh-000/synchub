<?php

namespace App\Http\Controllers;

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Jobs\RunSync;
use App\Models\SyncRun;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Replays the items a run failed on, as a new run linked to it. Only those
 * items are processed, whatever the watermark says.
 */
class RetrySyncRunController extends Controller
{
    public function __invoke(SyncRun $run): RedirectResponse
    {
        /** @var list<string> $itemKeys */
        $itemKeys = $run->failures()->distinct()->orderBy('item_key')->pluck('item_key')->all();

        if ($itemKeys === []) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'This run has no failed items.']);

            return back();
        }

        if (SyncRun::query()->where('sync', $run->sync)->where('status', SyncRunStatus::Running)->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'This sync is running. Try again when it finishes.']);

            return back();
        }

        RunSync::dispatch($run->sync, false, SyncTrigger::Manual, $itemKeys, $run->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => count($itemKeys).' failed item(s) queued for retry.']);

        return back();
    }
}
