<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShopifyController;
use App\Models\Log as SyncLog;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\ShopSubscription;
use App\Services\ShopifyInventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $activeShop = $request->attributes->get('active_shop') ?? $request->query('shop');

            if (!$activeShop) {
                abort(404, 'Active shop not found.');
            }

            $shop = Shop::where('shop', $activeShop)->where('is_active', 1)->first();

            if (!$shop) {
                return view('dashboard-waiting', ['shop' => $activeShop ]);
            }
        }

        $shopId = $shop->id;
        $inventory = $this->shopifyInventoryService->getInventory($shop);

        $amazonInventory = [];
        $amazonInventoryCacheExists = false;

        if (!empty($shop->amazon_seller_id)) {
            $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";

            $amazonInventoryCacheExists = Cache::has($cacheKey);
            $amazonInventory = Cache::get($cacheKey, []);
        }
        $thirtyDaysAgo = \Carbon\Carbon::today()->subDays(30);
        $cacheTtl = 300;  // Cache heavy charts for 5 minutes

        // 1. Top KPI Aggregates (Eager & efficient counts)
        $totalProducts = Product::where('shop_id', $shopId)->count();
        $totalOrders = ShopifyOrder::where('shop_id', $shopId)->count();
        $totalMapped = ProductMarketplaceMapping::where('shop_id', $shopId)->count();

        // System Health Status
        $isShopConnected = true;  // Replace with actual OAuth token check

        // 2. Chart.js Data Generation (Cached)
        $ordersTimeline = Cache::remember("shop_{$shopId}_orders_timeline", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return ShopifyOrder::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });

        // B. Product Creation Trend (Last 30 Days)
        $productTrend = Cache::remember("shop_{$shopId}_product_trend", $cacheTtl, function () use ($shopId, $thirtyDaysAgo) {
            return Product::where('shop_id', $shopId)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });

        // 3. Recent Activity Logs
        $recentLogs = collect([]);
        if (class_exists(SyncLog::class)) {
            $recentLogs = SyncLog::where('shop_id', $shopId)->latest()->take(8)->get();
        }
        $getTopSelling = function ($sinceDate, $cacheSuffix) use ($shopId, $cacheTtl) {
            return Cache::remember(
                "shop_{$shopId}_top_selling_{$cacheSuffix}",
                $cacheTtl,
                function () use ($shopId, $sinceDate) {
                    $query = ShopifyOrder::where('shop_id', $shopId);
                    if ($sinceDate !== null) {
                        $query->where(function ($q) use ($sinceDate) {
                            $q->where('order_created_at', '>=', $sinceDate)
                              ->orWhere('created_at', '>=', $sinceDate);
                        });
                    }

                    $orders = $query->get();

                    return $orders->flatMap(function ($order) {
                            $items = is_array($order->line_items)
                                ? $order->line_items
                                : json_decode($order->line_items, true);

                            return is_array($items) ? $items : [];
                        })
                        ->filter(function ($item) {
                            return !empty($item['title']) || !empty($item['name']) || !empty($item['product_id']);
                        })
                        ->groupBy(function ($item) {
                            return $item['product_id'] ?? $item['variant_id'] ?? $item['title'] ?? $item['name'] ?? 'item';
                        })
                        ->map(function ($items) {
                            $first = $items->first();
                            return [
                                'title' => $first['title'] ?? $first['name'] ?? 'Unknown Product',
                                'quantity' => collect($items)->sum(fn($item) => (int)($item['quantity'] ?? 1)),
                                'amount' => collect($items)->sum(function ($item) {
                                    return (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
                                }),
                            ];
                        })
                        ->sortByDesc('quantity')
                        ->take(5)
                        ->values();
                }
            );
        };

        $topSelling24h = $getTopSelling(now()->subHours(24), '24h');
        $topSelling7d = $getTopSelling(now()->subDays(7), '7d');

        $topSelling24hLabels = $topSelling24h->pluck('title')->map(fn($t) => \Illuminate\Support\Str::limit($t, 15))->values();
        $topSelling24hData = $topSelling24h->pluck('quantity')->values();

        $topSelling7dLabels = $topSelling7d->pluck('title')->map(fn($t) => \Illuminate\Support\Str::limit($t, 15))->values();
        $topSelling7dData = $topSelling7d->pluck('quantity')->values();

        $initialTimeframe = $topSelling24h->isNotEmpty() ? '24h' : ($topSelling7d->isNotEmpty() ? '7d' : '24h');
        $topSellingProducts = $initialTimeframe === '24h' ? $topSelling24h : $topSelling7d;
        $topSellingChartLabels = $topSellingProducts->pluck('title')->map(fn($t) => \Illuminate\Support\Str::limit($t, 15))->values();
        $topSellingChartData = $topSellingProducts->pluck('quantity')->values();

        $lowInventoryProducts = collect($inventory)
            ->map(function ($item) {
                if (!isset($item['available']) || $item['available'] === null) {
                    $item['available'] = $item['qty'] ?? 0;
                }
                return $item;
            })
            ->filter(function ($item) {
                $avail = $item['available'] ?? $item['qty'] ?? null;
                return $avail !== null && (int)$avail < 10;
            })
            ->sortBy(function ($item) {
                return (int)($item['available'] ?? $item['qty'] ?? 0);
            })
            ->take(7)
            ->values();

        $amazonLowInventoryProducts = collect($amazonInventory)
            ->filter(function ($item) {
                return ($item['quantity'] ?? 0) < 10;
            })
            ->sortBy('quantity')
            ->take(7)
            ->values();

        // Return only the exact variables required by the frontend
        return view('dashboard', compact( 'totalProducts','totalMapped',
            'totalOrders','isShopConnected','ordersTimeline','productTrend',
            'recentLogs', 'topSellingProducts', 'topSellingChartLabels',
            'topSellingChartData', 'topSelling24hLabels', 'topSelling24hData',
            'topSelling7dLabels', 'topSelling7dData', 'initialTimeframe',
            'lowInventoryProducts',  'amazonLowInventoryProducts',
            'amazonInventoryCacheExists' ,'shop'
        ));
    }

    public function lowInventory(Request $request)
    {
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            $activeShop = $request->attributes->get('active_shop')
                ?? $request->query('shop');

            if (!$activeShop) {
                abort(404, 'Active shop not found.');
            }

            $shop = Shop::where('shop', $activeShop)
                ->where('is_active', 1)
                ->first();

            if (!$shop) {
                abort(404, 'Active shop not found.');
            }
        }

        $amazonInventory = [];
        $amazonInventoryCacheExists = false;

        if (!empty($shop->amazon_seller_id)) {
            $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
            $amazonInventoryCacheExists = Cache::has($cacheKey);
            $amazonInventory = Cache::get($cacheKey, []);
        }

        $amazonLowInventoryProducts = collect($amazonInventory)
            ->filter(function ($item) {
                return ($item['quantity'] ?? 0) < 10;
            })
            ->sortBy('quantity')
            ->values();

        return view('inventory.low-inventory', compact(
            'amazonLowInventoryProducts',
            'shop',
            'amazonInventoryCacheExists'
        ));
    }
}
