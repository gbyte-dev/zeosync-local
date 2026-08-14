<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SellingPartnerApi\Seller\SellerConnector;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\SellingPartnerApi;
use App\Services\AmazonPayloadTransformerV2;
use Illuminate\Support\Facades\LOG;
use App\Services\ShopifyService;
// use Illuminate\Support\Facades\Log;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPatchRequest;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\PatchOperation;
use App\Models\ProductSyncLog;
use App\Models\AdminSetting;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use SellingPartnerApi\Seller\ProductTypeDefinitionsV20200901\Requests\GetDefinitionsProductType;
use App\Models\Shop;
use App\Models\ProductMarketplaceMapping;

class AmazonService
{
    protected string $baseUrl;
    protected string $region;
    protected $client;

    public function __construct(
        AmazonPayloadTransformerV2 $payloadTransformerV2,
        string $region = 'na'
    ) {
        $this->payloadTransformerV2 = $payloadTransformerV2;

        $this->region = $region;

        $this->baseUrl = match ($region) {
            'eu' => 'https://sellingpartnerapi-eu.amazon.com',
            'na' => 'https://sellingpartnerapi-na.amazon.com',
            'fe' => 'https://sellingpartnerapi-fe.amazon.com',
            default => 'https://sellingpartnerapi-eu.amazon.com',
        };
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        $this->baseUrl = match ($region) {
            'eu' => 'https://sellingpartnerapi-eu.amazon.com',
            'na' => 'https://sellingpartnerapi-na.amazon.com',
            'fe' => 'https://sellingpartnerapi-fe.amazon.com',
            default => 'https://sellingpartnerapi-eu.amazon.com',
        };

        return $this;
    }
    /**
     *  Get Access Token (cached per region)
     */
    public function getAccessToken()
    {

        $cacheKey = "amazon_access_token_{$this->region}";
        return Cache::remember($cacheKey, 3000, function () {
            $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => env('AMAZON_REFRESH_TOKEN'),
                'client_id' => env('AMAZON_CLIENT_ID'),
                'client_secret' => env('AMAZON_CLIENT_SECRET'),
            ]);
            if (!$response->successful()) {
                LOG::error('Amazon Auth Failed', [
                    'body' => $response->body()
                ]);
                return null;
            }
            return $response->json()['access_token'] ?? null;
        });
    }
    /**
     *  Generic GET Request
     */
    public function get($endpoint, $params = [])
    {
        $token = $this->getAccessToken();
        if (!$token) return null;
        $response = Http::withHeaders([
            'x-amz-access-token' => $token,
            'Content-Type' => 'application/json'
        ])->get($this->baseUrl . $endpoint, $params);
        if (!$response->successful()) {
            LOG::error('Amazon API failed', [
                'region' => $this->region,
                'url' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }
        return $response->json();
    }
    /**
     *  Get Inventory
     */
    public function fetchInventory($amazonSellerId, $marketplaceId)
    {
        \Log::info('========== FETCH INVENTORY START ==========');

        \Log::info('Fetch Inventory Input', [
            'region'         => $this->region,
            'base_url'       => $this->baseUrl,
            'seller_id'      => $amazonSellerId,
            'marketplace_id' => $marketplaceId,
        ]);

        $data = $this->get('/listings/2021-08-01/items', [
            'sellerId'       => $amazonSellerId,
            'marketplaceIds' => $marketplaceId,
            'includedData'   => 'summaries,attributes',
        ]);

        \Log::info('Amazon Raw Response', [
            'response' => $data,
        ]);

        if (!$data) {

            \Log::warning('Amazon response is NULL or FALSE');

            return [];
        }

        \Log::info('Amazon Response Keys', [
            'keys' => array_keys($data),
        ]);

        $items = $data['items'] ?? [];

        \Log::info('Amazon Items Count', [
            'count' => count($items),
        ]);

        $result = [];

        foreach ($items as $index => $item) {

            \Log::info("Amazon Item {$index}", [
                'item' => $item,
            ]);

            $summary = $item['summaries'][0] ?? [];

            $mapped = [
                'sku'          => $item['sku'] ?? '',
                'asin'         => $item['asin'] ?? '',
                'title'        => $summary['itemName'] ?? '',
                'product_type' => $summary['productType'] ?? '',
                'status'       => $summary['status'][0] ?? 'UNKNOWN',
                'qty'          => 0,
                'image'        => null,
            ];

            \Log::info("Mapped Item {$index}", $mapped);

            $result[] = $mapped;
        }

        \Log::info('Final Inventory Result', [
            'count' => count($result),
            'data'  => $result,
        ]);

        \Log::info('========== FETCH INVENTORY END ==========');

        return $result;
    }
    public function createReturnsReport($marketplace_id)
    {
        $accessToken = $this->getAccessToken();
        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/reports/2021-06-30/reports', [
            'reportType' => 'GET_FLAT_FILE_RETURNS_DATA_BY_RETURN_DATE',
            'marketplaceIds' => [$marketplace_id],
            'dataStartTime' => now()->subDays(7)->toIso8601String(),
            'dataEndTime' => now()->toIso8601String(),
        ]);
        return $response->json()['reportId'] ?? null;
    }
    public function getReport($reportId)
    {
        sleep(5);
        $accessToken = $this->getAccessToken();
        $report = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->get($this->baseUrl . "/reports/2021-06-30/reports/{$reportId}");
        $docId = $report->json()['reportDocumentId'];
        $doc = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->get($this->baseUrl . "/reports/2021-06-30/documents/{$docId}");
        return file_get_contents($doc->json()['url']);
    }
    public function parseReport($content)
    {
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines), "\t");
        $data = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line, "\t");
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
        return $data;
    }

    public static function getClient()
    {
        return SellingPartnerApi::seller(
            clientId: env('AMAZON_CLIENT_ID'),
            clientSecret: env('AMAZON_CLIENT_SECRET'),
            refreshToken: env('AMAZON_REFRESH_TOKEN'),
            endpoint: Endpoint::NA_SANDBOX,
        );
    }

    // =========================================
    // NEW FUNCTION (DB CREDENTIALS)
    // =========================================
    public function getDbCredentials($shop)
    {
        $config = AdminSetting::pluck('option_value', 'option_key');
        return [
            'client_id' => $config['production_client_id'] ?? null,
            'client_secret' => $config['production_client_secret'] ?? null,
            'refresh_token' => $shop->amazon_refresh_token ?? null,
            'seller_id' => $shop->amazon_seller_id ?? null,
        ];
    }

    public function getDbConnectorFromCredentials($shop)
    {
        $creds = $this->getDbCredentials($shop);
        if (
            empty($creds['client_id']) ||
            empty($creds['client_secret']) ||
            empty($creds['refresh_token'])
        ) {
            throw new \Exception('DB credentials missing');
        }
        return SellingPartnerApi::seller(
            clientId: $creds['client_id'],
            clientSecret: $creds['client_secret'],
            refreshToken: $creds['refresh_token'],
            endpoint: Endpoint::NA
        )->listingsItemsV20210801();
    }
    public function getSellerConnector($shop)
    {
        $creds = $this->getDbCredentials($shop);

        if (
            empty($creds['client_id']) ||
            empty($creds['client_secret']) ||
            empty($creds['refresh_token'])
        ) {
            throw new \Exception('DB credentials missing');
        }

        return SellingPartnerApi::seller(
            clientId: $creds['client_id'],
            clientSecret: $creds['client_secret'],
            refreshToken: $creds['refresh_token'],
            endpoint: Endpoint::NA
        );
    }

    public function updateInventory(
        Shop $shop,
        string $sku,
        int $quantity
    ) {
        // Fetch latest listing
        $listing = $this->checkAmazonListing($shop, $sku);

        // Product Type
        $productType =
            $listing['summaries'][0]['productType']
            ?? throw new \Exception("Product type not found for SKU: {$sku}");

        // Fulfillment Channel
        $fulfillmentChannel =
            $listing['attributes']['fulfillment_availability'][0]['fulfillment_channel_code']
            ?? 'DEFAULT';

        // Connector
        $connector = $this->getDbConnectorFromCredentials($shop);

        // PATCH Body
        $patchRequest = new ListingsItemPatchRequest(
            productType: $productType,
            patches: [
                new PatchOperation(
                    op: 'replace',
                    path: '/attributes/fulfillment_availability',
                    value: [[
                        'fulfillment_channel_code' => $fulfillmentChannel,
                        'quantity' => $quantity,
                    ]]
                )
            ]
        );

        Log::info('Amazon Inventory PATCH Request', [
            'seller_id'      => $shop->amazon_seller_id,
            'marketplace_id' => $shop->amazon_marketplace_id,
            'sku'            => $sku,
            'product_type'   => $productType,
            'quantity'       => $quantity,
            'payload'        => $patchRequest->toArray(),
        ]);

        $response = $connector->patchListingsItem(
            sellerId: $shop->amazon_seller_id,
            sku: $sku,
            listingsItemPatchRequest: $patchRequest,
            marketplaceIds: [
                $shop->amazon_marketplace_id
            ],
            includedData: [
                'issues'
            ]
        );

        $responseBody = $response->json();

        Log::info('Amazon Inventory PATCH Response', [
            'sku'    => $sku,
            'status' => $response->status(),
            'body'   => $responseBody,
        ]);

        $mapping = ProductMarketplaceMapping::where('shop_id', $shop->id)
            ->where('amazon_sku', $sku)
            ->first();

        // Update DB only if Amazon update succeeded
        if (
            $mapping &&
            $response->status() >= 200 &&
            $response->status() < 300 &&
            empty($responseBody['issues'])
        ) {
            $mapping->update([
                'quantity' => $quantity,
            ]);

            Log::info('Marketplace mapping quantity updated.', [
                'mapping_id' => $mapping->id,
                'sku'        => $sku,
                'quantity'   => $quantity,
            ]);

            // Update Shopify Inventory
            $shopify = new ShopifyService(
                $shop->shop,
                $shop->access_token
            );

            $locations = $shop->shopify_locations ?? [];
            $selectedIndex = $shop->selected_location_index;

            $locationId = null;

            if ($selectedIndex !== null && isset($locations[$selectedIndex])) {
                $locationId = $locations[$selectedIndex]['id'] ?? null;
            }

            if (!$locationId) {
                Log::warning('SHOPIFY SELECTED LOCATION NOT FOUND', [
                    'shop_id' => $shop->id,
                    'selected_location_index' => $selectedIndex,
                ]);

                throw new \Exception(
                    'Please select a valid Shopify inventory location in Settings.'
                );
            }

            $shopify->shopifyRest(
                $shop,
                'post',
                'inventory_levels/set.json',
                [
                    'location_id'       => $locationId,
                    'inventory_item_id' => $mapping->shopify_inventory_item_id,
                    'available'         => $quantity,
                ]
            );
        }

        return $responseBody;
    }

    private function getFinalCategorySlug($product)
    {
        if (!empty($product->sub_category_id)) {
            return strtolower(trim(
                getCategoryData($product->sub_category_id, 'slug')
            ));
        }
        throw new \Exception("Sub category missing for product ID: {$product->id}");
    }
    // main buildpayload  for amazon sync funcanality 
    public function buildPayload($shop, $product, $amazon)
    {
        $slug = strtolower(trim($this->getFinalCategorySlug($product)));
        $variants = $this->parseJsonField($product->variants);
        $variantCount = count($variants);
        // 🔥 CATEGORY CHECK
        if (in_array($slug, ['shirt', 't-shirt', 'shorts'])) {
            // 🔴 MULTIPLE VARIANTS
            if ($variantCount > 1) {
                return $this->shirtVariantPayload($shop, $product, $amazon);
            }
            // 🟢 SINGLE VARIANT
            return $this->shirtFullPayload($product, $amazon);
        }
        if (in_array($slug, ['phone', 'mobile'])) {
            // MULTI VARIANT
            if ($variantCount > 1) {
                return $this->phoneVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            // SINGLE VARIANT
            return $this->phoneFullPayload(
                $product,
                $amazon
            );
        }
        if ($slug === 'headphones') {
            // MULTI VARIANT
            if ($variantCount > 1) {
                return $this->headphonesVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            // SINGLE PRODUCT
            return $this->headphonesFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['pants', 'jeans'])) {
            if ($variantCount > 1) {
                return $this->pantsVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->pantsFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['shoes', 'SHOES'])) {
            if ($variantCount > 1) {
                return $this->shoesVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->shoesFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['backpack', 'BACKPACK'])) {
            if ($variantCount > 1) {
                return $this->backpackVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->backpackFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['input_mouse', 'INPUT_MOUSE'])) {
            if ($variantCount > 1) {
                return $this->inputMouseVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->inputMouseFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['watch', 'WATCH'])) {
            if ($variantCount > 1) {
                return $this->watchVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->watchFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['camera', 'CAMERA'])) {
            if ($variantCount > 1) {
                return $this->cameraVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->cameraFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['monitor', 'MONITOR'])) {
            if ($variantCount > 1) {
                return $this->monitorVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->monitorFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['NOTEBOOK_COMPUTER', 'notebook_computer'])) {
            if ($variantCount > 1) {
                return $this->notebookComputerVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->notebookComputerFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['FOOTWEAR', 'footwear'])) {
            if ($variantCount > 1) {
                return $this->footwearVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->footwearFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['HANDBAG', 'handbag'])) {
            if ($variantCount > 1) {
                return $this->handbagVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->handbagFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['CABLE', 'cable'])) {
            if ($variantCount > 1) {
                return $this->cableVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->cableFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['CHAIR', 'chair'])) {
            if ($variantCount > 1) {
                return $this->chairVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->chairFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['TABLE', 'table'])) {
            if ($variantCount > 1) {
                return $this->tableVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->tableFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['SOFA', 'sofa'])) {
            if ($variantCount > 1) {
                return $this->sofaVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->sofaFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['MATTRESS', 'mattress'])) {
            if ($variantCount > 1) {
                return $this->mattressVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->mattressFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['BED', 'bed'])) {
            if ($variantCount > 1) {
                return $this->bedVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->bedFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['STAPLER', 'stapler'])) {
            if ($variantCount > 1) {
                return $this->staplerVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->staplerFullPayload(
                $product,
                $amazon
            );
        }
        if (in_array($slug, ['keyboards', 'KEYBOARDS'])) {
            if ($variantCount > 1) {
                return $this->keyboardVariantPayload(
                    $shop,
                    $product,
                    $amazon
                );
            }
            return $this->keyboardFullPayload(
                $product,
                $amazon
            );
        }
        throw new \Exception("Unsupported category: $slug");
    }
    public function putListing($shop, $sku, $payload, $product)
    {
        try {
            LOG::info('AMAZON PUT START', [
                'seller_id' => $shop->amazon_seller_id,
                'sku' => $sku,
                'payload_type' => gettype($payload),
                'is_array' => is_array($payload),
                'payload_keys' => is_array($payload) ? array_keys($payload) : null,
            ]);
            $client = $this->getDbConnectorFromCredentials($shop);
            LOG::info('CLIENT READY', [
                'client_class' => get_class($client),
            ]);
            if ($product->sub_category_id) {
                $type = getCategoryData($product->sub_category_id, 'slug');
            }
            $productType = match (strtolower(trim($product->category ?? ''))) {
                'phone' => 'PHONE',
                'shirt' => 'SHIRT',
                'headphones' => 'HEADPHONES',
                default => 'PRODUCT'
            };
            if (isset($type) && $type) {
                $productType = strtoupper(trim($type));
            }
            LOG::info('FINAL PRODUCT TYPE', [
                'category' => $product->category,
                'sub_category_id' => $product->sub_category_id,
                'type_from_db' => $type ?? null,
                'productType' => $productType,
            ]);
            $dto = new ListingsItemPutRequest(
                productType: $productType,
                attributes: $payload,
            );
            LOG::info(' DTO CREATED', [
                'is_dto' => $dto instanceof ListingsItemPutRequest,
                'dto_class' => get_class($dto),
            ]);
            //  BEFORE API CALL
            LOG::info(' CALLING AMAZON API', [
                'seller_id' => $shop->amazon_seller_id,
                'sku' => $sku,
                'marketplace' => 'ATVPDKIKX0DER',
            ]);
            $response = $client->putListingsItem(
                $shop->amazon_seller_id,
                $sku,
                $dto,
                ['ATVPDKIKX0DER']
            );
            //  AFTER API CALL
            LOG::info(' AMAZON RESPONSE RECEIVED', [
                'response_class' => is_object($response) ? get_class($response) : gettype($response),
            ]);
            // Try extracting useful data safely
            try {
                $responseData = method_exists($response, 'dto')
                    ? $response->dto()
                    : (method_exists($response, 'payload') ? $response->payload() : null);
                LOG::info(' AMAZON RESPONSE DATA', [
                    'data' => $responseData
                ]);
            } catch (\Exception $inner) {
                LOG::warning('⚠️ RESPONSE PARSE FAILED', [
                    'error' => $inner->getMessage()
                ]);
            }
            return $response;
        } catch (\Exception $e) {
            LOG::error('❌ AMAZON PUT FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => substr(
                    $e->getTraceAsString(),
                    0,
                    1000
                ),
            ]);
            // RAW AMAZON RESPONSE
            if (
                method_exists($e, 'getResponse')
            ) {
                try {
                    LOG::error(
                        '🔥 AMAZON RAW BODY',
                        [
                            'body' =>
                            $e->getResponse()
                                ->body(),
                            'json' =>
                            $e->getResponse()
                                ->json(),
                            'status' =>
                            $e->getResponse()
                                ->status(),
                        ]
                    );
                } catch (\Throwable $inner) {
                    LOG::error(
                        'RAW BODY PARSE FAILED',
                        [
                            'error' =>
                            $inner->getMessage()
                        ]
                    );
                }
            }
            return $e->getMessage();
        }
    }
    private function safeValue($value)
    {
        if (is_array($value)) {
            return implode(' ', array_map(function ($v) {
                return is_array($v) ? implode(' ', $v) : $v;
            }, $value));
        }
        return (string) $value;
    }
    private function parseJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
    // category wise specific payloades for amazon 
    public function checkAmazonListing($shop, $sku)
    {
        try {
            $client = $this->getDbConnectorFromCredentials($shop);
            $response = $client->getListingsItem(
                sellerId: $shop->amazon_seller_id,
                sku: $sku,
                marketplaceIds: ['ATVPDKIKX0DER'],
                includedData: [
                    'attributes',
                    'issues',
                    'summaries',
                    'offers',
                    'fulfillmentAvailability',
                    'relationships'
                ]
            );
            $data = $response->dto();
            LOG::info('AMAZON LISTING CHECK', [
                'sku' => $sku,
                'status' => $data->status ?? null,
                'relationships' => $data->relationships ?? [],
                'issues' => $data->issues ?? [],
            ]);
            return json_decode(
                json_encode($data),
                true
            );
        } catch (\Throwable $e) {
            LOG::error('LISTING CHECK FAILED', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    public function CreateToyPayload($product, $amazon)
    {
        try {
            $sku = $amazon->sku;
            $marketplaceId = 'ATVPDKIKX0DER';
            $attributes = [
                'item_name' => [[
                    'value' => $amazon->amazon_title ?? $product->title,
                    'language_tag' => 'en_US'
                ]],
                'brand' => [[
                    'value' =>  $product->vendor ?? "Generic"
                ]],
                'manufacturer' => [[
                    'value' =>  $product->vendor ?? "Generic"
                ]],
                'part_number' => [[
                    'value' => 'TB-FB3000'
                ]],
                'externally_assigned_product_identifier' => [[
                    'type' => 'ean',
                    'value' => '4006381733931'
                ]],
                'product_description' => [[
                    'value' => $product->description,
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
                    'value' => $product->price,
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
            return $attributes;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function createBeautyPayload($product, $amazon)
    {
        try {
            $sku = $amazon->sku;
            $marketplaceId = 'ATVPDKIKX0DER';
            $attributes = [
                'item_name' => [[
                    'value' =>  $amazon->amazon_title ?? $product->title,
                    'language_tag' => 'en_US'
                ]],
                'brand' => [[
                    'value' =>  $product->vendor ?? "Generic"
                ]],
                'manufacturer' => [[
                    'value' =>  $product->vendor ?? "Generic"
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
            return $attributes;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    //    all payload section
    private function shirtFullPayload($product, $amazon) // 1
    {
        $variants = $this->parseJsonField($product->variants);
        $images   = $this->parseJsonField($product->images);
        $price = $variants[0]['price'] ?? $product->price ?? 0;
        $qty   = $variants[0]['inventory_quantity'] ?? 0;
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title ?? $product->title),
                    0,
                    80
                ),
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => $product->vendor ?? "Generic",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => $product->vendor ?? "Generic",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags($product->description ?? ''),
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => (float)$price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int)$qty
            ]],
            // CATEGORY REQUIRED
            "item_type_keyword" => [[
                "value" => "polo-shirts",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "target_gender" => [[
                "value" => "male",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "shirt_size" => [[
                "size_system" => "as1",
                "size_class" => "alpha",
                "size" => "m"
            ]],
            "color" => [[
                "value" => "black",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fabric_type" => [[
                "value" => "cotton",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fit_type" => [[
                "value" => "regular",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "care_instructions" => [[
                "value" => "machine wash",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "department" => [[
                "value" => "mens",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "age_range_description" => [[
                "value" => "adult",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "unit_count" => [[
                "value" => 1,
                "type" => [
                    "value" => "Count",
                    "language_tag" => "en_US"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => $amazon->sku ?? "SHIRT-MODEL",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($this->parseJsonField($amazon->bullet_points))
                ->map(fn($b) => [
                    "value" => $b,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])->toArray(),
            "externally_assigned_product_identifier" => [[
                "type" => "ean",
                "value" => "8901234567890",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "style" => [[
                "value" => "casual",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "import_designation" => [[
                "value" => "imported",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "merchant_shipping_group" => [[
                "value" => "Migrated Template",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_dimensions" => [[
                "length" => [
                    "value" => 30,
                    "unit" => "centimeters"
                ],
                "width" => [
                    "value" => 25,
                    "unit" => "centimeters"
                ],
                "height" => [
                    "value" => 3,
                    "unit" => "centimeters"
                ]
            ]],
            "item_package_weight" => [[
                "value" => 300,
                "unit" => "grams"
            ]],
            "special_size_type" => [[
                "value" => "standard",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "standardized_values" => [[
                "value" => "m",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "merchant_suggested_asin" => [[
                "value" => "B0TEST1234", // exactly 10 chars
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "sleeve" => [[
                "type" => [[
                    "value" => "short_sleeve",
                    "language_tag" => "en_US"
                ]]
            ]],
            "neck" => [[
                "neck_style" => [[
                    "value" => "crew_neck",
                    "language_tag" => "en_US"
                ]]
            ]],
        ];
        // Dynamic Additional Images
        $imageIndex = 1;
        foreach ($images as $index => $img) {
            if ($index === 0) {
                continue;
            }
            $imageUrl = $img['src']
                ?? $img['url']
                ?? null;
            if (empty($imageUrl)) {
                continue;
            }
            $key = 'other_product_image_locator_' . $imageIndex;
            $payload[$key] = [[
                "media_location" => $imageUrl,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $imageIndex++;
            // Amazon safe limit
            if ($imageIndex > 8) {
                break;
            }
        }
        return $payload;
    }
    public function shirtVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $client = $this->getDbConnectorFromCredentials($shop);
            $variants = $this->parseJsonField($product->variants);
            $images = $this->parseJsonField($product->images);
            if (empty($variants)) {
                LOG::error('NO VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            /*
        ==================================================
        PARENT PAYLOAD
        ==================================================
        */
            $parentPayload = $this->shirtFullPayload(
                $product,
                $amazon
            );
            unset($parentPayload['merchant_shipping_group']);
            unset($parentPayload['standardized_values']);
            unset($parentPayload['externally_assigned_product_identifier']);
            unset($parentPayload['merchant_suggested_asin']);
            if (!empty($images)) {
                $parentPayload['main_product_image_locator'] = [[
                    "media_location" => $images[0]['src'],
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                foreach ($images as $index => $img) {
                    if ($index === 0) {
                        continue;
                    }
                    $key = 'other_product_image_locator_' . $index;
                    $parentPayload[$key] = [[
                        "media_location" => $img['src'],
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
            }
            // Parent should not contain offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset($parentPayload['externally_assigned_product_identifier']);
            unset($parentPayload['merchant_suggested_asin']);
            $parentPayload['variation_theme'] = [[
                "name" => "SIZE/COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // $parentDto = new ListingsItemPutRequest(
            //     productType: 'SHIRT',
            //     attributes: $parentPayload,
            //     requirements: 'LISTING'
            // );
            // $parentResponse = $client->putListingsItem(
            //     $shop->amazon_seller_id,
            //     $parentSku,
            //     $parentDto,
            //     ['ATVPDKIKX0DER']
            // );
            // $parentBody = $parentResponse->dto();
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists($parentResponse, 'dto')
                ? $parentResponse->dto()
                : null;
            $isAccepted = ($parentBody->status ?? null) === 'ACCEPTED';
            LOG::info('PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            /*
        ==================================================
        CHILD VARIANTS
        ==================================================
        */
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku'] ?? ('SKU-' . $variant['id'])
                );
                $price = (float)($variant['price'] ?? 0);
                $qty = (int)($variant['inventory_quantity'] ?? 0);
                $color = trim(
                    strtolower($variant['option1'] ?? 'black')
                );
                $size = strtolower(
                    trim($variant['option2'] ?? 'm')
                );
                /*
            ==========================================
            CHILD PAYLOAD
            ==========================================
            */
                $childPayload = $this->shirtFullPayload(
                    $product,
                    $amazon
                );
                unset($childPayload['merchant_shipping_group']);
                unset($childPayload['standardized_values']);
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains($key, 'other_product_image_locator')
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $childPayload['externally_assigned_product_identifier'] = [[
                    'type' => 'ean',
                    'value' => '1234567890123',
                    'marketplace_id' => 'ATVPDKIKX0DER'
                ]];
                $childPayload['merchant_suggested_asin'] = [[
                    'value' => 'B0D123ABC45',
                    'marketplace_id' => 'ATVPDKIKX0DER'
                ]];
                unset($childPayload['externally_assigned_product_identifier']);
                unset($childPayload['merchant_suggested_asin']);
                $childPayload['supplier_declared_has_product_identifier_exemption'] = [[
                    'value' => true,
                    'marketplace_id' => 'ATVPDKIKX0DER'
                ]];
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array($variant['id'], $img['variant_ids'])
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['color'] = [[
                    "value" => $color,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['shirt_size'] = [[
                    "size_system" => "as1",
                    "size_class" => "alpha",
                    "size" => $size,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['size'] = [[
                    "value" => $size,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "SIZE/COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // $childDto = new ListingsItemPutRequest(
                //     productType: 'SHIRT',
                //     attributes: $childPayload,
                //     requirements: 'LISTING'
                // );
                // $childResponse = $client->putListingsItem(
                //     $shop->amazon_seller_id,
                //     $sku,
                //     $childDto,
                //     ['ATVPDKIKX0DER']
                // );
                // $childBody = $childResponse->dto();
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists($childResponse, 'dto')
                    ? $childResponse->dto()
                    : null;
                if (($childBody->status ?? null) !== 'ACCEPTED') {
                    $isAccepted = false;
                }
                Log::info('CHILD IDENTIFIER DEBUG', [
                    'external' => $childPayload['externally_assigned_product_identifier'] ?? null,
                    'asin' => $childPayload['merchant_suggested_asin'] ?? null,
                ]);
                LOG::info('CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function headphonesFullPayload($product, $amazon)  // 2
    {
        $variants = $this->parseJsonField($product->variants);
        $images   = $this->parseJsonField($product->images);
        $price = $variants[0]['price'] ?? $product->price ?? 0;
        $qty   = $variants[0]['inventory_quantity'] ?? 0;
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title ?? $product->title),
                    0,
                    180
                ),
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => $product->vendor ?? "Generic",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => $product->vendor ?? "Generic",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags($product->description ?? ''),
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => (float)$price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int)$qty
            ]],
            // CATEGORY REQUIRED
            "item_type_keyword" => [[
                "value" => "headphones",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "black",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "connectivity_technology" => [[
                "value" => "wireless",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "headphones_form_factor" => [[
                "value" => "in_ear",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "headphones_ear_placement" => [[
                "value" => "in_ear",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "warranty_description" => [[
                "value" => "1 year warranty",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Earbuds, Charging Case, Cable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "unit_count" => [[
                "value" => 1,
                "type" => [
                    "value" => "Count",
                    "language_tag" => "en_US"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => $amazon->sku ?? "HEADPHONE-MODEL",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => $amazon->sku ?? "HEADPHONE-MODEL",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($this->parseJsonField($amazon->bullet_points))
                ->map(fn($b) => [
                    "value" => $b,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])->toArray(),
            "externally_assigned_product_identifier" => [[
                "type" => "ean",
                "value" => "8901234567890",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "merchant_suggested_asin" => [[
                "value" => "B0TEST12345",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "merchant_shipping_group" => [[
                "value" => "Default Shipping Template",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_dimensions" => [[
                "length" => [
                    "value" => 8,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 6,
                    "unit" => "inches"
                ],
                "height" => [
                    "value" => 3,
                    "unit" => "inches"
                ]
            ]],
            "item_package_weight" => [[
                "value" => 0.75,
                "unit" => "pounds"
            ]],
            "battery" => [[
                "cell_composition" => [[
                    "value" => "lithium_ion"
                ]]
            ]],
            "num_batteries" => [[
                "quantity" => 1,
                "type" => "lithium_ion"
            ]],
            "has_multiple_battery_powered_components" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "includes_rechargable_battery" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_lithium_metal_cells" => [[
                "value" => 0,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_lithium_ion_cells" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "lithium_battery" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "contains_battery_or_cell" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_battery_non_spillable" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "non_lithium_battery_packaging" => [[
                "value" => "batteries_contained_in_equipment",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "battery_contains_free_unabsorbed_liquid" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "has_less_than_30_percent_state_of_charge" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "battery_installation_device_type" => [[
                "value" => "battery_installed",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "hazmat" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "ghs" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "safety_data_sheet_url" => [[
                "value" => "https://example.com/sds.pdf",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // Dynamic Additional Images
        $imageIndex = 1;
        foreach ($images as $index => $img) {
            if ($index === 0) {
                continue;
            }
            $imageUrl = $img['src']
                ?? $img['url']
                ?? null;
            if (empty($imageUrl)) {
                continue;
            }
            $key = 'other_product_image_locator_' . $imageIndex;
            $payload[$key] = [[
                "media_location" => $imageUrl,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $imageIndex++;
            // Amazon safe limit
            if ($imageIndex > 8) {
                break;
            }
        }
        return $payload;
    }
    public function headphonesVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $client = $this->getDbConnectorFromCredentials($shop);
            $variants = $this->parseJsonField($product->variants);
            $images = $this->parseJsonField($product->images);
            if (empty($variants)) {
                LOG::error('NO HEADPHONE VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            $parentPayload = $this->headphonesFullPayload(
                $product,
                $amazon
            );
            if (!empty($images)) {
                $parentPayload['main_product_image_locator'] = [[
                    "media_location" => $images[0]['src'],
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                foreach ($images as $index => $img) {
                    if ($index === 0) {
                        continue;
                    }
                    $key = 'other_product_image_locator_' . $index;
                    $parentPayload[$key] = [[
                        "media_location" => $img['src'],
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists($parentResponse, 'dto')
                ? $parentResponse->dto()
                : null;
            $isAccepted = ($parentBody->status ?? null) === 'ACCEPTED';
            LOG::info('HEADPHONE PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku'] ?? ('HEADPHONE-' . $variant['id'])
                );
                $price = (float)($variant['price'] ?? 0);
                $qty = (int)($variant['inventory_quantity'] ?? 0);
                $color = trim(
                    strtolower($variant['option1'] ?? 'black')
                );
                $childPayload = $this->headphonesFullPayload(
                    $product,
                    $amazon
                );
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains($key, 'other_product_image_locator')
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array($variant['id'], $img['variant_ids'])
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['color'] = [[
                    "value" => $color,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists($childResponse, 'dto')
                    ? $childResponse->dto()
                    : null;
                if (($childBody->status ?? null) !== 'ACCEPTED') {
                    $isAccepted = false;
                }
                LOG::info('HEADPHONE CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon headphone variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon headphone variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('HEADPHONE VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function phoneFullPayload($product, $amazon)  // 3
    {
        $variants = $this->parseJsonField($product->variants);
        $images   = $this->parseJsonField($product->images);
        $price = $variants[0]['price'] ?? $product->price ?? 0;
        $qty = $variants[0]['inventory_quantity'] ?? 0;
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            // Basic Info
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title ?? $product->title),
                    0,
                    180
                ),
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => $product->vendor ?? "Generic",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => $product->vendor ?? "Generic",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => $amazon->sku ?? "PHONE-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => $amazon->sku ?? "PART-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Description
            "product_description" => [[
                "value" => strip_tags($product->description ?? ''),
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)->map(
                fn($b) => [
                    "value" => $b,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]
            )->toArray(),
            // Images
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Price & Inventory
            "list_price" => [[
                "value" => (float) $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int) $qty
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Phone Category
            "item_type_keyword" => [[
                "value" => "telephones",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "telephone_type" => [[
                "value" => "Dual-SIM",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "operating_system" => [[
                "value" => "Android",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "memory_storage_capacity" => [[
                "value" => 128,
                "unit" => "GB",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "digital_storage_capacity" => [[
                "value" => 128,
                "unit" => "GB",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "display" => [[
                "size" => [[
                    "value" => 6.5,
                    "unit" => "inches"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "effective_still_resolution" => [[
                "value" => 48,
                "unit" => "megapixels",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Battery
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Extra
            "warranty_description" => [[
                "value" => "1 year warranty",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Black",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Handset, Charger, Cable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Identifier
            // "merchant_suggested_asin" => [[
            //     "value" => "B0DUMMY1234",
            //     "marketplace_id" => "ATVPDKIKX0DER"
            // ]],
            "externally_assigned_product_identifier" => [[
                "type" => "ean",
                "value" => "8901234567890",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // Dynamic Additional Images
        $imageIndex = 1;
        foreach ($images as $index => $img) {
            if ($index === 0) {
                continue;
            }
            $imageUrl = $img['src']
                ?? $img['url']
                ?? null;
            if (empty($imageUrl)) {
                continue;
            }
            $key = 'other_product_image_locator_' . $imageIndex;
            $payload[$key] = [[
                "media_location" => $imageUrl,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $imageIndex++;
            // Amazon safe limit
            if ($imageIndex > 8) {
                break;
            }
        }
        return $payload;
    }
    public function phoneVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $client = $this->getDbConnectorFromCredentials($shop);
            $variants = $this->parseJsonField($product->variants);
            $images   = $this->parseJsonField($product->images);
            if (empty($variants)) {
                LOG::error('NO PHONE VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            /*
        ==================================================
        PARENT PAYLOAD
        ==================================================
        */
            $parentPayload = $this->phoneFullPayload(
                $product,
                $amazon
            );
            if (!empty($images)) {
                $parentPayload['main_product_image_locator'] = [[
                    "media_location" => $images[0]['src'],
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                foreach ($images as $index => $img) {
                    if ($index === 0) {
                        continue;
                    }
                    $imageUrl = $img['src']
                        ?? $img['url']
                        ?? null;
                    if (empty($imageUrl)) {
                        continue;
                    }
                    $key = 'other_product_image_locator_' . $index;
                    $parentPayload[$key] = [[
                        "media_location" => $imageUrl,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
            }
            // Parent should not contain offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR_NAME",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists($parentResponse, 'dto')
                ? $parentResponse->dto()
                : null;
            $isAccepted = ($parentBody->status ?? null) === 'ACCEPTED';
            LOG::info('PHONE PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            /*
        ==================================================
        CHILD VARIANTS
        ==================================================
        */
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku'] ?? ('PHONE-' . $variant['id'])
                );
                $price = (float)($variant['price'] ?? 0);
                $qty = (int)($variant['inventory_quantity'] ?? 0);
                $color = trim(
                    strtolower($variant['option1'] ?? 'black')
                );
                $storage = trim(
                    strtolower($variant['option2'] ?? '128gb')
                );
                /*
            ==========================================
            CHILD PAYLOAD
            ==========================================
            */
                $childPayload = $this->phoneFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains($key, 'other_product_image_locator')
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                /*
            ==========================================
            VARIANT IMAGE
            ==========================================
            */
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array($variant['id'], $img['variant_ids'])
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                /*
            ==========================================
            OFFER DATA
            ==========================================
            */
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                /*
            ==========================================
            VARIATION ATTRIBUTES
            ==========================================
            */
                $childPayload['color'] = [[
                    "value" => $color,
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                preg_match('/\d+/', $storage, $matches);
                $storageValue = $matches[0] ?? 128;
                $childPayload['memory_storage_capacity'] = [[
                    "value" => (int)$storageValue,
                    "unit" => "GB",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['digital_storage_capacity'] = [[
                    "value" => (int)$storageValue,
                    "unit" => "GB",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                /*
            ==========================================
            RELATIONSHIP
            ==========================================
            */
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR_NAME",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                /*
            ==========================================
            AMAZON PUT
            ==========================================
            */
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists($childResponse, 'dto')
                    ? $childResponse->dto()
                    : null;
                if (($childBody->status ?? null) !== 'ACCEPTED') {
                    $isAccepted = false;
                }
                LOG::info('PHONE CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon phone variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon phone variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('PHONE VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    private function pantsFullPayload($product, $amazon)  // 4
    {
        $variants = $this->parseJsonField($product->variants);
        $images   = $this->parseJsonField($product->images);
        $price = $variants[0]['price'] ?? $product->price ?? 0;
        $qty   = $variants[0]['inventory_quantity'] ?? 0;
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField($amazon->bullet_points);
        // Strip "Title: " prefix from amazon_title
        $cleanTitle = preg_replace('/^Title:\s*/i', '', $amazon->amazon_title ?? $product->title ?? '');
        $payload = [
            // ── Required fields that need language_tag ─────────────────────────
            "item_name" => [[
                "value"          => substr(trim($cleanTitle), 0, 200),
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value"          => $product->vendor ?? "Generic",
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value"          => $amazon->sku ?? "PANTS-MODEL",
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value"          => strip_tags($product->description ?? ''),
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // bullet_point requires language_tag per item
            "bullet_point" => collect($bulletPoints)->map(
                fn($b) => [
                    "value"          => $b,
                    "language_tag"   => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]
            )->values()->toArray(),
            "fabric_type" => [[
                "value"          => "100% Cotton",
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "style" => [[
                "value"          => "Casual",
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "department" => [[
                "value"          => "Mens",          // ✅ exact enum from schema
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "age_range_description" => [[
                "value"          => "Adult",         // ✅ exact enum from schema
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "target_gender" => [[
                "value"          => "male",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ── bottoms_size: size_system "as1" IS correct per schema ──────────
            // For Adult + male + alpha class, valid sizes: s, m, l, x_l, xx_l etc.
            "bottoms_size" => [[
                "size_system"    => "as1",           // ✅ correct enum value (display name is "US")
                "size_class"     => "alpha",
                "size"           => "m",             // ✅ valid alpha size for adult male
                "body_type"      => "regular",       // ✅ required for adult male alpha class
                "height_type"    => "regular",       // ✅ required for adult male alpha/numeric class
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "special_size_type" => [[
                "value" => "standard",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ── Images ─────────────────────────────────────────────────────────
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ── Offer / pricing ────────────────────────────────────────────────
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity"                 => (int) $qty
            ]],
            "list_price" => [[
                "value"          => (float) $price,
                "currency"       => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value"          => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ── Pants-specific ─────────────────────────────────────────────────
            "item_type_keyword" => [[
                "value"          => "casual-pants",  // ✅ valid enum: Men > Clothing > Pants > Casual
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "pants_form_type" => [[
                "value"          => "chino_pants",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // closure uses nested "type" array with language_tag per schema
            "closure" => [[
                "type" => [[
                    "value"        => "Zipper",      // ✅ exact enum from schema
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // rise uses nested "style" array with language_tag per schema
            "rise" => [[
                "style" => [[
                    "value"        => "Mid Rise",    // ✅ exact enum from schema
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fit_type" => [[
                "value"          => "Slim",          // ✅ valid enum from schema
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value"            => "Black",
                "standardized_values" => ["Black"],  // ✅ valid standardized color enum
                "language_tag"     => "en_US",
                "marketplace_id"   => "ATVPDKIKX0DER"
            ]],
            "care_instructions" => [[
                "value"          => "Machine Wash",  // ✅ exact enum from schema
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "import_designation" => [[
                "value"          => "Imported",      // ✅ exact enum from schema
                "language_tag"   => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "country_of_origin" => [[
                "value"          => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value"          => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ── GTIN exemption — use this instead of a fake EAN ───────────────
            "supplier_declared_has_product_identifier_exemption" => [[
                "value"          => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
        ];
        // ── Additional product images ─────────────────────────────────────────
        $imageIndex = 1;
        foreach ($images as $index => $img) {
            if ($index === 0) continue;
            $imageUrl = $img['src'] ?? $img['url'] ?? null;
            if (empty($imageUrl)) continue;
            $payload['other_product_image_locator_' . $imageIndex] = [[
                "media_location" => $imageUrl,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $imageIndex++;
            if ($imageIndex > 8) break;
        }
        return $payload;
    }
    public function pantsVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO PANTS VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->pantsFullPayload(
                $product,
                $amazon
            );
            // Remove offer data from parent
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove parent images
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent relationship
            $parentPayload['variation_theme'] = [[
                "name" => "SIZE/COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('PANTS PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // Child variants
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('PANTS-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price'] ?? 0
                );
                $qty = (int)(
                    $variant['inventory_quantity'] ?? 0
                );
                // Shopify option1 = Color
                $color = trim(
                    $variant['option1'] ?? 'Black'
                );
                // Shopify option2 = Size
                $size = trim(
                    strtolower(
                        $variant['option2'] ?? 'm'
                    )
                );
                // Child payload
                $childPayload = $this->pantsFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add variant image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // Price
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Inventory
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "standardized_values" => [
                        ucfirst($color)
                    ],
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Variant size
                $childPayload['bottoms_size'] = [[
                    "size_system" => "as1",
                    "size_class" => "alpha",
                    "size" => $size,
                    "body_type" => "regular",
                    "height_type" => "regular",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Child relationship
                $childPayload['variation_theme'] = [[
                    "name" => "SIZE/COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('PANTS CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update product status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon pants variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon pants variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('PANTS VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function shoesFullPayload($product, $amazon)  // 5 
    {
        $variants = $this->parseJsonField(
            $product->variants
        );
        $images = $this->parseJsonField(
            $product->images
        );
        $price = $variants[0]['price']
            ?? $product->price
            ?? 0;
        $qty = $variants[0]['inventory_quantity']
            ?? 0;
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        $cleanTitle = preg_replace(
            '/^Title:\s*/i',
            '',
            $amazon->amazon_title
                ?? $product->title
                ?? ''
        );
        $payload = [
            // Title
            "item_name" => [[
                "value" => substr(
                    trim($cleanTitle),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Brand
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN exemption
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Manufacturer
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Product type
            "item_type_keyword" => [[
                "value" => "SHOES",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Description
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Comfortable running shoes'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Bullet points
            "bullet_point" => collect(
                $bulletPoints
            )->map(
                fn($b) => [
                    "value" => $b,
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]
            )->values()->toArray(),
            // Search keywords
            "generic_keyword" => [[
                "value" => implode(
                    ' ',
                    $searchTerms
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Style
            "style" => [[
                "value" => "Running",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Color
            "color" => [[
                "value" => "Black",
                "standardized_values" => [
                    "Black"
                ],
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Part number
            "part_number" => [[
                "value" => "SHOE-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Model number
            "model_number" => [[
                "value" => "RUN-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Model name
            "model_name" => [[
                "value" => "Running Sneakers",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Gender
            "target_gender" => [[
                "value" => "male",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Age range
            "age_range_description" => [[
                "value" => "Adult",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Outer material
            "outer" => [[
                "material" => [[
                    "value" => "Mesh",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Sole material
            "sole_material" => [[
                "value" => "Rubber",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Closure
            "closure" => [[
                "type" => [[
                    "value" => "Lace-Up",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Heel
            "heel" => [[
                "type" => [[
                    "value" => "Flat",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Shoe type
            "height_map" => [[
                "value" => "Low Top",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "footwear_size" => [[
                "size_system" => "us_footwear_size_system",
                "size_class" => "numeric",
                "size" => "numeric_9",
                "gender" => "men",
                "age_group" => "adult",
                "width" => "medium",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Main image
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Price
            "list_price" => [[
                "value" => (float) $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Inventory
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int) $qty
            ]],
            // Condition
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Country
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Batteries
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // Extra images
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function shoesVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO SHOE VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->shoesFullPayload(
                $product,
                $amazon
            );
            // Remove offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove parent images
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent relationship
            $parentPayload['variation_theme'] = [[
                "name" => "SIZE/COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('SHOE PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // Child variants
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('SHOE-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price'] ?? 0
                );
                $qty = (int)(
                    $variant['inventory_quantity'] ?? 0
                );
                // Shopify option1 = Color
                $color = trim(
                    $variant['option1'] ?? 'Black'
                );
                // Shopify option2 = Size
                $size = trim(
                    $variant['option2'] ?? '9'
                );
                // Convert size for Amazon enum
                $amazonSize = 'numeric_' . $size;
                // Child payload
                $childPayload = $this->shoesFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add variant image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // Price
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Inventory
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "standardized_values" => [
                        ucfirst($color)
                    ],
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Variant footwear size
                $childPayload['footwear_size'] = [[
                    "size_system" => "us_footwear_size_system",
                    "size_class" => "numeric",
                    "size" => $amazonSize,
                    "gender" => "men",
                    "age_group" => "adult",
                    "width" => "medium",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Child relationship
                $childPayload['variation_theme'] = [[
                    "name" => "SIZE/COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('SHOE CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update sync status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon shoe variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon shoe variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('SHOE VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function backpackFullPayload($product, $amazon)  // 6
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $price = $variants[0]['price']
            ?? $product->price
            ?? 0;
        $qty = $variants[0]['inventory_quantity']
            ?? 0;
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        $cleanTitle = preg_replace(
            '/^Title:\s*/i',
            '',
            $amazon->amazon_title
                ?? $product->title
                ?? ''
        );
        $payload = [
            // TITLE
            "item_name" => [[
                "value" => substr(trim($cleanTitle), 0, 200),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BRAND
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN EXEMPTION
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRODUCT TYPE
            "item_type_keyword" => [[
                "value" => "BACKPACK",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MODEL
            "model_number" => [[
                "value" => "BP-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Casual Backpack",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MANUFACTURER
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DESCRIPTION
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Durable everyday backpack'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BULLETS
            "bullet_point" => collect($bulletPoints)
                ->map(fn($b) => [
                    "value" => substr($b, 0, 100),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            // SEARCH TERMS
            "generic_keyword" => [[
                "value" => substr(
                    implode(' ', $searchTerms),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // STYLE
            "style" => [[
                "value" => "Casual",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GENDER
            "target_gender" => [[
                "value" => "unisex",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // AGE
            "age_range_description" => [[
                "value" => "Adult",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MATERIAL
            "material" => [[
                "value" => "Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // LINING
            "lining_description" => [[
                "value" => "Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DEPARTMENT
            "department" => [[
                "value" => "unisex-adult",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // STORAGE VOLUME
            "storage_volume" => [[
                "value" => 30,
                "unit" => "liters",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // OUTER MATERIAL
            "outer" => [[
                "material" => [[
                    "value" => "Polyester",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // IMPORT DESIGNATION
            "import_designation" => [[
                "value" => "imported",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SPECIAL FEATURES
            "special_feature" => [[
                "value" => "Water Resistant",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // RECOMMENDED USES
            "recommended_uses_for_product" => [[
                "value" => "Travel",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ITEM DIMENSIONS
            "item_depth_width_height" => [[
                "depth" => [
                    "value" => 20,
                    "unit" => "centimeters"
                ],
                "width" => [
                    "value" => 32,
                    "unit" => "centimeters"
                ],
                "height" => [
                    "value" => 48,
                    "unit" => "centimeters"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // STRAP
            "strap_type" => [[
                "value" => "Adjustable",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // WATER RESISTANCE
            "water_resistance_level" => [[
                "value" => "water_resistant",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COLOR
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SIZE
            "size" => [[
                "value" => "One Size",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PART NUMBER
            "part_number" => [[
                "value" => "BAG-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BACKPACK TYPE
            "backpack_design" => [[
                "value" => "daypack_backpack",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CLOSURE
            "closure" => [[
                "type" => [[
                    "value" => "Zipper",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MAIN IMAGE
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRICE
            "list_price" => [[
                "value" => (float) $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INVENTORY
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int) $qty
            ]],
            // CONDITION
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COUNTRY
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DG/HZ
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERIES
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // EXTRA IMAGES
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function backpackVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO BACKPACK VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->backpackFullPayload(
                $product,
                $amazon
            );
            // Remove offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove parent images
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent relationship
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR/SIZE",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('BACKPACK PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // Child variants
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('BACKPACK-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price'] ?? 0
                );
                $qty = (int)(
                    $variant['inventory_quantity'] ?? 0
                );
                // Shopify option1 = Color
                $color = trim(
                    $variant['option1'] ?? 'Black'
                );
                // Shopify option2 = Size
                $size = trim(
                    $variant['option2'] ?? 'One Size'
                );
                // Child payload
                $childPayload = $this->backpackFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add variant image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // Price
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Inventory
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Variant size
                $childPayload['size'] = [[
                    "value" => $size,
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Child relationship
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR/SIZE",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('BACKPACK CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update sync status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon backpack variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon backpack variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('BACKPACK VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function inputMouseFullPayload($product, $amazon)  // 7 
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $price = $variants[0]['price']
            ?? $product->price
            ?? 0;
        $qty = $variants[0]['inventory_quantity']
            ?? 0;
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        $cleanTitle = preg_replace(
            '/^Title:\s*/i',
            '',
            $amazon->amazon_title
                ?? $product->title
                ?? ''
        );
        $payload = [
            // TITLE
            "item_name" => [[
                "value" => substr(trim($cleanTitle), 0, 200),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BRAND
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN EXEMPTION
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRODUCT TYPE
            "item_type_keyword" => [[
                "value" => "INPUT_MOUSE",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MODEL
            "model_number" => [[
                "value" => "MOUSE-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Gaming Mouse",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MANUFACTURER
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DESCRIPTION
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'USB wired gaming mouse'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BULLET POINTS
            "bullet_point" => collect($bulletPoints)
                ->map(fn($b) => [
                    "value" => substr($b, 0, 200),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            // SEARCH TERMS
            "generic_keyword" => [[
                "value" => substr(
                    implode(' ', $searchTerms),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SPECIAL FEATURE
            "special_feature" => [[
                "value" => "LED Lighting",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // STYLE
            "style" => [[
                "value" => "Gaming",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MATERIAL
            "material" => [[
                "value" => "Plastic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ITEM COUNT
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PACKAGE QUANTITY
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COLOR
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SIZE
            "size" => [[
                "value" => "Standard",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PART NUMBER
            "part_number" => [[
                "value" => "MOUSE-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // EDITION
            "edition" => [[
                "value" => "Standard",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CONFIGURATION
            "configuration" => [[
                "value" => "Wired",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // HARDWARE PLATFORM
            "hardware_platform" => [[
                "value" => "PC",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PLATFORM
            "platform_for_display" => [[
                "value" => "Windows",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // POWER SOURCE
            "power_source_type" => [[
                "value" => "Corded Electric",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SENSOR TECHNOLOGY
            "movement_detection_technology" => [[
                "value" => "Optical",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CONNECTIVITY
            "connectivity_technology" => [[
                "value" => "USB",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // OS
            "operating_system" => [[
                "value" => "Windows",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // HAND ORIENTATION
            "hand_orientation" => [[
                "value" => "Right",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SENSOR
            "sensor" => [[
                "value" => "Optical",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BUTTONS
            "button_quantity" => [[
                "value" => 6,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CONTROL METHOD
            "control_method" => [[
                "value" => "push_button",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DPI
            "mouse_maximum_sensitivity" => [[
                "value" => 1600,
                "unit" => "dots_per_inch",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INCLUDED COMPONENTS
            "included_components" => [[
                "value" => "Gaming Mouse",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // RECOMMENDED USE
            "recommended_uses_for_product" => [[
                "value" => "Gaming",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COMPATIBLE DEVICES
            "compatible_devices" => [[
                "value" => "Laptop",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // WARRANTY
            "warranty_description" => [[
                "value" => "1 Year Limited Warranty",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERIES
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CONDITION
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRICE
            "list_price" => [[
                "value" => (float) $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INVENTORY
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int) $qty
            ]],
            // MAIN IMAGE
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COUNTRY
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DG/HZ
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // EXTRA IMAGES
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function inputMouseVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO MOUSE VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->inputMouseFullPayload(
                $product,
                $amazon
            );
            // Remove offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove parent images
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Remove GTIN for parent
            unset(
                $parentPayload['externally_assigned_product_identifier']
            );
            // Parent relationship
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Safety cleanup
            unset(
                $parentPayload['child_parent_sku_relationship']
            );
            // Upload parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('MOUSE PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // CHILD VARIANTS
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('MOUSE-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price'] ?? 0
                );
                $qty = (int)(
                    $variant['inventory_quantity'] ?? 0
                );
                // option1 = Color
                $color = trim(
                    $variant['option1'] ?? 'Black'
                );
                // Child payload
                $childPayload = $this->inputMouseFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image mapping
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add variant image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // Variant price
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Inventory
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Variation Theme
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Child relation
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('MOUSE CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update sync status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon mouse variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon mouse variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('MOUSE VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function watchFullPayload($product, $amazon)  // 8
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = $variant['price']
            ?? $product->price
            ?? 0;
        $qty = $variant['inventory_quantity']
            ?? 0;
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        $cleanTitle = preg_replace(
            '/^Title:\s*/i',
            '',
            $amazon->amazon_title
                ?? $product->title
                ?? ''
        );
        $payload = [
            // TITLE
            "item_name" => [[
                "value" => substr(trim($cleanTitle), 0, 200),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BRAND
            "brand" => [[
                "value" => "Symbol",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN EXEMPTION
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRODUCT TYPE
            "item_type_keyword" => [[
                "value" => "WATCH",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MODEL
            "model_number" => [[
                "value" => "AZ-SYM-SS21A-12C",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Analog Black Dial Watch",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // WARRANTY TYPE
            "warranty_type" => [[
                "value" => "manufacturer",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MULTIPLE BATTERY COMPONENTS
            "has_multiple_battery_powered_components" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CALENDAR TYPE
            "calendar_type" => [[
                "value" => "no_calendar",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY INSTALLATION
            "battery_installation_device_type" => [[
                "value" => "not_installed",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY COUNT
            "num_batteries" => [[
                "quantity" => 1,
                "type" => "lr44",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY DETAILS
            "battery" => [[
                "cell_composition" => [[
                    "value" => "alkaline",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY PACKAGING
            "non_lithium_battery_packaging" => [[
                "value" => "batteries_contained_in_equipment",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MANUFACTURER
            "manufacturer" => [[
                "value" => "Symbol",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DESCRIPTION
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Premium analog wrist watch with quartz movement and stylish black dial.'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BULLET POINTS
            "bullet_point" => collect($bulletPoints)
                ->map(fn($b) => [
                    "value" => substr($b, 0, 200),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            // SEARCH TERMS
            "generic_keyword" => [[
                "value" => substr(
                    implode(' ', $searchTerms),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // FEATURES
            "special_feature" => [[
                "value" => "Water Resistant",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // STYLE
            "style" => [[
                "value" => "Casual",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DEPARTMENT
            "department" => [[
                "value" => "mens",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // TARGET GENDER
            "target_gender" => [[
                "value" => "male",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MATERIAL
            "material" => [[
                "value" => "PU",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ITEM COUNT
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PACKAGE QUANTITY
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // STRAP TYPE
            "strap_type" => [[
                "value" => "strap",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // WATER RESISTANCE
            "water_resistance_level" => [[
                "value" => "water_resistant",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DISPLAY
            "display" => [[
                "value" => "Analog",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COLOR
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SIZE
            "size" => [[
                "value" => "One Size",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PART NUMBER
            "part_number" => [[
                "value" => "AZ-SYM-SS21A-12C",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CLASP
            "clasp_type" => [[
                "value" => "Buckle",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MOVEMENT
            "watch_movement_type" => [[
                "value" => "Quartz",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SHAPE
            "item_shape" => [[
                "value" => "Round",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // EDITION
            "edition" => [[
                "value" => "Standard Edition",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // POWER SOURCE
            "power_source_type" => [[
                "value" => "Battery Powered",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INCLUDED COMPONENTS
            "included_components" => [[
                "value" => "Watch",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // WARRANTY DESCRIPTION
            "warranty_description" => [[
                "value" => "1 Year Manufacturer Warranty",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY FLAGS
            "batteries_required" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CONDITION
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRICE
            "list_price" => [[
                "value" => (float) $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INVENTORY
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int) $qty
            ]],
            // MAIN IMAGE
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COUNTRY
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DG/HZ
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // EXTRA IMAGES
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function watchVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO WATCH VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->watchFullPayload(
                $product,
                $amazon
            );
            // Remove offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove images from parent
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent relationship
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('WATCH PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // CHILD VARIANTS
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('WATCH-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price'] ?? 0
                );
                $qty = (int)(
                    $variant['inventory_quantity'] ?? 0
                );
                // Shopify option1 = Color
                $color = trim(
                    $variant['option1'] ?? 'Black'
                );
                // Child payload
                $childPayload = $this->watchFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image mapping
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add variant image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // PRICE
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // INVENTORY
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant Color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // CHILD RELATIONSHIP
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('WATCH CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update sync status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon watch variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon watch variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('WATCH VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function cameraFullPayload($product, $amazon)  // 9
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = $variant['price']
            ?? $product->price
            ?? 0;
        $qty = $variant['inventory_quantity']
            ?? 0;
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        $cleanTitle = preg_replace(
            '/^Title:\s*/i',
            '',
            $amazon->amazon_title
                ?? $product->title
                ?? ''
        );
        $payload = [
            // TITLE
            "item_name" => [[
                "value" => substr(trim($cleanTitle), 0, 200),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BRAND
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN EXEMPTION
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRODUCT TYPE
            "item_type_keyword" => [[
                "value" => "CAMERA",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MODEL
            "model_number" => [[
                "value" => "CAM-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MANUFACTURER
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DESCRIPTION
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'High quality digital camera.'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BULLET POINTS
            "bullet_point" => collect($bulletPoints)
                ->map(fn($b) => [
                    "value" => substr($b, 0, 200),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            // SEARCH TERMS
            "generic_keyword" => [[
                "value" => substr(
                    implode(' ', $searchTerms),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COLOR
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // SIZE
            "size" => [[
                "value" => "Standard",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PART NUMBER
            "part_number" => [[
                "value" => "CAM-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INCLUDED COMPONENTS
            "included_components" => [[
                "value" => "Camera",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // ITEM COUNT
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PACKAGE QUANTITY
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY FLAGS
            "batteries_required" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // RECHARGEABLE BATTERY
            "includes_rechargable_battery" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // MULTIPLE BATTERY COMPONENTS
            "has_multiple_battery_powered_components" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY INSTALLATION
            "battery_installation_device_type" => [[
                "value" => "not_installed",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY COUNT
            "num_batteries" => [[
                "quantity" => 1,
                "type" => "aa",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY DETAILS
            "battery" => [[
                "cell_composition" => [[
                    "value" => "alkaline",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // BATTERY PACKAGING
            "non_lithium_battery_packaging" => [[
                "value" => "batteries_contained_in_equipment",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // WARRANTY
            "warranty_description" => [[
                "value" => "1 Year Manufacturer Warranty",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // CONDITION
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // PRICE
            "list_price" => [[
                "value" => (float) $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // INVENTORY
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => (int) $qty
            ]],
            // MAIN IMAGE
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // COUNTRY
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DG/HZ
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // EXTRA IMAGES
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function cameraVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO CAMERA VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->cameraFullPayload(
                $product,
                $amazon
            );
            // Remove offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove parent images
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent relationship
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('CAMERA PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // CHILD VARIANTS
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('CAMERA-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price'] ?? 0
                );
                $qty = (int)(
                    $variant['inventory_quantity'] ?? 0
                );
                // Shopify option1 = Color
                $color = trim(
                    $variant['option1'] ?? 'Black'
                );
                // Child payload
                $childPayload = $this->cameraFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image mapping
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add variant image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // PRICE
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // INVENTORY
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant Color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // CHILD RELATIONSHIP
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('CAMERA CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update sync status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon camera variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon camera variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('CAMERA VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function monitorFullPayload($product, $amazon) // 10
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        // Dynamic Price
        $price = (float) (
            $variant['price']
            ?? $product->price
            ?? 199.99
        );
        if ($price <= 1) {
            $price = 199.99;
        }
        // Quantity
        $qty = (int) (
            $variant['inventory_quantity']
            ?? 10
        );
        // Main Image
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        // Amazon Data
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        $payload = [
            // Title
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Brand
            "brand" => [[
                "value" => "ZEBRONICS",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Manufacturer
            "manufacturer" => [[
                "value" => "ZEBRONICS",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN Exemption
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Product Type
            "item_type_keyword" => [[
                "value" => "computer-monitors",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Model
            "model_number" => [[
                "value" => "ZEB-V19HD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "18.5 Inch LED Monitor",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Description
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'HD LED monitor with HDMI and VGA support.'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Bullet Points
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 200),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            // Search Terms
            "generic_keyword" => [[
                "value" => substr(
                    implode(' ', $searchTerms),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Style
            "style" => [[
                "value" => "Modern",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Material
            "material" => [[
                "value" => "Plastic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Item Count
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "unit_count" => [[
                "value" => 1,
                "unit" => "count",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Display
            "display" => [[
                "size" => [[
                    "value" => 18.5,
                    "unit" => "inches"
                ]],
                "resolution_maximum" => [[
                    "value" => "1366 x 768"
                ]],
                "technology" => [[
                    "value" => "LED"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "resolution" => [[
                "value" => "HD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Color
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Size
            "size" => [[
                "value" => "18.5 Inch",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Part Number
            "part_number" => [[
                "value" => "ZEB-V19HD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Shape
            "item_shape" => [[
                "value" => "Rectangular",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Horizontal Resolution
            "max_horizontal_resolution" => [[
                "value" => 1366,
                "unit" => "pixels",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Vertical Resolution
            "max_vertical_resolution" => [[
                "value" => 768,
                "unit" => "pixels",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Refresh Rate
            "refresh_rate" => [[
                "value" => 60,
                "unit" => "hertz",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Aspect Ratio
            "aspect_ratio" => [[
                "value" => "16:9",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Image Aspect Ratio
            "image_aspect_ratio" => [[
                "value" => "16:9",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Response Time
            "response_time" => [[
                "value" => 5,
                "unit" => "milliseconds",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Screen Surface
            "screen_surface_description" => [[
                "value" => "Glossy",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Item Dimensions
            "item_depth_width_height" => [[
                "depth" => [
                    "value" => 7,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 17,
                    "unit" => "inches"
                ],
                "height" => [
                    "value" => 13,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Compatible Devices
            "compatible_devices" => [[
                "value" => "Laptop, PC",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Included Components
            "included_components" => [[
                "value" => "Monitor, HDMI Cable, Power Cable, User Manual",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Warranty
            "warranty_description" => [[
                "value" => "1 Year Manufacturer Warranty",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Batteries
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "has_multiple_battery_powered_components" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Condition
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Price
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Inventory
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]],
            // Main Image
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Country
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DG/HZ
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // Extra Images
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function monitorVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error('NO MONITOR VARIANTS FOUND', [
                    'product_id' => $product->id
                ]);
                return false;
            }
            // Parent SKU
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->monitorFullPayload(
                $product,
                $amazon
            );
            // Remove offer data
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            // Remove parent images
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent Relationship
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload Parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info('MONITOR PARENT RESPONSE', [
                'sku' => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);
            // CHILD VARIANTS
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('MONITOR-' . $variant['id'])
                );
                // Dynamic Price
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 199.99
                );
                if ($price <= 1) {
                    $price = 199.99;
                }
                // Quantity
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                // Shopify option1 = Color
                $color = trim(
                    $variant['option1']
                        ?? 'Black'
                );
                // Child Payload
                $childPayload = $this->monitorFullPayload(
                    $product,
                    $amazon
                );
                // Remove gallery images
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant image mapping
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                // Add Variant Image
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                // Price
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Inventory
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant Color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Child Relationship
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                // Upload Child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info('MONITOR CHILD RESPONSE', [
                    'sku' => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }
            // Update sync status
            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync' => $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' => 'Amazon monitor variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Amazon monitor variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error('MONITOR VARIANT UPLOAD FAILED', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function notebookComputerFullPayload($product, $amazon) // 11
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float) (
            $variant['price']
            ?? $product->price
            ?? 999.99
        );
        if ($price <= 1) {
            $price = 999.99;
        }
        $qty = (int) (
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            // REQUIRED
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "HP",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "standard-laptop-computers",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Notebook Computer'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // RECOMMENDED
            "manufacturer" => [[
                "value" => "HP",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "HP15-I5",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "HP Notebook",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Silver",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]],
            "graphics_processor_manufacturer" => [[
                "value" => "NVIDIA",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "version_for_country" => [[
                "value" => "US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "cpu_model" => [[
                "manufacturer" => [[
                    "value" => "Intel"
                ]],
                "model_number" => [[
                    "value" => "i7-13700HX"
                ]],
                "family" => [[
                    "value" => "core_i7"
                ]],
                "speed" => [[
                    "value" => 2.1,
                    "unit" => "GHz"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "processor_count" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "operating_system" => [[
                "value" => "Windows 11",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "display" => [[
                "type" => [[
                    "value" => "LED",
                    "language_tag" => "en_US"
                ]],
                "technology" => [[
                    "value" => "LCD",
                    "language_tag" => "en_US"
                ]],
                "size" => [[
                    "value" => 15.6,
                    "unit" => "inches"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "graphics_description" => [[
                "value" => "Dedicated",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "graphics_ram" => [[
                "size" => [[
                    "value" => 8,
                    "unit" => "GB"
                ]],
                "type" => [[
                    "value" => "gddr6"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "graphics_card_interface" => [[
                "value" => "pci_e",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_display_weight" => [[
                "value" => 2.4,
                "unit" => "kilograms",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_length_width_thickness" => [[
                "length" => [
                    "value" => 14.1,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 10.2,
                    "unit" => "inches"
                ],
                "thickness" => [
                    "value" => 0.95,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "ram_memory" => [[
                "installed_size" => [[
                    "value" => 16,
                    "unit" => "GB"
                ]],
                "maximum_size" => [[
                    "value" => 32,
                    "unit" => "GB"
                ]],
                "technology" => [[
                    "value" => "DDR5",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "flash_memory" => [[
                "installed_size" => [[
                    "value" => 1024,
                    "unit" => "GB"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "hard_disk" => [[
                "description" => [[
                    "value" => "SSD"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Laptop, Power Adapter, User Manual",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "warranty_description" => [[
                "value" => "1 Year Manufacturer Warranty",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "modified_product" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "specific_uses_for_product" => [[
                "value" => "Gaming",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "target_region" => [[
                "value" => "Global",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "total_usb_3_0_ports" => [[
                "value" => 3,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "total_usb_2_0_ports" => [[
                "value" => 0,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_year" => [[
                "value" => 2025,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function notebookComputerVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO NOTEBOOK VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload =
                $this->notebookComputerFullPayload(
                    $product,
                    $amazon
                );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach (
                $parentPayload as $key => $value
            ) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset(
                        $parentPayload[$key]
                    );
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'NOTEBOOK PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' => $parentBody->status ?? null,
                    'issues' => $parentBody->issues ?? [],
                ]
            );
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'NOTEBOOK-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 999.99
                );
                if ($price <= 1) {
                    $price = 999.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Silver'
                );
                $childPayload =
                    $this->notebookComputerFullPayload(
                        $product,
                        $amazon
                    );
                foreach (
                    $childPayload as $key => $value
                ) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset(
                            $childPayload[$key]
                        );
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code"
                    => "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['color'] = [[
                    "value" => ucfirst(
                        $color
                    ),
                    "language_tag" =>
                    "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'NOTEBOOK CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' => $childBody->status ?? null,
                        'issues' => $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' =>
                'Amazon notebook variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon notebook variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'NOTEBOOK VARIANT UPLOAD FAILED',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' =>
                $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function footwearFullPayload($product, $amazon) // 12
    {
        $variants = $this->parseJsonField(
            $product->variants
        );
        $images = $this->parseJsonField(
            $product->images
        );
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Nike",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "road-running-shoes",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Footwear'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect(
                $bulletPoints
            )->map(fn($point) => [
                "value" => substr(
                    $point,
                    0,
                    500
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ])->values()->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Nike",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "NK-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Running Shoe",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "style" => [[
                "value" => "Sport",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "target_gender" => [[
                "value" => "male",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "age_range_description" => [[
                "value" => "Adult",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Black",
                "standardized_values" => [
                    "Black"
                ],
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "size" => [[
                "value" => "9",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "footwear_size" => [[
                "size_system" =>
                "us_footwear_size_system",
                "size_class" =>
                "numeric",
                "size" =>
                "numeric_9",
                "gender" =>
                "men",
                "age_group" =>
                "adult",
                "width" =>
                "medium",
                "marketplace_id" =>
                "ATVPDKIKX0DER"
            ]],
            "water_resistance_level" => [[
                "value" => "water_resistant",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]],
            "closure" => [[
                "type" => [[
                    "value" => "Lace-Up",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "sole_material" => [[
                "value" => "rubber",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "cpsia_cautionary_statement" => [[
                "value" => "no_warning_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "heel" => [[
                "type" => [[
                    "value" => "flat"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "height_map" => [[
                "value" => "low_top",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "contains_battery_or_cell" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
        ];
        foreach ($images as $index => $img) {
            if (
                $index === 0 ||
                $index > 8
            ) {
                continue;
            }
            $payload['other_product_image_locator_' .
                $index] = [[
                "media_location" =>
                $img['src'],
                "marketplace_id" =>
                "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function footwearVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO FOOTWEAR VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent Payload
            $parentPayload = $this->footwearFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted = (
                $parentBody->status ?? null
            ) === 'ACCEPTED';
            LOG::info(
                'FOOTWEAR PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' => $parentBody->status ?? null,
                    'issues' => $parentBody->issues ?? [],
                ]
            );
            // CHILD VARIANTS
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'FOOTWEAR-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Black'
                );
                $childPayload =
                    $this->footwearFullPayload(
                        $product,
                        $amazon
                    );
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" =>
                    "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['color'] = [[
                    "value" => ucfirst(
                        $color
                    ),
                    "standardized_values" => [
                        ucfirst($color)
                    ],
                    "language_tag" =>
                    "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'FOOTWEAR CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' =>
                        $childBody->status ?? null,
                        'issues' =>
                        $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'success',
                'message' =>
                'Amazon footwear variation sync successful',
                'type' => 'product'
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon footwear variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'FOOTWEAR VARIANT UPLOAD FAILED',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );
            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => 'amazon',
                'status' => 'error',
                'error_message' =>
                $e->getMessage(),
                'type' => 'product'
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function handbagFullPayload($product, $amazon) // 13
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float) (
            $variant['price']
            ?? $product->price
            ?? 29.99
        );
        if ($price <= 1) {
            $price = 29.99;
        }
        $qty = (int) (
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    125
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "clutch-handbags",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Women Clutch Handbag'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "HB-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Designer Clutch",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "department" => [[
                "value" => "Womens",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "target_gender" => [[
                "value" => "female",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "age_range_description" => [[
                "value" => "Adult",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "style" => [[
                "value" => "Evening",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "lining_description" => [[
                "value" => "Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "pattern_type" => [[
                "value" => "Geometric",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "special_feature" => [[
                "value" => "Lightweight",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]],
            "size_info" => [[
                "display_name" => [[
                    "value" => "Medium",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "seasons" => [[
                "value" => "all_seasons",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "capacity" => [[
                "value" => 1,
                "unit" => "liters",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Blue",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "inner" => [[
                "material" => [[
                    "value" => "Polyester",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "outer" => [[
                "material" => [[
                    "value" => "Polyester",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_compartments" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "import_designation" => [[
                "value" => "imported",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "strap_type" => [[
                "value" => "Chain Strap",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "closure" => [[
                "type" => [[
                    "value" => "Snap"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_dimensions" => [[
                "length" => [
                    "value" => 12,
                    "unit" => "centimeters"
                ],
                "width" => [
                    "value" => 5,
                    "unit" => "centimeters"
                ],
                "height" => [
                    "value" => 30,
                    "unit" => "centimeters"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function handbagVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO HANDBAG VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent
            $parentPayload = $this->handbagFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            // Parent Relation
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            // Upload Parent
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'HANDBAG PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' =>
                    $parentBody->status ?? null,
                    'issues' =>
                    $parentBody->issues ?? [],
                ]
            );
            // Children
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'HANDBAG-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 29.99
                );
                if ($price <= 1) {
                    $price = 29.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Blue'
                );
                // Child Payload
                $childPayload =
                    $this->handbagFullPayload(
                        $product,
                        $amazon
                    );
                foreach (
                    $childPayload as $key => $value
                ) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset(
                            $childPayload[$key]
                        );
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant Image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                // Offer
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" =>
                    "DEFAULT",
                    "quantity" => $qty
                ]];
                // Color
                $childPayload['color'] = [[
                    "value" => ucfirst(
                        $color
                    ),
                    "language_tag" =>
                    "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                // Child Relation
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                // Upload Child
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'HANDBAG CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' =>
                        $childBody->status ?? null,
                        'issues' =>
                        $childBody->issues ?? [],
                    ]
                );
            }
            // Sync Status
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon handbag variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'HANDBAG VARIANT UPLOAD FAILED',
                [
                    'error' =>
                    $e->getMessage(),
                    'line' =>
                    $e->getLine(),
                    'file' =>
                    $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' =>
                $e->getMessage()
            ]);
        }
    }
    public function cableFullPayload($product, $amazon) // 14
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float) (
            $variant['price']
            ?? $product->price
            ?? 9.99
        );
        if ($price <= 1) {
            $price = 9.99;
        }
        $qty = (int) (
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "electrical-cable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Cable'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 700),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "CBL-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => "CBL-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "size" => [[
                "value" => "1 Meter",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Cable",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]],
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function cableVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO CABLE VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent
            $parentPayload = $this->cableFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'CABLE PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' =>
                    $parentBody->status ?? null,
                    'issues' =>
                    $parentBody->issues ?? [],
                ]
            );
            // Children
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'CABLE-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 9.99
                );
                if ($price <= 1) {
                    $price = 9.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Black'
                );
                $childPayload =
                    $this->cableFullPayload(
                        $product,
                        $amazon
                    );
                foreach (
                    $childPayload as $key => $value
                ) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset(
                            $childPayload[$key]
                        );
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant Image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                // Offer
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" =>
                    "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant Color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                // Child Relation
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'CABLE CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' =>
                        $childBody->status ?? null,
                        'issues' =>
                        $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon cable variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'CABLE VARIANT UPLOAD FAILED',
                [
                    'error' =>
                    $e->getMessage(),
                    'line' =>
                    $e->getLine(),
                    'file' =>
                    $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' =>
                $e->getMessage()
            ]);
        }
    }
    public function chairFullPayload($product, $amazon) // 15
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "dining-chairs",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Chair'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Wood",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Office Chair",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "CHR-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => "CHR-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Ergonomic Office Chair",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_assembly_required" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_fragile" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_shape" => [[
                "value" => "L-Shaped",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_weight" => [[
                "value" => 15,
                "unit" => "kilograms",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "maximum_weight_recommendation" => [[
                "value" => 120,
                "unit" => "kilograms",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "frame" => [[
                "material" => [[
                    "value" => "Metal",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "seat" => [[
                "depth" => [
                    [
                        "value" => 45,
                        "unit" => "centimeters"
                    ]
                ],
                "height" => [
                    [
                        "value" => 50,
                        "unit" => "centimeters"
                    ]
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_depth_width_height" => [[
                "depth" => [
                    "value" => 24,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 24,
                    "unit" => "inches"
                ],
                "height" => [
                    "value" => 48,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]]
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function chairVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO CHAIR VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent
            $parentPayload = $this->chairFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'CHAIR PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' =>
                    $parentBody->status ?? null,
                    'issues' =>
                    $parentBody->issues ?? [],
                ]
            );
            // Children
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'CHAIR-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Black'
                );
                $childPayload =
                    $this->chairFullPayload(
                        $product,
                        $amazon
                    );
                foreach (
                    $childPayload as $key => $value
                ) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset(
                            $childPayload[$key]
                        );
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant Image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                // Offer
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" =>
                    "DEFAULT",
                    "quantity" => $qty
                ]];
                // Variant Color
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                // Child Relation
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'CHAIR CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' =>
                        $childBody->status ?? null,
                        'issues' =>
                        $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon chair variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'CHAIR VARIANT UPLOAD FAILED',
                [
                    'error' =>
                    $e->getMessage(),
                    'line' =>
                    $e->getLine(),
                    'file' =>
                    $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' =>
                $e->getMessage()
            ]);
        }
    }
    public function tableFullPayload($product, $amazon) // 16
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "dining-tables",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Wooden Table'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Brown",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Wood",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "top" => [[
                "material" => [[
                    "value" => "Wood",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "frame" => [[
                "material" => [[
                    "value" => "Wood",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Dining Table",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "base_type" => [[
                "value" => "Leg",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_shape" => [[
                "value" => "Rectangular",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_fragile" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_depth_width_height" => [[
                "depth" => [
                    "value" => 24,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 36,
                    "unit" => "inches"
                ],
                "height" => [
                    "value" => 30,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Table",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "TBL-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => "TBL-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_weight" => [[
                "value" => 20,
                "unit" => "kilograms",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_length" => [[
                "value" => 24,
                "unit" => "inches",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_width" => [[
                "value" => 36,
                "unit" => "inches",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]]
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function tableVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO TABLE VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent
            $parentPayload = $this->tableFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'TABLE PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' =>
                    $parentBody->status ?? null,
                    'issues' =>
                    $parentBody->issues ?? [],
                ]
            );
            // Children
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'TABLE-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Brown'
                );
                $childPayload =
                    $this->tableFullPayload(
                        $product,
                        $amazon
                    );
                foreach (
                    $childPayload as $key => $value
                ) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset(
                            $childPayload[$key]
                        );
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                // Variant Image
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" =>
                    "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'TABLE CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' =>
                        $childBody->status ?? null,
                        'issues' =>
                        $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon table variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'TABLE VARIANT UPLOAD FAILED',
                [
                    'error' =>
                    $e->getMessage(),
                    'line' =>
                    $e->getLine(),
                    'file' =>
                    $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' =>
                $e->getMessage()
            ]);
        }
    }
    public function sofaFullPayload($product, $amazon) // 17
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "sofas",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Comfortable Sofa'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Brown",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Wood",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Living Room Sofa",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "frame" => [[
                "material" => [[
                    "value" => "Wood",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Sofa",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "SOFA-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => "SOFA-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_weight" => [[
                "value" => 35,
                "unit" => "kilograms",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "seat" => [[
                "height" => [[
                    "value" => 18,
                    "unit" => "inches"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_fragile" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_assembly_required" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "sofa_type" => [[
                "value" => "standard",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "seating_capacity" => [[
                "value" => 3,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fabric_type" => [[
                "value" => "Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_depth_width_height" => [[
                "depth" => [
                    "value" => 36,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 82,
                    "unit" => "inches"
                ],
                "height" => [
                    "value" => 34,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_length" => [[
                "value" => 36,
                "unit" => "inches",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_width" => [[
                "value" => 84,
                "unit" => "inches",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]]
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function sofaVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO SOFA VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where(
                    'product_id',
                    $product->id
                )
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent
            $parentPayload = $this->sofaFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability']
            );
            unset(
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'SOFA PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' =>
                    $parentBody->status ?? null,
                    'issues' =>
                    $parentBody->issues ?? [],
                ]
            );
            // Children
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? (
                            'SOFA-' .
                            $variant['id']
                        )
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Brown'
                );
                $childPayload =
                    $this->sofaFullPayload(
                        $product,
                        $amazon
                    );
                foreach (
                    $childPayload as $key => $value
                ) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset(
                            $childPayload[$key]
                        );
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids'])
                        &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage =
                            $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" =>
                        $variantImage,
                        "marketplace_id" =>
                        "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" =>
                    "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['color'] = [[
                    "value" => ucfirst($color),
                    "language_tag" => "en_US",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" =>
                    $parentSku,
                    "child_relationship_type" =>
                    "variation",
                    "marketplace_id" =>
                    "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'SOFA CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' =>
                        $childBody->status ?? null,
                        'issues' =>
                        $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            return response()->json([
                'success' => true,
                'message' =>
                'Amazon sofa variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'SOFA VARIANT UPLOAD FAILED',
                [
                    'error' =>
                    $e->getMessage(),
                    'line' =>
                    $e->getLine(),
                    'file' =>
                    $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' =>
                $e->getMessage()
            ]);
        }
    }
    public function mattressFullPayload($product, $amazon) // 18
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "mattresses",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Comfortable Mattress'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Memory Foam Mattress",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Memory Foam",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "construction_type" => [[
                "value" => "Memory Foam",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "size" => [[
                "value" => "Queen (U.S. Standard)",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "White",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_shape" => [[
                "value" => "Rectangular",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "special_feature" => [[
                "value" => "Pressure Relief",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "MAT-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => "MAT-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "sub_brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_weight" => [[
                "value" => 25,
                "unit" => "pounds",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fill_material" => [[
                "value" => "Memory Foam",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_firmness_description" => [[
                "value" => "Medium",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "top_style" => [[
                "value" => "tight_top",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "top" => [[
                "material" => [[
                    "value" => "Memory Foam",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Mattress",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_length_width_thickness" => [[
                "length" => [
                    "value" => 80,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 60,
                    "unit" => "inches"
                ],
                "thickness" => [
                    "value" => 10,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]]
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function mattressVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField($product->variants);
            $images = $this->parseJsonField($product->images);
            if (empty($variants)) {
                LOG::error(
                    'NO MATTRESS VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            // Parent
            $parentPayload = $this->mattressFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability'],
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "SIZE",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'MATTRESS PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' => $parentBody->status ?? null,
                    'issues' => $parentBody->issues ?? [],
                ]
            );
            // Children
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('MATTRESS-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $size = trim(
                    $variant['option1']
                        ?? 'Queen'
                );
                $childPayload = $this->mattressFullPayload(
                    $product,
                    $amazon
                );
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['size'] = [[
                    "value" => $size,
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "SIZE",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'MATTRESS CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' => $childBody->status ?? null,
                        'issues' => $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            LOG::info(
                'MATTRESS DB UPDATE',
                [
                    'product_id' => $product->id,
                    'synced_to_amazon' => $isAccepted ? 1 : 0,
                    'needs_resync' => $isAccepted ? 0 : 1
                ]
            );
            return response()->json([
                'success' => true,
                'message' => 'Amazon mattress variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'MATTRESS VARIANT UPLOAD FAILED',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function bedFullPayload($product, $amazon) // 19
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            "item_name" => [[
                "value" => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "brand" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_type_keyword" => [[
                "value" => "beds",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Comfortable Bed'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($point) => [
                    "value" => substr($point, 0, 500),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Generic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Modern Bed Frame",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "material" => [[
                "value" => "Wood",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fabric_type" => [[
                "value" => "100% Polyester",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "size" => [[
                "value" => "Queen (U.S. Standard)",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "color" => [[
                "value" => "Brown",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "style" => [[
                "value" => "Modern",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "age_range_description" => [[
                "value" => "Adult",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "special_feature" => [[
                "value" => "Upholstered",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_number" => [[
                "value" => "BED-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "part_number" => [[
                "value" => "BED-001",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_weight" => [[
                "value" => 50,
                "unit" => "pounds",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Bed Frame",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "frame" => [[
                "material" => [[
                    "value" => "Wood",
                    "language_tag" => "en_US"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_shape" => [[
                "value" => "Rectangular",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "is_assembly_required" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_length_width_height" => [[
                "length" => [
                    "value" => 80,
                    "unit" => "inches"
                ],
                "width" => [
                    "value" => 60,
                    "unit" => "inches"
                ],
                "height" => [
                    "value" => 48,
                    "unit" => "inches"
                ],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]]
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    public function bedVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField($product->variants);
            $images = $this->parseJsonField($product->images);
            if (empty($variants)) {
                LOG::error(
                    'NO BED VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            $parentPayload = $this->bedFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability'],
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "SIZE",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'BED PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' => $parentBody->status ?? null,
                    'issues' => $parentBody->issues ?? [],
                ]
            );
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('BED-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $size = trim(
                    $variant['option1']
                        ?? 'Queen'
                );
                $childPayload = $this->bedFullPayload(
                    $product,
                    $amazon
                );
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['size'] = [[
                    "value" => $size,
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "SIZE",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'BED CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'status' => $childBody->status ?? null,
                        'issues' => $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            LOG::info(
                'BED DB UPDATE',
                [
                    'product_id' => $product->id,
                    'synced_to_amazon' => $isAccepted ? 1 : 0,
                    'needs_resync' => $isAccepted ? 0 : 1
                ]
            );
            return response()->json([
                'success' => true,
                'message' => 'Amazon bed variation sync successful'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'BED VARIANT UPLOAD FAILED',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function staplerFullPayload($product, $amazon)  // 20
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        $price = (float)(
            $variant['price']
            ?? $product->price
            ?? 99.99
        );
        if ($price <= 1) {
            $price = 99.99;
        }
        $qty = (int)(
            $variant['inventory_quantity']
            ?? 10
        );
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $payload = [
            'item_name' => [[
                'value' => substr(
                    trim($amazon->amazon_title),
                    0,
                    200
                ),
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'brand' => [[
                'value' => 'Generic',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'item_type_keyword' => [[
                'value' => 'desk-staplers',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'product_description' => [[
                'value' => strip_tags(
                    $product->description
                        ?? 'Desktop stapler for office and school use.'
                ),
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'bullet_point' => collect($bulletPoints)
                ->map(fn($point) => [
                    'value' => substr($point, 0, 500),
                    'language_tag' => 'en_US',
                    'marketplace_id' => 'ATVPDKIKX0DER'
                ])
                ->values()
                ->toArray(),
            'country_of_origin' => [[
                'value' => 'IN',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'supplier_declared_dg_hz_regulation' => [[
                'value' => 'not_applicable',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'supplier_declared_has_product_identifier_exemption' => [[
                'value' => true,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'manufacturer' => [[
                'value' => 'Generic',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'model_number' => [[
                'value' => 'STP-001',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'part_number' => [[
                'value' => 'STP-001',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'style' => [[
                'value' => 'Modern',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'material' => [[
                'value' => 'Metal',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'color' => [[
                'value' => 'Black',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'size' => [[
                'value' => 'Standard',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'operation_mode' => [[
                'value' => 'Manual',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'pattern' => [[
                'value' => 'Solid',
                'language_tag' => 'en_US',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'number_of_items' => [[
                'value' => 1,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'item_package_quantity' => [[
                'value' => 1,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'unit_count' => [[
                'value' => 1,
                'type' => [
                    'value' => 'Count',
                    'language_tag' => 'en_US'
                ],
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'batteries_required' => [[
                'value' => false,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'item_package_weight' => [[
                'value' => 1.5,
                'unit' => 'pounds',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'item_package_dimensions' => [[
                'length' => [
                    'value' => 8,
                    'unit' => 'inches'
                ],
                'width' => [
                    'value' => 3,
                    'unit' => 'inches'
                ],
                'height' => [
                    'value' => 2,
                    'unit' => 'inches'
                ],
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'list_price' => [[
                'value' => $price,
                'currency' => 'USD',
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'number_of_fasteners' => [[
                'value' => 1,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'item_length_width' => [[
                'length' => [
                    'value' => 6,
                    'unit' => 'inches'
                ],
                'width' => [
                    'value' => 2,
                    'unit' => 'inches'
                ],
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'main_product_image_locator' => [[
                'media_location' => $image,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]]
        ];
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload['other_product_image_locator_' . $index] = [[
                'media_location' => $img['src'],
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]];
        }
        return $payload;
    }
    public function staplerVariantPayload($shop, $product, $amazon)
    {
        try {
            $isAccepted = false;
            $variants = $this->parseJsonField(
                $product->variants
            );
            $images = $this->parseJsonField(
                $product->images
            );
            if (empty($variants)) {
                LOG::error(
                    'NO STAPLER VARIANTS FOUND',
                    [
                        'product_id' => $product->id
                    ]
                );
                return false;
            }
            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');
            if (!$parentSku) {
                throw new \Exception(
                    'Parent SKU not found'
                );
            }
            $parentPayload = $this->staplerFullPayload(
                $product,
                $amazon
            );
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability'],
                $parentPayload['main_product_image_locator']
            );
            foreach ($parentPayload as $key => $value) {
                if (
                    str_contains(
                        $key,
                        'other_product_image_locator'
                    )
                ) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name" => "COLOR",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentPayload['parentage_level'] = [[
                "value" => "parent",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
            $parentResponse = $this->putListing(
                $shop,
                $parentSku,
                $parentPayload,
                $product
            );
            $parentBody = method_exists(
                $parentResponse,
                'dto'
            )
                ? $parentResponse->dto()
                : null;
            $isAccepted =
                ($parentBody->status ?? null)
                === 'ACCEPTED';
            LOG::info(
                'STAPLER PARENT RESPONSE',
                [
                    'sku' => $parentSku,
                    'status' => $parentBody->status ?? null,
                    'issues' => $parentBody->issues ?? [],
                ]
            );
            foreach ($variants as $variant) {
                $sku = trim(
                    $variant['sku']
                        ?? ('STP-' . $variant['id'])
                );
                $price = (float)(
                    $variant['price']
                    ?? $product->price
                    ?? 99.99
                );
                if ($price <= 1) {
                    $price = 99.99;
                }
                $qty = (int)(
                    $variant['inventory_quantity']
                    ?? 0
                );
                $color = trim(
                    $variant['option1']
                        ?? 'Black'
                );
                $childPayload = $this->staplerFullPayload(
                    $product,
                    $amazon
                );
                foreach ($childPayload as $key => $value) {
                    if (
                        str_contains(
                            $key,
                            'other_product_image_locator'
                        )
                    ) {
                        unset($childPayload[$key]);
                    }
                }
                unset(
                    $childPayload['main_product_image_locator']
                );
                $variantImage = null;
                foreach ($images as $img) {
                    if (
                        !empty($img['variant_ids']) &&
                        in_array(
                            $variant['id'],
                            $img['variant_ids']
                        )
                    ) {
                        $variantImage = $img['src'];
                        break;
                    }
                }
                if ($variantImage) {
                    $childPayload['main_product_image_locator'] = [[
                        "media_location" => $variantImage,
                        "marketplace_id" => "ATVPDKIKX0DER"
                    ]];
                }
                $childPayload['color'] = [[
                    "value" => $color,
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['list_price'] = [[
                    "value" => $price,
                    "currency" => "USD",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity" => $qty
                ]];
                $childPayload['variation_theme'] = [[
                    "name" => "COLOR",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['parentage_level'] = [[
                    "value" => "child",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku" => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]];
                $childResponse = $this->putListing(
                    $shop,
                    $sku,
                    $childPayload,
                    $product
                );
                $childBody = method_exists(
                    $childResponse,
                    'dto'
                )
                    ? $childResponse->dto()
                    : null;
                if (
                    ($childBody->status ?? null)
                    !== 'ACCEPTED'
                ) {
                    $isAccepted = false;
                }
                LOG::info(
                    'STAPLER CHILD RESPONSE',
                    [
                        'sku' => $sku,
                        'color' => $color,
                        'status' => $childBody->status ?? null,
                        'issues' => $childBody->issues ?? [],
                    ]
                );
            }
            $product->update([
                'synced_to_amazon' =>
                $isAccepted ? 1 : 0,
                'needs_resync' =>
                $isAccepted ? 0 : 1
            ]);
            LOG::info(
                'STAPLER DB UPDATE',
                [
                    'product_id' => $product->id,
                    'synced_to_amazon' =>
                    $isAccepted ? 1 : 0,
                    'needs_resync' =>
                    $isAccepted ? 0 : 1
                ]
            );
            return response()->json([
                'success' => $isAccepted,
                'message' => $isAccepted
                    ? 'Amazon stapler variation sync successful'
                    : 'Amazon stapler variation sync failed'
            ]);
        } catch (\Throwable $e) {
            LOG::error(
                'STAPLER VARIANT UPLOAD FAILED',
                [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function keyboardFullPayload($product, $amazon)
    {
        $variants = $this->parseJsonField($product->variants);
        $images = $this->parseJsonField($product->images);
        $variant = $variants[0] ?? [];
        // Price
        $price = (float) (
            $variant['price']
            ?? $product->price
            ?? 49.99
        );
        if ($price <= 1) {
            $price = 49.99;
        }
        // Quantity
        $qty = (int) (
            $variant['inventory_quantity']
            ?? 0
        );
        // Main image
        $image = $images[0]['src']
            ?? 'https://via.placeholder.com/500';
        // Amazon fields
        $bulletPoints = $this->parseJsonField(
            $amazon->bullet_points
        );
        $searchTerms = $this->parseJsonField(
            $amazon->search_terms
        );
        // Clean title
        $cleanTitle = preg_replace(
            '/^Title:\s*/i',
            '',
            $amazon->amazon_title
                ?? $product->title
                ?? ''
        );
        $payload = [
            // Title
            "item_name" => [[
                "value" => substr(trim($cleanTitle), 0, 200),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Brand
            "brand" => [[
                "value" => "Portronics",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "manufacturer" => [[
                "value" => "Portronics",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // GTIN exemption
            "supplier_declared_has_product_identifier_exemption" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Product type
            "item_type_keyword" => [[
                "value" => "KEYBOARDS",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Model
            "model_number" => [[
                "value" => "HYDRA-10",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "model_name" => [[
                "value" => "Hydra 10 Mechanical Gaming Keyboard",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Description
            "product_description" => [[
                "value" => strip_tags(
                    $product->description
                        ?? 'Wireless RGB mechanical gaming keyboard.'
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Bullet points
            "bullet_point" => collect($bulletPoints)
                ->map(fn($b) => [
                    "value" => substr($b, 0, 200),
                    "language_tag" => "en_US",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ])
                ->values()
                ->toArray(),
            // Search terms
            "generic_keyword" => [[
                "value" => substr(
                    implode(' ', $searchTerms),
                    0,
                    250
                ),
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Features
            "special_feature" => [[
                "value" => "RGB Backlit",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "style" => [[
                "value" => "Gaming",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "theme" => [[
                "value" => "Gaming",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Material
            "material" => [[
                "value" => "Plastic",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Quantity details
            "number_of_items" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "item_package_quantity" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "unit_count" => [[
                "value" => 1,
                "unit" => "count",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Color and size
            "color" => [[
                "value" => "Black",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "size" => [[
                "value" => "Compact 68 Keys",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Part info
            "part_number" => [[
                "value" => "HYDRA10-KB",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "edition" => [[
                "value" => "Standard Edition",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "configuration" => [[
                "value" => "Keyboard Only",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Keyboard info
            "keyboard_description" => [[
                "value" => "Mechanical Gaming Keyboard",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "keyboard_layout" => [[
                "value" => "qwerty",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "keyboard_backlighting_color_support" => [[
                "value" => "rgb",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "switch_type" => [[
                "value" => "Mechanical",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Platform
            "hardware_platform" => [[
                "value" => "PC",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "platform_for_display" => [[
                "value" => "Windows",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "compatible_devices" => [[
                "value" => "Laptop, PC",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Language
            "language" => [[
                "value" => "english",
                "type" => "unknown",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Connectivity
            "connectivity_technology" => [[
                "value" => "Bluetooth",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "power_source_type" => [[
                "value" => "Battery Powered",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Battery
            "contains_battery_or_cell" => [[
                "value" => "battery",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_required" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "batteries_included" => [[
                "value" => true,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "has_multiple_battery_powered_components" => [[
                "value" => false,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "battery_installation_device_type" => [[
                "value" => "installed_in_equipment",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "number_of_lithium_ion_cells" => [[
                "value" => 1,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "num_batteries" => [[
                "quantity" => 1,
                "type" => "nonstandard_battery",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "battery" => [[
                "cell_composition" => [[
                    "value" => "lithium_ion",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "weight" => [[
                    "value" => 10,
                    "unit" => "grams",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "lithium_battery" => [[
                "energy_content" => [[
                    "value" => 3.7,
                    "unit" => "watt_hours",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "weight" => [[
                    "value" => 10,
                    "unit" => "grams",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "packaging" => [[
                    "value" => "batteries_contained_in_equipment",
                    "marketplace_id" => "ATVPDKIKX0DER"
                ]],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Package
            "customer_package_type" => [[
                "value" => "Retail Packaging",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            "included_components" => [[
                "value" => "Keyboard, USB Receiver, Charging Cable",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Warranty
            "warranty_description" => [[
                "value" => "1 Year Limited Warranty",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Orientation
            "orientation" => [[
                "value" => "Ambidextrous",
                "language_tag" => "en_US",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Water resistance
            "water_resistance_level" => [[
                "value" => "water_resistant",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Condition
            "condition_type" => [[
                "value" => "new_new",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Price
            "list_price" => [[
                "value" => $price,
                "currency" => "USD",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Inventory
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity" => $qty
            ]],
            // Main image
            "main_product_image_locator" => [[
                "media_location" => $image,
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // Country
            "country_of_origin" => [[
                "value" => "IN",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]],
            // DG/HZ
            "supplier_declared_dg_hz_regulation" => [[
                "value" => "not_applicable",
                "marketplace_id" => "ATVPDKIKX0DER"
            ]]
        ];
        // Extra images
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) {
                continue;
            }
            $payload["other_product_image_locator_" . $index] = [[
                "media_location" => $img['src'],
                "marketplace_id" => "ATVPDKIKX0DER"
            ]];
        }
        return $payload;
    }
    // this is test 
    public function getSchemaConnector($shop)
    {
        $creds = $this->getDbCredentials($shop);
        return SellingPartnerApi::seller(
            clientId: $creds['client_id'],
            clientSecret: $creds['client_secret'],
            refreshToken: $creds['refresh_token'],
            endpoint: Endpoint::NA,
        );
    }
    public function getProductTypeDefinition($shop)
    {
        try {
            $connector = $this->getSchemaConnector($shop);
            $request = new GetDefinitionsProductType(
                productType: 'SHOES',
                marketplaceIds: ['ATVPDKIKX0DER'],
                requirements: 'LISTING',
                locale: 'en_US',
                productTypeVersion: 'LATEST'
            );
            $response = $connector->send($request);
            return $response->json();
        } catch (\Throwable $e) {
            LOG::error('SCHEMA ERROR', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return [
                'error' => $e->getMessage()
            ];
        }
    }
}
