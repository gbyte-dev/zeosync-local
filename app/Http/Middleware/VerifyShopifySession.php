<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopify\App\ShopifyApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class VerifyShopifySession
{
    public function handle(Request $request, Closure $next)
    {
        DB::enableQueryLog(); // Enable query logging for debugging
        $apikey = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_KEY')->first()->option_value; // Example query to log
        $apisecret = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_SECRET')->first()->option_value; // Example query to log
        $queries = DB::getQueryLog(); // Get the logged queries

        $apikey = Crypt::decryptString($apikey);
        $apisecret = Crypt::decryptString($apisecret);
        dd($apikey, $apisecret, $queries); // Dump the API key, secret, and queries for debugging

        $shopify = new ShopifyApp(
            config('shopify.api_key'),
            config('shopify.api_secret')
        );

        $result = $shopify->verifyAppHomeReq($request);

        if (!$result->ok) {
            // Returns clean JSON, not a redirect — this is the fix for your loop
            return $result->response;
        }

        $request->attributes->set('shopify_shop', $result->shop);
        $request->attributes->set('shopify_result', $result);

        return $next($request);
    }
}