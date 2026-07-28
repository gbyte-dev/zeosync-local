<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopService
{
    public function getActiveShop(?Request $request = null): ?Shop
    {
        $request = $request ?? request();


        $shopDomain = $request->get('shop');

        Log::info('SHOP SERVICE STEP 1', [
            'query_shop' => $shopDomain,
            'full_url' => $request->fullUrl()
        ]);

        //  STEP 2: fallback via host (IMPORTANT for embedded apps)
        if (!$shopDomain && $request->has('host')) {
            $host = $request->get('host');
            $decoded = base64_decode($host);

            Log::info('SHOP SERVICE STEP 2 (HOST)', [
                'host' => $host,
                'decoded' => $decoded
            ]);

            if (preg_match('/store\/([a-z0-9\-]+)/', $decoded, $matches)) {
                $shopDomain = $matches[1] . '.myshopify.com';
            }
        }

        Log::info('SHOP SERVICE STEP 3 FINAL SHOP', [
            'shop' => $shopDomain
        ]);


        if (!$shopDomain) {
            Log::warning('SHOP SERVICE FAILED: NO SHOP FOUND');
            return null;
        }

        // 🔍 STEP 4: DB lookup
        $shop = Shop::where('shop', $shopDomain)->first();

        Log::info('SHOP SERVICE STEP 4 DB RESULT', [
            'found' => $shop ? true : false,
            'shop_id' => $shop->id ?? null
        ]);

        return $shop;
    }
}
