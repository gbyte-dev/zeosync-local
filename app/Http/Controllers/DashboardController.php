<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Http\Controllers\ShopifyController;
use App\Models\Setting;
use App\Models\Log as SyncLog;
use App\Models\ReturnItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\ShopifyOrder;
use App\Models\ProductMarketplaceMapping;
use App\Services\ShopifyInventoryService;
use Illuminate\Support\Collection;


class DashboardController extends ShopifyController
{
    protected ShopifyInventoryService $shopifyInventoryService;

    public function __construct()
    {
        $this->shopifyInventoryService = app(ShopifyInventoryService::class);
    }

    public function index(Request $request)
    {
        // Fallback to active shop identification strategy (Adjust if using osiset/laravel-shopify)
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            $activeShop = $request->attributes->get('active_shop')
                ?? $request->query('shop');

            if (!$activeShop) {
                abort(404, 'Active shop not found.');
            }

            $shop = Shop::where('shop', $activeShop)
                ->where('is_active', 1)
                ->firstOrFail();
        }

        $shopId = $shop->id;
        $inventory = $this->shopifyInventoryService->getInventory($shop);
        $amazonInventory = [];

        if (!empty($shop->amazon_marketplace_id)) {
            $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_marketplace_id}";
            $amazonInventory = Cache::get($cacheKey, []);
        }
        $thirtyDaysAgo = \Carbon\Carbon::today()->subDays(30);
        $cacheTtl = 300; // Cache heavy charts for 5 minutes

        // 1. Top KPI Aggregates (Eager & efficient counts)
        $totalProducts = Product::where('shop_id', $shopId)->count();
        $totalOrders = ShopifyOrder::where('shop_id', $shopId)->count();
        $totalMapped = ProductMarketplaceMapping::where('shop_id', $shopId)->count();

        // System Health Status
        $isShopConnected = true; // Replace with actual OAuth token check

        // 2. Chart.js Data Generation (Cached)
        $ordersTimeline = Cache::remember("shop_{$shopId}_orders_timeline", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return ShopifyOrder::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')->orderBy('date')->get();
        });

        // B. Product Creation Trend (Last 30 Days)
        $productTrend = Cache::remember("shop_{$shopId}_product_trend", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return Product::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')->orderBy('date')->get();
        });

        // 3. Recent Activity Logs
        $recentLogs = collect([]);
        if (class_exists(SyncLog::class)) {
            $recentLogs = SyncLog::where('shop_id', $shopId)->latest()->take(8)->get();
        }
        $topSellingProducts = Cache::remember(
            "shop_{$shopId}_top_selling_products",
            $cacheTtl,
            function () use ($shopId) {

                return ShopifyOrder::where('shop_id', $shopId)
                    ->where('order_created_at', '>=', now()->subDay())
                    ->get()
                    ->flatMap(function ($order) {

                        $items = is_array($order->line_items)
                            ? $order->line_items
                            : json_decode($order->line_items, true);

                        return is_array($items) ? $items : [];
                    })
                    ->groupBy('product_id')
                    ->map(function ($items) {

                        return [
                            'title' => $items->first()['title'] ?? 'Unknown Product',
                            'quantity' => collect($items)->sum('quantity'),
                            'amount' => collect($items)->sum(function ($item) {
                                return ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                            }),
                        ];
                    })
                    ->sortByDesc('quantity')
                    ->take(5)
                    ->values();
            }
        );

        $topSellingChartLabels = $topSellingProducts
            ->pluck('title')
            ->map(fn($title) => \Illuminate\Support\Str::limit($title, 15))
            ->values();

        $topSellingChartData = $topSellingProducts
            ->pluck('quantity')
            ->values();

        $lowInventoryProducts = collect($inventory)
            ->filter(function ($item) {
                return ($item['available'] ?? 0) < 10;
            })
            ->sortBy('available')
            ->take(7)
            ->values();

        $amazonLowInventoryProducts = collect($amazonInventory)
            ->filter(function ($item) {
                return ($item['quantity'] ?? 0) <= 10;
            })
            ->sortBy('quantity')
            ->take(7)
            ->values();



        // Return only the exact variables required by the frontend
        return view('dashboard', compact(
            'totalProducts',
            'totalMapped',
            'totalOrders',
            'isShopConnected',
            'ordersTimeline',
            'productTrend',
            'recentLogs',
            'topSellingProducts',
            'topSellingChartLabels',
            'topSellingChartData',
            'lowInventoryProducts',
            'amazonLowInventoryProducts'
        ));
    }
}
