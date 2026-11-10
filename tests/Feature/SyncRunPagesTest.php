<?php

use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

test('runs are listed newest first', function () {
    $older = SyncRun::factory()->create();
    $newer = SyncRun::factory()->create();

    $this->get(route('runs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('runs/index')
            ->where('runs.data.0.id', $newer->id)
            ->where('runs.data.1.id', $older->id));
});

test('runs can be filtered by status', function () {
    SyncRun::factory()->create();
    $failed = SyncRun::factory()->failed()->create();

    $this->get(route('runs.index', ['status' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('runs.data', 1)
            ->where('runs.data.0.id', $failed->id)
            ->where('filters.status', 'failed'));
});

test('an unknown status filter is rejected', function () {
    $this->get(route('runs.index', ['status' => 'whatever']))->assertSessionHasErrors('status');
});

test('a run shows its counters, error and rejected items', function () {
    $run = SyncRun::factory()->failed('Store down.')->create(['status' => SyncRunStatus::Failed, 'failed' => 1]);
    $run->failures()->create(['legacy_key' => '41', 'message' => 'Invalid price.']);

    $this->get(route('runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page
            ->component('runs/show')
            ->where('run.id', $run->id)
            ->where('run.status', 'failed')
            ->where('run.error', 'Store down.')
            ->where('run.failures.0.legacy_key', '41'));
});
