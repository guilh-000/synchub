<?php

namespace App\Http\Controllers;

use App\Enums\SyncRunStatus;
use App\Http\Resources\SyncRunResource;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Models\SyncRun;
use App\Sync\Products\ProductSync;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(WooCommerceClient $store): Response
    {
        $lastRun = SyncRun::query()->where('sync', ProductSync::CURSOR)->latest('id')->first();

        return Inertia::render('dashboard', [
            'syncs' => [[
                'key' => ProductSync::CURSOR,
                'title' => 'Products',
                'direction' => 'ERP → store',
                'schedule' => 'Every 5 minutes',
                'watermark' => SyncCursor::query()->where('name', ProductSync::CURSOR)->value('changed_at'),
                'mapped' => SyncMapping::query()->where('entity', SyncMapping::PRODUCT)->count(),
                'running' => $lastRun?->status === SyncRunStatus::Running,
                'last_run' => $lastRun === null ? null : SyncRunResource::make($lastRun)->resolve(),
            ]],
            'recentRuns' => SyncRunResource::collection(SyncRun::query()->latest('id')->limit(8)->get())->resolve(),
            // Checking both systems costs a query and an HTTP call: loaded
            // after the page renders, so a slow store never blocks it.
            'health' => Inertia::defer(fn (): array => [
                'erp' => $this->check(fn (): string => DB::connection('legacy')->table('PRODUTO')->count().' products'),
                'store' => $this->check(fn (): string => $store->productCount().' products'),
            ]),
        ]);
    }

    /**
     * @param  callable(): string  $probe
     * @return array{ok: bool, detail: string}
     */
    private function check(callable $probe): array
    {
        try {
            return ['ok' => true, 'detail' => $probe()];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'detail' => $exception->getMessage()];
        }
    }
}
