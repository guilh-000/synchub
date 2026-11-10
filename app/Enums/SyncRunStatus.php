<?php

namespace App\Enums;

enum SyncRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    /** Finished, but the store rejected some items. */
    case Partial = 'partial';
    /** Stopped before the end (store or ERP unreachable, bug). */
    case Failed = 'failed';
}
