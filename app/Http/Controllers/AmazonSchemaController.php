<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use App\Services\AmazonSchemaService;
use App\Services\AmazonService;
use App\Services\AmazonPayloadTransformer;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use App\Models\Category;
use App\Models\AmazonSchema;
use App\Models\Product;
use App\Models\AmazonProduct;
use App\Models\ProductSyncLog;
use App\Services\AmazonPayloadAnalyzer;
use App\Services\Amazon\AmazonIssueNormalizer;
use App\Services\AmazonPayloadTransformerV2;
use App\DTOs\AmazonTransformerConfig;

class AmazonSchemaController extends Controller
{

    public function __construct(
        AmazonPayloadTransformerV2 $payloadTransformerV2,
        AmazonService $amazonService
    ) {
        $this->payloadTransformerV2 = $payloadTransformerV2;
        $this->amazonService = $amazonService;
    }

    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->orderBy('name')->get();
        return view( 'amazon-schema', compact('categories'));
    }

    public function getSubcategories($categoryId)
    {
        $subcategories = Category::where( 'parent_id', $categoryId )->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'subcategories' =>
            $subcategories
        ]);
    }
    // private function getPayloadFields(string $productType): array
    // {
    //     $productType = strtoupper($productType);
    //     return match ($productType) {
    //         'SHIRT' => [
    //             'item_name',
    //             'brand',
    //             'manufacturer',
    //             'product_description',
    //             'bullet_point',
    //             'generic_keyword',
    //             'color',
    //             'shirt_size',
    //             'fit_type',
    //             'neck',
    //             'sleeve',
    //             'department',
    //             'target_gender',
    //             'fabric_type',
    //             'style',
    //             'care_instructions',
    //             'country_of_origin',
    //             'externally_assigned_product_identifier',
    //             'list_price',
    //             'fulfillment_availability',
    //             'item_package_weight',
    //             'item_package_dimensions',
    //             'unit_count',
    //             'condition_type'
    //         ],
    //         default => []
    //     };
    // }
    public function getFields($slug)
    {
        try {
            $shop = Shop::first();
            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'No connected shop found',
                    'fields' => []
                ]);
            }
            $amazonService = new AmazonSchemaService();
            $storedSchema = AmazonSchema::where('category_slug', $slug )->first();
            if ( $storedSchema && $storedSchema->last_synced_at &&
                $storedSchema->last_synced_at->gt(now()->subDays(7))
            ) {
                Log::info('SCHEMA LOADED FROM DB', [
                    'slug' => $slug
                ]);
                $schema = json_decode($storedSchema->schema_json, true );
                $rules = json_decode($storedSchema->rules_json, true) ?? [];
             
                if ( empty($schema) ||  !is_array($schema) ) {

                    $storedSchema->delete();
                    $schema = $amazonService->getProductTypeDefinition(
                            $shop,  $slug );
                    $ruleParser = app(\App\Services\Amazon\AmazonSchemaRuleParser::class);
                    $rules =  $ruleParser->extract($schema);
                    AmazonSchema::updateOrCreate(
                        [
                            'category_slug' => $slug
                        ],
                        [
                            'schema_json'  => json_encode($schema),
                            'rules_json'   => json_encode($rules),
                            'last_synced_at' => now()
                        ]
                    );
                }
            } else {
                $schema = $amazonService->getProductTypeDefinition(
                        $shop,  $slug );
                if ( empty($schema) || isset($schema['message']) ||
                    !isset($schema['real_schema']['properties'])
                ) {
                    return response()->json([  'success' => false,
                        'message' => $schema['message']  ?? 'Schema fetch failed',
                        'fields' => []
                    ]);
                }
                $ruleParser =
                    app(\App\Services\Amazon\AmazonSchemaRuleParser::class);
                $rules = $ruleParser->extract($schema);
                AmazonSchema::updateOrCreate(
                    [
                        'category_slug' => $slug
                    ],
                    [
                        'schema_json'  => json_encode($schema),
                        'rules_json'   => json_encode($rules),
                        'last_synced_at' => now()
                    ]
                );
                Category::where('slug', $slug)
                    ->whereNotNull('parent_id')
                    ->update([
                        'status' => 'Active'
                    ]);
          
            }
  
            $fields = $amazonService->extractFields($schema);
            // $payloadFields = $this->getPayloadFields($slug);
            $payloadAnalyzer = app(AmazonPayloadAnalyzer::class);
            $method = $payloadAnalyzer->getPayloadMethodBySlug($slug);
            $amazonPayloadService = app(\App\Services\AmazonService::class);
            $product = new \stdClass();
            $product->title = 'Test Product';
            $product->description = 'Test Description';
            $product->vendor = 'Test Brand';
            $product->price = 100;
            $product->variants = json_encode([]);
            $product->images = json_encode([]);
            $amazon = new \stdClass();
            $amazon->amazon_title = 'Test Product';
            $amazon->sku = 'TESTSKU';
            $amazon->bullet_points = json_encode([
                'Bullet Point 1',
                'Bullet Point 2'
            ]);
            $amazon->search_terms = json_encode([
                'shirt',
                'cotton'
            ]);
          
            $payloadFields =
                $payloadAnalyzer->getPayloadFields(
                    $amazonPayloadService,
                    $method,
                    [
                        $product,
                        $amazon
                    ]
                );
            $payloadRoots = collect($payloadFields)
                ->map(function ($field) {
                    return explode('.', $field)[0];
                })->unique()->values()->toArray();
            $fields = collect($fields)->whereIn('key', $payloadRoots)
                ->values()->toArray();
            $fieldMap = [];
            foreach ($payloadFields as $payloadField) {
                $root = explode('.', $payloadField)[0];
                $fieldMap[$root][] = $payloadField;
            }
            $fieldData = [];
            foreach ($fields as &$field) {
                $field['payload_fields'] = $fieldMap[$field['key']] ?? [];
                if ( isset( $field['schema']['items']['properties'] )) {
                    $field['nested_fields'] = array_keys( $field['schema']['items']['properties']);
                }
            }

            $evaluator = app( \App\Services\Amazon\AmazonRuleEvaluator::class );
            $result = $evaluator->validate( $rules,  []  );

            Log::info('VALIDATOR ERRORS', [
                'errors' => $result['errors'] ?? []
            ]);
            if (strtoupper($slug) === 'SHIRT') {
                $exists = collect($fields)->contains('key', 'standardized_values');
                if (!$exists) {
                    $fields[] = [
                        'key' => 'standardized_values',
                        'label' => 'Standardized Values',
                        'type' => 'text',
                        'required' => true,
                        'schema' => [],
                        'payload_fields' => ['standardized_values'],
                        'nested_fields' => []
                    ];
                }
            }
            return response()->json([
                'success' => true,
                'product_type' => $schema['productType'] ?? $slug,
                'total_fields' => count($fields),
                'required_fields' => $schema['real_schema']['required'] ?? [],
                'fields' => $fields,
                'rules' => $rules
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'AMAZON SCHEMA CONTROLLER ERROR',
                [
                    'slug' => $slug,
                    'error' =>
                    $e->getMessage(),
                    'line' =>
                    $e->getLine(),
                    'file' =>
                    $e->getFile()
                ]
            );
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'fields' => []
            ], 500);
        }
    }
    public function validateRules(Request $request)
    {
        $slug = $request->slug;
        $schema = AmazonSchema::where(
            'category_slug',
            $slug
        )->first();
        if (!$schema) {
            return response()->json([
                'success' => false,
                'message' => 'Schema not found'
            ]);
        }
        $rules = json_decode(
            $schema->rules_json,
            true
        ) ?? [];
        $payload = $request->payload ?? [];
        $evaluator =
            app(
                \App\Services\Amazon\AmazonRuleEvaluator::class
            );
        $result =
            $evaluator->validate(
                $rules,
                $payload
            );

        \Log::info('RULE VALIDATION RESULT', [
            'slug' => $slug,
            'errors' => $result['errors'] ?? [],
            'dynamic_options' => $result['dynamic_options'] ?? [],
        ]);
        return response()->json([
            'success' => true,
            'errors' => $result['errors'] ?? [],
            'dynamic_options' =>
            $result['dynamic_options'] ?? []
        ]);
    }
    public function amazonTest(Request $request)
    {
        $categories = Category::whereNull('parent_id')
            ->orderBy('name')
            ->get();
        $product = null;
        $selectedCategory = '';
        $selectedSubcategory = '';
        $prefillData = [];
        if ($request->product_id) {
            $product = Product::find(
                $request->product_id
            );
            if ($product) {
                $categoryModel = $product->category_id
                    ? Category::find($product->category_id)
                    : null;
                $subcategoryModel = $product->sub_category_id
                    ? Category::find($product->sub_category_id)
                    : null;
                $selectedCategory = $categoryModel->name ?? '';
                $selectedSubcategory = $subcategoryModel->name ?? '';
                $description = strip_tags($product->description ?? '');
                $variants = $product->variants;
                $images = $product->images;
                $variants = is_array($variants)
                    ? $variants
                    : (json_decode($variants, true) ?? []);
                $images = is_array($images)
                    ? $images
                    : (json_decode($images, true) ?? []);
                $firstVariant = $variants[0] ?? [];
                Log::info('FIRST VARIANT', [
                    'variant' => $firstVariant
                ]);
                Log::info('IMAGES', [
                    'images' => $images
                ]);
                $prefillData['item_name'] =
                    $product->title ?? '';
                $prefillData['brand'] =
                    $product->vendor ?? '';
                $prefillData['description'] =
                    $description;
                $prefillData['product_description'] =
                    $description;
                $prefillData['item_description'] =
                    $description;
                $prefillData['item_type_keyword'] =
                    $product->product_type ?? '';
                $prefillData['list_price.value'] =
                    $firstVariant['price'] ?? '';
                $prefillData['fulfillment_availability.quantity'] =
                    $firstVariant['inventory_quantity'] ?? '';
                $prefillData['main_product_image_locator'] =
                    $images[0]['src'] ?? '';
                $imageIndex = 1;
                foreach ($images as $index => $image) {
                    if ($index === 0) {
                        continue;
                    }
                    $prefillData['other_product_image_locator_' . $imageIndex] =
                        $image['src'] ?? '';
                    $imageIndex++;
                }
                $prefillData['model_name'] =
                    $firstVariant['sku'] ?? '';
                $amazonProduct =
                    AmazonProduct::where(
                        'product_id',
                        $product->id
                    )->first();
                if ($amazonProduct) {
                    foreach (
                        $amazonProduct->getAttributes()
                        as $key => $value
                    ) {
                        if (  in_array( $key, [  'id',   'product_id',  'created_at',   'updated_at'  ]   )
                        ) {
                            continue;
                        }
                        if ( is_null($value) ||    $value === ''  ) {
                            continue;
                        }
                        $decoded =   json_decode(  $value, true );
                        if ( is_string($decoded)  ) {
                            $decoded =  json_decode( $decoded, true );
                        }
                        if (is_array($decoded)) {
                            $cleanValues = [];
                            array_walk_recursive(
                                $decoded,
                                function ($item)
                                use (&$cleanValues) {
                                    if (
                                        !is_null($item) &&
                                        $item !== ''
                                    ) {
                                        $cleanValues[] =
                                            trim($item);
                                    }
                                }
                            );
                            $finalValue =   implode(
                                    ', ',
                                    $cleanValues
                                );
                        } else {
                            $finalValue =
                                $decoded ?: $value;
                        }
                        $prefillData[$key] =
                            $finalValue;
                        if (
                            $key ===
                            'bullet_points'
                        ) {
                            $prefillData['bullet_point'] =
                                $finalValue;
                        }
                        if (
                            $key ===
                            'search_terms'
                        ) {
                            $prefillData['generic_keyword'] =
                                $finalValue;
                        }
                        if (
                            $key ===
                            'amazon_title'
                        ) {
                            $prefillData['item_name'] =
                                $finalValue;
                        }
                        if (
                            $key === 'sku'
                        ) {
                            $prefillData['model_name'] =
                                $finalValue;
                        }
                    }
                }
            }
        }
        Log::info(
            'PREFILL DATA',
            $prefillData
        );
        return view(
            'amazon-test',
            compact(
                'categories',
                'product',
                'selectedCategory',
                'selectedSubcategory',
                'prefillData'
            )
        );
    }
    public function manualSync(Request $request)
    {

        Log::info('ENV DEBUG', [
            'env' => env('AMAZON_PAYLOAD_TRANSFORMER'),
            'config' => config('amazon.payload_transformer'),
        ]);
        try {
            $request->validate([
                'product_id' => 'required',
                'payload' => 'required|array'
            ]);

            $product = Product::findOrFail($request->product_id);
            $shop = Shop::findOrFail($product->shop_id);

            $variants = is_array($product->variants)
                ? $product->variants
                : json_decode($product->variants, true);

            $sku = $variants[0]['sku'] ?? ('SKU-' . $product->id);

            // // 1. Generate Payload from Transformer (Source of Truth)
            // $transformer = new AmazonPayloadTransformer();
            // $payload = $transformer->build($request->payload);

            // $config = AmazonTransformerConfig::fromStore($shop);

            // Log::info('V2 Config Ready', [
            //     'marketplace' => $config->marketplaceId,
            //     'language' => $config->languageTag,
            // ]);

            // $type = null;

            // if ($product->sub_category_id) {
            //     $type = getCategoryData($product->sub_category_id, 'slug');
            // }

            // $productType = $type
            //     ? strtoupper(trim($type))
            //     : 'PRODUCT';

            // try {

            //     $v2Payload = $this->payloadTransformerV2->build(
            //         $shop,
            //         $productType,
            //         $request->payload,
            //         $config
            //     );

            //     Log::info('V2 PAYLOAD GENERATED', [
            //         'payload' => $v2Payload,
            //     ]);

            //     Log::info('================ PAYLOAD COMPARISON ================');

            //     Log::info('OLD PAYLOAD', [
            //         'payload' => $payload,
            //     ]);

            //     Log::info('V2 PAYLOAD', [
            //         'payload' => $v2Payload,
            //     ]);
            // } catch (\Throwable $e) {

            //     Log::error('V2 PAYLOAD FAILED', [
            //         'message' => $e->getMessage(),
            //         'line' => $e->getLine(),
            //         'file' => $e->getFile(),
            //     ]);
            // }
            $config = AmazonTransformerConfig::fromStore($shop);

            Log::info('Payload Transformer Config', [
                'driver' => config('amazon.payload_transformer'),
                'marketplace' => $config->marketplaceId,
                'language' => $config->languageTag,
            ]);

            $type = null;

            if ($product->sub_category_id) {
                $type = getCategoryData($product->sub_category_id, 'slug');
            }

            $productType = $type
                ? strtoupper(trim($type))
                : 'PRODUCT';

            try {

                if (config('amazon.payload_transformer') === 'v2') {

                    Log::info('USING AMAZON PAYLOAD TRANSFORMER V2');

                    $payload = $this->payloadTransformerV2->build(
                        $shop,
                        $productType,
                        $request->payload,
                        $config
                    );
                } else {

                    Log::info('USING AMAZON PAYLOAD TRANSFORMER V1');

                    $transformer = new AmazonPayloadTransformer();

                    $payload = $transformer->build(
                        $request->payload
                    );
                }

                Log::info('FINAL GENERATED PAYLOAD', [
                    'payload' => $payload,
                ]);
            } catch (\Throwable $e) {

                Log::error('PAYLOAD GENERATION FAILED', [
                    'driver' => config('amazon.payload_transformer'),
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);

                throw $e;
            }

            // 2. Identify Product Type dynamically
            // $productType = strtoupper($payload['item_type_keyword'][0]['value'] ?? '');

            // 3. Apply Global API Defaults safely (never overwriting user data)
            $payload = $this->applyGlobalDefaults($payload);

            // 4. Clean formatting and Amazon-specific structural rules
            $payload = $this->sanitizeFulfillmentAvailability($payload);
            $payload = $this->castBooleanFields($payload);

            // 5. Apply Dynamic Product-Type Specific Handlers
            $payload = $this->applyProductTypeDefaults($productType, $payload);

            unset($payload['merchant_shipping_group']);

            // if ($productType === 'FASHION-SNEAKERS') {
            //     unset($payload['heel']);
            //     unset($payload['outer']);
            // }




            // 6. Execute required debugging logs
            Log::info('PRODUCT TYPE', [
                'product_type' => $productType
            ]);
            Log::info('PAYLOAD KEYS', [
                'keys' => array_keys($payload)
            ]);
            Log::info('FINAL AMAZON PAYLOAD', [
                'payload' => $payload
            ]);



            // 7. Send to Amazon SP-API
            // $amazonService = new AmazonService();
            // $response = $amazonService->putListing($shop, $sku, $payload, $product);

            $response = $this->amazonService->putListing(
                $shop,
                $sku,
                $payload,
                $product
            );

            if (is_string($response)) {
                return response()->json([
                    'success' => false,
                    'message' => $response
                ]);
            }

            $dto = method_exists($response, 'dto') ? $response->dto() : null;

            // Handle Amazon Rejection
            if ($dto && isset($dto->status) && strtoupper($dto->status) === 'INVALID') {
                $issues = json_decode(json_encode($dto->issues ?? []), true);
                $normalizer = app(AmazonIssueNormalizer::class);
                $errors = $normalizer->normalize($issues);

                Log::warning('AMAZON LISTING INVALID', [
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'issues' => $issues,
                    'errors' => $errors
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Amazon rejected listing',
                    'issues' => $issues,
                    'errors' => $errors
                ], 422);
            }

            // Save Smart Payload for future mapping
            AmazonProduct::updateOrCreate(
                ['product_id' => $product->id],
                ['smart_payload' => json_encode($request->payload, JSON_UNESCAPED_SLASHES)]
            );

            // Mark Synced
            $updated = Product::where('id', $product->id)
                ->update([
                    'synced_to_amazon' => 1
                ]);

            Log::info('SYNC UPDATE DEBUG', [
                'product_id'   => $product->id,
                'updated_rows' => $updated,
            ]);

            $freshProduct = Product::find($product->id);

            Log::info('SYNC UPDATE VERIFY', [
                'product_id'       => $product->id,
                'synced_to_amazon' => $freshProduct?->synced_to_amazon,
            ]);
            ProductSyncLog::create([
                'product_id'    => $product->id,
                'shop_id'       => $shop->id,
                'platform'      => 'amazon',
                'status'        => 'success',
                'message'       => 'Amazon listing accepted',
                'type'          => 'manual_sync',
            ]);

            unset($payload['merchant_shipping_group']);

            return response()->json([
                'success' => true,
                'message' => 'Product synced successfully',
                'amazon_response' => $dto
            ]);
        } catch (\Throwable $e) {
            Log::error('MANUAL AMAZON SYNC FAILED', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dynamically routes payload manipulation based on product type.
     */
    private function applyProductTypeDefaults(string $productType, array $payload): array
    {
        return match ($productType) {
            'HEADPHONES' => $this->prepareHeadphonesPayload($payload),
            'SHIRT'      => $this->prepareShirtPayload($payload),
            // Add future types here (e.g., 'PANTS' => $this->preparePantsPayload($payload))
            default      => $payload,
        };
    }

    /**
     * Safely applies Headphone-specific default requirements.
     */
    private function prepareHeadphonesPayload(array $payload): array
    {
        $payload['connectivity_technology'] ??= [['value' => 'wireless']];
        $payload['headphones_form_factor'] ??= [['value' => 'in_ear']];
        return $payload;
    }

    /**
     * Safely applies Shirt-specific default requirements.
     */
    private function prepareShirtPayload(array $payload): array
    {
        // Only append marketplace_id if the user actually mapped a shirt size
        if (isset($payload['shirt_size'][0]) && !isset($payload['shirt_size'][0]['marketplace_id'])) {
            $payload['shirt_size'][0]['marketplace_id'] = 'ATVPDKIKX0DER';
        }
        return $payload;
    }

    /**
     * Applies generic fallback requirements across all categories safely.
     */
    private function applyGlobalDefaults(array $payload): array
    {
        $payload['supplier_declared_has_product_identifier_exemption'] ??= [[
            'value' => true,
            'marketplace_id' => 'ATVPDKIKX0DER'
        ]];

        $payload['item_package_weight'] ??= [[
            'value' => 0.3,
            'unit' => 'kilograms'
        ]];

        $payload['number_of_items'] ??= [['value' => 1]];

        if (isset($payload['list_price'][0]) && !isset($payload['list_price'][0]['currency'])) {
            $payload['list_price'][0]['currency'] = 'USD';
        }

        return $payload;
    }

    /**
     * Resolves Amazon SP-API conflict between 'is_inventory_available' and 'quantity'
     */
    private function sanitizeFulfillmentAvailability(array $payload): array
    {
        if (isset($payload['fulfillment_availability'][0])) {
            $fa = $payload['fulfillment_availability'][0];

            // SP-API Rule: You cannot provide BOTH quantity and a boolean inventory flag
            if (isset($fa['is_inventory_available']) && filter_var($fa['is_inventory_available'], FILTER_VALIDATE_BOOLEAN)) {
                unset($payload['fulfillment_availability'][0]['quantity']);
            } else {
                unset($payload['fulfillment_availability'][0]['is_inventory_available']);
            }
        }
        return $payload;
    }

    /**
     * Safely type-casts string booleans ('true' / 'false') to native booleans for Amazon arrays
     */
    private function castBooleanFields(array $payload): array
    {
        $boolFields = [
            'batteries_required',
            'batteries_included',
            'includes_rechargable_battery',
            'has_multiple_battery_powered_components',
            'battery_contains_free_unabsorbed_liquid',
            'is_battery_non_spillable',
            'has_less_than_30_percent_state_of_charge',
        ];

        foreach ($boolFields as $field) {
            if (isset($payload[$field][0]['value'])) {
                $payload[$field][0]['value'] = filter_var($payload[$field][0]['value'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $payload;
    }

    public function loadMissingFields(Request $request)
    {
        $slug = $request->slug;
        $fields = $request->fields ?? [];

        Log::info('LOAD MISSING FIELDS START', [
            'slug' => $slug,
            'requested_fields' => $fields
        ]);

        $schema = AmazonSchema::where(
            'category_slug',
            $slug
        )->first();

        if (!$schema) {

            Log::warning('SCHEMA NOT FOUND', [
                'slug' => $slug
            ]);

            return response()->json([
                'success' => false
            ]);
        }

        $amazonService = new AmazonSchemaService();

        $schemaData = json_decode(
            $schema->schema_json,
            true
        );

        Log::info('SCHEMA LOADED', [
            'slug' => $slug,
            'schema_id' => $schema->id ?? null
        ]);

        $allFields = $amazonService
            ->extractFields($schemaData);

        Log::info('EXTRACTED FIELDS', [
            'total' => count($allFields),
            'keys' => collect($allFields)
                ->pluck('key')
                ->values()
                ->toArray()
        ]);

        $missing = collect($allFields)
            ->filter(function ($field) use ($fields) {

                foreach ($fields as $missingField) {

                    if (
                        $field['key'] === $missingField ||
                        str_starts_with(
                            $field['key'],
                            $missingField . '.'
                        )
                    ) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        Log::info('FILTER RESULT', [
            'requested' => $fields,
            'found_count' => $missing->count(),
            'found_keys' => $missing
                ->pluck('key')
                ->values()
                ->toArray()
        ]);

        if ($missing->isEmpty()) {

            Log::warning('NO MATCH FOUND', [
                'requested_fields' => $fields,
                'available_fields' => collect($allFields)
                    ->pluck('key')
                    ->values()
                    ->toArray()
            ]);
        }

        return response()->json([
            'success' => true,
            'fields' => $missing
        ]);
    }

    public function evaluateConditions(
        \Illuminate\Http\Request $request,
        \App\Services\Amazon\AmazonConditionalEvaluator $evaluator
    ): \Illuminate\Http\JsonResponse {
        $validated = $request->validate([
            'slug'    => 'required|string',
            'payload' => 'nullable|array',
        ]);

        $schema = \App\Models\AmazonSchema::where(
            'category_slug',
            $validated['slug']
        )->first();

        if (!$schema) {
            return response()->json([
                'success' => false,
                'message' => 'Schema not found',
            ], 404);
        }

        $rules = is_string($schema->rules_json)
            ? json_decode($schema->rules_json, true)
            : ($schema->rules_json ?? []);

        if (!is_array($rules)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid rules_json.',
            ], 422);
        }

        $payload = $validated['payload'] ?? [];

        $uiState = $evaluator->evaluate($rules, $payload);

        return response()->json([
            'success' => true,
            'state'   => $uiState,
        ]);
    }
}
