<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AmazonPayloadTransformer
{
    // Transform field
    public function transform(
        string $field,
        $value
    ): array {
        Log::info(
            'TRANSFORM FIELD',
            [
                'field' => $field,
                'value' => $value,
                'type' => gettype($value)
            ]
        );
        // Skip empty
        if (
            $value === null ||
            $value === ''
        ) {
            return [];
        }
        // Special transformers
        switch ($field) {
            // case 'shirt_size':
            //     return $this->shirtSize(
            //         $value
            //     );
            // case 'neck':
            //     return $this->neck(
            //         $value
            //     );
            // case 'sleeve':
            //     return $this->sleeve(
            //         $value
            //     );
            case 'bullet_point':
            case 'generic_keyword':
            case 'care_instructions':
            case 'product_description':
                return $this->languageField(
                    $value
                );

            case 'heel':
                return $this->heel($value);

            case 'outer':
                return $this->outer($value);

            case 'closure':
                return $this->closure($value);
            case 'list_price':
                return $this->price(
                    $value
                );
            case 'item_package_dimensions':
                return $this->dimensions($value);

            case 'fulfillment_availability':
                return $this->inventory(
                    $value
                );
            default:
                return $this->simple(
                    $value
                );
        }
    }


    private function isFormattedPayload(array $value): bool
    {
        if (!isset($value[0]) || !is_array($value[0])) {
            return false;
        }

        $keys = [
            'value',
            'language_tag',
            'marketplace_id',
            'currency',
            'fulfillment_channel_code',
            'quantity',
        ];

        return count(array_intersect($keys, array_keys($value[0]))) > 0;
    }
    private function dimensions($value): array
    {
        if (isset($value[0])) {
            $value = $value[0];
        }

        return [[
            'length' => [
                'value' => (float) ($value['length'] ?? 0),
                'unit'  => 'centimeters',
            ],

            'width' => [
                'value' => (float) ($value['width'] ?? 0),
                'unit'  => 'centimeters',
            ],

            'height' => [
                'value' => (float) ($value['height'] ?? 0),
                'unit'  => 'centimeters',
            ],
        ]];
    }
    // Normalize value
    private function normalize($value)
    {
        if (
            is_array($value) &&
            isset($value[0]['value'])
        ) {
            return $value[0]['value'];
        }

        return $value;
    }
    // Simple field
    private function simple(
        $value
    ): array {
        $value =
            $this->normalize(
                $value
            );
        return [[
            'value' => $value
        ]];
    }

    private function heel($value): array
    {
        $heelType = is_array($value)
            ? ($value['type'] ?? '')
            : $value;

        return [[
            'type' => [[
                'value' => $heelType,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'marketplace_id' => 'ATVPDKIKX0DER'
        ]];
    }

    private function outer($value): array
    {
        $material = is_array($value)
            ? ($value['material'] ?? '')
            : $value;

        return [[
            'material' => [[
                'value' => $material,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'marketplace_id' => 'ATVPDKIKX0DER'
        ]];
    }

    private function closure($value): array
    {
        $value = $this->normalize($value);

        return [[
            'type' => [[
                'value' => $value,
                'marketplace_id' => 'ATVPDKIKX0DER'
            ]],
            'marketplace_id' => 'ATVPDKIKX0DER'
        ]];
    }
    // Language field
    private function languageField(
        $value
    ): array {
        $value =
            $this->normalize(
                $value
            );
        return [[
            'value' => $value,
            'language_tag' =>
            'en_US'
        ]];
    }
    // Shirt size
    private function shirtSize($value): array
    {
        // [[...]] -> [...]
        if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            $value = $value[0];
        }

        // New group payload
        if (is_array($value)) {

            $size = $value['size']
                ?? $value['value']
                ?? '';

            return [[
                'size_system' => $value['size_system'] ?? 'US',
                'size_class'  => $value['size_class'] ?? 'alpha',
                'size'        => strtoupper((string) $size),
            ]];
        }

        // Old payload
        return [[
            'size_system' => 'US',
            'size_class'  => 'alpha',
            'size'        => strtoupper((string) $value),
        ]];
    }
    // Neck
    private function neck(
        $value
    ): array {
        $value =
            $this->normalize(
                $value
            );
        return [[
            'neck_style' => [[
                'value' =>
                $value,
                'language_tag' =>
                'en_US'
            ]]
        ]];
    }
    // Sleeve
    private function sleeve(
        $value
    ): array {
        $value =
            $this->normalize(
                $value
            );
        return [[
            'type' => [[
                'value' =>
                $value,
                'language_tag' =>
                'en_US'
            ]]
        ]];
    }
    // Price
    private function price(
        $value
    ): array {
        $value =
            $this->normalize(
                $value
            );
        return [[
            'value' =>
            (float)$value,
            'currency' =>
            'USD'
        ]];
    }
    // Inventory
    private function inventory(
        $value
    ): array {
        $value =
            $this->normalize(
                $value
            );
        return [[
            'fulfillment_channel_code'
            => 'DEFAULT',
            'quantity'
            => (int)$value
        ]];
    }

    private function hasTransformer(string $field): bool
    {
        return in_array($field, [

            'shirt_size',
            'neck',
            'sleeve',

            'bullet_point',
            'generic_keyword',
            'care_instructions',
            'product_description',

            'heel',
            'outer',
            'closure',

            'list_price',

            'fulfillment_availability',
            'item_package_dimensions',

        ], true);
    }
    public function build(array $fields): array
    {
        $payload = [];

        foreach ($fields as $key => $value) {

            Log::info('BUILD DEBUG', [
                'field' => $key,
                'is_array' => is_array($value),
                'value' => $value,
            ]);

            if (
                $value === null ||
                $value === '' ||
                $value === [] ||
                $value === 0
            ) {
                continue;
            }

            // Agar is field ka transformer exist karta hai to hamesha use karo
            if ($this->hasTransformer($key)) {

                Log::info('USING TRANSFORMER', [
                    'field' => $key,
                ]);

                $payload[$key] = $this->transform($key, $value);
                continue;
            }

            // Amazon formatted payload already hai
            if (is_array($value) && $this->isFormattedPayload($value)) {

                Log::info('FORMATTED ARRAY BYPASS', [
                    'field' => $key,
                ]);

                $payload[$key] = $value;
                continue;
            }

            // Raw array
            if (is_array($value)) {

                $payload[$key] = $value;
                continue;
            }

            // Default
            $payload[$key] = [
                [
                    'value' => $value
                ]
            ];
        }

        Log::info('FINAL TRANSFORMED PAYLOAD', $payload);

        return $payload;
    }
}
