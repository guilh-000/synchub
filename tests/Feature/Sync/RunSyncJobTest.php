<?php

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Jobs\RunSync;
use App\Models\SyncRun;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

beforeEach(fn () => LegacyErp::boot());

test('the job runs the sync and records the run', function () {
    FakeStore::fake();
    LegacyErp::product(1);

    RunSync::dispatchSync('products');

    expect(SyncRun::query()->sole())
        ->sync->toBe('products')
        ->trigger->toBe(SyncTrigger::Schedule)
        ->status->toBe(SyncRunStatus::Succeeded)
        ->created->toBe(1);
});

test('a retry job replays only the given items and links to the original run', function () {
    $store = FakeStore::fake();
    LegacyErp::product(1);
    LegacyErp::product(2);
    $original = SyncRun::factory()->create(['status' => SyncRunStatus::Partial]);

    RunSync::dispatchSync('products', false, SyncTrigger::Manual, ['2'], $original->id);

    $retry = SyncRun::query()->whereKeyNot($original->id)->sole();
    expect($retry->retry_of_id)->toBe($original->id)
        ->and($retry->read)->toBe(1)
        ->and($store->batches[0]['create'][0]['sku'])->toBe('SKU-00002');
});

test('one run per sync at a time', function () {
    expect((new RunSync('stock'))->uniqueId())->toBe('stock')
        ->and((new RunSync('stock', false, SyncTrigger::Manual, ['1'], 5))->uniqueId())->toBe('stock');
});
