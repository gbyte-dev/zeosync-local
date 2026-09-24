<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopify\App\ShopifyApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use App\Models\Shop;
use App\Models\AdminSetting;

class VerifyShopifySession
{
    public function handle(Request $request, Closure $next)
    {
        $apikey = AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key'));
        $apisecret = AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret'));

        if (empty($apikey) || empty($apisecret)) {
            return $next($request);
        }

        try{
            if (!class_exists('Shopify\App\ShopifyApp')) {
                return $next($request);
            }
            $shopify = new ShopifyApp($apikey, $apisecret );
            $req = [
                'url'     => $request->fullUrl(),
                'headers' => $request->headers->all(),
            ];
            $result = $shopify->verifyAppHomeReq($req , '/api/shopify/patch-id-token');
        }catch(\Throwable $e){
            if($request->expectsJson() || $request->ajax()){
                 return $next($request);
                // return response()->json(['error' => 'Invalid Shopify session: ' . $e->getMessage()], 401);
            }
            
            return $next($request);
        }
        if (!$result->ok) {
            return $next($request);
        }
        $idToken = $result->idToken; 
        $verifiedShopDomain = $result->shop?$result->shop.'.myshopify.com':session('active_shop');
        $shop = Shop::where('shop', $verifiedShopDomain)->first();

        if (!$shop) {
            $shop = new Shop();
            $shop->shop = $verifiedShopDomain;
            $shop->installed_at = now();
            $shop->save();
        }

        $request->attributes->set('shopify_id_token', $idToken);
        $request->attributes->set('shopify_shop', $result->shop);
        $request->attributes->set('shopify_result', $result);

        return redirect()->route('dashboard', ['shop' => $shop->shop ])->with('success', 'Welcome '. $shop->shop . '.');

        // return $next($request);
    }
}
