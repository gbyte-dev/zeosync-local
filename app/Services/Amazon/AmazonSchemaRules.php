<?php

namespace App\Services\Amazon;

class AmazonSchemaRules
{
    public static function evaluate(array $formData): array
    {
        $rules = [
            'shirt_size' => [
                'show' => [
                    'size',
                    'size_to',
                    'body_type',
                    'height_type',
                    'neck_size',
                    'neck_size_to',
                    'sleeve_length',
                    'sleeve_length_to',
                ],
                'hide' => [],
                'required' => [],
            ],
            'fulfillment_availability' => [
                'show' => [
                    'fulfillment_channel_code',
                    'quantity',
                    'is_inventory_available',
                    'lead_time_to_ship_max_days',
                    'restock_date',
                ],
                'hide' => [],
                'required' => [],
            ],
        ];

        $shirtSize = $formData['shirt_size'][0] ?? [];
        $age = $formData['age_range_description'][0]['value'] ?? null;
        $gender = $formData['target_gender'][0]['value'] ?? null;
        $sizeClass = $shirtSize['size_class'] ?? null;
        $sizeSystem = $shirtSize['size_system'] ?? null;

        // SHIRT SIZE RULES
        if ($sizeSystem === 'as1' && $sizeClass === 'alpha') {
            $rules['shirt_size']['required'][] = 'size';
            $rules['shirt_size']['hide'][] = 'size_to';
            $rules['shirt_size']['hide'][] = 'neck_size';
            $rules['shirt_size']['hide'][] = 'neck_size_to';
            $rules['shirt_size']['hide'][] = 'sleeve_length';
            $rules['shirt_size']['hide'][] = 'sleeve_length_to';

            if (in_array($age, ['Adult', 'Adulto'], true)) {
                $rules['shirt_size']['required'][] = 'height_type';
            } else {
                $rules['shirt_size']['hide'][] = 'height_type';
            }

            if (in_array($age, ['Adult', 'Big Kid', 'Little Kid', 'Adulto', 'Adolescente', 'Niño Chico'], true)) {
                $rules['shirt_size']['required'][] = 'body_type';
            }
        }

        if ($sizeSystem === 'as1' && in_array($sizeClass, ['neck', 'neck_sleeve'], true)) {
            $rules['shirt_size']['required'][] = 'neck_size';
            $rules['shirt_size']['hide'][] = 'height_type';

            if ($sizeClass === 'neck_sleeve') {
                $rules['shirt_size']['required'][] = 'sleeve_length';
            } else {
                $rules['shirt_size']['hide'][] = 'sleeve_length';
            }
        }

        if ($sizeSystem === 'as1' && $sizeClass === 'numeric') {
            $rules['shirt_size']['required'][] = 'size';
            $rules['shirt_size']['hide'][] = 'neck_size';
            $rules['shirt_size']['hide'][] = 'sleeve_length';

            if (in_array($age, ['Adult', 'Adulto'], true) && $gender === 'female') {
                $rules['shirt_size']['required'][] = 'height_type';
            } else {
                $rules['shirt_size']['hide'][] = 'height_type';
            }
        }

        // FULFILLMENT RULES
        $fulfillment = $formData['fulfillment_availability'][0] ?? [];
        $channel = $fulfillment['fulfillment_channel_code'] ?? null;
        $isInventoryAlways = $fulfillment['is_inventory_available'] ?? null;

        if ($channel === 'DEFAULT') {
            if ($isInventoryAlways === true || $isInventoryAlways === 'true') {
                $rules['fulfillment_availability']['hide'][] = 'quantity';
            } else {
                $rules['fulfillment_availability']['required'][] = 'quantity';
            }
        } else {
            $rules['fulfillment_availability']['hide'][] = 'quantity';
            $rules['fulfillment_availability']['hide'][] = 'is_inventory_available';
        }

        return $rules;
    }
}