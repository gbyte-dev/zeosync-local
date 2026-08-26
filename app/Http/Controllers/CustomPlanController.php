<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\CustomPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomPlanController extends Controller
{
    protected CustomPlanService $customPlanService;

    public function __construct()
    {
        $this->customPlanService = app(CustomPlanService::class);
    }

    public function store(Request $request)
    {
        $plan = $this->customPlanService->create($request->all());

        return redirect()
            ->back()
            ->with('success', 'Custom plan created successfully.');
    }

    public function activate(Plan $plan)
    {
        if (!$plan->is_custom || !$plan->shop_id) {
            abort(404);
        }

        $shop = Shop::findOrFail($plan->shop_id);

        DB::beginTransaction();

        try {
            $subscription = ShopSubscription::where('shop_id', $shop->id)
                ->first();

            if (!$subscription) {
                throw new \Exception(
                    'No subscription record found for this shop.'
                );
            }

            $shopifyBillingService = app(
                \App\Services\ShopifyBillingService::class
            );

            $shopifySubscription = $shopifyBillingService
                ->fetchLatestSubscription($shop);

            if ($shopifySubscription) {
                $shopifyGid = $shopifySubscription['gid'] ?? null;

                if (!$shopifyGid) {
                    throw new \Exception(
                        'Active Shopify subscription ID is missing.'
                    );
                }

                $cancelled = $shopifyBillingService->cancelSubscription(
                    $shop,
                    $shopifyGid
                );

                if (!$cancelled) {
                    throw new \Exception(
                        'Unable to cancel the existing Shopify subscription.'
                    );
                }

                $cancelledSubscription =
                    $shopifyBillingService->fetchSubscriptionByGid(
                        $shop,
                        $shopifyGid
                    );

                $shopifyStatus = strtoupper(
                    (string) ($cancelledSubscription['status'] ?? '')
                );

                if ($shopifyStatus !== 'CANCELLED') {
                    throw new \Exception(
                        'Shopify subscription cancellation could not be verified.'
                    );
                }
            }

            $subscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'price' => $plan->price,
                'billing_cycle_months' => 1,
                'billing_interval' => 'EVERY_30_DAYS',
                'currency_code' => 'USD',
                'started_at' => now(),
                'activated_at' => now(),
                'current_period_end' => null,
                'ended_at' => null,
                'cancelled_at' => null,
                'trial_days' => 0,
                'trial_used' => 0,
                'trial_ends_at' => null,
                'is_trial' => false,
                'is_test' => false,
                'shopify_subscription_gid' => null,
                'shopify_confirmation_url' => null,
                'shopify_return_url' => null,
            ]);

            DB::commit();

            Log::info('INTERNAL CUSTOM PLAN ACTIVATED', [
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
            ]);

            return redirect()
                ->back()
                ->with('success', 'Custom plan activated successfully.');
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('INTERNAL CUSTOM PLAN ACTIVATION FAILED', [
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(Plan $plan)
    {
        if (!$plan->is_custom || !$plan->shop_id) {
            abort(404);
        }

        $shop = Shop::findOrFail($plan->shop_id);

        DB::beginTransaction();

        try {
            $subscription = ShopSubscription::where('shop_id', $shop->id)
                ->where('plan_id', $plan->id)
                ->whereIn('status', ['active', 'accepted', 'trialing'])
                ->latest('id')
                ->first();

            if (!$subscription) {
                return redirect()
                    ->back()
                    ->with('error', 'This custom plan is not active.');
            }

            /*
         * Custom plan is internal only.
         * Do NOT call Shopify cancellation here.
         */
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ended_at' => now(),
            ]);

            DB::commit();

            Log::info('Internal Custom Plan Cancelled', [
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
            ]);

            return redirect()
                ->back()
                ->with('success', 'Custom plan cancelled successfully.');
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Internal Custom Plan Cancellation Failed', [
                'shop_id' => $shop->id,
                'plan_id' => $plan->id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Unable to cancel custom plan.');
        }
    }
}
