<?php

namespace App\Http\Middleware;

use App\Services\StoreStatusService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckStoreStatus
{
    protected StoreStatusService $storeStatusService;

    public function __construct()
    {
        $this->storeStatusService = app(StoreStatusService::class);
    }

    public function handle(Request $request, Closure $next)
    {
        Log::info('CHECK STORE STATUS MIDDLEWARE HIT', [
            'route' => optional($request->route())->getName(),
            'url'   => $request->fullUrl(),
        ]);

        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {

            Log::warning('NO ACTIVE SHOP FOUND');

            return $next($request);
        }

        Log::info('SHOP FOUND', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);

        if (!$shop->needsStatusCheck()) {

            Log::info('STORE STATUS CHECK SKIPPED', [
                'last_checked' => $shop->last_status_check_at,
            ]);

            return $next($request);
        }

        Log::info('STORE STATUS NEEDS TO BE CHECKED', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);

        try {

            $this->storeStatusService->check($shop);
        } catch (\Throwable $e) {

            Log::error('STORE STATUS CHECK FAILED', [
                'shop_id' => $shop->id,
                'shop'    => $shop->shop,
                'message' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
