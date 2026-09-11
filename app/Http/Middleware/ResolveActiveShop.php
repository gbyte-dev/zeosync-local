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
        // 1. Bypass admin routes (handled by auth:admin guard)
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        // 2. Bypass webhook routes
        if (
            $request->routeIs('shopify.webhooks.orders.create')
            || $request->routeIs('shopify.webhooks.app.uninstalled')
            || $request->routeIs('shopify.webhooks.customers.data_request')
            || $request->routeIs('shopify.webhooks.customers.redact')
            || $request->routeIs('shopify.webhooks.shop.redact')
            || $request->is('webhooks/*')
            || $request->is('shopify/webhooks/*')
        ) {
            Log::info('BYPASS RESOLVE ACTIVE SHOP FOR WEBHOOK', [
                'route' => $request->route()?->getName(),
            ]);

            return $next($request);
        }

        // 2. Consume cryptographically verified shop from VerifyShopifyAuthentication
        if ($request->attributes->has('shopify_verified_shop')) {
            $verifiedShopDomain = $request->attributes->get('shopify_verified_shop');
            /** @var Shop|null $shop */
            $shop = $request->attributes->get('shopify_verified_model');

            if (!$shop) {
                try {
                    $shop = Shop::where('shop', $verifiedShopDomain)
                        ->where('is_active', 1)
                        ->first();
                } catch (\Throwable $e) {
                    $shop = null;
                }
            }

            if ($shop && !empty($shop->access_token)) {
                $request->attributes->set('active_shop', $shop->shop);
                $request->attributes->set('active_shop_model', $shop);

                session([
                    'active_shop'    => $shop->shop,
                    'active_shop_id' => $shop->id,
                ]);

                View::share('activeShop', $shop->shop);
                View::share('activeShopModel', $shop);

                Log::info('RESOLVED VERIFIED ACTIVE SHOP', [
                    'shop_id' => $shop->id,
                    'shop'    => $shop->shop,
                    'source'  => $request->attributes->get('shopify_auth_source'),
                ]);

                // Activation check for setup flow
                $isActivated = filled($shop->shop_name) && filled($shop->email);
                if (
                    !$isActivated &&
                    !$request->routeIs('setup.form') &&
                    !$request->routeIs('setup.store') &&
                    !$request->routeIs('shopify.callback') &&
                    !$request->routeIs('shopify.install')
                ) {
                    return redirect()
                        ->route('setup.form', [
                            'shop' => $shop->shop,
                        ])
                        ->with('error', 'Please fill the activation form to activate the app.');
                }

                return $next($request);
            }
        }

        // 3. Public / Setup / OAuth Entry routes allow legacy parameter-based discovery for onboarding
        if (
            $request->routeIs('crm.entry') ||
            $request->routeIs('shopify.app.launch*') ||
            $request->routeIs('shopify.install') ||
            $request->routeIs('shopify.callback') ||
            $request->routeIs('setup.form') ||
            $request->routeIs('setup.store') ||
            $request->routeIs('about') ||
            $request->routeIs('pricing') ||
            $request->routeIs('contact') ||
            $request->routeIs('contact.store') ||
            $request->routeIs('terms') ||
            $request->routeIs('privacy')
        ) {
            $activeShop = $this->resolveShopDomain($request);

            if ($activeShop) {
                $request->attributes->set('active_shop', $activeShop);
            }

            $shop = null;
            if ($activeShop) {
                try {
                    $shop = Shop::where('shop', $activeShop)->where('is_active', 1)->first();
                } catch (\Throwable $e) {
                    $shop = null;
                }
            }
            if ($shop) {
                $request->attributes->set('active_shop_model', $shop);
            }

            if (
                $shop && filled($shop->shop_name) && filled($shop->email) &&
                $request->routeIs('setup.store')
            ) {
                return redirect()->route('dashboard', [
                    'shop' => $shop->shop,
                ]);
            }

            View::share('activeShop', $activeShop);
            return $next($request);
        }

        // 4. Protected tenant route without verified identity
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'Unauthenticated Shopify request.',
            ], 401);
        }

        // For protected browser routes without verified identity, fail closed to entry/install
        Log::warning('UNAUTHENTICATED ACCESS TO PROTECTED ROUTE BLOCKED', [
            'path' => $request->path(),
            'shop' => $request->query('shop'),
        ]);

        return redirect()->route('crm.entry')->with('error', 'Session expired or unauthenticated.');
    }

    private function resolveShopDomain(Request $request): ?string
    {
        // 1. Shopify host param
        if ($request->has('host')) {
            $decoded = $this->decodeShopifyHost($request->get('host'));

            if (preg_match('#/store/([a-z0-9-]+)#i', (string) $decoded, $matches)) {
                return strtolower($matches[1] . '.myshopify.com');
            }

            if (preg_match('#^([a-z0-9-]+)\.myshopify\.com#i', (string) $decoded, $matches)) {
                return strtolower($matches[1] . '.myshopify.com');
            }
        }

        // 2. Query Parameter
        $shop = $request->query('shop');
        if ($shop) {
            $shop = strtolower(trim($shop));
            if (!str_contains($shop, '.myshopify.com')) {
                $shop .= '.myshopify.com';
            }
            if (preg_match('/^[a-z0-9-]+\.myshopify\.com$/', $shop)) {
                return $shop;
            }
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

