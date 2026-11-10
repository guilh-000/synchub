<?php

use App\Jobs\SyncProducts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

beforeEach(fn () => LegacyErp::boot());

test('the command runs the sync and prints the counters', function () {
    FakeStore::fake();
    LegacyErp::product(1);

    $this->artisan('sync:products')
        ->expectsTable(['Read', 'Created', 'Updated', 'Skipped', 'Failed'], [[1, 1, 0, 0, 0]])
        ->assertSuccessful();
});

test('the command fails when the store is down', function () {
    FakeStore::configure();
    Http::fake(['store.test/*' => Http::response('down', 503)]);
    Sleep::fake();
    LegacyErp::product(1);

    $this->artisan('sync:products')->assertFailed();
});

test('the command can queue the sync instead', function () {
    Queue::fake();

    $this->artisan('sync:products --full --queue')->assertSuccessful();

    Queue::assertPushed(SyncProducts::class, fn (SyncProducts $job): bool => $job->full);
});
