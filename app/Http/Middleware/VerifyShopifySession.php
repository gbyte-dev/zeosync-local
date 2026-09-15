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
        DB::enableQueryLog(); // Enable query logging for debugging
        $apikey = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_KEY')->first()->option_value; // Example query to log
        $apisecret = DB::table('admin_settings')->where('option_key', 'SHOPIFY_API_SECRET')->first()->option_value; // Example query to log
        $queries = DB::getQueryLog(); // Get the logged queries

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
                return response()->json(['error' => 'Invalid Shopify session: ' . $e->getMessage()], 401);
            }
            
            return $next($request);
        }


        if (!$result->ok) {
            // Returns clean JSON, not a redirect — this is the fix for your loop
            return $next($request);
        }
        $idToken = $result->idToken; 
        $verifiedShopDomain = $result->shop.'myshopify.com';

        $shop = Shop::where('shop', $verifiedShopDomain)->first();
        // if (!$shop) {
        //     $shop = new Shop();
        //     $shop->shop = $verifiedShopDomain;
        //     $shop->installed_at = now();
        //     $shop->access_token = $result->accessToken;
        //     $shop->save();
        // }elseif(empty($shop->store_status !='active') && $shop->access_token != $result->accessToken) {
        //     $shop->access_token = $result->accessToken;
        //     $shop->save();
        // }

        echo "<pre>";
         print_r($result);
         
         die;
        $request->attributes->set('shopify_id_token', $idToken);
        $request->attributes->set('shopify_shop', $result->shop);
        $request->attributes->set('shopify_result', $result);

        return $next($request);
    }
}