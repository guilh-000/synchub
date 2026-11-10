<?php

namespace App\Console\Commands;

use App\Integrations\WooCommerce\WooCommerceException;
use App\Jobs\SyncProducts;
use App\Sync\Products\ProductSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:products {--full : Compare every product, ignoring the watermark} {--queue : Dispatch to the queue instead of running now}')]
#[Description('Send products changed in the legacy ERP to the store')]
class SyncProductsCommand extends Command
{
    public function handle(ProductSync $sync): int
    {
        if ($this->option('queue')) {
            SyncProducts::dispatch((bool) $this->option('full'));
            $this->components->info('Product sync queued.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->run((bool) $this->option('full'));
        } catch (WooCommerceException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Read', 'Created', 'Updated', 'Skipped', 'Failed'], [array_values($result->counters())]);

        foreach ($result->failures as $failure) {
            $this->components->warn("Product {$failure['legacy_key']}: {$failure['message']}");
        }

        return $result->failed() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
