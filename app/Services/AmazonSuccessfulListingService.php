<?php

namespace App\Services;

use App\Models\AllProduct;

class AmazonSuccessfulListingService
{
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

        foreach ($failedFields as $field) {
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