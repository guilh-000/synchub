<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_mappings', function (Blueprint $table) {
            $table->id();
            // What is mapped ("product", later "order") and its key on each side.
            $table->string('entity', 30);
            $table->string('legacy_key', 50);
            $table->unsignedBigInteger('store_id');
            // SHA-256 of the last payload sent: an unchanged record is skipped.
            $table->char('payload_hash', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['entity', 'legacy_key']);
            $table->unique(['entity', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_mappings');
    }
};
