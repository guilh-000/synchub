<?php

namespace App\Http\Controllers;

use App\Enums\SyncRunStatus;
use App\Http\Resources\SyncRunResource;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\SyncCursor;
use App\Models\SyncMapping;
use App\Models\SyncRun;
use App\Sync\Orders\OrderImport;
use App\Sync\Products\ProductSync;
use App\Sync\Stock\StockSync;
use App\Sync\Sync;
use App\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    /** Which mappings count as "mapped" for each sync. */
    private const array MAPPED_ENTITY = [
        ProductSync::KEY => SyncMapping::PRODUCT,
        StockSync::KEY => SyncMapping::STOCK,
        OrderImport::KEY => SyncMapping::ORDER,
    ];

    public function __invoke(SyncRegistry $syncs, WooCommerceClient $store): Response
    {
        return Inertia::render('dashboard', [
            'syncs' => array_map($this->overview(...), $syncs->all()),
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
     * @return array<string, mixed>
     */
    private function overview(Sync $sync): array
    {
        $lastRun = SyncRun::query()->where('sync', $sync->key())->latest('id')->first();
        $entity = self::MAPPED_ENTITY[$sync->key()] ?? null;

        return [
            'key' => $sync->key(),
            'title' => $sync->title(),
            'direction' => $sync->direction(),
            'schedule' => $sync->key() === OrderImport::KEY ? 'Every 2 minutes' : 'Every 5 minutes',
            'watermark' => SyncCursor::query()->where('name', $sync->key())->value('changed_at'),
            'mapped' => $entity === null ? 0 : SyncMapping::query()->where('entity', $entity)->count(),
            'running' => $lastRun?->status === SyncRunStatus::Running,
            'last_run' => $lastRun === null ? null : SyncRunResource::make($lastRun)->resolve(),
        ];
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
