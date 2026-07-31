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
        $shopName = $request->input('shop');

        if (!$shopName) {
            return response()->json([
                'success' => false,
                'message' => 'Shop parameter is required.'
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
        Log::info('Shopify Customer Redact Webhook', $request->all());

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
        $shopName = $request->input('shop_domain');

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
