<?php

use App\Jobs\SyncProducts;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncProducts)->everyFiveMinutes();
