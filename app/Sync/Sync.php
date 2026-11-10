<?php

namespace App\Sync;

/**
 * One direction of data between the legacy ERP and the store.
 */
interface Sync
{
    /** Stable identifier: cursor name, sync_runs.sync, route segment. */
    public function key(): string;

    public function title(): string;

    /** "ERP → store" or "store → ERP". */
    public function direction(): string;

    /**
     * Processes everything changed since the watermark (or everything, when
     * $full), filling $result as it goes so the counters survive an
     * exception halfway through.
     */
    public function run(bool $full, SyncResult $result): SyncResult;

    /**
     * Processes the given items again, ignoring the watermark: the replay of
     * a run's failures.
     *
     * @param  list<string>  $itemKeys
     */
    public function retry(array $itemKeys, SyncResult $result): SyncResult;
}
