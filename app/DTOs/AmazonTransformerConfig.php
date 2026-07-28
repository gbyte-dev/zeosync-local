<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class AmazonTransformerConfig
{
    public function __construct(
        public string $marketplaceId,
        public string $languageTag,
        public string $currency,
        public string $measurementUnit,
        public string $weightUnit,
    ) {
    }

    /**
     * Build configuration from the connected shop.
     */
    public static function fromStore(object $store): self
    {
        return new self(
            marketplaceId: $store->amazon_marketplace_id
                ?? config('amazon.default_marketplace', 'ATVPDKIKX0DER'),

            languageTag: $store->amazon_language
                ?? config('amazon.default_language', 'en_US'),

            currency: $store->currency
                ?? config('amazon.default_currency', 'USD'),

            measurementUnit: config('amazon.default_measurement_unit', 'inches'),

            weightUnit: config('amazon.default_weight_unit', 'pounds'),
        );
    }
}