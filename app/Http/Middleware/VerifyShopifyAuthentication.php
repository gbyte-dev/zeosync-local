<?php

namespace App\Http\Middleware;

use App\Models\AdminSetting;
use App\Models\Shop;
use App\Services\ShopifySessionTokenValidator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyAuthentication
{
    protected ShopifySessionTokenValidator $validator;

    public function __construct(ShopifySessionTokenValidator $validator)
    {
        $this->validator = $validator;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Priority 1: Shopify Launch HMAC parameters (can appear on crm.entry or any page)
        if ($request->has('hmac') && $request->has('shop')) {
            $hmacShop = $this->verifyLaunchHmac($request);
            if ($hmacShop) {
                $request->attributes->set('shopify_verified_shop', $hmacShop->shop);
                $request->attributes->set('shopify_verified_model', $hmacShop);
                $request->attributes->set('shopify_auth_source', 'shopify_hmac');

                session([
                    '_shopify_verified_shop' => $hmacShop->shop,
                    '_shopify_verified_at'   => time(),
                    'active_shop'            => $hmacShop->shop,
                    'active_shop_id'         => $hmacShop->id,
                ]);

                return $next($request);
            }
        }

        // 2. Priority 2: App Bridge Session Token (Authorization header, custom header, or URL query token) OR Laravel Crypt Path/Query Token
        $sessionToken = $this->extractSessionToken($request);
        if ($sessionToken) {
            // 2a. Validate App Bridge JWT Session Token
            $tokenResult = $this->validator->validate($sessionToken);

            if ($tokenResult) {
                $request->attributes->set('shopify_verified_shop', $tokenResult['shop']);
                $request->attributes->set('shopify_verified_model', $tokenResult['shop_model']);
                $request->attributes->set('shopify_auth_source', 'bearer_token');

                session([
                    '_shopify_verified_shop' => $tokenResult['shop'],
                    '_shopify_verified_at'   => time(),
                    'active_shop'            => $tokenResult['shop'],
                    'active_shop_id'         => $tokenResult['shop_model']->id,
                ]);

                return $next($request);
            }

            // 2b. Validate Laravel Crypt Token (from path /apps/{token} or query)
            $cryptResult = $this->verifyCryptToken($sessionToken);
            if ($cryptResult) {
                $request->attributes->set('shopify_verified_shop', $cryptResult['shop']);
                $request->attributes->set('shopify_verified_model', $cryptResult['shop_model']);
                $request->attributes->set('shopify_auth_source', 'bearer_token');

                session([
                    '_shopify_verified_shop' => $cryptResult['shop'],
                    '_shopify_verified_at'   => time(),
                    'active_shop'            => $cryptResult['shop'],
                    'active_shop_id'         => $cryptResult['shop_model']->id,
                ]);

                return $next($request);
            }

            // If a session token was provided but is invalid, fail closed for AJAX/JSON
            Log::warning('VerifyShopifyAuthentication: Invalid session token provided.', [
                'path' => $request->path(),
            ]);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'error'   => 'Unauthorized',
                    'message' => 'Invalid, expired, or untrusted Shopify session token.',
                ], 401);
            }
        }

        // 3. Bypass unauthenticated / public / webhook / admin routes
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        // 4. Priority 3: Established Cryptographically Verified Session
        if (session()->has('_shopify_verified_shop')) {
            $sessionShopDomain = session('_shopify_verified_shop');
            try {
                $sessionShop = Shop::where('shop', $sessionShopDomain)
                    ->where('is_active', 1)
                    ->first();
            } catch (\Throwable $e) {
                $sessionShop = null;
            }

            if ($sessionShop && !empty($sessionShop->access_token)) {
                $request->attributes->set('shopify_verified_shop', $sessionShop->shop);
                $request->attributes->set('shopify_verified_model', $sessionShop);
                $request->attributes->set('shopify_auth_source', 'verified_session');

                return $next($request);
            }
        }

        // 5. If AJAX / JSON request without authentication on protected route -> 401
        if ($request->ajax() || $request->expectsJson()) {
            Log::warning('VerifyShopifyAuthentication: Unauthenticated AJAX/API request rejected.', [
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'Shopify authentication required.',
            ], 401);
        }

        // 6. Non-AJAX browser requests continue to ResolveActiveShop
        return $next($request);
    }

    /**
     * Extract session token from Bearer header, X-Shopify-Session-Token header, route param, path, or query parameters.
     */
    protected function extractSessionToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (!empty($bearer)) {
            return trim($bearer);
        }

        $headerToken = $request->header('X-Shopify-Session-Token');
        if (!empty($headerToken) && is_string($headerToken)) {
            return trim($headerToken);
        }

        // Route parameter 'token' (e.g. /apps/{token}/dashboard)
        $routeToken = $request->route('token');
        if (!empty($routeToken) && is_string($routeToken)) {
            return trim($routeToken);
        }

        // Path fallback (e.g. apps/{token}/dashboard or store/{store}/apps/{token})
        $path = $request->path();
        if (preg_match('~apps/([^/?#]+)~i', $path, $matches)) {
            if (!empty($matches[1]) && $matches[1] !== 'dashboard') {
                return trim($matches[1]);
            }
        }

        $queryCandidates = ['id_token', 'token', 'session_token', 'session', 'shopify_token'];
        foreach ($queryCandidates as $param) {
            $val = $request->query($param);
            if (!empty($val) && is_string($val)) {
                return trim($val);
            }
        }

        return null;
    }

    /**
     * Verify a Laravel Crypt encrypted string (e.g. from /apps/{token}/dashboard or query).
     */
    public function verifyCryptToken(string $token): ?array
    {
        $candidates = [
            $token,
            urldecode($token),
            strtr($token, '-_', '+/'),
            strtr(urldecode($token), '-_', '+/'),
        ];

        $decrypted = null;
        foreach (array_unique($candidates) as $candidate) {
            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($candidate);
                if (!empty($decrypted)) {
                    break;
                }
            } catch (\Throwable $e) {
                // Decryption failed for this candidate, try next
            }
        }

        if (empty($decrypted)) {
            return null;
        }

        $shopDomain = null;

        // Check if decrypted payload is JSON
        $json = json_decode($decrypted, true);
        if (is_array($json)) {
            $shopDomain = $json['shop'] ?? $json['shop_domain'] ?? $json['store'] ?? null;

            // Check expiration if present
            if (isset($json['exp']) && is_numeric($json['exp']) && $json['exp'] < time()) {
                Log::warning('VerifyShopifyAuthentication: Crypt token expired.');
                return null;
            }
            if (isset($json['time']) && is_numeric($json['time']) && (time() - $json['time'] > 86400)) {
                Log::warning('VerifyShopifyAuthentication: Crypt token timestamp expired.');
                return null;
            }
        } elseif (is_string($decrypted)) {
            $shopDomain = trim($decrypted);
        }

        if (empty($shopDomain)) {
            return null;
        }

        $normalizedShop = $this->validator->normalizeShopDomain($shopDomain);
        if (!$normalizedShop) {
            return null;
        }

        try {
            $shopModel = Shop::where('shop', $normalizedShop)
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable $e) {
            $shopModel = null;
        }

        if (!$shopModel || empty($shopModel->access_token)) {
            return null;
        }

        return [
            'shop'       => $normalizedShop,
            'shop_model' => $shopModel,
        ];
    }

    /**
     * Check if route is public, webhook, admin, or OAuth entrypoint.
     */
    protected function shouldBypass(Request $request): bool
    {
        // Webhook routes
        if (
            $request->routeIs('shopify.webhooks.*') ||
            $request->routeIs('webhooks.*') ||
            $request->routeIs('stripe.webhook') ||
            $request->routeIs('amazon.webhooks.*') ||
            $request->is('webhooks/*') ||
            $request->is('shopify/webhooks/*') ||
            $request->is('customers/*') ||
            $request->is('shop/*')
        ) {
            return true;
        }

        // OAuth lifecycle & public / CRM entry routes
        if (
            $request->routeIs('crm.entry') ||
            $request->routeIs('shopify.install') ||
            $request->routeIs('shopify.callback') ||
            $request->routeIs('setup.form') ||
            $request->routeIs('setup.store') ||
            $request->routeIs('about') ||
            $request->routeIs('pricing') ||
            $request->routeIs('contact') ||
            $request->routeIs('contact.store') ||
            $request->routeIs('terms') ||
            $request->routeIs('privacy') ||
            $request->is('admin') ||
            $request->is('admin/*')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Validate Shopify launch HMAC query parameters for embedded page loads.
     */
    protected function verifyLaunchHmac(Request $request): ?Shop
    {
        $query = $request->query();
        $hmac = $query['hmac'] ?? null;

        if (empty($hmac) || !is_string($hmac)) {
            return null;
        }

        $apiSecret = AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret'));
        if (empty($apiSecret)) {
            Log::error('VerifyShopifyAuthentication: SHOPIFY_API_SECRET not set for launch HMAC check.');
            return null;
        }

        unset($query['hmac'], $query['signature']);
        ksort($query);

        $computedHmac = hash_hmac('sha256', urldecode(http_build_query($query)), $apiSecret);

        if (!hash_equals($hmac, $computedHmac)) {
            Log::warning('VerifyShopifyAuthentication: Launch HMAC signature mismatch.');
            return null;
        }

        // Optional timestamp check (within 24 hours)
        if (isset($query['timestamp']) && is_numeric($query['timestamp'])) {
            if (abs(time() - (int) $query['timestamp']) > 86400) {
                Log::warning('VerifyShopifyAuthentication: Launch HMAC timestamp too old.');
                return null;
            }
        }

        $rawShop = $query['shop'] ?? null;
        $normalizedShop = $this->validator->normalizeShopDomain($rawShop);

        if (!$normalizedShop) {
            return null;
        }

        try {
            $shop = Shop::where('shop', $normalizedShop)
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable $e) {
            $shop = null;
        }

        return ($shop && !empty($shop->access_token)) ? $shop : null;
    }
}
