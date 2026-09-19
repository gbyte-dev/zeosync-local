<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInventoryUpdateJob;
use App\Models\InventorySyncOperation;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use App\Services\SyncLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        // Log::info('Saved Record', $mapping->fresh()->toArray());

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

        // Log::info('UpdateOrCreate Payload', $updateData);

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
        Log::info('Shopify inventory update: 1. Request received', [
            'shop' => $request->shop ?? $request->query('shop'),
            'inventory_item_id' => $request->inventory_item_id,
            'quantity' => $request->quantity,
        ]);

        try {
            $request->validate([
                'shop' => 'nullable',
                'inventory_item_id' => 'required',
                'quantity' => 'required|integer|min:0',
                'shopify_variant_id' => 'nullable',
                'mapping_id' => 'nullable|integer',
                'baseline_quantity' => 'nullable|integer',
            ]);

            Log::info('Shopify inventory update: 2. Request validation passed', [
                'inventory_item_id' => $request->inventory_item_id,
                'quantity' => $request->quantity,
            ]);

            $shop = $this->getActiveShopModel($request);
            if (!$shop) {
                Log::warning('Shopify inventory update: Active shop resolution returned null', [
                    'shop_param' => $request->shop ?? $request->query('shop'),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized or shop not found.'
                ], 401);
            }

            Log::info('Shopify inventory update: 3. Active shop resolved', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
            ]);

            // Use currently selected Shopify Location
            $locations = $shop->shopify_locations ?? [];
            $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
                ? (int) $shop->selected_location_index
                : 0;
            $selectedLocation = $locations[$selectedIndex] ?? null;
            $locationId = $selectedLocation['id'] ?? null;

            // If locations are missing or selected location could not be resolved, self-heal by refreshing from Shopify
            if (!$locationId) {
                try {
                    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);
                    $locResponse = $shopifyService->getLocations($shop);
                    if (empty($locResponse['error']) && !empty($locResponse['locations'])) {
                        $fetchedLocations = $locResponse['locations'];
                        $effectiveIndex = (isset($shop->selected_location_index) && isset($fetchedLocations[$shop->selected_location_index]))
                            ? (int) $shop->selected_location_index
                            : 0;
                        $shop->update([
                            'shopify_locations' => $fetchedLocations,
                            'selected_location_index' => $effectiveIndex,
                        ]);
                        $shop->refresh();
                        $locations = $shop->shopify_locations ?? [];
                        $selectedIndex = $effectiveIndex;
                        $selectedLocation = $locations[$selectedIndex] ?? null;
                        $locationId = $selectedLocation['id'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::warning('SHOPIFY LOCATIONS SELF-HEAL FAILED', [
                        'shop_id' => $shop->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!$locationId) {
                Log::warning('SHOPIFY SELECTED LOCATION NOT FOUND', [
                    'shop_id' => $shop->id,
                    'selected_location_index' => $shop->selected_location_index,
                    'effective_index' => $selectedIndex,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No Shopify location found for this store.'
                ], 422);
            }

            Log::info('Shopify inventory update: 4. Shopify location resolved', [
                'shop_id' => $shop->id,
                'location_id' => $locationId,
                'index' => $selectedIndex,
            ]);

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

            Log::info('Shopify inventory update: 5. Inventory mapping found', [
                'shop_id' => $shop->id,
                'mapping_id' => $mapping?->id,
                'amazon_sku' => $mapping?->amazon_sku,
            ]);

            $expectedVersion = (int) ($mapping?->inventory_version ?? 1);

            $baselineQuantity = null;
            if ($request->has('baseline_quantity') && $request->baseline_quantity !== null && $request->baseline_quantity !== '') {
                $baselineQuantity = (int) $request->baseline_quantity;
            } else {
                // Fetch fresh authoritative Shopify baseline when not provided by the caller
                try {
                    $shopifyService = new ShopifyService($shop->shop, $shop->access_token);
                    $levelsResponse = $shopifyService->getInventoryLevel(
                        $shop,
                        $request->inventory_item_id,
                        $locationId
                    );

                    $levels = $levelsResponse['inventory_levels'] ?? [];
                    $liveLevel = null;
                    foreach ($levels as $lvl) {
                        if ((string) ($lvl['location_id'] ?? '') === (string) $locationId) {
                            $liveLevel = $lvl;
                            break;
                        }
                    }
                    if (!$liveLevel && !empty($levels)) {
                        $liveLevel = $levels[0];
                    }

                    if (isset($liveLevel['available']) && $liveLevel['available'] !== null) {
                        $baselineQuantity = (int) $liveLevel['available'];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Shopify inventory update: Failed to fetch live baseline', [
                        'shop_id' => $shop->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($baselineQuantity === null && $mapping && $mapping->quantity !== null && $mapping->quantity !== '') {
                    $baselineQuantity = (int) $mapping->quantity;
                }
            }

            Log::info('Shopify inventory update: 6. InventorySyncOperation database record creation started', [
                'shop_id' => $shop->id,
                'inventory_item_id' => $request->inventory_item_id,
                'desired_quantity' => (int) $request->quantity,
            ]);

            // -------------------------------------------------------------
            // TRANSACTIONAL OUTBOX: Persist desired final quantity in DB
            // -------------------------------------------------------------
            $operation = DB::transaction(function () use ($shop, $mapping, $request, $locationId, $baselineQuantity, $expectedVersion) {
                // Latest-wins: Supersede any older pending operations for the same item
                InventorySyncOperation::where('shop_id', $shop->id)
                    ->where('shopify_inventory_item_id', (string) $request->inventory_item_id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'superseded',
                        'last_error' => 'Superseded by newer manual update.',
                    ]);

                return InventorySyncOperation::create([
                    'operation_uuid' => (string) Str::uuid(),
                    'source_key' => 'manual:' . Str::uuid(),
                    'shop_id' => $shop->id,
                    'mapping_id' => $mapping?->id,
                    'shopify_inventory_item_id' => (string) $request->inventory_item_id,
                    'shopify_location_id' => (string) $locationId,
                    'amazon_sku' => $mapping?->amazon_sku,
                    'desired_quantity' => (int) $request->quantity,
                    'baseline_quantity' => $baselineQuantity,
                    'expected_inventory_version' => $expectedVersion,
                    'source' => 'manual_ui',
                    'status' => 'pending',
                    'stage' => 'pending',
                    'attempts' => 0,
                    'max_attempts' => 4,
                    'last_dispatched_at' => now(),
                ]);
            });

            // Log::info('Shopify inventory update: 7. InventorySyncOperation database record created', [
            //     'shop_id'        => $shop->id,
            //     'operation_id'   => $operation->id,
            //     'operation_uuid' => $operation->operation_uuid,
            // ]);

            // Log::info('Shopify inventory update: 8. ProcessInventoryUpdateJob dispatch started', [
            //     'shop_id'      => $shop->id,
            //     'operation_id' => $operation->id,
            // ]);

            // Dispatch background processing job after DB transaction has committed
            ProcessInventoryUpdateJob::dispatch($operation->id);

            Log::info('Shopify inventory update: 9. ProcessInventoryUpdateJob dispatched', [
                'shop_id' => $shop->id,
                'operation_id' => $operation->id,
            ]);

            // Invalidate cache
            Cache::forget("shopify_inventory_{$shop->shop}_location_{$selectedIndex}");

            // Log::info('Shopify inventory update: 10. Cache invalidation completed', [
            //     'shop'      => $shop->shop,
            //     'cache_key' => "shopify_inventory_{$shop->shop}_location_{$selectedIndex}",
            // ]);

            // Log::info('Shopify inventory update: 11. Shopify inventory update flow completed', [
            //     'shop_id'      => $shop->id,
            //     'operation_id' => $operation->id,
            // ]);

            return response()->json([
                'success' => true,
                'status' => 'pending',
                'operation_id' => $operation->id,
                'message' => 'Inventory update queued successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Log::error('Shopify inventory update failed', [
            //     'shop'              => $shop->shop ?? $request->shop ?? $request->query('shop'),
            //     'inventory_item_id' => $request->inventory_item_id ?? null,
            //     'quantity'          => $request->quantity ?? null,
            //     'error'             => $e->getMessage(),
            //     'file'              => $e->getFile(),
            //     'line'              => $e->getLine(),
            // ]);

            if (isset($operation) && $operation instanceof InventorySyncOperation) {
                try {
                    $operation->update([
                        'status' => 'failed',
                        'last_error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Shopify inventory update failed: ' . $e->getMessage(),
            ], 422);
        }
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

        // Log::info('========== UNMAP START ==========', [
        //     'shop_id' => $shop->id,
        //     'mapping_id' => $mapping->id,
        //     'amazon_sku' => $mapping->amazon_sku,
        //     'shopify_variant_id' => $mapping->shopify_variant_id,
        // ]);

        $mapping->delete();

        // Log::info('========== UNMAP SUCCESS ==========', [
        //     'shop_id' => $shop->id,
        //     'mapping_id' => $mapping->id,
        // ]);

        $syncUsage = app(SyncLimitService::class)->canMap($shop);

        return response()->json([
            'success' => true,
            'message' => 'Product unmapped successfully.',
            'sync_usage' => $syncUsage,
        ]);
    }
}
