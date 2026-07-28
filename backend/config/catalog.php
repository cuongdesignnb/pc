<?php

return [
    'storefront_url' => env('CATALOG_STOREFRONT_URL', env('FRONTEND_URL', env('APP_URL'))),
    'feed_disk' => env('CATALOG_FEED_DISK', 'local'),
    'feed_directory' => 'catalog-feeds',
    'feed_cache_seconds' => (int) env('CATALOG_FEED_CACHE_SECONDS', 900),
    'sync_chunk_size' => (int) env('CATALOG_SYNC_CHUNK_SIZE', 250),
    'sync_lock_seconds' => (int) env('CATALOG_SYNC_LOCK_SECONDS', 1800),
    'google_sheets' => [
        'enabled' => env('GOOGLE_SHEETS_ENABLED', false),
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'worksheet' => env('GOOGLE_SHEETS_WORKSHEET', 'Products'),
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'connect_timeout_seconds' => 5,
        'request_timeout_seconds' => 20,
        'max_attempts' => 4,
    ],
    'google_merchant' => [
        'enabled' => env('GOOGLE_MERCHANT_ENABLED', false),
        'artifact' => 'google-products.xml',
    ],
    'meta_catalog' => [
        'enabled' => env('META_CATALOG_ENABLED', false),
        'artifact' => 'meta-products.csv',
    ],
];
