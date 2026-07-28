<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('CHECK SUBSCRIPTION MIDDLEWARE HIT', [
            'route' => optional($request->route())->getName(),
            'url' => $request->fullUrl(),
        ]);

        // 🔥 Global bypass switch
        if (config('app.disable_subscription')) {

            Log::warning('SUBSCRIPTION CHECK DISABLED');

            return $next($request);
        }

        // ResolveActiveShop middleware se attach hua shop model
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {

            Log::warning('NO ACTIVE SHOP FOUND', [
                'route' => optional($request->route())->getName(),
            ]);

            return redirect()->route('plans.index', [
                'shop' => $request->get('shop')
            ]);
        }

        Log::info('SHOP FOUND', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
        ]);

        if (!isSubscriptionActive($shop->id)) {

            Log::warning('SUBSCRIPTION NOT ACTIVE', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
            ]);

            return redirect()->route('plans.index', [
                'shop' => $shop->shop
            ])->with(
                'error',
                'Please activate a subscription plan.'
            );
        }

        Log::info('SUBSCRIPTION ACTIVE', [
            'shop_id' => $shop->id,
        ]);

        return $next($request);
    }
}
