<?php

return [
    'client_id' => env('AMAZON_CLIENT_ID'),
    'client_secret' => env('AMAZON_CLIENT_SECRET'),
    'refresh_token' => env('AMAZON_REFRESH_TOKEN'),
    'aws_access_key_id' => env('AWS_ACCESS_KEY_ID'),
    'aws_secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
    'seller_id' => env('AMAZON_SELLER_ID'),
    'app_id' => env('AMAZON_APP_ID'),

    'payload_transformer' => env(
        'AMAZON_PAYLOAD_TRANSFORMER',
        'v2'
    ),

];
