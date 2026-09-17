<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Closure;

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
            $request->routeIs('shopify.webhooks.orders.create') ||
            $request->routeIs('shopify.webhooks.app.uninstalled') ||
            $request->routeIs('shopify.webhooks.customers.data_request') ||
            $request->routeIs('shopify.webhooks.customers.redact') ||
            $request->routeIs('shopify.webhooks.shop.redact') ||
            $request->is('webhooks/*') ||
            $request->is('shopify/webhooks/*')
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

            Log::info('SHOPIFY_DEBUG: resolve_active_shop_from_verified_attr', [
                'verified_shop_domain' => $verifiedShopDomain,
                'shop_model_found' => (bool) $shop,
                'shop_id' => $shop?->id,
                'access_token_present' => !empty($shop?->access_token),
                'session_active_shop' => session('active_shop'),
            ]);

            if ($shop && !empty($shop->access_token)) {
                $request->attributes->set('active_shop', $shop->shop);
                $request->attributes->set('active_shop_model', $shop);

                session([
                    'active_shop' => $shop->shop,
                    'active_shop_id' => $shop->id,
                ]);

                View::share('activeShop', $shop->shop);
                View::share('activeShopModel', $shop);

                Log::info('RESOLVED VERIFIED ACTIVE SHOP', [
                    'shop_id' => $shop->id,
                    'shop' => $shop->shop,
                    'source' => $request->attributes->get('shopify_auth_source'),
                ]);

                // Activation check for setup flow
                $isActivated = filled($shop->shop_name) && filled($shop->email);
                if (
                    !$isActivated &&
                    !$request->routeIs('setup.form') &&
                    !$request->routeIs('setup.store') &&
                    !$request->routeIs('setup.activation.status')
                ) {
                    Log::info('RESOLVE_ACTIVE_SHOP: Activation required', [
                        'shop_id' => $shop->id,
                        'shop' => $shop->shop,
                        'route' => $request->route()?->getName(),
                        'is_ajax' => $request->ajax() || $request->expectsJson(),
                    ]);

                    if ($request->ajax() || $request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'requires_activation' => true,
                            'redirect_url' => route('setup.form', [
                                'shop' => $shop->shop,
                            ]),
                            'code' => 'SHOP_ACTIVATION_REQUIRED',
                            'message' => 'Shop activation is required.',
                        ], 403);
                    }

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
            $request->routeIs('api.shop.status') ||
            $request->routeIs('setup.form') ||
            $request->routeIs('setup.store') ||
            $request->routeIs('setup.activation.status') ||
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

            Log::info('SHOPIFY_DEBUG: resolve_active_shop_public_route', [
                'route' => $request->route()?->getName(),
                'resolved_active_shop' => $activeShop,
                'db_shop_exists' => (bool) $shop,
                'db_shop_id' => $shop?->id,
                'session_active_shop' => session('active_shop'),
                'session_verified_shop' => session('_shopify_verified_shop'),
            ]);

            View::share('activeShop', $activeShop);
            return $next($request);
        }

        // 4. Protected tenant route without verified identity
        if ($request->ajax() || $request->expectsJson()) {
            $resolvedShop = $this->resolveShopDomain($request);
            return response()->json([
                'success' => false,
                'requires_reauth' => true,
                'redirect_url' => route('shopify.install', array_filter(['shop' => $resolvedShop])),
                'error' => 'Unauthorized',
                'message' => 'Shopify authentication required.',
            ], 401)->header('X-Shopify-Retry-Invalid-Session-Request', '1');
        }

        // For protected browser routes without verified identity:
        // Only return the lightweight App Bridge reauth bounce view when the request originates
        // from the embedded Shopify app context (e.g. inside Shopify Admin iframe).
        if ($this->isEmbeddedShopifyRequest($request)) {
            $targetUrl = $request->fullUrl();
            $resolvedShop = $this->resolveShopDomain($request);
            $host = $request->query('host');

            $existingShop = null;

            if ($resolvedShop) {
                $existingShop = Shop::where('shop', $resolvedShop)->first();
            }

            /*
             * |--------------------------------------------------------------------------
             * | New / inactive / tokenless shop
             * |--------------------------------------------------------------------------
             */
            if (
                !$existingShop ||
                !$existingShop->is_active ||
                empty($existingShop->access_token)
            ) {
                return redirect()->route('shopify.install', array_filter([
                    'shop' => $resolvedShop,
                    'host' => $host,
                    'embedded' => $request->query('embedded', '1'),
                ]));
            }

            /*
             * |--------------------------------------------------------------------------
             * | Existing shop with expired browser session
             * |--------------------------------------------------------------------------
             */
            return response()->view('shopify.reauth', [
                'targetUrl' => $targetUrl,
                'shop' => $resolvedShop,
                'host' => $host,
            ]);
        }

        // Standalone non-Shopify browser requests redirect to the entry/landing page
        return redirect()->route('crm.entry');
    }

    private function isEmbeddedShopifyRequest(Request $request): bool
    {
        if ($request->query('embedded') === '1' || $request->query('embedded') === 'true') {
            return true;
        }

        if ($request->filled('host')) {
            return true;
        }

        if ($request->filled('shop')) {
            return true;
        }

        if ($request->header('Sec-Fetch-Dest') === 'iframe') {
            return true;
        }

        $referer = (string) $request->header('referer');
        if ($referer !== '' && (str_contains($referer, 'admin.shopify.com') || str_contains($referer, '.myshopify.com'))) {
            return true;
        }

        if ($request->has('id_token') || $request->has('session_token')) {
            return true;
        }

        return false;
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
