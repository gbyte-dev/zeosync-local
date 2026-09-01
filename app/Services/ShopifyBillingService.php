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
        Log::info('FETCH SUBSCRIPTION START', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
            'has_access_token' => !empty($shop->access_token),
            'access_token_last4' => $shop->access_token
                ? substr($shop->access_token, -4)
                : null,
        ]);

        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
query AppSubscriptions {
    currentAppInstallation {
        allSubscriptions(first: 20, reverse: true) {
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

        Log::info('SHOPIFY RAW GRAPHQL RESPONSE', [
            'shop_id' => $shop->id,
            'has_data' => isset($response['data']),
            'has_errors' => !empty($response['errors']),
            'response' => $response,
        ]);

        if (!empty($response['errors'])) {
            Log::error('SHOPIFY SUBSCRIPTION GRAPHQL ERROR', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'errors' => $response['errors'],
            ]);

            return null;
        }

        $edges = data_get(
            $response,
            'data.currentAppInstallation.allSubscriptions.edges',
            []
        );

        Log::info('SHOPIFY RAW SUBSCRIPTION EDGES', [
            'shop_id' => $shop->id,
            'edge_count' => count($edges),
            'edges' => $edges,
        ]);

        $subscriptions = collect($edges)
            ->pluck('node')
            ->map(function (array $node) {
                $normalized = $this->normalizeSubscription($node);

                Log::info('SHOPIFY SUBSCRIPTION NORMALIZED', [
                    'raw_gid' => $node['id'] ?? null,
                    'raw_name' => $node['name'] ?? null,
                    'raw_status' => $node['status'] ?? null,
                    'normalized' => $normalized,
                ]);

                return $normalized;
            })
            ->filter()
            ->values();

        Log::info('SHOPIFY ALL NORMALIZED SUBSCRIPTIONS', [
            'shop_id' => $shop->id,
            'count' => $subscriptions->count(),
            'subscriptions' => $subscriptions->map(function ($subscription) {
                return [
                    'gid' => $subscription['gid'] ?? null,
                    'name' => $subscription['name'] ?? null,
                    'status' => $subscription['status'] ?? null,
                    'test' => $subscription['test'] ?? null,
                    'created_at' => $subscription['created_at'] ?? null,
                    'current_period_end' =>
                    $subscription['current_period_end'] ?? null,
                    'billing_interval' =>
                    $subscription['billing_interval'] ?? null,
                    'amount' => $subscription['amount'] ?? null,
                    'currency_code' =>
                    $subscription['currency_code'] ?? null,
                ];
            })->all(),
        ]);

        $activeCandidates = $subscriptions
            ->filter(function (array $subscription) {
                $status = $subscription['status'] ?? null;
                $isActive = $this->isActivatedStatus($status);

                Log::info('SUBSCRIPTION STATUS CHECK', [
                    'gid' => $subscription['gid'] ?? null,
                    'name' => $subscription['name'] ?? null,
                    'status' => $status,
                    'is_activated_status' => $isActive,
                ]);

                return $isActive;
            })
            ->sortByDesc(function (array $subscription) {
                return $subscription['created_at']?->getTimestamp() ?? 0;
            })
            ->values();

        Log::info('SHOPIFY ACTIVE CANDIDATES', [
            'shop_id' => $shop->id,
            'count' => $activeCandidates->count(),
            'candidates' => $activeCandidates->map(function ($subscription) {
                return [
                    'gid' => $subscription['gid'] ?? null,
                    'name' => $subscription['name'] ?? null,
                    'status' => $subscription['status'] ?? null,
                    'created_at' => $subscription['created_at'] ?? null,
                ];
            })->all(),
        ]);

        $activeSubscription = $activeCandidates->first();

        $latestSubscription = $subscriptions
            ->sortByDesc(function (array $subscription) {
                return $subscription['created_at']?->getTimestamp() ?? 0;
            })
            ->first();

        $selectedSubscription = $activeSubscription ?? $latestSubscription;

        Log::info('SHOPIFY SUBSCRIPTION CANDIDATES', [
            'shop_id' => $shop->id,

            'active_gid' => $activeSubscription['gid'] ?? null,
            'active_name' => $activeSubscription['name'] ?? null,
            'active_status' => $activeSubscription['status'] ?? null,
            'active_created_at' => $activeSubscription['created_at'] ?? null,

            'latest_gid' => $latestSubscription['gid'] ?? null,
            'latest_name' => $latestSubscription['name'] ?? null,
            'latest_status' => $latestSubscription['status'] ?? null,
            'latest_created_at' => $latestSubscription['created_at'] ?? null,
        ]);

        Log::info('SHOPIFY SUBSCRIPTION FINAL SELECTION', [
            'shop_id' => $shop->id,
            'selected_gid' => $selectedSubscription['gid'] ?? null,
            'selected_name' => $selectedSubscription['name'] ?? null,
            'selected_status' => $selectedSubscription['status'] ?? null,
            'selected_created_at' =>
            $selectedSubscription['created_at'] ?? null,
            'selection_source' => $activeSubscription
                ? 'ACTIVE'
                : ($latestSubscription ? 'LATEST_FALLBACK' : 'NONE'),
        ]);

        $targetGid = 'gid://shopify/AppSubscription/36927078716';

        $targetSubscription = $subscriptions->first(
            fn(array $subscription) => ($subscription['gid'] ?? null) === $targetGid
        );

        Log::info('TARGET NEW SUBSCRIPTION CHECK', [
            'shop_id' => $shop->id,
            'target_gid' => $targetGid,
            'found' => $targetSubscription !== null,
            'target_subscription' => $targetSubscription,
            'target_status' => $targetSubscription['status'] ?? null,
            'target_created_at' =>
            $targetSubscription['created_at'] ?? null,
        ]);

        return $selectedSubscription;
    }
    public function isActivatedStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['ACTIVE', 'ACCEPTED'], true);
    }

    private function persistSubscription(
        Shop $shop,
        array $shopifySubscription,
        ?ShopSubscription $localSubscription = null
    ): ShopSubscription {
        Log::info('TEST PERSIST START', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
            'incoming_gid' => $shopifySubscription['gid'] ?? null,
            'incoming_status' => $shopifySubscription['status'] ?? null,
            'incoming_name' => $shopifySubscription['name'] ?? null,
            'local_plan_id' => $localSubscription?->plan_id,
            'local_status' => $localSubscription?->status,
            'local_gid' => $localSubscription?->shopify_subscription_gid,
        ]);

        /*
     * TEST ONLY:
     * Keep only the subscription -> plan resolution -> DB persistence.
     * All custom-plan/trial-specific logic is intentionally bypassed.
     */

        $plan = $this->resolvePlan(
            $shopifySubscription,
            $localSubscription
        );

        Log::info('TEST RESOLVE PLAN', [
            'shop_id' => $shop->id,
            'incoming_gid' => $shopifySubscription['gid'] ?? null,
            'incoming_status' => $shopifySubscription['status'] ?? null,
            'incoming_name' => $shopifySubscription['name'] ?? null,
            'resolved_plan_id' => $plan?->id,
            'resolved_plan_name' => $plan?->name,
            'local_plan_id' => $localSubscription?->plan_id,
        ]);

        $status = strtolower(
            (string) ($shopifySubscription['status'] ?? 'pending')
        );

        $gid = $shopifySubscription['gid'] ?? null;

        if (!$gid) {
            Log::error('TEST PERSIST ABORTED - NO SHOPIFY GID', [
                'shop_id' => $shop->id,
                'shop' => $shop->shop,
                'subscription' => $shopifySubscription,
            ]);

            return $localSubscription
                ?? throw new \RuntimeException('Shopify subscription GID is missing.');
        }

        $finalPlanId = $plan?->id ?? $localSubscription?->plan_id;

        Log::info('TEST FINAL UPDATE DATA', [
            'shop_id' => $shop->id,
            'shop' => $shop->shop,
            'shopify_gid' => $gid,
            'shopify_status' => $status,
            'resolved_plan_id' => $plan?->id,
            'final_plan_id' => $finalPlanId,
        ]);

        $subscription = ShopSubscription::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'plan_id' => $finalPlanId,
                'shopify_subscription_gid' => $gid,
                'status' => $status,
                'price' => $shopifySubscription['amount'] ?? 0,
                'billing_interval' => $shopifySubscription['billing_interval']
                    ?? 'EVERY_30_DAYS',
                'currency_code' => $shopifySubscription['currency_code']
                    ?? config('services.shopify.billing.currency', 'USD'),
                'started_at' => $shopifySubscription['created_at'] ?? null,
                'current_period_end' => $shopifySubscription['current_period_end'] ?? null,
            ]
        );

        Log::info('TEST PERSIST COMPLETE', [
            'shop_id' => $subscription->shop_id,
            'incoming_gid' => $gid,
            'saved_gid' => $subscription->shopify_subscription_gid,
            'incoming_status' => $status,
            'saved_status' => $subscription->status,
            'resolved_plan_id' => $plan?->id,
            'saved_plan_id' => $subscription->plan_id,
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
