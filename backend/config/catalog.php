<?php

return [
    'storefront_url' => env('SEO_SITE_ORIGIN', env('CATALOG_STOREFRONT_URL', env('FRONTEND_URL', env('APP_URL')))),
    'feed_disk' => env('CATALOG_FEED_DISK', 'local'),
    'feed_directory' => 'catalog-feeds',
    'feed_cache_seconds' => (int) env('CATALOG_FEED_CACHE_SECONDS', 900),
    'sync_chunk_size' => (int) env('CATALOG_SYNC_CHUNK_SIZE', 250),
    'sync_lock_seconds' => (int) env('CATALOG_SYNC_LOCK_SECONDS', 1800),
    'website' => [
        'price_source' => env('CATALOG_WEBSITE_PRICE_SOURCE', 'retail_price'),
        'fallback_policy' => env('CATALOG_WEBSITE_PRICE_FALLBACK', 'none'),
    ],
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
        'test_mode' => env('META_CATALOG_TEST_MODE', true),
        // Taxonomies stay empty unless an operator provides reviewed numeric
        // Meta/Google taxonomy IDs keyed by local category ID or category path.
        'google_product_category_map' => json_decode((string) env('META_GOOGLE_CATEGORY_MAP', '[]'), true) ?: [],
        'fb_product_category_map' => json_decode((string) env('META_FACEBOOK_CATEGORY_MAP', '[]'), true) ?: [],
        // Generic/Unbranded is only allowed for explicitly approved SKUs.
        'unbranded_skus' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('META_UNBRANDED_SKUS', '')),
        ))),
        'unbranded_label' => env('META_UNBRANDED_LABEL', 'Unbranded'),
        'use_confirmed_barcodes_as_gtin' => env('META_USE_CONFIRMED_BARCODES_AS_GTIN', false),
        'shipping' => env('META_CATALOG_SHIPPING', ''),
    ],
];
