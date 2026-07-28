<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Models\ShopifySubscription;
use App\Models\Plan;
use App\Models\ShopSubscription;
use App\Services\UserNotificationService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;

class StripeWebhookController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    /**
     * Handle Stripe webhooks
     */
    public function handle(Request $request)
    {
        Log::info('WEBHOOK ENTRY HIT');

        Log::info('WEBHOOK HEADERS', [
            'stripe_signature' => $request->header('Stripe-Signature')
        ]);

        $payload = $request->getContent();
        Log::info('WEBHOOK PAYLOAD', [
            'payload' => $payload
        ]);
        $sigHeader = $request->header('Stripe-Signature');
        $secret = \App\Providers\StripeServiceProvider::getWebhookSecret();
        Log::info('WEBHOOK SECRET DEBUG', [
            'secret_prefix' => substr($secret, 0, 15),
            'secret_length' => strlen($secret),
        ]);

        // Verify webhook signature
        $event = $this->stripeService->verifyWebhook($payload, $sigHeader, $secret);

        Log::info('WEBHOOK VERIFY RESULT', [
            'verified' => !empty($event)
        ]);

        if (!$event) {
            Log::error('Stripe webhook verification failed');
            return response('Invalid signature', 400);
        }

        Log::info('STRIPE EVENT RECEIVED', [
            'type' => $event->type
        ]);

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    Log::info('CHECKOUT SESSION COMPLETED START');
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    Log::info('CHECKOUT SESSION COMPLETED END');
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                default:
                    Log::info('Unhandled webhook type: ' . $event->type);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook handler error', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Error processing webhook', 500);
        }

        return response('Webhook processed', 200);
    }

    /**
     * Handle checkout.session.completed
     */
    private function handleCheckoutSessionCompleted($session): void
    {
        Log::info('CHECKOUT HANDLER START', [
            'session_id'   => $session->id ?? null,
            'customer'     => $session->customer ?? null,
            'subscription' => $session->subscription ?? null,
        ]);

        $shopId = $session->metadata->shop_id ?? null;
        $interval = $session->metadata->interval ?? 'month';
        $subscriptionId = $session->metadata->shopify_subscription_id ?? null;

        if (!$shopId || !$subscriptionId) {
            Log::error('Missing checkout metadata', [
                'shop_id' => $shopId,
                'shopify_subscription_id' => $subscriptionId,
                'session_id' => $session->id,
            ]);
            return;
        }

        $shop = Shop::find($shopId);

        if (!$shop) {
            Log::error('Shop not found', [
                'shop_id' => $shopId,
            ]);
            return;
        }

        $subscription = ShopifySubscription::find($subscriptionId);

        if (!$subscription) {
            Log::error('ShopifySubscription not found', [
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        Log::info('SHOPIFY SUBSCRIPTION FOUND', [
            'id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        Log::info('ALL SUBSCRIPTIONS BEFORE OLD LOOKUP', [
            'shop_id' => $shop->id,
            'subscriptions' => ShopifySubscription::where('shop_id', $shop->id)
                ->orderBy('id')
                ->get([
                    'id',
                    'stripe_subscription_id',
                    'status',
                    'payment_status',
                    'created_at',
                ])
                ->toArray(),
        ]);

        $oldSubscription = ShopifySubscription::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->where('id', '!=', $subscription->id)
            ->whereNotNull('stripe_subscription_id')
            ->latest('id')
            ->first();

        Log::info('OLD SUB LOOKUP RESULT', [
            'old_subscription' => $oldSubscription?->toArray(),
        ]);

        $subscription->update([
            'stripe_customer_id'      => $session->customer,
            'stripe_subscription_id'  => $session->subscription,
            'stripe_price_id'         => $session->line_items->data[0]->price->id ?? null,
            'stripe_product_id'       => $session->line_items->data[0]->price->product ?? null,
            'status'                  => 'active',
            'payment_status'          => 'paid',
            'current_period_start'    => now(),
            'current_period_end'      => now()->addMonths($interval === 'year' ? 12 : 1),
        ]);

        Log::info('SHOPIFY SUBSCRIPTION UPDATED', [
            'id' => $subscription->id,
            'stripe_subscription_id' => $session->subscription,
        ]);

        $this->stripeService->update_shop_subcriptipon(
            $shop->id,
            now(),
            now()->addMonths($interval === 'year' ? 12 : 1)
        );

        Log::info('SHOP SUBSCRIPTION SERVICE COMPLETED', [
            'shop_id' => $shop->id,
        ]);

        if (
            $oldSubscription &&
            $oldSubscription->stripe_subscription_id !== $subscription->stripe_subscription_id
        ) {

            Log::info('OLD ACTIVE SUBSCRIPTION FOUND', [
                'old_row_id' => $oldSubscription->id,
                'old_subscription_id' => $oldSubscription->stripe_subscription_id,
                'new_row_id' => $subscription->id,
                'new_subscription_id' => $subscription->stripe_subscription_id,
            ]);

            $result = $this->stripeService->cancelSubscription(
                $oldSubscription->stripe_subscription_id
            );

            Log::info('OLD SUBSCRIPTION CANCEL RESULT', [
                'success' => $result,
                'subscription_id' => $oldSubscription->stripe_subscription_id,
            ]);
        } else {

            Log::warning('NO PREVIOUS ACTIVE SUBSCRIPTION FOUND', [
                'shop_id' => $shop->id,
                'current_subscription_id' => $subscription->id,
                'current_stripe_subscription_id' => $subscription->stripe_subscription_id,
            ]);
        }

        Log::info('CHECKOUT SESSION COMPLETED PROCESSED', [
            'shop_id' => $shop->id,
            'subscription_row' => $subscription->id,
            'stripe_subscription_id' => $session->subscription,
        ]);
    }

    /**
     * Handle invoice.payment_succeeded
     */
    // private function handleInvoicePaymentSucceeded($invoice): void
    // {
    //     // Try to find by subscription_id first, fallback to customer_id for initial payment
    //     $subscription = ShopifySubscription::where('stripe_subscription_id', $invoice->subscription)->first();

    //     Log::info('WEBHOOK DATA', [
    //         'invoice' => $invoice
    //     ]);

    //     if (!$subscription && $invoice->customer) {
    //         // Fallback: find by customer_id (for initial payment linking)
    //         $subscription = ShopifySubscription::where('stripe_customer_id', $invoice->customer)
    //             ->where('status', 'pending')
    //             ->first();
    //     }

    //     if ($subscription) {
    //         $subscription->update([
    //             'stripe_subscription_id' => $invoice->subscription, // Link if not already
    //             'payment_status' => 'paid',
    //             'current_period_start' => date('Y-m-d H:i:s', $invoice->period_start),
    //             'current_period_end' => date('Y-m-d H:i:s', $invoice->period_end),
    //         ]);
    //         $this->stripeService->update_shop_subcriptipon($subscription->shop_id, $subscription->current_period_start, $subscription->current_period_end);

    //         Log::info('Invoice payment succeeded', [
    //             'subscription_id' => $subscription->id,
    //             'invoice_id' => $invoice->id
    //         ]);
    //     } else {
    //         Log::warning('Invoice payment succeeded but no subscription found', [
    //             'subscription_id' => $invoice->subscription,
    //             'customer_id' => $invoice->customer
    //         ]);
    //     }
    // }

    private function handleInvoicePaymentSucceeded($invoice): void
    {
        Log::info('INVOICE PAYMENT SUCCEEDED', [
            'invoice_id'   => $invoice->id ?? null,
            'subscription' => $invoice->subscription ?? null,
            'customer'     => $invoice->customer ?? null,
        ]);

        $stripeSubscriptionId =
            $invoice->subscription
            ?? data_get($invoice, 'parent.subscription_details.subscription')
            ?? data_get($invoice, 'lines.data.0.parent.subscription_item_details.subscription')
            ?? null;

        Log::info('RESOLVED SUBSCRIPTION', [
            'subscription_id' => $stripeSubscriptionId,
        ]);

        $subscription = null;

        if ($stripeSubscriptionId) {

            $subscription = ShopifySubscription::where(
                'stripe_subscription_id',
                $stripeSubscriptionId
            )->first();

            Log::info('LOOKUP BY STRIPE SUBSCRIPTION', [
                'found' => !is_null($subscription),
                'row_id' => $subscription?->id,
            ]);
        }

        if (!$subscription && $invoice->customer) {

            Log::warning('LOOKUP BY CUSTOMER FALLBACK', [
                'customer' => $invoice->customer,
            ]);

            $subscription = ShopifySubscription::where(
                'stripe_customer_id',
                $invoice->customer
            )
                ->where('status', 'pending')
                ->whereNull('stripe_subscription_id')
                ->oldest('id')
                ->first();

            Log::info('LOOKUP BY CUSTOMER RESULT', [
                'found' => !is_null($subscription),
                'row_id' => $subscription?->id,
            ]);
        }

        if (!$subscription) {

            Log::error('SHOPIFY SUBSCRIPTION NOT FOUND', [
                'invoice_id' => $invoice->id,
                'customer' => $invoice->customer,
                'subscription_id' => $stripeSubscriptionId,
            ]);

            return;
        }

        $line = $invoice->lines->data[0] ?? null;

        if (!$line) {

            Log::error('INVOICE LINE NOT FOUND', [
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        $periodStart = date('Y-m-d H:i:s', $line->period->start);
        $periodEnd   = date('Y-m-d H:i:s', $line->period->end);

        Log::info('UPDATING SHOPIFY SUBSCRIPTION', [
            'row_id' => $subscription->id,
            'shop_id' => $subscription->shop_id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $subscription->update([
            'stripe_subscription_id' => $stripeSubscriptionId,
            'status'                 => 'active',
            'payment_status'         => 'paid',
            'current_period_start'   => $periodStart,
            'current_period_end'     => $periodEnd,
        ]);

        Log::info('SHOPIFY SUBSCRIPTION UPDATED', [
            'row_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        $shopSubscription = ShopSubscription::where(
            'shop_id',
            $subscription->shop_id
        )->first();

        Log::info('SHOP SUBSCRIPTION', [
            'exists' => !is_null($shopSubscription),
            'plan_id' => $shopSubscription?->plan_id,
            'requested_plan_id' => $shopSubscription?->requested_plan_id,
        ]);

        if (
            $shopSubscription &&
            !empty($shopSubscription->requested_plan_id)
        ) {

            $newPlan = Plan::find(
                $shopSubscription->requested_plan_id
            );

            if (!$newPlan) {

                Log::error('REQUESTED PLAN NOT FOUND', [
                    'requested_plan_id' => $shopSubscription->requested_plan_id,
                ]);

                return;
            }

            $price = $shopSubscription->billing_cycle_months == 12
                ? ($newPlan->prices['ANNUAL'] ?? 0)
                : ($newPlan->prices['EVERY_30_DAYS'] ?? 0);

            $shopSubscription->update([
                'plan_id' => $newPlan->id,
                'price' => $price,
                'requested_plan_id' => null,
                'status' => 'active',
            ]);

            Log::info('PLAN SWITCH COMPLETED', [
                'shop_id' => $subscription->shop_id,
                'plan_id' => $newPlan->id,
                'price' => $price,
            ]);
        }

        Log::info('INVOICE PAYMENT COMPLETED', [
            'invoice_id' => $invoice->id,
            'subscription_row_id' => $subscription->id,
        ]);
    }

    /**
     * Handle invoice.payment_failed
     */
    private function handleInvoicePaymentFailed($invoice): void
    {
        // Try to find by subscription_id first, fallback to customer_id
        $subscription = ShopifySubscription::where('stripe_subscription_id', $invoice->subscription)->first();

        if (!$subscription && $invoice->customer) {
            $subscription = ShopifySubscription::where('stripe_customer_id', $invoice->customer)
                ->where('status', 'pending')
                ->first();
        }

        if ($subscription) {
            $subscription->update([
                'stripe_subscription_id' => $invoice->subscription,
                'payment_status' => 'failed',
            ]);

            UserNotificationService::send(
                $subscription->shop_id,
                'payment_failed',
                'Payment Failed',
                'Your subscription payment has failed. Please update your payment method to continue using the service.'
            );

            Log::warning('Invoice payment failed', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id
            ]);
        }
    }

    /**
     * Handle customer.subscription.updated
     */
    private function handleSubscriptionUpdated($subscription): void
    {
        $shopSubscription = ShopifySubscription::where('stripe_subscription_id', $subscription->id)->first();

        if ($shopSubscription) {
            $shopSubscription->update([
                'status' => $subscription->status,
                'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'trial_start' => $subscription->trial_start ? date('Y-m-d H:i:s', $subscription->trial_start) : null,
                'trial_end' => $subscription->trial_end ? date('Y-m-d H:i:s', $subscription->trial_end) : null,
                'cancel_at' => $subscription->cancel_at ? date('Y-m-d H:i:s', $subscription->cancel_at) : null,
            ]);

            Log::info('Subscription updated', [
                'subscription_id' => $shopSubscription->id,
                'status' => $subscription->status
            ]);
        }
    }

    /**
     * Handle customer.subscription.deleted
     */
    private function handleSubscriptionDeleted($subscription): void
    {
        $shopifySubscription = ShopifySubscription::where(
            'stripe_subscription_id',
            $subscription->id
        )->first();

        if (!$shopifySubscription) {

            Log::warning('Subscription not found', [
                'stripe_subscription_id' => $subscription->id,
            ]);

            return;
        }

        if ($shopifySubscription->status === 'canceled') {

            Log::info('Subscription already cancelled', [
                'id' => $shopifySubscription->id,
            ]);

            return;
        }

        // Cancel only this Stripe subscription record
        $shopifySubscription->update([
            'status' => 'canceled',
            'payment_status' => 'canceled',
            'canceled_at' => now(),
            'cancel_reason' => 'Subscription canceled from Stripe',
        ]);

        /*
    |--------------------------------------------------------------------------
    | Check if merchant still has any active subscription
    |--------------------------------------------------------------------------
    */

        $hasActiveSubscription = ShopifySubscription::where('shop_id', $shopifySubscription->shop_id)
            ->where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->where('id', '!=', $shopifySubscription->id)
            ->exists();

        /*
    |--------------------------------------------------------------------------
    | If no active subscription exists then cancel application subscription
    |--------------------------------------------------------------------------
    */

        if (!$hasActiveSubscription) {

            $this->stripeService->cancel_shop_subcriptipon(
                $shopifySubscription->shop_id,
                now(),
                'Subscription canceled by Stripe'
            );

            Log::info('Shop subscription cancelled', [
                'shop_id' => $shopifySubscription->shop_id,
            ]);
        } else {

            Log::info('Old Stripe subscription cancelled. Active subscription still exists.', [
                'shop_id' => $shopifySubscription->shop_id,
            ]);
        }

        Log::info('Stripe subscription deleted webhook processed.', [
            'stripe_subscription_id' => $subscription->id,
            'shopify_subscription_id' => $shopifySubscription->id,
        ]);
    }
}
