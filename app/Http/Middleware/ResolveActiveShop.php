<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ResolveActiveShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $activeShop = $this->resolveShopDomain($request);

        if ($request->ajax() || $request->expectsJson()) {

            if ($activeShop) {

                $shop = Shop::where('shop', $activeShop)
                    ->where('is_active', 1)
                    ->first();

                if ($shop) {
                    $request->attributes->set('active_shop', $shop->shop);
                    $request->attributes->set('active_shop_model', $shop);
                }
            }

            return $next($request);
        }

        Log::info('MIDDLEWARE START', [
            'route' => optional($request->route())->getName(),
            'url'   => $request->fullUrl(),
        ]);

        if (
            $request->routeIs('crm.entry') ||
            $request->routeIs('shopify.install') ||
            $request->routeIs('shopify.callback') ||
            $request->routeIs('setup.form')
        ) {

            Log::info('BYPASS ROUTE', [
                'route' => optional($request->route())->getName(),
                'url'   => $request->fullUrl(),
            ]);

            if ($activeShop) {

                $request->attributes->set('active_shop', $activeShop);

                session([
                    'active_shop' => $activeShop,
                ]);
            }

            View::share('activeShop', $activeShop);

            return $next($request);
        }

        if (!$activeShop && session()->has('active_shop')) {

            $activeShop = session('active_shop');

            Log::info('SHOP RESTORED FROM SESSION', [
                'shop' => $activeShop,
            ]);
        }

        if (!$activeShop) {

            Log::warning('NO SHOP IN REQUEST', [
                'url' => $request->fullUrl(),
            ]);

            View::share('activeShop', null);

            return $next($request);
        }

        $shop = Shop::where('shop', $activeShop)
            ->where('is_active', 1)
            ->first();

        Log::info('SHOP CHECK', [
            'shop'   => $activeShop,
            'exists' => (bool) $shop,
            'token'  => $shop->access_token ?? null,
        ]);

        if (!$shop || empty($shop->access_token)) {

            Log::warning('SHOP NOT INSTALLED → CONTROLLER WILL HANDLE', [
                'shop' => $activeShop,
            ]);

            View::share('activeShop', $activeShop);

            return $next($request);
        }

        $request->attributes->set('active_shop', $shop->shop);
        $request->attributes->set('active_shop_model', $shop);

        session([
            'active_shop'    => $shop->shop,
            'active_shop_id' => $shop->id,
        ]);

        View::share('activeShop', $shop->shop);
        View::share('activeShopModel', $shop);

        Log::info('ACTIVE SHOP SAVED GLOBALLY', [
            'shop_id' => $shop->id,
            'shop'    => $shop->shop,
        ]);

        // if ($shop->needsStatusCheck()) {

        //     app(\App\Services\StoreStatusService::class)->check($shop);
        // } else {

        //     Log::info('STORE STATUS CHECK SKIPPED', [
        //         'shop_id'      => $shop->id,
        //         'shop'         => $shop->shop,
        //         'last_checked' => $shop->last_status_check_at,
        //     ]);
        // }

        return $next($request);
    }

    private function resolveShopDomain(Request $request): ?string
    {
        // =========================================================
        // 1. Shopify HOST (PRIMARY SOURCE)
        // =========================================================

        if ($request->has('host')) {

            $decoded = $this->decodeShopifyHost($request->get('host'));

            \Log::info('HOST DETECTED', [
                'raw_host' => $request->get('host'),
                'decoded'  => $decoded,
            ]);

            // admin.shopify.com/store/xyz

            if (preg_match('#/store/([a-z0-9-]+)#i', $decoded, $matches)) {
                return strtolower($matches[1] . '.myshopify.com');
            }

            // xyz.myshopify.com

            if (preg_match('#^([a-z0-9-]+)\.myshopify\.com#i', $decoded, $matches)) {
                return strtolower($matches[1] . '.myshopify.com');
            }

            // xyz.myshopify.com/admin

            if (preg_match('#([a-z0-9-]+)\.myshopify\.com#i', $decoded, $matches)) {
                return strtolower($matches[1] . '.myshopify.com');
            }
        }

        // =========================================================
        // 2. Query Parameter
        // =========================================================

        $shop = $request->query('shop');

        if ($shop) {

            $shop = strtolower(trim($shop));

            if (!str_contains($shop, '.myshopify.com')) {
                $shop .= '.myshopify.com';
            }

            \Log::info('SHOP FROM QUERY', [
                'shop' => $shop,
            ]);

            return $shop;
        }

        // =========================================================
        // 3. Session Fallback
        // =========================================================

        if (session()->has('active_shop')) {

            \Log::info('SHOP FROM SESSION', [
                'shop' => session('active_shop'),
            ]);

            return session('active_shop');
        }

        return null;
    }

    private function decodeShopifyHost(?string $host): ?string
    {
        $host = trim((string) $host);

        if ($host === '') {
            return null;
        }

        $padding = strlen($host) % 4;

        if ($padding > 0) {
            $host .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($host, '-_', '+/'), true);

        return $decoded !== false ? $decoded : $host;
    }
}
