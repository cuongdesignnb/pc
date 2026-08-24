<?php

return [
    'content' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://modelapi.vn/v1'),
        'wire_api' => env('OPENAI_WIRE_API', 'chat_completions'),
        'model' => env('OPENAI_MODEL', 'gpt-5.5'),
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'high'),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4096),
        'timeout' => (int) env('OPENAI_TIMEOUT_SECONDS', 120),
    ],
    'image' => [
        'api_key' => env('OPENAI_IMAGE_API_KEY'),
        'base_url' => env('OPENAI_IMAGE_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        'quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'timeout' => (int) env('OPENAI_IMAGE_TIMEOUT_SECONDS', 120),
        'max_bytes' => (int) env('OPENAI_IMAGE_MAX_BYTES', 8388608),
    ],
    'limits' => [
        'topic' => 500,
        'keywords' => 1000,
        'existing_content' => 20000,
        'max_image_count' => 10,
        'max_attempts' => 3,
    ],
];
