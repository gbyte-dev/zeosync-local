<?php

namespace App\Services;

use Stripe\StripeClient;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\ShopifySubscription;
use App\Models\MailTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiErrorException;
use App\Models\ShopSubscription;
use App\Mail\PlanActivatedMail;
use App\Mail\SubscriptionCancelledMail;
use Illuminate\Support\Facades\DB;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct(StripeClient $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Create a new customer in Stripe
     */
    public function createCustomer(array $data): ?object
    {
        try {
            return $this->stripe->customers->create([
                'email' => $data['email'] ?? null,
                'name' => $data['name'] ?? null,
                'metadata' => [
                    'shop_id' => $data['shop_id'] ?? null,
                    'shop_domain' => $data['shop_domain'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe create customer failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return null;
        }
    }

    /**
     * Create a checkout session for subscription
     */
    public function createCheckoutSession(
        Shop $shop,
        array $planData,
        ?ShopifySubscription $shopifySubscription = null
    ): ?object {
        try {
            // Create or get Stripe customer
            $customerId = $shop->stripe_customer_id;

            if (!$customerId) {
                $customer = $this->createCustomer([
                    'email' => $shop->email,
                    'name' => $shop->shop_name,
                    'shop_id' => $shop->id,
                    'shop_domain' => $shop->domain,
                ]);

                if (!$customer) {
                    return null;
                }

                $customerId = $customer->id;
                $shop->update(['stripe_customer_id' => $customerId]);
            }

            return $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $planData['name'] ?? 'Subscription',
                            'description' => $planData['description'] ?? '',
                        ],
                        'unit_amount' => $planData['amount'] * 100, // Convert to cents
                        'recurring' => [
                            'interval' => $planData['interval'] ?? 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $planData['success_url'] ?? route('shopify.dashboard'),
                'cancel_url' => $planData['cancel_url'] ?? route('shopify.plans'),
                'metadata' => [
                    'shop_id' => $shop->id,
                    'plan_id' => $planData['plan_id'] ?? null,
                    'interval' => $planData['interval'] ?? 'month',
                    'shopify_subscription_id' => $shopifySubscription?->id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe checkout session failed', [
                'error' => $e->getMessage(),
                'shop_id' => $shop->id,
                'plan' => $planData
            ]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
            //return null;
        }
    }

    /**
     * Create a one-time payment checkout
     */
    public function createOneTimePayment(Shop $shop, array $paymentData): ?object
    {
        try {
            $customerId = $shop->stripe_customer_id;

            if (!$customerId) {
                $customer = $this->createCustomer([
                    'email' => $shop->email,
                    'name' => $shop->shop_name,
                    'shop_id' => $shop->id,
                    'shop_domain' => $shop->domain,
                ]);

                if (!$customer) {
                    return null;
                }

                $customerId = $customer->id;
                $shop->update(['stripe_customer_id' => $customerId]);
            }

            return $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'line_items' => [[
                    'price_data' => [
                        'currency' => $paymentData['currency'] ?? 'usd',
                        'product_data' => [
                            'name' => $paymentData['name'] ?? 'Payment',
                            'description' => $paymentData['description'] ?? '',
                        ],
                        'unit_amount' => $paymentData['amount'] * 100, // Convert to cents
                    ],
                    'quantity' => $paymentData['quantity'] ?? 1,
                ]],
                'mode' => 'payment',
                'success_url' => $paymentData['success_url'] ?? route('shopify.dashboard'),
                'cancel_url' => $paymentData['cancel_url'] ?? route('shopify.plans'),
                'metadata' => [
                    'shop_id' => $shop->id,
                    'order_id' => $paymentData['order_id'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe one-time payment failed', [
                'error' => $e->getMessage(),
                'shop_id' => $shop->id,
                'payment' => $paymentData
            ]);
            return null;
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $this->stripe->subscriptions->cancel($subscriptionId);
            return true;
        } catch (\Exception $e) {
            Log::error('Stripe cancel subscription failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);
            return false;
        }
    }

    /**
     * Retrieve a subscription
     */
    public function getSubscription(string $subscriptionId): ?object
    {
        try {
            return $this->stripe->subscriptions->retrieve($subscriptionId);
        } catch (\Exception $e) {
            Log::error('Stripe get subscription failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);
            return null;
        }
    }

    /**
     * List customer subscriptions
     */
    public function listCustomerSubscriptions(string $customerId): array
    {
        try {
            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $customerId,
                'status' => 'all',
            ]);

            return $subscriptions->data;
        } catch (\Exception $e) {
            Log::error('Stripe list subscriptions failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId
            ]);
            return [];
        }
    }

    /**
     * Create billing portal session
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): ?object
    {
        try {
            return $this->stripe->billingPortal->sessions->create([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe billing portal failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId
            ]);
            return null;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhook(string $payload, string $sigHeader, string $secret): ?object
    {
        try {
            return \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::error('WEBHOOK VERIFY FAILED', [
                'message' => $e->getMessage(),
                'secret_prefix' => substr($secret, 0, 15),
                'secret_length' => strlen($secret),
            ]);
            Log::error('Stripe webhook verification failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create checkout session and send payment link via email
     * 
     * @param Shop $shop Shop model
     * @param array $paymentData Payment details (name, description, amount, currency, etc.)
     * @param string $toEmail Email address to send payment link
     * @param string|null $templateName Optional mail template name to use
     * @return array ['success' => bool, 'url' => string|null, 'message' => string]
     */
    public function createCheckoutAndSendEmail(
        Shop $shop,
        array $paymentData,
        string $toEmail,
        ?string $templateName = null
    ): array {
        try {

            // Get / Create customer
            $customerId = $shop->stripe_customer_id;

            if (!$customerId) {
                $customer = $this->createCustomer([
                    'email'       => $shop->email,
                    'name'        => $shop->shop_name,
                    'shop_id'     => $shop->id,
                    'shop_domain' => $shop->domain,
                ]);

                if (!$customer) {
                    return [
                        'success' => false,
                        'url' => null,
                        'message' => 'Unable to create Stripe customer.',
                    ];
                }

                $customerId = $customer->id;

                $shop->update([
                    'stripe_customer_id' => $customerId
                ]);
            }

            $pending = ShopifySubscription::where('shop_id', $shop->id)
                ->where('status', 'pending')
                ->first();

            if ($pending) {
                $pending->delete();
            }

            // Create pending subscription FIRST
            $shopifySubscription = ShopifySubscription::create([
                'shop_id'                => $shop->id,
                'stripe_customer_id'     => $customerId,
                'stripe_subscription_id' => null,
                'stripe_price_id'        => null,
                'stripe_product_id'      => null,
                'status'                 => 'pending',
                'payment_status'         => 'pending',
                'quantity'               => $paymentData['quantity'] ?? 1,
            ]);

            Log::info('Pending ShopifySubscription created', [
                'subscription_id' => $shopifySubscription->id,
            ]);

            // NOW create checkout session
            $session = $this->createCheckoutSessionGeneric(
                $shop,
                $paymentData,
                $shopifySubscription
            );

            if (is_object($session) && method_exists($session, 'getStatusCode')) {

                $shopifySubscription->delete();

                return [
                    'success' => false,
                    'url' => null,
                    'message' => 'Failed to create checkout session.',
                ];
            }

            if (!$session || empty($session->url)) {

                $shopifySubscription->delete();

                return [
                    'success' => false,
                    'url' => null,
                    'message' => 'Failed to create checkout session.',
                ];
            }

            Log::info('Checkout session created', [
                'session_id' => $session->id,
                'subscription_id' => $shopifySubscription->id,
            ]);

            $emailData = [
                'shop_name'           => $shop->shop_name,
                'shop_domain'         => $shop->domain,
                'customer_email'      => $shop->email,
                'payment_name'        => $paymentData['name'] ?? 'Payment',
                'payment_description' => $paymentData['description'] ?? '',
                'payment_amount'      => $paymentData['amount'] ?? 0,
                'payment_currency'    => strtoupper($paymentData['currency'] ?? 'USD'),
                'payment_url'         => $session->url,
                'session_id'          => $session->id,
                'subscription_id'     => $shopifySubscription->id,
            ];

            if ($templateName) {

                $template = MailTemplate::where('slug', $templateName)
                    ->where('is_active', true)
                    ->first();

                if ($template) {
                    $this->sendTemplateEmail($toEmail, $template, $emailData);
                } else {
                    $this->sendDefaultPaymentEmail($toEmail, $emailData);
                }
            } else {

                $this->sendDefaultPaymentEmail($toEmail, $emailData);
            }

            return [
                'success' => true,
                'url' => $session->url,
                'message' => 'Payment link created successfully.',
            ];
        } catch (\Throwable $e) {

            Log::error('Create checkout failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'url' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create generic checkout session (subscription or one-time)
     */
    private function createCheckoutSessionGeneric(
        Shop $shop,
        array $paymentData,
        ShopifySubscription $shopifySubscription
    ): ?object {
        // Create or get Stripe customer
        $customerId = $shop->stripe_customer_id;

        if (!$customerId) {
            $customer = $this->createCustomer([
                'email' => $shop->email,
                'name' => $shop->shop_name,
                'shop_id' => $shop->id,
                'shop_domain' => $shop->domain,
            ]);

            if (!$customer) {
                return null;
            }

            $customerId = $customer->id;
            $shop->update(['stripe_customer_id' => $customerId]);
        }

        // Use existing Stripe price_id if provided, otherwise create dynamic price
        if (!empty($paymentData['price_id'])) {
            $lineItem = [
                'price' => $paymentData['price_id'],
                'quantity' => $paymentData['quantity'] ?? 1,
            ];
        } else {
            $lineItem = [
                'price_data' => [
                    'currency' => $paymentData['currency'] ?? 'usd',
                    'product_data' => [
                        'name' => $paymentData['name'] ?? 'Payment',
                        'description' => $paymentData['description'] ?? '',
                    ],
                    'unit_amount' => ($paymentData['amount'] ?? 0) * 100, // Convert to cents
                ],
                'quantity' => $paymentData['quantity'] ?? 1,
            ];

            // Add recurring for subscriptions
            if (($paymentData['mode'] ?? 'payment') === 'subscription') {
                $lineItem['price_data']['recurring'] = [
                    'interval' => $paymentData['interval'] ?? 'monthly',
                ];
            }
        }

        try {
            $sessionConfig = [
                'customer' => $customerId,
                'line_items' => [$lineItem],
                'mode' => $paymentData['mode'] ?? 'payment',
                'success_url' => $paymentData['success_url'] ?? route('shopify.dashboard', ['shop' => $shop->domain]),
                'cancel_url' => $paymentData['cancel_url'] ?? route('shopify.plans', ['shop' => $shop->domain]),
                'metadata' => [
                    'shop_id' => $shop->id,
                    'plan_id' => $paymentData['plan_id'] ?? null,
                    'interval' => $paymentData['interval'] ?? 'month',
                    'shopify_subscription_id' => $shopifySubscription->id,
                ],
            ];

            if (!empty($paymentData['trialdays']) && $paymentData['trialdays'] > 0) {
                $sessionConfig['subscription_data'] = [
                    'trial_period_days' => (int)$paymentData['trialdays'],
                ];
            }

            return $this->stripe->checkout->sessions->create($sessionConfig);
        } catch (ApiErrorException $e) {

            Log::error('Stripe Error', [
                'message' => $e->getMessage(),
                'error' => $e->getError(),
                'type' => $e->getError()->type ?? null,
                'code' => $e->getError()->code ?? null,
                'param' => $e->getError()->param ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getError()->message ?? $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send email using mail template
     */
    private function sendTemplateEmail(string $toEmail, MailTemplate $template, array $data): void
    {
        $subject = $this->replaceVariables($template->subject, $data);
        $body = $this->replaceVariables($template->body, $data);

        Mail::send([], [], function ($message) use ($toEmail, $subject, $body) {
            $message->to($toEmail)
                ->subject($subject)
                ->html($body);
        });
    }

    /**
     * Send default payment email
     */
    private function sendDefaultPaymentEmail(string $toEmail, array $data): void
    {
        $subject = 'Complete Your Payment - ' . $data['payment_name'];

        $body = "
            <h2>Hello,</h2>
            <p>You have a pending payment for <strong>{$data['payment_name']}</strong>.</p>
            <p><strong>Amount:</strong> {$data['payment_amount']} {$data['payment_currency']}</p>
            <p>{$data['payment_description']}</p>
            <p style='margin: 20px 0;'>
                <a href='{$data['payment_url']}' 
                   style='background: #6772e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                    Complete Payment
                </a>
            </p>
            <p>Or copy this link: {$data['payment_url']}</p>
            <p>Thank you,<br>{$data['shop_name']}</p>
        ";

        Mail::send([], [], function ($message) use ($toEmail, $subject, $body) {
            $message->to($toEmail)
                ->subject($subject)
                ->html($body);
        });
    }

    /**
     * Replace template variables with actual values
     */
    private function replaceVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }


    public function update_shop_subcriptipon($shop_id, $activated_at, $current_period_end)
    {
        if (!$shop_id || !$activated_at || !$current_period_end) {
            Log::error('Plan update failed: Missing parameters');
            return;
        }

        $shopModel = Shop::find($shop_id);

        if (!$shopModel) {
            Log::error('Plan update failed: Shop not found', [
                'shop_id' => $shop_id,
            ]);
            return;
        }

        $subscription = ShopSubscription::firstOrCreate(
            ['shop_id' => $shopModel->id],
            [
                'status' => 'pending',
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | If user requested another plan then make it active
    |--------------------------------------------------------------------------
    */
        if (!empty($subscription->requested_plan_id)) {
            $subscription->plan_id = $subscription->requested_plan_id;
            $subscription->requested_plan_id = null;
        }

        $subscription->status = 'active';
        $subscription->activated_at = $activated_at;
        $subscription->started_at = $activated_at;
        $subscription->current_period_end = $current_period_end;

        $subscription->save();

        Log::info('SHOP SUBSCRIPTION UPDATED', [
            'shop_id' => $shopModel->id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
        ]);

        try {

            $subscription->load('plan');

            Mail::to($shopModel->email)->send(
                new PlanActivatedMail(
                    $shopModel,
                    $subscription->plan
                )
            );

            Log::info('Plan activation mail sent', [
                'shop_id' => $shopModel->id,
            ]);
        } catch (\Exception $e) {

            Log::error('Plan mail failed', [
                'shop_id' => $shopModel->id,
                'message' => $e->getMessage(),
            ]);
        }
    }


    public function cancel_shop_subcriptipon($shopId, $canceled_at, $cancel_reason = null)
    {
        if (empty($shopId) || empty($canceled_at)) {
            Log::error('Subscription cancellation failed: Missing required parameters', [
                'shop_id' => $shopId,
                'cancelled_at' => $canceled_at,
            ]);
            return false;
        }

        DB::beginTransaction();

        try {

            $shop = Shop::find($shopId);

            if (!$shop) {
                throw new \Exception("Shop not found. Shop ID: {$shopId}");
            }

            // Update latest stripe/shopify subscription
            ShopifySubscription::where('shop_id', $shop->id)
                ->latest('id')
                ->first()?->update([
                    'status' => 'canceled',
                    'payment_status' => 'canceled',
                    'canceled_at' => $canceled_at,
                    'cancel_reason' => $cancel_reason,
                    'current_period_end' => $canceled_at,
                ]);

            // Update shop subscription
            $shopSubscription = ShopSubscription::firstOrNew([
                'shop_id' => $shop->id,
            ]);

            $shopSubscription->status = 'canceled';
            $shopSubscription->cancelled_at = $canceled_at;
            $shopSubscription->ended_at = $canceled_at;
            $shopSubscription->current_period_end = $canceled_at;
            $shopSubscription->requested_plan_id = null;

            // Uncomment if you want to move merchant to Free plan
            /*
        $freePlan = Plan::where('slug', 'free')->first();

        if ($freePlan) {
            $shopSubscription->plan_id = $freePlan->id;
            $shopSubscription->price = 0;
            $shopSubscription->billing_cycle_months = 1;
        }
        */

            $shopSubscription->save();

            DB::commit();

            try {
                Mail::to($shop->email)
                    ->send(new SubscriptionCancelledMail($shopSubscription));
            } catch (\Throwable $mailException) {

                Log::warning('Subscription cancellation mail failed', [
                    'shop_id' => $shop->id,
                    'message' => $mailException->getMessage(),
                ]);
            }

            Log::info('Subscription cancelled successfully', [
                'shop_id' => $shop->id,
            ]);

            return true;
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Subscription cancellation failed', [
                'shop_id' => $shopId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return false;
        }
    }

    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): bool
    {
        try {

            $this->stripe->subscriptions->update(
                $subscriptionId,
                [
                    'cancel_at_period_end' => true,
                ]
            );

            Log::info('STRIPE SUBSCRIPTION MARKED FOR CANCELLATION', [
                'subscription_id' => $subscriptionId,
            ]);

            return true;
        } catch (\Exception $e) {

            Log::error('STRIPE CANCEL AT PERIOD END FAILED', [
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
