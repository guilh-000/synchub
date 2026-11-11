<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RetrySyncRunController;
use App\Http\Controllers\RunSyncController;
use App\Http\Controllers\SyncRunController;
use Illuminate\Support\Facades\Route;

// No marketing page: the app is the dashboard (guests land on the login).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('runs', [SyncRunController::class, 'index'])->name('runs.index');
    Route::get('runs/{run}', [SyncRunController::class, 'show'])->name('runs.show');
    Route::post('runs/{run}/retry', RetrySyncRunController::class)->name('runs.retry');

    Route::post('syncs/{sync}/run', RunSyncController::class)->name('syncs.run');
});

require __DIR__.'/settings.php';
