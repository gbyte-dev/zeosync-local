<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyComplianceWebhook;
use App\Models\ComplianceRequest;
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
        return $this->accept($request, 'customers_data_request');
    }

    private function accept(Request $request, string $type)
    {
        $payload = $request->getContent();
        if (! app(\App\Services\ShopifyWebhookService::class)->isValidWebhook($payload, (string) $request->header('X-Shopify-Hmac-Sha256'))) {
            Log::warning('Rejected Shopify compliance webhook with invalid HMAC.', ['type' => $type]);

            return response()->json(['success' => false, 'error' => 'invalid_webhook_signature'], 401);
        }
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return response()->json(['success' => false, 'error' => 'invalid_payload'], 400);
        }
        $shopDomain = strtolower(trim((string) ($request->header('X-Shopify-Shop-Domain') ?: ($data['shop_domain'] ?? ''))));
        $shop = $shopDomain !== '' ? Shop::withTrashed()->where('shop', $shopDomain)->first() : null;
        $transportId = trim((string) ($request->header('X-Shopify-Event-Id') ?: $request->header('X-Shopify-Webhook-Id')));
        $eventId = $transportId !== '' ? $transportId : hash('sha256', $type.'|'.$shopDomain.'|'.$payload);
        $customerId = data_get($data, 'customer.id');
        $customerEmail = strtolower(trim((string) data_get($data, 'customer.email', '')));
        $record = ComplianceRequest::firstOrCreate(['event_id' => $eventId], [
            'shop_id' => $shop?->id,
            'shop_domain_hash' => $shopDomain !== '' ? hash('sha256', $shopDomain) : null,
            'type' => $type,
            'customer_id' => $customerId !== null ? (string) $customerId : null,
            'customer_email_hash' => $customerEmail !== '' ? hash('sha256', $customerEmail) : null,
            'status' => 'received',
            'request_payload' => $data,
        ]);
        if (! in_array($record->status, ['completed', 'revoked'], true)) {
            ProcessShopifyComplianceWebhook::dispatch($record->id);
        }

        return response()->json(['success' => true], 200);
    }

    public function legacyCustomersDataRequest(Request $request)
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
        return $this->accept($request, 'customers_redact');
    }

    public function legacyCustomersRedact(Request $request)
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
        return $this->accept($request, 'shop_redact');
    }

    public function legacyShopRedact(Request $request)
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
