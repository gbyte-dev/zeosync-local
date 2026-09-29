<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Determine whether the given shop currently has an active, valid entitlement
     * (including active trials and paid subscriptions scheduled for cancellation with a future period end).
     *
     * @param int|Shop $shop
     * @return bool
     */
    public function hasActiveEntitlement(int|Shop $shop): bool
    {
        return $this->getActiveSubscription($shop) !== null;
    }

    /**
     * Retrieve the currently valid ShopSubscription for the given shop,
     * enforcing lifecycle transitions (e.g. marking expired trials or expired periods).
     *
     * @param int|Shop $shop
     * @return ShopSubscription|null
     */
    public function getActiveSubscription(int|Shop $shop): ?ShopSubscription
    {
        $shopId = $shop instanceof Shop ? $shop->id : (int) $shop;

        $subscription = ShopSubscription::with('plan')
            ->where('shop_id', $shopId)
            ->latest('id')
            ->first();

        if (!$subscription) {
            return null;
        }

        // 1. Trial Evaluation (Isolated from paid cancellation logic)
        if ((int) $subscription->is_trial === 1 || $subscription->status === 'trialing') {
            $trialEndsAt = $subscription->trial_ends_at
                ? Carbon::parse($subscription->trial_ends_at)
                : null;

            if ($trialEndsAt && $trialEndsAt->isFuture()) {
                return $subscription;
            }

            // Trial expired
            if (in_array($subscription->status, ['trialing', 'active'], true)) {
                Log::info('TRIAL SUBSCRIPTION EXPIRED -> UPDATING DB', [
                    'shop_id' => $shopId,
                    'subscription_id' => $subscription->id,
                ]);

                $subscription->update([
                    'status' => 'expired',
                    'ended_at' => now(),
                    'trial_used' => 1,
                    'is_trial' => 0,
                ]);
            }

            return null;
        }

        // 2. Paid Subscription Evaluation
        $currentPeriodEnd = $subscription->current_period_end
            ? Carbon::parse($subscription->current_period_end)
            : null;

        if ($currentPeriodEnd) {
            if ($currentPeriodEnd->isFuture()) {
                // Subscription is active or scheduled for cancellation with a valid future period
                if (in_array($subscription->status, ['active', 'accepted', 'cancelled', 'pending_cancel'], true)) {
                    return $subscription;
                }
            } else {
                // Period end has passed -> mark expired if not already
                if (in_array($subscription->status, ['active', 'accepted', 'cancelled', 'pending_cancel'], true)) {
                    Log::info('PAID SUBSCRIPTION PERIOD EXPIRED -> UPDATING DB', [
                        'shop_id' => $shopId,
                        'subscription_id' => $subscription->id,
                        'current_period_end' => $subscription->current_period_end,
                    ]);

                    $subscription->update([
                        'status' => 'expired',
                        'ended_at' => now(),
                    ]);
                }

                return null;
            }
        } else {
            // Period end is null (e.g. pending confirmation or instant active without period end yet)
            if (in_array($subscription->status, ['active', 'accepted'], true)) {
                return $subscription;
            }
        }

        return null;
    }

    /**
     * Backward-compatible alias for hasActiveEntitlement.
     *
     * @param int|Shop $shopId
     * @return bool
     */
    public function isActive(int|Shop $shopId): bool
    {
        return $this->hasActiveEntitlement($shopId);
    }

    /**
     * Check if the subscription is in a scheduled cancellation state (paid period still active).
     *
     * @param ShopSubscription|null $subscription
     * @return bool
     */
    public function isCancellationScheduled(?ShopSubscription $subscription): bool
    {
        if (!$subscription || (int) $subscription->is_trial === 1) {
            return false;
        }

        $hasFuturePeriod = $subscription->current_period_end
            && Carbon::parse($subscription->current_period_end)->isFuture();

        $isMarkedCancelled = $subscription->cancelled_at !== null
            || in_array($subscription->status, ['cancelled', 'pending_cancel'], true);

        return $hasFuturePeriod && $isMarkedCancelled;
    }
}

