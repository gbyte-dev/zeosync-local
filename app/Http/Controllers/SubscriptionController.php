<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Http\Controllers\ShopifyController;
use App\Services\ShopifyBillingService;
use Illuminate\Support\Str;
use App\Services\ShopifyWebhookService;
use App\Services\Billing\BillingManager;
use App\Services\Billing\BillingProvider;
use RuntimeException;

class SubscriptionController extends ShopifyController
{
    protected ShopifyBillingService $shopifyBilling;
    protected ShopifyWebhookService $shopifyWebhook;
    protected BillingManager $billingManager;
    protected BillingProvider $billingProvider;

    public function __construct()
    {
        $this->shopifyBilling = app(ShopifyBillingService::class);
        $this->shopifyWebhook = app(ShopifyWebhookService::class);
        $this->billingManager = app(BillingManager::class);
        $this->billingProvider = app(BillingProvider::class);
    }

    public function plans(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect(
                $this->shopAwareUrl(
                    '/',
                    $request->query('shop') ?? $request->input('shop')
                )
            )->with('error', 'No shop connected.');
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_custom', false)
            ->whereNull('shop_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $customPlan = Plan::query()
            ->where('shop_id', $shopModel->id)
            ->where('is_custom', true)
            ->where('is_active', true)
            ->first();

        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopModel->id)
            ->first();

        Log::info('PLANS PAGE DB FETCH', [
            'shop_id' => $shopModel->id,
            'plan_id' => $subscription?->plan_id,
            'requested_plan_id' => $subscription?->requested_plan_id,
            'status' => $subscription?->status,
            'plan_name' => $subscription?->plan?->name,
        ]);

        try {
            Log::info('PLANS PAGE BEFORE SYNC', [
                'shop_id' => $shopModel->id,
                'plan_id' => $subscription?->plan_id,
                'requested_plan_id' => $subscription?->requested_plan_id,
                'status' => $subscription?->status,
                'plan_name' => $subscription?->plan?->name,
            ]);

            // Use BillingManager so the sync respects the active provider
            $subscription = $this->billingManager->sync($shopModel, $subscription);

            $subscription?->load('plan');
            Log::info('PLANS PAGE AFTER SYNC', [
                'shop_id' => $shopModel->id,
                'plan_id' => $subscription?->plan_id,
                'requested_plan_id' => $subscription?->requested_plan_id,
                'status' => $subscription?->status,
                'plan_name' => $subscription?->plan?->name,
            ]);
        } catch (RuntimeException $exception) {
            Log::warning(
                'Unable to sync billing status before rendering plans.',
                [
                    'shop' => $shopModel->shop,
                    'error' => $exception->getMessage(),
                ]
            );
        }

        $billingOptions = [
            [
                'value' => 'EVERY_30_DAYS',
                'label' => 'Monthly',
                'description' => 'Billed every 30 days'
            ],
            [
                'value' => 'ANNUAL',
                'label' => 'Annual',
                'description' => 'Billed every 365 days'
            ],
        ];

        return view('plans', compact(
            'plans',
            'customPlan',
            'subscription',
            'activeShop',
            'billingOptions'
        ));
    }

    public function subscribeToPlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_interval' => 'nullable|string|in:EVERY_30_DAYS,ANNUAL,1,12',
        ]);

        session([
            'payment_is_iframe' => $request->boolean('is_iframe')
        ]);

        $shopModel = $this->getActiveShop($request);
        if (!$shopModel) {
            return redirect()->back()->with('error', 'No shop connected.');
        }

        $plan = Plan::query()
            ->where('is_active', true)
            ->findOrFail($request->integer('plan_id'));

        Log::info('PLAN DEBUG FULL', [
            'request_plan_id' => $request->plan_id,
            'selected_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'is_trial' => $plan->is_trial,
        ]);

        // Normalize the billing interval: Shopify uses string, Stripe may use months
        $billingInterval = strtoupper((string) $request->input('billing_interval', 'EVERY_30_DAYS'));

        if ($billingInterval === '12' || $billingInterval === 'ANNUAL') {
            $billingInterval = 'ANNUAL';
        } elseif ($billingInterval === '1' || $billingInterval === 'MONTHLY' || $billingInterval === '') {
            $billingInterval = 'EVERY_30_DAYS';
        }

        $existingSubscription = ShopSubscription::where('shop_id', $shopModel->id)->first();

        // If user is currently on trial and selects a paid plan, end the trial immediately.
        if (
            !$plan->is_trial && $existingSubscription &&
            $existingSubscription->status === 'trialing'
        ) {
            $existingSubscription->update([
                'ended_at' => now(),
                'trial_ends_at' => now(),
                'current_period_end' => now(),
                'is_trial' => 0,
                'trial_used' => 1,
            ]);
        }

        // Handle Trial plans directly (same for both providers)
        if ($plan->is_trial) {
            return $this->handleTrial($shopModel, $plan);
        }

        // Delegate to the active billing provider via BillingManager
        $result = $this->billingManager->subscribe($shopModel, $plan, $billingInterval);

        $redirectUrl = $result['redirect'] ?? $this->shopAwareUrl('/plans', $shopModel->shop);

        if (!empty($result['error'])) {
            return redirect($redirectUrl)->with('error', $result['error']);
        }

        if (!empty($result['message'])) {
            return redirect($redirectUrl)->with('success', $result['message']);
        }

        // Shopify flow returns the confirmation URL directly
        return redirect()->away($redirectUrl);
    }

    public function cancel(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        if ($shopModel) {
            //  GET TEMPLATE
            $template = \App\Models\MailTemplate::active()
                ->where('slug', 'payment-cancelled')
                ->first();

            //  SEND EMAIL
            if ($template) {
                app(\App\Services\EmailService::class)
                    ->sendDynamicEmail($template, (object)[
                        'name' => $shopModel->shop,
                        'first_name' => explode('.', $shopModel->shop)[0],
                        'email' => $shopModel->email
                    ]);
            } else {
                \Log::warning('Payment cancel template not found', [
                    'shop' => $shopModel->shop
                ]);
            }

            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('error', 'Payment cancelled.');
        }

        //  fallback (agar shop null ho)
        return redirect('/crm/plans')
            ->with('error', 'Payment cancelled.');
    }

    public function success(Request $request)
    {
        Log::info('PAYMENT SUCCESS HIT', [
            'shop' => $request->shop
        ]);

        $shopModel = $this->getActiveShop($request);
        if ($shopModel) {
            //  GET TEMPLATE
            $template = \App\Models\MailTemplate::active()
                ->where('slug', 'payment-success')
                ->first();

            if ($template) {
                app(\App\Services\EmailService::class)
                    ->sendDynamicEmail($template, (object)[
                        'name' => $shopModel->shop,
                        'first_name' => explode('.', $shopModel->shop)[0],
                        'email' => $shopModel->email
                    ]);
            } else {
                \Log::warning('Payment success template not found', [
                    'shop' => $shopModel->shop
                ]);
            }
        }

        return redirect()->route('payment.success.page', [
            'shop' => $shopModel?->shop,
        ]);
    }

    public function checkStatus(Request $request)
    {
        $shop = $request->get('shop');
        $shopModel = \App\Models\Shop::where('shop', $shop)->first();
        if (!$shopModel) {
            return response()->json(['status' => 'not_found']);
        }

        return response()->json($this->billingManager->checkStatus($shopModel));
    }

    public function paymentSuccessPage(Request $request)
    {
        return view('payment.success', [
            'shop' => $request->shop,
        ]);
    }

    private function handleTrial(Shop $shopModel, Plan $plan)
    {
        $trialDays = $plan->trial_days ?? 4;
        $now = now();
        $trialEnd = $now->copy()->addDays($trialDays);

        ShopSubscription::updateOrCreate(
            ['shop_id' => $shopModel->id],
            [
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'price' => 0,
                'billing_cycle_months' => 1,
                'trial_days' => $trialDays,
                'is_trial' => 1,
                'trial_used' => 0,
                'started_at' => $now,
                'activated_at' => $now,
                'trial_ends_at' => $trialEnd,
                'current_period_end' => $trialEnd,
                'ended_at' => $trialEnd,
                'shopify_return_url' => null,
                'shopify_confirmation_url' => null,
            ]
        );

        return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
            ->with('success', 'Trial activated successfully.');
    }

    private function redirectToShopifyPricing(Shop $shopModel)
    {
        $storeHandle = Str::before($shopModel->shop, '.myshopify.com');
        $appHandle = config('services.shopify.app_handle');

        if (!$appHandle) {
            Log::error('SHOPIFY APP HANDLE MISSING', [
                'shop' => $shopModel->shop,
            ]);

            return redirect()
                ->back()
                ->with('error', 'Shopify billing configuration is incomplete.');
        }

        $pricingUrl = "https://admin.shopify.com/store/{$storeHandle}/charges/{$appHandle}/pricing_plans";

        Log::info('SHOPIFY PRICING REDIRECT', [
            'shop' => $shopModel->shop,
            'store_handle' => $storeHandle,
            'app_handle' => $appHandle,
            'pricing_url' => $pricingUrl,
        ]);

        return redirect()->away($pricingUrl);
    }
}