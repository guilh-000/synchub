<?php

use App\Sync\Orders\OrderImport;
use App\Sync\Products\ProductSync;
use App\Sync\Stock\StockSync;

return [

    /*
    |--------------------------------------------------------------------------
    | Syncs
    |--------------------------------------------------------------------------
    |
    | Every sync SyncHub runs, by key. Order matters on the dashboard and for
    | a first run: products must exist in the store before their stock.
    |
    */

    'syncs' => [
        'products' => ProductSync::class,
        'stock' => StockSync::class,
        'orders' => OrderImport::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | WooCommerce store
    |--------------------------------------------------------------------------
    |
    | In the Docker stack `shop-init` writes the credentials to
    | `credentials_file`; the env variables take precedence when set.
    |
    */

    'woocommerce' => [
        'base_url' => env('WOOCOMMERCE_URL'),
        'username' => env('WOOCOMMERCE_USERNAME'),
        'password' => env('WOOCOMMERCE_APP_PASSWORD'),
        'credentials_file' => storage_path('app/private/woocommerce.json'),
        'timeout' => (int) env('WOOCOMMERCE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product sync
    |--------------------------------------------------------------------------
    |
    | batch_size: products per request (WooCommerce accepts up to 100).
    | overlap_seconds: each run rereads this window before the watermark, so
    | a row changed within the same second as the last one read is not lost.
    | Unchanged rows in the window are skipped by their payload hash.
    |
    */

    'products' => [
        'batch_size' => (int) env('SYNC_PRODUCTS_BATCH_SIZE', 100),
        'overlap_seconds' => (int) env('SYNC_PRODUCTS_OVERLAP_SECONDS', 5),
    ],

    'stock' => [
        'batch_size' => (int) env('SYNC_STOCK_BATCH_SIZE', 100),
        'overlap_seconds' => (int) env('SYNC_STOCK_OVERLAP_SECONDS', 5),
    ],

    /*
    | Paid orders only: the ERP should not see carts that were never paid.
    */

    'orders' => [
        'statuses' => ['processing', 'completed'],
        'page_size' => (int) env('SYNC_ORDERS_PAGE_SIZE', 50),
        'overlap_seconds' => (int) env('SYNC_ORDERS_OVERLAP_SECONDS', 60),
    ],

];
