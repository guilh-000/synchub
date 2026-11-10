<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders fail by the store's order ID, not an ERP code: the failure's key is
 * now "the item's key on the side it was read from". A retry run points at
 * the run whose failures it replays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_run_failures', function (Blueprint $table) {
            $table->renameColumn('legacy_key', 'item_key');
        });

        Schema::table('sync_runs', function (Blueprint $table) {
            $table->foreignId('retry_of_id')->nullable()->after('full')->constrained('sync_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sync_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retry_of_id');
        });

        Schema::table('sync_run_failures', function (Blueprint $table) {
            $table->renameColumn('item_key', 'legacy_key');
        });
    }
};
