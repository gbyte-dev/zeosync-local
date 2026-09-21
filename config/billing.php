<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Provider
    |--------------------------------------------------------------------------
    |
    | Controls which billing provider is used for plan subscriptions.
    |
    | For this app, Shopify billing is required for App Store compliance.
    | We intentionally keep the provider locked to 'shopify' and do not permit
    | legacy Stripe checkout overrides for app subscriptions.
    |
    */

    //'provider' => env('BILLING_PROVIDER', 'shopify'),
    'provider' => 'shopify',
    'providers' => [
        'stripe' => [
            'label' => 'Stripe',
        ],
        'shopify' => [
            'label' => 'Shopify',
        ],
    ],

];