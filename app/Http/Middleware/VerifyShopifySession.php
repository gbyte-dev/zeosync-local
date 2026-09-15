<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopify\App\ShopifyApp;
use App\Models\AdminSetting;

class VerifyShopifySession
{
    public function handle(Request $request, Closure $next)
    {
        $apikey = AdminSetting::get('SHOPIFY_API_KEY', '');
        $apisecret = AdminSetting::get('SHOPIFY_API_SECRET', '');

        try{
            $shopify = new ShopifyApp($apikey, $apisecret );
            $result = $shopify->verifyAppHomeReq($request);
            dd($result);
        }catch(\Exception $e){
            return response()->json(['error' => 'Invalid Shopify session: ' . $e->getMessage()], 401);
        }


        if (!$result->ok) {
            // Returns clean JSON, not a redirect — this is the fix for your loop
            return $result->response;
        }

        $request->attributes->set('shopify_shop', $result->shop);
        $request->attributes->set('shopify_result', $result);

        return $next($request);
    }
}