<?php

namespace App\Http\Controllers;

use App\Models\AmazonProduct;
use App\Services\NotificationService;
use App\Services\UserNotificationService;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\ShopSubscription;
use App\Services\ShopifyBillingService;
use App\Services\ShopifyWebhookService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\DB;
use App\Models\ProductSyncLog;
use SellingPartnerApi\SellingPartnerApi;
use App\Models\AdminSetting;
use SellingPartnerApi\Enums\Endpoint;
use App\Models\ProductMarketplaceMapping;
use App\Services\AmazonService;
use App\Amazon\Requests\PutListingItemRequest;
use SellingPartnerApi\Seller\SellerConnector;
use SellingPartnerApi\Seller\OrdersV0\Requests\GetOrdersRequest;
use RuntimeException;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use App\Models\Category;
use App\Services\Amazon\ShopifyAmazonMapper;
use App\Models\ProductSchema;
use App\Http\Controllers\ProductSchemaController;


class ShopifyController extends Controller
{
    protected ShopifyBillingService $shopifyBilling;
    protected ShopifyWebhookService $shopifyWebhook;
    protected AmazonService $amazonService;


    public function __construct()
    {
        $this->shopifyBilling = app(ShopifyBillingService::class);
        $this->shopifyWebhook = app(ShopifyWebhookService::class);
        $this->amazonService = app(AmazonService::class);
    }
    public function entry(Request $request)
    {
        $shop = null;
        //   PRIORITY 1: Shopify HOST (REAL SOURCE)
        if ($request->has('host')) {
            $decoded = base64_decode($request->get('host'));
            LOG::info('HOST DECODED', [
                'host' => $request->get('host'),
                'decoded' => $decoded
            ]);
            preg_match('/store\/([a-z0-9\-]+)/', $decoded, $matches);
            if (!empty($matches[1])) {
                $shop = strtolower($matches[1] . '.myshopify.com');
            }
        }
        //   PRIORITY 2: Query param (manual entry)
        if (!$shop && $request->has('shop')) {
            $shop = strtolower(trim($request->get('shop')));
            if (!str_contains($shop, '.myshopify.com')) {
                $shop .= '.myshopify.com';
            }
        }

        if (!$shop) {
            return view('welcome'); // landing page
        }

        $shopModel = \App\Models\Shop::where('shop', $shop)->first();

        if (
            $shopModel &&  $shopModel->is_active == 1 && !empty($shopModel->access_token)
        ) {
            if (!$this->isShopActive($shopModel)) {

                $shopModel->update([
                    'is_active' => 0,
                ]);

                session()->forget('active_shop');
                return redirect()->route('shopify.install', [
                    'shop' => $shopModel->shop,
                ]);
            }
            session(['active_shop' => $shop]);
            return redirect()->route('dashboard', [
                'shop' => $shop
            ]);
        }

        return redirect()->route('shopify.install', ['shop' => $shop]);
    }
    private function isShopActive(Shop $shop): bool
    {
        try {
            $this->ensureFreshAccessToken($shop);
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    "https://{$shop->shop}/admin/api/2025-01/graphql.json",
                    [
                        'query' => '
                        query {
                            shop {
                                id
                                name
                            }
                        }
                    ',
                    ]
                );

            Log::info('SHOP STATUS CHECK', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Token revoked / invalid
            if (in_array($response->status(), [401, 402], true)) {
                return false;
            }

            // Shopify reachable
            if ($response->successful()) {
                return true;
            }

            // 403, 429, 500 etc ko uninstall mat samjho
            return true;
        } catch (\Throwable $e) {

            Log::error('SHOP STATUS CHECK FAILED', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'error' => $e->getMessage(),
            ]);

            // Network failure ≠ uninstall
            return true;
        }
    }
    public function install(Request $request)
    {
        Log::info('INSTALL HIT', [
            'full_url' => $request->fullUrl(),
            'query' => $request->all()
        ]);
        $shop = $request->query('shop');
        //   fallback from host (IMPORTANT)
        if (!$shop && $request->has('host')) {
            $decoded = base64_decode($request->get('host'));
            LOG::info('INSTALL HOST DECODED', [
                'host' => $request->get('host'),
                'decoded' => $decoded
            ]);
            if (preg_match('/store\/([a-z0-9\-]+)/', $decoded, $matches)) {
                $shop = $matches[1] . '.myshopify.com';
            }
        }

        if (!$shop) {
            Log::error('SHOP MISSING');
            return response('Missing shop parameter', 400);
        }

        if (!str_contains($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        // ✅ strict validation
        if (!preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop)) {
            Log::error('INVALID SHOP FORMAT', [
                'shop' => $shop
            ]);
            return response('Invalid shop domain', 400);
        }
        $state = base64_encode(json_encode([
            'shop' => $shop,
            'time' => time()
        ]));

        Log::info('SHOPIFY API KEY', [
            'value' => AdminSetting::get(
                'SHOPIFY_API_KEY',
                config('services.shopify.api_key')
            )
        ]);
        // ⚡ build query safely
        $shopifyApiKey = AdminSetting::get(
            'SHOPIFY_API_KEY',
            config('services.shopify.api_key')
        );

        $shopifyRedirectUri = AdminSetting::get(
            'SHOPIFY_REDIRECT_URI',
            config('services.shopify.redirect_uri')
        );

        $query = http_build_query([
            'client_id'    => $shopifyApiKey,
            'scope'        => $this->oauthScopes(),
            'redirect_uri' => $shopifyRedirectUri,
            'state'        => $state,
        ]);
        $redirectUrl = "https://{$shop}/admin/oauth/authorize?{$query}";
        Log::info('STEP 4: REDIRECT URL', [
            'url' => $redirectUrl
        ]);
        //   IMPORTANT (iframe fix)
        $redirectUrl = "https://{$shop}/admin/oauth/authorize?{$query}";

        return response()->view('shopify.auth-popup', [
            'redirectUrl' => $redirectUrl,
            'shop' => $shop,
        ]);
    }
    public function callback(Request $request)
    {
        Log::info('CALLBACK HIT', $request->all());
        // =========================
        // STEP 1: HMAC VALIDATION (FIRST)
        // =========================
        $query = $request->query();
        $hmac = $query['hmac'] ?? null;
        unset($query['hmac'], $query['signature']);
        ksort($query);
        $computedHmac = hash_hmac(
            'sha256',
            urldecode(http_build_query($query)),
            \App\Models\AdminSetting::get(
                'SHOPIFY_API_SECRET',
                config('services.shopify.api_secret')
            )
        );
        if (!$hmac || !hash_equals($hmac, $computedHmac)) {
            Log::error('HMAC FAILED', [
                'hmac' => $hmac,
                'computed' => $computedHmac
            ]);
            abort(403, 'Invalid HMAC');
        }
        // =========================
        // STEP 2: STATE DECODE (NO CACHE)
        // =========================
        $state = $request->state;
        $code = $request->code;
        $decodedState = json_decode(base64_decode($state), true);
        if (!$decodedState || !isset($decodedState['shop'])) {
            Log::error('STATE DECODE FAILED', ['state' => $state]);
            abort(403, 'Invalid state');
        }
        Log::info('STATE DEBUG', [
            'raw_state' => $state,
            'decoded' => $decodedState
        ]);
        $shop = $decodedState['shop'] ?? null;
        if (!$shop) {
            Log::error('STATE INVALID OR SHOP MISSING');
            abort(403, 'Invalid state');
        }
        // =========================
        // STEP 3: VALIDATE SHOP
        // =========================
        if (!preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop)) {
            Log::error('INVALID SHOP', ['shop' => $shop]);
            abort(400, 'Invalid shop domain');
        }
        // =========================
        // STEP 4: TOKEN EXCHANGE
        // =========================
        $response = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => AdminSetting::get(
                'SHOPIFY_API_KEY',
                config('services.shopify.api_key')
            ),
            'client_secret' => AdminSetting::get(
                'SHOPIFY_API_SECRET',
                config('services.shopify.api_secret')
            ),
            'code' => $code,
            'expiring' => 1,
        ]);
        if (!$response->successful()) {
            Log::error('TOKEN FAILED', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return redirect('/')->with('error', 'Shopify connection failed.');
        }
        $data = $response->json();
        if (!isset($data['access_token'])) {
            Log::error('NO ACCESS TOKEN', $data);
            return redirect('/')->with('error', 'Shopify connection failed.');
        }
        $accessToken = $data['access_token'];
        Log::info('TOKEN RECEIVED');
        $refreshToken      = $data['refresh_token'] ?? null;
        $expiresIn         = $data['expires_in'] ?? 3600;                 // access token, ~60 min
        $refreshExpiresIn  = $data['refresh_token_expires_in'] ?? (90 * 86400); // refresh token, ~90 days

        $shopModel = \App\Models\Shop::updateOrCreate(
            ['shop' => $shop],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'access_token_expires_at' => now()->addSeconds($expiresIn),
                'refresh_token_expires_at' => now()->addSeconds($refreshExpiresIn),
                'installed_at' => now(),
                'hmac' => $request->hmac,
                'is_active' => 1
            ]
        );

        // Fetch and store all Shopify locations
        try {
            $locationResponse = $this->shopifyRest(
                $shopModel,
                'get',
                'locations.json'
            );

            if (!empty($locationResponse['error'])) {
                Log::error('SHOPIFY LOCATIONS FETCH FAILED', [
                    'shop_id' => $shopModel->id,
                    'shop' => $shopModel->shop,
                    'response' => $locationResponse,
                ]);
            } else {
                $locations = $locationResponse['locations'] ?? [];

                $shopModel->update([
                    'shopify_locations' => $locations,
                    'selected_location_index' => null,
                ]);

                Log::info('SHOPIFY LOCATIONS SAVED', [
                    'shop_id' => $shopModel->id,
                    'shop' => $shopModel->shop,
                    'locations_count' => count($locations),
                    'locations' => $locations,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SHOPIFY LOCATIONS SAVE FAILED', [
                'shop_id' => $shopModel->id,
                'shop' => $shopModel->shop,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
        }

        // optional session
        session(['active_shop' => $shop]);
        // =========================
        // STEP 6: WEBHOOK
        // =========================
        try {
            $this->shopifyWebhook->ensureOrdersCreateWebhook($shopModel);
            $this->shopifyWebhook->ensureAppUninstalledWebhook($shopModel);
        } catch (\Exception $e) {
            Log::error('WEBHOOK FAILED', [
                'error' => $e->getMessage()
            ]);
        }
        // =========================
        // STEP 7: REDIRECT
        // =========================

        NotificationService::send(
            'shopify_connected',
            'Shopify Store Connected',
            $shopModel->shop . ' connected successfully.'
        );

        $setupUrl = route('setup.form', ['shop' => $shop]);
        return response()->view('shopify.auth-callback', [
            'shop' => $shopModel->shop,
            'redirectUrl' => $setupUrl,
        ]);
    }
    public function checkShopStatus(Request $request)
    {
        $shop = $request->query('shop');
        if (!$shop) {
            return response()->json(['error' => 'Shop parameter required'], 400);
        }
        $shopModel = Shop::where('shop', $shop)->first();
        if (!$shopModel) {
            return response()->json(['shop_name' => null, 'email' => null], 200);
        }
        return response()->json([
            'shop_name' => $shopModel->shop_name,
            'email' => $shopModel->email,
        ], 200);
    }
    public function plans(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect($this->shopAwareUrl('/', $request->query('shop') ?? $request->input('shop')))
                ->with('error', 'No shop connected.');
        }
        // $plans = Plan::query()->where(['is_active' => true])
        //     ->orderBy('sort_order')
        //     ->orderBy('id')
        //     ->get();
        $activeShopId = $shopModel->id;
        $plans = Plan::query()
            ->where('is_active', true)
            ->where(function ($query) use ($activeShopId) {
                $query->where('is_custom', 0)
                    ->orWhere('shop_id', $activeShopId);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopModel->id)
            ->first();
        try {
            Log::info('PLANS PAGE BEFORE SYNC', [
                'plan_id' => $subscription?->plan_id,
                'requested_plan_id' => $subscription?->requested_plan_id,
            ]);
            $subscription = $this->shopifyBilling->syncSubscription($shopModel, $subscription);
            Log::info('PLANS PAGE AFTER SYNC', [
                'plan_id' => $subscription?->plan_id,
                'requested_plan_id' => $subscription?->requested_plan_id,
            ]);
            $subscription?->loadMissing('plan');
        } catch (RuntimeException $exception) {
            Log::warning('Unable to sync Shopify billing status before rendering plans.', [
                'shop' => $shopModel->shop,
                'error' => $exception->getMessage(),
            ]);
        }
        $billingOptions = [
            ['value' => '1', 'label' => 'Monthly', 'description' => 'Billed every 30 days'],
            ['value' => '30', 'label' => 'Annual', 'description' => 'Billed every 365 days'],
        ];
        return view('plans', compact('plans', 'subscription', 'activeShop', 'billingOptions') + ['billingProvider' => app(\App\Services\Billing\BillingProvider::class)->provider()]);
    }
    public function subscribeToPlan(Request $request)
    {
        Log::info('Controller shopifycontroller called');
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_interval' => 'required|string|in:EVERY_30_DAYS,ANNUAL',
        ]);
        $shopModel = $this->getActiveShop($request);
        if (!$shopModel) {
            return redirect()->back()->with('error', 'No shop connected.');
        }
        $plan = Plan::query()
            ->where('is_active', true)
            ->findOrFail($request->integer('plan_id'));
        //  TRIAL PLAN HANDLE
        if ($plan->is_trial) {
            $trialDays = $plan->trial_days ?? 7;
            ShopSubscription::updateOrCreate(
                ['shop_id' => $shopModel->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'trialing',
                    'price' => 0,
                    'billing_interval' => 'EVERY_30_DAYS',
                    'trial_days' => $trialDays,
                    'trial_ends_at' => now()->addDays($trialDays),
                    'started_at' => now(),
                    'activated_at' => now(),
                    //  CLEAN OLD DATA
                    'shopify_subscription_gid' => null,
                    'shopify_confirmation_url' => null,
                    'shopify_return_url' => null,
                    'current_period_end' => null,
                    'cancelled_at' => null,
                    'ended_at' => null,
                ]
            );
            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('success', 'Trial activated successfully.');
        }
        $billingInterval = strtoupper($request->string('billing_interval')->value());
        $billingCycleMonths = $billingInterval === 'ANNUAL' ? 12 : 1;
        $existingSubscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopModel->id)
            ->first();
        try {
            $existingSubscription = $this->shopifyBilling->syncSubscription($shopModel, $existingSubscription);
        } catch (RuntimeException $exception) {
            Log::warning('Unable to sync Shopify subscription before creating a new billing request.', [
                'shop' => $shopModel->shop,
                'error' => $exception->getMessage(),
            ]);
        }
        if (
            $existingSubscription &&
            $this->shopifyBilling->isActivatedStatus($existingSubscription->status) &&
            (int) $existingSubscription->plan_id === (int) $plan->id &&
            (string) $existingSubscription->billing_interval === $billingInterval
        ) {
            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('success', "{$plan->name} is already active for {$shopModel->shop}.");
        }
        $returnUrl = $this->shopifyBilling->buildReturnUrl($shopModel);
        try {
            $createdSubscription = $this->shopifyBilling->createSubscription(
                $shopModel,
                $plan,
                $billingInterval,
                $returnUrl
            );
        } catch (RuntimeException $exception) {
            Log::error('Failed to create Shopify app subscription.', [
                'shop' => $shopModel->shop,
                'plan_id' => $plan->id,
                'billing_interval' => $billingInterval,
                'error' => $exception->getMessage(),
            ]);
            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('error', $exception->getMessage());
        }
        ShopSubscription::updateOrCreate(
            ['shop_id' => $shopModel->id],
            [
                'plan_id' => $plan->id,
                'shopify_subscription_gid' => $createdSubscription['subscription_gid'],
                'shopify_confirmation_url' => $createdSubscription['confirmation_url'],
                'shopify_return_url' => $returnUrl,
                'status' => 'pending',
                'price' => $createdSubscription['amount'],
                'billing_cycle_months' => $billingCycleMonths,
                'billing_interval' => $billingInterval,
                'currency_code' => $createdSubscription['currency_code'],
                'trial_days' => (int) config('services.shopify.billing.trial_days', 0),
                'is_test' => (bool) config('services.shopify.billing.test', false),
                'trial_ends_at' => null,
                'started_at' => null,
                'activated_at' => null,
                'current_period_end' => null,
                'ended_at' => null,
                'cancelled_at' => null,
            ]
        );
        return redirect()->away($createdSubscription['confirmation_url']);
    }
    public function billingCallback(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            return redirect('/')->with('error', 'No shop connected.');
        }
        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopModel->id)
            ->first();

        try {
            $subscription = $this->shopifyBilling->syncSubscription(
                $shopModel,
                $subscription
            );
            $subscription?->loadMissing('plan');
        } catch (RuntimeException $exception) {
            Log::error('Failed to confirm Shopify billing callback.', [
                'shop' => $shopModel->shop,
                'subscription_gid' => $subscription->shopify_subscription_gid,
                'error' => $exception->getMessage(),
            ]);
            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('error', 'Shopify billing confirmation failed: ' . $exception->getMessage());
        }
        if (!$subscription || !$this->shopifyBilling->isActivatedStatus($subscription->status)) {
            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('error', 'Shopify did not activate the subscription. Please approve the charge to continue.');
        }
        return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
            ->with('success', ($subscription->plan?->name ?? 'Selected') . ' plan is now active for ' . $shopModel->shop . '.');
    }
    public function orders(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect($this->shopAwareUrl('/', $request->query('shop') ?? $request->input('shop')))
                ->with('error', 'No shop connected.');
        }
        $source = $request->get('source', 'shopify'); //   IMPORTANT
        $refresh = $request->get('refresh');
        $search = $request->input('search');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 10);
        // =========================
        //   AMAZON FLOW
        // =========================
        if ($source === 'amazon') {
            // if ($refresh) {
            //     UserNotificationService::send(
            //         $shopModel->id,
            //         'order_sync',
            //         'Amazon Order Sync Started',
            //         "{$shopModel->shop} Amazon order sync started."
            //     );
            // }

            $amazonOrders = $this->fetchAmazonOrders($refresh ? true : false);
            // if ($refresh) {
            //     UserNotificationService::send(
            //         $shopModel->id,
            //         'order_sync',
            //         'Amazon Order Sync Completed',
            //         "{$shopModel->shop} Amazon orders synced successfully. Total orders: " . count($amazonOrders)
            //     );
            // }


            return view('orders', [
                'source' => 'amazon',
                'orders' => $amazonOrders,
                'activeShop' => $activeShop,
                // dummy stats for now
                'totalOrders' => count($amazonOrders),
                'paidOrders' => 0,
                'pendingOrders' => 0,
                'cancelledOrders' => 0,
                'search' => $search,
                'status' => $status
            ]);
        }
        // =========================
        //   SHOPIFY FLOW (DEFAULT)
        // =========================
        $query = ShopifyOrder::query()->where('shop_id', $shopModel->id);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('customer_first_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_last_name', 'LIKE', "%{$search}%")
                    ->orWhere('order_number', 'LIKE', "%{$search}%");
            });
        }
        if ($status && $status !== 'all') {
            $query->where('financial_status', strtoupper($status));
        }
        $totalOrders = ShopifyOrder::where('shop_id', $shopModel->id)->count();
        $paidOrders = ShopifyOrder::where('shop_id', $shopModel->id)->where('financial_status', 'PAID')->count();
        $pendingOrders = ShopifyOrder::where('shop_id', $shopModel->id)->where('financial_status', 'PENDING')->count();
        $cancelledOrders = ShopifyOrder::where('shop_id', $shopModel->id)->where('financial_status', 'CANCELLED')->count();
        $shopifyOrders = $query->latest('order_created_at')->paginate($perPage)->withQueryString();
        // if ($refresh) {
        //     UserNotificationService::send(
        //         $shopModel->id,
        //         'order_sync',
        //         'Shopify Order Sync Completed',
        //         "{$shopModel->shop} Shopify orders synced successfully. Total orders: {$totalOrders}"
        //     );
        // }
        return view('orders', [
            'source' => 'shopify',
            'shopifyOrders' => $shopifyOrders,
            'activeShop' => $activeShop,
            'totalOrders' => $totalOrders,
            'paidOrders' => $paidOrders,
            'pendingOrders' => $pendingOrders,
            'cancelledOrders' => $cancelledOrders,
            'search' => $search,
            'status' => $status
        ]);
    }
    public function showOrder(Request $request, ShopifyOrder $order)
    {
        $source = $request->query('source', 'shopify');

        if ($source !== 'shopify') {
            return redirect($this->shopAwareUrl('/orders'))
                ->with('error', 'Only Shopify order details are available right now.');
        }

        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            return redirect(
                $this->shopAwareUrl(
                    '/',
                    $request->query('shop') ?? $request->input('shop')
                )
            )->with('error', 'No shop connected.');
        }

        if ((int) $order->shop_id !== (int) $shopModel->id) {
            abort(404, 'Order not found.');
        }

        return view('order-details', [
            'order'      => $order,
            'source'     => $source,
            'activeShop' => $shopModel->shop,
        ]);
    }
    public function syncToAmazon(Request $request, $id)
    {
        try {

            $shopModel = $this->getActiveShop($request);
            $this->ensureFreshAccessToken($shopModel);
            if (!$shopModel) {
                ProductSyncLog::create([
                    'product_id' => null,
                    'shop_id' => null,
                    'platform' => 'amazon',
                    'status' => 'error',
                    'error_message' => 'No shop connected',
                    'type' => 'product'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No shop connected'
                ]);
            }
            $product = Product::where('shop_id', $shopModel->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)
                        ->orWhere('shopify_id', $id);
                })
                ->first();

            if (!$product) {
                ProductSyncLog::create([
                    'product_id' => null,
                    'shop_id' => $shopModel->id,
                    'platform' => 'amazon',
                    'status' => 'failed',
                    'error_message' => 'Product not found',
                    'type' => 'product'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ]);
            }
            if ($product->synced_to_amazon && !$product->needs_resync) {
                ProductSyncLog::create([
                    'product_id' => $product->id,
                    'shop_id' => $shopModel->id,
                    'platform' => 'amazon',
                    'status' => 'failed',
                    'error_message' => 'Already synced',
                    'type' => 'product'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Already synced'
                ]);
            }
            $amazon = AmazonProduct::where('product_id', $product->id)->first();

            if (!$amazon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amazon data missing'
                ]);
            }
            $bulletPoints = json_decode($amazon->bullet_points, true) ?? [];
            $keywords = json_decode($amazon->platinum_keywords, true) ?? [];
            $searchTerms = json_decode($amazon->search_terms, true) ?? [];
            $images = is_array($product->images) ? $product->images  : json_decode($product->images, true) ?? [];
            $mainImage = $images[0]['src'] ?? 'https://via.placeholder.com/500';
            Log::info('🟢 STEP 6 IMAGES', [
                'images_count' => count($images),
                'main_image' => $mainImage
            ]);
            $otherImages = [];
            foreach ($images as $index => $img) {
                if ($index == 0) continue;
                $otherImages[] = $img['src'];
            }
            $variants = is_array($product->variants) ? $product->variants
                : json_decode($product->variants, true) ?? [];
            if (count($variants) > 1) {
                $amazonService = new \App\Services\AmazonService();
                $response = $amazonService->buildPayload($shopModel, $product,  $amazon);
                // If service already returned JSON response
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData(true);
                    return response()->json([
                        'success' => $data['success'] ?? false,
                        'message' => $data['message'] ?? 'Variant sync failed',
                        'error' => $data['error'] ?? null
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Variant sync failed'
                ]);
            }
            $price = $variants[0]['price'] ?? $product->price;
            $qty = $variants[0]['inventory_quantity'] ?? 0;
            $sku = $variants[0]['sku'] ?? ('SKU-' . $product->id);
            Log::info('🟢 STEP 7 VARIANTS', [
                'variants_count' => count($variants),
                'price' => $price,
                'qty' => $qty,
                'sku' => $sku
            ]);
            $mergedKeywords = array_values(array_filter(array_merge($keywords, $searchTerms)));
            if (empty($mergedKeywords)) {
                $mergedKeywords = ['default keyword'];
            }
            Log::info('🟢 STEP 8 KEYWORDS', [
                'keywords' => $keywords,
                'search_terms' => $searchTerms,
                'merged' => $mergedKeywords
            ]);
            $amazonService = new \App\Services\AmazonService();
            $attributes = $amazonService->buildPayload(
                $shopModel,
                $product,
                $amazon
            );
            if ($product->sub_category_id) {
                $productType =  getCategoryData($product->sub_category_id, 'slug');
            } else {
                $productType = $product->product_type ?? 'HEADPHONES';
            }
            $response = $amazonService->putListing(
                $shopModel,
                $sku,
                $attributes,
                $product
            );
            LOG::info('FINAL AMAZON ATTRIBUTES', $attributes);
            Log::info('🚀 AMAZON REQUEST START', [
                'seller_id' => 'handled_by_service',
                'sku' => $sku,
                'product_id' => $product->id,
                'shop_id' => $shopModel->id,
                'payload_preview' => $attributes
            ]);
            // ✅ FIXED: no inner try
            if (!is_object($response)) {
                Log::error('AMAZON RESPONSE INVALID', [
                    'response' => $response,
                    'sku' => $sku
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Amazon API request failed',
                    'error' => $response
                ]);
            }
            $responseBody = $response->dto();
            $status = $responseBody->status ?? null;
            $issues = $responseBody->issues ?? [];
            $isAccepted = $status === 'ACCEPTED';
            Log::info('✅ AMAZON RESPONSE', [
                'http_status' => method_exists($response, 'status') ? $response->status() : null,
                'submission_status' => $status,
                'is_accepted' => $isAccepted,
                'sku' => $sku,
                'product_id' => $product->id,
                'shop_id' => $shopModel->id,
                'issues_count' => is_array($issues) ? count($issues) : 0,
            ]);
            if (!empty($issues)) {
                Log::warning('⚠️ AMAZON VALIDATION ISSUES', [
                    'sku' => $sku,
                    'issues' => $issues
                ]);
            }
            Log::info('BEFORE DB UPDATE');
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            if (!$isAccepted) {
                Log::error('🔴 AMAZON FAILED', [
                    'status' => $status,
                    'sku' => $sku,
                    'issues' => $issues
                ]);
            }
            Log::info('AFTER DB UPDATE');
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shopModel->id,
                'platform' => 'amazon',
                'status' => $isAccepted ? 'success' : 'failed',
                'message' => $isAccepted
                    ? 'Amazon request accepted'
                    : 'Amazon validation failed',
                'type' => 'product'
            ]);
            // $this->refreshProductsCache($shopModel);
            return response()->json([
                'success' => $isAccepted,
                'message' => $isAccepted
                    ? 'Amazon sync successful'
                    : 'Amazon validation failed',
                'issues' => $issues
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ AMAZON SYNC FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'sku' => $sku ?? null,
                'product_id' => $product->id ?? null,
                'shop_id' => $shopModel->id ?? null,
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id ?? null,
                'shop_id' => $shopModel->id ?? null,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function handleOrdersCreateWebhook(Request $request)
    {
        $payload = $request->getContent();
        $shopDomain = strtolower(trim((string) $request->header('X-Shopify-Shop-Domain')));
        $eventId = trim((string) $request->header('X-Shopify-Event-Id'));
        $webhookId = trim((string) $request->header('X-Shopify-Webhook-Id'));
        if (!$this->shopifyWebhook->isValidWebhook($payload, $request->header('X-Shopify-Hmac-Sha256'))) {
            Log::warning('Rejected Shopify order webhook because HMAC validation failed.', [
                'shop' => $shopDomain,
            ]);
            return response('Invalid webhook signature', 401);
        }
        $shopModel = $this->findShopByIdentifier($shopDomain);

        if (!$shopModel) {
            Log::warning('Rejected Shopify order webhook because shop was not found.', [
                'shop' => $shopDomain,
            ]);
            return response('Shop not found', 404);
        }
        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['id'])) {
            return response('Invalid order payload', 400);
        }
        if ($eventId !== '' && ShopifyOrder::where('shopify_event_id', $eventId)->exists()) {
            return response('OK', 200);
        }
        $customer = data_get($data, 'customer', []);
        $lineItems = data_get($data, 'line_items', []);
        $order = ShopifyOrder::updateOrCreate(
            ['shopify_order_id' => (int) $data['id']],
            [
                'shop_id' => $shopModel->id,
                'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id'),
                'shopify_event_id' => $eventId !== '' ? $eventId : null,
                'shopify_webhook_id' => $webhookId !== '' ? $webhookId : null,
                'order_number' => data_get($data, 'order_number'),
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'customer_first_name' => data_get($customer, 'first_name'),
                'customer_last_name' => data_get($customer, 'last_name'),
                'customer_phone' => data_get($customer, 'phone'),
                'phone' => data_get($data, 'phone'),
                'financial_status' => data_get($data, 'financial_status'),
                'fulfillment_status' => data_get($data, 'fulfillment_status'),
                'currency' => data_get($data, 'currency'),
                'subtotal_price' => (float) data_get($data, 'subtotal_price', 0),
                'total_tax' => (float) data_get($data, 'total_tax', 0),
                'total_discounts' => (float) data_get($data, 'total_discounts', 0),
                'total_price' => (float) data_get($data, 'total_price', 0),
                'line_items_count' => count($lineItems),
                'source_name' => data_get($data, 'source_name'),
                'tags' => data_get($data, 'tags'),
                'note' => data_get($data, 'note'),
                'customer' => $customer ?: null,
                'billing_address' => data_get($data, 'billing_address'),
                'shipping_address' => data_get($data, 'shipping_address'),
                'line_items' => $lineItems ?: null,
                'discount_codes' => data_get($data, 'discount_codes'),
                'shipping_lines' => data_get($data, 'shipping_lines'),
                'tax_lines' => data_get($data, 'tax_lines'),
                'raw_payload' => $data,
                'order_created_at' => $this->parseNullableDate(data_get($data, 'created_at')),
                'processed_at' => $this->parseNullableDate(data_get($data, 'processed_at')),
                'cancelled_at' => $this->parseNullableDate(data_get($data, 'cancelled_at')),
            ]
        );
        // YAHAN SE START
        Log::info('Processing Shopify order line items.', [
            'shop_id' => $shopModel->id,
            'order_id' => $data['id'] ?? null,
            'total_items' => count($lineItems),
        ]);

        foreach ($lineItems as $item) {

            $variantId = $item['variant_id'] ?? null;
            $orderedQty = $item['quantity'] ?? 0;

            Log::info('Processing line item.', [
                'variant_id' => $variantId,
                'ordered_qty' => $orderedQty,
                'title' => $item['title'] ?? null,
                'sku' => $item['sku'] ?? null,
            ]);

            if (!$variantId) {
                Log::warning('Variant ID not found in line item.', [
                    'line_item' => $item,
                ]);
                continue;
            }

            $query = ProductMarketplaceMapping::where('shop_id', $shopModel->id)
                ->where('shopify_variant_id', (string) $variantId);

            Log::info('SQL Query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $mapping = $query->first();

            Log::info('Mapping Result', [
                'mapping' => $mapping?->toArray(),
            ]);

            Log::info('Current Database', [
                'database' => DB::connection()->getDatabaseName(),
            ]);

            if (!$mapping) {
                Log::info('No marketplace mapping found.', [
                    'shop_id' => $shopModel->id,
                    'variant_id' => $variantId,
                ]);
                continue;
            }

            Log::info('Marketplace mapping found.', [
                'mapping_id' => $mapping->id,
                'variant_id' => $variantId,
                'amazon_sku' => $mapping->amazon_sku,
                'marketplace_id' => $mapping->amazon_marketplace_id,
                'current_quantity' => $mapping->quantity,
                'ordered_quantity' => $orderedQty,
            ]);

            $newQuantity = max(0, ((int) $mapping->quantity) - ((int) $orderedQty));

            Log::info('Calculated new inventory.', [
                'amazon_sku' => $mapping->amazon_sku,
                'old_quantity' => $mapping->quantity,
                'ordered_quantity' => $orderedQty,
                'new_quantity' => $newQuantity,
            ]);

            try {

                $response = $this->amazonService->updateInventory(
                    $shopModel,
                    $mapping->amazon_sku,
                    $newQuantity
                );

                Log::info('Webhook inventory sync success.', [
                    'variant_id' => $variantId,
                    'amazon_sku' => $mapping->amazon_sku,
                    'new_quantity' => $newQuantity,
                    'response' => $response,
                ]);
            } catch (\Throwable $e) {

                Log::error('Webhook inventory sync failed.', [
                    'variant_id' => $variantId,
                    'amazon_sku' => $mapping->amazon_sku,
                    'error' => $e->getMessage(),
                ]);
            }

            // Next Step:
            // Amazon inventory update yahin call hoga.
        }
        // YAHAN TAK
        if ($order->wasRecentlyCreated) {

            UserNotificationService::send(
                $shopModel->id,
                'order_sync',
                'Shopify Order Sync Completed',
                'Shopify order ' . ($data['name'] ?? '#' . $data['order_number']) . ' synced successfully.'
            );
        }
        return response('OK', 200);
    }
    public function products(Request $request)
    {
        Log::info('PRODUCTS METHOD START', [
            'url' => $request->fullUrl(),
            'shop_query' => $request->query('shop'),
            'host_query' => $request->query('host'),
        ]);

        set_time_limit(120);

        $shopModel = $this->getActiveShop($request);

        Log::info('PRODUCTS ACTIVE SHOP RESOLVED', [
            'shop_id' => $shopModel?->id,
            'shop' => $shopModel?->shop,
        ]);

        if (!$shopModel) {
            Log::warning('PRODUCTS NO ACTIVE SHOP');

            return redirect('/')->with('error', 'No store connected.');
        }

        Log::info('PRODUCTS BEFORE TOKEN CHECK', [
            'shop_id' => $shopModel->id,
            'shop' => $shopModel->shop,
        ]);

        $this->ensureFreshAccessToken($shopModel);

        Log::info('PRODUCTS AFTER TOKEN CHECK', [
            'shop_id' => $shopModel->id,
            'shop' => $shopModel->shop,
        ]);

        $activeShop = $shopModel->shop;

        $cacheKey = "products_shop_{$shopModel->id}";

        Log::info('PRODUCTS BEFORE CACHE', [
            'shop_id' => $shopModel->id,
            'cache_key' => $cacheKey,
        ]);
        //   REFRESH FLOW (correct order)
        if ($request->has('refresh')) {
            $this->refreshProductsCache($shopModel);
            return response()->json([
                'success' => true
            ]);
        }
        //   LOAD DATA (cache → DB fallback)
        $allProducts = Cache::remember(
            $cacheKey,
            now()->addMinutes(15),
            function () use ($shopModel) {

                Log::info('PRODUCT CACHE MISS → SYNCING FROM SHOPIFY', [
                    'shop_id' => $shopModel->id,
                    'shop' => $shopModel->shop,
                ]);

                // Fetch latest products from Shopify and update DB
                $this->syncProductsToDB($shopModel);

                // Load freshly synced products
                $products = Product::where('shop_id', $shopModel->id)
                    ->latest()
                    ->get();

                Log::info('PRODUCT CACHE REBUILT AFTER SHOPIFY SYNC', [
                    'shop_id' => $shopModel->id,
                    'products_count' => $products->count(),
                ]);

                return $products;
            }
        );
        //   PAGINATION
        $products = $allProducts;

        $shopSubscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopModel->id)
            ->where('status', 'active')
            ->first();

        $productLimitReached = false;
        $productLimit = 0;
        $productUsed = 0;

        if ($shopSubscription && $shopSubscription->plan) {

            $productLimit = $shopSubscription->plan->product_limit;

            $productUsed = Product::where('shop_id', $shopModel->id)
                ->whereBetween('created_at', [
                    $shopSubscription->activated_at,
                    $shopSubscription->current_period_end,
                ])
                ->count();

            $productLimitReached = $productLimit > 0 && $productUsed >= $productLimit;
        }

        $outOfStockProducts = $allProducts->filter(function ($product) {
            return collect($product->variants ?? [])
                ->sum('inventory_quantity') <= 0;
        })->count();
        $totalProducts = $allProducts->count();

        return view('products', compact(
            'products',
            'activeShop',
            'productLimitReached',
            'productLimit',
            'productUsed',
            'totalProducts',
            'outOfStockProducts'
        ));
    }
    public function syncProductsToDB($shopModel)
    {
        $this->ensureFreshAccessToken($shopModel);
        try {
            $response = $this->shopifyRest($shopModel, 'get', 'products.json', [
                'limit' => 250
            ]);
            if (!empty($response['error'])) return;
            $products = $response['products'] ?? [];
            foreach ($products as $product) {
                //  ADD THIS HERE (TOP)
                Log::info('SHOPIFY PRODUCT RAW', [
                    'product_id' => $product['id'],
                    'variants' => $product['variants']
                ]);
                //  STEP 1: Collect inventory item IDs
                $inventoryItemIds = [];
                foreach ($product['variants'] as $variant) {
                    if (!empty($variant['inventory_item_id'])) {
                        $inventoryItemIds[] = $variant['inventory_item_id'];
                    }
                }
                //  STEP 2: Fetch real inventory
                $locationId = $this->getSelectedShopifyLocationId($shopModel);

                if (!empty($inventoryItemIds) && $locationId) {
                    $inventoryRes = $this->shopifyRest(
                        $shopModel,
                        'get',
                        'inventory_levels.json',
                        [
                            'location_ids' => $locationId,
                            'inventory_item_ids' => implode(',', $inventoryItemIds),
                        ]
                    );
                    if (empty($inventoryRes['error'])) {
                        $inventoryMap = [];
                        foreach ($inventoryRes['inventory_levels'] ?? [] as $item) {
                            if (!isset($inventoryMap[$item['inventory_item_id']])) {
                                $inventoryMap[$item['inventory_item_id']] = 0;
                            }
                            $inventoryMap[$item['inventory_item_id']] = $item['available'];
                        }
                        Log::info('INVENTORY MAP', $inventoryMap);
                        //  STEP 3: Update variants with real inventory
                        foreach ($product['variants'] as &$variant) {
                            $variant['inventory_quantity'] =
                                $inventoryMap[$variant['inventory_item_id']] ?? 0;
                        }
                        Log::info('FINAL VARIANTS', $product['variants']);
                    }
                }
                //  STEP 4: SAVE TO DB
                $existingProduct = Product::where('shopify_id', $product['id'])->where('shop_id', $shopModel->id)->first();

                Log::info('EXISTING PRODUCT CHECK', [
                    'shopify_id' => (string)$product['id'],
                    'shop_id' => $shopModel->id,
                    'found' => $existingProduct?->id,
                    'existing_synced' => $existingProduct?->synced_to_amazon,
                    'existing_resync' => $existingProduct?->needs_resync,
                ]);

                $productModel = Product::firstOrNew([
                    'shopify_id' => (string)$product['id'],
                    'shop_id' => $shopModel->id,
                ]);

                $productModel->title = $product['title'];
                $productModel->description = $product['body_html'] ?? '';
                $productModel->price = $product['variants'][0]['price'] ?? 0;
                $productModel->status = $product['status'] ?? 'draft';
                $productModel->product_type = $product['product_type'] ?? null;
                $productModel->vendor = $product['vendor'] ?? null;
                $productModel->tags = $product['tags'] ?? null;
                $productModel->images = $product['images'] ?? [];
                $productModel->variants = $product['variants'] ?? [];
                $productModel->options = $product['options'] ?? [];
                $productModel->metafields = null;

                $productModel->save();

                $saved = Product::where(
                    'shopify_id',
                    (string)$product['id']
                )->where(
                    'shop_id',
                    $shopModel->id
                )->first();

                Log::info('REFRESH SAVE VERIFY', [
                    'product_id' => $saved?->id,
                    'synced_to_amazon' => $saved?->synced_to_amazon,
                    'needs_resync' => $saved?->needs_resync,
                ]);
                //  ADD THIS AFTER SAVE
                Log::info('PRODUCT SAVED', [
                    'shopify_id' => $product['id'],
                    'variants' => $product['variants']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SHOPIFY SYNC FAILED', [
                'error' => $e->getMessage()
            ]);
        }
    }
    public function viewProduct(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect($this->shopAwareUrl('/products', $request->query('shop') ?? $request->input('shop')))
                ->with('error', 'No shop connected.');
        }
        $this->ensureFreshAccessToken($shopModel);
        try {
            $response = $this->shopifyRest($shopModel, 'get', "products/{$id}.json");
            //  dd($response); 
            if (!empty($response['error'])) {
                return redirect($this->shopAwareUrl('/products', $shopModel->shop))
                    ->with('error', 'Product not found.');
            }
            $product = $response['product'] ?? null;
            if (!$product) {
                return redirect($this->shopAwareUrl('/products', $shopModel->shop))
                    ->with('error', 'Product not found.');
            }
            $inventoryItemIds = [];
            foreach ($product['variants'] as $variant) {
                if (!empty($variant['inventory_item_id'])) {
                    $inventoryItemIds[] = $variant['inventory_item_id'];
                }
            }
            $locationId = $this->getSelectedShopifyLocationId($shopModel);

            if (!empty($inventoryItemIds) && $locationId) {
                $inventoryRes = $this->shopifyRest(
                    $shopModel,
                    'get',
                    'inventory_levels.json',
                    [
                        'location_ids' => $locationId,
                        'inventory_item_ids' => implode(',', $inventoryItemIds),
                    ]
                );
                if (empty($inventoryRes['error'])) {
                    $inventoryMap = [];
                    foreach ($inventoryRes['inventory_levels'] ?? [] as $item) {
                        if (!isset($inventoryMap[$item['inventory_item_id']])) {
                            $inventoryMap[$item['inventory_item_id']] = 0;
                        }
                        $inventoryMap[$item['inventory_item_id']] = $item['available'];
                    }
                    foreach ($product['variants'] as &$variant) {
                        $variant['inventory_quantity'] = $inventoryMap[$variant['inventory_item_id']] ?? 0;
                    }
                }
            }
            return view('product-view', compact('product', 'activeShop'));
        } catch (\Exception $e) {
            Log::error('VIEW PRODUCT FAILED', [
                'error' => $e->getMessage()
            ]);
            return redirect($this->shopAwareUrl('/products', $shopModel->shop))
                ->with('error', $e->getMessage());
        }
    }
    public function editProduct(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            return redirect('/products')->with('error', 'No shop connected.');
        }
        try {
            $response = $this->shopifyRest($shopModel, 'get', "products/{$id}.json");
            if (!empty($response['error'])) {
                return back()->with('error', 'Product not found');
            }
            $product = $response['product'] ?? null;
            if (!$product) {
                return back()->with('error', 'Product not found');
            }
            $dbProduct = \App\Models\Product::where('shopify_id', $id)
                ->where('shop_id', $shopModel->id)
                ->first();
            $amazonData = null;
            if ($dbProduct) {
                $amazonData = \App\Models\AmazonProduct::where('product_id', $dbProduct->id)->first();
            }
            $inventoryItemIds = [];
            foreach ($product['variants'] as $variant) {
                if (!empty($variant['inventory_item_id'])) {
                    $inventoryItemIds[] = $variant['inventory_item_id'];
                }
            }
            $locationId = $this->getSelectedShopifyLocationId($shopModel);

            if (!empty($inventoryItemIds) && $locationId) {
                $inventoryRes = $this->shopifyRest(
                    $shopModel,
                    'get',
                    'inventory_levels.json',
                    [
                        'location_ids' => $locationId,
                        'inventory_item_ids' => implode(',', $inventoryItemIds),
                    ]
                );
                if (empty($inventoryRes['error'])) {
                    $inventoryMap = [];
                    foreach ($inventoryRes['inventory_levels'] ?? [] as $item) {
                        if (!isset($inventoryMap[$item['inventory_item_id']])) {
                            $inventoryMap[$item['inventory_item_id']] = 0;
                        }
                        $inventoryMap[$item['inventory_item_id']] = $item['available'];
                    }
                    foreach ($product['variants'] as &$variant) {
                        $variant['inventory_quantity'] = $inventoryMap[$variant['inventory_item_id']] ?? 0;
                    }
                }
            }
            return view('EditProduct', compact('product', 'activeShop', 'amazonData', 'dbProduct'));
        } catch (\Exception $e) {
            Log::error('EDIT PRODUCT FAILED', [
                'error' => $e->getMessage()
            ]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function create(Request $request)
    {
        $shop = $this->getActiveShop($request);

        return view('createProduct', [
            'activeShop' => $shop->shop,
            'currency'   => optional($shop->settings)->currency ?? 'INR',
        ]);
    }



    /**
     * Helper to Upsert Metafields using GraphQL
     */
    protected function syncProductMetafields(Shop $shopModel, $productId, $metaNames, $metaValues)
    {
        if (empty($metaNames) || empty($metaValues)) {
            return [];
        }

        $metafields = [];
        $localMetafields = [];

        foreach ($metaNames as $index => $name) {
            $name = trim($name);
            $value = isset($metaValues[$index]) ? trim($metaValues[$index]) : '';

            if ($name !== '' && $value !== '') {
                $metafields[] = [
                    'ownerId'   => "gid://shopify/Product/{$productId}",
                    'namespace' => 'custom', // Default namespace for custom attributes
                    'key'       => $name,
                    'type'      => 'single_line_text_field', // Best default for text/string
                    'value'     => (string)$value,
                ];
                $localMetafields[$name] = $value;
            }
        }

        if (empty($metafields)) {
            return $localMetafields;
        }

        $query = '
            mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
                metafieldsSet(metafields: $metafields) {
                    metafields {
                        id
                        namespace
                        key
                        value
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shopModel->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->post("https://{$shopModel->shop}/admin/api/2026-01/graphql.json", [
                    'query' => $query,
                    'variables' => [
                        'metafields' => $metafields
                    ]
                ]);
            $result = $response->json();

            if (isset($result['data']['metafieldsSet']['userErrors']) && count($result['data']['metafieldsSet']['userErrors']) > 0) {
                Log::error('GraphQL MetafieldsSet UserErrors', [
                    'shop'       => $shopModel->shop,
                    'product_id' => $productId,
                    'errors'     => $result['data']['metafieldsSet']['userErrors']
                ]);
            } else {
                Log::info('Successfully synced metafields via GraphQL', [
                    'shop'       => $shopModel->shop,
                    'product_id' => $productId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('GraphQL Metafields Sync Exception', [
                'shop'       => $shopModel->shop,
                'product_id' => $productId,
                'error'      => $e->getMessage()
            ]);
        }

        return $localMetafields;
    }


    public function createProduct(Request $request)
    {
        Log::info('STEP 1: REQUEST DATA', $request->all());
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'product_type' => 'nullable|string|max:255',
            'vendor' => 'nullable|string|max:255',
            'tags' => 'nullable|string',
            'images.*' => 'nullable|image|max:5120'
        ]);

        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            return redirect()->back()->withInput()->with('error', 'No shop connected. Please install the app first.');
        }

        try {
            $localImages = $this->UploadImageProvideUrl($request);
            $payloadData = $this->buildProductPayload($request);
            $productPayload = [
                'product' => $payloadData['product']
            ];

            $variantImageMap = $payloadData['variant_image_map'];
            $payloadIndexMap = $payloadData['payload_index_map'];

            $response = $this->shopifyRest(
                $shopModel,
                'post',
                'products.json',
                $productPayload
            );

            if (!empty($response['error'])) {
                return redirect()->back()->withInput()->with('error', $response['message'] ?? 'Failed to create product');
            }

            $productData = $response['product'] ?? null;
            if (!$productData) {
                return redirect()->back()->withInput()->with('error', 'Failed to create product: Unknown error occurred');
            }

            // --- FLOW: INVENTORY LEVEL SYNC ---
            $locationId = $this->getSelectedShopifyLocationId($shopModel);

            if ($locationId && !empty($productData['variants'])) {
                foreach ($productData['variants'] as $index => $variant) {
                    $inventoryItemId = $variant['inventory_item_id'] ?? null;

                    // Align Shopify's variant index with the original form index
                    $formIndex = $payloadIndexMap[$index] ?? $index;
                    $formVariant = $request->input("variants.{$formIndex}") ?? [];
                    $qty = (int) ($formVariant['qty'] ?? 0);

                    if ($inventoryItemId && $qty > 0) {
                        $this->shopifyRest($shopModel, 'post', 'inventory_levels/set.json', [
                            'location_id' => $locationId,
                            'inventory_item_id' => $inventoryItemId,
                            'available' => $qty,
                        ]);
                    }
                }
            }

            // --- FLOW: IMAGE ATTACHMENT TO VARIANTS ---
            $updatedResponse = $this->shopifyRest($shopModel, 'get', "products/{$productData['id']}.json");
            $productData = $updatedResponse['product'] ?? $productData;

            $productImages = $productData['images'] ?? [];
            $productVariants = $productData['variants'] ?? [];

            foreach ($variantImageMap as $payloadIndex => $imagePosition) {
                $variantId = $productVariants[$payloadIndex]['id'] ?? null;
                $imageId = $productImages[$imagePosition]['id'] ?? null;

                if (!$variantId || !$imageId) {
                    continue;
                }

                $this->shopifyRest(
                    $shopModel,
                    'put',
                    "variants/{$variantId}.json",
                    [
                        'variant' => [
                            'id' => $variantId,
                            'image_id' => $imageId
                        ]
                    ]
                );
            }

            // --- FLOW: MERGE FOR LOCAL DB ---
            $finalVariants = [];
            foreach ($productData['variants'] as $index => $variant) {
                $formIndex = $payloadIndexMap[$index] ?? $index;
                $formVariant = $request->input("variants.{$formIndex}") ?? [];

                $finalVariants[] = [
                    'id' => $variant['id'],
                    'price' => $variant['price'],
                    'sku' => $variant['sku'] ?? null,
                    'inventory_quantity' => (int) ($formVariant['qty'] ?? 0),
                    'option1' => $formVariant['option1'] ?? null,
                    'option2' => $formVariant['option2'] ?? null,
                ];
            }

            // --- FLOW: SYNC METAFIELDS VIA GRAPHQL ---
            $metaNames = $request->input('meta_name', []);
            $metaValues = $request->input('meta_value', []);
            $localMetafields = $this->syncProductMetafields($shopModel, $productData['id'], $metaNames, $metaValues);

            // Map Category details accurately
            $category = \App\Models\Category::where('id', $request->input('category'))->first();
            $producttype = !empty($category) ? $category->category : $request->input('product_type');
            $category_id = !empty($category) ? $category->id : $request->input('category');

            $subcategory = \App\Models\Category::where('id', $request->input('sub_category'))->first();
            $sub_category_id = !empty($subcategory) ? $subcategory->id : ($category->id ?? 0);
            $subcategory = !empty($subcategory) ? $subcategory->slug : ($category->slug ?? 'test');

            // 🔹 Save Product in DB
            $dbProduct = \App\Models\Product::updateOrCreate(
                [
                    'shopify_id' => (string)$productData['id'],
                    'shop_id' => $shopModel->id
                ],
                [
                    'title' => $request->title,
                    'description' => $request->description,
                    'price' => $finalVariants[0]['price'] ?? 0,
                    'status' => $request->status ?? 'draft',
                    'product_type' => $subcategory,
                    'category' => $producttype,
                    'category_id' => $category_id,
                    'sub_category_id' => $sub_category_id,
                    'vendor' => $request->vendor,
                    'tags' => $request->tags,
                    'images' => json_encode($productData['images'] ?? []),
                    'variants' => json_encode($finalVariants),
                    'options' => json_encode($productData['options'] ?? []),
                    'metafields' => json_encode($localMetafields),
                    'synced_to_amazon' => 0,
                    'local_images' => json_encode($localImages)
                ]
            );

            $syncid = $request->sync_id ?? '';
            if ($syncid) {
                $updatesync = new \App\Http\Controllers\ProductSchemaController();
                $updatesync->updateSyncShopify($syncid, $updatedResponse);
            }

            // 🔹 Save Amazon Data
            \App\Models\AmazonProduct::updateOrCreate(
                ['product_id' => $dbProduct->id],
                [
                    'amazon_title' => $request->input('amazon_title') ?? $request->title,
                    'search_terms' => json_encode(
                        $request->input('search_terms')
                            ? explode(',', $request->input('search_terms'))
                            : []
                    ),
                    'platinum_keywords' => json_encode($request->input('platinum_keywords', [])),
                    'bullet_points' => json_encode($request->input('bullet_points', [])),
                    'target_audience' => json_encode($request->input('target_audience', [])),
                    'subject_matter' => json_encode($request->input('subject_matter', [])),
                    'sku' => $request->input('sku'),
                    'intended_use' => json_encode($request->input('intended_use', [])),
                ]
            );
        } catch (\Exception $e) {
            Log::error('CREATE FAILED', [
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        \Illuminate\Support\Facades\Cache::forget("products_shop_{$shopModel->id}");

        return redirect($this->shopAwareUrl('/products', $shopModel->shop))
            ->with('success', 'Product created successfully!');
    }



    public function updateProduct(Request $request, $id)
    {
        set_time_limit(120);
        // DEBUG: Log entry into function
        Log::info("START: updateProduct called for Shopify Product ID: " . $id);

        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            Log::warning("FAIL: No active shop found for request");
            return back()->with('error', 'No shop connected');
        }

        // DEBUG: Log Shop Model state
        Log::debug("Active Shop found: ID " . $shopModel->id . ", Shop domain: " . $shopModel->shop);

        try {
            // 1. Fetch Local Product
            $dbProduct = Product::where('shop_id', $shopModel->id)
                ->where('shopify_id', $id)
                ->first();

            if ($dbProduct) {
                Log::debug("DB Product found: ID " . $dbProduct->id . " (Title: " . $dbProduct->title . ")");
            } else {
                Log::warning("DB Product NOT found for Shopify ID: " . $id);
            }

            // 2. Image Processing & Preservation (Main Gallery ONLY)
            $imagesdata = [];

            // a. Preserve existing images that weren't deleted
            $existingImages = $request->input('existing_images', []);
            $deletedImages = $request->input('deleted_images', []);
            $keptImages = array_diff($existingImages, $deletedImages);

            foreach ($keptImages as $imgId) {
                if (!empty($imgId) && is_numeric($imgId)) {
                    $imagesdata[] = ['id' => (int) $imgId];
                }
            }

            // b. Add ONLY newly uploaded generic gallery images here
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $galleryImage) {
                    if ($galleryImage->isValid()) {
                        $imagesdata[] = [
                            'attachment' => base64_encode(file_get_contents($galleryImage->getRealPath()))
                        ];
                    }
                }
            }

            $productPayload = [
                "product" => [
                    "id" => (int)$id,
                    "title" => $request->title,
                    "body_html" => $request->description,
                    "vendor" => $request->vendor,
                    "product_type" => $request->product_type,
                    "status" => $request->status,
                    "images" => array_values($imagesdata) // Syncs gallery and deletes removed ones
                ]
            ];

            // 3. Shopify Product API Update
            Log::info("Sending PUT request to Shopify for Product ID: " . $id);
            $sh = $this->shopifyRest($shopModel, 'put', "products/{$id}.json", $productPayload);
            Log::debug("Shopify Product API Response received", ['response' => $sh]);

            // 4 & 5. Update Existing Variants, Create New Variants & Update Inventory
            $variantIds = $request->input('variant_ids', []);
            $variantCombos = $request->input('variant_combo', []);
            $existingVariantImages = $request->input('existing_variant_image', []);
            $variantImages = $request->file('variant_image', []); // Array of newly uploaded variant images

            Log::info("Iterating over variants. Total Count: " . count($variantIds));

            $locationId = $this->getSelectedShopifyLocationId($shopModel);

            foreach ($variantIds as $index => $variantId) {
                $price = $request->input("variant_price.$index");
                $sku   = $request->input("variant_sku.$index");
                $qty   = (int)$request->input("variant_quantity.$index", 0);

                // -------------------------------------------------------------
                // Deterministic Image ID Assignment [BULLETPROOF FIX]
                // -------------------------------------------------------------
                $assignedImageId = null;

                // 1. If a NEW image was uploaded specifically for this variant
                if (isset($variantImages[$index]) && $variantImages[$index]->isValid()) {
                    Log::info("Uploading new image directly for variant index: {$index}");

                    // Directly push to Shopify's image endpoint to guarantee exact ID retrieval
                    $newImageRes = $this->shopifyRest($shopModel, 'post', "products/{$id}/images.json", [
                        'image' => [
                            'attachment' => base64_encode(file_get_contents($variantImages[$index]->getRealPath()))
                        ]
                    ]);

                    if (!empty($newImageRes['image']['id'])) {
                        $assignedImageId = $newImageRes['image']['id'];
                    }
                }
                // 2. Otherwise, check if it had an existing image that we intentionally kept
                elseif (isset($existingVariantImages[$index]) && in_array($existingVariantImages[$index], $keptImages)) {
                    $assignedImageId = (int) $existingVariantImages[$index];
                }

                if (!empty($variantId)) {
                    // --- FLOW: UPDATE EXISTING VARIANT ---
                    Log::info("Updating Existing Variant ID: " . $variantId . " | Price: " . $price);

                    $variantPayload = [
                        "variant" => [
                            "id" => $variantId,
                            "price" => $price,
                            "sku"   => $sku,
                        ]
                    ];

                    // Explicitly link/unlink the image ID to the existing variant
                    $variantPayload['variant']['image_id'] = $assignedImageId ?: null;

                    $this->shopifyRest($shopModel, 'put', "variants/{$variantId}.json", $variantPayload);

                    // Update Inventory for Existing Variant
                    $inventoryItemId = $request->input("inventory_item_id.$index");
                    if ($inventoryItemId && $locationId) {
                        $this->shopifyRest($shopModel, 'post', 'inventory_levels/set.json', [
                            "location_id" => $locationId,
                            "inventory_item_id" => $inventoryItemId,
                            "available" => $qty,
                        ]);
                    }
                } else {
                    // --- FLOW: CREATE NEW VARIANT ---
                    Log::info("Creating New Variant for Product ID: " . $id);

                    $comboJson = $variantCombos[$index] ?? null;
                    $options = [];
                    if ($comboJson) {
                        $combo = json_decode($comboJson, true);
                        if (is_array($combo)) {
                            foreach ($combo as $i => $c) {
                                $options['option' . ($i + 1)] = $c['value'] ?? '';
                            }
                        }
                    }

                    $newVariantPayload = [
                        "variant" => array_merge([
                            "price" => $price,
                            "sku"   => $sku,
                            "inventory_management" => "shopify",
                            "inventory_policy" => "deny",
                        ], $options)
                    ];

                    // Attach the accurate image ID to the new variant
                    if ($assignedImageId) {
                        $newVariantPayload['variant']['image_id'] = $assignedImageId;
                    }

                    $createRes = $this->shopifyRest($shopModel, 'post', "products/{$id}/variants.json", $newVariantPayload);

                    if (empty($createRes['error']) && isset($createRes['variant'])) {
                        $newVariant = $createRes['variant'];

                        // Instantly update the inventory for the newly created variant
                        $newInventoryItemId = $newVariant['inventory_item_id'] ?? null;
                        if ($newInventoryItemId && $locationId) {
                            $this->shopifyRest($shopModel, 'post', 'inventory_levels/set.json', [
                                "location_id" => $locationId,
                                "inventory_item_id" => $newInventoryItemId,
                                "available" => $qty,
                            ]);
                        }
                    }
                }
            }

            // --- FLOW: SYNC METAFIELDS VIA GRAPHQL ---
            Log::info("Syncing Metafields for Product ID: " . $id);
            $metaNames = $request->input('meta_name', []);
            $metaValues = $request->input('meta_value', []);
            $localMetafields = $this->syncProductMetafields($shopModel, $id, $metaNames, $metaValues);

            // 6. Determine Category
            $category = Category::where('id', $request->input('category'))->first();
            $producttype = "";
            if ($category) {
                $producttype = $category->category;
                $category_id = $category->id;
            } else {
                $producttype = $request->input('product_type');
                $category_id = $request->input('category');
            }

            $subcategory = Category::where('id', $request->input('sub_category'))->first();
            if ($subcategory) {
                $sub_category_id = $subcategory->id;
                $subcategory = $subcategory->slug;
            } else {
                $sub_category_id = $category->id ?? 0;
                $subcategory = $category->slug ?? 'test';
            }

            // 7. Update Local DB
            if ($dbProduct) {
                $productdata = [];
                if ((int)$dbProduct->synced_to_amazon === 1) {
                    $productdata['needs_resync'] = 1;
                    $productdata['synced_to_amazon'] = 0;
                }

                $productdata['title'] = $request->title;
                $productdata['description'] = $request->description;
                $productdata['vendor'] = $request->vendor;
                $productdata['product_type'] = $subcategory;
                $productdata['category'] = $producttype;
                $productdata['category_id'] = $category_id;
                $productdata['sub_category_id'] = $sub_category_id;
                $productdata['metafields'] = json_encode($localMetafields);

                $dbProduct->update($productdata);
            }

            // 8. Sync Log
            ProductSyncLog::create([
                'product_id' => $dbProduct->id,
                'shop_id' => $shopModel->id,
                'platform' => 'shopify',
                'status' => 'success',
                'message' => 'Product "' . $dbProduct->title . '" was updated successfully during resync.',
                'type' => 'product'
            ]);

            // 9. Amazon Data Update
            if ($dbProduct) {
                $amazonData = AmazonProduct::where('product_id', $dbProduct->id)->first();
                $data = [
                    'amazon_title' => $request->input('amazon_title'),
                    'sku' => $request->input('sku'),
                    'platinum_keywords' => json_encode($request->input('platinum_keywords', [])),
                    'bullet_points' => json_encode($request->input('bullet_points', [])),
                    'target_audience' => json_encode($request->input('target_audience', [])),
                    'subject_matter' => json_encode($request->input('subject_matter', [])),
                    'intended_use' => json_encode($request->input('intended_use', [])),
                    'search_terms' => json_encode(
                        $request->input('search_terms')
                            ? array_map('trim', explode(',', $request->input('search_terms')))
                            : []
                    ),
                ];

                if ($amazonData) {
                    $amazonData->update($data);
                } else {
                    $data['product_id'] = $dbProduct->id;
                    AmazonProduct::create($data);
                }
            }

            // UserNotificationService::send(
            //     $shopModel->id,
            //     'inventory_stock_update',
            //     'Inventory Stock Updated',
            //     "{$shopModel->shop} inventory has been updated successfully."
            // );

            $message = sprintf(
                '%s - "%s" has been updated successfully.',
                $shopModel->shop,
                $dbProduct->title
            );

            UserNotificationService::send(
                $shopModel->id,
                'inventory_stock_update',
                'Inventory Stock Updated',
                $message
            );

            // Sync down to your local DB
            $this->refreshProductsCache($shopModel);

            Log::info("END: updateProduct completed successfully for ID: " . $id);
            return redirect($this->shopAwareUrl('/products', $shopModel->shop))
                ->with('success', 'Product updated successfully!');
        } catch (\Exception $e) {
            Log::error('CRITICAL ERROR in updateProduct: ' . $e->getTraceAsString(), [
                'shopify_id' => $id,
                'shop_id' => $shopModel->id ?? 'unknown',
                'message' => $e->getMessage()
            ]);

            ProductSyncLog::create([
                'product_id' => $dbProduct->id ?? null,
                'shop_id' => $shopModel->id ?? null,
                'platform' => 'shopify',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return back()->with('error', $e->getMessage());
        }
    }
    public function deleteProduct(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            return response()->json(['success' => false, 'message' => 'No shop connected'], 401);
        }
        try {
            Log::info('Delete Product Debug', [
                'id' => $id,
                'type' => gettype($id),
                'endpoint' => "products/{$id}.json",
            ]);
            $response = $this->shopifyRest($shopModel, 'delete', "products/{$id}.json");
            if (!empty($response['error'])) {
                throw new RuntimeException($response['message'] ?? 'Failed to delete product');
            }
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product from Shopify: ' . $exception->getMessage(),
            ], 400);
        }
        Product::withTrashed()
            ->where('shop_id', $shopModel->id)
            ->where('shopify_id', $id)
            ->first()?->forceDelete();

        $updatesync = new ProductSchemaController();
        $updatesync->updatelog($id, 'shopify', 'deleted', true);

        $this->refreshProductsCache($shopModel);
        return response()->json(['success' => true, 'message' => 'Product deleted successfully']);
    }
    private function refreshProductsCache($shopModel): void
    {
        $cacheKey = "products_shop_{$shopModel->id}";
        Cache::forget($cacheKey);
        $this->syncProductsToDB($shopModel);
        $products = Product::where('shop_id', $shopModel->id)
            ->latest()
            ->get();
        Cache::put(
            $cacheKey,
            $products,
            now()->addMinutes(15)
        );
        Log::info('PRODUCT CACHE REBUILT', [
            'shop_id' => $shopModel->id,
            'products_count' => $products->count()
        ]);
    }
    private function oauthScopes(): string
    {
        return collect(explode(',', (string) config('services.shopify.scopes', '')))
            ->map(fn($scope) => trim($scope))
            ->filter()
            ->merge(['read_products', 'write_products', 'read_orders', 'read_locations'])
            ->unique()
            ->implode(',');
    }
    // private function rememberActiveShop(Shop $shop): Shop
    // {
    //     session(['active_shop' => $shop->shop]);
    //     $shop->touch();
    //     return $shop->fresh() ?? $shop;
    // }
    private function decodeShopifyHost(?string $host): ?string
    {
        $host = trim((string) $host);
        if ($host === '') {
            return null;
        }
        $padding = strlen($host) % 4;
        if ($padding > 0) {
            $host .= str_repeat('=', 4 - $padding);
        }
        $decodedHost = base64_decode(strtr($host, '-_', '+/'), true);
        return $decodedHost !== false ? $decodedHost : $host;
    }


    public function extractShopIdentifier(?Request $request = null): ?string
    {
        $request ??= request();

        foreach ([$request?->query('shop'), $request?->input('shop')] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return strtolower($candidate);
            }
        }

        $hostValue = $this->decodeShopifyHost(
            $request?->query('host') ?? $request?->input('host')
        );

        if (!empty($hostValue)) {
            if (preg_match('#/store/([^/?]+)#i', $hostValue, $matches)) {
                return strtolower($matches[1]);
            }

            if (preg_match('#^([a-z0-9-]+)\.myshopify\.com$#i', $hostValue, $matches)) {
                return strtolower($matches[1] . '.myshopify.com');
            }
        }

        $referer = $request?->headers->get('referer');

        if (!empty($referer) && preg_match('#/store/([^/?]+)#i', $referer, $matches)) {
            return strtolower($matches[1]);
        }

        // Session fallback
        // Session fallback
        foreach (
            [
                session('active_shop'),
                session('amazon_shop'), // optional backward compatibility
                session('shop'),        // optional backward compatibility
            ] as $candidate
        ) {

            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return strtolower($candidate);
            }
        }

        return null;
    }
    public function findShopByIdentifier(?string $identifier): ?Shop
    {
        $identifier = strtolower(trim((string) $identifier));
        if ($identifier === '') {
            return null;
        }
        if (str_contains($identifier, '.myshopify.com')) {
            return Shop::whereRaw('LOWER(shop) = ?', [$identifier])->first();
        }
        return Shop::whereRaw('LOWER(shop) = ?', [$identifier . '.myshopify.com'])->first();
    }
    protected function getActiveShop(?Request $request = null): ?Shop
    {
        $request ??= request();
        $shopIdentifier = $this->extractShopIdentifier($request);
        if ($shopIdentifier === null) {
            LOG::warning('NO SHOP IDENTIFIER FOUND', [
                'query_shop' => $request?->query('shop'),
                'query_host' => $request?->query('host'),
                'path' => $request?->path(),
            ]);
            return null;
        }
        $shop = $this->findShopByIdentifier($shopIdentifier);
        if (!$shop) {
            LOG::warning('Shopify shop not found in database.', [
                'shop_identifier' => $shopIdentifier,
                'query_shop' => $request?->query('shop'),
                'query_host' => $request?->query('host'),
                'path' => $request?->path(),
            ]);
            return null;
        }
        LOG::info('ACTIVE SHOP CHECK', [
            'shop' => $shop->shop,
            'is_active' => $shop->is_active,
            'access_token_empty' => empty($shop->access_token),
        ]);
        if (
            (int) $shop->is_active !== 1 ||
            empty($shop->access_token)
        ) {
            LOG::warning('INACTIVE SHOP BLOCKED', [
                'shop' => $shop->shop,
                'is_active' => $shop->is_active,
            ]);
            return null;
        }
        return $shop;
    }
    protected function shopAwareUrl(string $path, ?string $shopDomain = null): string
    {
        if (empty($shopDomain)) {
            return $path;
        }
        return $path . '?shop=' . urlencode($shopDomain);
    }
    protected function shopifyRest(Shop $shop, string $method, string $endpoint, array $payload = []): array
    {
        $method = strtolower($method);
        $url = sprintf(
            'https://%s/admin/api/%s/%s',
            $shop->shop,
            config('services.shopify.api_version', '2026-01'),
            ltrim($endpoint, '/')
        );
        $options = [];
        if ($method === 'get') {
            $options['query'] = $payload;
        } else {
            $options['json'] = $payload;
        }
        try {
            $response = Http::timeout(120)
                ->connectTimeout(120)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->send(strtoupper($method), $url, $options);
            if (!$response->successful()) {
                $body = $response->body();
                $json = $response->json();
                Log::error('Shopify API Error', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'json' => $response->json(),
                ]);
                return [
                    'error' => true,
                    'status' => $response->status(),
                    'message' => $json ? json_encode($json) : $body,
                ];
            }
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Shopify API Exception', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function getSelectedShopifyLocationId(Shop $shop): ?int
    {
        $locations = $shop->shopify_locations ?? [];
        $index = $shop->selected_location_index;

        if ($index === null || !isset($locations[$index])) {
            Log::warning('SHOPIFY LOCATION NOT SELECTED', [
                'shop_id' => $shop->id,
                'selected_location_index' => $index,
            ]);

            return null;
        }

        $locationId = $locations[$index]['id'] ?? null;

        if (!$locationId) {
            Log::warning('SHOPIFY SELECTED LOCATION ID MISSING', [
                'shop_id' => $shop->id,
                'selected_location_index' => $index,
                'location' => $locations[$index],
            ]);

            return null;
        }

        return (int) $locationId;
    }
    private function formatDescription($text): string
    {
        if (empty($text)) {
            return '';
        }
        $text = preg_replace('/^\s*<p>\s*/i', '', (string) $text);
        $text = preg_replace('/\s*<\/p>\s*$/i', '', (string) $text);
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        return '<p>' . nl2br($text) . '</p>';
    }
    private function normalizeStringArray(array $values): array
    {
        return collect($values)
            ->map(fn($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }
    private function parseSearchTerms(?string $searchTerms): array
    {
        return collect(explode(',', (string) $searchTerms))
            ->map(fn($term) => trim($term))
            ->filter()
            ->values()
            ->all();
    }
    private function saveAmazonProductData(Product $product, Request $request, array $searchTerms): void
    {
        AmazonProduct::updateOrCreate(
            ['product_id' => $product->id],
            [
                'amazon_title' => $request->input('amazon_title') ?: $request->input('title'),
                'search_terms' => $searchTerms ?: null,
                'platinum_keywords' => $this->normalizeStringArray($request->input('platinum_keywords', [])) ?: null,
                'bullet_points' => $this->normalizeStringArray($request->input('bullet_points', [])) ?: null,
                'target_audience' => $this->normalizeStringArray($request->input('target_audience', [])) ?: null,
                'subject_matter' => $this->normalizeStringArray($request->input('subject_matter', [])) ?: null,
                'sku' => $request->input('sku'),
                'intended_use' => $this->normalizeStringArray($request->input('intended_use', [])) ?: null,
            ]
        );
    }
    private function buildProductPayload(Request $request): array
    {
        $status = $request->input('status', 'draft');
        if ($status === 'inactive') {
            $status = 'draft';
        }

        $images = [];
        $variantImageMap = [];


        $existingImages = $request->input('existing_images', []);
        $deletedImages = $request->input('deleted_images', []);
        $keptImages = array_diff($existingImages, $deletedImages);

        foreach ($keptImages as $img) {
            if (filter_var($img, FILTER_VALIDATE_URL)) {
                $images[] = ['src' => $img]; // Instructs Shopify to download from Amazon URL
            } elseif (is_numeric($img)) {
                $images[] = ['id' => (int) $img];
            }
        }


        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $galleryImage) {
                if ($galleryImage->isValid()) {
                    $images[] = [
                        'attachment' => base64_encode(
                            file_get_contents($galleryImage->getRealPath())
                        )
                    ];
                }
            }
        }


        foreach ($request->file('variants', []) as $index => $variantFiles) {
            if (!empty($variantFiles['image']) && $variantFiles['image']->isValid()) {
                $file = $variantFiles['image'];
                $images[] = [
                    'attachment' => base64_encode(
                        file_get_contents($file->getRealPath())
                    )
                ];
                $variantImageMap[$index] = count($images) - 1;
            }
        }


        $variantsInput = $request->input('variants', []);
        $variantNames = $request->input('variant_names', []);
        $variants = [];
        $payloadIndexToFormIndexMap = [];

        foreach ($variantsInput as $originalIndex => $v) {
            // Enforce fallback price to prevent array skips
            $vPrice = !empty($v['price']) ? (float) $v['price'] : (float) $request->input('price', 0);

            $variant = [
                'price' => $vPrice,
                'sku' => !empty($v['sku']) ? $v['sku'] : 'SKU-' . uniqid(),
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
            ];

            if (!empty($v['option1'])) $variant['option1'] = trim($v['option1']);
            if (!empty($v['option2'])) $variant['option2'] = trim($v['option2']);
            if (!empty($v['option3'])) $variant['option3'] = trim($v['option3']);

            // Attach any existing image ID mapped to this variant directly
            if (!empty($v['existing_image_id']) && is_numeric($v['existing_image_id'])) {
                $variant['image_id'] = (int) $v['existing_image_id'];
            }

            $variants[] = $variant;
            $payloadIndexToFormIndexMap[count($variants) - 1] = $originalIndex;
        }

        if (empty($variants)) {
            $variants[] = [
                'price' => (float) $request->input('price', 0),
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
            ];
            $payloadIndexToFormIndexMap[0] = 0;
        }

        // Convert map to ensure index alignment: {payloadIndex => imagePosition}
        $finalVariantImageMap = [];
        foreach ($payloadIndexToFormIndexMap as $payloadIdx => $formIdx) {
            if (isset($variantImageMap[$formIdx])) {
                $finalVariantImageMap[$payloadIdx] = $variantImageMap[$formIdx];
            }
        }

        // Build options
        $options = [];
        foreach ($variantNames as $i => $name) {
            $values = collect($variantsInput)
                ->pluck('option' . ($i + 1))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (!empty($name) && !empty($values)) {
                $options[] = [
                    'name' => $name,
                    'values' => $values,
                ];
            }
        }

        $category = \App\Models\Category::where('id', $request->input('category'))->first();
        $producttype = !empty($category) ? $category->category : $request->input('product_type');

        $subcategory = \App\Models\Category::where('id', $request->input('sub_category'))->first();
        $subcategory = !empty($subcategory) ? $subcategory->slug : ($category->slug ?? 'test');

        return [
            'product' => [
                'title' => $request->input('title'),
                'body_html' => $this->formatDescription($request->input('description')),
                'vendor' => $request->input('vendor'),
                'product_type' => $request->input('product_type'),
                'category' => $producttype ?: $subcategory,
                'tags' => $request->input('tags'),
                'status' => $status,
                'options' => $options,
                'variants' => $variants,
                'images' => $images
            ],
            'variant_image_map' => $finalVariantImageMap,
            'payload_index_map' => $payloadIndexToFormIndexMap
        ];
    }
    private function parseNullableDate(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        return Carbon::parse($value);
    }
    // public function show($id)
    // {
    //     $shop = \App\Models\Shop::with('subscription.plan')->findOrFail($id);
    //     //   extra data
    //     $productCount = $shop->products()->count();
    //     $logCount = \App\Models\Log::where('shop_id', $shop->id)->count();
    //     $orderCount = $shop->orders()->count();
    //     return view('admin.shops.view', compact(
    //         'shop',
    //         'productCount',
    //         'logCount',
    //         'orderCount'
    //     ));
    // }

    public function show($id)
    {
        $shop = Shop::with('subscription.plan')->findOrFail($id);

        $customPlan = Plan::where('shop_id', $shop->id)->first();

        $productCount = $shop->products()->count();
        $logCount = ProductSyncLog::where('shop_id', $shop->id)->count();
        $orderCount = $shop->orders()->count();

        return view('admin.shops.view', compact(
            'shop',
            'customPlan',
            'productCount',
            'logCount',
            'orderCount'
        ));
    }
    public function getSellerIdFull()
    {
        try {
            //   STEP 1: CONNECTOR
            $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                clientId: AdminSetting::get(
                    'production_client_id',
                    config('amazon.client_id')
                ),
                clientSecret: AdminSetting::get(
                    'production_client_secret',
                    config('amazon.client_secret')
                ),
                refreshToken: AdminSetting::get(
                    'amazon_refresh_token',
                    config('amazon.refresh_token')
                ),
                endpoint: \SellingPartnerApi\Enums\Endpoint::NA_SANDBOX
            );
            // ,
            //     awsAccessKeyId: config('amazon.aws_access_key_id'),
            //     awsSecretAccessKey: config('amazon.aws_secret_access_key'),
            Log::info('🟢 CONNECTOR CREATED');
            //   STEP 2: API CALL
            $res = $connector->sellersV1()->getMarketplaceParticipations();
            //   STEP 3: RAW RESPONSE (MOST IMPORTANT)
            $rawBody = $res->body();
            $json = $res->json();
            $headers = $res->headers();
            Log::info('🟢 RAW BODY', ['body' => $rawBody]);
            Log::info('🟢 JSON RESPONSE', $json ?? []);
            Log::info('🟢 HEADERS', $headers ? $headers->all() : []);
            //   STEP 4: TRY EXTRACT sellerId
            $sellerId = null;
            if (!empty($json['payload'])) {
                foreach ($json['payload'] as $item) {
                    if (isset($item['sellerId'])) {
                        $sellerId = $item['sellerId'];
                        break;
                    }
                }
            }
            //   STEP 5: FINAL RESPONSE
            return response()->json([
                'status' => 'success',
                'seller_id' => $sellerId ?? 'NOT_FOUND_IN_SANDBOX ❌',
                'marketplace_id' => $json['payload'][0]['marketplace']['id'] ?? null,
                // 👇 DEBUG (VERY IMPORTANT)
                'raw_body' => $rawBody,
                'headers' => $headers,
                'full_json' => $json
            ]);
        } catch (\Exception $e) {
            Log::error('❌ SELLER ID FETCH FAILED', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    }

    private function fetchAmazonOrders($forceRefresh = false)
    {
        $cacheKey = 'amazon_orders_' . session('active_shop');
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        $date = now()->subDays(30)->toDateTimeString();
        if (session('active_shop')) {
            $shop = \App\Models\Shop::where('shop', session('active_shop'))->first();
            $createdAfter = \Carbon\Carbon::parse($shop->created_at, 'UTC')->toAtomString();
        } else {
            $createdAfter = now()->subDays(30)->toDateTimeString();
        }

        return Cache::remember($cacheKey, now()->addHours(24), function () {
            try {
                $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                    clientId: AdminSetting::get(
                        'production_client_id',
                        config('amazon.client_id')
                    ),
                    clientSecret: AdminSetting::get(
                        'production_client_secret',
                        config('amazon.client_secret')
                    ),
                    refreshToken: AdminSetting::get(
                        'amazon_refresh_token',
                        config('amazon.refresh_token')
                    ),
                    endpoint: \SellingPartnerApi\Enums\Endpoint::NA_SANDBOX
                );
                $response = $connector->ordersV0()->getOrders(
                    marketplaceIds: ['ATVPDKIKX0DER'],
                    createdAfter: 'TEST_CASE_200'
                );
                $data = json_decode($response->body(), true);
                $orders = $data['payload']['Orders'] ?? [];
                //   YAHI MAGIC HAI
                foreach ($orders as $order) {
                    $orderId = $order['AmazonOrderId'] ?? null;
                    if ($orderId) {
                        Cache::put(
                            'amazon_order_' . $orderId,
                            $order,
                            now()->addHours(24)
                        );
                    }
                }
                return $orders;
            } catch (\Exception $e) {

                return [];
            }
        });
    }

    public function getAmazonOrders()
    {
        try {
            $orders = $this->fetchAmazonOrders();
            return response()->json([
                'success' => true,
                'orders' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    // public function testAmazon()
    // {
    //     //   connector (sandbox)
    //     $connector = SellingPartnerApi::seller(
    //         clientId: config('amazon.client_id'),
    //         clientSecret: config('amazon.client_secret'),
    //         refreshToken: config('amazon.refresh_token'),
    //         endpoint: Endpoint::NA_SANDBOX
    //     );
    //     //   payload (abhi static, baad me DB se aayega)
    //     $payload = [
    //         'productType' => 'PRODUCT',
    //         'attributes' => [
    //             'item_name' => 'Test Product',
    //             'brand' => 'Test Brand',
    //             'price' => 1000
    //         ]
    //     ];
    //     try {
    //         //   request object
    //         $request = new PutListingItemRequest(
    //             sellerId: config('amazon.seller_id'),
    //             sku: 'TEST-SKU-1',
    //             marketplaceIds: ['ATVPDKIKX0DER'],
    //             payload: $payload
    //         );
    //         //   send request
    //         $response = $connector->send($request);
    //         return response()->json([
    //             'status' => 'success ✅',
    //             'amazon_response' => $response->json(),
    //             'sent_payload' => $payload
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'sandbox_mode 😎',
    //             'error' => $e->getMessage(),
    //             'sent_payload' => $payload
    //         ]);
    //     }
    // }
    // public function handleAppUninstalledWebhook(Request $request)
    // {
    //     LOG::info('POST WEBHOOK HIT');
    //     LOG::info('UNINSTALL WORKING FINAL TEST');
    //     return response('OK', 200);
    // }
    public function handleAppUninstalledWebhook(Request $request)
    {
        LOG::info('UNINSTALL WEBHOOK HIT', [
            'shop' => $request->header('X-Shopify-Shop-Domain')
        ]);
        $payload = $request->getContent();
        $shopDomain = strtolower(trim((string) $request->header('X-Shopify-Shop-Domain', '')));
        if (!$shopDomain) {
            return response('No shop domain', 400);
        }
        if (!$this->shopifyWebhook->isValidWebhook($payload, $request->header('X-Shopify-Hmac-Sha256'))) {
            LOG::warning('Invalid uninstall webhook', ['shop' => $shopDomain]);
            return response('Invalid webhook', 401);
        }
        try {
            $shop = \App\Models\Shop::where('shop', $shopDomain)->first();
            if (!$shop) {
                return response('Shop not found', 404);
            }
            $template = \App\Models\MailTemplate::active()
                ->where('slug', 'app-uninstalled')
                ->first();
            if ($template) {
                dispatch(function () use ($template, $shop) {
                    app(\App\Services\EmailService::class)
                        ->sendDynamicEmail($template, (object)[
                            'name' => $shop->shop,
                            'first_name' => explode('.', $shop->shop)[0],
                            'email' => $shop->email
                        ]);
                });
            }
            $shop->update([
                'is_active' => 0,
                'access_token' => '',
            ]);
            LOG::info('App uninstalled handled', ['shop' => $shopDomain]);
            return response('OK', 200);
        } catch (\Exception $e) {
            LOG::error('UNINSTALL ERROR', [
                'error' => $e->getMessage()
            ]);
            return response('Error', 500);
        }
    }
    // testing static amazon listing 
    public function getAmazonSchema(Request $request)
    {
        $shop = $request->query('shop');
        $shopModel = \App\Models\Shop::where('shop', $shop)->first();
        if (!$shopModel) {
            return response()->json(['error' => 'Shop not found'], 404);
        }
        $amazonService = new \App\Services\AmazonService();
        try {
            //   DB credentials
            $creds = $amazonService->getDbCredentials($shopModel);
            //   Connector
            $definitions = SellingPartnerApi::seller(
                clientId: $creds['client_id'],
                clientSecret: $creds['client_secret'],
                refreshToken: $creds['refresh_token'],
                endpoint: Endpoint::NA
            )->productTypeDefinitionsV20200901();
            //   Fetch schema
            $response = $definitions->getDefinitionsProductType(
                'KEYBOADRS', //   change category here
                ['ATVPDKIKX0DER']
            );
            return response()->json([
                'success' => true,
                'schema' => $response->json()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function searchAmazonSchema(Request $request, $keyword)
    {
        try {
            $shop      = $request->query('shop');
            $shopModel = \App\Models\Shop::where('shop', $shop)->first();
            if (!$shopModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found'
                ], 404);
            }
            $amazonService = new \App\Services\AmazonService();
            $creds         = $amazonService->getDbCredentials($shopModel);
            $definitions = SellingPartnerApi::seller(
                clientId: $creds['client_id'],
                clientSecret: $creds['client_secret'],
                refreshToken: $creds['refresh_token'],
                endpoint: Endpoint::NA
            )->productTypeDefinitionsV20200901();
            // ── Step 1: Search for matching product type ──────────────────────
            $searchResponse = $definitions->searchDefinitionsProductTypes(
                marketplaceIds: ['ATVPDKIKX0DER'],
                keywords: [$keyword]
            );
            $productTypes = $searchResponse->dto()->productTypes ?? [];
            if (empty($productTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching category found for keyword: ' . $keyword
                ], 404);
            }
            // Collect all matched type names
            $matchedTypes = collect($productTypes)
                ->pluck('name')
                ->filter()
                ->values()
                ->toArray();
            $matchedType = $matchedTypes[0]; // primary match
            // ── Step 2: Fetch full schema for primary match ───────────────────
            $schemaResponse = $definitions->getDefinitionsProductType(
                $matchedType,
                ['ATVPDKIKX0DER']
            );
            $schemaDto = $schemaResponse->dto();
            $schemaUrl = $schemaDto->schema->link->resource ?? null;
            if (!$schemaUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schema URL not found in response'
                ], 500);
            }
            // ── Step 3: Download and parse schema JSON ────────────────────────
            $schemaJson = Http::timeout(20)->get($schemaUrl)->json();
            if (!$schemaJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch schema from Amazon'
                ], 500);
            }
            $properties = $schemaJson['properties'] ?? [];
            // ── Step 4: Extract clean, readable attribute list ────────────────
            $attributes = collect($properties)->map(function ($prop, $name) {
                return [
                    'attribute'   => $name,
                    'type'        => $prop['type'] ?? ($prop['items']['type'] ?? 'object'),
                    'required'    => isset($prop['minItems']) && $prop['minItems'] > 0,
                    'enum_values' => $prop['items']['properties']['value']['enum']
                        ?? $prop['properties']['value']['enum']
                        ?? null,
                    'description' => $prop['description'] ?? null,
                ];
            })->values()->toArray();
            return response()->json([
                'success'              => true,
                'searched_keyword'     => $keyword,
                'matched_product_type' => $matchedType,
                'all_matched_types'    => $matchedTypes,
                'total_attributes'     => count($attributes),
                'attributes'           => $attributes,
                // Full raw schema if you need to inspect everything
                'raw_schema'           => $schemaJson,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }
    // ytest function 
    public function getUnitCountSchema(Request $request)
    {
        // Schema URL jo upar mili thi
        $schemaUrl = "https://selling-partner-definitions-prod-iad.s3.amazonaws.com/schema/HEADPHONES.json/jXX6LRGtwa9PpAYd2AQCyQ%253D%253D?X-Amz-Security-Token=IQoJb3JpZ2luX2VjEKz%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCXVzLWVhc3QtMSJGMEQCIBRvb8CochetD7WR2Semgxp%2BUKc487piUDTInjlRqPkLAiBLAi4DtE%2FzV0WWsHwz2LYRN4%2BEHgc%2FOvRfwrrqPA1F3yrJBAh1EAMaDDUzNTc4OTgyMzgwOCIMxWQ6qA3PSR31lrVyKqYESqXfFZm40qe6QciT%2BtalSH%2FyNG2xic0oZi4Q1ECxUz2t8fXl%2FXMh%2BZnxcHCqnkgXvuRfeELhOQlr5vBRaDWjSQDujPXXtM0kmJbz48Ywmue5PgrxMH2E%2BLCtgOnC5%2FwNuOROoWS0RU%2BSi5u0sPj%2B75DcA3ER84%2FQMgMkBf1ijaLWZJajW0OzDVI53JEruXjX7CZuXMn2aSb9Nfsh8liRWzclSop5zAn96Zk9ODjR1kM0qgE9Gjpf4r7XfGiLHEvbrCe%2Bdk2TahkW1zky%2BraPDFw7UdR7mU7MEmrxnEpzuRwKYZzLC67o%2FLcS02YkR1sbuA%2FoZNEWoYSVk1w6urrGDtQ4cyk0xcZLvqtcSjXMBWf9vKk8eHD4rz6tb4a4NFzyURV2bgUwxaMDMQhcXbWp%2FFLpmC%2FEIfkeSk3I8%2FI6zGmgWwxc7lo780dD%2FGL6zdoq2et8QzNZpt4a6R3pJu%2FnHj4prztBE0M3O32BWnVOFzMIzr903NV%2BdqDe1%2F06PAGhnYVVtnCVrD553SV5AXTOOWNFR5c8lnLfBGYiXk5XiKdW5O%2FiCDFd3IfSZw9duUP46F0csHj8kA5vv3fStqc1Urrmrd8jUJNGgnJDrn31fxk1TR8Rp52FbEbS1Z2vecLaHd6256B3jNOHMMXRftBojXGB64U5MK8YidYRoI1uYtTOs9JXqmcNhNEg1oDs%2Fj3%2Bk5lQhF208zfmjA53aM9mP0SZ3Pz%2BljDlyKvPBjqoAdTJnbJ%2FYK97fusffOYlCp%2FwFf%2FVZNEx1C09LMunTGV8yOQZN05SzMkE0B5HxVyJl%2F%2BK5zU2ThNsB%2BtYJ7caLdQfG%2Bz%2FBlHmG7DePHPazECJysb3QPyRIyqp%2FeXYdhvN8CdVqI8Xioo7SezxZoVmUjUkP7cpVtBQ8SSPxQjRnfnl3xI%2Bbin1D9FMARhrfsx1yT1FWkEM2LkoeqKf6e4ifxoNhzKsMtmulA%3D%3D&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20260424T052339Z&X-Amz-SignedHeaders=host&X-Amz-Credential=ASIAXZP4P45AOD3WNEMG%2F20260424%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Expires=604800&X-Amz-Signature=80d1d96f0062ef09ac0a2c4a72d52503ff32330596a92c88f0a3a5aa9be0e6d5"; // poori URL paste karo
        $schema = file_get_contents($schemaUrl);
        $schema = json_decode($schema, true);
        // unit_count dhundo
        $unitCount = $schema['properties']['unit_count'] ?? 'NOT FOUND';
        return response()->json(['unit_count' => $unitCount]);
    }
    public function testSchemaBasedStatic(Request $request)
    {
        $shop = $request->query('shop');
        $shopModel = \App\Models\Shop::where('shop', $shop)->first();
        if (!$shopModel) {
            return response()->json(['error' => 'Shop not found'], 404);
        }
        $amazonService = new \App\Services\AmazonService();
        $attributes = [
            "item_name" => [["value" => "Wireless Bluetooth Earbuds", "marketplace_id" => "ATVPDKIKX0DER"]],
            "brand" => [["value" => "MyBrand", "marketplace_id" => "ATVPDKIKX0DER"]],
            "manufacturer" => [["value" => "MyBrand", "marketplace_id" => "ATVPDKIKX0DER"]],
            "model_name" => [["value" => "TWS-100", "marketplace_id" => "ATVPDKIKX0DER"]],
            "model_number" => [["value" => "TWS-100", "marketplace_id" => "ATVPDKIKX0DER"]],
            "item_type_keyword" => [["value" => "earbuds", "marketplace_id" => "ATVPDKIKX0DER"]],
            "product_description" => [["value" => "High quality wireless earbuds with Bluetooth 5.0", "marketplace_id" => "ATVPDKIKX0DER"]],
            "bullet_point" => [
                ["value" => "Bluetooth 5.0 for stable wireless connection", "marketplace_id" => "ATVPDKIKX0DER"],
                ["value" => "Active Noise Cancellation", "marketplace_id" => "ATVPDKIKX0DER"]
            ],
            "color" => [["value" => "black", "marketplace_id" => "ATVPDKIKX0DER"]],
            "connectivity_technology" => [["value" => "wireless", "marketplace_id" => "ATVPDKIKX0DER"]],
            "headphones_form_factor" => [["value" => "in_ear", "marketplace_id" => "ATVPDKIKX0DER"]],
            "headphones_ear_placement" => [["value" => "in_ear", "marketplace_id" => "ATVPDKIKX0DER"]],
            "included_components" => [
                ["value" => "earbuds", "marketplace_id" => "ATVPDKIKX0DER"],
                ["value" => "charging case", "marketplace_id" => "ATVPDKIKX0DER"]
            ],
            "number_of_items" => [["value" => 1, "marketplace_id" => "ATVPDKIKX0DER"]],
            "main_product_image_locator" => [
                ["media_location" => "https://m.media-amazon.com/images/I/61CGHv6kmWL._SL1500_.jpg", "marketplace_id" => "ATVPDKIKX0DER"]
            ],
            "country_of_origin" => [["value" => "CN", "marketplace_id" => "ATVPDKIKX0DER"]],
            "batteries_required" => [["value" => false, "marketplace_id" => "ATVPDKIKX0DER"]],
            "batteries_included" => [["value" => false, "marketplace_id" => "ATVPDKIKX0DER"]],
            "externally_assigned_product_identifier" => [
                ["type" => "ean", "value" => "8901234567890", "marketplace_id" => "ATVPDKIKX0DER"]
            ],
            "unit_count" => [
                [
                    "value" => 1, // or 20 if actual pack
                    "type" => [
                        "value" => "Count",
                        "language_tag" => "en_US"
                    ],
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]
            ],
            // ✅ Required missing fields add kiye
            "item_package_weight" => [
                ["value" => 0.5, "unit" => "kilograms", "marketplace_id" => "ATVPDKIKX0DER"]
            ],
            "item_package_dimensions" => [
                [
                    "length" => ["value" => 10, "unit" => "centimeters"],
                    "width"  => ["value" => 5, "unit" => "centimeters"],
                    "height" => ["value" => 3, "unit" => "centimeters"],
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]
            ],
            "supplier_declared_dg_hz_regulation" => [["value" => "not_applicable", "marketplace_id" => "ATVPDKIKX0DER"]],
            "warranty_description" => [["value" => "1 year warranty", "marketplace_id" => "ATVPDKIKX0DER"]],
            "condition_type" => [["value" => "new_new", "marketplace_id" => "ATVPDKIKX0DER"]],
            "list_price" => [["value" => 49.99, "currency" => "USD", "marketplace_id" => "ATVPDKIKX0DER"]],
            "fulfillment_availability" => [
                ["fulfillment_channel_code" => "DEFAULT", "quantity" => 10]
            ],
        ];
        // ✅ Correct order: productType, attributes, requirements
        $payload = new \SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest(
            productType: 'HEADPHONES',
            attributes: $attributes,
            requirements: 'LISTING',
        );
        $sku = 'earbuds-static-' . time();
        try {
            $connector = $amazonService->getDbConnectorFromCredentials($shopModel);
            // ✅ Correct order: sellerId, sku, listingsItemPutRequest, marketplaceIds
            $response = $connector->putListingsItem(
                $shopModel->amazon_seller_id,
                $sku,
                $payload,
                ['ATVPDKIKX0DER'],
            );
            return response()->json([
                'sku'      => $sku,
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ], 500);
        }
    }
    private function UploadImageProvideUrl($request)
    {
        $paths = [];
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            foreach ($images as $image) {
                if ($image->isValid()) {
                    $path = $image->store('uploads', 'public');
                    $paths[] = asset('storage/' . $path);
                }
            }
        }
        return $paths;
    }

    public function syncShopifyToAmazon(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect('/products')->with('error', 'No shop connected.');
        }

        try {
            $response = $this->shopifyRest($shopModel, 'get', "products/{$id}.json");

            if (!empty($response['error'])) {
                return back()->with('error', 'Product not found');
            }
            $product = $response['product'] ?? null;
            if (!$product) {
                return back()->with('error', 'Product not found');
            }

            $product_type = $product['product_type'] ?? '';

            $dbProduct = \App\Models\Product::where('shopify_id', $id)
                ->where('shop_id', $shopModel->id)->first();
            $amazonData = null;

            $inventoryItemIds = [];
            foreach ($product['variants'] as $variant) {
                if (!empty($variant['inventory_item_id'])) {
                    $inventoryItemIds[] = $variant['inventory_item_id'];
                }
            }

            if (!empty($inventoryItemIds)) {
                $inventoryRes = $this->shopifyRest(
                    $shopModel,
                    'get',
                    'inventory_levels.json',
                    [
                        'inventory_item_ids' => implode(',', $inventoryItemIds)
                    ]
                );
                if (empty($inventoryRes['error'])) {
                    $inventoryMap = [];
                    foreach ($inventoryRes['inventory_levels'] ?? [] as $item) {
                        if (!isset($inventoryMap[$item['inventory_item_id']])) {
                            $inventoryMap[$item['inventory_item_id']] = 0;
                        }
                        $inventoryMap[$item['inventory_item_id']] += $item['available'];
                    }
                    foreach ($product['variants'] as &$variant) {
                        $variant['inventory_quantity'] = $inventoryMap[$variant['inventory_item_id']] ?? 0;
                    }
                }
            }

            $mapper = new ShopifyAmazonMapper();
            $mappedproduct = $mapper->map($product);
            $mappedproduct['shopify_inventory_item_id'] = $product['variants'][0]['inventory_item_id'] ?? '';
            if (isset($dbProduct) && ($dbProduct->sub_category_id != null)) {
                $category = Category::where('id', $dbProduct->sub_category_id)->first();

                if ($category) {
                    $pschema =   ProductSchema::where('product_type', $category->category)->first();
                    $producttype = $category->category ?? '';
                    if ($pschema) {
                        $schema_id = $pschema->id;
                    } else {
                        return redirect()->route('shopify.products', ['shop' => session('active_shop')])->with('error', 'please connect to admin for this category sync or map product from inventory');
                    }
                } else {
                    return redirect()->route('shopify.product.edit', ['id' => $id, 'shop' => session('active_shop')])->with('success', 'please update category first to sync product');
                }
            } else {
                return redirect()->route('shopify.product.edit', ['id' => $id, 'shop' => session('active_shop')])->with('success', 'please update category first to sync product');
            }

            $updatesync = new ProductSchemaController();
            $mapped_id = $updatesync->syncProductShopify($mappedproduct, $shopModel->id, $dbProduct->id, $producttype);
            unset($mappedproduct['shopify_product_id']);
            unset($mappedproduct['shopify_inventory_item_id']);
            unset($mappedproduct['shopify_variant_id']);
            unset($mappedproduct['sku']);
            unset($mappedproduct['other_product_image_locator']);
            unset($mappedproduct['shopify_handle']);
            unset($mappedproduct['item_display_weight']);
            unset($mappedproduct['shopify_status']);
            unset($mappedproduct['price']);
            unset($mappedproduct['quantity']);

            $product_id =  $updatesync->productstoreAmazon($mappedproduct, $schema_id);

            $dbProduct = \App\Models\Product::where('shopify_id', $id)
                ->where('shop_id', $shopModel->id)
                ->update(['amazon_product_id' => $product_id]);


            return redirect()->route(
                'admin.product.productEdit',
                [
                    'product' => $product_id,
                    'shop' => $shopModel->shop,
                ]
            )->with('success', 'Product all information to update');
        } catch (\Exception $e) {

            return back()->with('error', $e->getMessage());
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

    public function searchCategories(Request $request)
    {
        $search = trim($request->query('search', ''));
        $parentId = $request->query('parent_id');

        if ($search === '') {
            return response()->json([]);
        }

        $query = Category::query()
            ->where('name', 'like', '%' . $search . '%');

        if ($parentId !== null) {
            $query->where('parent_id', (int) $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return response()->json(
            $query->orderBy('name')
                ->limit(20)
                ->get(['id', 'name'])
        );
    }
}
