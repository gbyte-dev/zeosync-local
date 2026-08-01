<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Api\ListingsApi;
use SellingPartnerApi\Seller\ProductTypeDefinitionsV20200901\Requests\GetDefinitionsProductType;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use Illuminate\Support\Facades\DB;
use App\Services\CategoryService;
use App\Jobs\CheckStoreStatusJob;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use DateTime;
use App\Models\Category;
use App\Models\Shop;
use App\Models\Plan;
use App\Models\ShopifySubscription;

class TestController extends Controller
{
    private $connector;
    private array $credentials = [];

    public function __construct()
    {

        $clientId = DB::table('admin_settings')
            ->where('option_key', 'production_client_id')
            ->value('option_value');

        $clientSecret = DB::table('admin_settings')
            ->where('option_key', 'production_client_secret')
            ->value('option_value');

        $shop = request()->shop ?? session('active_shop');

        if (auth()->check()) {

            $refreshToken = DB::table('admin_settings')
                ->where('option_key', 'amazon_refresh_token')
                ->value('option_value');
        } else {
            if ($shop) {

                $usertoken = Shop::where('shop', $shop)->first();
                $refreshToken = $usertoken->amazon_refresh_token;
                $sellerid =  $usertoken->amazon_seller_id;
                $amazon_marketplace_id =  $usertoken->amazon_marketplace_id;
            } else {

                $refreshToken = DB::table('admin_settings')
                    ->where('option_key', 'amazon_refresh_token')
                    ->value('option_value');
            }
        }

        $this->credentials = [
            'seller_id'      => $sellerid ?? '',
            'marketplace_id' => $amazon_marketplace_id ?? 'ATVPDKIKX0DER',
            'refresh_token'  => $refreshToken
        ];
    }

    private function getAmazonConnector()
    {
        if (!$this->connector) {

            $clientId = DB::table('admin_settings')
                ->where('option_key', 'production_client_id')
                ->value('option_value');

            $clientSecret = DB::table('admin_settings')
                ->where('option_key', 'production_client_secret')
                ->value('option_value');

            $shop = request()->shop ?? session('active_shop');

            if (auth()->check()) {

                $refreshToken = DB::table('admin_settings')
                    ->where('option_key', 'amazon_refresh_token')
                    ->value('option_value');
            } else {
                if ($shop) {

                    $usertoken = Shop::where('shop', $shop)->first();
                    $refreshToken = $usertoken->amazon_refresh_token;
                    if (!$refreshToken) {
                        return $this->connector = null;
                    }
                    // ONLY ADD THIS
                    $this->credentials = [
                        'seller_id'      => $usertoken->amazon_seller_id,
                        'marketplace_id' => $usertoken->amazon_marketplace_id,
                        'refresh_token'  => $usertoken->amazon_refresh_token,
                        'region'         => $usertoken->amazon_mws_region,
                        'endpoint'       => $usertoken->amazon_endpoint,
                    ];
                } else {

                    return  $this->connector = null;
                }
            }
            Log::info('Amazon Connector', [
                'client_id' => $clientId,
                'client_secret_sha1' => sha1($clientSecret),
                'refresh_token_sha1' => sha1($refreshToken),
            ]);

            $this->connector = SellingPartnerApi::seller(
                clientId: $clientId,
                clientSecret: $clientSecret,
                refreshToken: $refreshToken,
                endpoint: Endpoint::NA,
            );
        }

        return $this->connector;
    }


    public function test($type = 'RING')
    {
        $connector = $this->getAmazonConnector();
        if (!$connector) {
            return response()->json(['status' => false, 'message' => "No Active Shop"]);
        }
        // get market place participations
        // $data = $connector->sellersV1()->getMarketplaceParticipations();
        // get listing item
        //  $data = $this->createSandboxProduct();
        // get listing item
        // $data =$this->getListingItem();
        // update listing item
        // $data = $this->updateSandboxProduct();
        // get all listing items
        //  $data = $this->getAllSellerProducts();
        // $data = $this->getShirtDefinitions();
        // $data = $this->addProductWithoutVariation();
        // $data = $this->updateProductWithoutVariation();
         $data = $this->getUnitCountSchema($type);
        //    $data = $this->testdata();
        //  $data = $this->getAllProductTypes();
        // $data = $this->getListingStatus('GQR1FGUVQIYB');
        // $data = $this->testBeautyData(); 
        return response()->json($data);
        return view('allproducts/toy');
    }
    public function testCategoryMapping()
    {
        $service = new CategoryService();
        $result = $service->updateParentBySearch(
            $keywords = [
                // industrial / construction
                'hardware',
                'industrial hardware',
                'construction hardware',
                'bolt',
                'nut',
                'screw',
                'fastener',
                'drill',
                'hammer',
                'spanner',
                'adhesive',
                'sealant',
                'abrasive',
                'metal parts',
                'building material',
                // computer hardware
                'computer hardware',
                'motherboard',
                'processor',
                'cpu',
                'ram',
                'ssd',
                'hard disk',
                'graphics card',
                'gpu',
                'power supply',
                'smps',
                // electrical
                'electrical hardware',
                'switch',
                'socket',
                'circuit breaker',
                'wire',
                'cable',
                'fuse',
                'electrical panel',
                'distribution board',
                // plumbing
                'plumbing hardware',
                'pipe',
                'pipe fitting',
                'tap',
                'valve',
                'faucet',
                'water connector',
                // tools
                'hand tools',
                'power tools',
                'tool kit',
                'wrench',
                'screwdriver',
                'cutting tool',
                'measuring tool'
            ], // 🔥 keywords
            40,                   // 🔥 parent_id
            false                 // partial match
        );
        return response()->json($result);
    }
    public function createSandboxProduct()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $variations = [
                ['sku' => 'TEST-SKU-1001-BLUE-MEDIUM', 'size' => 'M', 'color' => 'Blue'],
                ['sku' => 'TEST-SKU-1001-BLUE-LARGE', 'size' => 'L', 'color' => 'Blue'],
                ['sku' => 'TEST-SKU-1001-RED-MEDIUM', 'size' => 'M', 'color' => 'Red'],
                ['sku' => 'TEST-SKU-1001-RED-LARGE', 'size' => 'L', 'color' => 'Red']
            ];
            $createdProducts = [];
            foreach ($variations as $variation) {
                $attributes = [
                    'item_name' => [['value' => 'Sandbox Product Test Item', 'language_tag' => 'en_US']],
                    'brand' => [['value' => 'Test Brand']],
                    'color' => [['value' => $variation['color']]],
                    'department' => [['value' => 'mens']],
                    'target_gender' => [['value' => 'unisex']],
                    'age_range_description' => [['value' => 'child']],
                    'model_name' => [['value' => 'Test Model']],
                    'item_type_keyword' => [['value' => 't-shirt']],
                    'list_price' => [['value' => 19.99, 'currency' => 'USD']],
                    'condition_type' => [['value' => 'new_new']],
                    'purchasable_offer' => [[
                        'our_price' => [[
                            'schedule' => [[
                                'value_with_tax' => 19.99
                            ]]
                        ]],
                        'map_price' => [[
                            'schedule' => [[
                                'value_with_tax' => 24.99
                            ]]
                        ]],
                        'currency' => 'USD'
                    ]],
                    'shirt_size' => [[
                        'size_system' => 'as1',
                        'size_class' => 'alpha',
                        'size' => strtolower($variation['size']),
                    ]],
                    'fabric_type' => [['value' => '100% Cotton']],
                    'fit_type' => [['value' => 'Regular']],
                    'neck' => [[
                        'neck_style' => [[
                            'value' => 'Crew Neck',
                            'language_tag' => 'en_US'
                        ]]
                    ]],
                    'sleeve' => [[
                        'type' => [[
                            'value' => 'Short Sleeve',
                            'language_tag' => 'en_US'
                        ]],
                        'length_description' => [[
                            'value' => 'short_sleeve'
                        ]]
                    ]],
                    'style' => [['value' => 'Classic']],
                    'unit_count' => [[
                        'value' => 1,
                        'type' => [
                            'value' => 'Count',
                            'language_tag' => 'en_US'
                        ]
                    ]],
                    'fulfillment_availability' => [['fulfillment_channel_code' => 'AMAZON_NA', 'quantity' => 10]],
                    'item_package_dimensions' => [[
                        'length' => ['value' => 10, 'unit' => 'inches'],
                        'width' => ['value' => 8, 'unit' => 'inches'],
                        'height' => ['value' => 1, 'unit' => 'inches']
                    ]],
                    'item_package_weight' => [['value' => 0.5, 'unit' => 'pounds']],
                    'externally_assigned_product_identifier' => [['type' => 'ean', 'value' => '4006381333931']],
                    'merchant_suggested_asin' => [['value' => 'B08N5WRWNW']],
                    'product_description' => [['value' => 'Sandbox test product', 'language_tag' => 'en_US']],
                    'bullet_point' => [['value' => 'Test feature', 'language_tag' => 'en_US']],
                    'care_instructions' => [['value' => 'Machine wash', 'language_tag' => 'en_US']],
                    'country_of_origin' => [['value' => 'US']],
                    'import_designation' => [['value' => 'domestic']]
                ];
                $putRequest = new ListingsItemPutRequest(
                    productType: 'SHIRT',
                    attributes: $attributes,
                    requirements: 'LISTING'
                );
                $response = $listingsApi->putListingsItem(
                    sellerId: $this->credentials['seller_id'],
                    sku: $variation['sku'],
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    listingsItemPutRequest: $putRequest
                );
                $createdProducts[] = [
                    'sku' => $variation['sku'],
                    'size' => $variation['size'],
                    'color' => $variation['color'],
                    'response' => $response->json()
                ];
            }
            return response()->json([
                'success' => true,
                'message' => count($createdProducts) . ' variations submitted',
                'data' => $createdProducts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getListingItem()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $response = $listingsApi->getListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: 'TEST-SKU-1001',
                marketplaceIds: [$this->credentials['marketplace_id']]
            );
            \Log::info('Amazon Get Listing Item Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Listing item retrieved successfully',
                'data' => $response->json()
            ]);
        } catch (\Exception $e) {
            \Log::error('Amazon Get Listing Item Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getInventoryProducts()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            // Use Inventory API to get inventory
            $inventoryApi = $connector->fbaInventoryV1();
            // Get inventory summaries
            $response = $inventoryApi->getInventorySummaries(
                granularityType: 'Marketplace',
                granularityId: $this->credentials['marketplace_id'],
                marketplaceIds: [$this->credentials['marketplace_id']],
                details: true
            );
            \Log::info('Amazon Inventory Products Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Inventory products retrieved successfully',
                'data' => $response->json()
            ]);
        } catch (\Exception $e) {
            \Log::error('Amazon Inventory Products Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function getAllSellerProducts()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $allProducts = [];
            // Method 1: Try to get listings using search with date filter
            try {
                $listingsApi = $connector->listingsItemsV20210801();
                $response = $listingsApi->searchListingsItems(
                    sellerId: $this->credentials['seller_id'],
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    issueLocale: 'en_US',
                    createdAfter: new DateTime('2023-01-01')
                );
                if ($response->status() === 200) {
                    $listings = $response->json();
                    $allProducts['listings'] = $listings;
                    \Log::info('Listings found:', ['count' => count($listings['items'] ?? [])]);
                }
            } catch (\Exception $e) {
                \Log::warning('Listings API failed:', ['error' => $e->getMessage()]);
            }
            // Method 2: Get catalog items with keywords search
            try {
                $catalogApi = $connector->catalogItemsV20220401();
                $response = $catalogApi->searchCatalogItems(
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    identifiers: null,
                    identifiersType: null,
                    includedData: ['summaries', 'identifiers'],
                    locale: null,
                    sellerId: null,
                    keywords: null,
                    brandNames: null,
                    classificationIds: null,
                    pageToken: null,
                    keywordsLocale: null
                );
                if ($response->status() === 200) {
                    $catalog = $response->json();
                    $allProducts['catalog'] = $catalog;
                    \Log::info('Catalog items found:', ['count' => $catalog['numberOfResults'] ?? 0]);
                }
            } catch (\Exception $e) {
                \Log::warning('Catalog API failed:', ['error' => $e->getMessage()]);
            }
            // Method 3: Get inventory summaries (shows products with stock)
            try {
                $inventoryApi = $connector->fbaInventoryV1();
                $response = $inventoryApi->getInventorySummaries(
                    granularityType: 'Marketplace',
                    granularityId: $this->credentials['marketplace_id'],
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    details: true
                );
                if ($response->status() === 200) {
                    $inventory = $response->json();
                    $allProducts['inventory'] = $inventory;
                    \Log::info('Inventory items found:', ['count' => count($inventory['payload']['inventorySummaries'] ?? [])]);
                }
            } catch (\Exception $e) {
                \Log::warning('Inventory API failed:', ['error' => $e->getMessage()]);
            }
            // Method 4: Get orders (shows products that were sold)
            try {
                $ordersApi = $connector->ordersV0();
                $response = $ordersApi->getOrders(
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    createdAfter: new DateTime('2024-01-01')
                );
                if ($response->status() === 200) {
                    $orders = $response->json();
                    $allProducts['orders'] = $orders;
                    \Log::info('Orders found:', ['count' => count($orders['payload']['OrderItems'] ?? [])]);
                }
            } catch (\Exception $e) {
                \Log::warning('Orders API failed:', ['error' => $e->getMessage()]);
            }
            return response()->json([
                'success' => true,
                'message' => 'Seller products retrieved from multiple APIs',
                'data' => $allProducts,
                'summary' => [
                    'total_apis_tried' => 4,
                    'successful_apis' => count(array_filter($allProducts, fn($v) => !empty($v)))
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Get All Seller Products Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addProductWithoutVariation()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $sku = 'TEST-SKU-SINGLE-' . time();
            $attributes = [
                'item_name' => [[
                    'value' => 'Single hand shirt',
                    'language_tag' => 'en_US'
                ]],
                'brand' => [[
                    'value' => 'Sparky'
                ]],
                'manufacturer' => [[
                    'value' => 'Sparky'
                ]],
                'list_price' => [[
                    'value' => 0.05,
                    'currency' => 'USD'
                ]],
                'condition_type' => [[
                    'value' => 'new_new'
                ]],
                'fulfillment_availability' => [[
                    'fulfillment_channel_code' => 'AMAZON_NA',
                    'quantity' => 100
                ]],
                'product_description' => [[
                    'value' => 'This is a test product without variations',
                    'language_tag' => 'en_US'
                ]],
                'bullet_point' => [
                    [
                        'value' => 'Feature 1: High quality',
                        'language_tag' => 'en_US'
                    ],
                    [
                        'value' => 'Feature 2: Durable material',
                        'language_tag' => 'en_US'
                    ]
                ],
                'generic_keyword' => [
                    [
                        'value' => 'tshirt',
                        'language_tag' => 'en_US'
                    ]
                ],
                'item_package_dimensions' => [[
                    'length' => ['value' => 10, 'unit' => 'inches'],
                    'width' => ['value' => 8, 'unit' => 'inches'],
                    'height' => ['value' => 2, 'unit' => 'inches']
                ]],
                'item_package_weight' => [[
                    'value' => 1.5,
                    'unit' => 'pounds'
                ]],
                'country_of_origin' => [[
                    'value' => 'US'
                ]],
                'import_designation' => [[
                    'value' => 'domestic'
                ]],
                'age_range_description' => [[
                    'value' => 'adult'
                ]],
                'neck' => [[
                    'neck_style' => [[
                        'value' => 'Crew Neck',
                        'language_tag' => 'en_US'
                    ]]
                ]],
                'style' => [[
                    'value' => 'Classic'
                ]],
                'fit_type' => [[
                    'value' => 'Regular'
                ]],
                'fabric_type' => [[
                    'value' => '100% Cotton'
                ]],
                'care_instructions' => [[
                    'value' => 'Machine wash cold',
                    'language_tag' => 'en_US'
                ]],
                'externally_assigned_product_identifier' => [[
                    'type' => 'ean',
                    'value' => '4006381333931'
                ]],
                'department' => [[
                    'value' => 'mens'
                ]],
                'unit_count' => [[
                    'value' => 1,
                    'type' => [
                        'value' => 'Count',
                        'language_tag' => 'en_US'
                    ],
                    'marketplace_id' => $this->credentials['marketplace_id']
                ]],
                'merchant_suggested_asin' => [[
                    'value' => 'B08N5WRWNW'
                ]],
                'sleeve' => [[
                    'type' => [[
                        'value' => 'Long Sleeve',
                        'language_tag' => 'en_US'
                    ]],
                    'length_description' => [[
                        'value' => 'long_sleeve'
                    ]]
                ]],
                'shirt_size' => [[
                    'size_system' => 'as1',
                    'size_class' => 'alpha',
                    'size' => 'm'
                ]],
                'color' => [[
                    'value' => 'Blue'
                ]],
                'model_name' => [[
                    'value' => 'Test Model'
                ]],
                'item_type_keyword' => [[
                    'value' => 'Clothing, Shoes & Jewelry > Men > Clothing > Shirts > T-Shirts'
                ]],
                'target_gender' => [[
                    'value' => 'male'
                ]],
                'collar_style' => [[
                    'value' => 'Round Collar'
                ]]
            ];
            $putRequest = new ListingsItemPutRequest(
                productType: 'SHIRT',
                attributes: $attributes,
                requirements: 'LISTING'
            );
            $response = $listingsApi->putListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $sku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                listingsItemPutRequest: $putRequest
            );
            return response()->json([
                'success' => true,
                'message' => 'Product added successfully without variations',
                'sku' => $sku,
                'response' => $response->json()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function createOnlyListing($attributes, $type)
    {
        return  $putRequest = new ListingsItemPutRequest(
            productType: $type,
            attributes: $attributes,
            requirements: 'LISTING'
        );
    }

    public function checkStoreStatus()
    {
        $shops = Shop::where('is_active', 1)->get();

        foreach ($shops as $shop) {
            CheckStoreStatusJob::dispatch($shop);
        }

        return response()->json([
            'message' => 'Jobs dispatched successfully',
            'count' => $shops->count(),
        ]);
    }

    public function createOnlyputListing($putRequest, $sku)
    {
        $connector = $this->getAmazonConnector();
        if (!$connector) {
            return response()->json(['status' => false, 'message' => "No Active Shop"]);
        }
        $listingsApi = $connector->listingsItemsV20210801();
        $response = $listingsApi->putListingsItem(
            sellerId: $this->credentials['seller_id'],
            sku: $sku,
            marketplaceIds: [$this->credentials['marketplace_id']],
            listingsItemPutRequest: $putRequest
        );
        return  $response->json();
    }


    public function addProductVariation()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $parentSku = 'PARENT-SKU-' . time();
            // Define variations
            $variations = [
                ['sku' => $parentSku . '-BLUE-M', 'color' => 'Blue', 'size' => 'M'],
                ['sku' => $parentSku . '-BLUE-L', 'color' => 'Blue', 'size' => 'L'],
                ['sku' => $parentSku . '-RED-M', 'color' => 'Red', 'size' => 'M'],
                ['sku' => $parentSku . '-RED-L', 'color' => 'Red', 'size' => 'L'],
            ];
            $createdVariations = [];
            // Create parent product first
            $parentAttributes = [
                'item_name' => [[
                    'value' => 'Parent Product with Variations',
                    'language_tag' => 'en_US'
                ]],
                'brand' => [[
                    'value' => 'Test Brand'
                ]],
                'manufacturer' => [[
                    'value' => 'Test Manufacturer'
                ]],
                'list_price' => [[
                    'value' => 29.99,
                    'currency' => 'USD'
                ]],
                'condition_type' => [[
                    'value' => 'new_new'
                ]],
                'fulfillment_availability' => [[
                    'fulfillment_channel_code' => 'AMAZON_NA',
                    'quantity' => 100
                ]],
                'product_description' => [[
                    'value' => 'Parent product with multiple color and size variations',
                    'language_tag' => 'en_US'
                ]],
                'bullet_point' => [
                    [
                        'value' => 'Available in multiple colors and sizes',
                        'language_tag' => 'en_US'
                    ],
                    [
                        'value' => 'High quality material',
                        'language_tag' => 'en_US'
                    ]
                ],
                'item_package_dimensions' => [[
                    'length' => ['value' => 10, 'unit' => 'inches'],
                    'width' => ['value' => '8', 'unit' => 'inches'],
                    'height' => ['value' => '2', 'unit' => 'inches']
                ]],
                'item_package_weight' => [[
                    'value' => 1.5,
                    'unit' => 'pounds'
                ]],
                'country_of_origin' => [[
                    'value' => 'US'
                ]],
                'variation_theme' => [[
                    'value' => 'size_color'
                ]]
            ];
            $parentPutRequest = new ListingsItemPutRequest(
                productType: 'SHIRT',
                attributes: $parentAttributes,
                requirements: 'LISTING'
            );
            $parentResponse = $listingsApi->putListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $parentSku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                listingsItemPutRequest: $parentPutRequest
            );
            $createdVariations[] = [
                'type' => 'parent',
                'sku' => $parentSku,
                'response' => $parentResponse->json()
            ];
            // Create child variations
            foreach ($variations as $variation) {
                $childAttributes = [
                    'item_name' => [[
                        'value' => 'Child Product - ' . $variation['color'] . ' Size ' . $variation['size'],
                        'language_tag' => 'en_US'
                    ]],
                    'brand' => [[
                        'value' => 'Test Brand'
                    ]],
                    'manufacturer' => [[
                        'value' => 'Test Manufacturer'
                    ]],
                    'color' => [[
                        'value' => $variation['color']
                    ]],
                    'shirt_size' => [[
                        'size_system' => 'as1',
                        'size_class' => 'regular',
                        'size_value' => $variation['size']
                    ]],
                    'list_price' => [[
                        'value' => 29.99,
                        'currency' => 'USD'
                    ]],
                    'condition_type' => [[
                        'value' => 'new_new'
                    ]],
                    'fulfillment_availability' => [[
                        'fulfillment_channel_code' => 'AMAZON_NA',
                        'quantity' => 50
                    ]],
                    'product_description' => [[
                        'value' => 'Variation: ' . $variation['color'] . ' color, size ' . $variation['size'],
                        'language_tag' => 'en_US'
                    ]],
                    'bullet_point' => [
                        [
                            'value' => 'Color: ' . $variation['color'],
                            'language_tag' => 'en_US'
                        ],
                        [
                            'value' => 'Size: ' . $variation['size'],
                            'language_tag' => 'en_US'
                        ]
                    ],
                    'item_package_dimensions' => [[
                        'length' => ['value' => 10, 'unit' => 'inches'],
                        'width' => ['value' => '8', 'unit' => 'inches'],
                        'height' => ['value' => '2', 'unit' => 'inches']
                    ]],
                    'item_package_weight' => [[
                        'value' => 1.5,
                        'unit' => 'pounds'
                    ]],
                    'country_of_origin' => [[
                        'value' => 'US'
                    ]],
                    'parent_child' => [[
                        'parent_sku' => $parentSku,
                        'relationship' => 'child'
                    ]]
                ];
                $childPutRequest = new ListingsItemPutRequest(
                    productType: 'SHIRT',
                    attributes: $childAttributes,
                    requirements: 'LISTING'
                );
                $childResponse = $listingsApi->putListingsItem(
                    sellerId: $this->credentials['seller_id'],
                    sku: $variation['sku'],
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    listingsItemPutRequest: $childPutRequest
                );
                $createdVariations[] = [
                    'type' => 'child',
                    'sku' => $variation['sku'],
                    'color' => $variation['color'],
                    'size' => $variation['size'],
                    'response' => $childResponse->json()
                ];
            }
            return response()->json([
                'success' => true,
                'message' => 'Parent product and variations added successfully',
                'parent_sku' => $parentSku,
                'total_variations' => count($variations),
                'data' => $createdVariations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function getAllProductTypes()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $definitions = $connector->productTypeDefinitionsV20200901();
            $marketplaceId = $this->credentials['marketplace_id'];
            $response = $definitions->searchDefinitionsProductTypes(
                marketplaceIds: [$marketplaceId],
                keywords: []
            );
            $data = $response->json();
            $allTypes = [];
            if (isset($data['productTypes']) && is_array($data['productTypes'])) {
                foreach ($data['productTypes'] as $type) {
                    $allTypes[] = [
                        'name' => $type['name'] ?? null,
                        'displayName' => $type['displayName'] ?? null,
                        'marketplaceIds' => $type['marketplaceIds'] ?? [],
                    ];
                    $cat = Category::where('category', $type['name'])->first();
                    if (!$cat) {
                        $parent = Category::create([
                            'name' => $type['displayName'] ?? null,
                            'category' => $type['name'] ?? null,
                            'slug' => $type['name'] ?? null,
                            'level' => 1,
                            'parent_id' => 10,
                            'marketplaceIds' => json_encode($type['marketplaceIds'] ?? [])
                        ]);
                    }
                }
            }
            return response()->json([
                'success' => true,
                'total' => count($allTypes),
                'productTypes' => $allTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function getUnitCountSchema($type = 'SHIRT')
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $definitions = $connector->productTypeDefinitionsV20200901();
            // Fetch SHIRT schema
            $response = $definitions->getDefinitionsProductType(
                $type,
                [$this->credentials['marketplace_id']]
            );
            $schema = $response->json();
            $scemaurl  = $schema['schema']['link']['resource'] ?? null;
            $unitCount = $schema['properties']['unit_count'] ?? 'NOT FOUND';
            if ($scemaurl) {
                $mainSchema = file_get_contents($scemaurl);
            }
            return response()->json([
                'unit_count' => $unitCount,
                'full_schema' => $schema,
                'main_schema' => json_decode($mainSchema)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function testdata()
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $sku = 'TEST-SKU-TOYGUN-' . time();
            $marketplaceId = $this->credentials['marketplace_id'];
            $attributes = [
                'item_name' => [[
                    'value' => 'Premium Toy Gun with Foam Bullets',
                    'language_tag' => 'en_US'
                ]],
                'brand' => [[
                    'value' => 'Test Brand'
                ]],
                'manufacturer' => [[
                    'value' => 'Test Brand'
                ]],
                'part_number' => [[
                    'value' => 'TB-FB3000'
                ]],
                'externally_assigned_product_identifier' => [[
                    'type' => 'ean',
                    'value' => '4006381333931'
                ]],
                'product_description' => [[
                    'value' => 'A fun and safe toy gun with soft foam bullets for kids. Made from durable non-toxic plastic.',
                    'language_tag' => 'en_US'
                ]],
                'bullet_point' => [
                    ['value' => 'Soft foam bullets for safe play', 'language_tag' => 'en_US'],
                    ['value' => 'Durable non-toxic plastic construction', 'language_tag' => 'en_US'],
                    ['value' => 'Recommended for ages 8 and up', 'language_tag' => 'en_US']
                ],
                'generic_keyword' => [
                    ['value' => 'toy gun foam bullets', 'language_tag' => 'en_US']
                ],
                'list_price' => [[
                    'value' => 0.01,
                    'currency' => 'USD'
                ]],
                'condition_type' => [[
                    'value' => 'new_new'
                ]],
                'fulfillment_availability' => [[
                    'fulfillment_channel_code' => 'AMAZON_NA',
                    'quantity' => 100
                ]],
                'item_length_width_height' => [[
                    'length' => ['value' => 12, 'unit' => 'inches'],
                    'width' => ['value' => 6, 'unit' => 'inches'],
                    'height' => ['value' => 3, 'unit' => 'inches']
                ]],
                'item_package_dimensions' => [[
                    'length' => ['value' => 12, 'unit' => 'inches'],
                    'width' => ['value' => 6, 'unit' => 'inches'],
                    'height' => ['value' => 3, 'unit' => 'inches']
                ]],
                'item_package_weight' => [[
                    'value' => 1.0,
                    'unit' => 'pounds'
                ]],
                'country_of_origin' => [[
                    'value' => 'US'
                ]],
                'included_components' => [[
                    'value' => '1 Toy Gun, 10 Foam Bullets',
                    'language_tag' => 'en_US'
                ]],
                'number_of_items' => [[
                    'value' => 1
                ]],
                'batteries_required' => [[
                    'value' => 'false'
                ]],
                'safety_warning' => [[
                    'value' => 'Choking Hazard - Small parts. Not for children under 3 years.',
                    'language_tag' => 'en_US'
                ]],
                'manufacturer_minimum_age' => [[
                    'value' => 96,
                    'unit' => 'months'
                ]],
                'manufacturer_maximum_age' => [[
                    'value' => 156,
                    'unit' => 'months'
                ]],
                'target_audience_keyword' => [[
                    'value' => 'children',
                    'language_tag' => 'en_US'
                ]],
                'target_audience' => [[
                    'value' => 'children'
                ]],
                'is_assembly_required' => [[
                    'value' => 'false'
                ]],
                'cpsia_cautionary_statement' => [[
                    'value' => 'choking_hazard_small_parts',
                    'language_tag' => 'en_US'
                ]],
                'number_of_boxes' => [[
                    'value' => 1
                ]],
                'supplier_declared_dg_hz_regulation' => [[
                    'value' => 'not_applicable'
                ]],
                'theme' => [[
                    'value' => 'Action'
                ]],
                'material' => [[
                    'value' => 'plastic'
                ]],
                'age_range_description' => [[
                    'value' => '8-13 years'
                ]],
                'color' => [[
                    'value' => 'Black'
                ]],
                'model_name' => [[
                    'value' => 'Foam Blaster 3000'
                ]],
                'unit_count' => [[
                    'value' => 1,
                    'type' => [
                        'value' => 'Count',
                        'language_tag' => 'en_US'
                    ]
                ]],
                'item_type_keyword' => [[
                    'value' => 'Toys & Games > Toy Guns & Blasters'
                ]],
                'skip_offer' => [[
                    'value' => 'true'
                ]]
            ];
            $putRequest = new ListingsItemPutRequest(
                productType: 'TOY_GUN',
                attributes: $attributes,
                requirements: 'LISTING'
            );
            $response = $listingsApi->putListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $sku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                listingsItemPutRequest: $putRequest
            );
            return response()->json([
                'success' => true,
                'message' => 'TOY_GUN product added successfully',
                'sku' => $sku,
                'response' => $response->json()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function testBeautyData()
    {
        try {
            $connector = $this->getAmazonConnector();
            $listingsApi = $connector->listingsItemsV20210801();
            $sku = 'TEST-SKU-BEAUTY-' . time();
            $marketplaceId = $this->credentials['marketplace_id'];
            $attributes = [
                'item_name' => [[
                    'value' => 'Premium Facial Serum with Vitamin C',
                    'language_tag' => 'en_US'
                ]],
                'brand' => [[
                    'value' => 'Beauty Brand'
                ]],
                'manufacturer' => [[
                    'value' => 'Beauty Brand Inc'
                ]],
                'part_number' => [[
                    'value' => 'BS-VC5000'
                ]],
                'merchant_suggested_asin' => [[
                    'value' => 'B08XZP2P7K'
                ]],
                'product_description' => [[
                    'value' => 'Advanced anti-aging facial serum with Vitamin C, Hyaluronic Acid, and Niacinamide. Brightens skin, reduces dark spots, and improves elasticity.',
                    'language_tag' => 'en_US'
                ]],
                'bullet_point' => [
                    ['value' => 'Contains 20% Vitamin C for skin brightening', 'language_tag' => 'en_US'],
                    ['value' => 'Hyaluronic Acid for deep hydration', 'language_tag' => 'en_US'],
                    ['value' => 'Niacinamide to reduce dark spots and improve texture', 'language_tag' => 'en_US'],
                    ['value' => 'Suitable for all skin types', 'language_tag' => 'en_US'],
                    ['value' => 'Cruelty-free and paraben-free formula', 'language_tag' => 'en_US']
                ],
                'generic_keyword' => [
                    ['value' => 'facial serum vitamin c', 'language_tag' => 'en_US'],
                    ['value' => 'anti aging serum', 'language_tag' => 'en_US'],
                    ['value' => 'skin brightening serum', 'language_tag' => 'en_US']
                ],
                'list_price' => [[
                    'value' => 0.1,
                    'currency' => 'USD'
                ]],
                'condition_type' => [[
                    'value' => 'new_new'
                ]],
                'fulfillment_availability' => [[
                    'fulfillment_channel_code' => 'AMAZON_NA',
                    'quantity' => 50
                ]],
                'item_package_dimensions' => [[
                    'length' => ['value' => 5, 'unit' => 'inches'],
                    'width' => ['value' => 2, 'unit' => 'inches'],
                    'height' => ['value' => 2, 'unit' => 'inches']
                ]],
                'item_package_weight' => [[
                    'value' => 0.5,
                    'unit' => 'pounds'
                ]],
                'country_of_origin' => [[
                    'value' => 'US'
                ]],
                'number_of_items' => [[
                    'value' => 1
                ]],
                'item_form' => [[
                    'value' => 'liquid'
                ]],
                'item_package_quantity' => [[
                    'value' => 1
                ]],
                'scent' => [[
                    'value' => 'unscented'
                ]],
                'target_audience_keyword' => [[
                    'value' => 'adults',
                    'language_tag' => 'en_US'
                ]],
                'item_type_keyword' => [[
                    'value' => 'Beauty > Skin Care > Face > Serums'
                ]],
                'is_heat_sensitive' => [[
                    'value' => 'false'
                ]],
                'safety_warning' => [[
                    'value' => 'For external use only. Avoid contact with eyes. Discontinue use if irritation occurs.',
                    'language_tag' => 'en_US'
                ]],
                'lifestyle' => [[
                    'value' => 'classic'
                ]],
                'supplier_declared_dg_hz_regulation' => [[
                    'value' => 'not_applicable'
                ]],
                'contains_liquid_contents' => [[
                    'value' => 'true'
                ]],
                'is_liquid_double_sealed' => [[
                    'value' => 'true'
                ]],
                'liquid_volume' => [[
                    'value' => 1.0,
                    'unit' => 'fluid_ounces'
                ]],
                'fc_shelf_life' => [[
                    'value' => 730,
                    'unit' => 'days'
                ]],
                'product_expiration_type' => [[
                    'value' => 'Shelf Life',
                    'language_tag' => 'en_US'
                ]],
                'batteries_required' => [[
                    'value' => 'false'
                ]],
                'color' => [[
                    'value' => 'Clear'
                ]],
                'is_expiration_dated_product' => [[
                    'value' => 'true'
                ]],
                'unit_count' => [[
                    'value' => 1,
                    'type' => [
                        'value' => 'Count',
                        'language_tag' => 'en_US'
                    ]
                ]],
                'skip_offer' => [[
                    'value' => 'true'
                ]]
            ];
            $putRequest = new ListingsItemPutRequest(
                productType: 'BEAUTY',
                attributes: $attributes,
                requirements: 'LISTING'
            );
            $response = $listingsApi->putListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $sku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                listingsItemPutRequest: $putRequest
            );
            return response()->json([
                'success' => true,
                'message' => 'BEAUTY product added successfully',
                'sku' => $sku,
                'response' => $response->json()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function getListingData($sku)
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $response = $listingsApi->getListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $sku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                includedData: ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability']
            );
            $data = $response->json();
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getListingStatus($sku)
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $response = $listingsApi->getListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $sku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                includedData: ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability']
            );
            $data = $response->json();
            return response()->json([
                'sku' => $data['sku'] ?? null,
                'status' => $data['summaries'][0]['status'] ?? null,
                'itemName' => $data['attributes']['item_name'][0]['value'] ?? null,
                'price' => $data['offers'][0]['price']['sellingPrice'] ?? null,
                'quantity' => $data['fulfillmentAvailability'][0]['quantity'] ?? null,
                'fulfillmentChannel' => $data['fulfillmentAvailability'][0]['fulfillmentChannelCode'] ?? null,
                'issues' => $data['issues'] ?? [],
                'full_response' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getDownloadSchema($type = 'SHIRT')
    {
        try {
            $connector = $this->getAmazonConnector();

            $definitions = $connector->productTypeDefinitionsV20200901();
            // Fetch SHIRT schema
            $response = $definitions->getDefinitionsProductType(
                $type,
                [$this->credentials['marketplace_id']]
            );
            $schema = $response->json();
            $scemaurl  = $schema['schema']['link']['resource'] ?? null;
            $unitCount = $schema['properties']['unit_count'] ?? 'NOT FOUND';
            if ($scemaurl) {
                return  $mainSchema = file_get_contents($scemaurl);
            }
            return false;
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function getProductVariants($parentSku)
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return response()->json(['status' => false, 'message' => "No Active Shop"]);
            }
            $listingsApi = $connector->listingsItemsV20210801();
            $catalogApi = $connector->catalogItemsV20220401();
            $variantData = [];
            // Step 1: Get parent product data
            try {
                $parentResponse = $listingsApi->getListingsItem(
                    sellerId: $this->credentials['seller_id'],
                    sku: $parentSku,
                    marketplaceIds: [$this->credentials['marketplace_id']],
                    includedData: ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability', 'relationships', 'productTypes']
                );
                return  $parentData = $parentResponse->json();
                $variantData['parent'] = [
                    'sku' => $parentSku,
                    'data' => $parentData
                ];
            } catch (\Exception $e) {
                \Log::warning('Could not fetch parent product:', ['sku' => $parentSku, 'error' => $e->getMessage()]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Display Amazon product view page by fetching data directly from Amazon API.
     */
    public function amazonView($sku)
    {
        try {
            $connector = $this->getAmazonConnector();
            if (!$connector) {
                return view('schema.products.amazonview', [
                    'error' => 'No active Amazon connection. Please connect your Amazon account first.'
                ]);
            }

            $listingsApi = $connector->listingsItemsV20210801();
            $response = $listingsApi->getListingsItem(
                sellerId: $this->credentials['seller_id'],
                sku: $sku,
                marketplaceIds: [$this->credentials['marketplace_id']],
                includedData: ['summaries', 'attributes', 'issues', 'offers', 'fulfillmentAvailability', 'relationships', 'productTypes']
            );

            $data = $response->json();

            if (!$data || isset($data['errors'])) {
                $errorMsg = $data['errors'][0]['message'] ?? 'Failed to fetch product data from Amazon.';
                return view('schema.products.amazonview', [
                    'error' => $errorMsg
                ]);
            }

            $attributes = $data['attributes'] ?? [];
            $summaries = $data['summaries'][0] ?? [];
            $productTypes = $data['productTypes'][0] ?? [];
            $issues = $data['issues'] ?? [];
            $relationships = $data['relationships'] ?? [];

            // Build product object from Amazon data
            // Determine status from Amazon API response
            // Amazon returns statuses like: ACTIVE, BUYABLE, DRAFT, INCOMPLETE, CLOSED, etc.
            $amazonStatus = $data['status'] ?? null;
            // Also check summaries for a more accurate status
            $summaryStatus = $summaries['status'][0] ?? null;
            // Prefer summary status if available, otherwise use top-level status
            $resolvedStatus = $summaryStatus ?: ($amazonStatus ?: 'UNKNOWN');

            $product = (object) [
                'sku' => $sku,
                'status' => $resolvedStatus,
                'submission_status' => $summaryStatus,
                'submitted_on' => null,
                'parent_id' => null,
                'schema' => null,
                'filled_json' => json_encode($attributes),
                'final_json' => null,
                'created_at' => null,
                'updated_at' => null,
                'id' => $sku,
            ];

            // Extract attributes
            $allAttributes = [];
            $item_name = '';
            $brand = '';
            $description = '';
            $bullet_points = [];
            $images = [];
            $price = null;
            $purchasable_price = null;
            $manufacturer = '';
            $quantity = null;
            $condition = '';
            $productType = $productTypes['productType'] ?? '';

            foreach ($attributes as $key => $values) {
                if (empty($values) || !isset($values[0])) {
                    continue;
                }

                $firstVal = $values[0];
                $rawValue = $firstVal['value'] ?? $firstVal;

                $allAttributes[] = ['name' => $key, 'value' => is_array($rawValue) ? json_encode($rawValue) : $rawValue];

                switch ($key) {
                    case 'item_name':
                        $item_name = $firstVal['value'] ?? '';
                        break;
                    case 'brand':
                        $brand = $firstVal['value'] ?? '';
                        break;
                    case 'product_description':
                        $description = $firstVal['value'] ?? '';
                        break;
                    case 'manufacturer':
                        $manufacturer = $firstVal['value'] ?? '';
                        break;
                    case 'number_of_items':
                        $quantity = $firstVal['value'] ?? null;
                        break;
                    case 'condition_type':
                        $condition = $firstVal['value'] ?? '';
                        break;
                    case 'list_price':
                        $price = $firstVal['value'] ?? null;
                        break;
                    case 'purchasable_offer':
                        $purchasable_price = $firstVal['our_price'][0]['schedule'][0]['value_with_tax'] ?? null;
                        break;
                    case 'bullet_point':
                        foreach ($values as $bp) {
                            if (!empty($bp['value'])) {
                                $bullet_points[] = $bp['value'];
                            }
                        }
                        break;
                }

                // Images
                if (str_contains($key, 'image_locator') && !empty($firstVal['media_location'])) {
                    $images[] = $firstVal['media_location'];
                }
            }

            // Check for parent/child relationships
            $parentSku = null;
            $childSkus = [];
            $isParent = false;
            if (!empty($relationships)) {
                foreach ($relationships as $rel) {
                    if (isset($rel['relationships'][0]['parentSkus'])) {
                        $parentSku = $rel['relationships'][0]['parentSkus'][0] ?? null;
                    }
                    if (isset($rel['relationships'][0]['childSkus'])) {
                        $childSkus = $rel['relationships'][0]['childSkus'];
                        $isParent = true;
                    }
                }
            }

            // Fetch child variations if this is a parent product
            $variations = [];
            if ($isParent && !empty($childSkus)) {
                foreach ($childSkus as $childSku) {
                    try {
                        $childResponse = $listingsApi->getListingsItem(
                            sellerId: $this->credentials['seller_id'],
                            sku: $childSku,
                            marketplaceIds: [$this->credentials['marketplace_id']],
                            includedData: ['summaries', 'attributes', 'offers', 'fulfillmentAvailability']
                        );
                        $childData = $childResponse->json();
                        if ($childData && !isset($childData['errors'])) {
                            $childAttrs = $childData['attributes'] ?? [];
                            $childSummaries = $childData['summaries'][0] ?? [];
                            $childOffers = $childData['offers'][0] ?? [];

                            // Extract distinguishing variation attributes (color, size, etc.)
                            $variationAttrs = [];
                            $variationTheme = '';
                            foreach ($childAttrs as $attrKey => $attrVals) {
                                // Look for common variation-defining attributes
                                if (in_array($attrKey, ['color', 'size', 'shirt_size', 'item_package_quantity', 'flavor', 'scent', 'material', 'style', 'pattern', 'shape', 'wattage', 'voltage', 'capacity'])) {
                                    $val = $attrVals[0]['value'] ?? null;
                                    if ($val) {
                                        $variationAttrs[$attrKey] = $val;
                                    }
                                }
                                if ($attrKey === 'variation_theme') {
                                    $variationTheme = $attrVals[0]['value'] ?? '';
                                }
                            }

                            $variations[] = [
                                'sku' => $childSku,
                                'item_name' => $childAttrs['item_name'][0]['value'] ?? $childSku,
                                'status' => $childSummaries['status'][0] ?? $childData['status'] ?? 'UNKNOWN',
                                'price' => $childAttrs['list_price'][0]['value'] ?? null,
                                'offer_price' => $childOffers['price']['sellingPrice'] ?? null,
                                'quantity' => $childAttrs['fulfillment_availability'][0]['quantity'] ?? null,
                                'attributes' => $variationAttrs,
                                'image' => null,
                            ];

                            // Try to get an image
                            foreach ($childAttrs as $attrKey => $attrVals) {
                                if (str_contains($attrKey, 'image_locator') && !empty($attrVals[0]['media_location'])) {
                                    $variations[count($variations) - 1]['image'] = $attrVals[0]['media_location'];
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Could not fetch child variation: ' . $childSku, ['error' => $e->getMessage()]);
                    }
                }
            }

            // Ensure issues is passed to the view
            $issues = $issues ?? [];

            return view('schema.products.amazonview', compact(
                'product',
                'allAttributes',
                'item_name',
                'brand',
                'description',
                'bullet_points',
                'images',
                'price',
                'purchasable_price',
                'manufacturer',
                'quantity',
                'condition',
                'productType',
                'parentSku',
                'issues',
                'variations',
                'isParent',
                'childSkus'
            ));
        } catch (\Exception $e) {

            $errorMessage = 'Unable to fetch product details from Amazon.';
            $adminMessage = "Shop: " . (request()->shop ?? session('active_shop') ?? 'Unknown') . PHP_EOL;
            $adminMessage .= "SKU: {$sku}" . PHP_EOL;
            $adminMessage .= "Issue: Unable to fetch product details from Amazon." . PHP_EOL;

            if (preg_match('/Response:\s*(\{.*\})/s', $e->getMessage(), $matches)) {
                $response = json_decode($matches[1], true);

                if (!empty($response['errors'][0])) {
                    $amazonError = $response['errors'][0];

                    $message = $amazonError['message'] ?? '';
                    $details = $amazonError['details'] ?? '';

                    $errorMessage = trim($message . (!empty($details) ? ' ' . $details : ''));

                    $adminMessage .= "Reason: {$message}" . PHP_EOL;
                    if (!empty($details)) {
                        $adminMessage .= "Details: {$details}";
                    }
                }
            } else {
                $adminMessage .= "Details: " . $e->getMessage();
            }

            \Log::error('Amazon Product Fetch Failed', [
                'shop' => request()->shop ?? session('active_shop'),
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            \App\Services\NotificationService::send(
                'amazon_api_error',
                'Amazon Product Fetch Failed',
                $adminMessage
            );

            return view('schema.products.amazonview', [
                'error' => $errorMessage
            ]);
        }
    }

    public function checkMailTest()
    {

        try {
            $title = 'Mail Test from Amazon API';
            $messageText = 'This is a test email to verify the mail configuration in Laravel.';
            $shop = Shop::where('shop', session('active_shop'))->first();
            $plan = Plan::where('id', 2)->first();
            $subscription = ShopifySubscription::where('shop_id', $shop->id)->first();
            \Mail::to('testmenow066@gmail.com')->send(new \App\Mail\WelcomeMail($shop));
            return response()->json(['message' => 'Test email sent successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
