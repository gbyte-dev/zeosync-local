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
use Illuminate\Support\Facades\Queue;
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

        $rawVariants = $product->variants;

        if (!is_array($rawVariants)) {
            $rawVariants = json_decode($rawVariants, true) ?? [];
        }

        $hasVariants = !empty($rawVariants);
        $response = [];

        foreach ($rawVariants as $variant) {
            if (in_array((string) $variant['id'], $mapped, true)) {
                continue;
            }

            $response[] = [
                'id' => $variant['id'],
                'title' => $variant['title'] ?? ('Variant #' . ($variant['id'] ?? '')),
                'inventory_item_id' => $variant['inventory_item_id'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'has_variants' => $hasVariants,
            'total_variants_count' => count($rawVariants),
            'available_variants_count' => count($response),
            'variants' => $response,
            'shopify_product_id' => $product->shopify_id ?: (string) $product->id,
        ]);
    }

    /**
     * Resolve the authoritative current Shopify location ID for a shop.
     */
    public function resolveCurrentShopifyLocationId(Shop $shop): ?string
    {
        $locations = $shop->shopify_locations ?? [];
        $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;
        $selectedLocation = $locations[$selectedIndex] ?? null;
        $locationId = $selectedLocation['id'] ?? null;

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
                Log::warning('Shopify locations resolution failed during mapping', [
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $locationId ? (string) $locationId : null;
    }

    public function saveProductMapping(Request $request)
    {
        $request->validate([
            'shop' => 'nullable',
            'amazon_sku' => 'required',
            'product_id' => 'required',
            'variant_id' => 'nullable',
            'shopify_product_id' => 'nullable',
            'shopify_variant_id' => 'nullable',
            'shopify_inventory_item_id' => 'nullable',
            'shopify_location_id' => 'nullable',
        ]);

        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or shop not found.'
            ], 401);
        }

        $product = Product::where('shop_id', $shop->id)->find($request->product_id);
        if (!$product) {
            $product = Product::where('shop_id', $shop->id)->where('shopify_id', $request->product_id)->first();
        }
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or does not belong to this shop.'
            ], 404);
        }

        $rawVariants = $product->variants;
        if (!is_array($rawVariants)) {
            $rawVariants = json_decode($rawVariants, true) ?? [];
        }

        $shopifyProductId = (string) ($product->shopify_id ?: $product->id);
        $effectiveVariantId = null;
        $effectiveInventoryItemId = null;
        $quantity = 0;

        if (!empty($rawVariants)) {
            // Case A: Selected Shopify product HAS variants -> variant selection is required
            $requestedVariantId = (string) ($request->variant_id ?: $request->shopify_variant_id);
            if (empty($requestedVariantId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a variant for this product.'
                ], 422);
            }

            $selectedVariant = collect($rawVariants)->first(function ($v) use ($requestedVariantId) {
                return (string) ($v['id'] ?? '') === $requestedVariantId;
            });

            if (!$selectedVariant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected variant not found for this product.'
                ], 422);
            }

            $effectiveVariantId = (string) $selectedVariant['id'];
            $effectiveInventoryItemId = (string) ($selectedVariant['inventory_item_id'] ?? $request->shopify_inventory_item_id ?? '');
            $quantity = (int) ($selectedVariant['inventory_quantity'] ?? 0);
        } else {
            // Case B: Selected Shopify product has NO variants -> use product ID as shopify_variant_id
            $effectiveVariantId = $shopifyProductId;
            $effectiveInventoryItemId = (string) ($request->shopify_inventory_item_id ?? '');
            $quantity = 0;
        }

        $exists = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('shopify_variant_id', $effectiveVariantId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Variant already mapped.'
            ], 422);
        }

        $amazonSkuExists = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('amazon_sku', $request->amazon_sku)
            ->exists();

        if ($amazonSkuExists) {
            return response()->json([
                'success' => false,
                'message' => 'This Amazon SKU is already mapped with another Shopify variant.'
            ], 422);
        }

        // Resolve location: per-mapping override if provided, otherwise settings default
        $locationId = null;
        $requestedLocationId = $request->input('shopify_location_id');

        $locations = $shop->shopify_locations ?? [];
        if (!is_array($locations)) {
            $locations = json_decode($locations, true) ?? [];
        }

        if (!empty($requestedLocationId) && !empty($locations)) {
            foreach ($locations as $loc) {
                $locId = (string) ($loc['id'] ?? '');
                if ($locId === (string) $requestedLocationId || (basename($locId) !== '' && basename($locId) === basename((string) $requestedLocationId))) {
                    $locationId = $locId;
                    break;
                }
            }

            if (!$locationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected Shopify location is invalid or does not belong to this shop.'
                ], 422);
            }
        }

        if (!$locationId) {
            $locationId = $this->resolveCurrentShopifyLocationId($shop);
        }

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'No Shopify location found for this store.'
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

        $insertData = [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'variant_id' => $effectiveVariantId,
            'shopify_product_id' => $shopifyProductId,
            'shopify_variant_id' => $effectiveVariantId,
            'shopify_inventory_item_id' => $effectiveInventoryItemId ?: null,
            'shopify_location_id' => (string) $locationId,
            'amazon_sku' => $request->amazon_sku,
            'amazon_parent_sku' => $request->amazon_parent_sku ?: $request->amazon_sku,
            'quantity' => $quantity,
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

        Log::info('Shopify product mapping saved', [
            'shop_id'             => $shop->id,
            'mapping_id'          => $mapping->id,
            'amazon_sku'          => $mapping->amazon_sku,
            'shopify_product_id'  => $mapping->shopify_product_id,
            'shopify_variant_id'  => $mapping->shopify_variant_id,
            'shopify_location_id' => $mapping->shopify_location_id,
        ]);

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

        $mappings = ProductMarketplaceMapping::mappedForShop($shop->id)
            ->with('product')
            ->orderBy('id', 'desc')
            ->get();

        $shopifyProductIds = $mappings->pluck('shopify_product_id')->filter()->unique();

        $productsByShopifyId = collect();
        if ($shopifyProductIds->isNotEmpty()) {
            $productsByShopifyId = Product::where('shop_id', $shop->id)
                ->whereIn('shopify_id', $shopifyProductIds)
                ->get()
                ->keyBy(fn($p) => (string) $p->shopify_id);
        }

        $locations = is_array($shop->shopify_locations)
            ? $shop->shopify_locations
            : (json_decode($shop->shopify_locations, true) ?? []);

        $enrichedMappings = $mappings->map(function ($mapping) use ($shop, $productsByShopifyId, $locations) {
            $product = $mapping->product ?? ($mapping->shopify_product_id ? $productsByShopifyId->get((string) $mapping->shopify_product_id) : null);

            $shopifyProductId = $mapping->shopify_product_id ?? ($product ? ($product->shopify_id ?: $product->id) : $mapping->product_id);
            $shopifyProductTitle = $product ? $product->title : null;
            if (empty($shopifyProductTitle) && !empty($shopifyProductId)) {
                $shopifyProductTitle = 'Shopify Product #' . $shopifyProductId;
            }

            // Variant Title
            $shopifyVariantTitle = null;
            $shopifyVariantSku = null;
            if ($product && !empty($product->variants)) {
                $variants = is_array($product->variants) ? $product->variants : (json_decode($product->variants, true) ?? []);
                $matchedVariant = collect($variants)->first(fn($v) => (string) ($v['id'] ?? '') === (string) $mapping->shopify_variant_id);
                if ($matchedVariant) {
                    $shopifyVariantTitle = $matchedVariant['title'] ?? null;
                    $shopifyVariantSku = $matchedVariant['sku'] ?? null;
                }
            }
            if (empty($shopifyVariantTitle)) {
                if ((string) $mapping->shopify_variant_id === (string) $shopifyProductId) {
                    $shopifyVariantTitle = 'Default';
                } else {
                    $shopifyVariantTitle = $mapping->shopify_variant_id;
                }
            }

            // Location Name
            $locationName = 'Default';
            if (!empty($mapping->shopify_location_id)) {
                foreach ($locations as $loc) {
                    $locId = (string) ($loc['id'] ?? '');
                    if ($locId === (string) $mapping->shopify_location_id || (!empty($locId) && str_ends_with($locId, (string) $mapping->shopify_location_id))) {
                        $locationName = $loc['name'] ?? 'Default';
                        break;
                    }
                }
            } elseif (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index])) {
                $locationName = $locations[$shop->selected_location_index]['name'] ?? 'Default';
            }

            $shopifyProductUrl = !empty($shopifyProductId)
                ? route('shopify.product.view', ['id' => $shopifyProductId, 'shop' => $shop->shop])
                : null;

            $amazonProductUrl = !empty($mapping->amazon_sku)
                ? route('user.product.amazonView', ['sku' => $mapping->amazon_sku, 'shop' => $shop->shop])
                : null;

            return [
                'id' => $mapping->id,
                'product_id' => $mapping->product_id,
                'shopify_product_id' => $shopifyProductId,
                'shopify_product_title' => $shopifyProductTitle,
                'shopify_product_url' => $shopifyProductUrl,
                'shopify_variant_id' => $mapping->shopify_variant_id,
                'shopify_variant_title' => $shopifyVariantTitle,
                'shopify_variant_sku' => $shopifyVariantSku,
                'shopify_inventory_item_id' => $mapping->shopify_inventory_item_id,
                'shopify_location_id' => $mapping->shopify_location_id,
                'shopify_location_name' => $locationName,
                'amazon_sku' => $mapping->amazon_sku,
                'amazon_product_url' => $amazonProductUrl,
                'quantity' => $mapping->quantity,
                'sync_status' => $mapping->sync_status ?? 'active',
                'last_synced_at' => $mapping->last_synced_at ? $mapping->last_synced_at->format('M d, Y h:i A') : null,
                'last_synced_at_raw' => $mapping->last_synced_at ? $mapping->last_synced_at->toISOString() : null,
            ];
        });

        $syncUsage = app(SyncLimitService::class)->canMap($shop);

        return response()->json([
            'success' => true,
            'mappings' => $enrichedMappings,
            'sync_usage' => $syncUsage,
        ]);
    }

    public function saveAmazonMapping(Request $request)
    {
        $request->validate([
            'shop' => 'nullable',
            'product_id' => 'required',
            'shopify_variant_id' => 'nullable',
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
            $product = Product::where('shop_id', $shop->id)->find($request->product_id);
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or does not belong to this shop.'
            ], 404);
        }

        $rawVariants = $product->variants;
        if (!is_array($rawVariants)) {
            $rawVariants = json_decode($rawVariants, true) ?? [];
        }

        $shopifyProductId = (string) ($product->shopify_id ?: $product->id);
        $effectiveVariantId = null;
        $effectiveInventoryItemId = null;
        $quantity = '0';

        if (!empty($rawVariants)) {
            $requestedVariantId = (string) $request->shopify_variant_id;
            if (empty($requestedVariantId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected Shopify variant not found.'
                ], 422);
            }

            $variant = collect($rawVariants)->first(function ($v) use ($requestedVariantId) {
                return (string) ($v['id'] ?? '') === $requestedVariantId;
            });

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected Shopify variant not found.'
                ], 404);
            }

            $effectiveVariantId = (string) $variant['id'];
            $effectiveInventoryItemId = (string) ($variant['inventory_item_id'] ?? '');
            $quantity = (string) ($variant['inventory_quantity'] ?? 0);
        } else {
            $effectiveVariantId = $shopifyProductId;
            $effectiveInventoryItemId = (string) ($request->shopify_inventory_item_id ?? '');
            $quantity = '0';
        }

        $amazonExists = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('amazon_sku', $request->amazon_sku)
            ->where('shopify_variant_id', '!=', $effectiveVariantId)
            ->exists();

        if ($amazonExists) {
            return response()->json([
                'success' => false,
                'message' => 'This Amazon SKU is already mapped with another Shopify variant.'
            ], 422);
        }

        $locationId = $this->resolveCurrentShopifyLocationId($shop);
        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'No Shopify location found for this store.'
            ], 422);
        }

        $existingMapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('shopify_variant_id', $effectiveVariantId)
            ->first();

        if (!$existingMapping) {
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
        }

        $updateData = [
            'product_id' => $product->id,
            'variant_id' => $effectiveVariantId,
            'shopify_product_id' => $shopifyProductId,
            'shopify_variant_id' => $effectiveVariantId,
            'shopify_inventory_item_id' => $effectiveInventoryItemId ?: null,
            'shopify_location_id' => (string) $locationId,
            'amazon_sku' => $request->amazon_sku,
            'amazon_parent_sku' => $request->amazon_parent_sku ?: $request->amazon_sku,
            'quantity' => $quantity,
        ];

        try {
            $mapping = ProductMarketplaceMapping::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'shopify_variant_id' => $effectiveVariantId,
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

        Log::info('Amazon mapping saved', [
            'shop_id'             => $shop->id,
            'mapping_id'          => $mapping->id,
            'amazon_sku'          => $mapping->amazon_sku,
            'shopify_product_id'  => $mapping->shopify_product_id,
            'shopify_variant_id'  => $mapping->shopify_variant_id,
            'shopify_location_id' => $mapping->shopify_location_id,
        ]);

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
        $dbName = DB::connection()->getDatabaseName();
        $qConn = Queue::connection('database');
        $queueDbName = method_exists($qConn, 'getDatabase') ? $qConn->getDatabase()->getDatabaseName() : 'faked_or_default';
        $appQueueDefault = config('queue.default');

        Log::info('INV_TRACE_01_REQUEST', [
            'app_db'              => $dbName,
            'queue_db'            => $queueDbName,
            'queue_default'       => $appQueueDefault,
            'php_version'         => PHP_VERSION,
            'php_ini'             => php_ini_loaded_file(),
            'hostname'            => gethostname(),
            'shop_param'          => $request->shop ?? $request->query('shop'),
            'inventory_item_id'   => $request->inventory_item_id,
            'quantity'            => $request->quantity,
            'baseline_quantity'   => $request->baseline_quantity,
            'mapping_id'          => $request->mapping_id,
            'shopify_variant_id'  => $request->shopify_variant_id,
            'all_params'          => $request->all(),
            'bearer_token'        => $request->bearerToken() ? 'PRESENT' : 'NONE',
            'session_verified_shop' => session('_shopify_verified_shop'),
            'session_active_shop' => session('active_shop'),
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

            Log::info('INV_TRACE_02_VALIDATED', [
                'inventory_item_id' => $request->inventory_item_id,
                'quantity' => $request->quantity,
            ]);

            $shop = $this->getActiveShopModel($request);
            if (!$shop) {
                Log::warning('INV_TRACE_03_SHOP_FAILED', [
                    'shop_param' => $request->shop ?? $request->query('shop'),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized or shop not found.'
                ], 401);
            }

            Log::info('INV_TRACE_03_SHOP', [
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
                Log::warning('INV_TRACE_04_LOCATION_FAILED', [
                    'shop_id' => $shop->id,
                    'selected_location_index' => $shop->selected_location_index,
                    'effective_index' => $selectedIndex,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No Shopify location found for this store.'
                ], 422);
            }

            Log::info('INV_TRACE_04_LOCATION', [
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

            Log::info('INV_TRACE_05_MAPPING', [
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

            Log::info('INV_TRACE_06_BASELINE', [
                'shop_id' => $shop->id,
                'inventory_item_id' => $request->inventory_item_id,
                'baseline_quantity' => $baselineQuantity,
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

                $op = InventorySyncOperation::create([
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

                Log::info('INV_TRACE_07_OPERATION_CREATED', [
                    'shop_id'        => $shop->id,
                    'operation_id'   => $op->id,
                    'operation_uuid' => $op->operation_uuid,
                ]);

                return $op;
            });

            Log::info('INV_TRACE_08_TRANSACTION_COMMITTED', [
                'shop_id'        => $shop->id,
                'operation_id'   => $operation->id,
                'operation_uuid' => $operation->operation_uuid,
            ]);

            Log::info('INV_TRACE_09_BEFORE_DISPATCH', [
                'shop_id'      => $shop->id,
                'operation_id' => $operation->id,
                'target_conn'  => 'database',
                'target_queue' => 'default',
            ]);

            // Dispatch background processing job after DB transaction has committed
            $pendingJob = ProcessInventoryUpdateJob::dispatch($operation->id)
                ->onConnection('database')
                ->onQueue('default');

            $latestJob = DB::table('jobs')->orderByDesc('id')->first();

            Log::info('INV_TRACE_10_AFTER_DISPATCH', [
                'shop_id'      => $shop->id,
                'operation_id' => $operation->id,
                'pending_job_class' => get_debug_type($pendingJob),
                'latest_job_id' => $latestJob?->id,
                'latest_job_queue' => $latestJob?->queue,
                'latest_job_payload' => $latestJob ? substr($latestJob->payload, 0, 200) : null,
            ]);

            // Invalidate cache
            Cache::forget("shopify_inventory_{$shop->shop}_location_{$selectedIndex}");

            $responsePayload = [
                'success' => true,
                'status' => 'pending',
                'operation_id' => $operation->id,
                'message' => 'Inventory update queued successfully.',
            ];

            Log::info('INV_TRACE_11_RESPONSE', $responsePayload);

            return response()->json($responsePayload);
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
