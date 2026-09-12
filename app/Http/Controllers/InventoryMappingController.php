<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\InventorySyncOperation;
use App\Jobs\ProcessInventoryUpdateJob;
use Illuminate\Http\Request;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AmazonService;
use App\Services\SyncLimitService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Shop;


class InventoryMappingController extends Controller
{
    /**
     * Resolve the verified active Shopify shop from request attributes or verified session.
     * Never trusts client-supplied ?shop= parameter.
     */
    protected function getActiveShopModel(?Request $request = null): ?Shop
    {
        $request ??= request();

        if ($request?->attributes->has('active_shop_model')) {
            $shop = $request->attributes->get('active_shop_model');
            if ($shop instanceof Shop && (int) $shop->is_active === 1 && !empty($shop->access_token)) {
                return $shop;
            }
        }

        if (session()->has('_shopify_verified_shop')) {
            $sessionShop = session('_shopify_verified_shop');
            $shop = Shop::where('shop', $sessionShop)->where('is_active', 1)->first();
            if ($shop && !empty($shop->access_token)) {
                return $shop;
            }
        }

        if (session()->has('active_shop')) {
            $sessionShop = session('active_shop');
            $shop = Shop::where('shop', $sessionShop)->where('is_active', 1)->first();
            if ($shop && !empty($shop->access_token)) {
                return $shop;
            }
        }

        return null;
    }

    /**
     * Check if a QueryException is a unique key violation (MySQL 1062, SQLite 19, SQLSTATE 23000).
     */
    protected function isDuplicateKeyException(\Illuminate\Database\QueryException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? null;

        // MySQL duplicate entry error code is 1062
        // SQLite constraint error code is 19
        // SQLSTATE 23000 represents integrity constraint violation
        if ($errorCode === 1062 || $errorCode === 19 || $e->getCode() === '23000' || $e->getCode() === 23000) {
            $msg = strtolower($e->getMessage());
            return str_contains($msg, 'duplicate') || str_contains($msg, 'unique');
        }

        return false;
    }

    public function shopifyProducts(Request $request)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

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
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        if ((int) $product->shop_id !== (int) $shop->id) {
            abort(404, 'Product not found.');
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
            'shop' => 'nullable',
            'amazon_sku' => 'required',
            'product_id' => 'required',
            'variant_id' => 'required',
            'shopify_product_id' => 'required',
            'shopify_variant_id' => 'required',
            'shopify_inventory_item_id' => 'required',
        ]);

        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        $exists = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('shopify_variant_id', (string) $request->shopify_variant_id)
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

        $product = Product::where('shop_id', $shop->id)->find($request->product_id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or does not belong to this shop.'
            ], 404);
        }

        $variants = is_array($product->variants)
            ? $product->variants
            : json_decode($product->variants, true);

        $selectedVariant = collect($variants)
            ->firstWhere('id', (string) $request->variant_id);

        if (!$selectedVariant) {
            return response()->json([
                'success' => false,
                'message' => 'Selected variant not found for this product.'
            ], 404);
        }

        Log::info('Selected Variant', $selectedVariant ?? []);

        $insertData = [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'variant_id' => (string) $request->variant_id,
            'shopify_product_id' => (string) $request->shopify_product_id,
            'shopify_variant_id' => (string) $request->shopify_variant_id,
            'shopify_inventory_item_id' => (string) $request->shopify_inventory_item_id,
            'amazon_sku' => $request->amazon_sku,
            'amazon_parent_sku' => $request->amazon_parent_sku ?: $request->amazon_sku,
            'quantity' => $selectedVariant['inventory_quantity'] ?? 0,
            'sync_status' => 'pending',
            'submission_status' => 'not_submitted',
        ];

        try {
            $mapping = ProductMarketplaceMapping::create($insertData);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'unique_shop_amazon_sku') || str_contains($msg, 'amazon_sku')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This Amazon SKU is already mapped with another Shopify variant.'
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Variant already mapped.'
                ], 422);
            }

            throw $e;
        }

        Log::info('Saved Record', $mapping->fresh()->toArray());

        $latestSyncLimit = app(SyncLimitService::class)->canMap($shop);

        return response()->json([
            'success' => true,
            'message' => 'Product mapped successfully.',
            'used' => $latestSyncLimit['used'] ?? ($syncLimit['used'] + 1),
            'limit' => $latestSyncLimit['limit'] ?? $syncLimit['limit'],
            'remaining' => $latestSyncLimit['remaining'] ?? max(0, $syncLimit['remaining'] - 1),
            'sync_usage' => $latestSyncLimit,
        ]);
    }

    public function mappings(Request $request)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        $mappings = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->get([
                'id',
                'shopify_variant_id',
                'amazon_sku',
            ]);

        $syncUsage = app(SyncLimitService::class)->canMap($shop);

        return response()->json([
            'success' => true,
            'mappings' => $mappings,
            'sync_usage' => $syncUsage,
        ]);
    }


    public function saveAmazonMapping(Request $request)
    {
        $request->validate([
            'shop' => 'nullable',
            'product_id' => 'required',
            'shopify_variant_id' => 'required',
            'amazon_sku' => 'required',
        ]);

        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        $product = Product::where('shop_id', $shop->id)
            ->where('shopify_id', $request->product_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or does not belong to this shop.'
            ], 404);
        }

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

        try {
            $mapping = ProductMarketplaceMapping::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'shopify_variant_id' => (string) $variant['id'],
                ],
                $updateData
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Amazon SKU is already mapped with another Shopify variant.'
                ], 422);
            }

            throw $e;
        }

        Log::info('Saved Record', $mapping->fresh()->toArray());

        $latestSyncLimit = app(SyncLimitService::class)->canMap($shop);

        return response()->json([
            'success' => true,
            'message' => 'Amazon product mapped successfully.',
            'used' => $latestSyncLimit['used'] ?? null,
            'limit' => $latestSyncLimit['limit'] ?? null,
            'remaining' => $latestSyncLimit['remaining'] ?? null,
            'sync_usage' => $latestSyncLimit,
        ]);
    }


    public function updateShopifyInventory(Request $request)
    {
        $request->validate([
            'shop'               => 'nullable',
            'inventory_item_id'  => 'required',
            'quantity'           => 'required|integer|min:0',
            'shopify_variant_id' => 'nullable',
            'mapping_id'         => 'nullable|integer',
            'baseline_quantity'  => 'nullable|integer',
        ]);

        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        // Use currently selected Shopify Location
        $locations = $shop->shopify_locations ?? [];
        $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;
        $selectedLocation = $locations[$selectedIndex] ?? null;
        $locationId = $selectedLocation['id'] ?? null;

        if (!$locationId) {
            Log::warning('SHOPIFY SELECTED LOCATION NOT FOUND', [
                'shop_id'                 => $shop->id,
                'selected_location_index' => $shop->selected_location_index,
                'effective_index'         => $selectedIndex,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No Shopify location found for this store.'
            ], 422);
        }

        // Check existing mapping scoped strictly to active shop
        $mapping = null;
        if ($request->filled('mapping_id')) {
            $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)->find($request->mapping_id);
        } elseif ($request->filled('shopify_variant_id')) {
            $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
                ->where('shopify_variant_id', (string) $request->shopify_variant_id)
                ->first();
        } elseif ($request->filled('inventory_item_id')) {
            $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
                ->where('shopify_inventory_item_id', (string) $request->inventory_item_id)
                ->first();
        }

        $expectedVersion = (int) ($mapping?->inventory_version ?? 1);

        $baselineQuantity = null;
        if ($request->has('baseline_quantity') && $request->baseline_quantity !== null && $request->baseline_quantity !== '') {
            $baselineQuantity = (int) $request->baseline_quantity;
        } elseif ($mapping && $mapping->quantity !== null && $mapping->quantity !== '') {
            $baselineQuantity = (int) $mapping->quantity;
        }

        // -------------------------------------------------------------
        // TRANSACTIONAL OUTBOX: Persist desired final quantity in DB
        // -------------------------------------------------------------
        $operation = DB::transaction(function () use ($shop, $mapping, $request, $locationId, $baselineQuantity, $expectedVersion) {
            // Latest-wins: Supersede any older pending operations for the same item
            InventorySyncOperation::where('shop_id', $shop->id)
                ->where('shopify_inventory_item_id', (string) $request->inventory_item_id)
                ->where('status', 'pending')
                ->update([
                    'status'     => 'superseded',
                    'last_error' => 'Superseded by newer manual update.',
                ]);

            return InventorySyncOperation::create([
                'operation_uuid'             => (string) Str::uuid(),
                'shop_id'                    => $shop->id,
                'mapping_id'                 => $mapping?->id,
                'shopify_inventory_item_id'  => (string) $request->inventory_item_id,
                'shopify_location_id'        => (string) $locationId,
                'amazon_sku'                 => $mapping?->amazon_sku,
                'desired_quantity'           => (int) $request->quantity,
                'baseline_quantity'          => $baselineQuantity,
                'expected_inventory_version' => $expectedVersion,
                'source'                     => 'manual_ui',
                'status'                     => 'pending',
                'stage'                      => 'pending',
                'attempts'                   => 0,
                'max_attempts'               => 4,
                'created_by'                 => auth()->id(),
                'last_dispatched_at'         => now(),
            ]);
        });

        // Dispatch background processing job after DB transaction has committed
        ProcessInventoryUpdateJob::dispatch($operation->id);

        // Invalidate cache
        Cache::forget("shopify_inventory_{$shop->shop}_location_{$selectedIndex}");

        return response()->json([
            'success'      => true,
            'status'       => 'pending',
            'operation_id' => $operation->id,
            'message'      => 'Inventory update queued successfully.',
        ]);
    }


    public function unmap(Request $request, ProductMarketplaceMapping $mapping)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        if ((int) $mapping->shop_id !== (int) $shop->id) {
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

        $syncUsage = app(SyncLimitService::class)->canMap($shop);

        return response()->json([
            'success' => true,
            'message' => 'Product unmapped successfully.',
            'sync_usage' => $syncUsage,
        ]);
    }
}
