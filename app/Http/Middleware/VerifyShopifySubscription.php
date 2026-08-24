<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Self-contained middleware that checks the CURRENT active Shopify app
 * subscription directly on the merchant's account via the Shopify Admin
 * GraphQL API (currentAppInstallation -> activeSubscriptions).
 *
 * Usage (route alias registered in bootstrap/app.php):
 *   Route::middleware(['shopify.subscription'])->group(...);
 */
class VerifyShopifySubscription
{
    public function handle(Request $request, Closure $next)
    {
        // Global bypass switch
        if (config('app.disable_subscription')) {
          //  return $next($request);
        }
        print_r('VerifyShopifySubscription middleware hit');
        // Shop resolved by ResolveActiveShop middleware, fallback to ?shop= param
        /** @var Shop|null $shop */
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop && $request->filled('shop')) {
            $shop = Shop::where('shop', $request->get('shop'))->first();
        }

        // if (!$shop || empty($shop->access_token)) {
        //     Log::warning('VERIFY SHOPIFY SUBSCRIPTION: NO SHOP/TOKEN', [
        //         'route' => optional($request->route())->getName(),
        //         'url'   => $request->fullUrl(),
        //     ]);

        //     return redirect()
        //         ->route('plans.index', ['shop' => $request->get('shop')])
        //         ->with('error', 'Shop could not be verified. Please reinstall the app.');
        // }

        // ---- Query Shopify directly for the account's active subscriptions ----
        $response = Http::withHeaders([
            'Content-Type'          => 'application/json',
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->post(
            sprintf(
                'https://%s/admin/api/%s/graphql.json',
                $shop->shop,
                config('services.shopify.api_version', '2026-01')
            ),
            [
                'query' => <<<'GRAPHQL'
                    query ActiveSubscriptions {
                        currentAppInstallation {
                            activeSubscriptions(first: 10) {
                                id
                                name
                                status
                                test
                                createdAt
                                currentPeriodEnd
                            }
                        }
                    }
                GRAPHQL,
            ]
        );

        dd($response->json());

        if (!$response->successful()) {
            Log::error('VERIFY SHOPIFY SUBSCRIPTION: API REQUEST FAILED', [
                'shop'   => $shop->shop,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return redirect()
                ->route('plans.index', ['shop' => $shop->shop])
                ->with('error', 'We could not verify your subscription. Please try again.');
        }

        $payload = $response->json();

        if (!empty($payload['errors'])) {
            Log::error('VERIFY SHOPIFY SUBSCRIPTION: GRAPHQL ERRORS', [
                'shop'    => $shop->shop,
                'errors'  => $payload['errors'],
            ]);

            return redirect()
                ->route('plans.index', ['shop' => $shop->shop])
                ->with('error', 'We could not verify your subscription. Please try again.');
        }

        $subscriptions = data_get($payload, 'data.currentAppInstallation.activeSubscriptions', []);

        // Pick the most recent ACTIVE subscription on the merchant's account
        $activeSubscription = collect($subscriptions)
            ->filter(fn ($sub) => strtoupper((string) ($sub['status'] ?? '')) === 'ACTIVE')
            ->sortByDesc(fn ($sub) => $sub['createdAt'] ?? '')
            ->first();
        
        if (!$activeSubscription) {
            Log::info('VERIFY SHOPIFY SUBSCRIPTION: NO ACTIVE SUBSCRIPTION ON ACCOUNT', [
                'shop' => $shop->shop,
            ]);

            return redirect()
                ->route('plans.index', ['shop' => $shop->shop])
                ->with('error', 'No active Shopify subscription found on your account. Please choose a plan to continue.');
        }

        Log::info('VERIFY SHOPIFY SUBSCRIPTION: ACTIVE', [
            'shop'           => $shop->shop,
            'subscription'   => $activeSubscription['id'] ?? null,
            'name'           => $activeSubscription['name'] ?? null,
            'period_end'     => $activeSubscription['currentPeriodEnd'] ?? null,
        ]);

        // Share the live subscription data with controllers/views
        $request->attributes->set('shopify_subscription', $activeSubscription);

        return $next($request);
    }
}