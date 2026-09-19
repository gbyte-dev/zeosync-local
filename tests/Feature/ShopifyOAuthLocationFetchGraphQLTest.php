<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\ShopifyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'services.shopify.api_key'    => 'test-api-key',
        'services.shopify.api_secret' => 'test-api-secret',
        'app.disable_subscription'    => true,
    ]);

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('domain')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->json('shopify_locations')->nullable();
            $table->integer('selected_location_index')->nullable();
            $table->string('selected_location_id')->nullable();
            $table->string('amazon_marketplace_id')->nullable();
            $table->string('amazon_seller_id')->nullable();
            $table->string('amazon_mws_region')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->string('hmac')->nullable();
            $table->string('store_status')->nullable();
            $table->string('shopify_connection_status')->nullable();
            $table->softDeletes();
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
});

it('Test 1: successfully fetches single location via GraphQL with backward-compatible structure', function () {
    $shop = Shop::create([
        'shop' => 'single-loc.myshopify.com',
        'access_token' => 'shpat_single_123',
    ]);

    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor' => null,
                    ],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Location/998877',
                            'legacyResourceId' => '998877',
                            'name' => 'Main Warehouse',
                            'isActive' => true,
                            'address' => [
                                'address1' => '100 Market St',
                                'address2' => 'Suite 200',
                                'city' => 'San Francisco',
                                'province' => 'CA',
                                'country' => 'United States',
                                'zip' => '94105',
                                'phone' => '415-555-0100',
                                'countryCode' => 'US',
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->getLocations($shop);

    expect($result['error'])->toBeFalse();
    expect($result['locations'])->toHaveCount(1);

    $loc = $result['locations'][0];
    expect($loc['id'])->toBe(998877);
    expect($loc['name'])->toBe('Main Warehouse');
    expect($loc['active'])->toBeTrue();
    expect($loc['address1'])->toBe('100 Market St');
    expect($loc['city'])->toBe('San Francisco');
    expect($loc['province'])->toBe('CA');
    expect($loc['country'])->toBe('United States');
    expect($loc['zip'])->toBe('94105');
    expect($loc['country_code'])->toBe('US');
    expect($loc['admin_graphql_api_id'])->toBe('gid://shopify/Location/998877');
});

it('Test 2: successfully fetches multiple locations via GraphQL', function () {
    $shop = Shop::create([
        'shop' => 'multi-loc.myshopify.com',
        'access_token' => 'shpat_multi_123',
    ]);

    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor' => null,
                    ],
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Location/1001',
                            'legacyResourceId' => '1001',
                            'name' => 'Location Alpha',
                            'isActive' => true,
                        ],
                        [
                            'id' => 'gid://shopify/Location/1002',
                            'legacyResourceId' => '1002',
                            'name' => 'Location Beta',
                            'isActive' => true,
                        ],
                        [
                            'id' => 'gid://shopify/Location/1003',
                            'legacyResourceId' => '1003',
                            'name' => 'Location Gamma',
                            'isActive' => false,
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->getLocations($shop);

    expect($result['error'])->toBeFalse();
    expect($result['locations'])->toHaveCount(3);
    expect($result['locations'][0]['name'])->toBe('Location Alpha');
    expect($result['locations'][1]['name'])->toBe('Location Beta');
    expect($result['locations'][2]['name'])->toBe('Location Gamma');
    expect($result['locations'][2]['active'])->toBeFalse();
});

it('Test 3: successfully handles paginated locations across multiple GraphQL pages', function () {
    $shop = Shop::create([
        'shop' => 'paged-loc.myshopify.com',
        'access_token' => 'shpat_paged_123',
    ]);

    $requestCount = 0;

    Http::fake([
        '*graphql.json*' => function (\Illuminate\Http\Client\Request $request) use (&$requestCount) {
            $requestCount++;
            $data = $request->data();
            $vars = $data['variables'] ?? [];
            $after = $vars['after'] ?? null;

            if ($after === null) {
                // Page 1
                return Http::response([
                    'data' => [
                        'locations' => [
                            'pageInfo' => [
                                'hasNextPage' => true,
                                'endCursor' => 'cursor_page_1',
                            ],
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/Location/2001',
                                    'legacyResourceId' => '2001',
                                    'name' => 'Page 1 - Warehouse A',
                                    'isActive' => true,
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            if ($after === 'cursor_page_1') {
                // Page 2
                return Http::response([
                    'data' => [
                        'locations' => [
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => 'cursor_page_2',
                            ],
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/Location/2002',
                                    'legacyResourceId' => '2002',
                                    'name' => 'Page 2 - Warehouse B',
                                    'isActive' => true,
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['locations' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []]]], 200);
        },
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->getLocations($shop);

    expect($result['error'])->toBeFalse();
    expect($requestCount)->toBe(2);
    expect($result['locations'])->toHaveCount(2);
    expect($result['locations'][0]['name'])->toBe('Page 1 - Warehouse A');
    expect($result['locations'][1]['name'])->toBe('Page 2 - Warehouse B');
});

it('Test 4: handles empty locations gracefully', function () {
    $shop = Shop::create([
        'shop' => 'empty-loc.myshopify.com',
        'access_token' => 'shpat_empty_123',
    ]);

    Http::fake([
        '*graphql.json*' => Http::response([
            'data' => [
                'locations' => [
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'endCursor' => null,
                    ],
                    'nodes' => [],
                ],
            ],
        ], 200),
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->getLocations($shop);

    expect($result['error'])->toBeFalse();
    expect($result['locations'])->toBeArray()->toBeEmpty();
});

it('Test 5: handles GraphQL top-level errors and network failures cleanly', function () {
    $shop = Shop::create([
        'shop' => 'error-loc.myshopify.com',
        'access_token' => 'shpat_error_123',
    ]);

    Http::fake([
        '*graphql.json*' => Http::response([
            'errors' => [
                [
                    'message' => 'Unauthorized access to locations API',
                ],
            ],
        ], 401),
    ]);

    $service = new ShopifyService($shop->shop, $shop->access_token);
    $result = $service->getLocations($shop);

    expect($result['error'])->toBeTrue();
    expect($result['status'])->toBe(401);
    expect($result['message'])->toContain('Unauthorized access');
    expect($result['locations'])->toBeEmpty();
});

it('Test 6: OAuth callback persists locations fetched via GraphQL to the shop model and sets selected_location_index = 0', function () {
    config([
        'services.shopify.api_key' => 'test_api_key',
        'services.shopify.api_secret' => 'test_api_secret',
    ]);

    $shopDomain = 'oauth-test-shop.myshopify.com';
    $stateNonce = 'test_nonce_123';
    $state = base64_encode(json_encode(['shop' => $shopDomain, 'nonce' => $stateNonce]));
    session(['shopify_oauth_state' => $stateNonce]);

    \App\Models\AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_KEY'],
        ['option_value' => 'test_api_key']
    );
    \App\Models\AdminSetting::updateOrCreate(
        ['option_key' => 'SHOPIFY_API_SECRET'],
        ['option_value' => 'test_api_secret']
    );

    $code = 'auth_code_xyz';

    $recorded = [];
    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$recorded) {
        $recorded[] = [
            'url' => $request->url(),
            'data' => $request->data(),
        ];

        if (str_contains($request->url(), 'oauth/access_token')) {
            return Http::response([
                'access_token' => 'shpat_oauth_token_789',
                'expires_in' => 86400,
            ], 200);
        }

        if (str_contains($request->url(), 'graphql.json')) {
            $query = $request->data()['query'] ?? '';
            if (str_contains($query, 'locations(') || str_contains($query, 'GetLocations')) {
                return Http::response([
                    'data' => [
                        'locations' => [
                            'pageInfo' => [
                                'hasNextPage' => false,
                                'endCursor' => null,
                            ],
                            'nodes' => [
                                [
                                    'id' => 'gid://shopify/Location/777001',
                                    'legacyResourceId' => '777001',
                                    'name' => 'OAuth Primary Location',
                                    'isActive' => true,
                                ],
                                [
                                    'id' => 'gid://shopify/Location/777002',
                                    'legacyResourceId' => '777002',
                                    'name' => 'OAuth Secondary Location',
                                    'isActive' => true,
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }
            return Http::response(['data' => []], 200);
        }

        return Http::response(['data' => []], 200);
    });

    $params = [
        'code' => $code,
        'shop' => $shopDomain,
        'state' => $state,
        'timestamp' => (string) time(),
    ];
    ksort($params);
    $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $hmac = hash_hmac('sha256', $queryString, 'test_api_secret');
    $params['hmac'] = $hmac;

    $response = $this->get('/callback?' . http_build_query($params));

    $response->assertOk();
    $response->assertViewIs('shopify.auth-callback');

    $shopModel = Shop::where('shop', $shopDomain)->first();
    expect($shopModel)->not->toBeNull();
    expect($shopModel->access_token)->toBe('shpat_oauth_token_789');

    $rawLocations = $shopModel->shopify_locations;
    $locations = is_array($rawLocations)
        ? $rawLocations
        : (is_string($rawLocations) ? json_decode($rawLocations, true) : []);

    expect($locations)->toBeArray()->toHaveCount(2);
    expect($locations[0]['id'])->toBe(777001);
    expect($locations[0]['name'])->toBe('OAuth Primary Location');
    expect($shopModel->selected_location_index)->toBe(0);
});
