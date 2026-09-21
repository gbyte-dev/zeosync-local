<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Shop;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $shop;
    protected $token;
    protected $version = '2026-07';

    public function __construct($shop, $token)
    {
        $this->shop = $shop;
        $this->token = $token;
        $this->version = config('services.shopify.api_version', '2026-07');
    }

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
        query GetLocations($first: Int!, $after: String) {
            locations(first: $first, after: $after, includeInactive: false) {
                pageInfo {
                    hasNextPage
                    endCursor
                }
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

        $allLocations = [];
        $hasNextPage = true;
        $after = null;
        $pageSize = 50;

        while ($hasNextPage) {
            $variables = [
                'first' => $pageSize,
                'after' => $after,
            ];

            $response = $this->graphql($query, $variables);

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
            foreach ($nodes as $node) {
                $gid = $node['id'] ?? '';
                $numericId = $node['legacyResourceId'] ?? (str_contains((string) $gid, 'gid://shopify/Location/') ? substr($gid, strrpos($gid, '/') + 1) : $gid);

                $allLocations[] = [
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
            }

            $pageInfo = data_get($response, 'data.locations.pageInfo', []);
            $hasNextPage = (bool) ($pageInfo['hasNextPage'] ?? false);
            $after = $pageInfo['endCursor'] ?? null;

            if ($hasNextPage && blank($after)) {
                break;
            }
        }

        return [
            'error' => false,
            'locations' => $allLocations,
        ];
    }

    /**
     * Fetch all products from Shopify via Admin GraphQL with cursor pagination,
     * normalized into the legacy array format for DB synchronization.
     *
     * @param Shop|null $shop
     * @param int|string|null $locationId
     * @param int $pageSize
     * @return array
     */
    public function getProductsForSync(?Shop $shop = null, $locationId = null, int $pageSize = 50): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        $query = <<<'GRAPHQL'
        query GetProductsForSync($first: Int!, $after: String) {
            products(first: $first, after: $after) {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                nodes {
                    id
                    legacyResourceId
                    title
                    handle
                    descriptionHtml
                    vendor
                    productType
                    status
                    tags
                    createdAt
                    updatedAt
                    options {
                        id
                        name
                        position
                        values
                    }
                    media(first: 50) {
                        nodes {
                            id
                            alt
                            mediaContentType
                            preview {
                                image {
                                    id
                                    url
                                    altText
                                    width
                                    height
                                }
                            }
                            ... on MediaImage {
                                id
                                image {
                                    id
                                    url
                                    altText
                                    width
                                    height
                                }
                            }
                        }
                    }
                    images(first: 50) {
                        nodes {
                            id
                            url
                            altText
                            width
                            height
                        }
                    }
                    variants(first: 250) {
                        nodes {
                            id
                            legacyResourceId
                            title
                            sku
                            barcode
                            price
                            compareAtPrice
                            position
                            selectedOptions {
                                name
                                value
                            }
                            media(first: 10) {
                                nodes {
                                    id
                                    alt
                                    mediaContentType
                                    preview {
                                        image {
                                            id
                                            url
                                            altText
                                            width
                                            height
                                        }
                                    }
                                    ... on MediaImage {
                                        id
                                        image {
                                            id
                                            url
                                            altText
                                            width
                                            height
                                        }
                                    }
                                }
                            }
                            image {
                                id
                                url
                            }
                            inventoryQuantity
                            inventoryItem {
                                id
                                legacyResourceId
                                inventoryLevels(first: 50) {
                                    nodes {
                                        location {
                                            id
                                            legacyResourceId
                                        }
                                        quantities(names: ["available", "on_hand", "committed"]) {
                                            name
                                            quantity
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $allProducts = [];
        $hasNextPage = true;
        $after = null;
        $pageCount = 0;
        $maxPages = 250;
        $visitedCursors = [];

        Log::info('SHOPIFY GRAPHQL PRODUCT SYNC START', [
            'shop' => $this->shop,
            'location_id' => $locationId,
            'page_size' => $pageSize,
        ]);

        while ($hasNextPage && $pageCount < $maxPages) {
            $pageCount++;

            $variables = [
                'first' => min($pageSize, 250),
                'after' => $after,
            ];

            $response = $this->graphql($query, $variables);

            if (!empty($response['error']) || !empty($response['errors'])) {
                $errorMsg = $response['message'] ?? (is_array($response['errors'] ?? null) ? json_encode($response['errors']) : 'Failed to fetch products via GraphQL.');
                Log::error('Shopify GraphQL getProductsForSync Error', [
                    'shop' => $this->shop,
                    'response' => $response,
                    'after' => $after,
                    'page' => $pageCount,
                ]);
                return [
                    'error' => true,
                    'message' => $errorMsg,
                    'products' => $allProducts,
                ];
            }

            $productNodes = data_get($response, 'data.products.nodes', []);

            foreach ($productNodes as $node) {
                $allProducts[] = $this->normalizeProductNode($node, $locationId);
            }

            $pageInfo = data_get($response, 'data.products.pageInfo', []);
            $hasNextPage = (bool) ($pageInfo['hasNextPage'] ?? false);
            $nextCursor = $pageInfo['endCursor'] ?? null;

            if (!$hasNextPage || empty($nextCursor) || empty($productNodes)) {
                break;
            }

            if (isset($visitedCursors[$nextCursor]) || $nextCursor === $after) {
                Log::warning('Shopify product pagination repeated cursor detected', [
                    'shop' => $this->shop,
                    'cursor' => $nextCursor,
                    'page' => $pageCount,
                ]);
                break;
            }

            $visitedCursors[$nextCursor] = true;
            $after = $nextCursor;
        }

        Log::info('SHOPIFY GRAPHQL PRODUCT SYNC COMPLETE', [
            'shop' => $this->shop,
            'total_products' => count($allProducts),
            'total_pages' => $pageCount,
        ]);

        return [
            'error' => false,
            'products' => $allProducts,
        ];
    }

    /**
     * Get single product details for view/edit page via GraphQL (API 2026-07).
     *
     * @param Shop|null $shop
     * @param int|string $productId
     * @param int|string|null $locationId
     * @return array|null
     */
    public function getProductForView(?Shop $shop = null, $productId = null, $locationId = null): ?array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        if (blank($productId)) {
            return null;
        }

        $productGid = str_starts_with((string) $productId, 'gid://')
            ? (string) $productId
            : "gid://shopify/Product/{$productId}";

        $query = <<<'GRAPHQL'
        query GetProductForView($id: ID!) {
            product(id: $id) {
                id
                legacyResourceId
                title
                handle
                descriptionHtml
                vendor
                productType
                status
                tags
                createdAt
                updatedAt
                featuredImage {
                    id
                    url
                    altText
                }
                options {
                    id
                    name
                    position
                    values
                }
                media(first: 50) {
                    nodes {
                        id
                        alt
                        mediaContentType
                        preview {
                            image {
                                id
                                url
                                altText
                                width
                                height
                            }
                        }
                        ... on MediaImage {
                            id
                            image {
                                id
                                url
                                altText
                                width
                                height
                            }
                        }
                    }
                }
                images(first: 50) {
                    nodes {
                        id
                        url
                        altText
                        width
                        height
                    }
                }
                variants(first: 250) {
                    nodes {
                        id
                        legacyResourceId
                        title
                        sku
                        barcode
                        price
                        compareAtPrice
                        position
                        selectedOptions {
                            name
                            value
                        }
                        media(first: 10) {
                            nodes {
                                id
                                alt
                                mediaContentType
                                preview {
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                                ... on MediaImage {
                                    id
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                            }
                        }
                        image {
                            id
                            url
                        }
                        inventoryQuantity
                        inventoryItem {
                            id
                            legacyResourceId
                            inventoryLevels(first: 50) {
                                nodes {
                                    location {
                                        id
                                        legacyResourceId
                                    }
                                    quantities(names: ["available", "on_hand", "committed"]) {
                                        name
                                        quantity
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->graphql($query, ['id' => $productGid]);

        if (!empty($response['error']) || !empty($response['errors'])) {
            $errorMsg = $response['message'] ?? (is_array($response['errors'] ?? null) ? json_encode($response['errors']) : 'Failed to fetch product via GraphQL.');
            Log::error('Shopify GraphQL getProductForView Error', [
                'shop' => $this->shop,
                'product_id' => $productId,
                'response' => $response,
            ]);
            return null;
        }

        $node = data_get($response, 'data.product');
        if (!$node) {
            return null;
        }

        return $this->normalizeProductNode($node, $locationId);
    }

    /**
     * Normalize GraphQL product node into standard legacy array structure.
     *
     * @param array $node
     * @param int|string|null $locationId
     * @return array
     */
    public function normalizeProductNode(array $node, $locationId = null): array
    {
        $pGid = $node['id'] ?? '';
        $pNumericId = $node['legacyResourceId'] ?? (str_contains((string) $pGid, 'gid://shopify/Product/') ? substr($pGid, strrpos($pGid, '/') + 1) : $pGid);
        $pNumericId = is_numeric($pNumericId) ? (int) $pNumericId : $pNumericId;

        // Options
        $options = [];
        foreach ($node['options'] ?? [] as $opt) {
            $optGid = $opt['id'] ?? '';
            $optId = is_numeric($optGid) ? (int) $optGid : (str_contains((string)$optGid, '/') ? (int) substr($optGid, strrpos($optGid, '/') + 1) : $optGid);
            $options[] = [
                'id' => $optId,
                'product_id' => $pNumericId,
                'name' => $opt['name'] ?? '',
                'position' => $opt['position'] ?? 1,
                'values' => $opt['values'] ?? [],
            ];
        }

        // Build product media lookup map
        // Keyed by: GID, numeric ID, URL
        $mediaLookup = [];
        $images = [];
        $imgPos = 1;

        $mediaNodes = data_get($node, 'media.nodes', []);
        foreach ($mediaNodes as $media) {
            $mGid = $media['id'] ?? '';
            $mNumericId = is_numeric($mGid) ? (int)$mGid : (str_contains((string)$mGid, '/') ? (int)substr($mGid, strrpos($mGid, '/') + 1) : $mGid);
            $imgUrl = data_get($media, 'image.url') ?? data_get($media, 'preview.image.url');
            $altText = data_get($media, 'image.altText') ?? ($media['alt'] ?? null);
            $width = data_get($media, 'image.width') ?? data_get($media, 'preview.image.width');
            $height = data_get($media, 'image.height') ?? data_get($media, 'preview.image.height');

            $mediaObj = [
                'id' => $mNumericId,
                'product_id' => $pNumericId,
                'position' => $imgPos++,
                'src' => $imgUrl,
                'url' => $imgUrl,
                'alt' => $altText,
                'width' => $width,
                'height' => $height,
                'media_id' => $mNumericId,
                'admin_graphql_api_id' => $mGid,
            ];

            if (!empty($imgUrl)) {
                $images[] = $mediaObj;
            }

            if (!empty($mGid)) {
                $mediaLookup[$mGid] = $mediaObj;
            }
            if (!empty($mNumericId)) {
                $mediaLookup[(string)$mNumericId] = $mediaObj;
                $mediaLookup[(int)$mNumericId] = $mediaObj;
            }
            if (!empty($imgUrl)) {
                $mediaLookup[$imgUrl] = $mediaObj;
                $cleanUrl = strtok($imgUrl, '?');
                if ($cleanUrl && $cleanUrl !== $imgUrl) {
                    $mediaLookup[$cleanUrl] = $mediaObj;
                }
            }
        }

        // Also check legacy images.nodes if present
        $imgNodes = data_get($node, 'images.nodes', []);
        foreach ($imgNodes as $img) {
            $imgGid = $img['id'] ?? '';
            $imgNumericId = is_numeric($imgGid) ? (int)$imgGid : (str_contains((string)$imgGid, '/') ? (int)substr($imgGid, strrpos($imgGid, '/') + 1) : $imgGid);
            $imgUrl = $img['url'] ?? '';
            $altText = $img['altText'] ?? null;
            $width = $img['width'] ?? null;
            $height = $img['height'] ?? null;

            $imgObj = [
                'id' => $imgNumericId,
                'product_id' => $pNumericId,
                'position' => $imgPos++,
                'src' => $imgUrl,
                'url' => $imgUrl,
                'alt' => $altText,
                'width' => $width,
                'height' => $height,
                'admin_graphql_api_id' => $imgGid,
            ];

            if (!empty($imgUrl) && empty($mediaLookup[$imgUrl])) {
                $images[] = $imgObj;
            }
            if (!empty($imgGid) && !isset($mediaLookup[$imgGid])) {
                $mediaLookup[$imgGid] = $imgObj;
            }
            if (!empty($imgNumericId) && !isset($mediaLookup[(string)$imgNumericId])) {
                $mediaLookup[(string)$imgNumericId] = $imgObj;
                $mediaLookup[(int)$imgNumericId] = $imgObj;
            }
            if (!empty($imgUrl) && !isset($mediaLookup[$imgUrl])) {
                $mediaLookup[$imgUrl] = $imgObj;
            }
        }

        $featUrl = data_get($node, 'featuredImage.url');
        $featuredImage = $featUrl ? [
            'id' => data_get($node, 'featuredImage.id'),
            'src' => $featUrl,
            'url' => $featUrl,
            'alt' => data_get($node, 'featuredImage.altText'),
        ] : (!empty($images) ? $images[0] : null);

        // Variants
        $variants = [];
        $variantNodes = data_get($node, 'variants.nodes', []);
        $vPos = 1;
        foreach ($variantNodes as $v) {
            $vGid = $v['id'] ?? '';
            $vNumericId = $v['legacyResourceId'] ?? (str_contains((string)$vGid, 'gid://shopify/ProductVariant/') ? substr($vGid, strrpos($vGid, '/') + 1) : $vGid);
            $vNumericId = is_numeric($vNumericId) ? (int) $vNumericId : $vNumericId;

            $invGid = data_get($v, 'inventoryItem.id', '');
            $invNumericId = data_get($v, 'inventoryItem.legacyResourceId') ?? (str_contains((string)$invGid, 'gid://shopify/InventoryItem/') ? substr($invGid, strrpos($invGid, '/') + 1) : $invGid);
            $invNumericId = is_numeric($invNumericId) ? (int) $invNumericId : $invNumericId;

            $selectedOptions = $v['selectedOptions'] ?? [];
            $option1 = $selectedOptions[0]['value'] ?? null;
            $option2 = $selectedOptions[1]['value'] ?? null;
            $option3 = $selectedOptions[2]['value'] ?? null;

            // Location-specific inventory resolution
            $inventoryQuantity = isset($v['inventoryQuantity']) ? (int) $v['inventoryQuantity'] : 0;

            if ($locationId !== null) {
                $levels = data_get($v, 'inventoryItem.inventoryLevels.nodes', []);
                $levelMatched = false;
                foreach ($levels as $level) {
                    $locGid = data_get($level, 'location.id', '');
                    $locNumeric = data_get($level, 'location.legacyResourceId') ?? (str_contains((string)$locGid, 'gid://shopify/Location/') ? substr($locGid, strrpos($locGid, '/') + 1) : $locGid);
                    if ((string)$locNumeric === (string)$locationId || (string)$locGid === "gid://shopify/Location/{$locationId}") {
                        $levelMatched = true;
                        $available = null;
                        foreach ($level['quantities'] ?? [] as $q) {
                            if (($q['name'] ?? '') === 'available') {
                                $available = isset($q['quantity']) ? (int) $q['quantity'] : null;
                                break;
                            }
                        }
                        $inventoryQuantity = $available ?? 0;
                        break;
                    }
                }
                if (!$levelMatched && !empty($levels)) {
                    $inventoryQuantity = 0;
                }
            }

            // Variant media / image resolution
            $vMediaNodes = data_get($v, 'media.nodes', []);
            $resolvedVariantImg = null;
            $vMediaGid = null;
            $vMediaNumericId = null;

            if (!empty($vMediaNodes)) {
                $firstMedia = $vMediaNodes[0];
                $vMediaGid = $firstMedia['id'] ?? null;
                $vMediaNumericId = $vMediaGid ? (is_numeric($vMediaGid) ? (int)$vMediaGid : (str_contains((string)$vMediaGid, '/') ? (int)substr($vMediaGid, strrpos($vMediaGid, '/') + 1) : $vMediaGid)) : null;
                $vMediaUrl = data_get($firstMedia, 'image.url') ?? data_get($firstMedia, 'preview.image.url');
                if ($vMediaUrl) {
                    $resolvedVariantImg = [
                        'id' => $vMediaNumericId,
                        'src' => $vMediaUrl,
                        'url' => $vMediaUrl,
                        'admin_graphql_api_id' => $vMediaGid,
                    ];
                }
            }

            // Fallback to variant.image if media.nodes was empty
            if (!$resolvedVariantImg) {
                $vImgGid = data_get($v, 'image.id');
                $vImgUrl = data_get($v, 'image.url');
                if ($vImgGid || $vImgUrl) {
                    $vImgNumericId = $vImgGid ? (is_numeric($vImgGid) ? (int)$vImgGid : (str_contains((string)$vImgGid, '/') ? (int)substr($vImgGid, strrpos($vImgGid, '/') + 1) : $vImgGid)) : null;
                    $resolvedVariantImg = [
                        'id' => $vImgNumericId,
                        'src' => $vImgUrl,
                        'url' => $vImgUrl,
                        'admin_graphql_api_id' => $vImgGid,
                    ];
                    $vMediaGid = $vMediaGid ?? $vImgGid;
                    $vMediaNumericId = $vMediaNumericId ?? $vImgNumericId;
                }
            }

            // If we have an image URL but no media ID, try product mediaLookup
            if ($resolvedVariantImg && !empty($resolvedVariantImg['url']) && empty($vMediaNumericId)) {
                if (isset($mediaLookup[$resolvedVariantImg['url']])) {
                    $lookupObj = $mediaLookup[$resolvedVariantImg['url']];
                    $vMediaNumericId = $lookupObj['id'] ?? null;
                    $vMediaGid = $lookupObj['admin_graphql_api_id'] ?? null;
                    $resolvedVariantImg['id'] = $vMediaNumericId;
                }
            }

            $vFinalImgId = $resolvedVariantImg['id'] ?? $vMediaNumericId;
            $vFinalImgSrc = $resolvedVariantImg['src'] ?? null;
            $vFinalMediaId = $vMediaGid ? (is_numeric($vMediaGid) ? (int)$vMediaGid : (str_contains((string)$vMediaGid, '/') ? (int)substr($vMediaGid, strrpos($vMediaGid, '/') + 1) : $vMediaGid)) : $vFinalImgId;

            $variants[] = [
                'id' => $vNumericId,
                'product_id' => $pNumericId,
                'title' => $v['title'] ?? '',
                'price' => (string) ($v['price'] ?? '0.00'),
                'sku' => $v['sku'] ?? '',
                'position' => $v['position'] ?? $vPos++,
                'inventory_item_id' => $invNumericId,
                'inventory_quantity' => $inventoryQuantity,
                'option1' => $option1,
                'option2' => $option2,
                'option3' => $option3,
                'barcode' => $v['barcode'] ?? null,
                'compare_at_price' => $v['compareAtPrice'] ?? null,
                'image_id' => $vFinalImgId,
                'image' => $resolvedVariantImg ? [
                    'id' => $vFinalImgId,
                    'src' => $vFinalImgSrc,
                    'url' => $vFinalImgSrc,
                ] : null,
                'image_src' => $vFinalImgSrc,
                'media_id' => $vFinalMediaId,
                'admin_graphql_api_id' => $vGid,
            ];
        }

        $statusRaw = $node['status'] ?? 'draft';
        $tagsRaw = $node['tags'] ?? [];

        return [
            'id' => $pNumericId,
            'title' => $node['title'] ?? '',
            'handle' => $node['handle'] ?? '',
            'body' => $node['descriptionHtml'] ?? '',
            'body_html' => $node['descriptionHtml'] ?? '',
            'vendor' => $node['vendor'] ?? '',
            'product_type' => $node['productType'] ?? '',
            'status' => strtolower((string) $statusRaw),
            'tags' => is_array($tagsRaw) ? implode(', ', $tagsRaw) : ($tagsRaw ?? ''),
            'created_at' => $node['createdAt'] ?? null,
            'updated_at' => $node['updatedAt'] ?? null,
            'image' => $featuredImage,
            'images' => $images,
            'options' => $options,
            'variants' => $variants,
            'metafields' => [],
            'admin_graphql_api_id' => $pGid,
        ];
    }

    /**
     * Create a product on Shopify using GraphQL productSet mutation (API 2026-07).
     *
     * @param Shop|null $shop
     * @param array $payload
     * @param int|string|null $locationId
     * @return array
     */
    public function createProduct(?Shop $shop, array $payload, $locationId = null): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        if (empty($this->shop) || empty($this->token)) {
            return [
                'success' => false,
                'error' => 'Missing shop domain or access token.',
            ];
        }

        $title = $payload['title'] ?? '';
        if (empty($title)) {
            return [
                'success' => false,
                'error' => 'Product title is required.',
            ];
        }

        $statusRaw = strtoupper((string) ($payload['status'] ?? 'DRAFT'));
        $allowedStatuses = ['ACTIVE', 'DRAFT', 'ARCHIVED'];
        $status = in_array($statusRaw, $allowedStatuses) ? $statusRaw : 'DRAFT';

        $tags = [];
        if (!empty($payload['tags'])) {
            if (is_array($payload['tags'])) {
                $tags = array_values(array_filter(array_map('trim', $payload['tags'])));
            } else {
                $tags = array_values(array_filter(array_map('trim', explode(',', (string) $payload['tags']))));
            }
        }

        // Build productOptions
        $productOptions = [];
        $rawOptions = $payload['options'] ?? [];
        if (!empty($rawOptions)) {
            foreach ($rawOptions as $opt) {
                $optName = trim((string) ($opt['name'] ?? ''));
                if (empty($optName)) continue;
                $optValues = [];
                foreach ($opt['values'] ?? [] as $val) {
                    $valStr = trim((string) $val);
                    if ($valStr !== '') {
                        $optValues[] = ['name' => $valStr];
                    }
                }
                if (!empty($optValues)) {
                    $productOptions[] = [
                        'name' => $optName,
                        'values' => $optValues,
                    ];
                }
            }
        }

        // Build files / images with deduplication
        $files = [];
        $seenFiles = [];

        $addFile = function($src) use (&$files, &$seenFiles) {
            if (is_string($src) && filter_var($src, FILTER_VALIDATE_URL) && (str_starts_with($src, 'http://') || str_starts_with($src, 'https://'))) {
                if (!isset($seenFiles[$src])) {
                    $seenFiles[$src] = true;
                    $files[] = [
                        'originalSource' => $src,
                        'contentType' => 'IMAGE',
                    ];
                }
                return true;
            }
            return false;
        };

        $rawImages = $payload['images'] ?? [];
        foreach ($rawImages as $img) {
            $src = is_array($img) ? ($img['src'] ?? ($img['url'] ?? '')) : (string) $img;
            $addFile($src);
        }

        // Build variants
        $variants = [];
        $rawVariants = $payload['variants'] ?? [];
        foreach ($rawVariants as $v) {
            $price = isset($v['price']) ? (string) $v['price'] : '0.00';
            $sku = (string) ($v['sku'] ?? '');

            $variantInput = [
                'price' => $price,
                'sku' => $sku,
            ];

            if (!empty($v['barcode'])) {
                $variantInput['barcode'] = (string) $v['barcode'];
            }
            if (!empty($v['compare_at_price'])) {
                $variantInput['compareAtPrice'] = (string) $v['compare_at_price'];
            }

            // Option values
            $optionValues = [];
            if (!empty($productOptions)) {
                if (!empty($v['option1']) && isset($productOptions[0]['name'])) {
                    $optionValues[] = [
                        'optionName' => $productOptions[0]['name'],
                        'name' => trim((string) $v['option1']),
                    ];
                }
                if (!empty($v['option2']) && isset($productOptions[1]['name'])) {
                    $optionValues[] = [
                        'optionName' => $productOptions[1]['name'],
                        'name' => trim((string) $v['option2']),
                    ];
                }
                if (!empty($v['option3']) && isset($productOptions[2]['name'])) {
                    $optionValues[] = [
                        'optionName' => $productOptions[2]['name'],
                        'name' => trim((string) $v['option3']),
                    ];
                }
            }
            if (!empty($optionValues)) {
                $variantInput['optionValues'] = $optionValues;
            }

            // Inventory quantities
            $qty = isset($v['inventory_quantity']) ? (int) $v['inventory_quantity'] : (isset($v['qty']) ? (int) $v['qty'] : 0);
            if ($locationId !== null && $qty > 0) {
                $locGid = str_starts_with((string)$locationId, 'gid://shopify/Location/')
                    ? (string)$locationId
                    : "gid://shopify/Location/{$locationId}";
                $variantInput['inventoryQuantities'] = [
                    [
                        'locationId' => $locGid,
                        'name' => 'available',
                        'quantity' => $qty,
                    ],
                ];
            }

            // Variant file / image association
            $vImgUrl = null;
            $vImgId = null;

            if (!empty($v['file']) && is_array($v['file'])) {
                $variantInput['file'] = $v['file'];
                if (!empty($v['file']['originalSource'])) {
                    $addFile($v['file']['originalSource']);
                }
            } else {
                if (!empty($v['image'])) {
                    if (is_array($v['image'])) {
                        $vImgUrl = $v['image']['url'] ?? ($v['image']['src'] ?? null);
                        $vImgId = $v['image']['id'] ?? null;
                    } elseif (is_string($v['image'])) {
                        if (filter_var($v['image'], FILTER_VALIDATE_URL)) {
                            $vImgUrl = $v['image'];
                        } elseif (str_starts_with($v['image'], 'gid://shopify/') || is_numeric($v['image'])) {
                            $vImgId = $v['image'];
                        }
                    }
                }
                if (!$vImgUrl && !empty($v['image_src']) && filter_var($v['image_src'], FILTER_VALIDATE_URL)) {
                    $vImgUrl = $v['image_src'];
                }
                if (!$vImgId && !empty($v['image_id'])) {
                    $vImgId = $v['image_id'];
                }
                if (!$vImgId && !empty($v['media_id'])) {
                    $vImgId = $v['media_id'];
                }

                if ($vImgUrl && filter_var($vImgUrl, FILTER_VALIDATE_URL) && (str_starts_with($vImgUrl, 'http://') || str_starts_with($vImgUrl, 'https://'))) {
                    $addFile($vImgUrl);
                    $variantInput['file'] = [
                        'originalSource' => $vImgUrl,
                        'contentType' => 'IMAGE',
                    ];
                } elseif (!empty($vImgId)) {
                    $vGid = str_starts_with((string)$vImgId, 'gid://shopify/')
                        ? (string)$vImgId
                        : "gid://shopify/MediaImage/{$vImgId}";
                    $variantInput['file'] = [
                        'id' => $vGid,
                    ];
                }
            }

            $variants[] = $variantInput;
        }

        $input = [
            'title' => $title,
            'descriptionHtml' => $payload['body_html'] ?? ($payload['description'] ?? ''),
            'vendor' => $payload['vendor'] ?? '',
            'productType' => $payload['product_type'] ?? '',
            'status' => $status,
        ];

        if (!empty($tags)) {
            $input['tags'] = $tags;
        }
        if (!empty($productOptions)) {
            $input['productOptions'] = $productOptions;
        }
        if (!empty($variants)) {
            $input['variants'] = $variants;
        }
        if (!empty($files)) {
            $input['files'] = $files;
        }

        $query = '
            mutation ProductSet($input: ProductSetInput!, $synchronous: Boolean) {
                productSet(input: $input, synchronous: $synchronous) {
                    product {
                        id
                        legacyResourceId
                        title
                        handle
                        descriptionHtml
                        vendor
                        productType
                        status
                        tags
                        options {
                            id
                            name
                            values
                            position
                        }
                        media(first: 50) {
                            nodes {
                                id
                                alt
                                mediaContentType
                                preview {
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                                ... on MediaImage {
                                    id
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                            }
                        }
                        images(first: 50) {
                            nodes {
                                id
                                url
                                altText
                                width
                                height
                            }
                        }
                        variants(first: 250) {
                            nodes {
                                id
                                legacyResourceId
                                title
                                sku
                                barcode
                                price
                                compareAtPrice
                                position
                                selectedOptions {
                                    name
                                    value
                                }
                                media(first: 10) {
                                    nodes {
                                        id
                                        alt
                                        mediaContentType
                                        preview {
                                            image {
                                                id
                                                url
                                                altText
                                                width
                                                height
                                            }
                                        }
                                        ... on MediaImage {
                                            id
                                            image {
                                                id
                                                url
                                                altText
                                                width
                                                height
                                            }
                                        }
                                    }
                                }
                                image {
                                    id
                                    url
                                }
                                inventoryItem {
                                    id
                                    legacyResourceId
                                    inventoryLevels(first: 10) {
                                        nodes {
                                            location {
                                                id
                                                legacyResourceId
                                            }
                                            quantities(names: ["available"]) {
                                                name
                                                quantity
                                            }
                                        }
                                    }
                                }
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
        ';

        try {
            $res = $this->graphql($query, [
                'input' => $input,
                'synchronous' => true,
            ]);

            if (isset($res['errors']) && !empty($res['errors'])) {
                Log::error('Shopify GraphQL createProduct top-level errors', [
                    'shop' => $this->shop,
                    'errors' => $res['errors'],
                ]);
                return [
                    'success' => false,
                    'error' => $res['errors'][0]['message'] ?? 'GraphQL error occurred.',
                ];
            }

            $userErrors = data_get($res, 'data.productSet.userErrors', []);
            if (!empty($userErrors)) {
                Log::error('Shopify GraphQL createProduct userErrors', [
                    'shop' => $this->shop,
                    'userErrors' => $userErrors,
                ]);
                return [
                    'success' => false,
                    'error' => $userErrors[0]['message'] ?? 'Shopify validation error.',
                    'userErrors' => $userErrors,
                ];
            }

            $productNode = data_get($res, 'data.productSet.product');
            if (!$productNode) {
                return [
                    'success' => false,
                    'error' => 'Product was not returned by Shopify.',
                ];
            }

            $normalized = $this->normalizeProductNode($productNode, $locationId);

            return [
                'success' => true,
                'product' => $normalized,
            ];
        } catch (\Exception $e) {
            Log::error('Shopify GraphQL createProduct exception', [
                'shop' => $this->shop,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete files/images from Shopify using GraphQL fileDelete mutation (API 2026-07).
     *
     * @param Shop|null $shop
     * @param array $fileIds
     * @return array
     */
    public function deleteFiles(?Shop $shop, array $fileIds): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        if (empty($fileIds)) {
            return ['success' => true, 'deletedFileIds' => []];
        }

        $gids = [];
        foreach ($fileIds as $id) {
            if (str_starts_with((string)$id, 'gid://shopify/')) {
                $gids[] = (string)$id;
            } elseif (is_numeric($id)) {
                $gids[] = "gid://shopify/ProductImage/{$id}";
            }
        }

        if (empty($gids)) {
            return ['success' => true, 'deletedFileIds' => []];
        }

        $query = <<<'GRAPHQL'
        mutation FileDelete($fileIds: [ID!]!) {
            fileDelete(fileIds: $fileIds) {
                deletedFileIds
                userErrors {
                    field
                    message
                    code
                }
            }
        }
        GRAPHQL;

        $res = $this->graphql($query, ['fileIds' => $gids]);
        if (!empty($res['errors'])) {
            Log::error('Shopify GraphQL fileDelete errors', [
                'shop' => $this->shop,
                'errors' => $res['errors'],
            ]);
            return ['success' => false, 'error' => $res['errors'][0]['message'] ?? 'Failed to delete files'];
        }

        $deletedIds = data_get($res, 'data.fileDelete.deletedFileIds', []);
        return ['success' => true, 'deletedFileIds' => $deletedIds];
    }

    /**
     * Update an existing product on Shopify using GraphQL productSet mutation (API 2026-07).
     *
     * @param Shop|null $shop
     * @param int|string $productId
     * @param array $payload
     * @param int|string|null $locationId
     * @return array
     */
    public function updateProduct(?Shop $shop, $productId, array $payload, $locationId = null): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        if (empty($this->shop) || empty($this->token)) {
            return [
                'success' => false,
                'error' => 'Missing shop domain or access token.',
            ];
        }

        $pGid = str_starts_with((string)$productId, 'gid://shopify/Product/')
            ? (string)$productId
            : "gid://shopify/Product/{$productId}";

        // Handle image deletions if specified
        $deletedImages = $payload['deleted_images'] ?? [];
        if (is_string($deletedImages)) {
            $deletedImages = array_filter(array_map('trim', explode(',', $deletedImages)));
        }
        if (!empty($deletedImages)) {
            $this->deleteFiles($shop, (array) $deletedImages);
        }

        // Fetch current authoritative Shopify product to preserve untouched variants (anti-deletion protocol)
        $existingProduct = $this->getProductForView($shop, $productId, $locationId);
        $existingVariants = $existingProduct['variants'] ?? [];
        $existingOptions = $existingProduct['options'] ?? [];
        $existingImages = $existingProduct['images'] ?? [];

        // Build productOptions
        $productOptions = [];
        $rawOptions = $payload['options'] ?? [];
        if (!empty($rawOptions)) {
            foreach ($rawOptions as $opt) {
                $optName = trim((string) ($opt['name'] ?? ''));
                if (empty($optName)) continue;
                $optValues = [];
                foreach ($opt['values'] ?? [] as $val) {
                    $valStr = trim((string) $val);
                    if ($valStr !== '') {
                        $optValues[] = ['name' => $valStr];
                    }
                }
                if (!empty($optValues)) {
                    $productOptions[] = [
                        'name' => $optName,
                        'values' => $optValues,
                    ];
                }
            }
        } elseif (!empty($existingOptions)) {
            foreach ($existingOptions as $opt) {
                $optName = trim((string) ($opt['name'] ?? ''));
                if (empty($optName)) continue;
                $optValues = [];
                foreach ($opt['values'] ?? [] as $val) {
                    $valStr = trim((string) $val);
                    if ($valStr !== '') {
                        $optValues[] = ['name' => $valStr];
                    }
                }
                if (!empty($optValues)) {
                    $productOptions[] = [
                        'name' => $optName,
                        'values' => $optValues,
                    ];
                }
            }
        }

        // Build variants map from submitted payload
        $submittedVariants = $payload['variants'] ?? [];
        $submittedById = [];
        $newVariants = [];

        foreach ($submittedVariants as $v) {
            $vId = $v['id'] ?? ($v['variant_id'] ?? null);
            if (!empty($vId)) {
                $numericVId = is_numeric($vId) ? (int)$vId : (str_contains((string)$vId, '/') ? (int)substr($vId, strrpos($vId, '/') + 1) : $vId);
                $submittedById[$numericVId] = $v;
            } else {
                $newVariants[] = $v;
            }
        }

        $finalVariants = [];

        // Build files / images (excluding any deleted images by ID or URL)
        $deletedUrls = [];
        foreach ($deletedImages as $del) {
            $delStr = (string) $del;
            if (filter_var($delStr, FILTER_VALIDATE_URL)) {
                $deletedUrls[] = $delStr;
            } else {
                $delNumeric = is_numeric($delStr) ? (int)$delStr : (str_contains($delStr, '/') ? (int)substr($delStr, strrpos($delStr, '/') + 1) : $delStr);
                foreach ($existingImages as $existImg) {
                    $existId = $existImg['id'] ?? null;
                    if ($existId == $delNumeric || $existId == $delStr) {
                        if (!empty($existImg['src'])) {
                            $deletedUrls[] = $existImg['src'];
                        }
                    }
                }
            }
        }

        $files = [];
        $seenFiles = [];

        $addFile = function($src) use (&$files, &$seenFiles, $deletedUrls) {
            if (is_string($src) && filter_var($src, FILTER_VALIDATE_URL) && (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) && !in_array($src, $deletedUrls)) {
                if (!isset($seenFiles[$src])) {
                    $seenFiles[$src] = true;
                    $files[] = [
                        'originalSource' => $src,
                        'contentType' => 'IMAGE',
                    ];
                }
                return true;
            }
            return false;
        };

        $rawImages = $payload['images'] ?? [];
        foreach ($rawImages as $img) {
            $src = is_array($img) ? ($img['src'] ?? ($img['url'] ?? '')) : (string) $img;
            $addFile($src);
        }

        $resolveVariantFile = function(array $vData, ?array $existV = null) use (&$addFile) {
            if (!empty($vData['file']) && is_array($vData['file'])) {
                if (!empty($vData['file']['originalSource'])) {
                    $addFile($vData['file']['originalSource']);
                }
                return $vData['file'];
            }

            $vImgUrl = null;
            $vImgId = null;

            if (!empty($vData['image'])) {
                if (is_array($vData['image'])) {
                    $vImgUrl = $vData['image']['url'] ?? ($vData['image']['src'] ?? null);
                    $vImgId = $vData['image']['id'] ?? null;
                } elseif (is_string($vData['image'])) {
                    if (filter_var($vData['image'], FILTER_VALIDATE_URL)) {
                        $vImgUrl = $vData['image'];
                    } elseif (str_starts_with($vData['image'], 'gid://shopify/') || is_numeric($vData['image'])) {
                        $vImgId = $vData['image'];
                    }
                }
            }
            if (!$vImgUrl && !empty($vData['image_src']) && filter_var($vData['image_src'], FILTER_VALIDATE_URL)) {
                $vImgUrl = $vData['image_src'];
            }
            if (!$vImgId && !empty($vData['image_id'])) {
                $vImgId = $vData['image_id'];
            }
            if (!$vImgId && !empty($vData['media_id'])) {
                $vImgId = $vData['media_id'];
            }
            if (!$vImgId && !empty($vData['existing_image_id'])) {
                $vImgId = $vData['existing_image_id'];
            }

            // New image URL supplied
            if ($vImgUrl && filter_var($vImgUrl, FILTER_VALIDATE_URL) && (str_starts_with($vImgUrl, 'http://') || str_starts_with($vImgUrl, 'https://'))) {
                $addFile($vImgUrl);
                return [
                    'originalSource' => $vImgUrl,
                    'contentType' => 'IMAGE',
                ];
            }

            // Explicit image / media ID supplied
            if (!empty($vImgId)) {
                $gid = str_starts_with((string)$vImgId, 'gid://shopify/')
                    ? (string)$vImgId
                    : "gid://shopify/MediaImage/{$vImgId}";
                return [
                    'id' => $gid,
                ];
            }

            // Untouched/unmodified image: preserve existing image from authoritative Shopify variant
            if ($existV) {
                $existMediaId = $existV['media_id'] ?? ($existV['image_id'] ?? ($existV['image']['id'] ?? null));
                if (!empty($existMediaId)) {
                    $gid = str_starts_with((string)$existMediaId, 'gid://shopify/')
                        ? (string)$existMediaId
                        : "gid://shopify/MediaImage/{$existMediaId}";
                    return [
                        'id' => $gid,
                    ];
                }
            }

            return null;
        };

        // 1. Process all existing variants from Shopify (update modified, preserve unmodified)
        foreach ($existingVariants as $existV) {
            $existId = $existV['id'];
            $vGid = str_starts_with((string)$existId, 'gid://shopify/ProductVariant/')
                ? (string)$existId
                : "gid://shopify/ProductVariant/{$existId}";

            if (isset($submittedById[$existId])) {
                // Update existing variant with submitted values
                $formV = $submittedById[$existId];
                $price = isset($formV['price']) ? (string)$formV['price'] : (string)($existV['price'] ?? '0.00');
                $sku = isset($formV['sku']) ? (string)$formV['sku'] : (string)($existV['sku'] ?? '');

                $variantInput = [
                    'id' => $vGid,
                    'price' => $price,
                    'sku' => $sku,
                ];

                if (isset($formV['barcode'])) {
                    $variantInput['barcode'] = (string)$formV['barcode'];
                } elseif (!empty($existV['barcode'])) {
                    $variantInput['barcode'] = (string)$existV['barcode'];
                }

                if (isset($formV['compare_at_price'])) {
                    $variantInput['compareAtPrice'] = (string)$formV['compare_at_price'];
                } elseif (!empty($existV['compare_at_price'])) {
                    $variantInput['compareAtPrice'] = (string)$existV['compare_at_price'];
                }

                // Option values
                $optVals = [];
                if (!empty($productOptions)) {
                    $opt1 = $formV['option1'] ?? ($existV['option1'] ?? null);
                    $opt2 = $formV['option2'] ?? ($existV['option2'] ?? null);
                    $opt3 = $formV['option3'] ?? ($existV['option3'] ?? null);

                    if (!empty($opt1) && isset($productOptions[0]['name'])) {
                        $optVals[] = ['optionName' => $productOptions[0]['name'], 'name' => trim((string)$opt1)];
                    }
                    if (!empty($opt2) && isset($productOptions[1]['name'])) {
                        $optVals[] = ['optionName' => $productOptions[1]['name'], 'name' => trim((string)$opt2)];
                    }
                    if (!empty($opt3) && isset($productOptions[2]['name'])) {
                        $optVals[] = ['optionName' => $productOptions[2]['name'], 'name' => trim((string)$opt3)];
                    }
                }
                if (!empty($optVals)) {
                    $variantInput['optionValues'] = $optVals;
                }

                // Inventory quantity
                if (isset($formV['inventory_quantity']) || isset($formV['qty'])) {
                    $qty = (int) ($formV['inventory_quantity'] ?? $formV['qty']);
                    if ($locationId !== null) {
                        $locGid = str_starts_with((string)$locationId, 'gid://shopify/Location/')
                            ? (string)$locationId
                            : "gid://shopify/Location/{$locationId}";
                        $variantInput['inventoryQuantities'] = [
                            [
                                'locationId' => $locGid,
                                'name' => 'available',
                                'quantity' => $qty,
                            ],
                        ];
                    }
                }

                $resolvedFile = $resolveVariantFile($formV, $existV);
                if ($resolvedFile) {
                    $variantInput['file'] = $resolvedFile;
                }

                $finalVariants[] = $variantInput;
                unset($submittedById[$existId]);
            } else {
                // Untouched existing variant: PRESERVE verbatim to avoid accidental deletion
                $variantInput = [
                    'id' => $vGid,
                    'price' => (string)($existV['price'] ?? '0.00'),
                    'sku' => (string)($existV['sku'] ?? ''),
                ];
                if (!empty($existV['barcode'])) {
                    $variantInput['barcode'] = (string)$existV['barcode'];
                }
                if (!empty($existV['compare_at_price'])) {
                    $variantInput['compareAtPrice'] = (string)$existV['compare_at_price'];
                }
                $optVals = [];
                if (!empty($productOptions)) {
                    if (!empty($existV['option1']) && isset($productOptions[0]['name'])) {
                        $optVals[] = ['optionName' => $productOptions[0]['name'], 'name' => trim((string)$existV['option1'])];
                    }
                    if (!empty($existV['option2']) && isset($productOptions[1]['name'])) {
                        $optVals[] = ['optionName' => $productOptions[1]['name'], 'name' => trim((string)$existV['option2'])];
                    }
                    if (!empty($existV['option3']) && isset($productOptions[2]['name'])) {
                        $optVals[] = ['optionName' => $productOptions[2]['name'], 'name' => trim((string)$existV['option3'])];
                    }
                }
                if (!empty($optVals)) {
                    $variantInput['optionValues'] = $optVals;
                }

                $resolvedFile = $resolveVariantFile([], $existV);
                if ($resolvedFile) {
                    $variantInput['file'] = $resolvedFile;
                }

                $finalVariants[] = $variantInput;
            }
        }

        // 2. Process newly created variants (from submitted payload)
        foreach ($newVariants as $nV) {
            $price = isset($nV['price']) ? (string)$nV['price'] : '0.00';
            $sku = (string)($nV['sku'] ?? '');

            $variantInput = [
                'price' => $price,
                'sku' => $sku,
            ];

            if (!empty($nV['barcode'])) {
                $variantInput['barcode'] = (string)$nV['barcode'];
            }
            if (!empty($nV['compare_at_price'])) {
                $variantInput['compareAtPrice'] = (string)$nV['compare_at_price'];
            }

            $optVals = [];
            if (!empty($productOptions)) {
                if (!empty($nV['option1']) && isset($productOptions[0]['name'])) {
                    $optVals[] = ['optionName' => $productOptions[0]['name'], 'name' => trim((string)$nV['option1'])];
                }
                if (!empty($nV['option2']) && isset($productOptions[1]['name'])) {
                    $optVals[] = ['optionName' => $productOptions[1]['name'], 'name' => trim((string)$nV['option2'])];
                }
                if (!empty($nV['option3']) && isset($productOptions[2]['name'])) {
                    $optVals[] = ['optionName' => $productOptions[2]['name'], 'name' => trim((string)$nV['option3'])];
                }
            }
            if (!empty($optVals)) {
                $variantInput['optionValues'] = $optVals;
            }

            if (isset($nV['inventory_quantity']) || isset($nV['qty'])) {
                $qty = (int) ($nV['inventory_quantity'] ?? $nV['qty']);
                if ($locationId !== null) {
                    $locGid = str_starts_with((string)$locationId, 'gid://shopify/Location/')
                        ? (string)$locationId
                        : "gid://shopify/Location/{$locationId}";
                    $variantInput['inventoryQuantities'] = [
                        [
                            'locationId' => $locGid,
                            'name' => 'available',
                            'quantity' => $qty,
                        ],
                    ];
                }
            }

            $resolvedFile = $resolveVariantFile($nV, null);
            if ($resolvedFile) {
                $variantInput['file'] = $resolvedFile;
            }

            $finalVariants[] = $variantInput;
        }

        $input = [];
        if (isset($payload['title'])) {
            $input['title'] = $payload['title'];
        }
        if (isset($payload['body_html']) || isset($payload['description'])) {
            $input['descriptionHtml'] = $payload['body_html'] ?? $payload['description'];
        }
        if (isset($payload['vendor'])) {
            $input['vendor'] = $payload['vendor'];
        }
        if (isset($payload['product_type'])) {
            $input['productType'] = $payload['product_type'];
        }
        if (isset($payload['status'])) {
            $statusRaw = strtoupper((string)$payload['status']);
            $allowedStatuses = ['ACTIVE', 'DRAFT', 'ARCHIVED'];
            $input['status'] = in_array($statusRaw, $allowedStatuses) ? $statusRaw : 'DRAFT';
        }
        if (isset($payload['tags'])) {
            if (is_array($payload['tags'])) {
                $input['tags'] = array_values(array_filter(array_map('trim', $payload['tags'])));
            } else {
                $input['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) $payload['tags']))));
            }
        }
        if (!empty($productOptions)) {
            $input['productOptions'] = $productOptions;
        }
        if (!empty($finalVariants)) {
            $input['variants'] = $finalVariants;
        }
        if (!empty($files)) {
            $input['files'] = $files;
        }

        $query = '
            mutation ProductSet($input: ProductSetInput!, $synchronous: Boolean, $identifier: ProductSetIdentifiers) {
                productSet(input: $input, synchronous: $synchronous, identifier: $identifier) {
                    product {
                        id
                        legacyResourceId
                        title
                        handle
                        descriptionHtml
                        vendor
                        productType
                        status
                        tags
                        options {
                            id
                            name
                            values
                            position
                        }
                        media(first: 50) {
                            nodes {
                                id
                                alt
                                mediaContentType
                                preview {
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                                ... on MediaImage {
                                    id
                                    image {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                            }
                        }
                        images(first: 50) {
                            nodes {
                                id
                                url
                                altText
                                width
                                height
                            }
                        }
                        variants(first: 250) {
                            nodes {
                                id
                                legacyResourceId
                                title
                                sku
                                barcode
                                price
                                compareAtPrice
                                position
                                selectedOptions {
                                    name
                                    value
                                }
                                media(first: 10) {
                                    nodes {
                                        id
                                        alt
                                        mediaContentType
                                        preview {
                                            image {
                                                id
                                                url
                                                altText
                                                width
                                                height
                                            }
                                        }
                                        ... on MediaImage {
                                            id
                                            image {
                                                id
                                                url
                                                altText
                                                width
                                                height
                                            }
                                        }
                                    }
                                }
                                image {
                                    id
                                    url
                                }
                                inventoryItem {
                                    id
                                    legacyResourceId
                                    inventoryLevels(first: 10) {
                                        nodes {
                                            location {
                                                id
                                                legacyResourceId
                                            }
                                            quantities(names: ["available"]) {
                                                name
                                                quantity
                                            }
                                        }
                                    }
                                }
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
        ';

        try {
            $res = $this->graphql($query, [
                'input' => $input,
                'synchronous' => true,
                'identifier' => [
                    'id' => $pGid,
                ],
            ]);

            if (isset($res['errors']) && !empty($res['errors'])) {
                Log::error('Shopify GraphQL updateProduct top-level errors', [
                    'shop' => $this->shop,
                    'product_id' => $productId,
                    'errors' => $res['errors'],
                ]);
                return [
                    'success' => false,
                    'error' => $res['errors'][0]['message'] ?? 'GraphQL error occurred.',
                ];
            }

            $userErrors = data_get($res, 'data.productSet.userErrors', []);
            if (!empty($userErrors)) {
                Log::error('Shopify GraphQL updateProduct userErrors', [
                    'shop' => $this->shop,
                    'product_id' => $productId,
                    'userErrors' => $userErrors,
                ]);
                return [
                    'success' => false,
                    'error' => $userErrors[0]['message'] ?? 'Shopify validation error.',
                    'userErrors' => $userErrors,
                ];
            }

            $productNode = data_get($res, 'data.productSet.product');
            if (!$productNode) {
                return [
                    'success' => false,
                    'error' => 'Product was not returned by Shopify.',
                ];
            }

            $normalized = $this->normalizeProductNode($productNode, $locationId);

            return [
                'success' => true,
                'product' => $normalized,
            ];
        } catch (\Exception $e) {
            Log::error('Shopify GraphQL updateProduct exception', [
                'shop' => $this->shop,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a product from Shopify using GraphQL productDelete mutation (API 2026-07).
     *
     * @param Shop|null $shop
     * @param int|string $productId
     * @return array
     */
    public function deleteProduct(?Shop $shop = null, $productId = null): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        if (empty($this->shop) || empty($this->token)) {
            return [
                'success' => false,
                'error' => 'Missing shop domain or access token.',
            ];
        }

        if (blank($productId)) {
            return [
                'success' => false,
                'error' => 'Product ID is required.',
            ];
        }

        $productGid = str_starts_with((string) $productId, 'gid://shopify/Product/')
            ? (string) $productId
            : "gid://shopify/Product/{$productId}";

        $query = <<<'GRAPHQL'
        mutation ProductDelete($input: ProductDeleteInput!) {
            productDelete(input: $input) {
                deletedProductId
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        try {
            $res = $this->graphql($query, [
                'input' => [
                    'id' => $productGid,
                ],
            ]);

            if (isset($res['errors']) && !empty($res['errors'])) {
                Log::error('Shopify GraphQL deleteProduct top-level errors', [
                    'shop' => $this->shop,
                    'product_id' => $productId,
                    'errors' => $res['errors'],
                ]);
                return [
                    'success' => false,
                    'error' => $res['errors'][0]['message'] ?? 'GraphQL error occurred.',
                ];
            }

            $userErrors = data_get($res, 'data.productDelete.userErrors', []);
            if (!empty($userErrors)) {
                Log::error('Shopify GraphQL deleteProduct userErrors', [
                    'shop' => $this->shop,
                    'product_id' => $productId,
                    'userErrors' => $userErrors,
                ]);
                return [
                    'success' => false,
                    'error' => $userErrors[0]['message'] ?? 'Shopify error occurred.',
                    'userErrors' => $userErrors,
                ];
            }

            $deletedProductId = data_get($res, 'data.productDelete.deletedProductId');

            return [
                'success' => true,
                'deletedProductId' => $deletedProductId,
            ];
        } catch (\Exception $e) {
            Log::error('Shopify GraphQL deleteProduct exception', [
                'shop' => $this->shop,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synchronize a single product from Shopify to local DB via GraphQL (API 2026-07).
     *
     * @param Shop|null $shop
     * @param int|string $productId
     * @param int|string|null $locationId
     * @return array
     */
    public function singleProductSync(?Shop $shop = null, $productId = null, $locationId = null): array
    {
        if ($shop instanceof Shop) {
            $this->shop = $shop->shop;
            $this->token = $shop->access_token;
        }

        if (empty($this->shop) || empty($this->token)) {
            return [
                'success' => false,
                'error' => 'Missing shop domain or access token.',
            ];
        }

        if (blank($productId)) {
            return [
                'success' => false,
                'error' => 'Product ID is required.',
            ];
        }

        // Fetch normalized product data using GraphQL
        $normalizedProduct = $this->getProductForView($shop, $productId, $locationId);

        if (!$normalizedProduct) {
            return [
                'success' => false,
                'error' => 'Product not found on Shopify.',
            ];
        }

        // Persist/update to local DB
        if ($shop instanceof Shop) {
            $dbProduct = Product::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'shopify_id' => $normalizedProduct['id'],
                ],
                [
                    'title' => $normalizedProduct['title'] ?? '',
                    'description' => html_to_plain_text($normalizedProduct['body_html'] ?? ''),
                    'vendor' => $normalizedProduct['vendor'] ?? '',
                    'product_type' => $normalizedProduct['product_type'] ?? '',
                    'status' => $normalizedProduct['status'] ?? 'draft',
                    'tags' => $normalizedProduct['tags'] ?? '',
                    'price' => $normalizedProduct['variants'][0]['price'] ?? 0.00,
                    'variants' => json_encode($normalizedProduct['variants'] ?? []),
                    'options' => json_encode($normalizedProduct['options'] ?? []),
                    'images' => json_encode($normalizedProduct['images'] ?? []),
                ]
            );

            // Rebuild/refresh active shop cache
            $cacheKey = "products_shop_{$shop->id}";
            $cachedProducts = Cache::get($cacheKey);
            if ($cachedProducts) {
                $updatedList = Product::where('shop_id', $shop->id)->latest()->get();
                Cache::put($cacheKey, $updatedList, now()->addMinutes(15));
            }

            return [
                'success' => true,
                'product' => $normalizedProduct,
                'db_product' => $dbProduct,
            ];
        }

        return [
            'success' => true,
            'product' => $normalizedProduct,
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
    /**
     * Set the available inventory quantity for an item at a specific location using GraphQL.
     *
     * @param mixed $param1 Shop or inventoryItemId
     * @param mixed $param2 inventoryItemId or locationId
     * @param mixed $param3 locationId or quantity
     * @param mixed $param4 quantity or Shop
     * @param int|null $changeFromQuantity Optional authoritative live baseline quantity for OCC
     * @param string|null $idempotencyKey Deterministic idempotency key for this operation/retry
     * @return array
     */
    public function setInventoryQuantity(
        $param1,
        $param2 = null,
        $param3 = null,
        $param4 = null,
        ?int $changeFromQuantity = null,
        ?string $idempotencyKey = null,
        array $context = []
    ): array {
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
        mutation InventorySetQuantities($input: InventorySetQuantitiesInput!, $idempotencyKey: String!) {
            inventorySetQuantities(input: $input) @idempotent(key: $idempotencyKey) {
                inventoryAdjustmentGroup {
                    reason
                    changes {
                        name
                        delta
                        quantityAfterChange
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

        $quantityInput = [
            'inventoryItemId' => $itemGid,
            'locationId' => $locGid,
            'quantity' => (int) $quantity,
        ];

        if ($changeFromQuantity !== null) {
            $quantityInput['changeFromQuantity'] = (int) $changeFromQuantity;
        }

        $resolvedIdempotencyKey = !blank($idempotencyKey)
            ? (string) $idempotencyKey
            : (string) \Illuminate\Support\Str::uuid();

        $variables = [
            'input' => [
                'name' => 'available',
                'reason' => 'cycle_count_available',
                'quantities' => [
                    $quantityInput,
                ],
            ],
            'idempotencyKey' => $resolvedIdempotencyKey,
        ];

        Log::info('Shopify GraphQL inventorySetQuantities Request', [
            'api_version' => $this->version,
            'mutation_name' => 'InventorySetQuantities',
            'operation_id' => $context['operation_id'] ?? null,
            'operation_uuid' => $context['operation_uuid'] ?? $resolvedIdempotencyKey,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'desired_quantity' => (int) $quantity,
            'baseline_quantity' => $changeFromQuantity,
            'final_graphql_variables' => $variables,
        ]);

        $response = $this->graphql($mutation, $variables);

        if (!empty($response['error']) || !empty($response['errors'])) {
            $errorMsg = $response['message'] ?? (is_array($response['errors'] ?? null) ? json_encode($response['errors']) : 'Failed to execute inventorySetQuantities mutation.');
            Log::error('Shopify GraphQL setInventoryQuantity Error', [
                'shop' => $this->shop,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'change_from_quantity' => $changeFromQuantity,
                'idempotency_key' => $resolvedIdempotencyKey,
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
            $firstCode = $userErrors[0]['code'] ?? null;
            Log::error('Shopify GraphQL setInventoryQuantity userErrors', [
                'shop' => $this->shop,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'change_from_quantity' => $changeFromQuantity,
                'idempotency_key' => $resolvedIdempotencyKey,
                'userErrors' => $userErrors,
            ]);
            return [
                'error' => true,
                'status' => 422,
                'code' => $firstCode,
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
