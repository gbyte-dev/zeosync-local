<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyComplianceWebhookController extends Controller
{
    /**
     * Main Shopify compliance webhook handler.
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();

        $shopifyWebhook = app(\App\Services\ShopifyWebhookService::class);

        if (!$shopifyWebhook->isValidWebhook(
            $payload,
            $request->header('X-Shopify-Hmac-Sha256')
        )) {
            Log::warning('Invalid Shopify compliance webhook HMAC', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response('Invalid webhook signature', 401);
        }

        $topic = $request->header('X-Shopify-Topic');

        Log::info('Shopify compliance webhook received', [
            'topic' => $topic,
            'shop' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        return match ($topic) {
            'customers/data_request' => $this->customersDataRequest($request),
            'customers/redact' => $this->customersRedact($request),
            'shop/redact' => $this->shopRedact($request),
            default => response('Unsupported webhook topic', 400),
        };
    }

    /**
     * Shopify GDPR - Customer Data Request
     */
    public function customersDataRequest(Request $request)
    {
        $shopName = strtolower(
            trim((string) $request->header('X-Shopify-Shop-Domain'))
        );

        if (!$shopName) {
            return response()->json([
                'success' => false,
                'message' => 'Shop domain is required.'
            ], 400);
        }

        $shop = Shop::where('shop', $shopName)->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found.'
            ], 404);
        }

        return response()->json([
            'shop_name'    => $shop->shop,
            'email'        => $shop->email,
            'installed_at' => $shop->installed_at,
            'is_active'    => $shop->is_active,
            'store_status' => $shop->store_status,
        ]);
    }

    /**
     * Shopify GDPR - Customer Redact
     */
    public function customersRedact(Request $request)
    {
        Log::info('Shopify Customer Redact Webhook', [
            'shop' => $request->header('X-Shopify-Shop-Domain'),
            'payload' => $request->all(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer redact request received successfully.'
        ], 200);
    }

    /**
     * Shopify GDPR - Shop Redact
     */
    public function shopRedact(Request $request)
    {
        $shopName = strtolower(
            trim((string) $request->header('X-Shopify-Shop-Domain'))
        );

        if (!$shopName) {
            return response()->json([
                'success' => false,
                'message' => 'Shop domain is required.'
            ], 400);
        }

        $shop = Shop::where('shop', $shopName)->first();

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found.'
            ], 404);
        }

        $shop->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shop deleted successfully.'
        ]);
    }
}
