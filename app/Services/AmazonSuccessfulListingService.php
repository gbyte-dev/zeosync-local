<?php

namespace App\Services;

use App\Models\AllProduct;

class AmazonSuccessfulListingService
{
    public const EXCLUDED_AUTOFILL_FIELDS = [
        // ASIN
        'asin',
        'merchant_suggested_asin',

        // External Product ID
        'externally_assigned_product_identifier',
        'external_product_id',
        'external_product_identifier',

        // Product Name / Item Name
        'item_name',
        'product_name',
        'title',

        // Product Description
        'product_description',
        'description',

        // SKU
        'sku',
        'seller_sku',
        'contribution_sku',

        // Model Number
        'model_number',
        'model_name',
        'item_model_number',
    ];

    public function findFor(AllProduct $product): ?AllProduct
    {
        return AllProduct::query()
            ->where('schema_id', $product->schema_id)
            ->where('status', 'ACCEPTED')
            ->whereNotNull('filled_json')
            ->latest('id')
            ->first();
    }

    public function getAutofillData(
        AllProduct $currentProduct,
        array $failedFields
    ): array {
        $successfulListing = $this->findFor($currentProduct);

        if (!$successfulListing || empty($successfulListing->filled_json)) {
            return [];
        }

        $filledData = json_decode($successfulListing->filled_json, true);

        if (!is_array($filledData)) {
            return [];
        }

        $autofillData = [];
        $excludedFields = array_map('strtolower', self::EXCLUDED_AUTOFILL_FIELDS);

        foreach ($failedFields as $field) {
            $normalizedField = strtolower($field);

            if (in_array($normalizedField, $excludedFields, true)) {
                continue;
            }

            if (
                array_key_exists($field, $filledData) &&
                $filledData[$field] !== null &&
                $filledData[$field] !== ''
            ) {
                $autofillData[$field] = $filledData[$field];
            }
        }

        return $autofillData;
    }
}