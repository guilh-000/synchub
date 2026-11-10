<?php

use App\Enums\SyncTrigger;
use App\Jobs\RunSync;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeStore;
use Tests\Support\LegacyErp;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard shows the product sync and the latest runs', function () {
    $this->actingAs(User::factory()->create());
    SyncRun::factory()->count(3)->create();
    $last = SyncRun::factory()->running()->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('syncs.0.key', 'products')
            ->where('syncs.0.running', true)
            ->where('syncs.0.last_run.id', $last->id)
            ->where('syncs.1.key', 'stock')
            ->where('syncs.2.key', 'orders')
            ->where('syncs.2.last_run', null)
            ->has('recentRuns', 4)
            ->missing('health'));
});

test('the health of both systems loads after the page', function () {
    $this->actingAs(User::factory()->create());
    LegacyErp::boot();
    LegacyErp::product(1);
    FakeStore::configure();
    Http::fake(['store.test/*' => Http::response([['id' => 1]], 200, ['X-WP-Total' => '42'])]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('health.erp', ['ok' => true, 'detail' => '1 products'])
                ->where('health.store', ['ok' => true, 'detail' => '42 products'])));
});

test('an unreachable store shows up in the health check instead of breaking the page', function () {
    $this->actingAs(User::factory()->create());
    LegacyErp::boot();
    FakeStore::configure();
    Http::fake(['store.test/*' => Http::response('', 401)]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('health.store.ok', false)
                ->where('health.erp.ok', true)));
});

test('"Run now" queues the sync started from the dashboard', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    $this->from(route('dashboard'))
        ->post(route('syncs.run', 'orders'))
        ->assertRedirect(route('dashboard'));

    Queue::assertPushed(RunSync::class, fn (RunSync $job): bool => $job->sync === 'orders' && $job->trigger === SyncTrigger::Manual && ! $job->full);
});

test('"Run now" answers 404 for an unknown sync', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('syncs.run', 'customers'))->assertNotFound();
});

test('"Run now" does nothing while a sync is running', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());
    SyncRun::factory()->running()->create();

    $this->from(route('dashboard'))->post(route('syncs.run', 'products'));

    Queue::assertNothingPushed();
});
