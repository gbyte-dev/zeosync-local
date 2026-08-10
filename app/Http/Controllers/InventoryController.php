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
