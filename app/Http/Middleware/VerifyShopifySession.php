<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopify\App\ShopifyApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use App\Models\Shop;

class VerifyShopifySession
{
    public function handle(Request $request, Closure $next)
    {
        DB::enableQueryLog(); 
        $apikey = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_KEY')->first()->option_value; // Example query to log
        $apisecret = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_SECRET')->first()->option_value; // Example query to log
        $queries = DB::getQueryLog(); 

        $apikey = Crypt::decryptString($apikey);
        $apisecret = Crypt::decryptString($apisecret);

        try{
            $shopify = new ShopifyApp($apikey, $apisecret );
            $req = [
                'url'     => $request->fullUrl(),
                'headers' => $request->headers->all(),
            ];
            $result = $shopify->verifyAppHomeReq($req , '/api/shopify/patch-id-token');
        }catch(\Exception $e){
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