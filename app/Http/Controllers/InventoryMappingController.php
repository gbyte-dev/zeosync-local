<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use Illuminate\Http\Request;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AmazonService;
use App\Services\SyncLimitService;
use App\Models\Shop;


class InventoryMappingController extends Controller
{

    public function shopifyProducts(Request $request)
    {
        $shopDomain = $request->query('shop');

        $shop = Shop::where('shop', $shopDomain)->firstOrFail();

        $products = Product::where('shop_id', $shop->id)
            ->orderBy('title')
            ->get([
                'id',
                'title',
                'shopify_id'
            ]);

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    public function variants(Request $request, Product $product)
    {
        $shop = Shop::where('shop', $request->query('shop'))->firstOrFail();

        if ($product->shop_id != $shop->id) {
            abort(404);
        }

        $mapped = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->pluck('shopify_variant_id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        $variants = $product->variants;

        if (!is_array($variants)) {
            $variants = json_decode($variants, true) ?? [];
        }

        $response = [];

        foreach ($variants as $variant) {

            if (in_array((string) $variant['id'], $mapped)) {
                continue;
            }

            $response[] = [
                'id' => $variant['id'],
                'title' => $variant['title'],
                'inventory_item_id' => $variant['inventory_item_id'],
            ];
        }

        return response()->json([
            'success' => true,
            'variants' => $response,
            'shopify_product_id' => $product->shopify_id,
        ]);
    }


    public function saveProductMapping(Request $request)
    {
        $request->validate([
            'shop' => 'required',
            'amazon_sku' => 'required',
            'product_id' => 'required',
            'variant_id' => 'required',
            'shopify_product_id' => 'required',
            'shopify_variant_id' => 'required',
            'shopify_inventory_item_id' => 'required',
        ]);

        $shop = Shop::where('shop', $request->shop)->firstOrFail();

        $exists = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('shopify_variant_id', $request->shopify_variant_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Variant already mapped.'
            ], 422);
        }

        $syncLimit = app(SyncLimitService::class)->canMap($shop);

        if (!$syncLimit['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $syncLimit['message'],
                'used' => $syncLimit['used'],
                'limit' => $syncLimit['limit'],
                'remaining' => $syncLimit['remaining'],
            ], 403);
        }

        $product = Product::findOrFail($request->product_id);
        $variants = is_array($product->variants)
            ? $product->variants
            : json_decode($product->variants, true);

        $selectedVariant = collect($variants)
            ->firstWhere('id', (string) $request->variant_id);

        Log::info('Selected Variant', $selectedVariant ?? []);

        $insertData = [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'variant_id' => (string) $request->variant_id,
            'shopify_product_id' => $request->shopify_product_id,
            'shopify_variant_id' => $request->shopify_variant_id,
            'shopify_inventory_item_id' => $request->shopify_inventory_item_id,
            'amazon_sku' => $request->amazon_sku,
            'amazon_parent_sku' => $request->amazon_parent_sku ?: $request->amazon_sku,
            'quantity' => $selectedVariant['inventory_quantity'] ?? 0,
            'sync_status' => 'pending',
            'submission_status' => 'not_submitted',
        ];
        
        $mapping = ProductMarketplaceMapping::create($insertData);

        Log::info('Saved Record', $mapping->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Product mapped successfully.',
            'used' => $syncLimit['used'] + 1,
            'limit' => $syncLimit['limit'],
            'remaining' => max(0, $syncLimit['remaining'] - 1),
        ]);
    }


    public function saveAmazonMapping(Request $request)
    {

        $request->validate([
            'shop' => 'required',
            'product_id' => 'required',
            'shopify_variant_id' => 'required',
            'amazon_sku' => 'required',
        ]);

        $shop = Shop::where('shop', $request->shop)->firstOrFail();

        $product = Product::where(
            'shopify_id',
            $request->product_id
        )->firstOrFail();

        $variants = is_array($product->variants)
            ? $product->variants
            : json_decode($product->variants, true);


        $variant = collect($variants)->firstWhere(
            'id',
            (int) $request->shopify_variant_id
        );

        Log::info('Selected Variant', $variant ?? []);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Selected Shopify variant not found.'
            ], 404);
        }

        $amazonExists = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('amazon_sku', $request->amazon_sku)
            ->where('shopify_variant_id', '!=', (string) $request->shopify_variant_id)
            ->exists();

        if ($amazonExists) {
            return response()->json([
                'success' => false,
                'message' => 'This Amazon SKU is already mapped with another Shopify variant.'
            ], 422);
        }

        $existingMapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('shopify_variant_id', (string) $variant['id'])
            ->first();

        Log::info('Existing Mapping', [
            'exists' => !is_null($existingMapping),
            'mapping' => $existingMapping ? $existingMapping->toArray() : null,
        ]);

        if (!$existingMapping) {

            $syncLimit = app(SyncLimitService::class)->canMap($shop);
            Log::info('Sync Limit', $syncLimit);

            if (!$syncLimit['allowed']) {

                return response()->json([
                    'success' => false,
                    'message' => $syncLimit['message'],
                    'used' => $syncLimit['used'],
                    'limit' => $syncLimit['limit'],
                    'remaining' => $syncLimit['remaining'],
                ], 403);
            }
        }

        $updateData = [
            'product_id' => $product->id,
            'variant_id' => (string) $variant['id'],
            'shopify_product_id' => (string) $product->shopify_id,
            'shopify_variant_id' => (string) $variant['id'],
            'shopify_inventory_item_id' => (string) $variant['inventory_item_id'],
            'amazon_sku' => $request->amazon_sku,
            'amazon_parent_sku' => $request->amazon_parent_sku ?: $request->amazon_sku,
            'quantity' => (string) ($variant['inventory_quantity'] ?? 0),
        ];

        Log::info('UpdateOrCreate Payload', $updateData);

        $mapping = ProductMarketplaceMapping::updateOrCreate(
            [
                'shop_id' => $shop->id,
                'shopify_variant_id' => (string) $variant['id'],
            ],
            $updateData
        );

        Log::info('Saved Record', $mapping->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Amazon product mapped successfully.'
        ]);
    }

    public function updateShopifyInventory(Request $request)
    {
        $request->validate([
            'shop'              => 'required',
            'inventory_item_id' => 'required',
            'quantity'          => 'required|integer|min:0',
        ]);

        $shop = Shop::where('shop', $request->shop)->firstOrFail();

        $shopify = new ShopifyService(
            $shop->shop,
            $shop->access_token
        );

        // Get Shopify Location
        $locationRes = $shopify->shopifyRest(
            $shop,
            'get',
            'locations.json'
        );

        if (!empty($locationRes['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch Shopify location.'
            ], 422);
        }

        $locationId = $locationRes['locations'][0]['id'] ?? null;

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify location not found.'
            ], 422);
        }

        // Update Shopify Inventory
        $response = $shopify->shopifyRest(
            $shop,
            'post',
            'inventory_levels/set.json',
            [
                'location_id'       => $locationId,
                'inventory_item_id' => $request->inventory_item_id,
                'available'         => (int) $request->quantity,
            ]
        );

        if (!empty($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['message'] ?? 'Shopify inventory update failed.'
            ], 422);
        }

        // Check existing mapping
        $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('shopify_inventory_item_id', $request->inventory_item_id)
            ->first();

        // Product is not mapped -> Only Shopify update
        if (!$mapping) {
            return response()->json([
                'success' => true,
                'message' => 'Shopify inventory updated successfully.'
            ]);
        }

        try {

            app(AmazonService::class)->updateInventory(
                $shop,
                $mapping->amazon_sku,
                (int) $request->quantity
            );

            // Update existing mapping record only
            $mapping->update([
                'quantity'        => (int) $request->quantity,
                'sync_status'     => 'success',
                'last_synced_at'  => now(),
                'error_message'   => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shopify and Amazon inventory updated successfully.'
            ]);
        } catch (\Throwable $e) {

            \Log::error('Amazon inventory sync failed', [
                'shop_id'           => $shop->id,
                'amazon_sku'        => $mapping->amazon_sku,
                'inventory_item_id' => $request->inventory_item_id,
                'quantity'          => $request->quantity,
                'message'           => $e->getMessage(),
            ]);

            $mapping->update([
                'sync_status'   => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shopify inventory updated successfully. Amazon sync failed.'
            ]);
        }
    }

    public function unmap(Request $request, ProductMarketplaceMapping $mapping)
    {
        $shop = Shop::where('shop', $request->shop)->firstOrFail();

        if ($mapping->shop_id != $shop->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized mapping.'
            ], 403);
        }

        Log::info('========== UNMAP START ==========', [
            'shop_id' => $shop->id,
            'mapping_id' => $mapping->id,
            'amazon_sku' => $mapping->amazon_sku,
            'shopify_variant_id' => $mapping->shopify_variant_id,
        ]);

        $mapping->delete();

        Log::info('========== UNMAP SUCCESS ==========', [
            'shop_id' => $shop->id,
            'mapping_id' => $mapping->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product unmapped successfully.'
        ]);
    }
}
