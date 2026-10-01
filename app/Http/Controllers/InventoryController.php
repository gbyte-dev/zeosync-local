<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShopifyController;
use App\Models\AdminSetting;
use App\Models\AllProduct;
use App\Models\AmazonProduct;
use App\Models\InventorySyncOperation;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\AmazonInventoryReportService;
use App\Services\AmazonProductQueryService;
use App\Services\AmazonService;
use App\Services\AutoSkuMappingService;
use App\Services\InventoryCacheService;
use App\Services\ShopifyInventoryService;
use App\Services\ShopifyService;
use App\Services\SyncLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductMapping;

class InventoryController extends ShopifyController
{
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

    public function index(Request $request)
    {
        $shop = $this->getActiveShopModel($request);

        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }

        session([ 'shop' => $shop->shop, 'access_token' => $shop->access_token,
            'region' => $shop->amazon_mws_region   ]);

        $inventories = [];
        $mappedproducts = ProductMarketplaceMapping::mappedForShop($shop->id)->with('product')->orderBy('id', 'desc')->get();

        // Sync Usage
        $syncUsage = app(SyncLimitService::class)->canMap($shop);

        $allowedLengths = [10, 25, 50, 100];
        $rawShopifyLength = (int) session("inventory_page_length_{$shop->id}.shopify", 10);
        $rawAmazonLength = (int) session("inventory_page_length_{$shop->id}.amazon", 10);
        $rawAmazonProductsLength = (int) session("inventory_page_length_{$shop->id}.amazon_products", 10);

        $shopifyPageLength = in_array($rawShopifyLength, $allowedLengths, true) ? $rawShopifyLength : 10;
        $amazonPageLength = in_array($rawAmazonLength, $allowedLengths, true) ? $rawAmazonLength : 10;
        $amazonProductsPageLength = in_array($rawAmazonProductsLength, $allowedLengths, true) ? $rawAmazonProductsLength : 10;

        return view('inventory.index', compact( 'inventories', 'shop', 'syncUsage',
         'shopifyPageLength',  'amazonPageLength', 'amazonProductsPageLength', 'mappedproducts'  ));
    }

    public function updatePageLength(Request $request)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Active shop not resolved.',
            ], 401);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:shopify,amazon,amazon_products'],
            'length' => ['required', 'integer', 'in:10,25,50,100'],
        ]);

        $type = $validated['type'];
        $length = (int) $validated['length'];

        session([
            "inventory_page_length_{$shop->id}.{$type}" => $length,
        ]);

        return response()->json([
            'success' => true,
            'type' => $type,
            'length' => $length,
        ]);
    }

    public function shopify( Request $request,  ShopifyInventoryService $shopifyInventoryService
    ) {
        $shopModel = $this->getActiveShopModel($request);
        if (!$shopModel) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Active shop not resolved.',
            ], 401);
        }

        $this->ensureFreshAccessToken($shopModel);

        $data = $shopifyInventoryService->getInventory($shopModel);

        // Overlay authoritative database mapping state onto cached Shopify variants
        $mappings = ProductMarketplaceMapping::where('shop_id', $shopModel->id)
            ->get(['id', 'shopify_variant_id', 'amazon_sku'])
            ->keyBy(fn($m) => (string) $m->shopify_variant_id);

        if (is_array($data)) {
            foreach ($data as &$item) {
                $vid = (string) ($item['vid'] ?? '');
                $mapping = $mappings->get($vid);

                $isMapped = $mapping &&
                    !empty($mapping->shopify_variant_id) &&
                    !empty($mapping->amazon_sku);

                $item['is_mapped'] = $isMapped;
                $item['mapped_sku'] = $isMapped ? $mapping->amazon_sku : null;
                $item['mapping_id'] = $isMapped ? $mapping->id : null;
            }
            unset($item);
        }

        $amazonInventory = !empty($shopModel->amazon_seller_id)
            ? Cache::get("amazon_inventory_{$shopModel->id}_{$shopModel->amazon_seller_id}", [])
            : [];

        app(AutoSkuMappingService::class)->handle(
            $shopModel,   $data,  $amazonInventory
        );

        return response()->json($data);
    }

    /**
     * Amazon Inventory
     */
    public function amazon(Request $request)
    {
        $shop = $this->getActiveShopModel($request);

        if (!$shop) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Shop not resolved.'
            ], 401);
        }

        if (empty($shop->amazon_refresh_token)) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'status' => [
                    'connected' => false,
                    'refreshing' => false,
                    'sync_completed' => false,
                    'error' => 'amazon_not_connected',
                ],
                'message' => 'Please connect your Amazon account first.',
                'products' => [],
            ]);
        }

        $inventoryCacheService = app(InventoryCacheService::class);

        $response = $inventoryCacheService->getAmazonInventory(
            $shop,   $shop->amazon_marketplace_id
        );

        $products = $response['products'] ?? [];

        // Overlay authoritative database mapping state onto cached Amazon products
        $mappings = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->get(['id', 'amazon_sku', 'shopify_variant_id', 'shopify_product_id', 'shopify_location_id', 'quantity', 'submission_status', 'sync_status'])
            ->keyBy(fn($m) => (string) $m->amazon_sku);

        $activeVerifications = InventorySyncOperation::where('shop_id', $shop->id)
            ->whereIn('status', ['awaiting_verification', 'processing'])
            ->pluck('mapping_id')
            ->filter()
            ->flip();

        $shopifyProductIds = $mappings->pluck('shopify_product_id')->filter()->unique();
        $productsByShopifyId = collect();
        if ($shopifyProductIds->isNotEmpty()) {
            $productsByShopifyId = Product::where('shop_id', $shop->id)
                ->whereIn('shopify_id', $shopifyProductIds)
                ->get(['id', 'shopify_id', 'title', 'variants', 'images'])
                ->keyBy(fn($p) => (string) $p->shopify_id);
        }

        $locations = is_array($shop->shopify_locations)
            ? $shop->shopify_locations
            : (json_decode($shop->shopify_locations, true) ?? []);

        $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        $shopifyCachedInventory = Cache::get(
            "shopify_inventory_{$shop->shop}_location_{$selectedIndex}",
            []
        );
        $shopifyInventoryByVid = collect(is_array($shopifyCachedInventory) ? $shopifyCachedInventory : [])
            ->keyBy(fn($i) => (string) ($i['vid'] ?? ''));

        if (is_array($products)) {
            foreach ($products as &$item) {
                $sku = (string) ($item['sku'] ?? '');
                $mapping = $mappings->get($sku);

                $isMapped = $mapping &&
                    !empty($mapping->shopify_variant_id) &&
                    !empty($mapping->amazon_sku);

                $isVerifying = false;
                if ($mapping) {
                    $isVerifying = ($mapping->submission_status === 'accepted')
                        || isset($activeVerifications[$mapping->id]);
                }

                $item['is_mapped'] = $isMapped;
                $item['mapping_id'] = $isMapped ? $mapping->id : null;
                $item['mapped_shopify_variant_id'] = $isMapped ? $mapping->shopify_variant_id : null;
                $item['mapped_shopify_product_id'] = $isMapped ? $mapping->shopify_product_id : null;
                $item['is_verifying'] = $isVerifying;
                $item['submission_status'] = $mapping ? $mapping->submission_status : null;

                // Enriched mapping fields for Amazon Products tab
                if ($isMapped) {
                    $shopifyProd = $mapping->shopify_product_id ? $productsByShopifyId->get((string) $mapping->shopify_product_id) : null;
                    $shopifyProdTitle = $shopifyProd ? $shopifyProd->title : null;
                    if (empty($shopifyProdTitle) && !empty($mapping->shopify_product_id)) {
                        $shopifyProdTitle = 'Shopify Product #' . $mapping->shopify_product_id;
                    }

                    $shopifyVarTitle = null;
                    $shopifyVarSku = null;
                    if ($shopifyProd && !empty($shopifyProd->variants)) {
                        $vars = is_array($shopifyProd->variants) ? $shopifyProd->variants : (json_decode($shopifyProd->variants, true) ?? []);
                        $matchedVar = collect($vars)->first(fn($v) => (string) ($v['id'] ?? '') === (string) $mapping->shopify_variant_id);
                        if ($matchedVar) {
                            $shopifyVarTitle = $matchedVar['title'] ?? null;
                            $shopifyVarSku = $matchedVar['sku'] ?? null;
                        }
                    }
                    if (empty($shopifyVarTitle)) {
                        if ((string) $mapping->shopify_variant_id === (string) $mapping->shopify_product_id) {
                            $shopifyVarTitle = 'Default';
                        } else {
                            $shopifyVarTitle = $mapping->shopify_variant_id;
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

                    // Shopify Available Quantity from cache
                    $shopifyInvItem = $shopifyInventoryByVid->get((string) $mapping->shopify_variant_id);
                    $shopifyAvailableQty = $shopifyInvItem ? ($shopifyInvItem['available'] ?? null) : null;

                    $item['mapped_shopify_product_title'] = $shopifyProdTitle;
                    $item['mapped_shopify_variant_title'] = $shopifyVarTitle;
                    $item['mapped_shopify_variant_sku'] = $shopifyVarSku;
                    $item['mapped_shopify_location_name'] = $locationName;
                    $item['shopify_available_qty'] = $shopifyAvailableQty;
                    $item['mapped_shopify_product_url'] = !empty($mapping->shopify_product_id)
                        ? route('shopify.product.view', ['id' => $mapping->shopify_product_id, 'shop' => $shop->shop])
                        : null;
                } else {
                    $item['mapped_shopify_product_title'] = null;
                    $item['mapped_shopify_variant_title'] = null;
                    $item['mapped_shopify_variant_sku'] = null;
                    $item['mapped_shopify_location_name'] = null;
                    $item['shopify_available_qty'] = null;
                    $item['mapped_shopify_product_url'] = null;
                }

                $item['amazon_product_url'] = !empty($sku)
                    ? route('user.product.amazonView', ['sku' => $sku, 'shop' => $shop->shop])
                    : null;

                // Only overlay mapping quantity if verification is NOT active and mapping has not failed/mismatched
                if ($isMapped && !$isVerifying && !in_array($mapping->submission_status, ['mismatch', 'failed', 'rejected'], true)) {
                    if ($mapping->quantity !== null && $mapping->quantity !== '') {
                        $item['quantity'] = (int) $mapping->quantity;
                    }
                }
            }
            unset($item);
        }

        $response['products'] = $products;
        $data = $products;

        $locations = $shop->shopify_locations ?? [];
        $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        app(AutoSkuMappingService::class)
            ->handle(
                $shop,
                Cache::get(
                    "shopify_inventory_{$shop->shop}_location_{$selectedIndex}",
                    []
                ),
                $data
            );

        return response()->json($response);
    }

    /**
     * Amazon Products (Powered by the shared AmazonProductQueryService, enriched with Inventory/Mapping details)
     */
    public function amazonProducts(Request $request)
    {
        $shop = $this->getActiveShopModel($request);

        if (!$shop) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Shop not resolved.'
            ], 401);
        }

        $isConnected = !empty($shop->amazon_refresh_token);

        // 1. Fetch the EXACT base Amazon Products collection from the shared service
        $baseAmazonProducts = app(AmazonProductQueryService::class)->getProductsForShop($shop);

        // 2. Load shop's authoritative database mappings
        $mappings = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->get(['id', 'amazon_sku', 'shopify_variant_id', 'shopify_product_id', 'shopify_location_id', 'quantity', 'submission_status', 'sync_status']);

        $mappingsByAmazonSku = $mappings->filter(fn($m) => !empty($m->amazon_sku))
            ->keyBy(fn($m) => strtolower(trim((string) $m->amazon_sku)));

        $activeVerifications = InventorySyncOperation::where('shop_id', $shop->id)
            ->whereIn('status', ['awaiting_verification', 'processing'])
            ->pluck('mapping_id')
            ->filter()
            ->flip();

        $shopifyProductIds = $mappings->pluck('shopify_product_id')->filter()->unique();
        $productsByShopifyId = collect();
        if ($shopifyProductIds->isNotEmpty()) {
            $productsByShopifyId = Product::where('shop_id', $shop->id)
                ->whereIn('shopify_id', $shopifyProductIds)
                ->get(['id', 'shopify_id', 'title', 'variants', 'images'])
                ->keyBy(fn($p) => (string) $p->shopify_id);
        }

        // 3. Resolve Shopify Inventory for the shop (for available qty & location)
        $locations = is_array($shop->shopify_locations)
            ? $shop->shopify_locations
            : (json_decode($shop->shopify_locations, true) ?? []);

        $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        $shopifyCachedInventory = Cache::get(
            "shopify_inventory_{$shop->shop}_location_{$selectedIndex}",
            []
        );
        $shopifyInventoryByVid = collect(is_array($shopifyCachedInventory) ? $shopifyCachedInventory : [])
            ->keyBy(fn($i) => (string) ($i['vid'] ?? ''));

        // 4. Resolve cached Amazon listing inventory (for real-time Amazon quantity & fulfillment channel)
        $amazonInventoryCache = Cache::get("amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}", []);
        $amazonCacheBySku = collect(is_array($amazonInventoryCache) ? $amazonInventoryCache : [])
            ->keyBy(fn($item) => strtolower(trim((string) ($item['sku'] ?? ''))));

        // 5. Enrich each base Amazon Product record
        $enrichedProducts = [];

        foreach ($baseAmazonProducts as $amzProduct) {
            $sku = trim((string) ($amzProduct->sku ?? ''));
            $skuLower = strtolower($sku);

            // Extract title, image, asin, quantity from filled_json or attributes
            $filledData = [];
            if (!empty($amzProduct->filled_json)) {
                $filledData = is_array($amzProduct->filled_json)
                    ? $amzProduct->filled_json
                    : (json_decode($amzProduct->filled_json, true) ?? []);
            }

            $title = $filledData['item_name']
                ?? optional($amzProduct->attributes->firstWhere('attribute_name', 'item_name'))->attribute_value
                ?? optional($amzProduct->attributes->firstWhere('attribute_name', 'product_name'))->attribute_value
                ?? ($sku ?: 'Amazon Product #' . $amzProduct->id);

            $image = $filledData['main_product_image_locator']
                ?? optional($amzProduct->attributes->firstWhere('attribute_name', 'main_product_image_locator'))->attribute_value
                ?? optional($amzProduct->attributes->firstWhere('attribute_name', 'other_product_image_locator_1'))->attribute_value
                ?? asset('b6.png');

            $asin = optional($amzProduct->attributes->firstWhere('attribute_name', 'asin'))->attribute_value
                ?? optional($amzProduct->attributes->firstWhere('attribute_name', 'standard_product_id'))->attribute_value
                ?? ($filledData['asin'] ?? ($filledData['standard_product_id'] ?? null));

            $status = $amzProduct->status ?? 'draft';

            // Cached Amazon inventory data (real-time quantity & fulfillment channel)
            $cachedItem = $amazonCacheBySku->get($skuLower);
            $amazonQty = $cachedItem['quantity']
                ?? ($cachedItem['qty']
                    ?? ($filledData['number_of_items']
                        ?? (optional($amzProduct->attributes->firstWhere('attribute_name', 'number_of_items'))->attribute_value ?? 0)));
            $fulfillmentChannel = $cachedItem['fulfillment_channel']
                ?? ($cachedItem['fulfillment_channel_code'] ?? 'DEFAULT');

            // Mapping association
            $mapping = $mappingsByAmazonSku->get($skuLower);
            $isMapped = $mapping &&
                !empty($mapping->shopify_variant_id) &&
                !empty($mapping->amazon_sku);

            $isVerifying = false;
            if ($mapping) {
                $isVerifying = ($mapping->submission_status === 'accepted')
                    || isset($activeVerifications[$mapping->id]);
            }

            // Enriched mapping fields
            $mappedShopifyProductTitle = null;
            $mappedShopifyVariantTitle = null;
            $mappedShopifyVariantSku = null;
            $mappedShopifyLocationName = null;
            $shopifyAvailableQty = null;
            $mappedShopifyProductUrl = null;

            if ($isMapped) {
                $shopifyProd = $mapping->shopify_product_id ? $productsByShopifyId->get((string) $mapping->shopify_product_id) : null;
                $mappedShopifyProductTitle = $shopifyProd ? $shopifyProd->title : null;
                if (empty($mappedShopifyProductTitle) && !empty($mapping->shopify_product_id)) {
                    $mappedShopifyProductTitle = 'Shopify Product #' . $mapping->shopify_product_id;
                }

                if ($shopifyProd && !empty($shopifyProd->variants)) {
                    $vars = is_array($shopifyProd->variants) ? $shopifyProd->variants : (json_decode($shopifyProd->variants, true) ?? []);
                    $matchedVar = collect($vars)->first(fn($v) => (string) ($v['id'] ?? '') === (string) $mapping->shopify_variant_id);
                    if ($matchedVar) {
                        $mappedShopifyVariantTitle = $matchedVar['title'] ?? null;
                        $mappedShopifyVariantSku = $matchedVar['sku'] ?? null;
                    }
                }
                if (empty($mappedShopifyVariantTitle)) {
                    if ((string) $mapping->shopify_variant_id === (string) $mapping->shopify_product_id) {
                        $mappedShopifyVariantTitle = 'Default';
                    } else {
                        $mappedShopifyVariantTitle = $mapping->shopify_variant_id;
                    }
                }

                // Location name
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
                $mappedShopifyLocationName = $locationName;

                // Shopify available quantity
                $shopifyInvItem = $shopifyInventoryByVid->get((string) $mapping->shopify_variant_id);
                $shopifyAvailableQty = $shopifyInvItem ? ($shopifyInvItem['available'] ?? null) : null;

                $mappedShopifyProductUrl = !empty($mapping->shopify_product_id)
                    ? route('shopify.product.view', ['id' => $mapping->shopify_product_id, 'shop' => $shop->shop])
                    : null;

                // Overlay mapping quantity if verified/active
                if (!$isVerifying && !in_array($mapping->submission_status, ['mismatch', 'failed', 'rejected'], true)) {
                    if ($mapping->quantity !== null && $mapping->quantity !== '') {
                        $amazonQty = (int) $mapping->quantity;
                    }
                }
            }

            $amazonProductUrl = !empty($sku)
                ? route('user.product.amazonView', ['sku' => $sku, 'shop' => $shop->shop])
                : null;

            $enrichedProducts[] = [
                'id' => $amzProduct->id,
                'sku' => $sku,
                'asin' => $asin,
                'title' => $title,
                'image' => $image,
                'status' => $status,
                'is_mapped' => $isMapped,
                'mapping_id' => $isMapped ? $mapping->id : null,
                'mapped_shopify_variant_id' => $isMapped ? $mapping->shopify_variant_id : null,
                'mapped_shopify_product_id' => $isMapped ? $mapping->shopify_product_id : null,
                'mapped_shopify_product_title' => $mappedShopifyProductTitle,
                'mapped_shopify_variant_title' => $mappedShopifyVariantTitle,
                'mapped_shopify_variant_sku' => $mappedShopifyVariantSku,
                'mapped_shopify_location_name' => $mappedShopifyLocationName,
                'mapped_shopify_product_url' => $mappedShopifyProductUrl,
                'shopify_available_qty' => $shopifyAvailableQty,
                'quantity' => (int) $amazonQty,
                'fulfillment_channel' => $fulfillmentChannel,
                'is_verifying' => $isVerifying,
                'submission_status' => $mapping ? $mapping->submission_status : ($amzProduct->submission_status ?? null),
                'amazon_product_url' => $amazonProductUrl,
            ];
        }

        return response()->json([
            'success' => true,
            'connected' => true,
            'status' => [
                'connected' => true,
                'refreshing' => false,
                'sync_completed' => true,
            ],
            'products' => $enrichedProducts,
        ]);
    }

    public function syncAmazonInventory( Request $request,
        AmazonInventoryReportService $reportService
    ) {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found or unauthorized.'
            ], 401);
        }

        $region = session('region') ?: $shop->amazon_mws_region;
        $marketplaceId = $shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER';

        try {
            $result = $reportService->syncInventory(  shop: $shop,
                marketplaceId: $marketplaceId
            );

            return response()->json([
                'success' => true,
                'message' => 'Amazon inventory synced successfully.',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Active shop not resolved.',
            ], 401);
        }

        $this->ensureFreshAccessToken($shop);

        $type = $request->type;

        if ($type === 'shopify') {
            Cache::forget("shopify_inventory_{$shop->shop}_location_0");
            if ($shop->selected_location_index !== null) {
                Cache::forget("shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}");
            }
        } elseif ($type === 'amazon') {
            $marketplaceId = $shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER';

            // Preserve active inventory cache; only clear progress and dispatch background refresh
            Cache::forget("amazon_progress_{$shop->shop}");

            $inventoryCacheService = app(InventoryCacheService::class);
            $inventoryCacheService->dispatchRefresh($shop, $marketplaceId);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function productDetails(Request $request, $productId)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }

        // Decode the product ID from Shopify format
        $shopify = new ShopifyService($shop->shop, $shop->access_token);
        $product = $shopify->getProductById($productId);
        if (!$product) {
            return back()->with('error', 'Product not found');
        }
        return view('inventory.view', compact('product', 'shop'));
    }

    public function getProductCategory(Request $request)
    {
        $parent_id = (int) $request->parent_id;
        $datas = getCategorires($parent_id);
        $datcat = [];
        foreach ($datas as $data) {
            $datcat[] = [
                'id' => $data['id'],
                'name' => str_replace('_', ' ', $data['name'])
            ];
        }

        return response()->json($datcat);
    }

    public function amazonProgress(Request $request)
    {
        $shop = $this->getActiveShopModel($request);

        if (!$shop) {
            return response()->json([
                'percent' => 0,
                'message' => 'Shop not found',
            ], 404);
        }

        return response()->json(
            Cache::get(
                "amazon_progress_{$shop->shop}",
                [
                    'percent' => 0,
                    'message' => 'Preparing...',
                ]
            )
        );
    }

    public function variants(Request $request, string $parentSku)
    {
        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }

        $amazonService = app(AmazonService::class);

        $parent = $amazonService->checkAmazonListing(
            $shop,
            $parentSku
        );

        $parentName =
            $parent['summaries'][0]['itemName']
                ?? $parent['attributes']['item_name'][0]['value']
                ?? '-';

        $childSkus = $parent['relationships'][0]['relationships'][0]['childSkus'] ?? [];

        $variants = [];

        foreach ($childSkus as $childSku) {
            $variant = $amazonService->checkAmazonListing(
                $shop,
                $childSku
            );

            $attributes = $variant['attributes'] ?? [];

            $variants[] = [
                'sku' => $variant['sku'],
                'color' => $attributes['color'][0]['value'] ?? '-',
                'size' => $attributes['footwear_size'][0]['size']
                    ?? $attributes['size'][0]['value']
                    ?? '-',
                'asin' => $attributes['merchant_suggested_asin'][0]['value']
                    ?? $variant['summaries'][0]['asin']
                    ?? '-',
                'quantity' => $attributes['fulfillment_availability'][0]['quantity']
                    ?? 0,
            ];
        }

        return view(
            'inventory.variants',
            compact(
                'parentSku',
                'variants',
                'shop',
                'parentName'
            )
        );
    }

    public function updateAmazonQuantity(Request $request, string $childSku)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $shop = $this->getActiveShopModel($request);
        if (!$shop) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Active shop not resolved.',
            ], 401);
        }

        $amazonService = app(AmazonService::class);

        try {
            $response = $amazonService->updateInventory(
                $shop,
                $childSku,
                (int) $request->quantity
            );

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Amazon manual quantity update failed', [
                'shop_id' => $shop->id,
                'child_sku' => $childSku,
                'quantity' => $request->quantity,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ensures the shop has a valid access token, refreshing if needed.
     * Returns an array: ['success' => bool, 'access_token' => ?string, 'message' => string]
     */
    public function ensureFreshAccessToken(Shop $shopModel): array
    {
        try {
            // still valid — nothing to do
            if ($shopModel->access_token_expires_at && $shopModel->access_token_expires_at->isFuture()) {
                return [
                    'success' => true,
                    'access_token' => $shopModel->access_token,
                    'message' => 'Token still valid.',
                ];
            }

            // refresh token expired — merchant must relaunch the app to re-auth
            if (!$shopModel->refresh_token_expires_at || $shopModel->refresh_token_expires_at->isPast()) {
                Log::warning('REFRESH TOKEN EXPIRED', ['shop' => $shopModel->shop]);

                $shopModel->update(['is_active' => 0]);

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh token expired. App must be relaunched to reauthorize.',
                ];
            }

            $response = Http::asJson()->post("https://{$shopModel->shop}/admin/oauth/access_token", [
                'client_id' => AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key')),
                'client_secret' => AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret')),
                'grant_type' => 'refresh_token',
                'refresh_token' => $shopModel->refresh_token,
            ]);

            if (!$response->successful()) {
                // Log::error('TOKEN REFRESH FAILED', [
                //     'shop' => $shopModel->shop,
                //     'status' => $response->status(),
                //     'body' => $response->body(),
                // ]);

                // Shopify signals a dead refresh token with 401 invalid_request
                if ($response->status() === 401) {
                    $shopModel->update(['is_active' => 0]);
                    return [
                        'success' => false,
                        'access_token' => null,
                        'message' => 'Refresh token is no longer valid. App must be relaunched to reauthorize.',
                    ];
                }

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Failed to refresh Shopify access token. Status: ' . $response->status(),
                ];
            }

            $data = $response->json();

            if (!isset($data['access_token'])) {
                // Log::error('REFRESH RESPONSE MISSING TOKEN', ['shop' => $shopModel->shop, 'body' => $data]);
                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh response did not include an access token.',
                ];
            }

            $shopModel->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $shopModel->refresh_token,
                'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'refresh_token_expires_at' => now()->addSeconds($data['refresh_token_expires_in'] ?? 90 * 86400),
            ]);

            Log::info('TOKEN REFRESHED', ['shop' => $shopModel->shop]);

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'message' => 'Token refreshed successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('TOKEN REFRESH EXCEPTION', [
                'shop' => $shopModel->shop ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'access_token' => null,
                'message' => 'Unexpected error while refreshing token: ' . $e->getMessage(),
            ];
        }
    }
}
