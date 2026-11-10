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
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            // Position of the last record processed, in the source's order
            // (change timestamp, then key): the next run starts after it.
            $table->dateTime('changed_at')->nullable();
            $table->string('last_key', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_cursors');
    }
};
