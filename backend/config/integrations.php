<?php

return [
    'kiot' => [
        'enabled' => env('KIOT_INTEGRATION_ENABLED', false),
        'product_sync_enabled' => env('KIOT_PRODUCT_SYNC_ENABLED', false),
        'order_sync_enabled' => env('KIOT_ORDER_SYNC_ENABLED', false),
        'base_url' => env('KIOT_INTEGRATION_BASE_URL'),
        'client_id' => env('KIOT_INTEGRATION_CLIENT_ID', 'pc-website'),
        'secret' => env('KIOT_INTEGRATION_SECRET'),
        'api_version' => env('KIOT_API_VERSION', 'v1'),
        'connect_timeout_seconds' => env('KIOT_CONNECT_TIMEOUT_SECONDS', 3),
        'request_timeout_seconds' => env('KIOT_REQUEST_TIMEOUT_SECONDS', 10),
        'product_sync_limit' => env('KIOT_PRODUCT_SYNC_LIMIT', 100),
        'product_sync_overlap_seconds' => env('KIOT_PRODUCT_SYNC_OVERLAP_SECONDS', 120),
        'product_stale_after_minutes' => env('KIOT_PRODUCT_STALE_AFTER_MINUTES', 15),
        'sync_lock_seconds' => env('KIOT_SYNC_LOCK_SECONDS', 3600),
        'image_disk' => env('KIOT_IMAGE_DISK', 'public'),
        'image_max_bytes' => env('KIOT_IMAGE_MAX_BYTES', 8 * 1024 * 1024),
        'image_connect_timeout_seconds' => env('KIOT_IMAGE_CONNECT_TIMEOUT_SECONDS', 3),
        'image_timeout_seconds' => env('KIOT_IMAGE_TIMEOUT_SECONDS', 15),
        'image_allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('KIOT_IMAGE_ALLOWED_HOSTS', '')),
        ))),
        'outbox_max_attempts' => env('KIOT_OUTBOX_MAX_ATTEMPTS', 10),
        'outbox_retry_base_seconds' => env('KIOT_OUTBOX_RETRY_BASE_SECONDS', 30),
    ],
];
