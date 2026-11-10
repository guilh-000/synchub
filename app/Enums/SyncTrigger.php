<?php

namespace App\Enums;

/**
 * What started a sync run.
 */
enum SyncTrigger: string
{
    case Schedule = 'schedule';
    case Manual = 'manual';
    case Console = 'console';
}
