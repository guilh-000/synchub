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

        Schema::connection('legacy')->create('ESTOQUE', function ($table) {
            $table->integer('CODPROD');
            $table->smallInteger('CODLOCAL');
            $table->decimal('QTD', 12, 3);
            $table->dateTime('DT_ALT');
            $table->primary(['CODPROD', 'CODLOCAL']);
        });

        Schema::connection('legacy')->create('PEDIDO', function ($table) {
            $table->increments('NUMPED');
            $table->string('ORIGEM', 10);
            $table->string('NUM_EXTERNO', 30)->nullable();
            $table->string('CLIENTE_NOME', 100);
            $table->string('CLIENTE_DOC', 18)->nullable();
            $table->dateTime('DT_EMISSAO');
            $table->decimal('VL_TOTAL', 12, 2);
            $table->char('SITUACAO', 1);
            $table->dateTime('DT_ALT')->nullable();
        });

        Schema::connection('legacy')->create('PEDIDO_ITEM', function ($table) {
            $table->integer('NUMPED');
            $table->smallInteger('SEQ');
            $table->integer('CODPROD');
            $table->decimal('QTD', 12, 3);
            $table->decimal('VL_UNIT', 12, 2);
            $table->primary(['NUMPED', 'SEQ']);
        });
    }

    public static function stock(int $code, int $warehouse, float $quantity, string $at = '2026-01-01 08:00:00'): void
    {
        DB::connection('legacy')->table('ESTOQUE')->updateOrInsert(
            ['CODPROD' => $code, 'CODLOCAL' => $warehouse],
            ['QTD' => $quantity, 'DT_ALT' => $at],
        );
    }

    /**
     * @return list<object>
     */
    public static function orders(): array
    {
        return DB::connection('legacy')->table('PEDIDO')->orderBy('NUMPED')->get()->all();
    }

    /**
     * @return list<object>
     */
    public static function orderItems(int $number): array
    {
        return DB::connection('legacy')->table('PEDIDO_ITEM')->where('NUMPED', $number)->orderBy('SEQ')->get()->all();
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
