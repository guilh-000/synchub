<?php

use App\Jobs\RunSync;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RunSync('products'))->everyFiveMinutes();
Schedule::job(new RunSync('stock'))->everyFiveMinutes();
Schedule::job(new RunSync('orders'))->everyTwoMinutes();
