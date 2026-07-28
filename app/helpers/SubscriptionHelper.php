<?php

use App\Models\ShopSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; 

if (!function_exists('isSubscriptionActive')) {

    function isSubscriptionActive($shopId)
    {
        Log::info('CHECK START', [
            'input_shopId' => $shopId,
            'type' => gettype($shopId)
        ]);

        $subscription = ShopSubscription::where('shop_id', $shopId)
            ->latest()
            ->first();

        Log::info('SUBSCRIPTION FETCHED', [
            'subscription' => $subscription
        ]);

        // ❌ No subscription
        if (!$subscription) {
            Log::warning('NO SUBSCRIPTION FOUND');
            return false;
        }

        //  Allowed statuses
        $allowedStatuses = ['active', 'trialing'];

        Log::info('STATUS CHECK', [
            'status' => $subscription->status,
            'is_allowed' => in_array($subscription->status, $allowedStatuses)
        ]);

        //  Invalid status
        if (!in_array($subscription->status, $allowedStatuses)) {
            Log::warning('STATUS NOT ALLOWED');
            return false;
        }

        //  Expiry check
        $isExpired = false;

        if ($subscription->current_period_end) {
            $isExpired = Carbon::parse($subscription->current_period_end)->isPast();
        }

        Log::info('EXPIRY CHECK', [
            'current_period_end' => $subscription->current_period_end,
            'is_expired' => $isExpired,
            'now' => now()
        ]);

        // Expired → update DB (only once safely)
        if ($isExpired) {

            // only update if still active/trialing (avoid multiple updates)
            if (in_array($subscription->status, $allowedStatuses)) {

                Log::warning('SUBSCRIPTION EXPIRED → UPDATING');

                $subscription->update([
                    'status' => 'expired',
                    'ended_at' => now(),
                    'trial_used' => $subscription->is_trial ? 1 : $subscription->trial_used
                ]);
            }

            return false;
        }

        Log::info('SUBSCRIPTION ACTIVE OR TRIALING');

        return true;
    }
}
