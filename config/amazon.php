<?php

return [
    'client_id' => env('AMAZON_CLIENT_ID'),
    'client_secret' => env('AMAZON_CLIENT_SECRET'),
    'refresh_token' => env('AMAZON_REFRESH_TOKEN'),
    'aws_access_key_id' => env('AWS_ACCESS_KEY_ID'),
    'aws_secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
    'seller_id' => env('AMAZON_SELLER_ID'),
    'app_id' => env('AMAZON_APP_ID'),

    'default_marketplace_id' => env('AMAZON_DEFAULT_MARKETPLACE_ID', 'ATVPDKIKX0DER'),
    'marketplaces' => [
        'ATVPDKIKX0DER' => ['region' => 'na', 'currency' => 'USD', 'language' => 'en_US', 'seller_central' => 'https://sellercentral.amazon.com'],
        'A1F83G8C2ARO7P' => ['region' => 'eu', 'currency' => 'GBP', 'language' => 'en_GB', 'seller_central' => 'https://sellercentral-europe.amazon.com'],
    ],

    'payload_transformer' => env(
        'AMAZON_PAYLOAD_TRANSFORMER',
        'v2'
    ),

];
