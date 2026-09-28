<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(16));

        // Make nonce available to Blade views
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        // Apply standard security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($this->isEmbeddedShopifyContext($request)) {
            $response->headers->set(
                'Content-Security-Policy',
                "object-src 'none'; base-uri 'self'; frame-ancestors 'self' https://admin.shopify.com https://*.myshopify.com"
            );
        } else {
            $response->headers->set(
                'Content-Security-Policy',
                "object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
            );
        }

        return $response;
    }

    /**
     * Determine whether the request is operating within a validated embedded Shopify context.
     */
    protected function isEmbeddedShopifyContext(Request $request): bool
    {
        // 1. Hard Exclusion: Internal CRM super-admin routes must never be framed by Shopify
        if ($request->is('admin') || $request->is('admin/*')) {
            return false;
        }

        // 2. Verified Active Session / Request Attributes from Shopify Authentication
        if (
            $request->attributes->has('shopify_verified_shop') ||
            $request->attributes->has('shopify_verified_model') ||
            $request->attributes->has('active_shop') ||
            $request->attributes->has('active_shop_model') ||
            session()->has('_shopify_verified_shop') ||
            session()->has('active_shop')
        ) {
            return true;
        }

        // 3. Core Merchant-Facing Tenant Application Routes
        if (
            $request->is('dashboard') || $request->is('dashboard/*') ||
            $request->is('apps/*') || $request->is('store/*/apps/*') ||
            $request->is('plans') || $request->is('plans/*') ||
            $request->is('planview') || $request->is('planview/*') ||
            $request->is('billing/*') ||
            $request->is('inventory') || $request->is('inventory/*') ||
            $request->is('settings') || $request->is('settings/*') ||
            $request->is('product/*') || $request->is('products') || $request->is('products/*') ||
            $request->is('createProduct') || $request->is('createProduct/*') ||
            $request->is('editProduct/*') || $request->is('showProducts/*') ||
            $request->is('orders') || $request->is('orders/*') ||
            $request->is('returns/*') || $request->is('return_refunds') || $request->is('return_refunds/*') ||
            $request->is('ai-chat') || $request->is('ai-chat/*') || $request->is('ai/*') ||
            $request->is('logs') || $request->is('logs/*') ||
            $request->is('rules') || $request->is('help') || $request->is('support') ||
            $request->is('notification') || $request->is('notification/*') ||
            $request->is('connect') || $request->is('amazon/*') || $request->is('amzon/*') ||
            $request->is('setup') || $request->is('setup/*') ||
            $request->is('api/shopify/*')
        ) {
            return true;
        }

        // 4. Install and Entrypoint requests when framed or launched with Shopify context
        $hasShopifySignals = $request->query('embedded') === '1' ||
            $request->query('embedded') === 'true' ||
            $request->filled('host') ||
            $request->has('hmac') ||
            strtolower((string) $request->header('sec-fetch-dest')) === 'iframe' ||
            str_contains((string) $request->header('referer'), 'admin.shopify.com') ||
            str_contains((string) $request->header('referer'), '.myshopify.com');

        if (($request->is('install') || $request->is('/') || $request->routeIs('crm.entry')) && $hasShopifySignals) {
            return true;
        }

        // 5. Supporting iframe header fallback for non-admin pages
        if (strtolower((string) $request->header('sec-fetch-dest')) === 'iframe') {
            return true;
        }

        return false;
    }
}

