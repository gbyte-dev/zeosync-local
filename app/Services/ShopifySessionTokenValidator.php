<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ShopifySessionTokenValidator
{
    /**
     * Leeway in seconds for clock skew tolerance (exp and nbf).
     */
    private const CLOCK_TOLERANCE_SECONDS = 10;

    /**
     * Validate a Shopify App Bridge session token (JWT).
     *
     * @param string $token Raw Bearer token
     * @return array{shop: string, shop_model: Shop, payload: array}|null
     */
    public function validate(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            Log::warning('ShopifySessionTokenValidator: Malformed JWT structure.');
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // 1. Decode Header
        $headerJson = $this->base64UrlDecode($headerB64);
        if ($headerJson === null) {
            Log::warning('ShopifySessionTokenValidator: Failed to decode JWT header.');
            return null;
        }

        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            Log::warning('ShopifySessionTokenValidator: Unsupported or missing algorithm in header.', [
                'alg' => $header['alg'] ?? null,
            ]);
            return null;
        }

        // 2. Resolve Shopify API Secret & Verify Signature
        $apiSecret = AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret'));
        if (empty($apiSecret)) {
            Log::error('ShopifySessionTokenValidator: SHOPIFY_API_SECRET is not configured.');
            return null;
        }

        $signature = $this->base64UrlDecode($signatureB64, true);
        if ($signature === null) {
            Log::warning('ShopifySessionTokenValidator: Failed to decode JWT signature.');
            return null;
        }

        $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $apiSecret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('ShopifySessionTokenValidator: Signature verification failed.');
            return null;
        }

        // 3. Decode & Validate Payload Claims
        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            Log::warning('ShopifySessionTokenValidator: Failed to decode JWT payload.');
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            Log::warning('ShopifySessionTokenValidator: Invalid JSON in JWT payload.');
            return null;
        }

        // 4. Validate Audience (aud) - Strictly against SHOPIFY_API_KEY only
        $apiKey = AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key'));
        if (empty($apiKey)) {
            Log::error('ShopifySessionTokenValidator: SHOPIFY_API_KEY is not configured.');
            return null;
        }

        if (($payload['aud'] ?? null) !== $apiKey) {
            Log::warning('ShopifySessionTokenValidator: Audience mismatch.', [
                'expected' => $apiKey,
                'actual'   => $payload['aud'] ?? null,
            ]);
            return null;
        }

        $currentTime = time();

        // 5. Validate Expiration (exp)
        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            Log::warning('ShopifySessionTokenValidator: Missing or non-numeric exp claim.');
            return null;
        }

        if (($payload['exp'] + self::CLOCK_TOLERANCE_SECONDS) < $currentTime) {
            Log::warning('ShopifySessionTokenValidator: Token is expired.', [
                'exp' => $payload['exp'],
                'now' => $currentTime,
            ]);
            return null;
        }

        // 6. Validate Not Before (nbf)
        if (isset($payload['nbf']) && is_numeric($payload['nbf'])) {
            if (($payload['nbf'] - self::CLOCK_TOLERANCE_SECONDS) > $currentTime) {
                Log::warning('ShopifySessionTokenValidator: Token is not yet valid (nbf).', [
                    'nbf' => $payload['nbf'],
                    'now' => $currentTime,
                ]);
                return null;
            }
        }

        // 7. Validate & Extract Destination (dest) and Issuer (iss)
        $dest = $payload['dest'] ?? '';
        $iss = $payload['iss'] ?? '';

        if (!is_string($dest) || !is_string($iss)) {
            Log::warning('ShopifySessionTokenValidator: Invalid dest or iss claim types.');
            return null;
        }

        $destHost = parse_url($dest, PHP_URL_HOST);
        $issHost = parse_url($iss, PHP_URL_HOST);

        if (!$destHost || !$issHost) {
            Log::warning('ShopifySessionTokenValidator: Malformed URLs in dest or iss.', [
                'dest' => $dest,
                'iss'  => $iss,
            ]);
            return null;
        }

        $destShop = $this->normalizeShopDomain($destHost);

        $issHostLower = strtolower($issHost);
        $issShop = null;

        if ($issHostLower === 'admin.shopify.com') {
            $issPath = parse_url($iss, PHP_URL_PATH) ?? '';
            if (preg_match('#^/store/([a-z0-9-]+)(?:/.*)?$#i', $issPath, $matches)) {
                $issShop = $this->normalizeShopDomain($matches[1] . '.myshopify.com');
            } else {
                Log::warning('ShopifySessionTokenValidator: admin.shopify.com issuer missing valid store path.', [
                    'iss' => $iss,
                ]);
                return null;
            }
        } else {
            $issShop = $this->normalizeShopDomain($issHost);
        }

        if (!$destShop || !$issShop || $destShop !== $issShop) {
            Log::warning('ShopifySessionTokenValidator: Shop domain mismatch between dest and iss.', [
                'dest'     => $dest,
                'iss'      => $iss,
                'destShop' => $destShop,
                'issShop'  => $issShop,
            ]);
            return null;
        }

        // 8. Find active Shop in database
        try {
            $shopModel = Shop::where('shop', $destShop)
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable $e) {
            Log::error('ShopifySessionTokenValidator: Database error looking up shop.', [
                'error' => $e->getMessage(),
                'shop'  => $destShop,
            ]);
            return null;
        }

        if (!$shopModel || empty($shopModel->access_token)) {
            Log::warning('ShopifySessionTokenValidator: Shop not found or not active in database.', [
                'shop' => $destShop,
            ]);
            return null;
        }

        return [
            'shop'       => $destShop,
            'shop_model' => $shopModel,
            'payload'    => $payload,
        ];
    }

    /**
     * Normalize shop domain to standard lowercase {subdomain}.myshopify.com format.
     */
    public function normalizeShopDomain(?string $domain): ?string
    {
        $domain = strtolower(trim((string) $domain));
        if ($domain === '') {
            return null;
        }

        // Remove any protocol or path if passed
        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            $domain = $parsed ? strtolower($parsed) : $domain;
        }

        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        if (!str_contains($domain, '.myshopify.com')) {
            $domain .= '.myshopify.com';
        }

        if (!preg_match('/^[a-z0-9-]+\.myshopify\.com$/', $domain)) {
            return null;
        }

        return $domain;
    }

    /**
     * Base64Url decode helper.
     */
    private function base64UrlDecode(string $input, bool $raw = false): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }
}
