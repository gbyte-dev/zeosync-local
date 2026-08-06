<?php

namespace App\Services;

use App\Models\Shop;

class EmailDataHelper
{
    public static function build(array $context = []): array
    {
        //  Get active shop from session
        $shopDomain = session('active_shop');

        //  Cache shop (performance)
        $shop = $shopDomain
            ? cache()->remember("shop_{$shopDomain}", 300, function () use ($shopDomain) {
                return Shop::where('shop', $shopDomain)->first();
            })
            : null;

        // Customer (optional)
        $customer = $context['customer'] ?? null;

        return [

            // CUSTOMER VARIABLES
            'customer_first_name' => self::getFirstName($customer),

            //  SHOP VARIABLES
            'shop_name' => self::getShopName($shop),

            'amazon_connect_url' => $customer->amazon_connect_url ?? '',

            //  HARDCODED SUPPORT EMAIL
            'support_email' => 'brijeshverma7814@gmail.com',

            'logo_url' => asset('public/images/AmazonSync_logo.png'),
            'name' => $context['name'] ?? '',

            'email' => $context['email'] ?? '',

            'subject' => $context['subject'] ?? '',

            'message' => $context['message'] ?? '',

            'enquiry_type' => $context['enquiry_type'] ?? '',

        ];
    }

    /*
    |--------------------------------------------------------------------------
    |  Helper Methods
    |--------------------------------------------------------------------------
    */

    private static function getFirstName($customer): string
    {
        if (!$customer) return '';

        if (!empty($customer->first_name)) {
            return trim($customer->first_name);
        }

        if (!empty($customer->name)) {
            return explode(' ', trim($customer->name))[0];
        }

        return '';
    }

    private static function getShopName($shop): string
    {
        if (!$shop || empty($shop->shop)) {
            return 'Your App';
        }

        return explode('.', $shop->shop)[0];
    }
}
