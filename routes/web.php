<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RunSyncController;
use App\Http\Controllers\SyncRunController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('runs', [SyncRunController::class, 'index'])->name('runs.index');
    Route::get('runs/{run}', [SyncRunController::class, 'show'])->name('runs.show');

    Route::post('syncs/products/run', RunSyncController::class)->name('syncs.products.run');
});

require __DIR__.'/settings.php';
