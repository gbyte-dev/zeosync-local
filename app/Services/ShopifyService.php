<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $shop;
    protected $token;
    protected $version = '2026-01';

    public function __construct($shop, $token)
    {
        $this->shop = $shop;
        $this->token = $token;
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
        $url = sprintf(
            'https://%s/admin/api/%s/%s',
            $shop->shop,
            config('services.shopify.api_version', '2026-01'),
            ltrim($endpoint, '/')
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
dd($response->body());
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
}
