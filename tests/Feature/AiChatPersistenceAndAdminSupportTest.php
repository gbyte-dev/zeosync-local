<?php

use App\Models\Admin;
use App\Models\AdminSetting;
use App\Models\AiChatMessage;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    if (!Schema::hasTable('admins')) {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('super_admin');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_notifications')) {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('contact_inquiries')) {
        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('title')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('ai_chat_messages')) {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('role', 20);
            $table->text('message');
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['shop_id', 'id']);
        });
    }

    DB::table('ai_chat_messages')->truncate();
    DB::table('shops')->truncate();
    DB::table('admins')->truncate();
});

function createTestShop(string $domain, string $name = 'Test Store'): Shop
{
    return Shop::create([
        'shop'         => $domain,
        'shop_name'    => $name,
        'email'        => 'merchant@' . $domain,
        'is_active'    => 1,
        'access_token' => 'shpat_test_' . md5($domain),
    ]);
}

function testAuthSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
        'shopify_installed'      => true,
    ];
}

test('user ask persists both user prompt and ai assistant response in ai_chat_messages table', function () {
    $shop = createTestShop('test-shop.myshopify.com', 'Test Store');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Hello! I can help you with your Shopify and Amazon inventory.',
                    ],
                ],
            ],
            'usage' => ['total_tokens' => 45],
        ], 200),
    ]);

    $response = $this->withSession(testAuthSession($shop))->postJson(route('shopify.ai.chat.ask'), [
        'prompt' => 'How do I sync my inventory?',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Hello! I can help you with your Shopify and Amazon inventory.',
    ]);

    // Verify 2 messages persisted
    $messages = AiChatMessage::where('shop_id', $shop->id)->orderBy('id')->get();
    expect($messages)->toHaveCount(2);

    expect($messages[0]->role)->toBe('user');
    expect($messages[0]->message)->toBe('How do I sync my inventory?');

    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->message)->toBe('Hello! I can help you with your Shopify and Amazon inventory.');
});

test('user can fetch chat messages with multi-shop isolation', function () {
    $shopA = createTestShop('shop-a.myshopify.com', 'Store A');
    $shopB = createTestShop('shop-b.myshopify.com', 'Store B');

    AiChatMessage::create([
        'shop_id' => $shopA->id,
        'role'    => 'user',
        'message' => 'Shop A question',
    ]);
    AiChatMessage::create([
        'shop_id' => $shopA->id,
        'role'    => 'assistant',
        'message' => 'Shop A response',
    ]);
    AiChatMessage::create([
        'shop_id' => $shopB->id,
        'role'    => 'user',
        'message' => 'Shop B private data',
    ]);

    // Request from Shop A
    $responseA = $this->withSession(testAuthSession($shopA))->getJson(route('shopify.ai.chat.messages'));

    $responseA->assertOk();
    $responseA->assertJsonCount(2, 'messages');
    $responseA->assertJsonFragment(['message' => 'Shop A question']);
    $responseA->assertJsonMissing(['message' => 'Shop B private data']);

    // Polling with after_id
    $firstMsg = AiChatMessage::where('shop_id', $shopA->id)->first();
    $responsePoll = $this->withSession(testAuthSession($shopA))->getJson(route('shopify.ai.chat.messages', ['after_id' => $firstMsg->id]));

    $responsePoll->assertOk();
    $responsePoll->assertJsonCount(1, 'messages');
    $responsePoll->assertJsonFragment(['message' => 'Shop A response']);
});

test('clear conversation deletes persisted messages for the active shop only', function () {
    $shopA = createTestShop('shop-a.myshopify.com', 'Store A');
    $shopB = createTestShop('shop-b.myshopify.com', 'Store B');

    AiChatMessage::create(['shop_id' => $shopA->id, 'role' => 'user', 'message' => 'Shop A message']);
    AiChatMessage::create(['shop_id' => $shopB->id, 'role' => 'user', 'message' => 'Shop B message']);

    $response = $this->withSession(testAuthSession($shopA))->postJson(route('shopify.ai.chat.clear'));

    $response->assertOk();
    $response->assertJson(['success' => true]);

    expect(AiChatMessage::where('shop_id', $shopA->id)->count())->toBe(0);
    expect(AiChatMessage::where('shop_id', $shopB->id)->count())->toBe(1);
});

test('admin support chat requires admin authentication', function () {
    $shop = createTestShop('shop-a.myshopify.com', 'Store A');

    $this->get(route('admin.aichats.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.aichats.messages', $shop->id))->assertRedirect(route('admin.login'));
    $this->postJson(route('admin.aichats.send', $shop->id), ['message' => 'Hi'])->assertUnauthorized();
});

test('admin can view conversation list, shop messages, and send admin messages', function () {
    $admin = Admin::create([
        'name'     => 'Support Admin',
        'email'    => 'admin@zeosync.com',
        'password' => bcrypt('secret123'),
        'role'     => 'super_admin',
    ]);

    $shopA = createTestShop('shop-a.myshopify.com', 'Store Alpha');
    $shopB = createTestShop('shop-b.myshopify.com', 'Store Beta');

    AiChatMessage::create([
        'shop_id'    => $shopA->id,
        'role'       => 'user',
        'message'    => 'Help with product sync',
        'created_at' => now()->subMinutes(10),
    ]);

    AiChatMessage::create([
        'shop_id'    => $shopB->id,
        'role'       => 'user',
        'message'    => 'Billing question',
        'created_at' => now()->subMinutes(2),
    ]);

    // Admin index lists conversations sorted by latest message descending (Shop B first, then Shop A)
    $response = $this->actingAs($admin, 'admin')->get(route('admin.aichats.index'));
    $response->assertOk();
    $response->assertSee('Store Alpha');
    $response->assertSee('Store Beta');

    // Admin views messages of Shop A
    $msgResponse = $this->actingAs($admin, 'admin')->getJson(route('admin.aichats.messages', $shopA->id));
    $msgResponse->assertOk();
    $msgResponse->assertJsonCount(1, 'messages');
    $msgResponse->assertJsonFragment(['message' => 'Help with product sync']);

    // Admin sends message to Shop A
    $sendResponse = $this->actingAs($admin, 'admin')->postJson(route('admin.aichats.send', $shopA->id), [
        'message' => 'Hello! Support team here to help.',
    ]);
    $sendResponse->assertOk();
    $sendResponse->assertJson([
        'success' => true,
        'message' => [
            'role'    => 'admin',
            'message' => 'Hello! Support team here to help.',
        ],
    ]);

    // Verify stored in DB with role admin
    $adminMsg = AiChatMessage::where('shop_id', $shopA->id)->where('role', 'admin')->first();
    expect($adminMsg)->not->toBeNull();
    expect($adminMsg->message)->toBe('Hello! Support team here to help.');

    // User widget fetches the admin message
    $userPoll = $this->withSession(testAuthSession($shopA))->getJson(route('shopify.ai.chat.messages'));

    $userPoll->assertOk();
    $userPoll->assertJsonCount(2, 'messages');
    $userPoll->assertJsonFragment([
        'role'    => 'admin',
        'message' => 'Hello! Support team here to help.',
    ]);
});

test('5-day retention purge command deletes only messages older than 5 days', function () {
    $shop = createTestShop('shop-retention.myshopify.com', 'Retention Store');

    // 6 days old -> should be deleted
    $oldMsg1 = AiChatMessage::create([
        'shop_id'    => $shop->id,
        'role'       => 'user',
        'message'    => 'Old user message 6 days ago',
        'created_at' => now()->subDays(6),
        'updated_at' => now()->subDays(6),
    ]);
    // 5 days 1 hour old -> should be deleted
    $oldMsg2 = AiChatMessage::create([
        'shop_id'    => $shop->id,
        'role'       => 'assistant',
        'message'    => 'Old assistant reply',
        'created_at' => now()->subDays(5)->subHour(),
        'updated_at' => now()->subDays(5)->subHour(),
    ]);
    // 4 days old -> should be kept
    $freshMsg1 = AiChatMessage::create([
        'shop_id'    => $shop->id,
        'role'       => 'user',
        'message'    => 'Recent message 4 days ago',
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ]);
    // 1 hour old -> should be kept
    $freshMsg2 = AiChatMessage::create([
        'shop_id'    => $shop->id,
        'role'       => 'admin',
        'message'    => 'Fresh admin message 1 hour ago',
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    expect(AiChatMessage::count())->toBe(4);

    Artisan::call('ai-chat:purge-old');

    expect(AiChatMessage::count())->toBe(2);
    expect(AiChatMessage::where('id', $oldMsg1->id)->exists())->toBeFalse();
    expect(AiChatMessage::where('id', $oldMsg2->id)->exists())->toBeFalse();
    expect(AiChatMessage::where('id', $freshMsg1->id)->exists())->toBeTrue();
    expect(AiChatMessage::where('id', $freshMsg2->id)->exists())->toBeTrue();
});
