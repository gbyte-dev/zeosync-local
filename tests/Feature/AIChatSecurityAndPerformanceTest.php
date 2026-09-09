<?php

use App\Models\AdminSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopifyOrder;
use App\Models\ShopSubscription;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
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
    AdminSetting::updateOrCreate(
        ['option_key' => 'openai_api_key'],
        ['option_value' => 'sk-test-mock-key-12345']
    );
    AdminSetting::updateOrCreate(
        ['option_key' => 'openai_model'],
        ['option_value' => 'gpt-4.1-mini']
    );

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
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_id')->unique()->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->text('tags')->nullable();
            $table->string('category')->nullable();
            $table->text('collections')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->text('local_images')->nullable();
            $table->string('amazon_product_id')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('shopify_order_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('name')->nullable();
            $table->string('financial_status')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
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

    Product::truncate();
    ShopifyOrder::truncate();
    Shop::truncate();
    ShopSubscription::truncate();
    Plan::truncate();
    Cache::flush();
    RateLimiter::clear('ai_chat_1');
    RateLimiter::clear('ai_chat_2');

    Plan::create([
        'id'         => 1,
        'name'       => 'Pro Plan',
        'sync_limit' => 100,
    ]);
});

function createAiTestShop(int $id, string $domain): Shop
{
    $shop = new Shop();
    $shop->id = $id;
    $shop->shop = $domain;
    $shop->shop_name = "Store {$id}";
    $shop->email = "{$domain}@example.com";
    $shop->access_token = "token-{$id}";
    $shop->is_active = 1;
    $shop->amazon_seller_id = "SELLER_{$id}";
    $shop->amazon_refresh_token = "refresh-token-{$id}";
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

function mockShopAuthForAi(Shop $shop): void
{
    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => $shop->shop,
        'shop_model' => $shop,
        'payload'    => ['dest' => "https://{$shop->shop}"],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);
}

it('Test A: Missing active shop fails closed with 401 and does NOT call OpenAI API', function () {
    Http::fake();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'Tell me about my store',
    ]);

    $response->assertStatus(401);
    Http::assertNothingSent();
});

it('Test B: Tenant isolation ensures Shop A context contains only Shop A data', function () {
    $shopA = createAiTestShop(1, 'store-a.myshopify.com');
    $shopB = createAiTestShop(2, 'store-b.myshopify.com');

    Product::create([
        'shop_id'    => $shopA->id,
        'title'      => 'Unique Product Alpha',
        'price'      => 99.99,
        'shopify_id' => 1001,
    ]);

    Product::create([
        'shop_id'    => $shopB->id,
        'title'      => 'Secret Product Beta',
        'price'      => 199.99,
        'shopify_id' => 2002,
    ]);

    $capturedUserPrompt = null;
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (\Illuminate\Http\Client\Request $request) use (&$capturedUserPrompt) {
            $data = $request->data();
            $capturedUserPrompt = $data['messages'][1]['content'] ?? '';
            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Mock AI answer for Shop A']],
                ],
            ], 200);
        },
    ]);

    mockShopAuthForAi($shopA);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'List my products',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    expect($capturedUserPrompt)->toContain('Unique Product Alpha');
    expect($capturedUserPrompt)->not->toContain('Secret Product Beta');
    expect($capturedUserPrompt)->toContain('Shop name: store-a.myshopify.com');
    expect($capturedUserPrompt)->not->toContain('store-b.myshopify.com');
});

it('Test C: Large product catalog is bounded to 50 sample products while reflecting total count', function () {
    $shop = createAiTestShop(1, 'large-catalog.myshopify.com');

    // Create 120 products
    $products = [];
    for ($i = 1; $i <= 120; $i++) {
        $products[] = [
            'shop_id'    => $shop->id,
            'title'      => sprintf('Product %03d', $i),
            'price'      => 10.00 + $i,
            'shopify_id' => 10000 + $i,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    Product::insert($products);

    $capturedUserPrompt = null;
    Http::fake([
        '*api.openai.com*' => function (\Illuminate\Http\Client\Request $request) use (&$capturedUserPrompt) {
            $data = $request->data();
            $capturedUserPrompt = $data['messages'][1]['content'] ?? '';
            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Mock AI answer']],
                ],
            ], 200);
        },
    ]);

    mockShopAuthForAi($shop);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'How many products do I have?',
    ]);

    $response->assertOk();
    expect($capturedUserPrompt)->toContain('Total Shopify products: 120');
    expect($capturedUserPrompt)->toContain('Sample products (showing 50 of 120):');
    expect($capturedUserPrompt)->toContain('Product 001');
    expect($capturedUserPrompt)->toContain('Product 050');
    expect($capturedUserPrompt)->not->toContain('Product 051');
});

it('Test D: Large order dataset bounds recent order scan to latest 100 records', function () {
    $shop = createAiTestShop(1, 'large-orders.myshopify.com');

    // Create 150 orders
    for ($i = 1; $i <= 150; $i++) {
        ShopifyOrder::create([
            'shop_id'          => $shop->id,
            'shopify_order_id' => "ord_{$i}",
            'line_items'       => [
                ['title' => 'Hot Seller Widget', 'quantity' => 1],
            ],
            'order_created_at' => now()->subMinutes(160 - $i),
        ]);
    }

    $capturedUserPrompt = null;
    Http::fake([
        '*api.openai.com*' => function (\Illuminate\Http\Client\Request $request) use (&$capturedUserPrompt) {
            $data = $request->data();
            $capturedUserPrompt = $data['messages'][1]['content'] ?? '';
            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Top product is Hot Seller Widget']],
                ],
            ], 200);
        },
    ]);

    mockShopAuthForAi($shop);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'What is my best-selling product?',
    ]);

    $response->assertOk();
    // Scanned up to 100 recent orders: 100 units
    expect($capturedUserPrompt)->toContain('Hot Seller Widget sold 100 units');
});

it('Test E: Prompt context is strictly budgeted and truncated if exceeding max characters', function () {
    $shop = createAiTestShop(1, 'budget-check.myshopify.com');

    // Create products with extremely large titles
    for ($i = 1; $i <= 50; $i++) {
        Product::create([
            'shop_id'    => $shop->id,
            'title'      => 'Very Long Product Title ' . str_repeat('X', 400) . " #{$i}",
            'price'      => 50.00,
            'shopify_id' => 5000 + $i,
        ]);
    }

    $capturedUserPrompt = null;
    Http::fake([
        '*api.openai.com*' => function (\Illuminate\Http\Client\Request $request) use (&$capturedUserPrompt) {
            $data = $request->data();
            $capturedUserPrompt = $data['messages'][1]['content'] ?? '';
            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'AI Response']],
                ],
            ], 200);
        },
    ]);

    mockShopAuthForAi($shop);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'Analyze store',
    ]);

    $response->assertOk();
    expect(strlen($capturedUserPrompt))->toBeLessThan(13000);
    expect($capturedUserPrompt)->toContain('[Catalog context truncated for length]');
});

it('Test F: Rate limiting enforces 15 requests/minute per shop and returns 429 when exceeded', function () {
    $shopA = createAiTestShop(1, 'rate-limited.myshopify.com');
    $shopB = createAiTestShop(2, 'other-shop.myshopify.com');

    RateLimiter::clear("ai_chat_{$shopA->id}");
    RateLimiter::clear("ai_chat_{$shopB->id}");

    Http::fake([
        '*api.openai.com*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'OK']],
            ],
        ], 200),
    ]);

    mockShopAuthForAi($shopA);

    // Send 15 successful requests
    for ($i = 1; $i <= 15; $i++) {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token-1',
            'Accept'        => 'application/json',
        ])->postJson('/ai-chat', [
            'prompt' => "Question {$i}",
        ]);

        $response->assertOk();
    }

    // 16th request should fail with 429
    $rateLimitedResponse = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'Question 16',
    ]);

    $rateLimitedResponse->assertStatus(429);
    $rateLimitedResponse->assertJsonPath('success', false);
    expect($rateLimitedResponse->json('error'))->toContain('Too many AI requests');

    // Verify Shop B is NOT rate-limited (isolation)
    mockShopAuthForAi($shopB);
    $shopBResponse = $this->withHeaders([
        'Authorization' => 'Bearer token-2',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'Question from Shop B',
    ]);

    $shopBResponse->assertOk();
});

it('Test G: Normal AI chat flow returns structured assistant message and stores history', function () {
    $shop = createAiTestShop(1, 'normal-flow.myshopify.com');

    Product::create([
        'shop_id'    => $shop->id,
        'title'      => 'Widget Standard',
        'price'      => 29.99,
        'shopify_id' => 999,
    ]);

    Http::fake([
        '*api.openai.com*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Your Widget Standard is priced at $29.99.']],
            ],
        ], 200),
    ]);

    mockShopAuthForAi($shop);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-1',
        'Accept'        => 'application/json',
    ])->postJson('/ai-chat', [
        'prompt' => 'What is the price of Widget Standard?',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Your Widget Standard is priced at $29.99.',
    ]);
    expect($response->json('history'))->toHaveCount(2);
});

it('Test H: AI Chat index view renders input form, keyboard hints, and loader styles', function () {
    $shop = createAiTestShop(1, 'view-test.myshopify.com');

    $errors = new \Illuminate\Support\ViewErrorBag();
    $view = view('aichat.index', [
        'currentShop' => $shop->shop,
        'chatHistory' => [],
        'errors'      => $errors,
    ])->render();

    expect($view)->toContain('id="ai-chat-form"');
    expect($view)->toContain('id="prompt"');
    expect($view)->toContain('id="ai-chat-submit"');
    expect($view)->toContain('.ai-response-loader');
    expect($view)->toContain('loading_5192');
    expect($view)->toContain('AI is thinking');
});




