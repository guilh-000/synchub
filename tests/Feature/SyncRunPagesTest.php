<?php

use App\Enums\SyncRunStatus;
use App\Jobs\RunSync;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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
    $run->failures()->create(['item_key' => '41', 'message' => 'Invalid price.']);

    $this->get(route('runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page
            ->component('runs/show')
            ->where('run.id', $run->id)
            ->where('run.status', 'failed')
            ->where('run.error', 'Store down.')
            ->where('run.failures.0.item_key', '41'));
});

test('the failed items of a run can be retried as a new run linked to it', function () {
    Queue::fake();
    $run = SyncRun::factory()->create(['sync' => 'stock', 'status' => SyncRunStatus::Partial, 'failed' => 3]);
    $run->failures()->createMany([
        ['item_key' => '12', 'message' => 'Product is not in the store yet.'],
        ['item_key' => '7', 'message' => 'Product is not in the store yet.'],
        ['item_key' => '12', 'message' => 'Product is not in the store yet.'],
    ]);

    $this->from(route('runs.show', $run))
        ->post(route('runs.retry', $run))
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(RunSync::class, fn (RunSync $job): bool => $job->sync === 'stock'
        && $job->itemKeys === ['12', '7']
        && $job->retryOfId === $run->id);
});

test('a run without failures has nothing to retry', function () {
    Queue::fake();
    $run = SyncRun::factory()->create();

    $this->post(route('runs.retry', $run));

    Queue::assertNothingPushed();
});

test('a run links to the retries of its failures', function () {
    $run = SyncRun::factory()->create(['status' => SyncRunStatus::Partial]);
    $retry = SyncRun::factory()->create(['retry_of_id' => $run->id]);

    $this->get(route('runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page->where('run.retries.0.id', $retry->id));
});
