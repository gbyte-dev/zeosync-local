<?php

namespace App\Support;

class AmazonFieldHint
{
    /**
     * Standard UI Hint Titles
     */
    public const TITLE_DEFAULT = 'Expected Format';
    public const TITLE_VALUE   = 'Expected Value';

    /**
     * Internal mapping of fields to their hint data.
     * 
     * - String value: Assumes TITLE_DEFAULT and uses the string as the example.
     * - Array tuple: [Custom Title, Example]
     */
    private const MAPPING = [
        // ── Weight ───────────────────────────────────────────────────
        'item_weight'                   => '250 grams',
        'item_package_weight'           => '500 grams',
        'item_display_weight'           => '1 kg',
        'maximum_weight_recommendation' => '10 kg',

        // ── 3D Dimensions ────────────────────────────────────────────
        'item_length_width_height' => '20L × 10W × 5H cm',
        'item_package_dimensions'  => '39L × 17.5W × 3H cm',
        'item_depth_width_height'  => '10D × 5W × 2H inches',

        // ── 2D Dimensions ────────────────────────────────────────────
        'item_length_width'  => '20L × 10W cm',
        'item_width_length'  => '10W × 20L cm',
        'item_length_height' => '20L × 10H cm',
        'item_height_length' => '10H × 20L cm',
        'item_length_depth'  => '20L × 10D cm',
        'item_depth_length'  => '10D × 20L cm',
        'item_width_height'  => '10W × 5H cm',
        'item_height_width'  => '5H  × 10W cm',
        'item_width_depth'   => '10W × 5D cm',
        'item_depth_width'   => '5D  × 10W cm',
        'item_height_depth'  => '10H × 5D cm',
        'item_depth_height'  => '5D  × 10H cm',

        // ── 1D Dimensions / Lengths ──────────────────────────────────
        'item_length'      => '50 cm',
        'item_width'       => '20 cm',
        'item_height'      => '10 cm',
        'min_focal_length' => '50 mm',
        'seat_depth'       => '15 inches',
        'seat_width'       => '20 inches',
        'seat_height'      => '18 inches',
        'seat'             => '18 inches',
        'bridge'           => '20 mm',
        'arm'              => '140 mm',

        // ── Capacity & Shelf Life ────────────────────────────────────
        'capacity'      => '500 ml',
        'liquid_volume' => '500 ml',
        'fc_shelf_life' => '365 days',
        'unit_count'    => '50 Count or (12 Fl Oz) or (1 ounces)',

        // ── Other Formatted Fields ───────────────────────────────────
        'country_of_origin'                  => 'US, CN, or IN',
        'contains_battery_or_cell'           => [self::TITLE_VALUE, 'contains_battery or no'],
        'water_resistance_level'             => [self::TITLE_VALUE, 'waterproof or ipx8'],
        'supplier_declared_dg_hz_regulation' => [self::TITLE_VALUE, 'not_applicable or un3480'],
    ];

    /**
     * Retrieve the UI hint array for a specific Amazon PTD field.
     * Returns null if no hint is configured for the given field.
     *
     * @param string $fieldName
     * @return array{title: string, example: string}|null
     */
    public static function get(string $fieldName): ?array
    {
        $hint = self::MAPPING[$fieldName] ?? null;

        if ($hint === null) {
            return null;
        }

        // Normalize flat string maps into the expected array structure
        if (is_string($hint)) {
            return [
                'title'   => self::TITLE_DEFAULT,
                'example' => $hint,
            ];
        }

        // Normalize tuple maps [Title, Example] into the expected array structure
        return [
            'title'   => $hint[0],
            'example' => $hint[1],
        ];
    }
}
