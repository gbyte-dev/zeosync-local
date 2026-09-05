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
use App\Models\AdminSetting;


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
        $this->ensureFreshAccessToken($shopModel);

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
                Cache::get(
                    "shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}",
                    []
                ),
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
        //    $shopModel = Shop::where('shop', $shop)->first();
        $this->ensureFreshAccessToken($shop);

        $type = $request->type;

        if ($type === 'shopify') {

            Cache::forget(
                "shopify_inventory_{$shop->shop}_location_{$shop->selected_location_index}"
            );
        } elseif ($type === 'amazon') {

            $marketplaceId = $shop->amazon_marketplace_id ?: 'ATVPDKIKX0DER';

            Cache::forget("amazon_inventory_{$shop->id}_{$marketplaceId}");
            Cache::forget("amazon_progress_{$shop->shop}");

            $inventoryCacheService = app(InventoryCacheService::class);
            $inventoryCacheService->updateStatus($shop, $marketplaceId, [
                'refreshing' => false,
                'last_synced_at' => null,
            ]);
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
                'client_id'     => AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key')),
                'client_secret' => AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret')),
                'grant_type'    => 'refresh_token',
                'refresh_token' => $shopModel->refresh_token,
            ]);

            if (!$response->successful()) {
                Log::error('TOKEN REFRESH FAILED', [
                    'shop' => $shopModel->shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

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
                Log::error('REFRESH RESPONSE MISSING TOKEN', ['shop' => $shopModel->shop, 'body' => $data]);
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
