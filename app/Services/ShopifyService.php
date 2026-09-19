<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $shop;
    protected $token;
    protected $version = 2026-07;

    public function __construct($shop, $token)
    {
        $this->shop = $shop;
        $this->token = $token;
        $this->version = config('services.shopify.api_version', '2026-07');
    }

    /**
     *  1. Raw GraphQL Call
     */
    // public function graphql($query, $variables = [])
    // {
    //     if (empty($variables)) {
    //         $response = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $this->token,
    //             'Content-Type' => 'application/json',
    //         ])->post("https://{$this->shop}/admin/api/{$this->version}/graphql.json", [
    //             'query' => $query
    //         ]);
    //     } else {
    //         $response = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $this->token,
    //             'Content-Type' => 'application/json',
    //         ])->post("https://{$this->shop}/admin/api/{$this->version}/graphql.json", [
    //             'query' => $query,
    //             'variables' => $variables
    //         ]);
    //     }


    //     // dd($response->body());
    //     if (!$response->successful()) {
    //         \Log::error('Shopify GraphQL Error', [
    //             'body' => $response->body()
    //         ]);
    //         return null;
    //     }

    //     return $response->json();
    // }

    /**
     *  1. Raw GraphQL Call
     */
    public function graphql($query, $variables = [])
    {
        try {
            $payload = ['query' => $query];
            if (!empty($variables)) {
                $payload['variables'] = $variables;
            }

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->post("https://{$this->shop}/admin/api/{$this->version}/graphql.json", $payload);

            if (!$response->successful()) {
                \Log::error('Shopify GraphQL Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'error' => true,
                    'status' => $response->status(),
                    'message' => $response->body()
                ];
            }

            $json = $response->json();

            // Handle HTTP 200 but Empty/Invalid JSON
            if (is_null($json)) {
                return [
                    'error' => true,
                    'status' => 500,
                    'message' => 'Invalid JSON response'
                ];
            }

            return $json;
        } catch (\Exception $e) {
            \Log::error('Shopify GraphQL Exception', [
                'message' => $e->getMessage()
            ]);
            return [
                'error' => true,
                'status' => 0, // Designates a Network Exception
                'message' => $e->getMessage()
            ];
        }
    }

    public function shopifyRest(Shop $shop, string $method, string $endpoint, array $payload = []): array
    {
        $method = strtolower($method);
        $url = sprintf( 'https://%s/admin/api/%s/%s',  $shop->shop,
            config('services.shopify.api_version', '2026-07'),  ltrim($endpoint, '/')
        );
        
        $options = [];
        if ($method === 'get') {
            $options['query'] = $payload;
        } else {
            $options['json'] = $payload;
        }
        try {
            $response = Http::timeout(120)
                ->connectTimeout(120)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->send(strtoupper($method), $url, $options);
            if (!$response->successful()) {
                $body = $response->body();
                $json = $response->json();
                Log::error('Shopify API Error', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'json' => $response->json(),
                ]);
                return [
                    'error' => true,
                    'status' => $response->status(),
                    'message' => $json ? json_encode($json) : $body,
                ];
            }
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Shopify API Exception', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Shopify Locations via Admin GraphQL API.
     *
     * @param Shop|null $shop
     * @return array
     */
    public function getLocations(?Shop $shop = null): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        $query = <<<'GRAPHQL'
        query GetLocations {
            locations(first: 50, includeInactive: false) {
                nodes {
                    id
                    legacyResourceId
                    name
                    isActive
                    address {
                        address1
                        address2
                        city
                        province
                        country
                        zip
                        phone
                        countryCode
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->graphql($query);

        if (!empty($response['error']) || !empty($response['errors'])) {
            $errorMsg = $response['message'] ?? (is_array($response['errors'] ?? null) ? json_encode($response['errors']) : 'Failed to fetch Shopify locations via GraphQL.');
            Log::error('Shopify GraphQL getLocations Error', [
                'shop' => $this->shop,
                'response' => $response,
            ]);
            return [
                'error' => true,
                'status' => $response['status'] ?? 500,
                'message' => $errorMsg,
                'locations' => [],
            ];
        }

        $nodes = data_get($response, 'data.locations.nodes', []);
        $locations = collect($nodes)->map(function ($node) {
            $gid = $node['id'] ?? '';
            $numericId = $node['legacyResourceId'] ?? (str_contains((string) $gid, 'gid://shopify/Location/') ? substr($gid, strrpos($gid, '/') + 1) : $gid);

            return [
                'id' => is_numeric($numericId) ? (int) $numericId : $numericId,
                'name' => $node['name'] ?? '',
                'active' => $node['isActive'] ?? true,
                'address1' => data_get($node, 'address.address1'),
                'address2' => data_get($node, 'address.address2'),
                'city' => data_get($node, 'address.city'),
                'province' => data_get($node, 'address.province'),
                'country' => data_get($node, 'address.country'),
                'zip' => data_get($node, 'address.zip'),
                'phone' => data_get($node, 'address.phone'),
                'country_code' => data_get($node, 'address.countryCode'),
                'admin_graphql_api_id' => $gid,
            ];
        })->values()->all();

        return [
            'error' => false,
            'locations' => $locations,
        ];
    }

    /**
     * Get authoritative Shopify Inventory Level for a specific item and location via GraphQL.
     *
     * @param mixed $param1 (Shop or string|int $inventoryItemId)
     * @param mixed $param2 (string|int $inventoryItemId or string|int $locationId)
     * @param mixed $param3 (string|int $locationId or Shop|null)
     * @return array
     */
    public function getInventoryLevel($param1, $param2 = null, $param3 = null): array
    {
        if ($param1 instanceof Shop) {
            $shop = $param1;
            $inventoryItemId = $param2;
            $locationId = $param3;
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        } else {
            $inventoryItemId = $param1;
            $locationId = $param2;
            $shop = $param3;
            if ($shop instanceof Shop) {
                $this->shop = $shop->shop;
                $this->token = $shop->access_token;
            }
        }

        if (blank($inventoryItemId)) {
            return [
                'error' => true,
                'status' => 422,
                'message' => 'Inventory item ID is required.',
                'inventory_levels' => [],
                'available' => null,
            ];
        }

        $itemGid = str_starts_with((string) $inventoryItemId, 'gid://')
            ? (string) $inventoryItemId
            : "gid://shopify/InventoryItem/{$inventoryItemId}";

        $locNumeric = null;
        $locGid = null;
        if (!blank($locationId)) {
            $locNumeric = str_contains((string) $locationId, 'gid://shopify/Location/')
                ? substr((string) $locationId, strrpos((string) $locationId, '/') + 1)
                : (string) $locationId;
            $locGid = str_starts_with((string) $locationId, 'gid://')
                ? (string) $locationId
                : "gid://shopify/Location/{$locationId}";
        }

        $query = <<<'GRAPHQL'
        query GetInventoryItemLevels($id: ID!) {
            inventoryItem(id: $id) {
                id
                legacyResourceId
                inventoryLevels(first: 50) {
                    nodes {
                        id
                        location {
                            id
                            legacyResourceId
                            name
                        }
                        quantities(names: ["available", "on_hand", "committed"]) {
                            name
                            quantity
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->graphql($query, ['id' => $itemGid]);

        if (!empty($response['error']) || !empty($response['errors'])) {
            $errorMsg = $response['message'] ?? (is_array($response['errors'] ?? null) ? json_encode($response['errors']) : 'Failed to fetch inventory levels via GraphQL.');
            Log::error('Shopify GraphQL getInventoryLevel Error', [
                'shop' => $this->shop,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'response' => $response,
            ]);
            return [
                'error' => true,
                'status' => $response['status'] ?? 500,
                'message' => $errorMsg,
                'inventory_levels' => [],
                'available' => null,
            ];
        }

        $item = data_get($response, 'data.inventoryItem');
        if (!$item) {
            return [
                'error' => true,
                'status' => 404,
                'message' => 'Inventory item not found on Shopify.',
                'inventory_levels' => [],
                'available' => null,
            ];
        }

        $numericItemId = $item['legacyResourceId'] ?? (str_contains($itemGid, 'gid://shopify/InventoryItem/') ? substr($itemGid, strrpos($itemGid, '/') + 1) : $itemGid);
        $numericItemId = is_numeric($numericItemId) ? (int) $numericItemId : $numericItemId;

        $levelNodes = data_get($item, 'inventoryLevels.nodes', []);
        $parsedLevels = [];
        $matchedAvailable = null;
        $matchedLevel = null;

        foreach ($levelNodes as $node) {
            $nodeLocGid = data_get($node, 'location.id');
            $nodeLocNumeric = data_get($node, 'location.legacyResourceId') ?? (str_contains((string) $nodeLocGid, 'gid://shopify/Location/') ? substr($nodeLocGid, strrpos($nodeLocGid, '/') + 1) : (string) $nodeLocGid);
            $nodeLocNumeric = is_numeric($nodeLocNumeric) ? (int) $nodeLocNumeric : $nodeLocNumeric;

            $quantities = data_get($node, 'quantities', []);
            $availableQty = null;
            $onHandQty = null;
            $committedQty = null;

            foreach ($quantities as $q) {
                $name = $q['name'] ?? '';
                if ($name === 'available') {
                    $availableQty = isset($q['quantity']) ? (int) $q['quantity'] : null;
                } elseif ($name === 'on_hand') {
                    $onHandQty = isset($q['quantity']) ? (int) $q['quantity'] : null;
                } elseif ($name === 'committed') {
                    $committedQty = isset($q['quantity']) ? (int) $q['quantity'] : null;
                }
            }

            $lvlEntry = [
                'inventory_item_id' => $numericItemId,
                'location_id' => $nodeLocNumeric,
                'available' => $availableQty,
                'on_hand' => $onHandQty,
                'committed' => $committedQty,
                'admin_graphql_api_id' => $node['id'] ?? null,
            ];

            $parsedLevels[] = $lvlEntry;

            if ($locNumeric !== null && ((string) $nodeLocNumeric === (string) $locNumeric || (string) $nodeLocGid === (string) $locGid)) {
                $matchedAvailable = $availableQty;
                $matchedLevel = $lvlEntry;
            }
        }

        if ($matchedAvailable === null && empty($locationId) && !empty($parsedLevels)) {
            $matchedAvailable = $parsedLevels[0]['available'] ?? null;
            $matchedLevel = $parsedLevels[0];
        }

        return [
            'error' => false,
            'inventory_item_id' => $numericItemId,
            'location_id' => $locNumeric !== null && is_numeric($locNumeric) ? (int) $locNumeric : $locNumeric,
            'available' => $matchedAvailable,
            'inventory_levels' => $parsedLevels,
            'level' => $matchedLevel,
        ];
    }

    /**
     * Set inventory quantity on Shopify via Admin GraphQL mutation (inventorySetQuantities).
     *
     * @param mixed $param1 (Shop or string|int $inventoryItemId)
     * @param mixed $param2 (string|int $inventoryItemId or string|int $locationId)
     * @param mixed $param3 (string|int $locationId or int $quantity)
     * @param mixed $param4 (int $quantity or Shop|null)
     * @return array
     */
    public function setInventoryQuantity($param1, $param2 = null, $param3 = null, $param4 = null): array
    {
        if ($param1 instanceof Shop) {
            $shop = $param1;
            $inventoryItemId = $param2;
            $locationId = $param3;
            $quantity = (int) $param4;
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        } else {
            $inventoryItemId = $param1;
            $locationId = $param2;
            $quantity = (int) $param3;
            $shop = $param4;
            if ($shop instanceof Shop) {
                $this->shop = $shop->shop;
                $this->token = $shop->access_token;
            }
        }

        if (blank($inventoryItemId) || blank($locationId)) {
            return [
                'error' => true,
                'status' => 422,
                'message' => 'Shopify inventory item ID and location ID are required.',
            ];
        }

        $itemGid = str_starts_with((string) $inventoryItemId, 'gid://')
            ? (string) $inventoryItemId
            : "gid://shopify/InventoryItem/{$inventoryItemId}";

        $locGid = str_starts_with((string) $locationId, 'gid://')
            ? (string) $locationId
            : "gid://shopify/Location/{$locationId}";

        $numericItemId = str_contains($itemGid, 'gid://shopify/InventoryItem/')
            ? substr($itemGid, strrpos($itemGid, '/') + 1)
            : $inventoryItemId;
        $numericItemId = is_numeric($numericItemId) ? (int) $numericItemId : $numericItemId;

        $numericLocId = str_contains($locGid, 'gid://shopify/Location/')
            ? substr($locGid, strrpos($locGid, '/') + 1)
            : $locationId;
        $numericLocId = is_numeric($numericLocId) ? (int) $numericLocId : $numericLocId;

        $mutation = <<<'GRAPHQL'
        mutation InventorySetQuantities($input: InventorySetQuantitiesInput!) {
            inventorySetQuantities(input: $input) {
                inventoryAdjustmentGroup {
                    reason
                    changes {
                        name
                        delta
                        quantity
                        item {
                            id
                            legacyResourceId
                        }
                        location {
                            id
                            legacyResourceId
                        }
                    }
                }
                userErrors {
                    field
                    message
                    code
                }
            }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'name' => 'available',
                'reason' => 'cycle_count_available',
                'ignoreCompareQuantity' => true,
                'quantities' => [
                    [
                        'inventoryItemId' => $itemGid,
                        'locationId' => $locGid,
                        'quantity' => (int) $quantity,
                    ]
                ]
            ]
        ];

        $response = $this->graphql($mutation, $variables);

        if (!empty($response['error']) || !empty($response['errors'])) {
            $errorMsg = $response['message'] ?? (is_array($response['errors'] ?? null) ? json_encode($response['errors']) : 'Failed to execute inventorySetQuantities mutation.');
            Log::error('Shopify GraphQL setInventoryQuantity Error', [
                'shop' => $this->shop,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'response' => $response,
            ]);
            return [
                'error' => true,
                'status' => $response['status'] ?? 500,
                'message' => $errorMsg,
            ];
        }

        $userErrors = data_get($response, 'data.inventorySetQuantities.userErrors', []);
        if (!empty($userErrors)) {
            $firstMsg = $userErrors[0]['message'] ?? 'Inventory update rejected by Shopify.';
            Log::error('Shopify GraphQL setInventoryQuantity userErrors', [
                'shop' => $this->shop,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'userErrors' => $userErrors,
            ]);
            return [
                'error' => true,
                'status' => 422,
                'message' => $firstMsg,
                'userErrors' => $userErrors,
            ];
        }

        return [
            'error' => false,
            'inventory_level' => [
                'inventory_item_id' => $numericItemId,
                'location_id' => $numericLocId,
                'available' => (int) $quantity,
            ],
            'data' => $response['data'] ?? [],
        ];
    }

    /**
     * 2. Paginated Query (Array → GraphQL)
     */
    public function paginate($structure, $first = 50, $cursor = null)
    {
        // 🔹 Build GraphQL fields
        $queryBody = $this->buildQuery($structure);

        $query = <<<GRAPHQL
        query (\$cursor: String) {
            products(first: {$first}, after: \$cursor) {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                nodes {
                    {$queryBody}
                }
            }
        }
        GRAPHQL;

        $response = $this->graphql($query, [
            'cursor' => $cursor
        ]);
        Log::info('SHOPIFY PRODUCTS GRAPHQL RESPONSE', [
            'shop' => $this->shop,
            'response' => $response,
        ]);

        if (!$response || !isset($response['data']['products'])) {
            return [
                'data' => [],
                'next_cursor' => null,
                'has_next' => false
            ];
        }

        $products = $response['data']['products']['nodes'] ?? [];
        $pageInfo = $response['data']['products']['pageInfo'] ?? [];

        return [
            'data' => $products,
            'next_cursor' => $pageInfo['endCursor'] ?? null,
            'has_next' => $pageInfo['hasNextPage'] ?? false
        ];
    }

    /**
     *  Helper: Convert array → GraphQL fields
     */
    private function buildQuery($fields)
    {
        $query = '';

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $query .= $key . ' { ' . $this->buildQuery($value) . ' } ';
            } else {
                $query .= $value . ' ';
            }
        }

        return $query;
    }


    public function paginateRefunds($structure, $first = 50, $cursor = null, $search = null)
    {
        $queryBody = trim($this->buildQuery($structure));

        $query = <<<GRAPHQL
        query (\$cursor: String) {
            orders(first: {$first}, after: \$cursor, query: "financial_status:refunded") {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                nodes {
                    {$queryBody}
                }
            }
        }
        GRAPHQL;


        $response = $this->graphql($query, [
            'cursor' => $cursor
        ]);

        if (!$response || !isset($response['data']['orders'])) {
            return [
                'data' => [],
                'next_cursor' => null,
                'has_next' => false
            ];
        }

        return [
            'data' => $response['data']['orders']['nodes'] ?? [],
            'next_cursor' => $response['data']['orders']['pageInfo']['endCursor'] ?? null,
            'has_next' => $response['data']['orders']['pageInfo']['hasNextPage'] ?? false
        ];
    }

    public function getProductById($productId)
    {

        $productId = "gid://shopify/Product/" . $productId;

        $query = '
        query ProductFull($ownerId: ID!) {
          product(id: $ownerId) {
            id
            title
            vendor
            productType
            status
            handle
            descriptionHtml
            featuredImage {
              url
            }
            images(first: 10) {
              nodes {
                id
                url
              }
            }        
            variants(first: 50) {
              nodes {
                id
                title
                price
                sku
                inventoryQuantity
                selectedOptions {
                  name
                  value
                }
                image {
                  url
                }
                inventoryItem {
                  id
                  inventoryLevels(first: 1) {
                    nodes {
                      id
                      quantities(names: ["available", "committed", "incoming", "on_hand"]) {
                        name
                        quantity
                      }
                    }
                  }
                }
              }
            }
            metafields(first: 10) {
              edges {
                node {
                  namespace
                  key
                  value
                }
              }
            }
          }
        }';


        $response = $this->graphql($query, ['ownerId' => $productId]);

        $productData = $response['data']['product'] ?? null;

        if (!$productData) {
            return null;
        }

        return [
            'id' => $productData['id'],
            'title' => $productData['title'],
            'vendor' => $productData['vendor'],
            'product_type' => $productData['productType'],
            'status' => $productData['status'],
            'handle' => $productData['handle'],
            'body_html' => $productData['descriptionHtml'],
            'images' => collect($productData['images']['nodes'])->map(function ($img) {
                return [
                    'src' => $img['url']
                ];
            })->toArray(),

            'variants' => collect($productData['variants']['nodes'])->map(function ($v) {

                return [
                    'id' => $v['id'],
                    'title' => $v['title'],
                    'price' => $v['price'],
                    'sku' => $v['sku'],
                    'option1' => $v['selectedOptions'][0]['value'] ?? '',
                    'option2' => $v['selectedOptions'][1]['value'] ?? '',
                    'inventory_quantity' => $v['inventoryQuantity'] ?? 0,
                    'image_src' => $v['image']['url'] ?? null,
                ];
            })->toArray(),
        ];
    }

    public function getRefundDetails($orderId)
    {
        if (!str_contains($orderId, 'gid://')) {
            $orderId = "gid://shopify/Order/" . $orderId;
        }

        $query = '
        query ($id: ID!) {
        order(id: $id) {
            id
            name
            createdAt
            customer {
                firstName
                lastName
                email
            }
            refunds {
            id
            createdAt
            totalRefundedSet {
                shopMoney {
                    amount
                    currencyCode
                }
            }
            refundLineItems(first: 10) {
                nodes {
                quantity
                lineItem {
                    title
                    sku
                }
                }
            }
            }
        }
        }';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post("https://{$this->shop}/admin/api/2024-01/graphql.json", [
            'query' => $query,
            'variables' => [
                'id' => $orderId
            ]
        ]);

        return $response->json();
    }

    public function getOrder(string|int $orderId): ?array
    {
        $id = str_starts_with((string) $orderId, 'gid://')
            ? $orderId
            : "gid://shopify/Order/{$orderId}";

        $query = <<<'GRAPHQL'
    query ($id: ID!) {
    order(id: $id) {
        id legacyResourceId name orderNumber email phone createdAt processedAt cancelledAt
        currencyCode displayFinancialStatus displayFulfillmentStatus note tags
        customer { id firstName lastName email phone }
        billingAddress { firstName lastName company address1 address2 city province provinceCode country countryCodeV2 zip phone }
        shippingAddress { firstName lastName company address1 address2 city province provinceCode country countryCodeV2 zip phone }
        subtotalPriceSet { shopMoney { amount } }
        totalTaxSet { shopMoney { amount } }
        totalDiscountsSet { shopMoney { amount } }
        totalPriceSet { shopMoney { amount } }
        lineItems(first: 250) {
        nodes {
            id name title quantity sku variantTitle
            originalUnitPriceSet { shopMoney { amount } }
            discountedUnitPriceSet { shopMoney { amount } }
            variant { id legacyResourceId title sku }
            product { id legacyResourceId title }
        }
        }
        discountCodes { code applicable }
        shippingLines(first: 50) {
        nodes { title code originalPriceSet { shopMoney { amount } } }
        }
        taxLines { title rate priceSet { shopMoney { amount } } }
    }
    }
    GRAPHQL;

        $response = $this->graphql($query, ['id' => $id]);

        if (!empty($response['error']) || !empty($response['errors'])) {
            throw new \RuntimeException(
                'Shopify GraphQL error: ' .
                ($response['message'] ?? json_encode($response['errors']))
            );
        }

        $o = data_get($response, 'data.order');

        if (!$o) {
            return null;
        }

        $customer = $o['customer'] ?? [];
        $items = collect(data_get($o, 'lineItems.nodes', []))
            ->map(fn ($i) => [
                'id' => $i['id'] ?? null,
                'name' => $i['name'] ?? null,
                'title' => $i['title'] ?? null,
                'quantity' => $i['quantity'] ?? 0,
                'sku' => $i['sku'] ?? null,
                'variant_title' => $i['variantTitle'] ?? null,
                'price' => data_get($i, 'originalUnitPriceSet.shopMoney.amount'),
                'discounted_price' => data_get($i, 'discountedUnitPriceSet.shopMoney.amount'),
                'variant' => $i['variant'] ?? null,
                'product' => $i['product'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'id' => $o['legacyResourceId'] ?? $o['id'],
            'admin_graphql_api_id' => $o['id'],
            'order_number' => $o['orderNumber'] ?? null,
            'name' => $o['name'] ?? null,
            'email' => $o['email'] ?? null,
            'phone' => $o['phone'] ?? null,
            'customer' => [
                'id' => $customer['id'] ?? null,
                'first_name' => $customer['firstName'] ?? null,
                'last_name' => $customer['lastName'] ?? null,
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
            ],
            'financial_status' => $o['displayFinancialStatus'] ?? null,
            'fulfillment_status' => $o['displayFulfillmentStatus'] ?? null,
            'currency' => $o['currencyCode'] ?? null,
            'subtotal_price' => (float) data_get($o, 'subtotalPriceSet.shopMoney.amount', 0),
            'total_tax' => (float) data_get($o, 'totalTaxSet.shopMoney.amount', 0),
            'total_discounts' => (float) data_get($o, 'totalDiscountsSet.shopMoney.amount', 0),
            'total_price' => (float) data_get($o, 'totalPriceSet.shopMoney.amount', 0),
            'line_items_count' => count($items),
            'source_name' => null,
            'tags' => $o['tags'] ?? [],
            'note' => $o['note'] ?? null,
            'customer' => $customer ? [
                'id' => $customer['id'] ?? null,
                'first_name' => $customer['firstName'] ?? null,
                'last_name' => $customer['lastName'] ?? null,
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
            ] : [],
            'billing_address' => $o['billingAddress'] ?? null,
            'shipping_address' => $o['shippingAddress'] ?? null,
            'line_items' => $items,
            'discount_codes' => $o['discountCodes'] ?? [],
            'shipping_lines' => data_get($o, 'shippingLines.nodes', []),
            'tax_lines' => $o['taxLines'] ?? [],
            'created_at' => $o['createdAt'] ?? null,
            'processed_at' => $o['processedAt'] ?? null,
            'cancelled_at' => $o['cancelledAt'] ?? null,
            'raw_payload' => $o,
        ];
    }
}
