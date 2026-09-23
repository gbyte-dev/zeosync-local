<?php

namespace App\Http\Controllers;

use App\Amazon\Requests\PutListingItemRequest;
use App\Http\Controllers\ProductSchemaController;
use App\Models\AdminSetting;
use App\Models\AmazonProduct;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\ProductSchema;
use App\Models\ProductSyncLog;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\ShopSubscription;
use App\Services\Amazon\ShopifyAmazonMapper;
use App\Services\AmazonService;
use App\Services\NotificationService;
use App\Services\ShopifyBillingService;
use App\Services\ShopifyOrderSyncService;
use App\Services\ShopifyService;
use App\Services\ShopifyWebhookService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use SellingPartnerApi\Seller\OrdersV0\Requests\GetOrdersRequest;
use SellingPartnerApi\Seller\SellerConnector;
use SellingPartnerApi\SellingPartnerApi;
use RuntimeException;

class ShopifyController extends Controller
{
    protected ShopifyBillingService $shopifyBilling;
    protected ShopifyWebhookService $shopifyWebhook;
    protected AmazonService $amazonService;
    protected ShopifyOrderSyncService $orderSyncService;

    public function __construct()
    {
        $this->shopifyBilling = app(ShopifyBillingService::class);
        $this->shopifyWebhook = app(ShopifyWebhookService::class);
        $this->amazonService = app(AmazonService::class);
        $this->orderSyncService = app(ShopifyOrderSyncService::class);
    }

    public function entry(Request $request)
    {
        // 1. Authenticated Shopify Launch:
        // Only proceed to dashboard if the CURRENT request has been cryptographically verified
        // by VerifyShopifyAuthentication middleware (via launch HMAC, query session token, or header token).
        $verifiedShop = $request->attributes->get('shopify_verified_shop');

        if ($verifiedShop) {
            $shop = $verifiedShop;
            $shopModel = $request->attributes->get('shopify_verified_model')
                ?? \App\Models\Shop::where('shop', $shop)->where('is_active', 1)->first();

            if ($shopModel && $shopModel->is_active == 1 && !empty($shopModel->access_token)) {
                if (!$this->isShopActive($shopModel)) {
                    $shopModel->update([
                        'is_active' => 0,
                    ]);

                    session()->forget(['active_shop', 'active_shop_id', '_shopify_verified_shop', '_shopify_verified_at']);
                    return redirect()->route('shopify.install', [
                        'shop' => $shopModel->shop,
                    ]);
                }

                session([
                    'active_shop' => $shop,
                    'active_shop_id' => $shopModel->id,
                    '_shopify_verified_shop' => $shop,
                    '_shopify_verified_at' => session('_shopify_verified_at', time()),
                ]);

                if ($request->filled('charge_id')) {
                    $chargeId = $request->query('charge_id');
                    $subscriptionGid = 'gid://shopify/AppSubscription/' . $chargeId;

                    $subscription = ShopSubscription::with('plan')
                        ->where('shop_id', $shopModel->id)
                        ->first();

                    try {
                        $subscription = $this->shopifyBilling->syncSubscriptionByGid(
                            $shopModel,
                            $subscriptionGid,
                            $subscription
                        );
                    } catch (\Throwable $e) {
                        Log::error('MANAGED PRICING SYNC FAILED', [
                            'shop_id' => $shopModel->id,
                            'charge_id' => $chargeId,
                            'subscription_gid' => $subscriptionGid,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    if (
                        $subscription &&
                        $this->shopifyBilling->isActivatedStatus($subscription->status)
                    ) {
                        return redirect($this->shopAwareUrl('/planview', $shopModel->shop))
                            ->with('success', ($subscription->plan?->name ?? 'Selected') . ' plan is now active.');
                    }
                }

                $redirectParams = $request->query();
                unset(
                    $redirectParams['id_token'],
                    $redirectParams['token'],
                    $redirectParams['session_token'],
                    $redirectParams['session'],
                    $redirectParams['shopify_token']
                );
                $redirectParams['shop'] = $shop;
                if ($request->filled('host')) {
                    $redirectParams['host'] = $request->query('host');
                }
                if ($request->filled('embedded')) {
                    $redirectParams['embedded'] = $request->query('embedded');
                }

                return redirect()->route('dashboard', $redirectParams);
            }

            return redirect()->route('shopify.install', ['shop' => $shop]);
        }

        // 2. Public website / landing page:
        // All unauthenticated visits to / (with or without existing session cookies or ?shop parameter)
        // land on the public ZeoSync website without automatic redirection.
        return view('welcomemain');
    }

    public function appLaunch(Request $request, ?string $token = null)
    {
        $verifiedShop = $request->attributes->get('shopify_verified_shop')
            ?? (session()->has('_shopify_verified_shop') ? session('_shopify_verified_shop') : null);

        if (!$verifiedShop && !empty($token)) {
            $cryptResult = app(\App\Http\Middleware\VerifyShopifyAuthentication::class)->verifyCryptToken($token);
            if ($cryptResult) {
                $verifiedShop = $cryptResult['shop'];
                $request->attributes->set('shopify_verified_shop', $cryptResult['shop']);
                $request->attributes->set('shopify_verified_model', $cryptResult['shop_model']);
                $request->attributes->set('shopify_auth_source', 'bearer_token');

                session([
                    '_shopify_verified_shop' => $cryptResult['shop'],
                    '_shopify_verified_at' => time(),
                    'active_shop' => $cryptResult['shop'],
                    'active_shop_id' => $cryptResult['shop_model']->id,
                ]);
            }
        }

        if (!$verifiedShop) {
            $shopParam = $request->query('shop');
            if (!$shopParam && $request->has('host')) {
                $decoded = base64_decode($request->get('host'));
                if (preg_match('/store\/([a-z0-9\-]+)/', $decoded, $matches)) {
                    $shopParam = $matches[1] . '.myshopify.com';
                }
            }

            if ($shopParam) {
                return redirect()->route('shopify.install', ['shop' => $shopParam]);
            }

            return redirect()->route('crm.entry');
        }

        $shopModel = $request->attributes->get('shopify_verified_model')
            ?? Shop::where('shop', $verifiedShop)->where('is_active', 1)->first();

        if (!$shopModel || empty($shopModel->access_token)) {
            return redirect()->route('shopify.install', ['shop' => $verifiedShop]);
        }

        if (!$this->isShopActive($shopModel)) {
            $shopModel->update(['is_active' => 0]);
            session()->forget(['active_shop', 'active_shop_id', '_shopify_verified_shop', '_shopify_verified_at']);
            return redirect()->route('shopify.install', ['shop' => $shopModel->shop]);
        }

        session([
            'active_shop' => $verifiedShop,
            'active_shop_id' => $shopModel->id,
            '_shopify_verified_shop' => $verifiedShop,
            '_shopify_verified_at' => session('_shopify_verified_at', time()),
        ]);

        $redirectParams = $request->query();
        unset(
            $redirectParams['id_token'],
            $redirectParams['token'],
            $redirectParams['session_token'],
            $redirectParams['session'],
            $redirectParams['shopify_token']
        );
        $redirectParams['shop'] = $verifiedShop;
        if ($request->filled('host')) {
            $redirectParams['host'] = $request->query('host');
        }
        if ($request->filled('embedded')) {
            $redirectParams['embedded'] = $request->query('embedded');
        }

        return redirect()->route('dashboard', $redirectParams);
    }

    public function appLaunchStore(Request $request, string $shop_handle, ?string $token = null)
    {
        return $this->appLaunch($request, $token);
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

    protected function isValidShopifyHmac(array $query, string $apiSecret): bool
    {
        $providedHmac = $query['hmac'] ?? null;
        if (!is_string($providedHmac) || $providedHmac === '') {
            return false;
        }

        $canonicalQuery = $query;
        unset($canonicalQuery['hmac'], $canonicalQuery['signature']);
        $canonicalized = $this->normalizeShopifyQueryParams($canonicalQuery);

        $candidates = [
            http_build_query($canonicalized, '', '&', PHP_QUERY_RFC3986),
            urldecode(http_build_query($canonicalized, '', '&', PHP_QUERY_RFC3986)),
            urldecode(http_build_query($canonicalized)),
        ];

        foreach (array_unique(array_filter($candidates, static fn($candidate) => $candidate !== '')) as $candidate) {
            if (hash_equals($providedHmac, hash_hmac('sha256', $candidate, $apiSecret))) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeShopifyQueryParams(array $query): array
    {
        ksort($query);

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                ksort($value);
                $query[$key] = $this->normalizeShopifyQueryParams($value);
            }
        }

        return $query;
    }

    public function install(Request $request)
    {
        $shop = $request->query('shop');
        //   fallback from host (IMPORTANT)
        if (!$shop && $request->has('host')) {
            $decoded = base64_decode($request->get('host'));
            if (preg_match('/store\/([a-z0-9\-]+)/', $decoded, $matches)) {
                $shop = $matches[1] . '.myshopify.com';
            }
        }

        if (!$shop) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'Missing shop parameter'], 400);
            }
            return redirect()->route('crm.entry')->with('error', 'Shopify store information is missing. Please provide your store domain.');
        }

        if (!str_contains($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        // ✅ strict validation
        if (!preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop)) {
            return response('Invalid shop domain', 400);
        }
        $state = base64_encode(json_encode([
            'shop' => $shop,
            'time' => time()
        ]));

        // ⚡ build query safely
        $shopifyApiKey = AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key'));
        $shopifyRedirectUri = AdminSetting::get('SHOPIFY_REDIRECT_URI', config('services.shopify.redirect_uri'));
        $query = http_build_query([
            'client_id' => $shopifyApiKey,
            'scope' => $this->oauthScopes(),
            'redirect_uri' => $shopifyRedirectUri,
            'state' => $state,
        ]);
        $redirectUrl = "https://{$shop}/admin/oauth/authorize?{$query}";

        //   IMPORTANT (iframe fix)
        $redirectUrl = "https://{$shop}/admin/oauth/authorize?{$query}";

        return response()->view('shopify.auth-popup', [
            'redirectUrl' => $redirectUrl,
            'shop' => $shop,
        ]);
    }

    public function callback(Request $request)
    {
        // =========================
        // STEP 1: HMAC VALIDATION (FIRST)
        // =========================
        $query = $request->query();
        $apiSecret = \App\Models\AdminSetting::get(
            'SHOPIFY_API_SECRET',
            config('services.shopify.api_secret')
        );

        if (!is_string($apiSecret) || $apiSecret === '') {
            Log::error('HMAC FAILED: missing Shopify API secret.');
            abort(403, 'Invalid HMAC');
        }

        if (!$this->isValidShopifyHmac($query, $apiSecret)) {
            Log::error('HMAC FAILED', ['shop' => $query['shop'] ?? null]);
            abort(403, 'Invalid HMAC');
        }
        // =========================
        // STEP 2: STATE DECODE (NO CACHE)
        // =========================
        $state = $request->state;
        $code = $request->code;
        $decodedState = json_decode(base64_decode($state), true);
        if (!$decodedState || !isset($decodedState['shop'])) {
            Log::error('STATE DECODE FAILED');
            abort(403, 'Invalid state');
        }
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
            return redirect('/')->with('error', 'Shopify connection failed.');
        }
        $data = $response->json();
        if (!isset($data['access_token'])) {
            Log::error('NO ACCESS TOKEN', $data);
            return redirect('/')->with('error', 'Shopify connection failed.');
        }
        $accessToken = $data['access_token'];
        Log::info('TOKEN RECEIVED');
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;  // access token, ~60 min
        $refreshExpiresIn = $data['refresh_token_expires_in'] ?? (90 * 86400);  // refresh token, ~90 days

        $existingShop = \App\Models\Shop::withTrashed()->where('shop', $shop)->first();
        $isReinstall = false;
        if ($existingShop) {
            if ($existingShop->trashed()) {
                $existingShop->restore();
            }
            if ((int) $existingShop->is_active !== 1 || empty($existingShop->access_token)) {
                $isReinstall = true;
            }
        }

        $shopData = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'access_token_expires_at' => now()->addSeconds($expiresIn),
            'refresh_token_expires_at' => now()->addSeconds($refreshExpiresIn),
            'installed_at' => now(),
            'hmac' => $request->hmac,
            'is_active' => 1,
            'store_status' => 'active',
            'shopify_connection_status' => 'connected',
        ];

        // If newly installing or reinstalling after deactivation/uninstallation, clear stale activation details
        if (!$existingShop || $isReinstall) {
            $shopData['shop_name'] = null;
            $shopData['email'] = null;
        }

        $shopModel = \App\Models\Shop::updateOrCreate(
            ['shop' => $shop],
            $shopData
        );

        Log::info('SHOPIFY_OAUTH_CALLBACK: Shop authenticated', [
            'shop' => $shop,
            'shop_id' => $shopModel->id,
            'is_reinstall' => $isReinstall,
            'activation_required' => empty($shopModel->shop_name) || empty($shopModel->email),
        ]);

        // Fetch and store all Shopify locations via GraphQL
        try {
            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $locationResponse = $shopifyService->getLocations($shopModel);

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
                    'selected_location_index' => !empty($locations) ? 0 : null,
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

        // verified session
        session([
            'active_shop' => $shop,
            'active_shop_id' => $shopModel->id,
            '_shopify_verified_shop' => $shop,
        ]);
        // =========================
        // STEP 6: WEBHOOK
        // =========================
        try {
            $this->shopifyWebhook->ensureOrdersCreateWebhook($shopModel);
            $this->shopifyWebhook->ensureOrdersUpdateWebhook($shopModel);
            $this->shopifyWebhook->ensureAppUninstalledWebhook($shopModel);
            $this->shopifyWebhook->ensureProductsDeleteWebhook($shopModel);
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

        $setupUrl = route('setup.form', ['shop' => $shop, 'popup' => 1]);
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
        if (!$shopModel || (int) $shopModel->is_active !== 1 || empty($shopModel->access_token)) {
            return response()->json([
                'shop_name' => null,
                'email' => null,
                'is_active' => false,
            ], 200);
        }
        return response()->json([
            'shop_name' => $shopModel->shop_name,
            'email' => $shopModel->email,
            'is_active' => true,
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
                $query
                    ->where('is_custom', 0)
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

        if (!$shopModel) {
            return redirect('/')
                ->with('error', 'No shop connected.');
        }

        try {
            $this->ensureFreshAccessToken($shopModel);

            $subscription = ShopSubscription::with('plan')
                ->where('shop_id', $shopModel->id)
                ->first();

            $chargeId = $request->query('charge_id');

            if (!$chargeId) {
                return redirect(
                    $this->shopAwareUrl('/plans', $shopModel->shop)
                )->with(
                    'error',
                    'Shopify billing charge ID is missing.'
                );
            }

            $subscriptionGid =
                'gid://shopify/AppSubscription/' . $chargeId;

            Log::info('SHOPIFY BILLING CALLBACK', [
                'shop_id' => $shopModel->id,
                'shop' => $shopModel->shop,
                'charge_id' => $chargeId,
                'subscription_gid' => $subscriptionGid,
            ]);

            $subscription = $this->shopifyBilling->syncSubscriptionByGid(
                $shopModel,
                $subscriptionGid,
                $subscription
            );

            $subscription?->loadMissing('plan');
        } catch (\Throwable $exception) {
            Log::error('FAILED TO CONFIRM SHOPIFY BILLING CALLBACK', [
                'shop_id' => $shopModel->id,
                'shop' => $shopModel->shop,
                'charge_id' => $request->query('charge_id'),
                'subscription_gid' => $subscriptionGid ?? null,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return redirect(
                $this->shopAwareUrl('/plans', $shopModel->shop)
            )->with(
                'error',
                'Shopify billing confirmation failed: '
                    . $exception->getMessage()
            );
        }

        if (
            !$subscription ||
            !$this->shopifyBilling->isActivatedStatus(
                $subscription->status
            )
        ) {
            return redirect(
                $this->shopAwareUrl('/plans', $shopModel->shop)
            )->with(
                'error',
                'Shopify did not activate the subscription. Please approve the charge to continue.'
            );
        }

        return redirect(
            $this->shopAwareUrl('/plans', $shopModel->shop)
        )->with(
            'success',
            ($subscription->plan?->name ?? 'Selected')
                . ' plan is now active for '
                . $shopModel->shop
                . '.'
        );
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
        $source = $request->get('source', 'shopify');  //   IMPORTANT
        $refresh = $request->get('refresh');
        $search = $request->input('search');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 10);
        $amazonConnected = !empty($shopModel->amazon_refresh_token);
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

            $amazonConnected = !empty($shopModel->amazon_refresh_token);

            if (!$amazonConnected) {
                return view('orders', [
                    'source' => 'amazon',
                    'orders' => [],
                    'activeShop' => $activeShop,
                    'totalOrders' => 0,
                    'paidOrders' => 0,
                    'pendingOrders' => 0,
                    'cancelledOrders' => 0,
                    'search' => $search,
                    'status' => $status,
                    'amazonConnected' => $amazonConnected,
                ]);
            }

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
                'totalOrders' => count($amazonOrders),
                'paidOrders' => 0,
                'pendingOrders' => 0,
                'cancelledOrders' => 0,
                'search' => $search,
                'status' => $status,
                'amazonConnected' => $amazonConnected,
            ]);
        }
        // =========================
        //   SHOPIFY FLOW (DEFAULT)
        // =========================
        $query = ShopifyOrder::query()->where('shop_id', $shopModel->id);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q
                    ->where('name', 'LIKE', "%{$search}%")
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
            'status' => $status,
            'amazonConnected' => $amazonConnected,
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
        $order = $this->refreshOrderFromShopify($shopModel, $order);
        // dd($order->toArray());
        return view('order-details', [
            'order' => $order,
            'source' => $source,
            'activeShop' => $shopModel->shop,
        ]);
    }

    /**
     * Pulls the current order state from Shopify's Admin API and syncs it into
     * the local row before display. Falls back to the cached row on failure
     * so a Shopify API hiccup never breaks the page — it just shows slightly
     * stale data with a logged warning.
     */
    protected function refreshOrderFromShopify(Shop $shopModel, ShopifyOrder $order): ShopifyOrder
    {
        try {
            $shopifyService = app(ShopifyService::class, [
                'shop' => $shopModel->shop,
                'token' => $shopModel->access_token
            ]);
            $data = $shopifyService->getOrder($order->shopify_order_id);
        } catch (\Throwable $e) {
            Log::warning('Failed to refresh order from Shopify; showing cached data.', [
                'shop_id' => $shopModel->id,
                'shopify_order_id' => $order->shopify_order_id,
                'error' => $e->getMessage(),
            ]);

            return $order;
        }

        if (!is_array($data) || empty($data['id'])) {
            return $order;
        }

        $customer = data_get($data, 'customer', []);
        $lineItems = data_get($data, 'line_items', []);

        $order->fill([
            'admin_graphql_api_id' => data_get($data, 'admin_graphql_api_id', $order->admin_graphql_api_id),
            'order_number' => data_get($data, 'order_number', $order->order_number),
            'name' => data_get($data, 'name', $order->name),
            'email' => data_get($data, 'email', $order->email),
            'customer_first_name' => data_get($customer, 'first_name', $order->customer_first_name),
            'customer_last_name' => data_get($customer, 'last_name', $order->customer_last_name),
            'customer_phone' => data_get($customer, 'phone', $order->customer_phone),
            'phone' => data_get($data, 'phone', $order->phone),
            'financial_status' => data_get($data, 'financial_status', $order->financial_status),
            'fulfillment_status' => data_get($data, 'fulfillment_status', $order->fulfillment_status),
            'currency' => data_get($data, 'currency', $order->currency),
            'subtotal_price' => (float) data_get($data, 'subtotal_price', $order->subtotal_price),
            'total_tax' => (float) data_get($data, 'total_tax', $order->total_tax),
            'total_discounts' => (float) data_get($data, 'total_discounts', $order->total_discounts),
            'total_price' => (float) data_get($data, 'total_price', $order->total_price),
            'line_items_count' => $lineItems ? count($lineItems) : $order->line_items_count,
            'source_name' => data_get($data, 'source_name', $order->source_name),
            'tags' => data_get($data, 'tags', $order->tags),
            'note' => data_get($data, 'note', $order->note),
            'customer' => $customer ?: $order->customer,
            'billing_address' => data_get($data, 'billing_address', $order->billing_address),
            'shipping_address' => data_get($data, 'shipping_address', $order->shipping_address),
            'line_items' => $lineItems ?: $order->line_items,
            'discount_codes' => data_get($data, 'discount_codes', $order->discount_codes),
            'shipping_lines' => data_get($data, 'shipping_lines', $order->shipping_lines),
            'tax_lines' => data_get($data, 'tax_lines', $order->tax_lines),
            'raw_payload' => $data,
            'order_created_at' => $this->parseNullableDate(data_get($data, 'created_at')) ?? $order->order_created_at,
            'processed_at' => $this->parseNullableDate(data_get($data, 'processed_at')) ?? $order->processed_at,
            'cancelled_at' => $this->parseNullableDate(data_get($data, 'cancelled_at')) ?? $order->cancelled_at,
        ]);

        if ($order->isDirty()) {
            $order->save();
        }

        return $order;
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
                    $q
                        ->where('id', $id)
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
            $images = is_array($product->images) ? $product->images : json_decode($product->images, true) ?? [];
            $mainImage = $images[0]['src'] ?? 'https://via.placeholder.com/500';
            Log::info('🟢 STEP 6 IMAGES', [
                'images_count' => count($images),
                'main_image' => $mainImage
            ]);
            $otherImages = [];
            foreach ($images as $index => $img) {
                if ($index == 0)
                    continue;
                $otherImages[] = $img['src'];
            }
            $variants = is_array($product->variants)
                ? $product->variants
                : json_decode($product->variants, true) ?? [];
            if (count($variants) > 1) {
                $amazonService = new \App\Services\AmazonService();
                $response = $amazonService->buildPayload($shopModel, $product, $amazon);
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
                $productType = getCategoryData($product->sub_category_id, 'slug');
            } else {
                $productType = $product->product_type ?? 'HEADPHONES';
            }
            $response = $amazonService->putListing(
                $shopModel,
                $sku,
                $attributes,
                $product
            );
            // Log::info('FINAL AMAZON ATTRIBUTES', $attributes);
            // Log::info('🚀 AMAZON REQUEST START', [
            //     'seller_id' => 'handled_by_service',
            //     'sku' => $sku,
            //     'product_id' => $product->id,
            //     'shop_id' => $shopModel->id,
            //     'payload_preview' => $attributes
            // ]);
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
            // Log::info('✅ AMAZON RESPONSE', [
            //     'http_status' => method_exists($response, 'status') ? $response->status() : null,
            //     'submission_status' => $status,
            //     'is_accepted' => $isAccepted,
            //     'sku' => $sku,
            //     'product_id' => $product->id,
            //     'shop_id' => $shopModel->id,
            //     'issues_count' => is_array($issues) ? count($issues) : 0,
            // ]);
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

            if ($isAccepted) {
                $productTitle = $product->title ?? $product->sku ?? 'Product';
                UserNotificationService::send(
                    $shopModel->id,
                    'inventory_stock_update',
                    'Product Synced to Amazon',
                    sprintf('"%s" has been synced to Amazon successfully.', $productTitle)
                );
            }

            // $this->refreshProductsCache($shopModel);
            return response()->json([
                'success' => $isAccepted,
                'message' => $isAccepted
                    ? 'Amazon sync successful'
                    : 'Amazon validation failed',
                'issues' => $issues
            ]);
        } catch (\Throwable $e) {
            // Log::error('❌ AMAZON SYNC FAILED', [
            //     'error' => $e->getMessage(),
            //     'line' => $e->getLine(),
            //     'file' => $e->getFile(),
            //     'sku' => $sku ?? null,
            //     'product_id' => $product->id ?? null,
            //     'shop_id' => $shopModel->id ?? null,
            // ]);
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

    /**
     * orders/create — new order. Runs full sync: upsert row, adjust inventory, notify.
     */
    public function handleOrdersCreateWebhook(Request $request)
    {
        return $this->upsertOrderFromWebhook($request, 'create');
    }

    /**
     * orders/updated — existing order changed. Same upsert logic, updateOrCreate()
     * already handles "update if exists" so this can safely reuse the same path.
     */
    public function handleOrdersUpdateWebhook(Request $request)
    {
        return $this->upsertOrderFromWebhook($request, 'update');
    }

    /**
     * orders/delete — order removed from Shopify (rare; mostly dev/test stores).
     * Payload here is minimal — typically just {"id": ...} — so this does NOT
     * reuse the create/update logic. It only verifies + removes the local row.
     */
    public function handleOrdersDeleteWebhook(Request $request)
    {
        $payload = $request->getContent();

        $shopDomain = strtolower(
            trim((string) $request->header('X-Shopify-Shop-Domain'))
        );

        if (!$this->shopifyWebhook->isValidWebhook(
            $payload,
            $request->header('X-Shopify-Hmac-Sha256')
        )) {
            Log::warning(
                'Rejected Shopify order delete webhook because HMAC validation failed.',
                [
                    'shop' => $shopDomain,
                ]
            );

            return response('Invalid webhook signature', 401);
        }

        $shopModel = $this->findShopByIdentifier($shopDomain);

        if (!$shopModel) {
            Log::warning('Shopify order delete webhook shop not found.', [
                'shop_domain' => $shopDomain,
            ]);

            return response('Shop not found', 404);
        }

        $data = json_decode($payload, true);

        if (!is_array($data) || empty($data['id'])) {
            Log::warning('Invalid Shopify order delete payload.', [
                'shop_domain' => $shopDomain,
                'payload' => $data,
            ]);

            return response('Invalid order payload', 400);
        }

        $rawOrderId = (string) $data['id'];

        // Convert GraphQL ID to numeric Shopify order ID if required
        $orderId = $rawOrderId;

        if (str_starts_with($orderId, 'gid://shopify/Order/')) {
            $orderId = basename($orderId);
        }

        Log::info('Shopify order delete matching started.', [
            'shop_domain' => $shopDomain,
            'shop_id' => $shopModel->id,
            'raw_order_id' => $rawOrderId,
            'normalized_order_id' => $orderId,
        ]);

        $deleted = ShopifyOrder::where('shop_id', $shopModel->id)
            ->where('shopify_order_id', $orderId)
            ->delete();

        Log::info('Shopify order delete completed.', [
            'shop_domain' => $shopDomain,
            'shop_id' => $shopModel->id,
            'shopify_order_id' => $orderId,
            'deleted_rows' => $deleted,
        ]);

        return response()->json([
            'success' => true,
            'deleted_rows' => $deleted,
        ], 200);
    }

    /**
     * products/delete — product deleted directly from Shopify Admin.
     * Validates HMAC, resolves shop tenant from X-Shopify-Shop-Domain,
     * finds local Product using shop_id + shopify_id and soft-deletes it,
     * invalidates products_shop_{shopId} cache and Shopify inventory cache, and returns HTTP 200.
     */
    public function handleProductsDeleteWebhook(Request $request)
    {
        $payload = $request->getContent();
        $shopDomain = strtolower(
            trim((string) $request->header('X-Shopify-Shop-Domain', ''))
        );
        $webhookId = (string) ($request->header('X-Shopify-Webhook-Id') ?: $request->header('X-Shopify-Event-Id') ?: '');

        if ($shopDomain === '') {
            Log::warning('Shopify products/delete webhook rejected: missing shop domain header.', [
                'webhook_id' => $webhookId,
            ]);
            return response('No shop domain', 400);
        }

        if (!$this->shopifyWebhook->isValidWebhook(
            $payload,
            $request->header('X-Shopify-Hmac-Sha256')
        )) {
            Log::warning(
                'Rejected Shopify products/delete webhook because HMAC validation failed.',
                [
                    'shop' => $shopDomain,
                    'webhook_id' => $webhookId,
                ]
            );

            return response('Invalid webhook signature', 401);
        }

        $shopModel = $this->findShopByIdentifier($shopDomain);

        if (!$shopModel) {
            Log::warning('Shopify products/delete webhook shop not found.', [
                'shop_domain' => $shopDomain,
                'webhook_id' => $webhookId,
            ]);

            return response('Shop not found', 200);
        }

        $data = json_decode($payload, true);
        $productId = is_array($data) ? ($data['id'] ?? null) : null;

        if ($productId !== null && $productId !== '') {
            $product = Product::where('shop_id', $shopModel->id)
                ->where('shopify_id', (string) $productId)
                ->first();

            if ($product) {
                $product->delete();
                Log::info('Shopify products/delete webhook: local product soft-deleted.', [
                    'shop_id' => $shopModel->id,
                    'shopify_id' => $productId,
                    'local_product_id' => $product->id,
                ]);
            } else {
                Log::info('Shopify products/delete webhook: local product not found in DB.', [
                    'shop_id' => $shopModel->id,
                    'shopify_id' => $productId,
                ]);
            }
        }

        // Invalidate products list cache for this shop
        Cache::forget("products_shop_{$shopModel->id}");

        // Invalidate inventory cache for this shop
        app(\App\Services\ShopifyInventoryService::class)->invalidate($shopModel);

        Log::info('Shopify products/delete webhook received, product soft-deleted, and caches invalidated.', [
            'shop_id' => $shopModel->id,
            'shop_domain' => $shopDomain,
            'webhook_id' => $webhookId,
            'product_id' => $productId,
        ]);

        return response()->json([
            'success' => true,
        ], 200);
    }

    public function returnCreate(Request $request)
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
            Log::warning('Shopify return create webhook received for unregistered shop.', [
                'shop' => $shopDomain,
            ]);

            return response('OK', 200);
        }
        $data = json_decode($payload, true);
        return response('OK', 200);
    }

    public function returnUpdate(Request $request)
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
            Log::warning('Shopify return update webhook received for unregistered shop.', [
                'shop' => $shopDomain,
            ]);

            return response('OK', 200);
        }
        $data = json_decode($payload, true);
        return response('OK', 200);
    }

    public function resolveAggregateShipmentStatus(array $fulfillments): ?string
    {
        $activeFulfillments = array_values(array_filter($fulfillments, function ($f) {
            return is_array($f) && ($f['status'] ?? '') !== 'cancelled';
        }));

        if (empty($activeFulfillments)) {
            return null;
        }

        $shipmentStatuses = array_map(function ($f) {
            return $f['shipment_status'] ?? null;
        }, $activeFulfillments);

        $nonNullStatuses = array_values(array_filter($shipmentStatuses));

        if (empty($nonNullStatuses)) {
            return null;
        }

        // If all active fulfillments are delivered, the aggregate status is delivered
        if (count($nonNullStatuses) === count($activeFulfillments) && collect($nonNullStatuses)->every(fn($s) => $s === 'delivered')) {
            return 'delivered';
        }

        // Active delivery stage priority
        $priorityOrder = [
            'out_for_delivery',
            'in_transit',
            'attempted_delivery',
            'failure',
            'delivered',
            'ready_for_pickup',
            'label_printed',
            'label_purchased',
            'confirmed',
        ];

        foreach ($priorityOrder as $priority) {
            if (in_array($priority, $nonNullStatuses, true)) {
                return $priority;
            }
        }

        return $nonNullStatuses[0] ?? null;
    }

    public function upsertOrderFromWebhook(Request $request, string $action = 'create')
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
            // ACK the webhook so Shopify does not mark the endpoint as broken.
            // Do NOT create a placeholder Shop or restore a soft-deleted/uninstalled
            // Shop — that would manufacture unauthorized tenant state.
            Log::warning('Shopify order webhook received for unknown/inactive shop — acknowledged without processing.', [
                'shop_domain' => $shopDomain,
                'topic' => 'orders/' . $action,
                'reason' => 'shop_not_found_or_inactive',
            ]);

            return response('OK', 200);
        }

        $data = json_decode($payload, true);

        Log::info('Shopify order webhook received.', [
            'shop' => $shopDomain,
            'order_id' => $data['id'] ?? null,
            'topic' => 'orders/' . $action,
        ]);

        if (!is_array($data) || empty($data['id'])) {
            return response('Invalid order payload', 400);
        }

        try {
            $syncResult = $this->orderSyncService->syncOrder(
                $shopModel,
                $data,
                $action,
                $eventId !== '' ? $eventId : null,
                $webhookId !== '' ? $webhookId : null
            );
        } catch (\Throwable $e) {
            Log::error('Shopify order sync failed with exception.', [
                'shop' => $shopDomain,
                'order_id' => $data['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return response('Internal processing error', 500);
        }

        $orderNumber = ltrim((string) ($data['name'] ?? ($data['order_number'] ?? '')), '#');
        $orderDisplay = $data['name'] ?? ('#' . ($data['order_number'] ?? ''));

        if ($syncResult['result'] === 'created') {
            UserNotificationService::send(
                $shopModel->id,
                'order_sync',
                'Shopify Order Received',
                'Shopify Order ' . $orderDisplay . ' received successfully.'
            );
        } elseif ($syncResult['result'] === 'updated') {
            $isFulfilledTransition = !empty($syncResult['fulfillment_transition']);
            $isDeliveredTransition = !empty($syncResult['delivered_transition']);

            if ($isFulfilledTransition) {
                UserNotificationService::send(
                    $shopModel->id,
                    'order_sync',
                    'Shopify Order Fulfilled',
                    'Order #' . $orderNumber . ' has been fulfilled.'
                );
            }

            if ($isDeliveredTransition) {
                UserNotificationService::send(
                    $shopModel->id,
                    'order_sync',
                    'Shopify Order Delivered',
                    'Order #' . $orderNumber . ' has been delivered.'
                );
            }

            if (!$isFulfilledTransition && !$isDeliveredTransition && $action === 'update') {
                UserNotificationService::send(
                    $shopModel->id,
                    'order_sync',
                    'Shopify Order Updated',
                    'Shopify Order ' . $orderDisplay . ' updated successfully.'
                );
            }
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

        $this->ensureFreshAccessToken($shopModel);

        $activeShop = $shopModel->shop;

        $cacheKey = "products_shop_{$shopModel->id}";

        //   REFRESH FLOW (correct order)
        if ($request->has('refresh')) {
            $refreshSuccess = $this->refreshProductsCache($shopModel);
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => $refreshSuccess,
                ]);
            }
        }

        //   LOAD DATA (cache → sync/DB fallback)
        $allProducts = Cache::get($cacheKey);

        if ($allProducts === null) {
            Log::info('PRODUCT CACHE MISS → SYNCING FROM SHOPIFY', [
                'shop_id' => $shopModel->id,
                'shop' => $shopModel->shop,
            ]);

            $syncSuccess = $this->syncProductsToDB($shopModel);

            $products = Product::where('shop_id', $shopModel->id)
                ->latest()
                ->get();

            if ($syncSuccess) {
                Cache::put(
                    $cacheKey,
                    $products,
                    now()->addMinutes(15)
                );
                Log::info('PRODUCT CACHE REBUILT AFTER SHOPIFY SYNC', [
                    'shop_id' => $shopModel->id,
                    'products_count' => $products->count(),
                ]);
            } else {
                Log::warning('SHOPIFY PRODUCT SYNC FAILED → SERVING EXISTING DB RECORDS WITHOUT POISONING CACHE', [
                    'shop_id' => $shopModel->id,
                    'products_count' => $products->count(),
                ]);
            }

            $allProducts = $products;
        }

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
            $variants = $product->variants ?? [];
            if (is_string($variants)) {
                $decoded = json_decode($variants, true);
                $variants = is_array($decoded) ? $decoded : [];
            }
            $inv = collect($variants)->sum(function ($v) {
                $q = data_get($v, 'inventory_quantity')
                    ?? data_get($v, 'quantity')
                    ?? data_get($v, 'available')
                    ?? data_get($v, 'qty')
                    ?? data_get($v, 'stock')
                    ?? 0;
                return is_numeric($q) ? (int) $q : 0;
            });
            if ($inv === 0 && empty($variants)) {
                $directQty = data_get($product, 'inventory_quantity')
                    ?? data_get($product, 'inventory')
                    ?? data_get($product, 'stock')
                    ?? data_get($product, 'quantity')
                    ?? 0;
                $inv = is_numeric($directQty) ? (int) $directQty : 0;
            }
            return $inv <= 0;
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

    public function syncProductsToDB($shopModel): bool
    {
        $this->ensureFreshAccessToken($shopModel);
        try {
            $locationId = $this->getSelectedShopifyLocationId($shopModel);
            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $result = $shopifyService->getProductsForSync($shopModel, $locationId);

            if (!empty($result['error'])) {
                Log::error('SHOPIFY PRODUCT SYNC RETURNED ERROR', [
                    'shop_id' => $shopModel->id,
                    'shop' => $shopModel->shop,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                return false;
            }

            $products = $result['products'] ?? [];

            foreach ($products as $product) {
                $shopifyId = (string) $product['id'];

                $productModel = Product::withTrashed()
                    ->where('shopify_id', $shopifyId)
                    ->where('shop_id', $shopModel->id)
                    ->first();

                if ($productModel) {
                    if ($productModel->trashed()) {
                        $productModel->restore();
                    }
                } else {
                    $productModel = new Product([
                        'shopify_id' => $shopifyId,
                        'shop_id' => $shopModel->id,
                    ]);
                }

                $productModel->title = $product['title'] ?? '';
                $productModel->description = html_to_plain_text($product['body_html'] ?? '');
                $productModel->price = $product['variants'][0]['price'] ?? 0;
                $productModel->status = $product['status'] ?? 'draft';
                $productModel->product_type = $product['product_type'] ?? null;
                $productModel->vendor = $product['vendor'] ?? null;
                $productModel->tags = $product['tags'] ?? null;
                $productModel->images = $product['images'] ?? [];
                $productModel->variants = $product['variants'] ?? [];
                $productModel->options = $product['options'] ?? [];

                $productModel->save();

                Log::info('PRODUCT SYNC SAVED', [
                    'product_id' => $productModel->id,
                    'shopify_id' => $shopifyId,
                    'shop_id' => $shopModel->id,
                    'synced_to_amazon' => $productModel->synced_to_amazon,
                    'needs_resync' => $productModel->needs_resync,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('SHOPIFY SYNC EXCEPTION', [
                'shop_id' => $shopModel->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function viewProduct(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect()->route('dashboard')->with('error', 'Product not found');
        }
        $this->ensureFreshAccessToken($shopModel);
        try {
            $locationId = $this->getSelectedShopifyLocationId($shopModel);
            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $product = $shopifyService->getProductForView($shopModel, $id, $locationId);

            if (!$product) {
                return redirect($this->shopAwareUrl('/dashboard', $shopModel->shop))
                    ->with('error', 'Product not found');
            }

            return view('product-view', compact('product', 'activeShop'));
        } catch (\Exception $e) {
            Log::error('VIEW PRODUCT FAILED', [
                'error' => $e->getMessage()
            ]);
            return redirect($this->shopAwareUrl('/dashboard', $shopModel?->shop))
                ->with('error', 'Product not found');
        }
    }

    public function editProduct(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect()->route('dashboard')->with('error', 'Product not found');
        }
        $this->ensureFreshAccessToken($shopModel);
        try {
            $locationId = $this->getSelectedShopifyLocationId($shopModel);
            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $product = $shopifyService->getProductForView($shopModel, $id, $locationId);

            if (!$product) {
                return redirect($this->shopAwareUrl('/dashboard', $shopModel->shop))->with('error', 'Product not found');
            }

            $dbProduct = \App\Models\Product::where('shopify_id', $id)
                ->where('shop_id', $shopModel->id)
                ->first();

            $amazonData = null;
            if ($dbProduct) {
                $amazonData = \App\Models\AmazonProduct::where('product_id', $dbProduct->id)->first();
            }

            return view('EditProduct', compact('product', 'activeShop', 'amazonData', 'dbProduct'));
        } catch (\Exception $e) {
            Log::error('EDIT PRODUCT FAILED', [
                'shop' => $shopModel?->shop,
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            return redirect($this->shopAwareUrl('/dashboard', $shopModel?->shop))->with('error', 'Product not found');
        }
    }

    public function create(Request $request)
    {
        $shop = $this->getActiveShop($request);

        return view('createProduct', [
            'activeShop' => $shop->shop,
            'currency' => optional($shop->settings)->currency ?? 'INR',
        ]);
    }

    /**
     * Helper to Upsert Metafields using GraphQL
     */
    protected function syncProductMetafields(Shop $shopModel, $productId, $metaNames, $metaValues)
    {
        if (empty($metaNames) || empty($metaValues)) {
            return ['success' => true, 'metafields' => []];
        }

        $metafields = [];
        $localMetafields = [];

        foreach ($metaNames as $index => $name) {
            $name = trim((string) $name);
            $value = isset($metaValues[$index]) ? trim((string) $metaValues[$index]) : '';

            if ($name === '' || $value === '') {
                continue;
            }

            // Shopify keys must match ^[a-z0-9_]+$ - sanitize user input
            // (e.g. "Material Type" -> "material_type")
            $key = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $name));
            $key = trim($key, '_');

            if ($key === '') {
                continue;
            }

            // Avoid duplicate keys in a single metafieldsSet call (whole batch fails otherwise)
            $localMetafields[$key] = $value;
        }

        foreach ($localMetafields as $key => $value) {
            $ownerId = str_starts_with((string) $productId, 'gid://')
                ? (string) $productId
                : "gid://shopify/Product/{$productId}";

            $metafields[] = [
                'ownerId' => $ownerId,
                'namespace' => 'custom',  // Default namespace for custom attributes
                'key' => $key,
                'type' => 'single_line_text_field',  // Best default for text/string
                'value' => (string) $value,
            ];
        }

        if (empty($metafields)) {
            return ['success' => true, 'metafields' => $localMetafields];
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
                ->post("https://{$shopModel->shop}/admin/api/" . config('services.shopify.api_version', '2026-07') . '/graphql.json', [
                    'query' => $query,
                    'variables' => [
                        'metafields' => $metafields
                    ]
                ]);
            $result = $response->json();

            if (isset($result['errors']) && !empty($result['errors'])) {
                Log::error('GraphQL MetafieldsSet Top-level Errors', [
                    'shop' => $shopModel->shop,
                    'product_id' => $productId,
                    'errors' => $result['errors']
                ]);
                return [
                    'success' => false,
                    'metafields' => [],
                    'errors' => $result['errors'],
                    'error' => $result['errors'][0]['message'] ?? 'GraphQL metafieldsSet error.',
                ];
            }

            if (isset($result['data']['metafieldsSet']['userErrors']) && count($result['data']['metafieldsSet']['userErrors']) > 0) {
                Log::error('GraphQL MetafieldsSet UserErrors', [
                    'shop' => $shopModel->shop,
                    'product_id' => $productId,
                    'errors' => $result['data']['metafieldsSet']['userErrors']
                ]);
                return [
                    'success' => false,
                    'metafields' => [],
                    'errors' => $result['data']['metafieldsSet']['userErrors'],
                    'error' => $result['data']['metafieldsSet']['userErrors'][0]['message'] ?? 'Metafield validation error.',
                ];
            }

            Log::info('Successfully synced metafields via GraphQL', [
                'shop' => $shopModel->shop,
                'product_id' => $productId
            ]);

            return [
                'success' => true,
                'metafields' => $localMetafields,
            ];
        } catch (\Exception $e) {
            Log::error('GraphQL Metafields Sync Exception', [
                'shop' => $shopModel->shop,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'metafields' => [],
                'error' => $e->getMessage(),
            ];
        }
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
            'images.*' => 'nullable|image|max:5120',
            'existing_images' => 'nullable|array',
            'existing_images.*' => 'nullable|url',
            'variants' => 'nullable|array',
            'variants.*.image' => 'nullable|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.qty' => 'nullable|integer|min:0',
        ]);

        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            return redirect()->back()->withInput()->with('error', 'No shop connected. Please install the app first.');
        }

        try {
            $localImages = $this->UploadImageProvideUrl($request);
            $payloadData = $this->buildProductPayload($request);
            $locationId = $this->getSelectedShopifyLocationId($shopModel);

            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $result = $shopifyService->createProduct($shopModel, $payloadData['product'], $locationId);

            if (empty($result['success']) || empty($result['product'])) {
                return redirect()->back()->withInput()->with('error', $result['error'] ?? 'Failed to create product');
            }

            $productData = $result['product'];
            $payloadIndexMap = $payloadData['payload_index_map'] ?? [];

            // --- FLOW: MERGE FOR LOCAL DB ---
            $finalVariants = [];
            foreach ($productData['variants'] as $index => $variant) {
                $formIndex = $payloadIndexMap[$index] ?? $index;
                $formVariant = $request->input("variants.{$formIndex}") ?? [];

                $finalVariants[] = [
                    'id' => $variant['id'],
                    'price' => $variant['price'],
                    'sku' => $variant['sku'] ?? null,
                    'inventory_quantity' => $variant['inventory_quantity'] ?? (int) ($formVariant['qty'] ?? 0),
                    'option1' => $variant['option1'] ?? ($formVariant['option1'] ?? null),
                    'option2' => $variant['option2'] ?? ($formVariant['option2'] ?? null),
                    'image' => $variant['image']['src'] ?? ($formVariant['image'] ?? null),
                ];
            }

            // --- FLOW: SYNC METAFIELDS VIA GRAPHQL ---
            $metaNames = $request->input('meta_name', []);
            $metaValues = $request->input('meta_value', []);
            $metafieldsResult = $this->syncProductMetafields($shopModel, $productData['id'], $metaNames, $metaValues);
            $localMetafields = is_array($metafieldsResult) && isset($metafieldsResult['metafields']) ? $metafieldsResult['metafields'] : (is_array($metafieldsResult) ? $metafieldsResult : []);

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
                    'shopify_id' => (string) $productData['id'],
                    'shop_id' => $shopModel->id
                ],
                [
                    'title' => $request->title,
                    'description' => html_to_plain_text($request->description),
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
                $updatesync->updateSyncShopify($syncid, ['product' => $productData]);

                UserNotificationService::send(
                    $shopModel->id,
                    'inventory_stock_update',
                    'Product Synced to Shopify',
                    sprintf(
                        '"%s" has been synced to Amazon successfully.',
                        $dbProduct->title ?? $request->title ?? 'Product'
                    )
                );
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
        Log::info('START: updateProduct called for Shopify Product ID: ' . $id);

        $shopModel = $this->getActiveShop($request);
        $this->ensureFreshAccessToken($shopModel);
        if (!$shopModel) {
            Log::warning('FAIL: No active shop found for request');
            return back()->with('error', 'No shop connected');
        }

        Log::debug('Active Shop found: ID ' . $shopModel->id . ', Shop domain: ' . $shopModel->shop);

        try {
            $dbProduct = Product::where('shop_id', $shopModel->id)
                ->where('shopify_id', $id)
                ->first();

            $existingImages = $request->input('existing_images', []);
            if (is_string($existingImages)) {
                $decoded = json_decode($existingImages, true);
                $existingImages = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $existingImages)));
            }
            $deletedImages = $request->input('deleted_images', []);
            if (is_string($deletedImages)) {
                $decoded = json_decode($deletedImages, true);
                $deletedImages = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $deletedImages)));
            }
            $keptImages = array_diff($existingImages, $deletedImages);

            $imagesData = [];
            foreach ($keptImages as $imgId) {
                if (!empty($imgId)) {
                    if (is_string($imgId) && filter_var(trim($imgId), FILTER_VALIDATE_URL)) {
                        $imagesData[] = ['src' => trim($imgId)];
                    } elseif (is_numeric($imgId) || str_starts_with((string) $imgId, 'gid://')) {
                        $imagesData[] = ['id' => $imgId];
                    }
                }
            }

            // Also support direct 'images' array input if supplied
            $rawImages = $request->input('images', []);
            if (is_string($rawImages)) {
                $decoded = json_decode($rawImages, true);
                $rawImages = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $rawImages)));
            }
            if (is_array($rawImages)) {
                foreach ($rawImages as $img) {
                    if (is_array($img)) {
                        $src = $img['src'] ?? ($img['url'] ?? null);
                        $id = $img['id'] ?? null;
                        if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
                            $imagesData[] = ['src' => $src];
                        } elseif ($id) {
                            $imagesData[] = ['id' => $id];
                        }
                    } elseif (is_string($img)) {
                        $trimmed = trim($img);
                        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                            $imagesData[] = ['src' => $trimmed];
                        } elseif (is_numeric($trimmed) || str_starts_with($trimmed, 'gid://')) {
                            $imagesData[] = ['id' => $trimmed];
                        }
                    }
                }
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $galleryImage) {
                    if ($galleryImage->isValid()) {
                        $path = $galleryImage->store('uploads', 'public');
                        $imagesData[] = ['src' => asset('storage/' . $path)];
                    }
                }
            }

            $variantsPayload = [];
            $variantIds = $request->input('variant_ids', []);
            $variantCombos = $request->input('variant_combo', []);

            foreach ($variantIds as $index => $vId) {
                $vItem = [
                    'id' => !empty($vId) ? $vId : null,
                    'price' => $request->input("variant_price.{$index}"),
                    'sku' => $request->input("variant_sku.{$index}"),
                    'qty' => (int) $request->input("variant_quantity.{$index}", 0),
                    'inventory_quantity' => (int) $request->input("variant_quantity.{$index}", 0),
                ];

                $variantImageUrl = $request->input("variants.{$index}.image") ?? $request->input("variant_image.{$index}");
                $existingVariantImgId = $request->input("existing_variant_image.{$index}") ?? $request->input("variants.{$index}.image_id");

                if (!empty($variantImageUrl) && is_string($variantImageUrl) && filter_var(trim($variantImageUrl), FILTER_VALIDATE_URL)) {
                    $vItem['image'] = [
                        'url' => trim($variantImageUrl),
                        'id' => !empty($existingVariantImgId) ? $existingVariantImgId : null,
                    ];
                    $vItem['image_src'] = trim($variantImageUrl);
                } elseif (!empty($existingVariantImgId)) {
                    $vItem['image'] = [
                        'id' => $existingVariantImgId,
                    ];
                    $vItem['image_id'] = $existingVariantImgId;
                }

                if (!empty($variantCombos[$index])) {
                    $combo = is_array($variantCombos[$index]) ? $variantCombos[$index] : json_decode($variantCombos[$index], true);
                    if (is_array($combo)) {
                        foreach ($combo as $cIdx => $c) {
                            $vItem['option' . ($cIdx + 1)] = $c['value'] ?? ($c['name'] ?? '');
                        }
                    }
                }

                $variantsPayload[] = $vItem;
            }

            $locationId = $this->getSelectedShopifyLocationId($shopModel);

            $optionsInput = $request->input('options', []);
            $variantNames = $request->input('variant_names', []);
            $options = [];
            
            if (!empty($optionsInput) && is_array($optionsInput) && isset($optionsInput[0]['name'])) {
                foreach ($optionsInput as $opt) {
                    $optName = trim((string) ($opt['name'] ?? ''));
                    if (empty($optName))
                        continue;
                    $optVals = [];
                    foreach ($opt['values'] ?? [] as $val) {
                        $valStr = trim((string) $val);
                        if ($valStr !== '')
                            $optVals[] = $valStr;
                    }
                    if (!empty($optVals)) {
                        $options[] = [
                            'name' => $optName,
                            'values' => $optVals,
                        ];
                    }
                }
            } elseif (!empty($variantsPayload) && !empty($variantNames)) {
                foreach ($variantNames as $i => $name) {
                    $name = trim((string) $name);
                    if (empty($name)) {
                        continue;
                    }
                    $values = collect($variantsPayload)
                        ->pluck('option' . ($i + 1))
                        ->filter(fn($val) => !is_null($val) && trim((string) $val) !== '')
                        ->map(fn($val) => trim((string) $val))
                        ->unique()
                        ->values()
                        ->all();
                    if (!empty($values)) {
                        $options[] = [
                            'name' => $name,
                            'values' => $values,
                        ];
                    }
                }
            }

            $updatePayload = [
                'title' => $request->title,
                'description' => $this->formatDescription($request->description),
                'body_html' => $this->formatDescription($request->description),
                'vendor' => $request->vendor,
                'product_type' => $request->product_type,
                'status' => $request->status,
                'tags' => $request->tags,
                'images' => $imagesData,
                'deleted_images' => $deletedImages,
                'variants' => $variantsPayload,
            ];

            if (!empty($options)) {
                $updatePayload['options'] = $options;
            }

            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $result = $shopifyService->updateProduct($shopModel, $id, $updatePayload, $locationId);

            if (empty($result['success']) || empty($result['product'])) {
                Log::error('Shopify GraphQL updateProduct failed', [
                    'shop' => $shopModel->shop,
                    'product_id' => $id,
                    'result' => $result,
                ]);
                return back()->withInput()->with('error', $result['error'] ?? 'Failed to update product');
            }

            $productData = $result['product'];

            // --- FLOW: SYNC METAFIELDS VIA GRAPHQL ---
            Log::info('Syncing Metafields for Product ID: ' . $id);
            $metaNames = $request->input('meta_name', []);
            $metaValues = $request->input('meta_value', []);
            $metafieldsResult = $this->syncProductMetafields($shopModel, $id, $metaNames, $metaValues);
            $metafieldsSuccess = is_array($metafieldsResult) && ($metafieldsResult['success'] ?? true);
            $localMetafields = is_array($metafieldsResult) && isset($metafieldsResult['metafields']) ? $metafieldsResult['metafields'] : (is_array($metafieldsResult) ? $metafieldsResult : []);

            if (!$metafieldsSuccess) {
                Log::warning('PARTIAL SUCCESS: Shopify product updated, but metafieldsSet failed', [
                    'shop_id' => $shopModel->id,
                    'shop' => $shopModel->shop,
                    'product_id' => $id,
                    'errors' => $metafieldsResult['errors'] ?? null,
                    'error' => $metafieldsResult['error'] ?? null,
                ]);
            }

            // Determine Category
            $category = Category::where('id', $request->input('category'))->first();
            $producttype = '';
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

            // Update Local DB
            if ($dbProduct) {
                $productdata = [];
                if ((int) $dbProduct->synced_to_amazon === 1) {
                    $productdata['synced_to_amazon'] = 0;
                }

                $productdata['title'] = $request->title;
                $productdata['description'] = html_to_plain_text($request->description);
                $productdata['vendor'] = $request->vendor;
                $productdata['product_type'] = $subcategory;
                $productdata['category'] = $producttype;
                $productdata['category_id'] = $category_id;
                $productdata['sub_category_id'] = $sub_category_id;
                $productdata['variants'] = json_encode($productData['variants'] ?? []);
                $productdata['options'] = json_encode($productData['options'] ?? []);
                $productdata['images'] = json_encode($productData['images'] ?? []);
                if ($metafieldsSuccess) {
                    $productdata['metafields'] = json_encode($localMetafields);
                }

                $dbProduct->update($productdata);
            }

            // Sync Log
            if ($dbProduct) {
                ProductSyncLog::create([
                    'product_id' => $dbProduct->id,
                    'shop_id' => $shopModel->id,
                    'platform' => 'shopify',
                    'status' => $metafieldsSuccess ? 'success' : 'warning',
                    'message' => $metafieldsSuccess
                        ? 'Product "' . $dbProduct->title . '" was updated successfully during resync.'
                        : 'Product "' . $dbProduct->title . '" updated in Shopify, but metafields failed to save: ' . ($metafieldsResult['error'] ?? 'validation error'),
                    'type' => 'product'
                ]);
            }

            // Amazon Data Update
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

            $message = sprintf(
                '%s - "%s" has been updated successfully.',
                $shopModel->shop,
                $dbProduct->title ?? $request->title ?? 'Product'
            );

            UserNotificationService::send(
                $shopModel->id,
                'inventory_stock_update',
                'Inventory Stock Updated',
                $message
            );

            $this->refreshProductsCache($shopModel);

            Log::info('END: updateProduct completed successfully for ID: ' . $id);

            if (!$metafieldsSuccess) {
                return redirect($this->shopAwareUrl('/products', $shopModel->shop))
                    ->with('warning', 'Product updated in Shopify, but metafields failed to save: ' . ($metafieldsResult['error'] ?? 'GraphQL error'));
            }

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
                'shop_id' => $shopModel->id,
                'shop' => $shopModel->shop,
            ]);

            $numericId = is_numeric($id) ? (int) $id : (str_contains((string) $id, '/') ? (int) substr($id, strrpos($id, '/') + 1) : $id);

            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $response = $shopifyService->deleteProduct($shopModel, $id);

            if (empty($response['success'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete product from Shopify: ' . ($response['error'] ?? 'Unknown error'),
                ], 400);
            }

            Product::withTrashed()
                ->where('shop_id', $shopModel->id)
                ->where('shopify_id', $numericId)
                ->first()
                ?->forceDelete();

            $updatesync = new ProductSchemaController();
            $updatesync->updatelog($numericId, 'shopify', 'deleted', true);

            $this->refreshProductsCache($shopModel);
            return response()->json(['success' => true, 'message' => 'Product deleted successfully']);
        } catch (\Exception $exception) {
            Log::error('Delete Product Exception', [
                'shop_id' => $shopModel->id,
                'product_id' => $id,
                'error' => $exception->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product from Shopify: ' . $exception->getMessage(),
            ], 400);
        }
    }

    private function refreshProductsCache($shopModel): bool
    {
        $cacheKey = "products_shop_{$shopModel->id}";
        Cache::forget($cacheKey);
        $syncSuccess = $this->syncProductsToDB($shopModel);
        $products = Product::where('shop_id', $shopModel->id)
            ->latest()
            ->get();

        if ($syncSuccess || $products->isNotEmpty()) {
            Cache::put(
                $cacheKey,
                $products,
                now()->addMinutes(15)
            );
        }

        Log::info('PRODUCT CACHE REBUILT', [
            'shop_id' => $shopModel->id,
            'products_count' => $products->count(),
            'sync_success' => $syncSuccess,
        ]);

        return $syncSuccess;
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
                session('amazon_shop'),  // optional backward compatibility
                session('shop'),  // optional backward compatibility
            ] as $candidate
        ) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return strtolower($candidate);
            }
        }

        return null;
    }

    /**
     * Resolve a Shopify shop identifier (domain, URL variant) to an active, non-deleted Shop.
     *
     * Rules:
     *  - Strips https?://, www., and trailing slashes before matching.
     *  - Tries both "store.myshopify.com" and bare "store" forms.
     *  - Only returns a Shop that is NOT soft-deleted.
     *  - Never restores a soft-deleted shop — that is only the explicit OAuth/install flow's job.
     */
    public function findShopByIdentifier(?string $identifier): ?Shop
    {
        $raw = trim((string) $identifier);
        if ($raw === '') {
            return null;
        }

        // Normalize: strip protocol, www prefix, and trailing slashes.
        $cleaned = preg_replace('#^https?://#i', '', $raw);
        $cleaned = preg_replace('#^www\.#i', '', $cleaned);
        $cleaned = strtolower(trim($cleaned, "/ \t\n\r\0\v"));

        if ($cleaned === '') {
            return null;
        }

        // Build candidate forms: full myshopify domain and bare slug.
        $candidates = array_unique(array_filter([
            $cleaned,
            str_contains($cleaned, '.myshopify.com') ? $cleaned : ($cleaned . '.myshopify.com'),
            str_replace('.myshopify.com', '', $cleaned),
        ]));

        $hasDomainCol = \Illuminate\Support\Facades\Schema::hasColumn('shops', 'domain');

        foreach ($candidates as $cand) {
            // Only match active (non-soft-deleted) shops.
            $query = Shop::where(function ($q) use ($cand, $hasDomainCol) {
                $q->whereRaw('LOWER(shop) = ?', [$cand]);
                if ($hasDomainCol) {
                    $q->orWhereRaw('LOWER(domain) = ?', [$cand]);
                }
            });

            $shop = $query->first();

            if ($shop) {
                return $shop;
            }
        }

        return null;
    }

    protected function getActiveShop(?Request $request = null): ?Shop
    {
        $request ??= request();

        if ($request?->attributes->has('active_shop_model')) {
            $model = $request->attributes->get('active_shop_model');
            if ($model instanceof Shop && (int) $model->is_active === 1 && !empty($model->access_token)) {
                return $model;
            }
        }

        $shopIdentifier = $this->extractShopIdentifier($request);
        if ($shopIdentifier === null) {
            Log::warning('NO SHOP IDENTIFIER FOUND', [
                'query_shop' => $request?->query('shop'),
                'query_host' => $request?->query('host'),
                'path' => $request?->path(),
            ]);
            return null;
        }
        $shop = $this->findShopByIdentifier($shopIdentifier);
        if (!$shop) {
            Log::warning('Shopify shop not found in database.', [
                'shop_identifier' => $shopIdentifier,
                'query_shop' => $request?->query('shop'),
                'query_host' => $request?->query('host'),
                'path' => $request?->path(),
            ]);
            return null;
        }
        Log::info('ACTIVE SHOP CHECK', [
            'shop' => $shop->shop,
            'is_active' => $shop->is_active,
            'access_token_empty' => empty($shop->access_token),
        ]);
        if (
            (int) $shop->is_active !== 1 ||
            empty($shop->access_token)
        ) {
            Log::warning('INACTIVE SHOP BLOCKED', [
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

    protected function getSelectedShopifyLocationId(Shop $shop): ?int
    {
        $locations = $shop->shopify_locations ?? [];
        if (empty($locations)) {
            Log::warning('SHOPIFY LOCATION NOT SELECTED - NO LOCATIONS', [
                'shop_id' => $shop->id,
            ]);

            return null;
        }

        $index = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
            ? (int) $shop->selected_location_index
            : 0;

        $locationId = $locations[$index]['id'] ?? null;

        if (!$locationId) {
            Log::warning('SHOPIFY SELECTED LOCATION ID MISSING', [
                'shop_id' => $shop->id,
                'selected_location_index' => $shop->selected_location_index,
                'effective_index' => $index,
                'location' => $locations[$index] ?? null,
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
        if (is_string($existingImages)) {
            $decoded = json_decode($existingImages, true);
            $existingImages = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $existingImages)));
        }
        $deletedImages = $request->input('deleted_images', []);
        if (is_string($deletedImages)) {
            $decoded = json_decode($deletedImages, true);
            $deletedImages = is_array($decoded) ? $deletedImages : array_filter(array_map('trim', explode(',', $deletedImages)));
        }
        $keptImages = array_diff($existingImages, $deletedImages);

        foreach ($keptImages as $img) {
            if (is_string($img) && filter_var(trim($img), FILTER_VALIDATE_URL)) {
                $images[] = ['src' => trim($img)];  // Instructs Shopify to download from URL
            } elseif (is_numeric($img) || (is_string($img) && str_starts_with($img, 'gid://'))) {
                $images[] = ['id' => $img];
            }
        }

        // Support direct 'images' array/JSON input
        $rawImages = $request->input('images', []);
        if (is_string($rawImages)) {
            $decoded = json_decode($rawImages, true);
            $rawImages = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $rawImages)));
        }
        if (is_array($rawImages)) {
            foreach ($rawImages as $img) {
                if (is_array($img)) {
                    $src = $img['src'] ?? ($img['url'] ?? null);
                    $id = $img['id'] ?? null;
                    if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
                        $images[] = ['src' => $src];
                    } elseif ($id) {
                        $images[] = ['id' => $id];
                    }
                } elseif (is_string($img)) {
                    $trimmed = trim($img);
                    if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                        $images[] = ['src' => $trimmed];
                    } elseif (is_numeric($trimmed) || str_starts_with($trimmed, 'gid://')) {
                        $images[] = ['id' => $trimmed];
                    }
                }
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $galleryImage) {
                if ($galleryImage->isValid()) {
                    $path = $galleryImage->store('uploads', 'public');
                    $images[] = [
                        'src' => asset('storage/' . $path),
                        'attachment' => base64_encode(
                            file_get_contents($galleryImage->getRealPath())
                        )
                    ];
                }
            }
        }

        if ($request->hasFile('variants')) {
            foreach ($request->file('variants', []) as $index => $variantFiles) {
                if (!empty($variantFiles['image']) && $variantFiles['image']->isValid()) {
                    $file = $variantFiles['image'];
                    $path = $file->store('uploads', 'public');
                    $images[] = [
                        'src' => asset('storage/' . $path),
                        'attachment' => base64_encode(
                            file_get_contents($file->getRealPath())
                        )
                    ];
                    $variantImageMap[$index] = count($images) - 1;
                }
            }
        }

        $variantsInput = $request->input('variants', []);
        foreach ($variantsInput as $index => $vData) {
            if (!empty($vData['image']) && is_string($vData['image'])) {
                $variantImgUrl = trim($vData['image']);
                if (filter_var($variantImgUrl, FILTER_VALIDATE_URL)) {
                    $existingPosition = null;
                    foreach ($images as $pos => $imgObj) {
                        if (isset($imgObj['src']) && $imgObj['src'] === $variantImgUrl) {
                            $existingPosition = $pos;
                            break;
                        }
                    }
                    if ($existingPosition !== null) {
                        $variantImageMap[$index] = $existingPosition;
                    } else {
                        $images[] = ['src' => $variantImgUrl];
                        $variantImageMap[$index] = count($images) - 1;
                    }
                }
            }
        }

        $variantsInput = $request->input('variants', []);
        $optionsInput = $request->input('options', []);
        $variantNames = $request->input('variant_names', []);
        $variants = [];
        $payloadIndexToFormIndexMap = [];

        foreach ($variantsInput as $originalIndex => $v) {
            // Enforce fallback price to prevent array skips
            $vPrice = !empty($v['price']) ? (float) $v['price'] : (float) $request->input('price', 0);

            $variant = [
                'price' => $vPrice,
                'sku' => !empty($v['sku']) ? $v['sku'] : ($request->input('sku') ?: 'SKU-' . uniqid()),
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
            ];

            if (isset($v['qty'])) {
                $variant['qty'] = (int) $v['qty'];
                $variant['inventory_quantity'] = (int) $v['qty'];
            } elseif (isset($v['inventory_quantity'])) {
                $variant['qty'] = (int) $v['inventory_quantity'];
                $variant['inventory_quantity'] = (int) $v['inventory_quantity'];
            } elseif ($request->filled('qty')) {
                $variant['qty'] = (int) $request->input('qty');
                $variant['inventory_quantity'] = (int) $request->input('qty');
            }

            if (!empty($v['barcode'])) {
                $variant['barcode'] = (string) $v['barcode'];
            } elseif ($request->filled('barcode')) {
                $variant['barcode'] = (string) $request->input('barcode');
            }

            if (!empty($v['compare_at_price'])) {
                $variant['compare_at_price'] = (string) $v['compare_at_price'];
            } elseif ($request->filled('compare_at_price')) {
                $variant['compare_at_price'] = (string) $request->input('compare_at_price');
            }

            if (!empty($v['option1']))
                $variant['option1'] = trim($v['option1']);
            if (!empty($v['option2']))
                $variant['option2'] = trim($v['option2']);
            if (!empty($v['option3']))
                $variant['option3'] = trim($v['option3']);

            // Attach any image / image ID mapped to this variant
            $variantImgUrl = !empty($v['image']) && is_string($v['image']) ? trim($v['image']) : ($request->input("variant_image.{$originalIndex}") ?? null);
            $existingImgId = $v['existing_image_id'] ?? ($v['image_id'] ?? ($request->input("existing_variant_image.{$originalIndex}") ?? null));

            if (!empty($variantImgUrl) && filter_var($variantImgUrl, FILTER_VALIDATE_URL)) {
                $variant['image'] = [
                    'url' => $variantImgUrl,
                    'id' => !empty($existingImgId) ? $existingImgId : null,
                ];
                $variant['image_src'] = $variantImgUrl;
            } elseif (!empty($existingImgId)) {
                $variant['image'] = [
                    'id' => $existingImgId,
                ];
                $variant['image_id'] = $existingImgId;
            }

            $variants[] = $variant;
            $payloadIndexToFormIndexMap[count($variants) - 1] = $originalIndex;
        }

        if (empty($variants)) {
            $variant = [
                'price' => (float) $request->input('price', 0),
                'sku' => $request->input('sku') ?: 'SKU-' . uniqid(),
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
            ];
            if ($request->filled('qty')) {
                $variant['qty'] = (int) $request->input('qty');
                $variant['inventory_quantity'] = (int) $request->input('qty');
            }
            if ($request->filled('barcode')) {
                $variant['barcode'] = (string) $request->input('barcode');
            }
            if ($request->filled('compare_at_price')) {
                $variant['compare_at_price'] = (string) $request->input('compare_at_price');
            }
            $variants[] = $variant;
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
        if (!empty($optionsInput) && is_array($optionsInput) && isset($optionsInput[0]['name'])) {
            foreach ($optionsInput as $opt) {
                $optName = trim((string) ($opt['name'] ?? ''));
                if (empty($optName))
                    continue;
                $optVals = [];
                foreach ($opt['values'] ?? [] as $val) {
                    $valStr = trim((string) $val);
                    if ($valStr !== '')
                        $optVals[] = $valStr;
                }
                if (!empty($optVals)) {
                    $options[] = [
                        'name' => $optName,
                        'values' => $optVals,
                    ];
                }
            }
        } elseif (!empty($variantsInput) && !empty($variantNames)) {
            foreach ($variantNames as $i => $name) {
                $name = trim((string) $name);
                if (empty($name)) {
                    continue;
                }
                $values = collect($variantsInput)
                    ->pluck('option' . ($i + 1))
                    ->filter(fn($val) => !is_null($val) && trim((string) $val) !== '')
                    ->map(fn($val) => trim((string) $val))
                    ->unique()
                    ->values()
                    ->all();
                if (!empty($values)) {
                    $options[] = [
                        'name' => $name,
                        'values' => $values,
                    ];
                }
            }
        }

        // Validate that options and variants are aligned
        if (!empty($options)) {
            $allVariantsHaveOption1 = collect($variants)->every(function ($v) {
                return !empty($v['option1']);
            });

            if (!$allVariantsHaveOption1) {
                $options = [];
            }
        }

        // If no valid options, ensure single/default variants do not contain dangling option keys
        if (empty($options)) {
            foreach ($variants as &$v) {
                unset($v['option1'], $v['option2'], $v['option3']);
            }
            unset($v);
        }

        $category = \App\Models\Category::where('id', $request->input('category'))->first();
        $producttype = !empty($category) ? $category->category : $request->input('product_type');

        $subcategory = \App\Models\Category::where('id', $request->input('sub_category'))->first();
        $subcategory = !empty($subcategory) ? $subcategory->slug : ($category->slug ?? 'test');

        $product = [
            'title' => $request->input('title'),
            'body_html' => $this->formatDescription($request->input('description')),
            'vendor' => $request->input('vendor'),
            'product_type' => $request->input('product_type'),
            'category' => $producttype ?: $subcategory,
            'tags' => $request->input('tags'),
            'status' => $status,
            'variants' => $variants,
            'images' => $images
        ];

        if (!empty($options)) {
            $product['options'] = $options;
        }

        return [
            'product' => $product,
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

        // Revenue stats
        $totalRevenue = $shop
            ->orders()
            ->where('financial_status', '!=', 'refunded')
            ->sum('total_price');
        $averageOrderValue = $orderCount > 0 ? $totalRevenue / $orderCount : 0;

        // Orders by status
        $ordersByStatus = ShopifyOrder::where('shop_id', $shop->id)
            ->selectRaw('financial_status, COUNT(*) as count')
            ->groupBy('financial_status')
            ->pluck('count', 'financial_status')
            ->toArray();

        // Recent orders
        $recentOrders = ShopifyOrder::where('shop_id', $shop->id)
            ->orderBy('order_created_at', 'desc')
            ->limit(5)
            ->get();

        // Sync operation status breakdown
        $syncStatusCounts = \App\Models\InventorySyncOperation::where('shop_id', $shop->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Recent sync logs
        $recentSyncLogs = ProductSyncLog::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Admin notifications for this shop (includes global notifications with null shop_id)
        $notifications = \App\Models\UserNotification::where(function ($q) use ($shop) {
            $q->whereNull('shop_id')->orWhere('shop_id', $shop->id);
        })->orderBy('created_at', 'desc')->limit(20)->get();

        return view('admin.shops.view', compact('shop', 'customPlan', 'productCount',
            'logCount', 'orderCount', 'totalRevenue',
            'averageOrderValue', 'ordersByStatus', 'recentOrders',
            'syncStatusCounts', 'recentSyncLogs', 'notifications'));
    }

    public function getSellerIdFull()
    {
        try {
            //   STEP 1: CONNECTOR
            $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                clientId: AdminSetting::get('production_client_id',
                    config('amazon.client_id')),
                clientSecret: AdminSetting::get('production_client_secret',
                    config('amazon.client_secret')),
                refreshToken: AdminSetting::get('amazon_refresh_token',
                    config('amazon.refresh_token')),
                endpoint: \SellingPartnerApi\Enums\Endpoint::NA_SANDBOX
            );

            //   STEP 2: API CALL
            $res = $connector->sellersV1()->getMarketplaceParticipations();
            //   STEP 3: RAW RESPONSE (MOST IMPORTANT)
            $rawBody = $res->body();
            $json = $res->json();
            $headers = $res->headers();
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
            ]);
        } catch (\Exception $e) {
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
        $activeShop = session('active_shop');
        $shop = \App\Models\Shop::where('shop', $activeShop)->first();

        if (!$shop || empty($shop->amazon_refresh_token)) {
            return [];
        }

        $cacheKey = 'amazon_orders_' . $activeShop . '_' . $shop->seller_id;
        $cacheKeyai = 'amazon_orders_ai_' . $activeShop . '_' . $shop->seller_id;
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $date = now()->subDays(30)->toDateTimeString();

        if ($shop) {
            $createdAfter = \Carbon\Carbon::parse(
                $shop->created_at, 'UTC'
            )->toAtomString();
        } else {
            $createdAfter = now()->subDays(30)->toDateTimeString();
        }

        return Cache::remember(
            $cacheKey, now()->addHours(24),
            function () use ($shop, $createdAfter) {
                try {
                    $connector = \SellingPartnerApi\SellingPartnerApi::seller(
                        clientId: AdminSetting::get('production_client_id', config('amazon.client_id')),
                        clientSecret: AdminSetting::get('production_client_secret', config('amazon.client_secret')),
                        refreshToken: $shop->amazon_refresh_token,
                        endpoint: \SellingPartnerApi\Enums\Endpoint::NA
                    );

                    $response = $connector->ordersV0()->getOrders(
                        marketplaceIds: [$shop->amazon_marketplace_id],
                        createdAfter: now()->subDays(30)->utc()->toIso8601String()
                    );

                    $data = json_decode($response->body(), true);
                    $orders = $data['payload']['Orders'] ?? [];

                    foreach ($orders as $order) {
                        $orderId = $order['AmazonOrderId'] ?? null;

                        if ($orderId) {
                            Cache::put('amazon_order_' . $orderId, $order,
                                now()->addHours(24));
                        }
                    }
                    Cache::put($cacheKeyai, $orders, now()->addHours(24));
                    return $orders;
                } catch (\Exception $e) {
                    Log::error('Amazon orders fetch failed', [
                        'shop' => $shop->shop,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            }
        );
    }

    public function getAmazonOrders()
    {
        try {
            $activeShop = session('active_shop');

            $shop = Shop::where('shop', $activeShop)->first();

            // Amazon is not connected for this shop
            if (!$shop || empty($shop->amazon_refresh_token)) {
                return response()->json([
                    'success' => true,
                    'amazonConnected' => false,
                    'orders' => [],
                ]);
            }

            $orders = $this->fetchAmazonOrders();

            return response()->json([
                'success' => true,
                'amazonConnected' => true,
                'orders' => $orders,
            ]);
        } catch (\Exception $e) {
            \Log::error('AMAZON ORDERS FETCH ERROR', [
                'shop' => session('active_shop'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleAppUninstalledWebhook(Request $request)
    {
        Log::info('UNINSTALL WEBHOOK HIT', [
            'shop' => $request->header('X-Shopify-Shop-Domain')
        ]);
        $payload = $request->getContent();
        $shopDomain = strtolower(trim((string) $request->header('X-Shopify-Shop-Domain', '')));
        if (!$shopDomain) {
            return response('No shop domain', 400);
        }
        if (!$this->shopifyWebhook->isValidWebhook($payload, $request->header('X-Shopify-Hmac-Sha256'))) {
            Log::warning('Invalid uninstall webhook', ['shop' => $shopDomain]);
            return response('Invalid webhook', 401);
        }
        try {
            // acknowledges cleanly. We do NOT restore the shop — we only deactivate it.
            $normalizedDomain = strtolower(trim(
                preg_replace('#^www\.#i', '',
                    preg_replace('#^https?://#i', '', $shopDomain)),
                "/ \t\n\r\0\v"
            ));
            $shop = \App\Models\Shop::withTrashed()
                ->where(function ($q) use ($normalizedDomain) {
                    $q->whereRaw('LOWER(shop) = ?', [$normalizedDomain]);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('shops', 'domain')) {
                        $q->orWhereRaw('LOWER(domain) = ?', [$normalizedDomain]);
                    }
                })
                ->first();
            if (!$shop) {
                Log::info('App uninstalled webhook received for unknown shop — acknowledged.', [
                    'shop_domain' => $shopDomain,
                    'reason' => 'shop_not_found',
                ]);
                return response('OK', 200);
            }

            $recipientEmail = $shop->email;
            $shopDomainName = $shop->shop;

            $template = \App\Models\MailTemplate::active()
                ->where('slug', 'app-uninstalled')
                ->first();
            if ($template && !empty($recipientEmail)) {
                dispatch(function () use ($template, $shopDomainName, $recipientEmail) {
                    app(\App\Services\EmailService::class)
                        ->sendDynamicEmail($template, (object) [
                            'name' => $shopDomainName,
                            'first_name' => explode('.', $shopDomainName)[0],
                            'email' => $recipientEmail
                        ]);
                });
            }

            // Capture latest activation details before clearing
            $previousDetails = $shop->previous_activation_details;
            if (!empty($shop->shop_name) || !empty($shop->email)) {
                $previousDetails = [
                    'shop_name' => $shop->shop_name,
                    'email' => $shop->email,
                    'saved_at' => now()->toIso8601String(),
                ];
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($shop, $previousDetails) {
                $shop->update([
                    'is_active' => 0,
                    'access_token' => null,
                    'shop_name' => null,
                    'email' => null,
                    'previous_activation_details' => $previousDetails,
                    'shopify_connection_status' => 'uninstalled',
                    'store_status' => 'uninstalled',
                ]);
            });

            Log::info('App uninstalled handled', [
                'shop' => $shopDomain,
                'shop_id' => $shop->id,
            ]);
            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('UNINSTALL ERROR', [
                'error' => $e->getMessage()
            ]);
            return response('Error', 500);
        }
    }

    // testing static amazon listing
    public function getAmazonSchema(Request $request)
    {
        $shopModel = getActiveShopModel($request);
        if (!$shopModel) {
            return response()->json(['error' => 'Shop not found'], 403);
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
                'KEYBOADRS',  //   change category here
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
            $shopModel = getActiveShopModel($request);
            if (!$shopModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found'
                ], 403);
            }
            $amazonService = new \App\Services\AmazonService();
            $creds = $amazonService->getDbCredentials($shopModel);
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
                ], 403);
            }
            // Collect all matched type names
            $matchedTypes = collect($productTypes)
                ->pluck('name')
                ->filter()
                ->values()
                ->toArray();

            $matchedType = $matchedTypes[0];  // primary match
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
                    'attribute' => $name,
                    'type' => $prop['type'] ?? ($prop['items']['type'] ?? 'object'),
                    'required' => isset($prop['minItems']) && $prop['minItems'] > 0,
                    'enum_values' => $prop['items']['properties']['value']['enum']
                        ?? $prop['properties']['value']['enum']
                        ?? null,
                    'description' => $prop['description'] ?? null,
                ];
            })->values()->toArray();
            return response()->json([
                'success' => true,
                'searched_keyword' => $keyword,
                'matched_product_type' => $matchedType,
                'all_matched_types' => $matchedTypes,
                'total_attributes' => count($attributes),
                'attributes' => $attributes,
                // Full raw schema if you need to inspect everything
                'raw_schema' => $schemaJson,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    // ytest function
    public function getUnitCountSchema(Request $request)
    {
        // Schema URL jo upar mili thi
        $schemaUrl = 'https://selling-partner-definitions-prod-iad.s3.amazonaws.com/schema/HEADPHONES.json/jXX6LRGtwa9PpAYd2AQCyQ%253D%253D?X-Amz-Security-Token=IQoJb3JpZ2luX2VjEKz%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCXVzLWVhc3QtMSJGMEQCIBRvb8CochetD7WR2Semgxp%2BUKc487piUDTInjlRqPkLAiBLAi4DtE%2FzV0WWsHwz2LYRN4%2BEHgc%2FOvRfwrrqPA1F3yrJBAh1EAMaDDUzNTc4OTgyMzgwOCIMxWQ6qA3PSR31lrVyKqYESqXfFZm40qe6QciT%2BtalSH%2FyNG2xic0oZi4Q1ECxUz2t8fXl%2FXMh%2BZnxcHCqnkgXvuRfeELhOQlr5vBRaDWjSQDujPXXtM0kmJbz48Ywmue5PgrxMH2E%2BLCtgOnC5%2FwNuOROoWS0RU%2BSi5u0sPj%2B75DcA3ER84%2FQMgMkBf1ijaLWZJajW0OzDVI53JEruXjX7CZuXMn2aSb9Nfsh8liRWzclSop5zAn96Zk9ODjR1kM0qgE9Gjpf4r7XfGiLHEvbrCe%2Bdk2TahkW1zky%2BraPDFw7UdR7mU7MEmrxnEpzuRwKYZzLC67o%2FLcS02YkR1sbuA%2FoZNEWoYSVk1w6urrGDtQ4cyk0xcZLvqtcSjXMBWf9vKk8eHD4rz6tb4a4NFzyURV2bgUwxaMDMQhcXbWp%2FFLpmC%2FEIfkeSk3I8%2FI6zGmgWwxc7lo780dD%2FGL6zdoq2et8QzNZpt4a6R3pJu%2FnHj4prztBE0M3O32BWnVOFzMIzr903NV%2BdqDe1%2F06PAGhnYVVtnCVrD553SV5AXTOOWNFR5c8lnLfBGYiXk5XiKdW5O%2FiCDFd3IfSZw9duUP46F0csHj8kA5vv3fStqc1Urrmrd8jUJNGgnJDrn31fxk1TR8Rp52FbEbS1Z2vecLaHd6256B3jNOHMMXRftBojXGB64U5MK8YidYRoI1uYtTOs9JXqmcNhNEg1oDs%2Fj3%2Bk5lQhF208zfmjA53aM9mP0SZ3Pz%2BljDlyKvPBjqoAdTJnbJ%2FYK97fusffOYlCp%2FwFf%2FVZNEx1C09LMunTGV8yOQZN05SzMkE0B5HxVyJl%2F%2BK5zU2ThNsB%2BtYJ7caLdQfG%2Bz%2FBlHmG7DePHPazECJysb3QPyRIyqp%2FeXYdhvN8CdVqI8Xioo7SezxZoVmUjUkP7cpVtBQ8SSPxQjRnfnl3xI%2Bbin1D9FMARhrfsx1yT1FWkEM2LkoeqKf6e4ifxoNhzKsMtmulA%3D%3D&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20260424T052339Z&X-Amz-SignedHeaders=host&X-Amz-Credential=ASIAXZP4P45AOD3WNEMG%2F20260424%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Expires=604800&X-Amz-Signature=80d1d96f0062ef09ac0a2c4a72d52503ff32330596a92c88f0a3a5aa9be0e6d5';  // poori URL paste karo
        $schema = file_get_contents($schemaUrl);
        $schema = json_decode($schema, true);
        // unit_count dhundo
        $unitCount = $schema['properties']['unit_count'] ?? 'NOT FOUND';
        return response()->json(['unit_count' => $unitCount]);
    }

    public function testSchemaBasedStatic(Request $request)
    {
        $shopModel = getActiveShopModel($request);
        if (!$shopModel) {
            return response()->json(['error' => 'Shop not found'], 403);
        } else {
            return response()->json([
                'sku' => 'test-sku-123',
                'status' => 200,
                'response' => [],
            ]);
        }
        $amazonService = new \App\Services\AmazonService();
    }

    private function UploadImageProvideUrl($request)
    {
        $paths = [];
        $existing = $request->input('existing_images', []);
        if (is_array($existing)) {
            foreach ($existing as $imgUrl) {
                if (is_string($imgUrl) && trim($imgUrl) !== '') {
                    $paths[] = trim($imgUrl);
                }
            }
        }
        $variantsInput = $request->input('variants', []);
        if (is_array($variantsInput)) {
            foreach ($variantsInput as $v) {
                if (!empty($v['image']) && is_string($v['image']) && filter_var($v['image'], FILTER_VALIDATE_URL)) {
                    $paths[] = trim($v['image']);
                }
            }
        }
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            foreach ($images as $image) {
                if ($image->isValid()) {
                    $path = $image->store('uploads', 'public');
                    $paths[] = asset('storage/' . $path);
                }
            }
        }
        return array_values(array_unique($paths));
    }

    public function syncShopifyToAmazon(Request $request, $id)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect('/products')->with('error', 'No shop connected.');
        }

        try {
            $locationId = $this->getSelectedShopifyLocationId($shopModel);
            $shopifyService = new ShopifyService($shopModel->shop, $shopModel->access_token);
            $syncResult = $shopifyService->singleProductSync($shopModel, $id, $locationId);

            if (empty($syncResult['success']) || empty($syncResult['product'])) {
                return back()->with('error', $syncResult['error'] ?? 'Product not found');
            }

            $product = $syncResult['product'];
            $product_type = $product['product_type'] ?? '';

            $dbProduct = \App\Models\Product::where('shopify_id', $id)
                ->where('shop_id', $shopModel->id)
                ->first();
            $amazonData = null;

            $mapper = new ShopifyAmazonMapper();
            $mappedproduct = $mapper->map($product);
            $mappedproduct['shopify_inventory_item_id'] = $product['variants'][0]['inventory_item_id'] ?? '';
            if (isset($dbProduct) && ($dbProduct->sub_category_id != null)) {
                $category = Category::where('id', $dbProduct->sub_category_id)->first();

                if ($category) {
                    $pschema = ProductSchema::where('product_type', $category->category)->first();
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

            $product_id = $updatesync->productstoreAmazon($mappedproduct, $schema_id);

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
                'client_id' => AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key')),
                'client_secret' => AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret')),
                'grant_type' => 'refresh_token',
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
            $query
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name'])
        );
    }
}
