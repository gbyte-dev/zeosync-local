<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $activeShop = $this->resolveShopDomain($request);

        // ✅ only set if found (overwrite mat kar)
        if ($activeShop && session('active_shop') !== $activeShop) {
            session(['active_shop' => $activeShop]);
        }

        // ✅ request attribute (important for services)
        if ($activeShop) {
            $request->attributes->set('active_shop', $activeShop);
        }

        // ✅ global view share
        View::share('activeShop', $activeShop);

        // 🔍 debug (temporary)
        \Log::info('RESOLVE SHOP', [
            'query_shop' => $request->get('shop'),
            'host' => $request->get('host'),
            'session_shop' => session('active_shop'),
            'resolved' => $activeShop,
            'url' => $request->fullUrl()
        ]);

        return $next($request);
    }

    private function resolveShopDomain(Request $request): ?string
    {
        // =========================
        // 1. Direct query (?shop=)
        // =========================
        $shop = $request->query('shop') ?? $request->input('shop');
        
        if ($shop) {
            return $this->normalizeShop($shop);
        }
        if(session('active_shop')){
            $shop = session('active_shop');
             return $this->normalizeShop($shop);
        }
 
        // =========================
        // 2. Shopify host param
        // =========================
        $host = $request->query('host') ?? $request->input('host');

        if ($host) {
            $decoded = $this->decodeShopifyHost($host);

            if ($decoded && preg_match('#/store/([^/?]+)#i', $decoded, $matches)) {
                return $this->normalizeShop($matches[1]);
            }
        }

        // =========================
        // 3. Referer fallback (IMPORTANT)
        // =========================
        $referer = $request->headers->get('referer');

        if ($referer && preg_match('#/store/([^/?]+)#i', $referer, $matches)) {
            return $this->normalizeShop($matches[1]);
        }

        // =========================
        // 4. Session fallback
        // =========================
        if (session()->has('active_shop')) {
            return $this->normalizeShop(session('active_shop'));
        }

        return null;
    }

    private function normalizeShop(?string $shop): ?string
    {
        $shop = strtolower(trim((string) $shop));

        if (!$shop) return null;

        if (!str_contains($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        return $shop;
    }

    private function decodeShopifyHost(?string $host): ?string
    {
        $host = trim((string) $host);

        if (!$host) return null;

        $padding = strlen($host) % 4;
        if ($padding > 0) {
            $host .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($host, '-_', '+/'), true);

        return $decoded !== false ? $decoded : null;
    }
}ए