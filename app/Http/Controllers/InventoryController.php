<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ShopifyController;
use App\Models\Shop;
use App\Services\ShopifyService;
use App\Services\AmazonInventoryReportService;
use App\Services\AmazonService;
use App\Services\SyncLimitService;
use App\Services\ShopifyInventoryService;
use App\Services\AutoSkuMappingService;
use App\Services\InventoryCacheService;
use Illuminate\Support\Facades\Log;


class InventoryController extends ShopifyController
{
    public function index(Request $request)
    {
        $shopModel = $this->getActiveShop($request);

        $activeShop = $shopModel?->shop;

        $shop = Shop::where('shop', $activeShop)->first();

        if (!$shop) {
            return redirect()->route('dashboard')
                ->with('error', 'Shop not found.');
        }

        session([
            'shop' => $activeShop,
            'access_token' => $shop->access_token,
            'region' => $shop->amazon_mws_region,
        ]);

        $inventories = [];

        // Sync Usage
        $syncUsage = app(SyncLimitService::class)->canMap($shop);

        return view('inventory.index', compact(
            'inventories',
            'shop',
            'syncUsage'
        ));
    }

    // public function shopify(Request $request)
    // {
    //     $shop = $request->query('shop');

    //     $shopModel = Shop::where('shop', $shop)->first();

    //     if (!$shopModel) {
    //         return response()->json([]);
    //     }

    //     $token = $shopModel->access_token;

    //     $cacheKey = "shopify_inventory_{$shop}";

    //     $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($shop, $token) {

    //         $shopify = new ShopifyService($shop, $token);

    //         // ✅ Updated GraphQL structure
    //         $structure = [
    //             'id',
    //             'title',
    //             'featuredImage' => [
    //                 'url'
    //             ],
    //             'variants(first: 50)' => [
    //                 'nodes' => [
    //                     'id',
    //                     'title',
    //                     'sku',
    //                     'image' => ['url'],
    //                     'inventoryQuantity',
    //                     'inventoryItem' => [
    //                         'id',
    //                         'inventoryLevels(first: 1)' => [
    //                             'nodes' => [
    //                                 'quantities(names: ["available", "committed", "incoming", "on_hand"])' => [
    //                                     'name',
    //                                     'quantity'
    //                                 ]
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ];

    //         $allProducts = [];
    //         $cursor = null;

    //         do {
    //             $response = $shopify->paginate($structure, 50, $cursor);

    //             $allProducts = array_merge($allProducts, $response['data']);

    //             $cursor = $response['next_cursor'];
    //         } while ($response['has_next']);

    //         $result = [];

    //         $shopModel = Shop::where('shop', $shop)->first();

    //         $mappings = ProductMarketplaceMapping::where('shop_id', $shopModel->id)
    //             ->get()
    //             ->keyBy('shopify_variant_id');

    //         foreach ($allProducts as $product) {

    //             foreach ($product['variants']['nodes'] ?? [] as $variant) {

    //                 $qty = $variant['inventoryQuantity'] ?? 0;

    //                 $available = 0;
    //                 $committed = 0;
    //                 $onHand = 0;
    //                 $unavailable = 0;

    //                 $levels = $variant['inventoryItem']['inventoryLevels']['nodes'] ?? [];

    //                 if (!empty($levels)) {
    //                     $quantities = $levels[0]['quantities'] ?? [];

    //                     foreach ($quantities as $q) {
    //                         if ($q['name'] === 'available') {
    //                             $available = $q['quantity'];
    //                         }
    //                         if ($q['name'] === 'committed') {
    //                             $committed = $q['quantity'];
    //                         }
    //                         if ($q['name'] === 'on_hand') {
    //                             $onHand = $q['quantity'];
    //                         }
    //                     }

    //                     // Shopify logic
    //                     $unavailable = $onHand - $available;
    //                 }
    //                 $productId = str_replace('gid://shopify/Product/', '', $product['id']);
    //                 $variantId = str_replace(
    //                     'gid://shopify/ProductVariant/',
    //                     '',
    //                     $variant['id']
    //                 );

    //                 $mapping = $mappings[$variantId] ?? null;
    //                 $isMapped = $mapping
    //                     && !empty($mapping->shopify_variant_id)
    //                     && !empty($mapping->amazon_sku);

    //                 $inventoryItemId = isset($variant['inventoryItem']['id'])
    //                     ? str_replace(
    //                         'gid://shopify/InventoryItem/',
    //                         '',
    //                         $variant['inventoryItem']['id']
    //                     )
    //                     : null;

    //                 $result[] = [
    //                     'pid' => $productId,
    //                     'vid' => $variantId,
    //                     'inventory_item_id' => $inventoryItemId,
    //                     'product' => $product['title'] ?? '',
    //                     'variant' => $variant['title'] ?? '',
    //                     'sku' => $variant['sku'] ?? 'No SKU',
    //                     'available' => $available,
    //                     'committed' => $committed,
    //                     'on_hand' => $onHand,
    //                     'unavailable' => max(0, $unavailable),
    //                     'qty' => $qty,
    //                     'status' => $available > 0 ? 'synced' : 'pending',
    //                     'image' => $variant['image']['url'] ?? $product['featuredImage']['url'] ?? null,

    //                     'is_mapped' => $isMapped,
    //                     'mapped_sku' => $isMapped ? $mapping->amazon_sku : null,
    //                     'mapping_id' => $isMapped ? $mapping->id : null,
    //                 ];
    //             }
    //         }

    //         return $result;
    //     });
    //     app(\App\Services\AutoSkuMappingService::class)
    //         ->handle($shopModel, $data, Cache::get("amazon_inventory_{$shopModel->id}_ATVPDKIKX0DER", []));

    //     return response()->json($data);
    // }

    public function shopify(
        Request $request,
        ShopifyInventoryService $shopifyInventoryService
    ) {
        $shop = $request->query('shop');

        $shopModel = Shop::where('shop', $shop)->first();

        if (!$shopModel) {
            return response()->json([]);
        }

        $data = $shopifyInventoryService->getInventory($shopModel);
        app(AutoSkuMappingService::class)->handle(
            $shopModel,
            $data,
            Cache::get("amazon_inventory_{$shopModel->id}_ATVPDKIKX0DER", [])
        );

        return response()->json($data);
    }

    /**
     *  Amazon Inventory 
     */
    public function amazon(Request $request)
    {
        Log::info('AMAZON INVENTORY API HIT');
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {

            $activeShop = $request->attributes->get('active_shop')
                ?? $request->query('shop');

            if (!$activeShop) {
                return response()->json([
                    'message' => 'Shop not resolved.'
                ], 404);
            }

            $shop = Shop::where('shop', $activeShop)
                ->where('is_active', 1)
                ->first();

            if (!$shop) {
                return response()->json([
                    'message' => 'Shop not found.'
                ], 404);
            }
        }

        $inventoryCacheService = app(InventoryCacheService::class);

        $response = $inventoryCacheService->getAmazonInventory(
            $shop,
            $shop->amazon_marketplace_id
        );

        $data = $response['products'];

        app(AutoSkuMappingService::class)
            ->handle(
                $shop,
                Cache::get("shopify_inventory_{$shop->shop}", []),
                $data
            );

        return response()->json($response);
    }

    /**
     *  Shopify Inventory 
     */
    // public function shopify()
    // {
    //     $shop = session('shop');
    //     $token = session('access_token');

    //     if (!$shop || !$token) {
    //         return response()->json([]);
    //     }

    //     $cacheKey = "shopify_inventory_{$shop}";

    //     $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($shop, $token) {

    //         $shopify = new ShopifyService($shop, $token);

    //         // ✅ Updated GraphQL structure
    //         $structure = [
    //             'id',
    //             'title',
    //             'featuredImage' => [
    //                 'url'
    //             ],
    //             'variants(first: 50)' => [
    //                 'nodes' => [
    //                     'id',
    //                     'title',
    //                     'sku',
    //                     'image' => ['url'],
    //                     'inventoryQuantity',
    //                     'inventoryItem' => [
    //                         'id',
    //                         'inventoryLevels(first: 1)' => [
    //                             'nodes' => [
    //                                 'quantities(names: ["available", "committed", "incoming", "on_hand"])' => [
    //                                     'name',
    //                                     'quantity'
    //                                 ]
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ];

    //         $allProducts = [];
    //         $cursor = null;

    //         do {
    //             $response = $shopify->paginate($structure, 50, $cursor);

    //             $allProducts = array_merge($allProducts, $response['data']);

    //             $cursor = $response['next_cursor'];
    //         } while ($response['has_next']);

    //         $result = [];

    //         $shopModel = Shop::where('shop', $shop)->first();

    //         $mappings = ProductMarketplaceMapping::where('shop_id', $shopModel->id)
    //             ->get()
    //             ->keyBy('shopify_variant_id');

    //         foreach ($allProducts as $product) {

    //             foreach ($product['variants']['nodes'] ?? [] as $variant) {

    //                 $qty = $variant['inventoryQuantity'] ?? 0;

    //                 $available = 0;
    //                 $committed = 0;
    //                 $onHand = 0;
    //                 $unavailable = 0;

    //                 $levels = $variant['inventoryItem']['inventoryLevels']['nodes'] ?? [];

    //                 if (!empty($levels)) {
    //                     $quantities = $levels[0]['quantities'] ?? [];

    //                     foreach ($quantities as $q) {
    //                         if ($q['name'] === 'available') {
    //                             $available = $q['quantity'];
    //                         }
    //                         if ($q['name'] === 'committed') {
    //                             $committed = $q['quantity'];
    //                         }
    //                         if ($q['name'] === 'on_hand') {
    //                             $onHand = $q['quantity'];
    //                         }
    //                     }

    //                     // Shopify logic
    //                     $unavailable = $onHand - $available;
    //                 }
    //                 $productId = str_replace('gid://shopify/Product/', '', $product['id']);
    //                 $variantId = str_replace(
    //                     'gid://shopify/ProductVariant/',
    //                     '',
    //                     $variant['id']
    //                 );
    //                 $inventoryItemId = isset($variant['inventoryItem']['id'])
    //                     ? str_replace(
    //                         'gid://shopify/InventoryItem/',
    //                         '',
    //                         $variant['inventoryItem']['id']
    //                     )
    //                     : null;

    //                 $mapping = $mappings[$variantId] ?? null;

    //                 $result[] = [
    //                     'pid' => $productId,
    //                     'vid' => $variantId,
    //                     'inventory_item_id' => $inventoryItemId,
    //                     'product' => $product['title'] ?? '',
    //                     'variant' => $variant['title'] ?? '',
    //                     'sku' => $variant['sku'] ?? 'No SKU',
    //                     'available' => $available,
    //                     'committed' => $committed,
    //                     'on_hand' => $onHand,
    //                     'unavailable' => max(0, $unavailable),
    //                     'qty' => $qty,
    //                     'status' => $available > 0 ? 'synced' : 'pending',
    //                     'image' =>  $variant['image']['url'] ?? $product['featuredImage']['url']  ?? null,

    //                     'is_mapped' => $mapping !== null,
    //                     'mapped_sku' => $mapping?->amazon_sku,
    //                 ];
    //             }
    //         }

    //         return $result;
    //     });

    //     return response()->json($data);
    // }

    /**
     *  Amazon Inventory 
     */
    // public function amazon()
    // {

    //     $activeShop = session('active_shop');

    //     $shop = is_numeric($activeShop)
    //         ? Shop::findOrFail($activeShop)
    //         : Shop::where('shop', $activeShop)->firstOrFail();

    //     $marketplaceId = 'ATVPDKIKX0DER';

    //     $cacheKey = "amazon_inventory_{$shop->id}_{$marketplaceId}";

    //     if (Cache::has($cacheKey)) {
    //         \Log::info('AMAZON INVENTORY CACHE HIT', [
    //             'cache_key' => $cacheKey,
    //         ]);
    //     } else {
    //         \Log::info('AMAZON INVENTORY CACHE MISS', [
    //             'cache_key' => $cacheKey,
    //         ]);
    //     }

    //     $data = Cache::remember(
    //         $cacheKey,
    //         now()->addMinutes(20),
    //         function () use ($shop, $marketplaceId) {

    //             \Log::info('FETCHING AMAZON INVENTORY FROM API');

    //             return app(AmazonInventoryReportService::class)
    //                 ->syncInventory(
    //                     $shop,
    //                     $marketplaceId
    //                 );
    //         }
    //     );

    //     return response()->json($data);
    // }


    public function syncAmazonInventory(
        Request $request,
        AmazonInventoryReportService $reportService
    ) {
        $region = session('region');
        $activeShop = session('active_shop');

        $shop = is_numeric($activeShop)
            ? Shop::findOrFail($activeShop)
            : Shop::where('shop', $activeShop)->firstOrFail();

        $marketplaceId = 'ATVPDKIKX0DER';

        \Log::info('Amazon Inventory Sync Started', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
            'region' => $region,
            'marketplace_id' => $marketplaceId,
        ]);

        try {

            $result = $reportService->syncInventory(
                shop: $shop,
                region: $region,
                marketplaceId: $marketplaceId
            );

            \Log::info('Amazon Inventory Sync Completed', [
                'shop_id' => $shop->id,
                'result_count' => is_array($result) ? count($result) : null,
                'result' => $result,
            ]);

            Cache::forget("amazon_inventory_{$shop->id}_{$marketplaceId}");

            \Log::info('Amazon Inventory Cache Cleared', [
                'cache_key' => "amazon_inventory_{$shop->id}_{$marketplaceId}",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Amazon inventory synced successfully.',
                'data' => $result
            ]);
        } catch (\Throwable $e) {

            \Log::error('Amazon Inventory Sync Failed', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'region' => $region,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function refresh(Request $request)
    {

        $shop = Shop::where('shop', $request->shop)->firstOrFail();

        $type = $request->type;

        if ($type === 'shopify') {

            Cache::forget("shopify_inventory_{$shop->shop}");
        } elseif ($type === 'amazon') {

            $marketplaceId = 'ATVPDKIKX0DER';

            Cache::forget("amazon_inventory_{$shop->id}_{$marketplaceId}");
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function productDetails(Request $request, $productId)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        $shop = Shop::where('shop', $activeShop)->first();
        if (!$shop) {
            return redirect()->route('dashboard')->with('error', 'Shop not found.');
        }

        // Decode the product ID from Shopify format
        $shopify = new ShopifyService($activeShop, $shop->access_token);
        $product = $shopify->getProductById($productId);
        if (!$product) {
            return back()->with('error', 'Product not found');
        }
        return view('inventory.view', compact('product', 'shop'));
    }

    public function getProductCategory(Request $request)
    {
        $parent_id = (int)$request->parent_id;
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
        $shop = Shop::where('shop', $request->query('shop'))->first();

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
        $shop = Shop::where('shop', $request->query('shop'))
            ->firstOrFail();

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



                // 'status' => $variant['summaries'][0]['status'][0]
                //     ?? 'Unknown',

                // 'issues' => count($variant['issues'] ?? []),
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

        $shop = Shop::where('shop', $request->query('shop'))
            ->firstOrFail();

        $amazonService = app(AmazonService::class);

        return response()->json(
            $amazonService->updateInventory(
                $shop,
                $childSku,
                (int) $request->quantity
            )
        );
    }
}
