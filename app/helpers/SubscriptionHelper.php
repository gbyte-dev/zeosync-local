<?php

use App\Models\ShopSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

if (!function_exists('isSubscriptionActive')) {

    function isSubscriptionActive($shopId)
    {

        $subscription = ShopSubscription::where('shop_id', $shopId)
            ->latest()
            ->first();

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

        if ( $subscription->status === 'trialing' &&  $subscription->is_trial) 
        {
            $isExpired = $subscription->trial_ends_at
                ? Carbon::parse($subscription->trial_ends_at)->isPast()
                : true;
        } else {

            $isExpired = $subscription->current_period_end
                ? Carbon::parse($subscription->current_period_end)->isPast()
                : true;
        }

        Log::info('EXPIRY CHECK', [
            'status' => $subscription->status,
            'is_trial' => $subscription->is_trial,
            'trial_ends_at' => $subscription->trial_ends_at,
            'current_period_end' => $subscription->current_period_end,
            'is_expired' => $isExpired,
            'now' => now(),
        ]);

        // Expired → update DB (only once safely)
        if ($isExpired) {

            // only update if still active/trialing (avoid multiple updates)
            if (in_array($subscription->status, $allowedStatuses)) {

                Log::warning('SUBSCRIPTION EXPIRED → UPDATING');

                $subscription->update([
                    'status' => 'expired',
                    'ended_at' => now(),
                    'current_period_end' => now(),
                    'trial_used' => $subscription->is_trial ? 1 : $subscription->trial_used,
                    'is_trial' => 0,
                ]);
            }

            return false;
        }

        Log::info('SUBSCRIPTION ACTIVE OR TRIALING');

        return true;
    }

     function getShopActiveData($shopId)
    {
        $shops = DB::table('shops')->where('shop_id', $shopId)->latest()->first();

        if (!$shops) {
            return false;
        }

        if( $shops->shop_name && $shops->email){
            return true;
        }

        return false;

    }
}
