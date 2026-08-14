<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyComplianceWebhookController extends Controller
{
    /**
     * Shopify GDPR - Customer Data Request
     */
    public function customersDataRequest(Request $request)
    {
        Log::info('COMPLIANCE WEBHOOK HIT - CUSTOMER DATA REQUEST', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'shop' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        $payload = $request->getContent();

        $shopifyWebhook = app(\App\Services\ShopifyWebhookService::class);

        if (!$shopifyWebhook->isValidWebhook(
            $payload,
            $request->header('X-Shopify-Hmac-Sha256')
        )) {
            Log::warning('Invalid Shopify Customer Data Request HMAC', [
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
                'error' => 'invalid_webhook_signature',
            ], 401);
        }

        $shopName = strtolower(
            trim((string) $request->header('X-Shopify-Shop-Domain'))
        );

        if (!$shopName) {
            Log::warning('Customer Data Request: shop domain missing');

            return response()->json([
                'success' => true,
                'message' => 'Webhook received.',
            ], 200);
        }

        $shop = Shop::where('shop', $shopName)->first();

        if (!$shop) {
            Log::warning('Compliance shop not found', [
                'shop' => $shopName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'shop_name' => $shop->shop,
            'email' => $shop->email,
            'installed_at' => $shop->installed_at,
            'is_active' => $shop->is_active,
            'store_status' => $shop->store_status,
        ], 200);
    }

    /**
     * Shopify GDPR - Customer Redact
     */
    public function customersRedact(Request $request)
    {
        Log::info('COMPLIANCE WEBHOOK HIT - CUSTOMER REDACT', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'shop' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        $payload = $request->getContent();

        $shopifyWebhook = app(\App\Services\ShopifyWebhookService::class);

        if (!$shopifyWebhook->isValidWebhook(
            $payload,
            $request->header('X-Shopify-Hmac-Sha256')
        )) {
            Log::warning('Invalid Shopify Customer Redact HMAC', [
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
                'error' => 'invalid_webhook_signature',
            ], 401);
        }

        Log::info('Shopify Customer Redact Webhook', [
            'shop' => $request->header('X-Shopify-Shop-Domain'),
            'payload' => $request->all(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer redact request received successfully.',
        ], 200);
    }

    /**
     * Shopify GDPR - Shop Redact
     */
    public function shopRedact(Request $request)
    {
        Log::info('COMPLIANCE WEBHOOK HIT - SHOP REDACT', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'shop' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        $payload = $request->getContent();

        $shopifyWebhook = app(\App\Services\ShopifyWebhookService::class);

        if (!$shopifyWebhook->isValidWebhook(
            $payload,
            $request->header('X-Shopify-Hmac-Sha256')
        )) {
            Log::warning('Invalid Shopify Shop Redact HMAC', [
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
                'error' => 'invalid_webhook_signature',
            ], 401);
        }

        $shopName = strtolower(
            trim((string) $request->header('X-Shopify-Shop-Domain'))
        );

        if (!$shopName) {
            Log::warning('Shop Redact: shop domain missing');

            return response()->json([
                'success' => true,
                'message' => 'Webhook received.',
            ], 200);
        }

        $shop = Shop::where('shop', $shopName)->first();

        if (!$shop) {
            Log::warning('Compliance shop not found for redact', [
                'shop' => $shopName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received.',
            ], 200);
        }

        $shop->delete();

        Log::info('Shopify Shop Redact completed', [
            'shop' => $shopName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shop deleted successfully.',
        ], 200);
    }
}
