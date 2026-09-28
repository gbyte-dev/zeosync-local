<?php

namespace Tests\Feature;

use App\Http\Controllers\ShopifyController;
use App\Http\Middleware\VerifyShopifySubscription;
use App\Models\Shop;
use App\Services\ShopifyBillingService;
use App\Services\ShopifyService;
use App\Services\ShopifyWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class ShopifyAdminApiVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'shopify.api_version' => '2026-07',
            'services.shopify.api_version' => '2026-07',
        ]);
    }

    public function test_shopify_service_get_order_refund_details_uses_canonical_version_not_2024_01(): void
    {
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'order' => [
                        'id' => 'gid://shopify/Order/12345',
                        'name' => '#1001',
                        'createdAt' => '2026-07-01T00:00:00Z',
                        'customer' => ['firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com'],
                        'refunds' => [],
                    ],
                ],
            ], 200),
        ]);

        $service = new ShopifyService('test-shop.myshopify.com', 'shpat_test_token');
        $result = $service->getRefundDetails('12345');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://test-shop.myshopify.com/admin/api/2026-07/graphql.json'
                && !str_contains($request->url(), '2024-01');
        });

        expect($result['data']['order']['id'])->toBe('gid://shopify/Order/12345');
    }

    public function test_shopify_service_graphql_uses_configured_api_version(): void
    {
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => ['shop' => ['name' => 'Test Store']],
            ], 200),
        ]);

        $service = new ShopifyService('test-shop.myshopify.com', 'shpat_test_token');
        $result = $service->graphql('query { shop { name } }');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://test-shop.myshopify.com/admin/api/2026-07/graphql.json';
        });

        expect($result['data']['shop']['name'])->toBe('Test Store');
    }

    public function test_shopify_controller_is_shop_active_uses_canonical_version_not_2025_01(): void
    {
        Http::fake([
            'https://active-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => ['shop' => ['id' => 'gid://shopify/Shop/1', 'name' => 'Active Store']],
            ], 200),
        ]);

        $shop = Shop::create([
            'shop' => 'active-shop.myshopify.com',
            'access_token' => 'shpat_active_token',
            'is_active' => 1,
        ]);

        $controller = app(ShopifyController::class);
        $method = new ReflectionMethod(ShopifyController::class, 'isShopActive');
        $method->setAccessible(true);

        $isActive = $method->invoke($controller, $shop);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://active-shop.myshopify.com/admin/api/2026-07/graphql.json'
                && !str_contains($request->url(), '2025-01');
        });

        expect($isActive)->toBeTrue();
    }

    public function test_shopify_billing_service_uses_canonical_version(): void
    {
        Http::fake([
            'https://billing-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'node' => [
                        'id' => 'gid://shopify/AppSubscription/999',
                        'name' => 'Standard Plan',
                        'status' => 'ACTIVE',
                        'test' => true,
                        'createdAt' => '2026-07-01T00:00:00Z',
                        'currentPeriodEnd' => '2026-08-01T00:00:00Z',
                        'lineItems' => [],
                    ],
                ],
            ], 200),
        ]);

        $shop = Shop::create([
            'shop' => 'billing-shop.myshopify.com',
            'access_token' => 'shpat_billing_token',
            'is_active' => 1,
        ]);

        $billingService = app(ShopifyBillingService::class);
        $result = $billingService->fetchSubscriptionByGid($shop, 'gid://shopify/AppSubscription/999');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://billing-shop.myshopify.com/admin/api/2026-07/graphql.json'
                && !str_contains($request->url(), '2026-01');
        });

        expect($result['status'])->toBe('ACTIVE');
    }

    public function test_shopify_webhook_service_uses_canonical_version(): void
    {
        Http::fake([
            'https://webhook-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'webhookSubscriptionCreate' => [
                        'userErrors' => [],
                        'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/123'],
                    ],
                ],
            ], 200),
        ]);

        $shop = Shop::create([
            'shop' => 'webhook-shop.myshopify.com',
            'access_token' => 'shpat_webhook_token',
            'is_active' => 1,
        ]);

        $webhookService = app(ShopifyWebhookService::class);
        $method = new ReflectionMethod(ShopifyWebhookService::class, 'graphQl');
        $method->setAccessible(true);

        $result = $method->invoke($webhookService, $shop, 'mutation { webhookSubscriptionCreate }');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://webhook-shop.myshopify.com/admin/api/2026-07/graphql.json'
                && !str_contains($request->url(), '2026-01');
        });

        expect($result)->toBeArray();
    }

    public function test_verify_shopify_subscription_middleware_uses_canonical_version(): void
    {
        Http::fake([
            'https://middleware-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'currentAppInstallation' => [
                        'activeSubscriptions' => [
                            [
                                'id' => 'gid://shopify/AppSubscription/1',
                                'name' => 'Growth',
                                'status' => 'ACTIVE',
                                'test' => false,
                                'createdAt' => '2026-07-01T00:00:00Z',
                                'currentPeriodEnd' => '2026-08-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $shop = Shop::create([
            'shop' => 'middleware-shop.myshopify.com',
            'access_token' => 'shpat_middleware_token',
            'is_active' => 1,
        ]);

        $request = Request::create('/dashboard', 'GET', ['shop' => $shop->shop]);
        $request->attributes->set('active_shop_model', $shop);

        $middleware = new VerifyShopifySubscription();
        $response = $middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $req) {
            return $req->url() === 'https://middleware-shop.myshopify.com/admin/api/2026-07/graphql.json'
                && !str_contains($req->url(), '2026-01');
        });

        expect($response->getContent())->toBe('OK');
    }

    public function test_dynamic_configuration_change_updates_all_endpoint_versions(): void
    {
        config([
            'shopify.api_version' => '2027-01',
            'services.shopify.api_version' => '2027-01',
        ]);

        Http::fake([
            'https://dynamic-shop.myshopify.com/admin/api/2027-01/graphql.json' => Http::response([
                'data' => ['shop' => ['name' => 'Dynamic Store']],
            ], 200),
        ]);

        $service = new ShopifyService('dynamic-shop.myshopify.com', 'shpat_dynamic_token');
        $result = $service->graphql('query { shop { name } }');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://dynamic-shop.myshopify.com/admin/api/2027-01/graphql.json';
        });

        expect($result['data']['shop']['name'])->toBe('Dynamic Store');
    }
}
