<?php

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Models\SyncRun;
use App\Sync\SyncResult;
use App\Sync\SyncRunRecorder;

test('a run that finishes cleanly is recorded as succeeded with its counters', function () {
    $run = app(SyncRunRecorder::class)->record('products', SyncTrigger::Manual, false, function (SyncResult $result): void {
        $result->read = 10;
        $result->updated = 4;
        $result->skipped = 6;
    });

    expect($run->refresh())
        ->status->toBe(SyncRunStatus::Succeeded)
        ->trigger->toBe(SyncTrigger::Manual)
        ->read->toBe(10)
        ->updated->toBe(4)
        ->skipped->toBe(6)
        ->finished_at->not->toBeNull();
});

test('rejected items make the run partial and are kept', function () {
    $run = app(SyncRunRecorder::class)->record('products', SyncTrigger::Schedule, false, function (SyncResult $result): void {
        $result->read = 2;
        $result->created = 1;
        $result->fail('7', 'Invalid price.');
    });

    expect($run->refresh()->status)->toBe(SyncRunStatus::Partial)
        ->and($run->failed)->toBe(1)
        ->and($run->failures()->sole()->only(['item_key', 'message']))->toBe(['item_key' => '7', 'message' => 'Invalid price.']);
});

test('a run that dies is recorded as failed, keeping what it did before', function () {
    $recorder = app(SyncRunRecorder::class);

    expect(fn () => $recorder->record('products', SyncTrigger::Schedule, false, function (SyncResult $result): void {
        $result->read = 100;
        $result->created = 100;

        throw new RuntimeException('WooCommerce is unreachable.');
    }))->toThrow(RuntimeException::class);

    expect(SyncRun::query()->sole())
        ->status->toBe(SyncRunStatus::Failed)
        ->error->toBe('WooCommerce is unreachable.')
        ->created->toBe(100);
});
