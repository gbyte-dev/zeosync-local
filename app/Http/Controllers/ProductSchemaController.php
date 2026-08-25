<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductSchema;
use Illuminate\Support\Facades\DB;
use App\Services\Amazon\SchemaParser;
use App\Models\AllProduct as Product;
use App\Models\ProductAttribute;
use Illuminate\Support\Str;
use App\Services\Amazon\SchemaRendererService;
use App\Http\Controllers\TestController;
use App\Models\Category;
use App\Models\Shop;
use App\Models\AllProduct;
use App\Models\ProductMarketplaceMapping;
use App\Models\ProductSyncLog;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\ShopifyController;
use App\Services\ProductLimitService;
use App\Models\ShopSubscription;
use App\Services\TransformsAmazonAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\AI\AIAutoFillService;
use App\Services\AIFeatureService;
use Illuminate\Support\Facades\Http;
use App\Models\AdminSetting;
use App\Services\AmazonSuccessfulListingService;

class ProductSchemaController extends Controller
{
    protected $shop;
    private readonly AIAutoFillService $aiAutoFillService;
    private readonly AIFeatureService $aiFeatureService;
    private AmazonSuccessfulListingService $amazonSuccessfulListingService;
    public function __construct()
    {
        $this->aiAutoFillService = app(AIAutoFillService::class);;
        $this->aiFeatureService = app(AIFeatureService::class);
        $this->amazonSuccessfulListingService = app(AmazonSuccessfulListingService::class);
        if (session('active_shop')) {
            $this->shop = Shop::where('shop', session('active_shop'))->first();
        } else {
            $this->shop = Shop::where('shop', '!=', '')->first();
        }
    }
    public function index()
    {
        $schemas = ProductSchema::latest()->paginate(20);
        return view('schema.index', compact('schemas'));
    }
    public function create()
    {
        return view('schema.create');
    }
    public function addProductCategory(Request $request)
    {
        $categories = ProductSchema::WithRequiredColumns()->where('is_active', 1)->get();
        if ($request->isMethod('post')) {
            $request->validate([
                'category_id' => 'required|exists:product_schemas,id',
            ]);
            $shop = $request->query('shop') ?? $request->input('shop');
            return redirect()->route('admin.product.store', [
                'schemaId' => $request->input('category_id'),
                'shop' => $shop,
            ]);
        }
        return view('schema.products.selectcategory', compact('categories'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'schema_file' => 'required|file|mimes:json'
        ]);
        $schemaJson = json_decode(
            file_get_contents(
                $request->file('schema_file')->path()
            ),
            true
        );
        if (!$schemaJson) {
            return back()->withErrors(['schema_file' => 'Invalid JSON']);
        }
        $productType = basename($schemaJson['$id'] ?? uniqid());
        $renderer =  new SchemaRendererService($schemaJson);
        $fields = $renderer->render();
        ProductSchema::updateOrCreate(
            [
                'product_type' => $productType
            ],
            [
                'schema_json' => $schemaJson,
                'parsed_json' => $fields,
                'schema_version' => '1.0'
            ]
        );
        $category = Category::where('slug', $productType)->first();
        $category->status = 'active';
        $category->save();
        return redirect()->route('admin.category')->with(
            'success',
            'Schema imported successfully.'
        );
    }
    public function productcreate(Request $request, $schemaId)
    {
        $schema = ProductSchema::findOrFail($schemaId);
        $fields = $schema->parsed_json;
        $suggestions = AllProduct::query()
            ->where('schema_id', $schema->id)
            ->where('status', 'ACCEPTED')
            ->whereNotNull('filled_json')
            ->latest('id')
            ->limit(3)
            ->get(['id', 'schema_id', 'filled_json']);

        $excludedSuggestionFields = [
            'merchant_suggested_asin',
            'externally_assigned_product_identifier',
            'sku',
            'seller_sku',
            'seller_id',
            'shop_id',
            'user_id',
            'access_token',
            'refresh_token',
            'api_key',
            'client_secret',
            'model_number',
            'manufacturer_part_number',
            'part_number',
        ];

        $fieldSuggestions = [];

        foreach ($suggestions as $suggestion) {
            $filledData = json_decode($suggestion->filled_json, true);

            if (!is_array($filledData)) {
                continue;
            }

            foreach ($filledData as $fieldName => $value) {
                if (in_array(strtolower($fieldName), $excludedSuggestionFields, true)) {
                    continue;
                }

                if ( $value === null || $value === '' ||  is_array($value)
                ) {
                    continue;
                }

                $fieldSuggestions[$fieldName][] = $value;
            }
        }

        foreach ($fieldSuggestions as $fieldName => $values) {
            $fieldSuggestions[$fieldName] = collect($values)
                ->unique()
                ->values()
                ->take(3)
                ->all();
        }
        $tabs = [
            'product' => [],
            'images' => [],
            'variations' => [],
            'attributes' => [],
            'product_rules' => [],
            'battery_specs' => [],
            'other' => [],
        ];
        foreach ($fields as $field) {
            $name = strtolower($field['name']);

            if (in_array($name, [
                'item_name',
                'brand',
                'product_description',
                'bullet_point',
                'item_type_keyword',
                'externally_assigned_product_identifier',
                'supplier_declared_has_product_identifier_exemption',
                'merchant_suggested_asin',
                'model_number',
                'part_number',
                'generic_keyword',
                'department',
                'target_gender',
                'age_range_description',
                'number_of_items',
                'item_package_quantity',
                'product_site_launch_date',
                'merchant_release_date',
                'title_differentiation',
            ])) {

                $tabs['product'][] = $field;
            } elseif (str_contains($name, 'image')) {

                $tabs['images'][] = $field;
            } elseif (
                str_contains($name, 'variation') ||
                str_contains($name, 'parent')
            ) {

                $tabs['variations'][] = $field;
            } elseif (in_array($name, [
                'color',
                'size',
                'material',
                'style',
                'pattern',
                'manufacturer',
                'model_name',
                'flavor',
                'item_weight',
                'item_package_dimensions',
                'item_package_weight',
                'item_display_weight',
            ])) {

                $tabs['attributes'][] = $field;
            } elseif (in_array($name, [
                'list_price',
                'merchant_shipping_group',
                'max_order_quantity',
                'gift_options',
                'condition_type',
                'condition_note',
                'product_tax_code',
                'fulfillment_availability',
                'purchasable_offer',
                'import_designation',
                'country_of_origin',
                'supplier_declared_dg_hz_regulation',
                'ghs',
                'hazmat',
                'safety_data_sheet_url',
                'is_this_product_subject_to_buyer_age_restrictions',
                'california_proposition_65',
                'pesticide_marking',
                'fcc_radio_frequency_emission_compliance',
                'regulatory_compliance_certification',
                'dsa_responsible_party_address',
                'compliance_media',
                'gpsr_safety_attestation',
                'gpsr_manufacturer_reference',
                'contains_pfas',
                'ships_globally',
                'ghs_chemical_h_code',
                'baa_taa_regulation_compliance',
                'baa_taa_compliance_acknowledgement',
                'taa_compliant_country',
            ])) {

                $tabs['product_rules'][] = $field;
            } elseif (in_array($name, [
                "batteries_required",
                "batteries_included",
                "battery",
                "num_batteries",
                "number_of_lithium_metal_cells",
                "number_of_lithium_ion_cells",
                "lithium_battery",
                "has_multiple_battery_powered_components",
                "contains_battery_or_cell",
                "battery_contains_free_unabsorbed_liquid",
                "is_battery_non_spillable",
                "non_lithium_battery_packaging",
                "has_replaceable_battery",
                "non_lithium_battery_energy_content",
                "has_less_than_30_percent_state_of_charge",
                "battery_installation_device_type"
            ])) {

                $tabs['battery_specs'][] = $field;
            } else {

                $tabs['other'][] = $field;
            }
        }
        $requiredFields = collect($fields)->where('required', true)->values();
        $canUseAiAutoFill = $this->aiFeatureService->canUseAutoFill($this->shop->id);
        $canUseAiSingleField = $this->aiFeatureService->canUseSingleField($this->shop->id);
        return view(
            'schema.products.create',
            compact(
                'tabs',
                'schema',
                'fields',
                'requiredFields',
                'canUseAiAutoFill',
                'canUseAiSingleField',
                'suggestions',
                'fieldSuggestions'
            )
        );
    }
    public function productEdit($productid)
    {
        $productshow = Product::where('id', $productid)->first();
        if (!$productshow) {
            $productshow = Product::where('sku', $productid)->first();
            if ($productshow) {
                $productid = $productshow->id;
            } else {
                return redirect()->route('user.product.showProducts');
            }
        }
        $prodAttri = ProductAttribute::where('product_id', $productid)->get();
        $amazonDbAutofill = session('amazon_db_autofill', []);
        $fieldSuggestions = [];

        foreach ($amazonDbAutofill as $fieldName => $value) {

            $attribute = $prodAttri->firstWhere('attribute_name', $fieldName);

            if ($attribute) {
                // Replace existing value because this field had an Amazon error.
                $attribute->attribute_value = $value;
            } else {
                // Field does not exist yet, add it only to the current view data.
                $prodAttri->push(
                    new ProductAttribute([
                        'product_id' => $productid,
                        'attribute_name' => $fieldName,
                        'attribute_value' => $value,
                    ])
                );
            }
        }
        $schema = ProductSchema::findOrFail($productshow->schema_id);
        $fields = $schema->parsed_json;
        $tabs = [
            'product' => [],
            'images' => [],
            'variations' => [],
            'attributes' => [],
            'product_rules' => [],
            'battery_specs' => [],
            'other' => [],
        ];
        foreach ($fields as $field) {

            $name = strtolower($field['name']);

            if (in_array($name, [
                'item_name',
                'brand',
                'product_description',
                'bullet_point',
                'item_type_keyword',
                'externally_assigned_product_identifier',
                'supplier_declared_has_product_identifier_exemption',
                'merchant_suggested_asin',
                'model_number',
                'part_number',
                'generic_keyword',
                'department',
                'target_gender',
                'age_range_description',
                'number_of_items',
                'item_package_quantity',
                'product_site_launch_date',
                'merchant_release_date',
                'title_differentiation',
            ])) {

                $tabs['product'][] = $field;
            } elseif (str_contains($name, 'image')) {

                $tabs['images'][] = $field;
            } elseif (str_contains($name, 'variation') || str_contains($name, 'parent')) {

                $tabs['variations'][] = $field;
            } elseif (in_array($name, [
                'color',
                'size',
                'material',
                'style',
                'pattern',
                'manufacturer',
                'model_name',
                'flavor',
                'item_weight',
                'item_package_dimensions',
                'item_package_weight',
                'item_display_weight',
            ])) {

                $tabs['attributes'][] = $field;
            } elseif (in_array($name, [
                'list_price',
                'merchant_shipping_group',
                'max_order_quantity',
                'gift_options',
                'condition_type',
                'condition_note',
                'product_tax_code',
                'fulfillment_availability',
                'purchasable_offer',
                'import_designation',
                'country_of_origin',
                'supplier_declared_dg_hz_regulation',
                'ghs',
                'hazmat',
                'safety_data_sheet_url',
                'is_this_product_subject_to_buyer_age_restrictions',
                'california_proposition_65',
                'pesticide_marking',
                'fcc_radio_frequency_emission_compliance',
                'regulatory_compliance_certification',
                'dsa_responsible_party_address',
                'compliance_media',
                'gpsr_safety_attestation',
                'gpsr_manufacturer_reference',
                'contains_pfas',
                'ships_globally',
                'ghs_chemical_h_code',
                'baa_taa_regulation_compliance',
                'baa_taa_compliance_acknowledgement',
                'taa_compliant_country',
            ])) {

                $tabs['product_rules'][] = $field;
            } elseif (in_array($name, [
                "batteries_required",
                "batteries_included",
                "battery",
                "num_batteries",
                "number_of_lithium_metal_cells",
                "number_of_lithium_ion_cells",
                "lithium_battery",
                "has_multiple_battery_powered_components",
                "contains_battery_or_cell",
                "battery_contains_free_unabsorbed_liquid",
                "is_battery_non_spillable",
                "non_lithium_battery_packaging",
                "has_replaceable_battery",
                "non_lithium_battery_energy_content",
                "has_less_than_30_percent_state_of_charge",
                "battery_installation_device_type"
            ])) {

                $tabs['battery_specs'][] = $field;
            } else {

                $tabs['other'][] = $field;
            }
        }

        $tabErrorFields = array_fill_keys(array_keys($tabs), []);

        $amazonErrors = session('errors_amazon', []);

        $fieldAlias = [
            'externally_assigned_product_identifier' => [
                'external_product_id',
                'external_product_identifier',
            ],

            'material' => [
                'fabric_type',
            ],

            'apparel_size_class' => [
                'apparel_size',
            ],
        ];

        if (is_array($amazonErrors)) {

            foreach ($amazonErrors as $error) {

                if (!is_array($error)) {
                    continue;
                }

                // Only real Amazon validation errors.
                // Warnings should not appear in the red error badge.
                if (($error['severity'] ?? '') !== 'ERROR') {
                    continue;
                }

                $path = strtolower($error['path'] ?? '');

                $attributeNames = array_map(
                    'strtolower',
                    $error['attributeNames'] ?? []
                );

                foreach ($tabs as $tabName => $tabFields) {

                    foreach ($tabFields as $field) {

                        $fieldName = strtolower($field['name'] ?? '');
                        if (!$fieldName) {
                            continue;
                        }

                        $aliases = $fieldAlias[$fieldName] ?? [];
                        $matched = false;

                        foreach ($attributeNames as $attribute) {

                            if ( $attribute === $fieldName || in_array($attribute, $aliases, true)
                            ) {
                                $matched = true;
                                break;
                            }
                        }

                        if ( $matched || $path === $fieldName ) {
                            $tabErrorFields[$tabName][$fieldName] = true;
                            break 2;
                        }
                    }
                }
            }
        }

        $tabErrorCounts = [];

        foreach ($tabErrorFields as $tabName => $fieldsWithErrors) {
            $tabErrorCounts[$tabName] = count($fieldsWithErrors);
        }
        $requiredFields = collect($fields)->where('required', true)->values();
        $canUseAiAutoFill = $this->aiFeatureService->canUseAutoFill($this->shop->id);
        $canUseAiSingleField = $this->aiFeatureService->canUseSingleField($this->shop->id);

        Log::info('AMAZON ERRORS DEBUG', [
            'errors' => session('errors_amazon'),
        ]);

        return view(
            'schema.products.create',
            compact( 'tabs', 'schema', 'fields',  'requiredFields', 'productshow',
                'prodAttri', 'canUseAiAutoFill', 'canUseAiSingleField',
                'tabErrorCounts', 'fieldSuggestions'  )
            );
    }

    public function productstore( Request $request,  ProductLimitService $productLimitService,
        $product_id = null ) {
        $activeShop = $request->attributes->get('active_shop_model');
        if (!$activeShop) {
            return back()->with('error', 'Active shop not found.');
        }
        $shop_id = $activeShop->id;
        $shopModel = Shop::where('id', $shop_id)->first();
        $this->ensureFreshAccessToken($shopModel);
        // Check product limit only for new product creation
        if (!isset($product_id)) {
            $limitStatus = $productLimitService->canCreateProduct($shop_id);
            if (!$limitStatus['allowed']) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'product_limit' => $limitStatus['message']
                    ]);
            }
        }
        $product_id = DB::transaction(function () use ($request, $product_id, $shop_id) {
            if (!isset($product_id)) {
                if ($request->parent_id) {
                    $product = Product::create([
                        'parent_id' => $request->parent_id,
                        'user_id' => $shop_id,
                        'schema_id' => $request->schema_id,
                        'sku' => strtoupper(Str::random(12))
                    ]);
                } else {
                    $product = Product::create([
                        'user_id' => $shop_id,
                        'schema_id' => $request->schema_id,
                        'sku' => strtoupper(Str::random(12))
                    ]);
                }
                $product_id = $product->id;
            }
            foreach ($request['attributes'] as $key => $value) {
                if (($key == 'country_of_origin') && $value == '') {
                    $value = 'US';
                }

                if (is_array($value)) {
                    $value = array_values(array_filter(array_map(function ($item) {
                        if (is_array($item)) {
                            return json_encode($item);
                        }

                        if (is_string($item)) {
                            $trimmed = trim($item);
                            return $trimmed === '' ? null : $trimmed;
                        }

                        return $item;
                    }, $value), fn($item) => $item !== null && $item !== ''));
                    $value = $value === [] ? '' : json_encode($value);
                }

                // if ($value === null || $value === 'null' || $value === '') {
                //     continue;
                // }
                ProductAttribute::updateOrCreate(
                    [
                        'product_id' => $product_id,
                        'attribute_name' => $key,
                    ],
                    [
                        'attribute_value' => $value,
                    ],
                );
            }
            return $product_id;
        });
        if ($request->save_draft) {
            return redirect()->route('admin.product.productEdit', [
                'product' => $product_id,
                'shop'    => $activeShop->shop,
            ])->with('success', 'Product Saved as Draft');
        }
        return redirect()->route('admin.product.generatePayload', [
            'product' => $product_id,
            'shop'    => $activeShop->shop,
        ]);
    }
    public function generatePayload(Product $product, $type = 'main', $fields = []): array
    {
        $attributes = [];
        $requireddata = [];
        $transformer = new TransformsAmazonAttributes();
        $lensData = [];
        if (!empty($fields)) {
            $requiredFields = collect($fields)->where('required', true)->values();
            foreach ($requiredFields as $requiredField) {
                array_push($requireddata, $requiredField['name']);
            }
        }
        foreach ($product->attributes as $attribute) {
            $name  = $attribute->attribute_name;
            $value = $attribute->attribute_value;

            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '' && str_starts_with($value, '[')) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $value = $decoded;
                    }
                }

                if(is_string($value)) {
                    $value = str_replace('"', '', $value);
                    $value = trim($value);
                }
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '' && str_starts_with($trimmed, '[')) {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $value = $decoded;
                    }
                }
                $value = str_replace('"', '', $value);
                $value = trim($value);
            }

            // Skip null / literal "null" entirely — never send empty attributes
            if ($value === null || $value === 'null' || $value === '') {
                continue;
            }
            $notreq = [
                'is_refurbished',
                'packed_with_equipment',
                // 'water_resistance_level',
                'package_level'
            ];
            if (in_array($name, $notreq)) {
                continue;
            }
            if (in_array($name, ['lens_color', 'lens_width', 'lens_material'], true)) {
                $lensData[$name] = $value;
                continue;
            }
            $canonicalName = match ($name) {
                'color_name', 'colour' => 'color',
                default => $name,
            };

            if (in_array($canonicalName, ['seat_depth', 'seat_width', 'seat_height'], true)) {
                $seatPart = $transformer->transformAttribute($canonicalName, $value,$product->attributes);

                if ($seatPart !== null) {
                    $attributes['seat'][0] = array_merge(
                        $attributes['seat'][0] ?? [],
                        $seatPart
                    );
                }

                continue;
            }


            $transformed = $transformer->transformAttribute($canonicalName, $value,$product->attributes);
            if ($transformed === null) {
                continue;
            }
            $attributes[$canonicalName] = $transformed;
        }
        if (!empty($lensData)) {
            $lensTransformed = $transformer->transformAttribute('lens', $lensData,$product->attributes);
            if ($lensTransformed !== null) {
                $attributes['lens'] = $lensTransformed;
            }
        }

        return [
            'requirements' => 'LISTING',
            'attributes'   => $attributes,
        ];
    }
    public function aiAutoFill(Request $request)
    {
        $request->validate([
            'product_name'        => ['required', 'string'],
            'product_description' => ['nullable', 'string'],
            'category'            => ['required', 'string'],
        ]);
        $activeShop = Shop::where('shop', $request->input('shop'))
            ->where('is_active', 1)
            ->first();
        if (!$activeShop) {
            return response()->json([
                'success' => false,
                'message' => 'Active shop not found.',
            ], 404);
        }
        if (!$this->aiFeatureService->canUseAutoFill($activeShop->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Your current plan does not support AI AutoFill.',
            ], 403);
        }
        try {
            $result = $this->aiAutoFillService->generateGenericListing(
                productName: $request->product_name,
                productDescription: $request->product_description,
                category: $request->category,
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('AI AutoFill Failed', [
                'message'     => $e->getMessage(),
                'product'     => $request->product_name,
                'category'    => $request->category,
                'description' => $request->product_description,
            ]);
            return response()->json([
                'success' => false,
                'errors'  => ['Failed to generate AI listing.'],
                'data'    => [],
            ], 500);
        }
    }
    public function buildListingRequest(Product $product)
    {
        try {
            if ($product->parent_id) {
                $all = $this->addChildListing($product);
                $payload = $all['payload'];
                $payload2 = $all['payload2'];
                $payload3 = $all['payload3'];
                $prodAttributes['variants']['sku'] = $all['sku'];
            } else {
                $schema = ProductSchema::findOrFail($product->schema_id);
                $fields = $schema->parsed_json;
                $payload = $this->generatePayload($product, 'main', $fields);
                $testcontroller = new TestController();
                $sku = $product->sku;
                $payload2 = $testcontroller->createOnlyListing($payload['attributes'], $schema->product_type ?? 'KEYBOARDS');
                $payload3 = $testcontroller->createOnlyputListing($payload2, $sku);
            }
            if (isset($payload3['status']) && ($payload3['status'] == 'INVALID')) {

                $successfulListing = $this->amazonSuccessfulListingService->findFor($product);

                $failedFields = collect($payload3['issues'] ?? [])
                    ->pluck('attributeNames')
                    ->flatten()
                    ->unique()
                    ->values()
                    ->all();

                $filledData = json_decode(
                    $successfulListing?->filled_json ?? '{}',
                    true
                );

                $matchedData = [];

                foreach ($failedFields as $field) {
                    if (
                        array_key_exists($field, $filledData) &&
                        $filledData[$field] !== null &&
                        $filledData[$field] !== ''
                    ) {
                        $matchedData[$field] = $filledData[$field];
                    }
                }

                Log::info('AMAZON ERROR FIELD AUTOFILL DATA', [
                    'product_id' => $product->id,
                    'successful_listing_id' => $successfulListing?->id,
                    'failed_fields' => $failedFields,
                    'matched_data' => $matchedData,
                ]);

                // if (!$successfulListing) {

                if (false && !$successfulListing) {

                    Log::info('AMAZON ERROR AI AUTOFILL FALLBACK START', [
                        'product_id' => $product->id,
                        'failed_fields' => $failedFields,
                    ]);

                    $productName = $product->attributes
                        ->firstWhere('attribute_name', 'item_name')
                        ?->attribute_value ?? '';

                    $productDescription = $product->attributes
                        ->firstWhere('attribute_name', 'product_description')
                        ?->attribute_value ?? '';

                    $schema = ProductSchema::findOrFail($product->schema_id);

                    $aiResult = $this->aiAutoFillService->generateErrorAutoFill(
                        productName: $productName,
                        productDescription: $productDescription,
                        category: $schema->product_type ?? 'UNKNOWN',
                        errors: $payload3['issues'] ?? []
                    );

                    Log::info('AMAZON ERROR AI AUTOFILL FALLBACK RESULT', [
                        'product_id' => $product->id,
                        'success' => $aiResult['success'] ?? false,
                        'failed_fields' => $failedFields,
                        'ai_fields' => array_keys($aiResult['data'] ?? []),
                        'usage' => $aiResult['usage'] ?? null,
                    ]);

                    if (($aiResult['success'] ?? false) === true) {
                        foreach ($failedFields as $field) {
                            if (
                                array_key_exists($field, $aiResult['data']) &&
                                $aiResult['data'][$field] !== null &&
                                $aiResult['data'][$field] !== ''
                            ) {
                                $matchedData[$field] = $aiResult['data'][$field];
                            }
                        }
                    }
                }
                $this->updatelog(
                    $product->id,
                    'amazon',
                    'sync_failed',
                    false,
                    is_array($payload3['issues'] ?? null)
                        ? json_encode($payload3['issues'])
                        : ($payload3['issues'] ?? 'Amazon listing validation failed.')
                );

                return redirect()->route('admin.product.productEdit', [
                    'product' => $product->id,
                    'shop' => request('shop'),
                ])
                    ->with('errors_amazon', $payload3['issues'])
                    ->with('amazon_db_autofill', $matchedData);
            }
            $generatejson = $this->generatejson($product->id);
            $prodAttributes['sku'] = $product->sku;
            $this->updateSyncAmazon($product->id, $prodAttributes);
            $product->status = $payload3['status'];
            $product->submission_status = $payload3['submissionId'];
            $product->submitted_on = now();
            $product->final_json = json_encode($payload);
            $product->filled_json = json_encode($generatejson);
            $product->save();
            ProductAttribute::where('product_id', $product->id)->delete();
            $this->updatelog($product->id, 'amazon', 'added', false);
            return redirect()->route('user.product.showProducts')->with('success', 'Product added Successfully');
            return response()->json([
                'success' => true,
                'message' => 'Product added successfully',
                'sku' => $sku,
                'response' => $payload3
            ]);
        } catch (\Exception $e) {
            $this->updatelog($product->id, 'amazon', 'sync_failed', false, $e->getMessage());
            $strmessage = str_replace('Bad Request (400) Response:', '', $e->getMessage());
            $strmessage = json_decode($strmessage, true);
            if (is_array($strmessage) && isset($strmessage['errors'])) {
                $strmessage = $strmessage['errors'];
            }
            return redirect()->route('admin.product.productEdit', [
                'product' => $product->id,
                'shop' => request('shop'),
            ])->with('errors_amazon', $strmessage);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function addChildListing(Product $product)
    {
        $parentproduct = Product::where('id', $product->parent_id)->first();
        $parentSku =  $parentproduct->sku;
        $schema = ProductSchema::findOrFail($product->schema_id);
        $fields = $schema->parsed_json;
        $attributes = $this->generatePayload($product, 'child')['attributes'];
        $testcontroller = new TestController();
        // Override for child
        $attributes['parentage_level']            = [['value' => 'child']];
        $attributes['child_parent_sku_relationship'] = [[
            'child_relationship_type' => 'variation',
            'parent_sku'              => $parentSku,
        ]];
        $newsku =  $product->sku;
        $payload2 = $testcontroller->createOnlyListing($attributes, $schema->product_type ?? 'KEYBOARDS');
        $payload3 = $testcontroller->createOnlyputListing($payload2, $newsku);
        return ['payload' => $attributes, 'payload2' => $payload2, 'payload3' => $payload3, 'sku' => $newsku ?? ''];
    }

    public function downloadScema($category = 'SHIRTS')
    {
        $testcontroller = new TestController();
        $scema = $testcontroller->getDownloadSchema($category);
        return response($scema)
            ->header('Content-Type', 'application/json')
            ->header(
                'Content-Disposition',
                'attachment; filename="' . strtolower($category) . '_schema.json"'
            );
    }
    public function generatejson(int $productId): array
    {
        $attributes = ProductAttribute::where('product_id', $productId)
            ->orderBy('id')->get();
        $json = [];
        foreach ($attributes as $attribute) {
            $key = trim($attribute->attribute_name);
            $value = $attribute->attribute_value;
            if ($value === null || $value === '') {
                continue;
            }
            if (isset($json[$key])) {
                if (!is_array($json[$key])) {
                    $json[$key] = [$json[$key]];
                }
                $json[$key][] = $value;
            } else {
                $json[$key] = $value;
            }
        }
        return $json;
    }
    public function showProducts($product_id = null)
    {
        $shop = new Shop();
        // $shopModel = $shop->where([ 'shop' => session('active_shop') ])->first();
        // $shopSubscription = ShopSubscription::with('plan')
        //     ->where('shop', $shopModel->id)
        //     ->where('status', 'active')
        //     ->first();
        $productLimitReached = false;
        $productLimit = 0;
        $productUsed = 0;
        // if ($shopSubscription && $shopSubscription->plan) {
        //     $productLimit = $shopSubscription->plan->product_limit;
        //     $productUsed = Product::where('shop_id', $shopModel->id)
        //         ->whereBetween('created_at', [
        //             $shopSubscription->activated_at,
        //             $shopSubscription->current_period_end,
        //         ])
        //         ->count();
        //     $productLimitReached = $productUsed >= $productLimit;
        // }
        if (!$product_id) {
            $shop_id = $shop->getidByshop(session('active_shop'));
            // $products = Product::with('attributes', 'schema')->where('user_id', $shop_id)->where(['submission_status' => '!= null' ])->whereNull('parent_id')->paginate(10);
            $products = Product::with('attributes', 'schema')
                ->where('user_id', $shop_id)
                ->whereNull('parent_id')
                ->where(function ($query) {
                    $query->whereNotNull('submission_status')
                        ->orWhere('status', 'draft');
                })
                ->get();
            $parent_productid = '';
        } else {
            $shop_id = $shop->getidByshop(session('active_shop'));
            $products = Product::with('attributes', 'schema')->where('user_id', $shop_id)->where('parent_id', $product_id)->get();
            $parent_productid = $product_id;
        }
        return view('schema.products.index', compact('products', 'parent_productid', 'productLimitReached'));
    }
    public function generateField(Request $request)
    {
        $request->validate([
            'product_name'      => ['required', 'string'],
            'category'          => ['required', 'string'],
            'field'             => ['required', 'string'],
            'field_description' => ['nullable', 'string'],
            'field_hint'        => ['nullable', 'string'],
        ]);
        $activeShop = Shop::where('shop', $request->input('shop'))
            ->where('is_active', 1)
            ->first();
        if (!$activeShop) {
            return response()->json([
                'success' => false,
                'message' => 'Active shop not found.',
            ], 404);
        }
        if (!$this->aiFeatureService->canUseSingleField($activeShop->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Your current plan does not support AI Field Generation.',
            ], 403);
        }
        try {
            $result = $this->aiAutoFillService->generateSingleField(
                productName: $request->product_name,
                category: $request->category,
                field: $request->field,
                fieldDescription: $request->field_description,
                fieldHint: $request->field_hint,
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::error('AI Field Generation Failed', [
                'message' => $e->getMessage(),
                'field'   => $request->field,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate field.',
            ], 500);
        }
    }
    public function removeDrafts(Product $product)
    {
        ProductAttribute::where('product_id', $product->id)->delete();
        $product->delete();
        return redirect()->route('user.product.showProducts')->with('success', 'Product deleted successfully.');
    }
    private function getShopIdFromSession(): ?int
    {
        $shop = new Shop();
        $shopid =  $shop->getidByshop(session('active_shop'));
        if (!$shopid) {
            return redirect()->route('crm.entry')->with('error', 'Please select a shop first.');
        }
        return $shopid;
    }
    public function SyncAmazonProductToShopify(Request $request, $sku)
    {
        $shopId = $this->getShopIdFromSession();
        if (!$shopId) {
            return redirect()->route('crm.entry')->with('error', 'Please select a shop first.');
        }
        $activeShop = $request->shop ?? session('active_shop');
        $shopModel = Shop::where('shop', $activeShop)->first();
        $this->ensureFreshAccessToken($shopModel);
        $testcontroller = new TestController();
        $productdata =  $testcontroller->getProductVariants($sku);
        if (!$productdata) {
            return redirect()->route('user.product.showProducts')->with('error', 'Product not found.');
        }
        $product = Product::with('attributes', 'schema')->where('sku', $sku)
            ->where('user_id', $shopId)->first();

        if (!$product) {
            $product = $this->addProductToDbNotExists($productdata);
        }
        $childSkus = $productdata['relationships'][0]['relationships'][0]['childSkus'] ?? [];
        $product = $this->map($productdata['attributes'], $sku, $childSkus);
        $category = $productdata['productTypes'][0]['productType'] ?? null;
        $syncid = $this->syncProduct($product, $shopId, $category);
        if (!$product) {
            return redirect()->route('user.product.showProducts')->with('error', 'Product not found.');
        }
        return view('shopifySchema.create', compact('product', 'activeShop', 'syncid'));
    }

    public function addProductToDbNotExists($productdata)
    {
        $shopId = $this->getShopIdFromSession();
        $data['final_json'] = $prductattributes = json_encode($productdata['attributes'] ?? []);
        $data['submitted_on'] = now();
        $data['sku'] = $sku = $productdata['sku'] ?? null;
        $data['user_id'] = $shopId;
        $data['producttype'] = $producttypes = $productdata['productTypes'][0]['productType'] ?? null;
        $scemaid = ProductSchema::where('product_type', $producttypes)->first();
        if ($scemaid) {
            $data['schema_id'] = $scemaid->id;
        }
        $data['status'] = $productdata['status'] ?? 'ACCEPTED';
        $productmain = Product::updateOrCreate(
            ['sku' => $sku, 'user_id' => $shopId],
            $data
        );
        return $productmain;
    }
    public function map(array $attributes, string $sku = '', array $childSkus = []): array
    {
        return [
            'title' => self::value($attributes, 'item_name'),
            'body' => self::value($attributes, 'product_description'),
            'sku' => $sku,
            'price' => self::price($attributes),
            'status' => 'active',
            'vendor' => self::value($attributes, 'brand'),
            'product_type' => self::value($attributes, 'item_type_keyword'),
            'tags' => self::tags($attributes),
            'collections' => '',
            'images' => self::images($attributes),
            'options' => self::variants($attributes),
            'variants' => self::shopifyVariants($attributes, $childSkus),
            'metafields' => self::metafields($attributes),
            'amazon' => [
                'variation_theme' => $attributes['variation_theme'][0]['name'] ?? null,
                'parentage' => self::value($attributes, 'parentage_level'),
                'merchant_suggested_asin' => self::value($attributes, 'merchant_suggested_asin'),
                'marketplace_id' => $attributes['item_name'][0]['marketplace_id'] ?? null,
                'product_type' => self::value($attributes, 'item_type_keyword'),
                'brand' => self::value($attributes, 'brand'),
                'manufacturer' => self::value($attributes, 'manufacturer'),
                'model_name' => self::value($attributes, 'model_name'),
                'model_number' => self::value($attributes, 'model_number'),
                'country_of_origin' => self::value($attributes, 'country_of_origin'),
            ]
        ];
    }
    private static function shopifyVariants(array $attributes, array $childSkus): array
    {
        // No children → single variant
        if (empty($childSkus)) {
            $variant = [
                'sku' => self::value($attributes, 'merchant_sku') ?: '',
                'price' => self::price($attributes),
            ];
            foreach (self::variants($attributes) as $index => $option) {
                $variant['option' . ($index + 1)] = $option['values'][0] ?? null;
            }
            return [$variant];
        }
        $variants = [];
        foreach ($childSkus as $sku) {
            $variant = [
                'sku' => $sku,
                'price' => self::price($attributes),
            ];
            foreach (self::variants($attributes) as $index => $option) {
                $variant['option' . ($index + 1)] = $option['values'][0] ?? null;
            }
            $variants[] = $variant;
        }
        return $variants;
    }
    private static function value(array $attributes, string $key, string $field = 'value', $default = null)
    {
        return $attributes[$key][0][$field] ?? $default;
    }
    private static function price(array $attributes)
    {
        return
            $attributes['list_price'][0]['value']
            ?? $attributes['purchasable_offer'][0]['our_price'][0]['schedule'][0]['value_with_tax']
            ?? 0;
    }
    private static function tags(array $attributes): string
    {
        $tags = [];
        foreach (
            [
                'generic_keyword',
                'style',
                'pattern',
                'material',
                'department',
                'target_gender',
                'occasion_type',
                'season',
                'sport_type'
            ] as $field
        ) {
            if (!isset($attributes[$field][0])) {
                continue;
            }
            $value = $attributes[$field][0]['value'] ?? null;
            if (!$value) {
                continue;
            }
            if (is_array($value)) {
                $tags = array_merge($tags, $value);
            } else {
                $tags = array_merge($tags, array_map('trim', explode(',', $value)));
            }
        }
        return implode(', ', array_unique($tags));
    }
    private static function images(array $attributes): array
    {
        $images = [];
        foreach ($attributes as $key => $rows) {
            if (empty($rows[0]['media_location'])) {
                continue;
            }
            $src = $rows[0]['media_location'];
            $images[$src] = [
                'id' => null,
                'src' => $src
            ];
        }
        return array_values($images);
    }
    private static function variants(array $attributes): array
    {
        $map = [
            'color' => 'Color',
            'size_name' => 'Size',
            'apparel_size' => 'Size',
            'style' => 'Style',
            'material' => 'Material',
            'pattern' => 'Pattern',
            'flavor' => 'Flavor',
            'capacity' => 'Capacity',
            'pack_size' => 'Pack Size',
            'finish_type' => 'Finish',
            'scent' => 'Scent',
            'team_name' => 'Team'
        ];
        $options = [];
        foreach ($map as $amazonField => $shopifyName) {
            if (!isset($attributes[$amazonField][0])) {
                continue;
            }
            $row = $attributes[$amazonField][0];
            $values = [];
            if ($amazonField == 'apparel_size') {
                if (!empty($row['size'])) {
                    $values[] = $row['size'];
                }
                if (!empty($row['size_to'])) {
                    $values[] = $row['size_to'];
                }
            } else {
                if (!empty($row['value'])) {
                    if (is_array($row['value'])) {
                        $values = $row['value'];
                    } else {
                        $values[] = $row['value'];
                    }
                }
            }
            $values = array_unique(array_filter($values));
            if (!$values) {
                continue;
            }
            $options[] = [
                'name' => $shopifyName,
                'values' => array_values($values)
            ];
        }
        return $options;
    }
    private static function metafields(array $attributes): array
    {
        $skip = [
            'item_name',
            'product_description',
            'brand',
            'item_type_keyword',
            'generic_keyword',
            'list_price',
            'purchasable_offer',
            'variation_theme',
            'parentage_level',
            'merchant_suggested_asin',
            'main_product_image_locator',
            'other_product_image_locator_1',
            'other_product_image_locator_2',
            'other_product_image_locator_3',
            'other_product_image_locator_4',
            'other_product_image_locator_5',
            'other_product_image_locator_6',
            'other_product_image_locator_7',
            'other_product_image_locator_8',
            'fulfillment_availability',
            'color',
            'size_name'
        ];
        $meta = [];
        foreach ($attributes as $field => $rows) {
            if (in_array($field, $skip) || empty($rows[0])) {
                continue;
            }
            $value = self::flatten($rows[0]);
            $meta[$field] = is_array($value)
                ? ''  : (string) $value;
            //json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        }
        return $meta;
    }
    private static function flatten($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (isset($value['value'])) {
            return self::flatten($value['value']);
        }
        unset(
            $value['marketplace_id'],
            $value['language_tag']
        );
        foreach ($value as $k => $v) {
            $value[$k] = self::flatten($v);
        }
        return $value;
    }
    public function syncProduct($prodAttributes, $shop_id, $category = '')
    {
        $amzsku = $prodAttributes['variants'][0]['sku'] ?? $prodAttributes['sku'];
        $productmap = ProductMarketplaceMapping::updateOrCreate(
            [
                'shop_id' => $shop_id,
                'amazon_sku' => ($amzsku == '') ? $prodAttributes['sku'] : $amzsku,
                'amazon_parent_sku' => $prodAttributes['sku']
            ],
            [
                'amazon_product_type' => $category ?? '',
                'submission_id' => $prodAttributes['submission_id'] ?? null,
                'sync_status' => 'pending',
                'sync_message' => $prodAttributes['message'] ?? '',
            ]
        );
        return $productmap->id;
    }
    /**     
     * condition when product is on amzon and syncing to shopify
     */
    public function updateSyncShopify($syncid, $shopifyres)
    {
        $shopId = $this->getShopIdFromSession();
        $data = [];
        if (isset($shopifyres['product']['variants'])) {
            $data['shopify_variant_id'] = (string) $shopifyres['product']['variants'][0]['id'];
            $data['shopify_product_id'] = (string) $shopifyres['product']['variants'][0]['product_id'];
            $data['variant_id'] = (string) $shopifyres['product']['variants'][0]['id'];
            $data['shopify_inventory_item_id'] = (string) $shopifyres['product']['variants'][0]['inventory_item_id'];
            $data['sync_status'] = 'active';
        } else {
            $data['shopify_product_id'] = (string) $shopifyres['product']['id'];
            $data['shopify_variant_id'] = (string) $shopifyres['product']['id'];
            $data['shopify_inventory_item_id'] = (string) ($shopifyres['product']['inventory_item_id'] ?? '');
            $data['sync_status'] = 'active';
        }
        $updated = ProductMarketplaceMapping::where('shop_id', $shopId)
            ->where('id', $syncid)
            ->update($data);
        if (!$updated) {
            $this->updatelog(
                $data['shopify_product_id'],
                'shopify',
                'sync_failed',
                false,
                "No marketplace mapping row matched shop_id and id={$syncid}; nothing was updated."
            );
            return true;
        }
        $this->updatelog($data['shopify_product_id'], 'shopify', 'sync', false);
        return true;
    }
    public function syncProductShopify($prodAttributes, $shop_id, $product_id, $producttype)
    {
        $productmap = ProductMarketplaceMapping::updateOrCreate(
            [
                'shop_id' => $shop_id,
                'shopify_variant_id' => (string)$prodAttributes['shopify_variant_id'] ?? $prodAttributes['shopify_product_id'],
                'shopify_product_id' => (string)$prodAttributes['shopify_product_id']
            ],
            [
                'product_id' => $product_id ?? '',
                'amazon_product_type' => $producttype,
                'shopify_inventory_item_id' => (string)$prodAttributes['shopify_inventory_item_id'] ?? null,
                'sync_status' => 'pending'
            ]
        );
        return $productmap->id;
    }
    public function updateSyncAmazon($productid, $prodAttributes)
    {
        $productmappped = \App\Models\Product::where('amazon_product_id', $productid)->first();
        if (!$productmappped) {
            $this->updatelog($productid, 'amazon', 'sync_failed', false, 'No matching product found for amazon_product_id.');
            return;
        }
        $shopifyid = $productmappped->shopify_id;
        $shopId = $this->getShopIdFromSession();
        $data = [];
        if (isset($prodAttributes['variants'])) {
            $data['amazon_sku'] = $prodAttributes['variants']['sku'] ?? $prodAttributes['sku'];
            $data['amazon_parent_sku'] = $prodAttributes['sku'];
            $data['sync_status'] = 'active';
        } else {
            $data['amazon_sku'] = $prodAttributes['sku'];
            $data['amazon_parent_sku'] = $prodAttributes['sku'];
            $data['sync_status'] = 'active';
        }
        $updated = ProductMarketplaceMapping::where('shop_id', $shopId)
            ->where('shopify_product_id', $shopifyid)
            ->update($data);
        if (!$updated) {
            return;
        }
        $this->updatelog($productid, 'amazon', 'sync', false);
        return true;
    }
    public function productstoreAmazon(array $attributes,  $schema_id = null, $product_id = null, $parent_id = null)
    {
        return DB::transaction(function () use ($attributes, $product_id, $schema_id, $parent_id) {
            $shop = new Shop();
            $shop_id = $shop->getidByshop(session('active_shop'));
            // Create Product
            if (!$product_id) {
                $productData = [
                    'user_id'   => $shop_id,
                    'schema_id' => $schema_id,
                    'sku'       => !empty($attributes['sku'])
                        ? $attributes['sku']
                        : strtoupper(Str::random(12)),
                ];
                if (!empty($parent_id)) {
                    $productData['parent_id'] = $parent_id;
                }
                $product = Product::create($productData);
                $product_id = $product->id;
            }
            // Save Attributes
            foreach ($attributes as $key => $value) {
                if ($key == 'country_of_origin' && empty($value)) {
                    $value = 'US';
                }
                if (
                    $value === null ||
                    $value === '' ||
                    $value === 'null'
                ) {
                    continue;
                }
                // Convert arrays to JSON if any
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                ProductAttribute::updateOrCreate(
                    [
                        'product_id'     => $product_id,
                        'attribute_name' => $key,
                    ],
                    [
                        'attribute_value' => $value,
                    ]
                );
            }
            return $product_id;
        });
    }
    /**
     * Create a sync log entry and optionally remove the mapping record.
     *
     * Resolves a marketplace mapping for the given identifier based on the platform
     * inferred from $for (any value containing "amazon" is treated as Amazon, otherwise
     * Shopify). If $needremov is true and a mapping is found, it is deleted and the log
     * reflects the removal — this takes precedence over the message normally derived from
     * $type. If $needremov is true but no mapping is found, a 'no_action' status is logged
     * instead. If multiple mapping rows match the identifier, only the first is used/deleted,
     * and the log message notes that additional matches were found. If $type indicates a
     * failure ('failed' or 'sync_failed'), the log status is set to 'failed' and $error
     * (if provided) is appended to the message.
     *
     * No log entry is created if there is no active shop in the session; the method
     * returns false in that case instead of writing a row with a missing shop_id.
     *
     * @param int|string $product_id The Shopify variant ID, Shopify product ID, Amazon SKU, or Amazon parent SKU to resolve.
     * @param string $for The platform context. Any value containing "amazon" resolves to Amazon; anything else resolves to Shopify.
     * @param string $type The sync action type such as 'added', 'deleted', 'sync', 'sync_removed', 'failed', or 'sync_failed'.
     * @param bool $needremov Whether the matching marketplace mapping record should be deleted. When true and a mapping exists, the mapping is deleted and the log status is set to 'removed' regardless of $type.
     * @param string|null $error Optional error/failure reason, appended to the log message when $type indicates a failure.
     * @return bool True when the log entry is created successfully, false if there is no active shop in session and no log entry was written.
     */
    public function updatelog($product_id, $for, $type, $needremov = false, $error = null)
    {
        $shop = new Shop();
        $shop_id = $shop->getidByshop(session('active_shop'));
        if (!$shop_id) {
            // No active shop in session — abort rather than writing a bad log row.
            return false;
        }
        $platform = str_contains(strtolower((string) $for), 'amazon') ? 'amazon' : 'shopify';
        $normalizedType = strtolower((string) $type) ?: 'product';
        $mappingQuery = ProductMarketplaceMapping::query();
        $product_name = (string) $product_id;
        if ($platform === 'shopify') {
            $mappingQuery->where(function ($query) use ($product_id) {
                $query->where('shopify_variant_id', (string) $product_id)
                    ->orWhere('shopify_product_id', (string) $product_id);
            });
        } else {
            $mappingQuery->where(function ($query) use ($product_id) {
                $query->where('amazon_sku', (string) $product_id)
                    ->orWhere('amazon_parent_sku', (string) $product_id);
            });

            $product = ProductAttribute::where(['product_id' => (string) $product_id, 'attribute_name' => 'item_name'])->first();
            $product_name = $product ? $product->attribute_value : (string) $product_id;
        }
        // Use get() instead of first() so we don't silently ignore extra matches.
        $mappings = $mappingQuery->get();
        $mapping = $mappings->first();
        // Capture mapping details BEFORE deletion so the log reflects what existed,
        // and note explicitly that it's about to be/was removed.
        $mappingDetails = $mapping
            ? sprintf(
                'Mapped : shopify_variant_id=%s, shopify_product_id=%s, amazon_sku=%s, amazon_parent_sku=%s.',
                $mapping->shopify_variant_id ?? 'null',
                $mapping->shopify_product_id ?? 'null',
                $mapping->amazon_sku ?? 'null',
                $mapping->amazon_parent_sku ?? 'null'
            )
            : 'No matching marketplace mapping was found.';
        if ($mappings->count() > 1) {
            $mappingDetails .= sprintf(' Note: %d matching mapping rows found; only the first was used.', $mappings->count());
        }
        $didRemove = false;
        if ($needremov && $mapping) {
            $mapping->delete();
            $didRemove = true;
        }
        $status = 'success';
        $message = sprintf(
            'Product sync status updated successfully for %s with identifier %s.',
            $platform,
            $product_id
        );
        $isFailure = in_array($normalizedType, ['failed', 'sync_failed'], true);
        // If a removal actually happened, that takes precedence over $type-based
        // messaging — otherwise we'd log "added" while having just deleted the mapping.
        if ($didRemove) {
            $status = 'removed';
            $message = sprintf(
                'Mapping removed for %s product %s (requested via needremov). %s',
                $platform,
                $product_id,
                $mappingDetails
            );
        } elseif ($needremov && !$mapping) {
            // Removal was requested but there was nothing to remove.
            $status = 'no_action';
            $message = sprintf(
                'Removal requested for %s product %s, Error in product Sync.',
                $platform,
                $product_id
            );
        } elseif ($isFailure) {
            $status = 'failed';
            $message = sprintf(
                'Sync failed for %s product %s. %s%s',
                ucfirst($platform),
                $product_name,
                $mappingDetails,
                $error ?  '.' : ''
            );
        } else {
            switch ($normalizedType) {
                case 'added':
                    $message = sprintf(
                        'Product added to %s sync. Identifier: %s. %s',
                        ucfirst($platform),
                        $product_name,
                        $mappingDetails
                    );
                    break;
                case 'deleted':
                    $status = 'removed';
                    $message = sprintf(
                        'Product deleted from %s sync mapping. Identifier: %s. %s',
                        ucfirst($platform),
                        $product_name,
                        $mappingDetails
                    );
                    break;
                case 'sync_removed':
                    $status = 'removed';
                    $message = sprintf(
                        '%s sync removed for product %s. Mapping cleanup completed. %s',
                        ucfirst($platform),
                        $product_name,
                        $mappingDetails
                    );
                    break;
                case 'sync':
                    $message = sprintf(
                        'Product synced successfully to %s. Identifier: %s. %s',
                        ucfirst($platform),
                        $product_name,
                        $mappingDetails
                    );
                    break;
                default:
                    $message = sprintf(
                        '%s sync state updated for product %s. %s',
                        ucfirst($platform),
                        $product_name,
                        $mappingDetails
                    );
                    break;
            }
        }
        ProductSyncLog::create([
            'product_id' => $product_id,
            'shop_id'    => $shop_id,
            'platform'   => $platform,
            'status'     => $status,
            'message'    => $message,
            'type'       => $normalizedType,
        ]);
        return true;
    }
    public function importSchema($category)
    {
        try {
            // 1. Generate the schema
            $testcontroller = new TestController();
            $response = $testcontroller->getDownloadSchema($category);

            $schemaJson = null;

            // TACTIC A: Intercept and Handle JsonResponse Object
            if ($response instanceof JsonResponse) {
                // If the response contains an error status code (e.g., 400, 500)
                if (!$response->isSuccessful()) {
                    $responseData = $response->getData(true);
                    // Log the exact payload causing the failure
                    Log::error("Schema Fetch Failure for category '{$category}': ", $responseData ?? []);
                    return back()->withErrors(['schema_file' => 'Failed to fetch a valid schema. Please check the system logs.']);
                }
                // If it's a successful JSON response, normalize it to an array
                $schemaJson = $response->getData(true);
            }
            // TACTIC B: Handle raw JSON string
            elseif (is_string($response)) {
                $schemaJson = json_decode($response, true);
            }
            // TACTIC C: Handle raw Array
            elseif (is_array($response)) {
                $schemaJson = $response;
            }

            // 2. Validate the extracted data
            if (empty($schemaJson) || !is_array($schemaJson)) {
                Log::warning("Unusable schema data retrieved for category: {$category}");
                return back()->withErrors(['schema_file' => 'Invalid or empty schema generated for "' . $category . '"']);
            }

            // 3. Parse it (The danger zone is now clear; $schemaJson is guaranteed to be an array)
            $productType = basename($schemaJson['$id'] ?? uniqid());
            $renderer = new SchemaRendererService($schemaJson);
            $fields = $renderer->render();

            ProductSchema::updateOrCreate(
                [
                    'product_type' => $productType
                ],
                [
                    'schema_json' => $schemaJson,
                    'parsed_json' => $fields,
                    'schema_version' => '1.0'
                ]
            );

            // 4. Activate the category
            $categoryModel = Category::where('slug', $productType)->first();
            if (!$categoryModel) {
                Log::error("Schema import succeeded, but category model is missing for slug: {$productType}");
                return back()->withErrors(['schema_file' => 'No category found matching schema product type "' . $productType . '"']);
            }

            $categoryModel->status = 'active';
            $categoryModel->save();

            return redirect()->back()->with(
                'success',
                'Schema imported successfully for "' . $category . '".'
            );
        } catch (\Exception $e) {
            // TACTIC D: The Failsafe - Catch any unexpected crashes in rendering or DB insertion
            Log::critical("System Exception in importSchema for category {$category}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['schema_file' => 'An unexpected internal error occurred while processing the schema.']);
        }
    }
    public function deactivateSchema($category)
    {
        $categoryModel = Category::where('slug', $category)->first();
        if (!$categoryModel) {
            return back()->withErrors(['schema_file' => 'No category found matching "' . $category . '"']);
        }
        $categoryModel->status = 'Inactive';
        $categoryModel->save();
        return redirect()->back()->with(
            'success',
            'Schema deactivated successfully for "' . $category . '".'
        );
    }

    /**
     * Ensures the shop has a valid access token, refreshing if needed.
     * Returns an array: ['success' => bool, 'access_token' => ?string, 'message' => string]
     */
    public function ensureFreshAccessToken(Shop $shopModel): array
    {
        try {
            // still valid — nothing to do
            if ($shopModel->access_token_expires_at && $shopModel->access_token_expires_at->isFuture()) {
                return [
                    'success' => true,
                    'access_token' => $shopModel->access_token,
                    'message' => 'Token still valid.',
                ];
            }

            // refresh token expired — merchant must relaunch the app to re-auth
            if (!$shopModel->refresh_token_expires_at || $shopModel->refresh_token_expires_at->isPast()) {
                Log::warning('REFRESH TOKEN EXPIRED', ['shop' => $shopModel->shop]);

                $shopModel->update(['is_active' => 0]);

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh token expired. App must be relaunched to reauthorize.',
                ];
            }

            $response = Http::asJson()->post("https://{$shopModel->shop}/admin/oauth/access_token", [
                'client_id'     => AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key')),
                'client_secret' => AdminSetting::get('SHOPIFY_API_SECRET', config('services.shopify.api_secret')),
                'grant_type'    => 'refresh_token',
                'refresh_token' => $shopModel->refresh_token,
            ]);

            if (!$response->successful()) {
                Log::error('TOKEN REFRESH FAILED', [
                    'shop' => $shopModel->shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Shopify signals a dead refresh token with 401 invalid_request
                if ($response->status() === 401) {
                    $shopModel->update(['is_active' => 0]);
                    return [
                        'success' => false,
                        'access_token' => null,
                        'message' => 'Refresh token is no longer valid. App must be relaunched to reauthorize.',
                    ];
                }

                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Failed to refresh Shopify access token. Status: ' . $response->status(),
                ];
            }

            $data = $response->json();

            if (!isset($data['access_token'])) {
                Log::error('REFRESH RESPONSE MISSING TOKEN', ['shop' => $shopModel->shop, 'body' => $data]);
                return [
                    'success' => false,
                    'access_token' => null,
                    'message' => 'Refresh response did not include an access token.',
                ];
            }

            $shopModel->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $shopModel->refresh_token,
                'access_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                'refresh_token_expires_at' => now()->addSeconds($data['refresh_token_expires_in'] ?? 90 * 86400),
            ]);

            Log::info('TOKEN REFRESHED', ['shop' => $shopModel->shop]);

            return [
                'success' => true,
                'access_token' => $data['access_token'],
                'message' => 'Token refreshed successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('TOKEN REFRESH EXCEPTION', [
                'shop' => $shopModel->shop ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'access_token' => null,
                'message' => 'Unexpected error while refreshing token: ' . $e->getMessage(),
            ];
        }
    }
}
