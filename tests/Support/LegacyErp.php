<?php

namespace Tests\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy ERP's PRODUTO table on in-memory SQLite, for tests.
 */
final class LegacyErp
{
    public static function boot(): void
    {
        config(['database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
        DB::purge('legacy');

        Schema::connection('legacy')->create('PRODUTO', function ($table) {
            $table->integer('CODPROD')->primary();
            $table->string('REFERENCIA', 30);
            $table->string('DESCRICAO', 120);
            $table->decimal('PRECO_VENDA', 12, 2);
            $table->decimal('PESO_KG', 10, 3)->nullable();
            $table->char('ATIVO', 1)->default('S');
            $table->dateTime('DT_ALT');
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function product(int $code, array $overrides = []): void
    {
        DB::connection('legacy')->table('PRODUTO')->insert($overrides + [
            'CODPROD' => $code,
            'REFERENCIA' => sprintf('SKU-%05d', $code),
            'DESCRICAO' => "Product {$code}",
            'PRECO_VENDA' => '10.00',
            'PESO_KG' => '0.500',
            'ATIVO' => 'S',
            'DT_ALT' => '2026-01-01 08:00:00',
        ]);
    }

    /**
     * Changes a product the way the ERP does: the new values plus a fresh DT_ALT.
     *
     * @param  array<string, mixed>  $values
     */
    public static function change(int $code, array $values, string $at): void
    {
        DB::connection('legacy')->table('PRODUTO')
            ->where('CODPROD', $code)
            ->update($values + ['DT_ALT' => Carbon::parse($at)->format('Y-m-d H:i:s')]);
    }
}
