<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\Seller\ProductTypeDefinitionsV20200901\Requests\GetDefinitionsProductType;
use SellingPartnerApi\SellingPartnerApi;
use RuntimeException;
use Throwable;
use JsonException;

readonly class AmazonSchemaServiceV2
{
    /**
     * Fetch and cache the Amazon Product Type Definition schema.
     */
    public function getCachedSchema(
        object $shop,
        string $productType,
        array|string $marketplaceIds = 'ATVPDKIKX0DER',
        string $requirements = 'LISTING',
        string $requirementsEnforced = 'ENFORCED',
        string $version = 'LATEST',
        string $locale = 'en_US'
    ): array {
        try {
            $cacheKey = $this->buildCacheKey(
                $productType,
                $marketplaceIds,
                $requirements,
                $version,
                $locale
            );

            return Cache::remember(
                $cacheKey,
                now()->addDays((int) config('amazon.schema_cache_days', 7)),
                function () use (
                    $shop,
                    $productType,
                    $marketplaceIds,
                    $requirements,
                    $requirementsEnforced,
                    $version,
                    $locale,
                    $cacheKey
                ) {
                    Log::info('Schema cache miss - Fetching from Amazon API', [
                        'cache_key' => $cacheKey,
                        'product_type' => $productType,
                        'marketplace_ids' => $marketplaceIds,
                    ]);

                    $schemaData = $this->fetchProductTypeDefinition(
                        $shop,
                        $productType,
                        $marketplaceIds,
                        $requirements,
                        $requirementsEnforced,
                        $version,
                        $locale
                    );

                    if (isset($schemaData['schema']['link']['resource'])) {
                        $schemaData['real_schema'] = $this->downloadRealSchema(
                            $schemaData['schema']['link']['resource']
                        );
                    }

                    Log::info('Schema downloaded and stored in cache', [
                        'cache_key' => $cacheKey,
                        'product_type' => $productType,
                        'marketplace_ids' => $marketplaceIds,
                        'property_count' => isset($schemaData['real_schema']['properties'])
                            ? count($schemaData['real_schema']['properties'])
                            : null,
                    ]);

                    return $schemaData;
                }
            );
        } catch (Throwable $e) {
            Log::error('Amazon API failed', [
                'product_type' => $productType,
                'marketplace_ids' => $marketplaceIds,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a unique, normalized cache key based on query parameters.
     */
    private function buildCacheKey(
        string $productType,
        array|string $marketplaceIds,
        string $requirements,
        string $version,
        string $locale
    ): string {
        $marketplaces = is_array($marketplaceIds)
            ? implode('_', $marketplaceIds)
            : $marketplaceIds;

        $key = sprintf(
            'amazon_schema_%s_%s_%s_%s_%s',
            $productType,
            $marketplaces,
            $requirements,
            $version,
            $locale
        );

        return strtolower($key);
    }

    /**
     * Request the Product Type Definition from the Selling Partner API.
     */
    private function fetchProductTypeDefinition(
        object $shop,
        string $productType,
        array|string $marketplaceIds,
        string $requirements,
        string $requirementsEnforced,
        string $version,
        string $locale
    ): array {
        $connector = $this->getSchemaConnector($shop);

        $marketplacesArray = is_array($marketplaceIds)
            ? $marketplaceIds
            : [$marketplaceIds];

        $request = new GetDefinitionsProductType(
            productType: $productType, // Send the original casing to Amazon
            marketplaceIds: $marketplacesArray,
            requirements: $requirements,
            requirementsEnforced: $requirementsEnforced,
            locale: $locale,
            productTypeVersion: $version
        );

        return $connector->send($request)->json();
    }

    /**
     * Download, decode, and structurally validate the physical JSON schema file from Amazon's S3 link.
     *
     * @throws JsonException|RuntimeException
     */
    private function downloadRealSchema(string $url): array
    {
        Log::info('Downloading schema', ['url' => $url]);

        $schemaJson = file_get_contents($url);

        if ($schemaJson === false) {
            throw new RuntimeException('Failed to download real schema JSON from Amazon.');
        }

        $realSchema = json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR);

        if (
            !isset($realSchema['properties']) &&
            !isset($realSchema['allOf']) &&
            !isset($realSchema['anyOf']) &&
            !isset($realSchema['oneOf'])
        ) {
            throw new RuntimeException('Downloaded schema is invalid.');
        }

        return $realSchema;
    }

    /**
     * Extract API credentials from Admin settings and the Shop model.
     */
    private function getDbCredentials(object $shop): array
    {
        $config = AdminSetting::pluck('option_value', 'option_key');

        return [
            'client_id' => $config['production_client_id'] ?? null,
            'client_secret' => $config['production_client_secret'] ?? null,
            'refresh_token' => $shop->amazon_refresh_token ?? null,
            'seller_id' => $shop->amazon_seller_id ?? null,
        ];
    }

    /**
     * Initialize the SP-API Seller connector instance.
     */
    private function getSchemaConnector(object $shop): SellingPartnerApi
    {
        $creds = $this->getDbCredentials($shop);

        return SellingPartnerApi::seller(
            clientId: (string) $creds['client_id'],
            clientSecret: (string) $creds['client_secret'],
            refreshToken: (string) $creds['refresh_token'],
            endpoint: Endpoint::NA,
        );
    }
}
