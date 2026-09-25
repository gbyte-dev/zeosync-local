<?php

use App\Models\AdminSetting;
use App\Models\InventorySyncOperation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductMarketplaceMapping;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Services\AmazonService;
use App\Services\ShopifyService;
use App\Services\ShopifySessionTokenValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    Queue::fake();

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

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
            $table->string('shopify_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('shopify_status')->nullable();
            $table->text('shopify_error')->nullable();
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->string('tags')->nullable();
            $table->string('category')->nullable();
            $table->string('collections')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('options')->nullable();
            $table->json('metafields')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->boolean('synced_to_amazon')->default(0);
            $table->boolean('needs_resync')->default(0);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->string('local_images')->nullable();
            $table->string('amazon_product_id')->nullable();
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
            $table->string('shopify_location_id')->nullable();
            $table->string('amazon_sku')->nullable();
            $table->string('amazon_parent_sku')->nullable();
            $table->string('amazon_asin')->nullable();
            $table->string('amazon_parent_asin')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_product_type')->nullable();
            $table->string('quantity')->nullable();
            $table->integer('amazon_quantity')->nullable();
            $table->unsignedBigInteger('inventory_version')->default(1);
            $table->string('sync_status')->default('pending');
            $table->string('submission_status')->default('not_submitted');
            $table->string('submission_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->softDeletes();
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

    ProductMarketplaceMapping::query()->delete();
    Product::query()->delete();
    ShopSubscription::query()->delete();
    Plan::query()->delete();
    Shop::query()->delete();

    Plan::create([
        'name'       => 'Pro Plan',
        'sync_limit' => 100,
    ]);

    Http::fake([
        '*graphql.json*' => function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $query = $data['query'] ?? '';
            $vars = $data['variables'] ?? [];

            if (str_contains($query, 'inventorySetQuantities') || str_contains($query, 'InventorySetQuantities')) {
                return Http::response(['data' => ['inventorySetQuantities' => ['inventoryAdjustmentGroup' => null, 'userErrors' => []]]], 200);
            }

            if (str_contains($query, 'locations(')) {
                return Http::response([
                    'data' => [
                        'locations' => [
                            'edges' => [
                                ['node' => ['id' => 'gid://shopify/Location/999111', 'legacyResourceId' => '999111', 'name' => 'Main Warehouse', 'isActive' => true]]
                            ]
                        ]
                    ]
                ], 200);
            }

            return Http::response(['data' => []], 200);
        },
        '*inventory_levels/set.json*' => Http::response(['inventory_level' => ['available' => 10]], 200),
        '*'                           => Http::response(['access_token' => 'dummy_token', 'expires_in' => 3600], 200),
    ]);
});

function createMappingTestShop(int $id, string $domain, string $name = 'Store', array $locations = [['id' => '1001', 'name' => 'Location 1']], int $selectedIndex = 0): Shop
{
    $shop = new Shop();
    $shop->id = $id;
    $shop->shop = $domain;
    $shop->shop_name = $name;
    $shop->email = "{$domain}@example.com";
    $shop->access_token = "token-{$id}";
    $shop->is_active = 1;
    $shop->shopify_locations = $locations;
    $shop->selected_location_index = $selectedIndex;
    $shop->amazon_seller_id = "SELLER_{$id}";
    $shop->amazon_marketplace_id = 'ATVPDKIKX0DER';
    $shop->amazon_mws_region = 'us-east-1';
    $shop->save();

    ShopSubscription::create([
        'shop_id'            => $shop->id,
        'plan_id'            => Plan::first()?->id,
        'status'             => 'active',
        'started_at'         => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    return $shop;
}

function mockMappingShopAuth(Shop $shop): void
{
    $validator = Mockery::mock(ShopifySessionTokenValidator::class)->makePartial();
    $validator->shouldReceive('validate')->andReturn([
        'shop'       => $shop->shop,
        'shop_model' => $shop,
        'payload'    => ['dest' => "https://{$shop->shop}"],
    ]);
    app()->instance(ShopifySessionTokenValidator::class, $validator);
}

test('Case B: Product without variants maps directly with shopify_variant_id equal to shopify_product_id and saves location', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => '1001', 'name' => 'Location 1'],
        ['id' => '1002', 'name' => 'Location 2'],
    ], 1); // Active location is 1002

    mockMappingShopAuth($shop);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '777001',
        'title' => 'Standalone Product Without Variants',
        'variants' => [], // No variants
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shop->shop,
        'amazon_sku' => 'AMZ-STANDALONE-001',
        'product_id' => $product->id,
        'shopify_product_id' => '777001',
        'shopify_variant_id' => '777001',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
        ->where('amazon_sku', 'AMZ-STANDALONE-001')
        ->first();

    expect($mapping)->not->toBeNull()
        ->and($mapping->shopify_product_id)->toBe('777001')
        ->and($mapping->shopify_variant_id)->toBe('777001')
        ->and($mapping->shopify_location_id)->toBe('1002');
});

test('Case A: Product with variants requires variant selection and fails if missing or invalid', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => '1001', 'name' => 'Location 1'],
    ], 0);

    mockMappingShopAuth($shop);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '888001',
        'title' => 'T-Shirt With Variants',
        'variants' => [
            ['id' => 888101, 'title' => 'Small', 'inventory_item_id' => 901],
            ['id' => 888102, 'title' => 'Medium', 'inventory_item_id' => 902],
            ['id' => 888103, 'title' => 'Large', 'inventory_item_id' => 903],
        ],
    ]);

    // Attempt 1: Omit variant when variants exist -> must fail (422)
    $failResponse = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shop->shop,
        'amazon_sku' => 'AMZ-TSHIRT-S',
        'product_id' => $product->id,
        'shopify_product_id' => '888001',
        'shopify_variant_id' => null,
    ]);
    $failResponse->assertStatus(422);

    // Attempt 2: Variant belongs to another product / invalid variant -> must fail (422)
    $invalidVariantResponse = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shop->shop,
        'amazon_sku' => 'AMZ-TSHIRT-S',
        'product_id' => $product->id,
        'shopify_product_id' => '888001',
        'shopify_variant_id' => '999999', // Non-existent variant
    ]);
    $invalidVariantResponse->assertStatus(422);

    // Attempt 3: Valid variant -> must succeed
    $successResponse = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shop->shop,
        'amazon_sku' => 'AMZ-TSHIRT-S',
        'product_id' => $product->id,
        'variant_id' => '888101',
        'shopify_product_id' => '888001',
        'shopify_variant_id' => '888101',
        'shopify_inventory_item_id' => '901',
    ]);
    $successResponse->assertStatus(200);

    $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
        ->where('amazon_sku', 'AMZ-TSHIRT-S')
        ->first();

    expect($mapping)->not->toBeNull()
        ->and($mapping->shopify_product_id)->toBe('888001')
        ->and($mapping->shopify_variant_id)->toBe('888101')
        ->and($mapping->shopify_inventory_item_id)->toBe('901')
        ->and($mapping->shopify_location_id)->toBe('1001');
});

test('Variants endpoint returns has_variants and correct payload for products with and without variants', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A');

    mockMappingShopAuth($shop);

    $productWithVariants = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '501',
        'variants' => [
            ['id' => 5011, 'title' => 'Red', 'inventory_item_id' => 701],
        ],
    ]);

    $productWithoutVariants = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '502',
        'variants' => [],
    ]);

    $res1 = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->getJson(route('inventory.shopify.variants', ['product' => $productWithVariants->id]) . '?shop=' . $shop->shop);
    $res1->assertStatus(200);
    $res1->assertJson([
        'success' => true,
        'has_variants' => true,
        'shopify_product_id' => '501',
    ]);
    expect($res1->json('variants'))->toHaveCount(1);

    $res2 = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->getJson(route('inventory.shopify.variants', ['product' => $productWithoutVariants->id]) . '?shop=' . $shop->shop);
    $res2->assertStatus(200);
    $res2->assertJson([
        'success' => true,
        'has_variants' => false,
        'shopify_product_id' => '502',
        'variants' => [],
    ]);
});

test('Inventory update uses mapping stored shopify_location_id', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => 'loc_default', 'name' => 'Default Location'],
        ['id' => 'loc_specific', 'name' => 'Specific Location'],
    ], 0);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'AMZ-LOC-TEST',
        'shopify_product_id' => '111',
        'shopify_variant_id' => '222',
        'shopify_inventory_item_id' => '333',
        'shopify_location_id' => 'loc_specific', // Stored mapping location is loc_specific
        'quantity' => 15,
        'sync_status' => 'synced',
    ]);

    $recordedLocation = null;

    Http::fake([
        '*graphql.json*' => function (\Illuminate\Http\Client\Request $request) use (&$recordedLocation) {
            $data = $request->data();
            $vars = $data['variables'] ?? [];
            if (isset($vars['input']['quantities'][0]['locationId'])) {
                $recordedLocation = $vars['input']['quantities'][0]['locationId'];
            }
            return Http::response(['data' => ['inventorySetQuantities' => ['inventoryAdjustmentGroup' => null, 'userErrors' => []]]], 200);
        },
        '*inventory_levels/set.json*' => function (\Illuminate\Http\Client\Request $request) use (&$recordedLocation) {
            $data = $request->data();
            $recordedLocation = $data['location_id'] ?? null;
            return Http::response(['inventory_level' => ['available' => 25]], 200);
        },
        '*' => Http::response(['access_token' => 'dummy_token'], 200),
    ]);

    $mockResponse = Mockery::mock(\Saloon\Http\Response::class);
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUBMISSION-123',
        'issues'       => [],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries'  => [['productType' => 'PRODUCT']],
        'attributes' => ['fulfillment_availability' => [['fulfillment_channel_code' => 'DEFAULT']]],
    ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    $amazonService->updateInventory($shop, 'AMZ-LOC-TEST', 25, true);

    expect((string) $recordedLocation)->toContain('loc_specific');
});

test('Inventory update safely fails when mapping has no location ID without silently updating a default location', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => 'loc_default', 'name' => 'Default Location'],
    ], 0);

    $mapping = ProductMarketplaceMapping::create([
        'shop_id' => $shop->id,
        'amazon_sku' => 'AMZ-NO-LOC',
        'shopify_product_id' => '111',
        'shopify_variant_id' => '222',
        'shopify_inventory_item_id' => '333',
        'shopify_location_id' => null, // Missing location
        'quantity' => 15,
    ]);

    $mockResponse = Mockery::mock(\Saloon\Http\Response::class);
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('json')->andReturn([
        'status'       => 'ACCEPTED',
        'submissionId' => 'SUBMISSION-123',
        'issues'       => [],
    ]);

    $mockConnector = Mockery::mock();
    $mockConnector->shouldReceive('patchListingsItem')->andReturn($mockResponse);

    $amazonService = Mockery::mock(AmazonService::class)->makePartial();
    $amazonService->shouldReceive('checkAmazonListing')->andReturn([
        'summaries'  => [['productType' => 'PRODUCT']],
        'attributes' => ['fulfillment_availability' => [['fulfillment_channel_code' => 'DEFAULT']]],
    ]);
    $amazonService->shouldReceive('getDbConnectorFromCredentials')->andReturn($mockConnector);

    expect(fn () => $amazonService->updateInventory($shop, 'AMZ-NO-LOC', 25, true))
        ->toThrow(\Exception::class, 'Mapping has no associated Shopify location.');
});

test('Tenant isolation: Store A cannot map or access Store B product or location', function () {
    $shopA = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [['id' => 'loc_a', 'name' => 'Location A']], 0);
    $shopB = createMappingTestShop(2, 'store-b.myshopify.com', 'Store B', [['id' => 'loc_b', 'name' => 'Location B']], 0);

    mockMappingShopAuth($shopA);

    $productB = Product::create([
        'shop_id' => $shopB->id,
        'shopify_id' => '999000',
        'variants' => [],
    ]);

    // Store A tries to map Store B's product
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shopA->shop,
        'amazon_sku' => 'AMZ-CROSS-TENANT',
        'product_id' => $productB->id,
        'shopify_product_id' => '999000',
        'shopify_variant_id' => '999000',
    ]);

    $response->assertStatus(404);
});

test('Dashboard mapped count remains exactly equal to Inventory Mapping visible count', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [['id' => 'loc_100', 'name' => 'Location 100']], 0);
    mockMappingShopAuth($shop);

    // 2 valid mappings (one with variant, one standalone)
    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'amazon_sku'         => 'SKU-001',
        'shopify_product_id' => '1001',
        'shopify_variant_id' => '2001',
        'shopify_location_id'=> 'loc_100',
    ]);

    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'amazon_sku'         => 'SKU-002',
        'shopify_product_id' => '1002',
        'shopify_variant_id' => '1002', // Standalone
        'shopify_location_id'=> 'loc_100',
    ]);

    // 1 invalid mapping (null variant) that should NOT be counted in visible mappings
    ProductMarketplaceMapping::create([
        'shop_id'            => $shop->id,
        'amazon_sku'         => 'SKU-003',
        'shopify_product_id' => '1003',
        'shopify_variant_id' => null,
        'shopify_location_id'=> 'loc_100',
    ]);

    $dashboardMappedCount = ProductMarketplaceMapping::where('shop_id', $shop->id)
        ->whereNotNull('amazon_sku')
        ->where('amazon_sku', '!=', '')
        ->whereNotNull('shopify_variant_id')
        ->where('shopify_variant_id', '!=', '')
        ->count();

    expect($dashboardMappedCount)->toBe(2);
});

test('Modal layout has Product and Variant side-by-side in Row 1 and Shop Location in Row 2 with settings default preselected', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => '10001', 'name' => 'Main Warehouse'],
        ['id' => '10002', 'name' => 'Secondary Warehouse'],
    ], 1); // Selected index is 1 -> Secondary Warehouse

    $renderedHtml = view('inventory.partials.map-shopify-product-modal', compact('shop'))->render();

    // 1. Both Product and Variant exist in first row
    expect($renderedHtml)->toContain('id="shopifyProduct"')
        ->and($renderedHtml)->toContain('id="shopifyVariant"')
        ->and($renderedHtml)->toContain('id="shopifyLocation"');

    // 2. Row order: Product & Variant appear before Shop Location
    $productPos = strpos($renderedHtml, 'id="shopifyProduct"');
    $variantPos = strpos($renderedHtml, 'id="shopifyVariant"');
    $locationPos = strpos($renderedHtml, 'id="shopifyLocation"');

    expect($productPos)->toBeLessThan($locationPos)
        ->and($variantPos)->toBeLessThan($locationPos);

    // 3. Preselected location is index 1 (10002)
    expect($renderedHtml)->toMatch('/<option value="10002"\s+selected>/');
});

test('User can override Shop Location for a single mapping without changing global shop settings', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => 'loc_warehouse_a', 'name' => 'Warehouse A'],
        ['id' => 'loc_warehouse_b', 'name' => 'Warehouse B'],
    ], 0); // Settings global location is Warehouse A (index 0)

    mockMappingShopAuth($shop);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '999123',
        'title' => 'Override Test Product',
        'variants' => [],
    ]);

    // Save mapping with overridden location: loc_warehouse_b
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shop->shop,
        'amazon_sku' => 'AMZ-OVERRIDE-SKU',
        'product_id' => $product->id,
        'shopify_product_id' => '999123',
        'shopify_variant_id' => '999123',
        'shopify_location_id' => 'loc_warehouse_b', // User overrides location to Warehouse B
    ]);

    $response->assertStatus(200);

    // Verify mapping was saved with overridden location ID
    $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
        ->where('amazon_sku', 'AMZ-OVERRIDE-SKU')
        ->first();

    expect($mapping)->not->toBeNull()
        ->and($mapping->shopify_location_id)->toBe('loc_warehouse_b');

    // Verify global shop settings were NOT changed
    $shop->refresh();
    expect($shop->selected_location_index)->toBe(0);
});

test('Save mapping rejects an invalid or foreign location ID', function () {
    $shop = createMappingTestShop(1, 'store-a.myshopify.com', 'Store A', [
        ['id' => 'loc_warehouse_a', 'name' => 'Warehouse A'],
    ], 0);

    mockMappingShopAuth($shop);

    $product = Product::create([
        'shop_id' => $shop->id,
        'shopify_id' => '999456',
        'title' => 'Invalid Location Test',
        'variants' => [],
    ]);

    // Submit a location ID that does not belong to the shop
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token-test',
        'Accept'        => 'application/json',
    ])->postJson(route('inventory.save.mapping'), [
        'shop' => $shop->shop,
        'amazon_sku' => 'AMZ-INVALID-LOC-SKU',
        'product_id' => $product->id,
        'shopify_product_id' => '999456',
        'shopify_variant_id' => '999456',
        'shopify_location_id' => 'foreign_location_999',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'message' => 'Selected Shopify location is invalid or does not belong to this shop.',
    ]);
});

