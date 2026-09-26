<?php

use App\Models\InventorySyncOperation;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Services\InventoryCacheService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->text('amazon_refresh_token')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    } else {
        ProductMarketplaceMapping::truncate();
    }

    if (!Schema::hasTable('inventory_sync_operations')) {
        Schema::create('inventory_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_uuid')->nullable();
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->integer('desired_quantity')->nullable();
            $table->integer('baseline_quantity')->nullable();
            $table->integer('expected_inventory_version')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('pending');
            $table->string('stage')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(4);
            $table->text('last_error')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    } else {
        InventorySyncOperation::truncate();
    }
    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            $table->integer('product_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->default('active');
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('settings')) {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->unique();
            $table->boolean('auto_sku_mapping')->default(1);
            $table->timestamps();
        });
    }
});

function createUiTestShop(int $id = 701, string $domain = 'ui-verify-test.myshopify.com'): Shop
{
    $shop = Shop::create([
        'id'                      => $id,
        'shop'                    => $domain,
        'shop_name'               => 'UI Test Store ' . $id,
        'email'                   => 'test' . $id . '@example.com',
        'access_token'            => 'token-' . $id,
        'selected_location_index' => 0,
        'shopify_locations'       => [
            ['id' => 'loc_1', 'name' => 'Primary Location']
        ],
        'amazon_seller_id'        => 'SELLER_' . $id,
        'amazon_marketplace_id'   => 'ATVPDKIKX0DER',
        'amazon_mws_region'       => 'na',
        'amazon_refresh_token'    => 'amz_refresh_token_' . $id,
        'is_active'               => 1,
    ]);

    $plan = \App\Models\Plan::firstOrCreate(
        ['name' => 'Unlimited Plan'],
        ['price' => 0, 'product_limit' => 0, 'is_active' => true]
    );

    \App\Models\ShopSubscription::create([
        'shop_id' => $shop->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'price' => 0,
        'current_period_end' => now()->addYear(),
    ]);

    return $shop;
}

function authUiSession(Shop $shop): array
{
    return [
        '_shopify_verified_shop' => $shop->shop,
        '_shopify_verified_at'   => time(),
        'active_shop'            => $shop->shop,
        'active_shop_id'         => $shop->id,
    ];
}

test('1. Amazon endpoint returns original quantity and is_verifying=true while submission_status is accepted', function () {
    $shop = createUiTestShop(701);

    // Mock Amazon cache with reported Amazon quantity = 10
    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-PENDING-VERIFY',
            'title'    => 'Test Item 1',
            'quantity' => 10,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    // Mapping has pending target quantity = 15, but submission_status = 'accepted'
    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-701',
        'shopify_inventory_item_id' => 'INV-701',
        'amazon_sku'                => 'SKU-PENDING-VERIFY',
        'quantity'                  => 15,
        'sync_status'               => 'success',
        'submission_status'         => 'accepted',
        'submission_id'             => 'SUB-701',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    expect($data['products'])->toHaveCount(1);

    $product = $data['products'][0];
    expect($product['sku'])->toBe('SKU-PENDING-VERIFY')
        ->and($product['is_mapped'])->toBeTrue()
        ->and($product['is_verifying'])->toBeTrue()
        ->and($product['submission_status'])->toBe('accepted')
        // CRITICAL REQUIREMENT: Amazon quantity must stay 10, not falsely display 15 before confirmation!
        ->and($product['quantity'])->toBe(10);
});

test('2. Amazon endpoint returns updated quantity and is_verifying=false when submission_status is confirmed', function () {
    $shop = createUiTestShop(702);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-CONFIRMED',
            'title'    => 'Test Item 2',
            'quantity' => 10,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-702',
        'shopify_inventory_item_id' => 'INV-702',
        'amazon_sku'                => 'SKU-CONFIRMED',
        'quantity'                  => 15,
        'sync_status'               => 'success',
        'submission_status'         => 'confirmed',
        'submission_id'             => 'SUB-702',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['is_mapped'])->toBeTrue()
        ->and($product['is_verifying'])->toBeFalse()
        ->and($product['submission_status'])->toBe('confirmed')
        // When confirmed, Amazon displays the new confirmed quantity 15
        ->and($product['quantity'])->toBe(15);
});

test('3. Amazon endpoint returns original quantity and is_verifying=false when verification ends in mismatch or failed', function () {
    $shop = createUiTestShop(703);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-FAILED-VERIFY',
            'title'    => 'Test Item 3',
            'quantity' => 10,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $mapping = ProductMarketplaceMapping::create([
        'shop_id'                   => $shop->id,
        'shopify_variant_id'        => 'V-703',
        'shopify_inventory_item_id' => 'INV-703',
        'amazon_sku'                => 'SKU-FAILED-VERIFY',
        'quantity'                  => 15, // target that failed
        'sync_status'               => 'failed',
        'submission_status'         => 'mismatch',
        'error_message'             => 'Amazon inventory quantity mismatch',
    ]);

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['is_mapped'])->toBeTrue()
        ->and($product['is_verifying'])->toBeFalse()
        ->and($product['submission_status'])->toBe('mismatch')
        // Stays at original confirmed 10, NOT the failed 15
        ->and($product['quantity'])->toBe(10);
});

test('4. Amazon endpoint handles unmapped products safely without setting is_verifying', function () {
    $shop = createUiTestShop(704);

    $cacheKey = "amazon_inventory_{$shop->id}_{$shop->amazon_seller_id}";
    Cache::put($cacheKey, [
        [
            'sku'      => 'SKU-UNMAPPED',
            'title'    => 'Unmapped Item',
            'quantity' => 50,
            'status'   => 'active',
        ]
    ], now()->addMinutes(20));

    Cache::put("amazon_inventory_status_{$shop->id}_{$shop->amazon_seller_id}", [
        'refreshing'     => false,
        'sync_completed' => true,
        'last_synced_at' => now()->toIso8601String(),
    ], now()->addMinutes(20));

    $response = $this->withSession(authUiSession($shop))
        ->getJson("/inventory/amazon?shop={$shop->shop}");

    $response->assertOk();
    $data = $response->json();
    $product = $data['products'][0];

    expect($product['is_mapped'])->toBeFalse()
        ->and($product['is_verifying'])->toBeFalse()
        ->and($product['quantity'])->toBe(50);
});
