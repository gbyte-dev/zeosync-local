<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;
use App\Models\AdminSetting;
use SellingPartnerApi\Seller\ProductTypeDefinitionsV20200901\Requests\GetDefinitionsProductType;

class AmazonSchemaService
{
    public function getProductTypeDefinition(
        $shop,
        $productType
    ) {
        try {
            $connector =
                $this->getSchemaConnector($shop);
            $request =
                new GetDefinitionsProductType(
                    productType: $productType,
                    marketplaceIds: [
                        'ATVPDKIKX0DER'
                    ],
                    requirements: 'LISTING',
                    locale: 'en_US',
                    productTypeVersion: 'LATEST'
                );
            $response =
                $connector->send($request);
            $data =
                $response->json();
            if (
                isset(
                    $data['schema']['link']['resource']
                )
            ) {
                $schemaUrl =
                    $data['schema']['link']['resource'];
                $schemaJson =
                    file_get_contents($schemaUrl);
                $realSchema =
                    json_decode(
                        $schemaJson,
                        true
                    );
                $data['real_schema'] =
                    $realSchema;
            }
            Log::info('AMAZON SCHEMA RESPONSE', [
                'product_type' => $productType,
                'response_keys' =>
                array_keys($data),
                'real_schema_keys' =>
                array_keys(
                    $data['real_schema']
                        ?? []
                ),
                'total_properties' =>
                count(
                    $data['real_schema']['properties']
                        ?? []
                )
            ]);
            return $data;
        } catch (\Throwable $e) {
            Log::error('AMAZON SCHEMA ERROR', [
                'product_type' => $productType,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    private function resolveSchemaNode(array $node): array
    {
        if (isset($node['enum'])) {
            return $node;
        }
        if (isset($node['oneOf'][0])) {
            return $this->resolveSchemaNode(
                $node['oneOf'][0]
            );
        }
        if (isset($node['anyOf'][0])) {
            return $this->resolveSchemaNode(
                $node['anyOf'][0]
            );
        }
        if (isset($node['items'])) {
            return $this->resolveSchemaNode(
                $node['items']
            );
        }
        if (
            isset($node['properties']['value'])
        ) {
            return $this->resolveSchemaNode(
                $node['properties']['value']
            );
        }
        if (isset($node['properties'])) {
            $first =
                collect(
                    $node['properties']
                )->first();
            if ($first) {
                return $this->resolveSchemaNode(
                    $first
                );
            }
        }
        return $node;
    }
    public function extractFields($schema)
    {
        $groups =
            $schema['propertyGroups']
            ?? [];
        $properties =
            $schema['real_schema']['properties']
            ?? [];
        if (empty($properties)) {
            return [];
        }
        $fields = [];
        foreach ($groups as $group) {
            $propertyNames =
                $group['propertyNames']
                ?? [];
            foreach ($propertyNames as $propertyName) {
                $property =
                    $properties[$propertyName]
                    ?? [];
                if (empty($property)) {
                    continue;
                }
                $node =
                    $this->resolveSchemaNode(
                        $property
                    );
                $propertyType =
                    $node['type']
                    ?? 'string';
                // Skip complex objects
                if (
                    isset($property['properties'])
                    ||
                    (
                        isset($node['items']) &&
                        isset($node['items']['properties'])
                    )
                ) {
                    continue;
                }
                $fieldType = 'text';
                $options = [];
                // Select
                if (
                    isset($node['enum']) &&
                    is_array($node['enum'])
                ) {
                    $fieldType = 'select';
                    $options =
                        $node['enum'];
                }
                // Checkbox
                elseif (
                    $propertyType === 'boolean'
                ) {
                    $fieldType = 'checkbox';
                }
                // Image
                elseif (
                    str_contains(
                        strtolower($propertyName),
                        'image'
                    )
                    ||
                    str_contains(
                        strtolower($propertyName),
                        'media'
                    )
                ) {
                    $fieldType = 'image';
                }
                // Number
                elseif (
                    $propertyType === 'number'
                    ||
                    $propertyType === 'integer'
                ) {
                    $fieldType = 'number';
                }
                // Textarea
                elseif (
                    str_contains(
                        strtolower($propertyName),
                        'description'
                    )
                    ||
                    str_contains(
                        strtolower($propertyName),
                        'bullet'
                    )
                    ||
                    str_contains(
                        strtolower($propertyName),
                        'keyword'
                    )
                ) {
                    $fieldType = 'textarea';
                }
                // Default
                else {
                    $fieldType = 'text';
                }
                // Label
                $fieldLabel =
                    $property['title']
                    ?? ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $propertyName
                        )
                    );
                // Required
                $required =
                    in_array(
                        $propertyName,
                        $schema['real_schema']['required']
                            ?? []
                    );
                Log::info('FIELD DEBUG', [
                    'property' =>
                    $propertyName,
                    'type' =>
                    $fieldType,
                    'required' =>
                    $required
                ]);
                $fields[] = [
                    'key' => $propertyName,
                    'name' => $fieldLabel,
                    'label' => $fieldLabel,
                    'type' => $fieldType,
                    'required' => $required,
                    'options' => $options,
                    'group' => $group['title'] ?? 'General',
                    'schema' => $property
                ];
            }
        }
        return $fields;
    }
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
    private function getSchemaConnector($shop)
    {
        $creds = $this->getDbCredentials($shop);
        return SellingPartnerApi::seller(
            clientId: $creds['client_id'],
            clientSecret: $creds['client_secret'],
            refreshToken: $creds['refresh_token'],
            endpoint: Endpoint::NA,
        );
    }
}
