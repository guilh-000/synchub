<?php

return [

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

];
