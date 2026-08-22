<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Provider
    |--------------------------------------------------------------------------
    |
    | Controls which billing provider is used for plan subscriptions.
    | Supported values: 'stripe' | 'shopify'
    |
    | Priority:
    |   1. BILLING_PROVIDER env variable
    |   2. billing_provider admin setting (admin_settings table)
    |   3. Default: 'stripe'
    |
    */

    'provider' => env('BILLING_PROVIDER', 'shopify'),

    'providers' => [
        'stripe' => [
            'label' => 'Stripe',
        ],
        'shopify' => [
            'label' => 'Shopify',
        ],
    ],

];