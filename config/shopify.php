<?php

return [
    'api_key' => env('SHOPIFY_API_KEY','api_key'),
    'api_secret' => env('SHOPIFY_API_SECRET','api_secret'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI','redirect_uri'),
    'app_url' => env('SHOPIFY_APP_URL','app_url'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'app_handle' => env('SHOPIFY_APP','test-app'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET','test_secret'),
    'webhook_topic' => env('SHOPIFY_WEBHOOK_TOPIC', 'APP_UNINSTALLED'),
    'webhook_address' => env('SHOPIFY_WEBHOOK_ADDRESS', '/shopify/webhooks'),
    'webhook_api_version' => env('SHOPIFY_WEBHOOK_API_VERSION', '2026-07'),
    'webhook_retry_count' => env('SHOPIFY_WEBHOOK_RETRY_COUNT', 3),
    'webhook_retry_delay' => env('SHOPIFY_WEBHOOK_RETRY_DELAY', 60),
    'webhook_retry_backoff' => env('SHOPIFY_WEBHOOK_RETRY_BACKOFF', 2),
    'webhook_retry_max_delay' => env('SHOPIFY_WEBHOOK_RETRY_MAX_DELAY', 3600),
    'webhook_retry_jitter' => env('SHOPIFY_WEBHOOK_RETRY_JITTER', 0.1),
];