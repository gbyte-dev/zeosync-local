<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ShopifyBillingService
{
    public function buildReturnUrl(Shop $shop): string
    {
        $path = route('shopify.billing.callback', ['shop' => $shop->shop], false);
        return rtrim($this->publicAppUrl(), '/') . $path;
    }
    public function createSubscription(Shop $shop, Plan $plan, string $billingInterval, string $returnUrl): array
    {
        $billingInterval = strtoupper($billingInterval);
        $amount = $billingInterval === 'ANNUAL'
            ? round((float) $plan->price * 12, 2)
            : round((float) $plan->price, 2);
        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
    mutation AppSubscriptionCreate(
    $name: String!,
    $returnUrl: URL!,
    $lineItems: [AppSubscriptionLineItemInput!]!,
    $trialDays: Int,
    $test: Boolean!,
    $replacementBehavior: AppSubscriptionReplacementBehavior
    ) {
    appSubscriptionCreate(
        name: $name,
        returnUrl: $returnUrl,
        lineItems: $lineItems,
        trialDays: $trialDays,
        test: $test,
        replacementBehavior: $replacementBehavior
    ) {
        userErrors {
        field
        message
        }
        appSubscription {
        id
        name
        status
        }
        confirmationUrl
    }
    }
    GRAPHQL,
            [
                'name' => $plan->name,
                'returnUrl' => $returnUrl,
                // 'trialDays' => (int) config('services.shopify.billing.trial_days', 0),
                'trialDays' => $plan->is_trial
                    ? (int) $plan->trial_days
                    : 0,
                'test' => (bool) config('services.shopify.billing.test', false),
                'replacementBehavior' => 'STANDARD',
                'lineItems' => [[
                    'plan' => [
                        'appRecurringPricingDetails' => [
                            'price' => [
                                'amount' => $amount,
                                'currencyCode' => config('services.shopify.billing.currency', 'USD'),
                            ],
                            'interval' => $billingInterval,
                        ],
                    ],
                ]],
            ]
        );
        $payload = data_get($response, 'data.appSubscriptionCreate');
        $userErrors = data_get($payload, 'userErrors', []);
        if (!empty($userErrors)) {
            $message = collect($userErrors)
                ->pluck('message')
                ->filter()
                ->implode(' ');
            throw new RuntimeException($message !== '' ? $message : 'Shopify returned a billing validation error.');
        }
        $gid = data_get($payload, 'appSubscription.id');
        $confirmationUrl = data_get($payload, 'confirmationUrl');
        if (!$gid || !$confirmationUrl) {
            throw new RuntimeException('Shopify billing response was missing the subscription ID or confirmation URL.');
        }

        Log::info('SHOPIFY BILLING CREATED', [
            'subscription_gid' => $gid,
            'confirmation_url' => $confirmationUrl,
            'return_url' => $returnUrl,
        ]);
        return [
            'subscription_gid' => $gid,
            'confirmation_url' => $confirmationUrl,
            'name' => data_get($payload, 'appSubscription.name', $plan->name),
            'status' => data_get($payload, 'appSubscription.status', 'PENDING'),
            'amount' => $amount,
            'currency_code' => config('services.shopify.billing.currency', 'USD'),
            'billing_interval' => $billingInterval,
        ];
    }
    public function syncSubscription(
        Shop $shop,
        ?ShopSubscription $localSubscription = null
    ): ?ShopSubscription {

        $localSubscription ??= ShopSubscription::with('plan')
            ->where('shop_id', $shop->id)
            ->first();

        $shopifySubscription = $this->fetchLatestSubscription($shop);

        if (!$shopifySubscription) {
            return $localSubscription;
        }

        return $this->persistSubscription(
            $shop,
            $shopifySubscription,
            $localSubscription
        );
    }

    public function syncSubscriptionByGid(
        Shop $shop,
        string $gid,
        ?ShopSubscription $localSubscription = null
    ): ?ShopSubscription {
        $localSubscription ??= ShopSubscription::with('plan')
            ->where('shop_id', $shop->id)
            ->first();

        $shopifySubscription = $this->fetchSubscriptionByGid($shop, $gid);

        if (!$shopifySubscription) {
            Log::error('SHOPIFY SUBSCRIPTION NOT FOUND BY GID', [
                'shop_id' => $shop->id,
                'subscription_gid' => $gid,
            ]);

            return $localSubscription;
        }

        Log::info('SHOPIFY SUBSCRIPTION FOUND BY GID', [
            'shop_id' => $shop->id,
            'subscription_gid' => $gid,
            'name' => $shopifySubscription['name'] ?? null,
            'status' => $shopifySubscription['status'] ?? null,
            'amount' => $shopifySubscription['amount'] ?? null,
        ]);

        return $this->persistSubscription(
            $shop,
            $shopifySubscription,
            $localSubscription
        );
    }

    public function fetchSubscriptionByGid(Shop $shop, string $gid): ?array
    {
        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
    query AppSubscriptionStatus($id: ID!) {
    node(id: $id) {
        ... on AppSubscription {
        id
        name
        status
        test
        createdAt
        currentPeriodEnd
        lineItems {
            id
            plan {
            pricingDetails {
                __typename
                ... on AppRecurringPricing {
                interval
                price {
                    amount
                    currencyCode
                }
                }
            }
            }
        }
        }
    }
    }
    GRAPHQL,
            ['id' => $gid]
        );
        return $this->normalizeSubscription(data_get($response, 'data.node'));
    }
    public function fetchLatestSubscription(Shop $shop): ?array
    {
        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
query AppSubscriptions {
    currentAppInstallation {
        allSubscriptions(first: 50, reverse: true) {
            edges {
                node {
                    id
                    name
                    status
                    test
                    createdAt
                    currentPeriodEnd
                    lineItems {
                        id
                        plan {
                            pricingDetails {
                                __typename
                                ... on AppRecurringPricing {
                                    interval
                                    price {
                                        amount
                                        currencyCode
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
GRAPHQL
        );

        if (!empty($response['errors'])) {
            Log::error('Shopify subscription GraphQL error', [
                'shop_id' => $shop->id,
                'errors' => $response['errors'],
            ]);

            return null;
        }

        $edges = data_get(
            $response,
            'data.currentAppInstallation.allSubscriptions.edges',
            []
        );

        $subscriptions = collect($edges)
            ->pluck('node')
            ->map(fn(array $node) => $this->normalizeSubscription($node))
            ->filter()
            ->values();

        $activeSubscription = $subscriptions
            ->filter(function (array $subscription) {
                return $this->isActivatedStatus(
                    strtoupper((string) ($subscription['status'] ?? ''))
                );
            })
            ->sortByDesc(
                fn(array $subscription) =>
                $subscription['created_at']?->getTimestamp() ?? 0
            )
            ->first();

        $latestSubscription = $subscriptions
            ->sortByDesc(
                fn(array $subscription) =>
                $subscription['created_at']?->getTimestamp() ?? 0
            )
            ->first();

        return $activeSubscription ?? $latestSubscription;
    }
    public function isActivatedStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['ACTIVE', 'ACCEPTED'], true);
    }

    // private function persistSubscription(
    //     Shop $shop,
    //     array $shopifySubscription,
    //     ?ShopSubscription $localSubscription = null
    // ): ShopSubscription {
    //     $plan = $this->resolvePlan(
    //         $shopifySubscription,
    //         $localSubscription
    //     );

    //     $gid = $shopifySubscription['gid'] ?? null;

    //     if (!$gid) {
    //         return $localSubscription
    //             ?? throw new \RuntimeException(
    //                 'Shopify subscription GID is missing.'
    //             );
    //     }

    //     $status = strtolower(
    //         (string) ($shopifySubscription['status'] ?? 'pending')
    //     );

    //     $finalPlanId = $plan?->id ?? $localSubscription?->plan_id;

    //     $subscription = ShopSubscription::updateOrCreate(
    //         [
    //             'shop_id' => $shop->id,
    //         ],
    //         [
    //             'plan_id' => $finalPlanId,
    //             'shopify_subscription_gid' => $gid,
    //             'status' => $status,
    //             'price' => $shopifySubscription['amount'] ?? 0,
    //             'billing_interval' => $shopifySubscription['billing_interval']
    //                 ?? 'EVERY_30_DAYS',
    //             'currency_code' => $shopifySubscription['currency_code']
    //                 ?? config('services.shopify.billing.currency', 'USD'),
    //             'started_at' => $shopifySubscription['created_at'] ?? null,
    //             'current_period_end' =>
    //             $shopifySubscription['current_period_end'] ?? null,
    //         ]
    //     );

    //     return ShopSubscription::query()
    //         ->where('shop_id', $shop->id)
    //         ->first() ?? $subscription;
    // }


    private function persistSubscription(Shop $shop, array $shopifySubscription, ?ShopSubscription $localSubscription = null): ShopSubscription
    {
        Log::info('PERSIST START', [
            'shop_id' => $shop->id,
            'local_plan_id' => $localSubscription?->plan_id,
            'local_requested_plan_id' => $localSubscription?->requested_plan_id,
            'local_status' => $localSubscription?->status,
        ]);

        if (
            $localSubscription &&
            $localSubscription->plan &&
            $localSubscription->plan->is_custom
        ) {
            $shopifyStatus = strtolower(
                (string) ($shopifySubscription['status'] ?? '')
            );

            // Shopify plan is not active yet.
            // Keep the custom plan active.
            if (!$this->isActivatedStatus($shopifyStatus)) {
                Log::info('CUSTOM PLAN KEPT - NO ACTIVE SHOPIFY PLAN', [
                    'shop_id' => $shop->id,
                    'custom_plan_id' => $localSubscription->plan_id,
                    'shopify_status' => $shopifyStatus,
                    'shopify_subscription_gid' => $shopifySubscription['gid'] ?? null,
                ]);

                return $localSubscription;
            }

            // A new real Shopify plan is now active.
            // The custom plan must be replaced by the real plan.
            Log::info('CUSTOM PLAN REPLACED BY SHOPIFY PLAN', [
                'shop_id' => $shop->id,
                'old_custom_plan_id' => $localSubscription->plan_id,
                'shopify_subscription_gid' => $shopifySubscription['gid'] ?? null,
                'shopify_status' => $shopifyStatus,
            ]);
        }
        $gid = $shopifySubscription['gid'] ?? null;

        if (!$gid) {
            return $localSubscription
                ?? throw new RuntimeException(
                    'Shopify subscription GID is missing.'
                );
        }
        $plan = $this->resolvePlan($shopifySubscription, $localSubscription);

        // Already-used trial protection.
        // If Shopify is currently returning the trial plan again,
        // never overwrite the existing local subscription.
        if ($plan?->is_trial && (int) ($localSubscription?->trial_used ?? 0) === 1) {
            Log::warning('SHOPIFY TRIAL REUSE BLOCKED', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'incoming_plan_id' => $plan->id,
                'incoming_plan_name' => $plan->name,
                'local_plan_id' => $localSubscription?->plan_id,
                'local_status' => $localSubscription?->status,
                'trial_used' => $localSubscription?->trial_used,
                'shopify_subscription_gid' => $shopifySubscription['gid'] ?? null,
            ]);

            return $localSubscription;
        }

        Log::info('RESOLVE PLAN RESULT', [
            'resolved_plan_id' => $plan?->id,
            'resolved_plan_name' => $plan?->name,
        ]);
        $startedAt = $shopifySubscription['created_at'] ?? null;
        $isTrial = (bool) ($plan?->is_trial ?? $localSubscription?->is_trial ?? false);

        $trialDays = $isTrial
            ? (int) ($plan?->trial_days ?? $localSubscription?->trial_days ?? 0)
            : 0;

        $trialEndsAt = $isTrial && $startedAt && $trialDays > 0
            ? $startedAt->copy()->addDays($trialDays)
            : null;

        $trialUsed = (int) ($localSubscription?->trial_used ?? 0);

        $storedTrialEndsAt = $localSubscription?->trial_ends_at;

        if (
            $trialUsed === 0 &&
            $storedTrialEndsAt &&
            now()->greaterThanOrEqualTo($storedTrialEndsAt)
        ) {
            $trialUsed = 1;

            Log::info('SHOPIFY TRIAL ENDED - MARKED USED', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'trial_ends_at' => $storedTrialEndsAt,
                'shopify_subscription_gid' => $shopifySubscription['gid'] ?? null,
            ]);
        }

        Log::info('SHOPIFY PERIOD DEBUG', [
            'created_at' => $shopifySubscription['created_at'] ?? null,
            'current_period_end' => $shopifySubscription['current_period_end'] ?? null,
            'trial_days' => $trialDays,
            'is_trial' => $localSubscription?->is_trial,
        ]);
        $currentPeriodEnd = $shopifySubscription['current_period_end'] ?? null;
        $status = strtolower((string) ($shopifySubscription['status'] ?? 'pending'));
        $billingInterval = $shopifySubscription['billing_interval'] ?? 'EVERY_30_DAYS';
        $billingCycleMonths = $billingInterval === 'ANNUAL' ? 12 : 1;
        $amount = $shopifySubscription['amount'] ?? (float) ($plan?->price ?? 0);
        $finalPlanId = $plan?->id ?? $localSubscription?->plan_id;
        Log::info('SHOPIFY SUBSCRIPTION DATA', [
            'gid' => $shopifySubscription['gid'] ?? null,
            'status' => $shopifySubscription['status'] ?? null,
            'billing_interval' => $billingInterval,
            'amount' => $amount,
        ]);
        Log::info('FINAL UPDATE DATA', [
            'shop_id' => $shop->id,
            'final_plan_id' => $finalPlanId,
            'local_plan_id' => $localSubscription?->plan_id,
            'resolved_plan_id' => $plan?->id,
        ]);
        $subscription = ShopSubscription::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'plan_id' => $finalPlanId,
                'shopify_subscription_gid' => $gid,
                'shopify_confirmation_url' => $this->isActivatedStatus($status)
                    ? null
                    : ($localSubscription?->shopify_confirmation_url),
                'shopify_return_url' => $localSubscription?->shopify_return_url
                    ?? $this->buildReturnUrl($shop),
                'status' => $status,
                'price' => $amount,
                'billing_cycle_months' => $billingCycleMonths,
                'billing_interval' => $billingInterval,
                'currency_code' => $shopifySubscription['currency_code']
                    ?? config('services.shopify.billing.currency', 'USD'),
                'trial_days' => $trialDays,
                'is_trial' => $isTrial,
                'trial_used' => $trialUsed,
                'is_test' => (bool) ($shopifySubscription['test']
                    ?? $localSubscription?->is_test
                    ?? false),
                'trial_ends_at' => $trialEndsAt,
                'started_at' => $startedAt,
                'activated_at' => $this->isActivatedStatus($status)
                    ? ($localSubscription?->activated_at ?? $startedAt ?? now())
                    : null,
                'current_period_end' => $currentPeriodEnd,
                'ended_at' => $currentPeriodEnd,
                'cancelled_at' => $status === 'cancelled'
                    ? ($localSubscription?->cancelled_at ?? now())
                    : null,
            ]
        );
        Log::info('PERSIST COMPLETE', [
            'shop_id' => $subscription->shop_id,
            'saved_plan_id' => $subscription->plan_id,
            'saved_status' => $subscription->status,
        ]);
        return $subscription;
    }


    private function resolvePlan(
        array $shopifySubscription,
        ?ShopSubscription $localSubscription = null
    ): ?Plan {
        $name = trim((string) ($shopifySubscription['name'] ?? ''));

        if ($name !== '') {
            $slug = Str::slug($name);

            $plan = Plan::query()
                ->where('is_active', true)
                ->where(function ($query) use ($name, $slug) {
                    $query->where('name', $name)
                        ->orWhere('slug', $slug);
                })
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        $amount = (float) ($shopifySubscription['amount'] ?? 0);

        if ($amount > 0) {
            $monthlyPrice = ($shopifySubscription['billing_interval'] ?? null) === 'ANNUAL'
                ? round($amount / 12, 2)
                : round($amount, 2);

            $plan = Plan::query()
                ->where('is_active', true)
                ->where('price', number_format($monthlyPrice, 2, '.', ''))
                ->orderBy('sort_order')
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        if ($localSubscription?->plan_id) {
            return Plan::find($localSubscription->plan_id);
        }

        return null;
    }
    private function normalizeSubscription(?array $node): ?array
    {
        if (!$node || !isset($node['id'])) {
            return null;
        }
        $recurringDetails = collect($node['lineItems'] ?? [])
            ->pluck('plan.pricingDetails')
            ->first(fn($details) => data_get($details, '__typename') === 'AppRecurringPricing');
        return [
            'gid' => $node['id'],
            'name' => $node['name'] ?? null,
            'status' => $node['status'] ?? null,
            'test' => (bool) ($node['test'] ?? false),
            'created_at' => !empty($node['createdAt']) ? Carbon::parse($node['createdAt']) : null,
            'current_period_end' => !empty($node['currentPeriodEnd']) ? Carbon::parse($node['currentPeriodEnd']) : null,
            'billing_interval' => data_get($recurringDetails, 'interval', 'EVERY_30_DAYS'),
            'amount' => (float) data_get($recurringDetails, 'price.amount', 0),
            'currency_code' => data_get($recurringDetails, 'price.currencyCode', config('services.shopify.billing.currency', 'USD')),
        ];
    }
    private function graphQl(Shop $shop, string $query, array $variables = []): array
    {
        $payload = ['query' => $query];
        if (!empty($variables)) {
            $payload['variables'] = (object) $variables;
        }
        \Log::info('Shopify Token Debug', [
            'shop' => $shop->shop,
            'token' => $shop->access_token,
            'is_null' => is_null($shop->access_token),
            'is_empty' => empty($shop->access_token),
        ]);
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->post(
            sprintf(
                'https://%s/admin/api/%s/graphql.json',
                $shop->shop,
                config('services.shopify.api_version', '2026-01')
            ),
            $payload
        );
        Log::info('SHOPIFY GRAPHQL URL', [
            'url' => sprintf(
                'https://%s/admin/api/%s/graphql.json',
                $shop->shop,
                config('services.shopify.api_version', '2026-01')
            ),
        ]);
        if (!$response->successful()) {

            Log::error('SHOPIFY BILLING FAILED', [
                'shop' => $shop->shop ?? null,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);
            return [
                'errors' => [
                    'message' => 'Shopify billing request failed with HTTP ' . $response->status() . '.',
                ],
            ];
            //  throw new RuntimeException('Shopify billing request failed with HTTP ' . $response->status() . '.');
        }
        $payload = $response->json();
        if (!empty($payload['errors'])) {
            $message = collect($payload['errors'])
                ->map(function ($error) {
                    if (is_array($error)) {
                        return $error['message'] ?? json_encode($error);
                    }
                    return (string) $error;
                })
                ->implode(' ');
            return [
                'errors' => [
                    'message' => $message !== '' ? $message : 'Shopify billing request failed.',
                ],
            ];
        }
        return $payload;
    }
    private function publicAppUrl(): string
    {
        Log::info('URL DEBUG', [
            'shopify_app_url' => config('services.shopify.app_url'),
            'app_url' => config('app.url'),
        ]);
        $appUrl = trim((string) config('services.shopify.app_url'));
        if ($appUrl !== '' && !str_contains($appUrl, 'localhost')) {
            return $appUrl;
        }
        $redirectUri = trim((string) config('services.shopify.redirect_uri'));
        if ($redirectUri !== '') {
            $scheme = parse_url($redirectUri, PHP_URL_SCHEME);
            $host = parse_url($redirectUri, PHP_URL_HOST);
            $port = parse_url($redirectUri, PHP_URL_PORT);
            if ($scheme && $host) {
                return $scheme . '://' . $host . ($port ? ':' . $port : '');
            }
        }
        return $appUrl !== '' ? $appUrl : rtrim((string) config('app.url'), '/');
    }

    public function cancelSubscription(
        Shop $shop,
        string $subscriptionGid
    ): bool {
        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
mutation CancelSubscription(
    $id: ID!,
    $prorate: Boolean!
) {
    appSubscriptionCancel(
        id: $id,
        prorate: $prorate
    ) {
        appSubscription {
            id
            status
        }
        userErrors {
            field
            message
        }
    }
}
GRAPHQL,
            [
                'id' => $subscriptionGid,
                'prorate' => false,
            ]
        );

        $payload = data_get($response, 'data.appSubscriptionCancel');

        $errors = data_get($payload, 'userErrors', []);

        if (!empty($errors)) {

            Log::error('SHOPIFY SUBSCRIPTION CANCEL FAILED', [
                'shop' => $shop->shop,
                'errors' => $errors,
            ]);

            return false;
        }

        Log::info('SHOPIFY SUBSCRIPTION CANCEL SUCCESS', [
            'shop' => $shop->shop,
            'subscription_gid' => $subscriptionGid,
        ]);

        return true;
    }
}
