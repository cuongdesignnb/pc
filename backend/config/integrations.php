<?php

return [
    'kiot' => [
        'enabled' => env('KIOT_INTEGRATION_ENABLED', false),
        'product_sync_enabled' => env('KIOT_PRODUCT_SYNC_ENABLED', false),
        'order_sync_enabled' => env('KIOT_ORDER_SYNC_ENABLED', false),
        'base_url' => env('KIOT_INTEGRATION_BASE_URL'),
        'client_id' => env('KIOT_INTEGRATION_CLIENT_ID', 'pc-website'),
        'secret' => env('KIOT_INTEGRATION_SECRET'),
        'connect_timeout_seconds' => env('KIOT_CONNECT_TIMEOUT_SECONDS', 3),
        'request_timeout_seconds' => env('KIOT_REQUEST_TIMEOUT_SECONDS', 10),
        'product_sync_limit' => env('KIOT_PRODUCT_SYNC_LIMIT', 100),
        'product_sync_overlap_seconds' => env('KIOT_PRODUCT_SYNC_OVERLAP_SECONDS', 120),
        'product_stale_after_minutes' => env('KIOT_PRODUCT_STALE_AFTER_MINUTES', 15),
        'outbox_max_attempts' => env('KIOT_OUTBOX_MAX_ATTEMPTS', 10),
        'outbox_retry_base_seconds' => env('KIOT_OUTBOX_RETRY_BASE_SECONDS', 30),
    ],
];
