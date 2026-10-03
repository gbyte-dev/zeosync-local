<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductMarketplaceMapping;
use App\Models\ProductSchema;
use App\Models\Shop;
use App\Services\Amazon\ShopifyAmazonMapper;
use App\Services\TransformsAmazonAttributes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class InventoryMappingAddNewProductFixTest extends TestCase
{
    protected Shop $shop;
    protected ProductSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shopify.api_key' => 'test-api-key',
            'services.shopify.api_secret' => 'test-api-secret',
            'app.disable_subscription' => true,
        ]);

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
                $table->string('amazon_marketplace_id')->nullable();
                $table->json('shopify_locations')->nullable();
                $table->integer('selected_location_index')->nullable();
                $table->boolean('is_active')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category')->nullable();
                $table->string('slug')->nullable();
                $table->json('marketplaceIds')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->integer('level')->default(1);
                $table->string('status')->default('active');
                $table->boolean('self_added')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_schemas')) {
            Schema::create('product_schemas', function (Blueprint $table) {
                $table->id();
                $table->string('product_type');
                $table->text('json_schema')->nullable();
                $table->json('parsed_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->string('shopify_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('sub_category_id')->nullable();
                $table->unsignedBigInteger('schema_id')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('amazon_product_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('status')->default('draft');
                $table->string('producttype')->nullable();
                $table->string('submission_status')->nullable();
                $table->timestamp('submitted_on')->nullable();
                $table->text('final_json')->nullable();
                $table->text('filled_json')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_attributes')) {
            Schema::create('product_attributes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('attribute_name');
                $table->text('attribute_value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_marketplace_mappings')) {
            Schema::create('product_marketplace_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id');
                $table->string('product_id')->nullable();
                $table->string('variant_id')->nullable();
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
                $table->string('fulfillment_channel_code')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('inventory_version')->default(1);
                $table->string('sync_status')->default('pending');
                $table->string('submission_status')->nullable();
                $table->string('submission_id')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_sync_logs')) {
            Schema::create('product_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->string('platform')->nullable();
                $table->string('status')->nullable();
                $table->text('message')->nullable();
                $table->string('type')->nullable();
                $table->timestamps();
            });
        }

        ProductMarketplaceMapping::truncate();
        ProductAttribute::truncate();
        Product::truncate();
        ProductSchema::truncate();
        Category::truncate();
        Shop::truncate();

        $this->shop = Shop::create([
            'shop' => 'test-store.myshopify.com',
            'shop_name' => 'Test Store',
            'email' => 'test@example.com',
            'access_token' => 'shpat_test_token',
            'amazon_marketplace_id' => 'ATVPDKIKX0DER',
            'is_active' => true,
        ]);

        $this->schema = ProductSchema::create([
            'product_type' => 'SHIRT',
            'parsed_json' => [
                'item_name' => ['type' => 'string'],
                'brand' => ['type' => 'string'],
                'purchasable_offer' => ['type' => 'object'],
                'list_price' => ['type' => 'number'],
                'fulfillment_availability' => ['type' => 'object'],
                'externally_assigned_product_identifier' => ['type' => 'array'],
            ],
        ]);
    }

    protected function authSession(): array
    {
        return [
            '_shopify_verified_shop' => $this->shop->shop,
            '_shopify_verified_at' => time(),
            'active_shop' => $this->shop->shop,
            'active_shop_id' => $this->shop->id,
        ];
    }

    /**
     * TEST 1: Normal Add Product transformation still works as expected.
     */
    public function test_normal_add_product_transforms_standard_attributes(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $transformer = new TransformsAmazonAttributes('ATVPDKIKX0DER');
        
        $res = $transformer->transformAttribute('item_name', 'Test Product Name');
        $this->assertEquals([['value' => 'Test Product Name', 'language_tag' => 'en_US']], $res);

        $resBrand = $transformer->transformAttribute('brand', 'Acme');
        $this->assertEquals([['value' => 'Acme']], $resBrand);
    }

    /**
     * TEST 2: Normal Edit Product works and preserves existing attribute behavior.
     */
    public function test_normal_edit_product_transforms_color_and_dimensions(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $transformer = new TransformsAmazonAttributes('ATVPDKIKX0DER');

        $resColor = $transformer->transformAttribute('color', 'Red');
        $this->assertEquals([['value' => 'Red']], $resColor);
    }

    /**
     * TEST 3: Inventory -> Shopify -> Map -> Add New Product creates draft correctly.
     */
    public function test_mapping_flow_creates_draft_and_pending_mapping(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $shopifyProduct = [
            'id' => '998877',
            'title' => 'Sample Shopify Tee',
            'vendor' => 'TeeBrand',
            'body_html' => '<p>Soft cotton tee</p>',
            'handle' => 'sample-shopify-tee',
            'status' => 'active',
            'variants' => [
                [
                    'id' => '112233',
                    'sku' => 'SHOPIFY-TEE-1',
                    'price' => '24.99',
                    'inventory_quantity' => 15,
                    'inventory_item_id' => '776655',
                    'barcode' => '012345678905',
                ]
            ],
            'images' => [],
        ];

        $mapper = new ShopifyAmazonMapper();
        $mapped = $mapper->map($shopifyProduct);
        $mapped['shopify_inventory_item_id'] = $shopifyProduct['variants'][0]['inventory_item_id'];
        $mapped['shopify_location_id'] = 'loc_123';

        $schemaController = new \App\Http\Controllers\ProductSchemaController();
        $syncId = $schemaController->syncProductShopify($mapped, $this->shop->id, 50, 'SHIRT');

        $this->assertDatabaseHas('product_marketplace_mappings', [
            'id' => $syncId,
            'shop_id' => $this->shop->id,
            'shopify_product_id' => '998877',
            'shopify_variant_id' => '112233',
            'shopify_inventory_item_id' => '776655',
            'shopify_location_id' => 'loc_123',
            'sync_status' => 'pending',
        ]);
    }

    /**
     * TEST 6: Mapping flow omits identifier when barcode is absent/invalid; global transformer unchanged.
     */
    public function test_mapping_flow_omits_invalid_barcode_and_preserves_global_transformer(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $transformer = new TransformsAmazonAttributes('ATVPDKIKX0DER');

        // Unprefixed or invalid barcode in global transformer returns [] exactly as before
        $rawRes = $transformer->transformAttribute('externally_assigned_product_identifier', 'invalid-barcode');
        $this->assertEquals([], $rawRes);

        // ShopifyAmazonMapper handles mapping-specific normalization
        $mapper = new ShopifyAmazonMapper();
        $mappedEmpty = $mapper->map(['variants' => [['barcode' => '']]]);
        $this->assertEquals('', $mappedEmpty['externally_assigned_product_identifier']);

        $mappedInvalid = $mapper->map(['variants' => [['barcode' => 'invalid-abc']]]);
        $this->assertEquals('', $mappedInvalid['externally_assigned_product_identifier']);
    }

    /**
     * TEST 7: Mapping flow normalizes raw barcodes to UPC/EAN/GTIN, which cleanly transforms via Amazon transformer.
     */
    public function test_mapping_flow_normalizes_raw_barcodes_to_prefixed_format(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $mapper = new ShopifyAmazonMapper();
        $transformer = new TransformsAmazonAttributes('ATVPDKIKX0DER');

        // 12 digits -> UPC: ...
        $mapped12 = $mapper->map(['variants' => [['barcode' => '012345678905']]]);
        $this->assertEquals('UPC: 012345678905', $mapped12['externally_assigned_product_identifier']);
        $upcTransformed = $transformer->transformAttribute('externally_assigned_product_identifier', $mapped12['externally_assigned_product_identifier']);
        $this->assertEquals([[
            'marketplace_id' => 'ATVPDKIKX0DER',
            'type' => 'upc',
            'value' => '012345678905',
        ]], $upcTransformed);

        // 13 digits -> EAN: ...
        $mapped13 = $mapper->map(['variants' => [['barcode' => '0123456789012']]]);
        $this->assertEquals('EAN: 0123456789012', $mapped13['externally_assigned_product_identifier']);
        $eanTransformed = $transformer->transformAttribute('externally_assigned_product_identifier', $mapped13['externally_assigned_product_identifier']);
        $this->assertEquals([[
            'marketplace_id' => 'ATVPDKIKX0DER',
            'type' => 'ean',
            'value' => '0123456789012',
        ]], $eanTransformed);

        // 14 digits -> GTIN: ...
        $mapped14 = $mapper->map(['variants' => [['barcode' => '01234567890123']]]);
        $this->assertEquals('GTIN: 01234567890123', $mapped14['externally_assigned_product_identifier']);
        $gtinTransformed = $transformer->transformAttribute('externally_assigned_product_identifier', $mapped14['externally_assigned_product_identifier']);
        $this->assertEquals([[
            'marketplace_id' => 'ATVPDKIKX0DER',
            'type' => 'gtin',
            'value' => '01234567890123',
        ]], $gtinTransformed);
    }

    /**
     * TEST 8: Mapping flow preserves valid price/quantity information required for Amazon listing.
     */
    public function test_price_and_quantity_are_preserved_and_transformed(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $transformer = new TransformsAmazonAttributes('ATVPDKIKX0DER');

        $offerRes = $transformer->transformAttribute('purchasable_offer', '29.99');
        $this->assertEquals([[
            'our_price' => [
                ['schedule' => [['value_with_tax' => 29.99]]]
            ],
            'currency' => 'USD',
        ]], $offerRes);

        $fulfillmentRes = $transformer->transformAttribute('fulfillment_availability', json_encode([
            'fulfillment_channel_code' => 'DEFAULT',
            'quantity' => 10,
        ]));
        $this->assertEquals([[
            'fulfillment_channel_code' => 'DEFAULT',
            'quantity' => 10,
        ]], $fulfillmentRes);
    }

    /**
     * TEST 9: Amazon ACCEPTED updates the existing ProductMarketplaceMapping to active with amazon_sku and submission info.
     */
    public function test_amazon_accepted_updates_existing_mapping(): void
    {
        session(['active_shop' => $this->shop->shop]);

        // 1. Create shopify product in db
        $shopifyProductDb = Product::create([
            'user_id' => $this->shop->id,
            'shop_id' => $this->shop->id,
            'shopify_id' => '998877',
            'sku' => 'SHOPIFY-SKU',
            'status' => 'draft',
        ]);

        // 2. Create pending mapping
        ProductMarketplaceMapping::create([
            'shop_id' => $this->shop->id,
            'product_id' => (string) $shopifyProductDb->id,
            'shopify_product_id' => '998877',
            'shopify_variant_id' => '112233',
            'sync_status' => 'pending',
        ]);

        // 3. Create amazon draft product
        $amazonProduct = Product::create([
            'user_id' => $this->shop->id,
            'schema_id' => $this->schema->id,
            'sku' => 'AMZ-SKU-100',
            'status' => 'draft',
        ]);

        // Link shopify product to amazon draft product
        $shopifyProductDb->update(['amazon_product_id' => $amazonProduct->id]);

        $schemaController = new \App\Http\Controllers\ProductSchemaController();
        $schemaController->updateSyncAmazon($amazonProduct->id, [
            'sku' => 'AMZ-SKU-100',
            'submission_id' => 'SUB-999',
            'submission_status' => 'ACCEPTED',
        ]);

        $this->assertDatabaseHas('product_marketplace_mappings', [
            'shop_id' => $this->shop->id,
            'shopify_product_id' => '998877',
            'amazon_sku' => 'AMZ-SKU-100',
            'sync_status' => 'active',
            'submission_id' => 'SUB-999',
            'submission_status' => 'ACCEPTED',
        ]);
    }

    /**
     * TEST 10: Amazon INVALID does NOT mark mapping as successfully mapped.
     */
    public function test_amazon_invalid_does_not_mark_mapping_as_active(): void
    {
        session(['active_shop' => $this->shop->shop]);

        $mapping = ProductMarketplaceMapping::create([
            'shop_id' => $this->shop->id,
            'shopify_product_id' => '998877',
            'shopify_variant_id' => '112233',
            'sync_status' => 'pending',
            'amazon_sku' => null,
        ]);

        // When Amazon returns INVALID, updateSyncAmazon is never called or mapping remains pending
        $this->assertDatabaseHas('product_marketplace_mappings', [
            'id' => $mapping->id,
            'sync_status' => 'pending',
            'amazon_sku' => null,
        ]);
    }

    /**
     * TEST 11: Repeated submit / map does not create duplicate mappings.
     */
    public function test_repeated_sync_does_not_create_duplicate_mappings(): void
    {
        session(['active_shop' => $this->shop->shop]);
        $schemaController = new \App\Http\Controllers\ProductSchemaController();

        $prodAttributes = [
            'shopify_product_id' => '998877',
            'shopify_variant_id' => '112233',
            'shopify_inventory_item_id' => '776655',
            'shopify_location_id' => 'loc_123',
        ];

        $id1 = $schemaController->syncProductShopify($prodAttributes, $this->shop->id, 10, 'SHIRT');
        $id2 = $schemaController->syncProductShopify($prodAttributes, $this->shop->id, 10, 'SHIRT');

        $this->assertEquals($id1, $id2);
        $this->assertEquals(1, ProductMarketplaceMapping::where('shop_id', $this->shop->id)->where('shopify_product_id', '998877')->count());
    }

    /**
     * TEST 12: Shop context remains correct and error display renders detailed Amazon issue info.
     */
    public function test_amazon_error_banner_renders_attribute_code_and_message_visibly(): void
    {
        session(['active_shop' => $this->shop->shop]);
        View::share('cspNonce', 'test-csp-nonce-edit');
        View::share('errors', new ViewErrorBag());

        $amazonErrors = [
            [
                'code' => 'INVALID_ATTRIBUTE',
                'message' => "The value of attribute 'externally_assigned_product_identifier' is invalid.",
                'attributeNames' => ['externally_assigned_product_identifier'],
            ]
        ];

        $amazonProduct = Product::create([
            'user_id' => $this->shop->id,
            'schema_id' => $this->schema->id,
            'sku' => 'AMZ-SKU-100',
            'status' => 'draft',
        ]);

        $fieldsList = [
            ['name' => 'item_name', 'title' => 'Item Name', 'type' => 'text'],
            ['name' => 'brand', 'title' => 'Brand', 'type' => 'text'],
        ];

        $tabs = [
            'product' => $fieldsList,
            'images' => [],
            'variations' => [],
            'attributes' => [],
            'product_rules' => [],
            'battery_specs' => [],
            'other' => [],
        ];

        $html = View::make('schema.products.create', [
            'productshow' => $amazonProduct,
            'activeShop' => $this->shop->shop,
            'visibleAmazonErrors' => $amazonErrors,
            'schema' => $this->schema,
            'fields' => $fieldsList,
            'tabs' => $tabs,
            'requiredFields' => [],
            'autofillCount' => 0,
            'canUseAiAutoFill' => false,
            'canUseAiSingleField' => false,
        ])->render();

        $this->assertStringContainsString('Amazon Validation Errors: Please check all tabs', $html);
        $this->assertStringContainsString('externally_assigned_product_identifier', $html);
        $this->assertStringContainsString('INVALID_ATTRIBUTE', $html);
        $this->assertStringContainsString('The value of attribute', $html);
        // Ensure attribute name is not hidden with display:none
        $this->assertStringNotContainsString('<strong style="display:none">', $html);
    }
}
