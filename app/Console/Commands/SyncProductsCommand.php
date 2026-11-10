<?php

namespace App\Console\Commands;

use App\Enums\SyncTrigger;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Jobs\SyncProducts;
use App\Sync\Products\ProductSync;
use App\Sync\Products\SyncResult;
use App\Sync\SyncRunRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:products {--full : Compare every product, ignoring the watermark} {--queue : Dispatch to the queue instead of running now}')]
#[Description('Send products changed in the legacy ERP to the store')]
class SyncProductsCommand extends Command
{
    public function handle(ProductSync $sync, SyncRunRecorder $recorder): int
    {
        $full = (bool) $this->option('full');

        if ($this->option('queue')) {
            SyncProducts::dispatch($full, SyncTrigger::Console);
            $this->components->info('Product sync queued.');

            return self::SUCCESS;
        }

        try {
            $run = $recorder->record(ProductSync::CURSOR, SyncTrigger::Console, $full, fn (SyncResult $result): SyncResult => $sync->run($full, $result));
        } catch (WooCommerceException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Read', 'Created', 'Updated', 'Skipped', 'Failed'], [[$run->read, $run->created, $run->updated, $run->skipped, $run->failed]]);

        foreach ($run->failures as $failure) {
            $this->components->warn("Product {$failure->legacy_key}: {$failure->message}");
        }

        return $run->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
