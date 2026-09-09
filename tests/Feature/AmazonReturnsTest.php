<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonService;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Http::fake([
        '*' => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    AdminSetting::forget('SHOPIFY_API_KEY');
    AdminSetting::forget('SHOPIFY_API_SECRET');

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->string('amazon_endpoint')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('return_items')) {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('order_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('status')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('sync_limit')->default(100);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    Shop::truncate();
    ShopSubscription::truncate();
    Plan::truncate();
    Cache::flush();

    Plan::create([
        'id'         => 1,
        'name'       => 'Pro Plan',
        'sync_limit' => 100,
    ]);
});

function createReturnsTestShop(int $id, string $domain, ?string $amazonRefreshToken = null): Shop
{
    $shop = new Shop();
    $shop->id = $id;
    $shop->shop = $domain;
    $shop->shop_name = "Store {$id}";
    $shop->email = "{$domain}@example.com";
    $shop->access_token = "token-{$id}";
    $shop->is_active = 1;
    $shop->amazon_seller_id = "SELLER_{$id}";
    $shop->amazon_refresh_token = $amazonRefreshToken;
    $shop->amazon_marketplace_id = 'ATVPDKIKX0DER';
    $shop->amazon_mws_region = 'na';
    $shop->save();

    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => 1,
        'status'             => 'active',
        'started_at'         => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    return $shop;
}

function mockShopAuthForReturns(Shop $shop): void
{
    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => $shop->shop,
        'shop_model' => $shop,
        'payload'    => ['dest' => "https://{$shop->shop}"],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);
}

it('Test 1: Disconnected shop without amazon_refresh_token returns empty array', function () {
    $shop = createReturnsTestShop(1, 'disconnected-shop.myshopify.com', null);
    mockShopAuthForReturns($shop);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $amazonServiceMock->shouldNotReceive('createReturnsReport');
    app()->instance(AmazonService::class, $amazonServiceMock);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->getJson('/returns/amazon');

    $response->assertOk();
    $response->assertExactJson([]);
});

it('Test 2: Connected shop with amazon_refresh_token retrieves and parses Amazon returns report', function () {
    $shop = createReturnsTestShop(2, 'connected-shop.myshopify.com', 'valid-amazon-refresh-token-2');
    mockShopAuthForReturns($shop);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $amazonServiceMock->shouldReceive('setRegion')
        ->with('na')
        ->andReturnSelf();
    $amazonServiceMock->shouldReceive('createReturnsReport')
        ->with('ATVPDKIKX0DER')
        ->once()
        ->andReturn('report-12345');
    $amazonServiceMock->shouldReceive('getReport')
        ->with('report-12345')
        ->once()
        ->andReturn("order-id\tproduct-name\tsku\tquantity\trefund-amount\treturn-reason-code\treturn-date\nORD-101\tTest Widget\tWID-01\t2\t49.99\tDEFECTIVE\t2026-09-01");
    $amazonServiceMock->shouldReceive('parseReport')
        ->once()
        ->andReturn([
            [
                'order-id'           => 'ORD-101',
                'product-name'       => 'Test Widget',
                'sku'                => 'WID-01',
                'quantity'           => '2',
                'refund-amount'      => '49.99',
                'return-reason-code' => 'DEFECTIVE',
                'return-date'        => '2026-09-01',
            ],
        ]);

    app()->instance(AmazonService::class, $amazonServiceMock);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-2',
        'Accept'        => 'application/json',
    ])->getJson('/returns/amazon');

    $response->assertOk();
    $data = $response->json();
    expect($data)->toBeArray()->toHaveCount(1);
    expect($data[0]['order_id'])->toBe('ORD-101');
    expect($data[0]['product_name'])->toBe('Test Widget');
    expect($data[0]['sku'])->toBe('WID-01');
    expect($data[0]['quantity'])->toBe('2');
    expect($data[0]['status'])->toBe('returned');
    expect($data[0]['refund_amount'])->toBe('49.99');
    expect($data[0]['reason'])->toBe('DEFECTIVE');
    expect($data[0]['created_at'])->toBe('2026-09-01');
});

it('Test 3: Cache isolation ensures separate shop cache keys and prevents cross-tenant data leakage', function () {
    $shopA = createReturnsTestShop(101, 'shop-a.myshopify.com', 'refresh-token-a');
    $shopB = createReturnsTestShop(102, 'shop-b.myshopify.com', 'refresh-token-b');

    // Pre-seed cache for Shop A and Shop B
    Cache::put("amazon_returns_{$shopA->id}", [
        [
            'order_id'      => 'ORDER-SHOP-A',
            'product_name'  => 'Product A',
            'sku'           => 'SKU-A',
            'quantity'      => 1,
            'status'        => 'returned',
            'refund_amount' => 10.00,
            'reason'        => 'A Reason',
            'created_at'    => '2026-09-01',
        ]
    ], 600);

    Cache::put("amazon_returns_{$shopB->id}", [
        [
            'order_id'      => 'ORDER-SHOP-B',
            'product_name'  => 'Product B',
            'sku'           => 'SKU-B',
            'quantity'      => 5,
            'status'        => 'returned',
            'refund_amount' => 50.00,
            'reason'        => 'B Reason',
            'created_at'    => '2026-09-02',
        ]
    ], 600);

    // Request as Shop A
    mockShopAuthForReturns($shopA);
    $responseA = $this->withHeaders([
        'Authorization' => 'Bearer token-101',
        'Accept'        => 'application/json',
    ])->getJson('/returns/amazon');

    $responseA->assertOk();
    $dataA = $responseA->json();
    expect($dataA)->toHaveCount(1);
    expect($dataA[0]['order_id'])->toBe('ORDER-SHOP-A');

    // Request as Shop B
    mockShopAuthForReturns($shopB);
    $responseB = $this->withHeaders([
        'Authorization' => 'Bearer token-102',
        'Accept'        => 'application/json',
    ])->getJson('/returns/amazon');

    $responseB->assertOk();
    $dataB = $responseB->json();
    expect($dataB)->toHaveCount(1);
    expect($dataB[0]['order_id'])->toBe('ORDER-SHOP-B');
});

it('Test 4: Amazon API failure handles exception gracefully, logs error, and returns empty array', function () {
    $shop = createReturnsTestShop(3, 'error-shop.myshopify.com', 'valid-refresh-token-3');
    mockShopAuthForReturns($shop);

    $amazonServiceMock = Mockery::mock(AmazonService::class);
    $amazonServiceMock->shouldReceive('setRegion')
        ->with('na')
        ->andReturnSelf();
    $amazonServiceMock->shouldReceive('createReturnsReport')
        ->with('ATVPDKIKX0DER')
        ->andThrow(new \Exception('Amazon API Rate Limit Exceeded'));

    app()->instance(AmazonService::class, $amazonServiceMock);

    Log::spy();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-3',
        'Accept'        => 'application/json',
    ])->getJson('/returns/amazon');

    $response->assertOk();
    $response->assertExactJson([]);

    Log::shouldHaveReceived('error')
        ->with('Amazon Returns fetch failed', Mockery::on(function ($context) use ($shop) {
            return isset($context['shop_id']) && $context['shop_id'] === $shop->id
                && isset($context['error']) && str_contains($context['error'], 'Amazon API Rate Limit Exceeded');
        }));
});

it('Test 5: Frontend Blade template uses amazon_refresh_token boolean and does not expose secret token', function () {
    $connectedShop = createReturnsTestShop(4, 'frontend-connected.myshopify.com', 'secret-amazon-refresh-token-444');
    $disconnectedShop = createReturnsTestShop(5, 'frontend-disconnected.myshopify.com', null);

    // Connected view
    $viewConnected = view('Return.index', [
        'shop'       => $connectedShop,
        'activeShop' => $connectedShop->shop,
        'returns'    => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
        'errors'     => new \Illuminate\Support\ViewErrorBag(),
    ])->render();

    // Verify boolean isAmazonConnected is true
    expect($viewConnected)->toContain('let isAmazonConnected = true;');
    // Verify secret refresh token is NOT printed in the rendered output
    expect($viewConnected)->not->toContain('secret-amazon-refresh-token-444');
    // Verify no mention of legacy amazon_access_token
    expect($viewConnected)->not->toContain('amazon_access_token');

    // Disconnected view
    $viewDisconnected = view('Return.index', [
        'shop'       => $disconnectedShop,
        'activeShop' => $disconnectedShop->shop,
        'returns'    => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
        'errors'     => new \Illuminate\Support\ViewErrorBag(),
    ])->render();

    expect($viewDisconnected)->toContain('let isAmazonConnected = false;');
    expect($viewDisconnected)->not->toContain('amazon_access_token');
});
