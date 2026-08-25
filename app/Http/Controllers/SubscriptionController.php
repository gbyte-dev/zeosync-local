<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Store;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Http\Controllers\ShopifyController;
use App\Services\ShopifyBillingService;
use Illuminate\Support\Str;
use App\Services\ShopifyWebhookService;
use App\Services\Billing\BillingProvider;

class SubscriptionController extends ShopifyController
{
    protected ShopifyBillingService $shopifyBilling;
    protected ShopifyWebhookService $shopifyWebhook;

    public function __construct()
    {
        $this->shopifyBilling = app(ShopifyBillingService::class);
        $this->shopifyWebhook = app(ShopifyWebhookService::class);
    }
    public function plans(Request $request)
    {
        $shopModel = $this->getActiveShop($request);
        $activeShop = $shopModel?->shop;
        if (!$shopModel) {
            return redirect($this->shopAwareUrl(
                '/',
                $request->query('shop') ?? $request->input('shop')
            ))->with('error', 'No shop connected.');
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

        // dd([
        //     'request_shop' => $request->query('shop') ?? $request->input('shop'),
        //     'active_shop_id' => $shopModel->id,
        //     'active_shop_domain' => $shopModel->shop,
        //     'custom_plan_id' => $customPlan?->id,
        //     'custom_plan_shop_id' => $customPlan?->shop_id,
        //     'custom_plan_name' => $customPlan?->name,
        // ]);



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
            $subscription = $this->shopifyBilling->syncSubscription(
                $shopModel,
                $subscription
            );
            // force reload relation
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
                'Unable to sync Shopify billing status before rendering plans.',
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
        ) + ['billingProvider' => app(BillingProvider::class)->provider()]);
    }

    public function subscribeToPlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_interval' => 'nullable|integer|in:1,12',
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
        Log::info('TRIAL DAYS DEBUG', [
            'value' => $plan->trial_days,
            'type' => gettype($plan->trial_days),
        ]);
        $existingSubscription = ShopSubscription::where('shop_id', $shopModel->id)->first();
        // If user is currently on trial and selects a paid plan,
        // end the trial immediately.
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
        // if ($plan->is_trial) {
        //     $trialDays = $plan->trial_days ?? 4;
        //     $now = now();
        //     Log::info('TRIAL DAYS DEBUG', [
        //         'value' => $trialDays,
        //         'type' => gettype($trialDays),
        //     ]);
        //     $trialEnd = $now->copy()->addDays($trialDays);
        //     ShopSubscription::updateOrCreate(
        //         ['shop_id' => $shopModel->id],
        //         [
        //             'plan_id' => $plan->id,
        //             'status' => 'trialing',
        //             'price' => 0,
        //             'billing_cycle_months' => 1,
        //             'trial_days' => $trialDays,
        //             'is_trial' => 1,
        //             'trial_used' => 0,
        //             'started_at' => $now,
        //             'activated_at' => $now,
        //             'trial_ends_at' => $trialEnd,
        //             'current_period_end' => $trialEnd,
        //             'ended_at' => $trialEnd,
        //             'shopify_return_url' => null,
        //             'shopify_confirmation_url' => null,
        //         ]
        //     );
        //     return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
        //         ->with('success', 'Trial activated successfully.');
        // }

        if ($plan->is_trial) {
            return $this->redirectToShopifyPricing(
                $shopModel,
                $plan->slug,
                'EVERY_30_DAYS'
            );
        }

        $billingCycleMonths = (int) $request->input('billing_interval', 1);
        $startedAt = now();
        Log::info('BILLING MONTHS DEBUG', [
            'value' => $billingCycleMonths,
            'type' => gettype($billingCycleMonths),
        ]);
        $endedAt = $startedAt->copy()->addMonths($billingCycleMonths);
        $trialDays = (int) ($plan->trial_days ?? 0);
        $trialDays = $plan->is_trial ? $trialDays : 0;
        Log::info('NON TRIAL DEBUG', [
            'trialDays' => $trialDays,
            'trialDaysType' => gettype($trialDays),
            'billingCycleMonths' => $billingCycleMonths,
            'billingType' => gettype($billingCycleMonths),
        ]);
        $trialEndsAt =  $startedAt->copy()->addDays($trialDays);
        if ($trialEndsAt->greaterThan($endedAt)) {
            $trialEndsAt = $endedAt->copy();
        }
        $billingInterval = (int) $request->input('billing_interval', 1);

        $shopifyInterval = $billingInterval === 12
            ? 'ANNUAL'
            : 'EVERY_30_DAYS';

        return $this->redirectToShopifyPricing(
            $shopModel,
            $plan->slug,
            $shopifyInterval
        );
        $subscription = ShopSubscription::firstOrCreate(
            ['shop_id' => $shopModel->id], // Argument 1: Search criteria
            ['plan_id' => $request->plan_id] // Argument 2: Data to add if NOT found
        );

        // Active paid subscription -> create upgrade request
        if ($subscription->status === 'active') {

            Log::info('UPGRADE REQUEST CREATED', [
                'shop' => $shopModel->shop,
                'old_plan' => $subscription->plan_id,
                'new_plan' => $plan->id,
            ]);

            $subscription->update([
                'requested_plan_id' => $plan->id,
            ]);
        } else {

            // New subscription OR Trial upgraded to paid
            $subscription->update([
                'plan_id' => $plan->id,
                'requested_plan_id' => 0,
                'status' => 'pending',
                'price' => $price,
                'billing_cycle_months' => $billingCycleMonths,
                'started_at' => $startedAt,
                'activated_at' => null,
                'trial_ends_at' => null,
                'trial_days' => 0,
                'is_trial' => 0,
                'trial_used' => 1,
                'current_period_end' => $endedAt,
                'ended_at' => $endedAt,
                'shopify_return_url' => route('payment.cancel'),
                'shopify_confirmation_url' => route('payment.success'),
            ]);
        }
        if (!isset($result['url']) || !$result['url']) {
            return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
                ->with('error', 'Failed to generate payment link. Please try again.');
        }

        return redirect($this->shopAwareUrl('/plans', $shopModel->shop))
            ->with([
                'success' => "{$plan->name} plan activation initiated for {$shopModel->shop}",
                'payment_initiated' => true
            ]);
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
            //  SEND EMAIL (DYNAMIC)
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
        $subscription = \App\Models\ShopifySubscription::where('shop_id', $shopModel->id)
            ->latest()->first();

        if (!$subscription) {
            return response()->json(['status' => 'not_found']);
        }
        return response()->json([
            'status' => $subscription->status,
            'payment_status' => $subscription->payment_status
        ]);
    }

    public function paymentSuccessPage(Request $request)
    {
        return view('payment.success', [
            'shop' => $request->shop,
        ]);
    }

    private function redirectToShopifyPricing(
        Shop $shopModel,
        string $planHandle,
        string $billingInterval = 'EVERY_30_DAYS'
    ) {
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

        $billingInterval = strtoupper($billingInterval);

        $pricingUrl = "https://admin.shopify.com/store/{$storeHandle}/charges/{$appHandle}/plans/{$planHandle}?interval={$billingInterval}";

        Log::info('SHOPIFY PLAN REDIRECT', [
            'shop' => $shopModel->shop,
            'store_handle' => $storeHandle,
            'app_handle' => $appHandle,
            'plan_handle' => $planHandle,
            'billing_interval' => $billingInterval,
            'pricing_url' => $pricingUrl,
        ]);

        return response()->view('billing.shopify-redirect', [
            'pricingUrl' => $pricingUrl,
        ]);
    }
}
