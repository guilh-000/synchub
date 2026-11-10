<?php

use App\Enums\SyncTrigger;
use App\Jobs\RunSync;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

beforeEach(fn () => LegacyErp::boot());

test('the command runs a sync, records it and prints the counters', function () {
    FakeStore::fake();
    LegacyErp::product(1);

    $this->artisan('sync:run products')
        ->expectsTable(['Read', 'Created', 'Updated', 'Skipped', 'Failed'], [[1, 1, 0, 0, 0]])
        ->assertSuccessful();

    expect(SyncRun::query()->sole())
        ->sync->toBe('products')
        ->trigger->toBe(SyncTrigger::Console);
});

test('the command fails when the store is down', function () {
    FakeStore::configure();
    Http::fake(['store.test/*' => Http::response('down', 503)]);
    Sleep::fake();
    LegacyErp::product(1);

    $this->artisan('sync:run products')->assertFailed();
});

test('the command rejects an unknown sync', function () {
    $this->artisan('sync:run customers')->assertExitCode(2);
});

test('the command can queue the sync instead', function () {
    Queue::fake();

    $this->artisan('sync:run stock --full --queue')->assertSuccessful();

    Queue::assertPushed(RunSync::class, fn (RunSync $job): bool => $job->sync === 'stock' && $job->full && $job->itemKeys === null);
});
