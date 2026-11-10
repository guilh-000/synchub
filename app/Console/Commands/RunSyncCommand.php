<?php

namespace App\Console\Commands;

use App\Enums\SyncTrigger;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Jobs\RunSync;
use App\Sync\SyncRegistry;
use App\Sync\SyncResult;
use App\Sync\SyncRunRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:run {sync : products, stock or orders} {--full : Ignore the watermark and compare everything} {--queue : Dispatch to the queue instead of running now}')]
#[Description('Run one of the syncs between the legacy ERP and the store')]
class RunSyncCommand extends Command
{
    public function handle(SyncRegistry $syncs, SyncRunRecorder $recorder): int
    {
        $key = (string) $this->argument('sync');
        $full = (bool) $this->option('full');

        if (! $syncs->has($key)) {
            $this->components->error("Unknown sync [{$key}]. Available: ".implode(', ', $syncs->keys()).'.');

            return self::INVALID;
        }

        if ($this->option('queue')) {
            RunSync::dispatch($key, $full, SyncTrigger::Console);
            $this->components->info("Sync [{$key}] queued.");

            return self::SUCCESS;
        }

        $sync = $syncs->get($key);

        try {
            $run = $recorder->record($key, SyncTrigger::Console, $full, fn (SyncResult $result): SyncResult => $sync->run($full, $result));
        } catch (WooCommerceException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Read', 'Created', 'Updated', 'Skipped', 'Failed'], [[$run->read, $run->created, $run->updated, $run->skipped, $run->failed]]);

        foreach ($run->failures as $failure) {
            $this->components->warn("{$failure->item_key}: {$failure->message}");
        }

        return $run->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
