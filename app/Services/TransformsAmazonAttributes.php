<?php

namespace App\Services;

use App\Models\Shop;
use App\Traits\AmazonServiceValues;
use App\Traits\AmzonNormalizerTrait;
/**
 * class TransformsAmazonAttributes
 *
 * Converts raw source attribute values into the exact array structure
 * required by the Amazon Selling Partner API (Listings Items API) for
 * a given product type's attribute schema.
 *
 * Usage: add `$transformer = new TransformsAmazonAttributes();` to any class that needs
 * to build Amazon attribute payloads, then call:
 *
 */

class TransformsAmazonAttributes
{
    use AmazonServiceValues,AmzonNormalizerTrait;
    /**
     * Transform a raw (name, value) pair into Amazon's expected attribute array.
     * Returns null when the value can't be mapped/validated and should be skipped.
     */
    public function transformAttribute(string $name, mixed $value , mixed $productAttributes = null): ?array
    {
        if (session('active_shop')) {
            $shop = Shop::where('shop', session('active_shop'))->first();
        } else {
            $shop = Shop::where('shop', '!=', '')->first();
        }

        $marketplaceId = $shop?->amazon_marketplace_id ?? 'AB8Z5GI65VK9X';
        $weightUnitMap = $this->weightUnitMap();
        $unitMap = $this->lengthUnitMap();
        $voltageUnit = $this->voltageUnitMap();
        $liguidunit = [
            'ml' => 'milliliters',
            'milliliter' => 'milliliters',
            'milliliters' => 'milliliters',
            'l' => 'liters',
            'ltr' => 'liters',
            'liter' => 'liters',
            'liters' => 'liters',
            'fl oz' => 'fluid ounces',
            'fluid ounce' => 'fluid ounces',
            'fluid ounces' => 'fluid ounces',
            'oz' => 'ounces',
            'ounce' => 'ounces',
            'ounces' => 'ounces',
            'gal' => 'gallons',
            'gallon' => 'gallons',
            'gallons' => 'gallons'
        ];

        try {
            // ── Simple flag/type lookups ──────────────────────────────────────
            if (in_array($name, $this->booleanFields())) {
                return [['value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]];
            }

            if ($name === 'unit_count') {
                preg_match('/([\d.]+)\s*(.*)/i', trim((string) $value), $m);

                $number = (float) ($m[1] ?? $value);
                $rawUnit = strtolower(trim($m[2] ?? ''));

                $type = match ($rawUnit) {
                    'fl oz',
                    'floz',
                    'fluid oz',
                    'fluid ounce',
                    'fluid ounces',
                    'fluid_ounces' => 'Fl Oz',

                    'oz',
                    'ounce',
                    'ounces' => 'Ounce',

                    'count',
                    'unit',
                    'each',
                    'piece',
                    'pieces',
                    'pc',
                    'pcs' => 'Count',

                    default => 'Count',
                };

                return [[
                    'value' => $number,
                    'type' => [
                        'value' => $type,
                        'language_tag' => 'en_US',
                    ],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if (in_array($name, $this->integerFields())) {
                return [['value' => max(0, (int) $value)]];
            }

            if (in_array($name, $this->languageTagFields())) {
                return [['value' => $value, 'language_tag' => 'en_US']];
            }

            if (str_contains($name, 'image_locator')) {
                return [['media_location' => $value]];
            }

            // ── Weight ─────────────────────────────────────────────────────────
            if (in_array($name, ['item_package_weight', 'item_weight', 'item_display_weight', 'maximum_weight_recommendation', 'total_diamond_weight', 'total_gem_weight'])) {
                if (!preg_match('/^([\d.]+)\s*(\w+)$/i', $value, $m)) {
                    return null;
                }
                $unit = $weightUnitMap[strtolower(trim($m[2]))] ?? strtolower($m[2]);
                return [['value' => (float) $m[1], 'unit' => $unit]];
            }

            // ── length like  50mm , 50 mm ─────────────────────────────────────────────────────────
            if (in_array($name, ['min_focal_length'])) {
                if (!preg_match(
                    '/^\s*([\d]+(?:\.\d+)?)\s*(mm|millimeter|millimeters|cm|centimeter|centimeters|m|meter|meters|in|inch|inches|ft|foot|feet)\s*$/i',
                    $value,
                    $m
                )) {
                    return null;
                }

                $unit = $unitMap[strtolower($m[2])] ?? strtolower($m[2]);
                return [[
                    'value' => (float) $m[1],
                    'unit'  => $unit,
                ]];
            }

            // ── length like  50mm , 50 mm ───
            if ($name === 'objective_lens') {
                preg_match('/[\d.]+/', $value, $match);
                return [[
                    'diameter' => [[
                        'value' => (float) ($match[0] ?? 0)
                    ]]
                ]];
            }

            // ── L x W x H labeled dimensions ("39L x 17.5W x 3H Centimeters") or simple "10 x 2 x 2.7 inches" ──
            if (in_array($name, ['item_package_dimensions', 'item_length_width_height'])) {
                $valueString = trim((string) $value);
                $valueString = str_replace(['×', '*'], 'x', $valueString);

                $regex = '/^\s*([\d.]+)\s*[Ll]?\s*x\s*([\d.]+)\s*[Ww]?\s*x\s*([\d.]+)\s*[Hh]?\s*(.*?)\s*$/i';

                if (!preg_match($regex, $valueString, $m)) {
                    // dd($valueString);
                    return null;
                }

                $unit = $unitMap[strtolower(trim($m[4] ?? ''))] ?? strtolower(trim($m[4] ?? 'inches'));

                return [[
                    'length' => ['value' => (float) $m[1], 'unit' => $unit],
                    'width'  => ['value' => (float) $m[2], 'unit' => $unit],
                    'height' => ['value' => (float) $m[3], 'unit' => $unit],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            // ── L x W x T dimensions ─────────────────────────────────────
            if ($name === 'item_length_width_thickness') {

                $valueString = str_replace(['×', '*'], 'x', trim((string) $value));

                $regex = '/^\s*([\d.]+)\s*[Ll]?\s*x\s*([\d.]+)\s*[Ww]?\s*x\s*([\d.]+)\s*[Tt]?\s*(.*?)\s*$/i';

                if (!preg_match($regex, $valueString, $m)) {
                    return null;
                }

                $unit = strtolower(trim($m[4] ?? 'inches'));
                $unit = $unitMap[$unit] ?? 'inches';

                return [[
                    'length' => [
                        'value' => (float) $m[1],
                        'unit' => $unit,
                    ],
                    'width' => [
                        'value' => (float) $m[2],
                        'unit' => $unit,
                    ],
                    'thickness' => [
                        'value' => (float) $m[3],
                        'unit' => $unit,
                    ],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'included_components') {
                $values = array_filter(array_map('trim', explode(',', $value)));
                return array_map(fn($v) => [
                    'value' => $v,
                    'language_tag' => 'en_US'
                ], $values);
            }

            if (in_array($name, ['fc_shelf_life', 'maximum_reading_interest_age', 'minimum_reading_interest_age'])) {
                preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z]+)?/', strtolower(trim($value)), $m);

                return [[
                    'value' => (float)($m[1] ?? $value),
                    'unit'  => [
                        'day' => 'days',
                        'days' => 'days',
                        'week' => 'weeks',
                        'weeks' => 'weeks',
                        'month' => 'months',
                        'months' => 'months',
                        'year' => 'years',
                        'years' => 'years'
                    ][$m[2] ?? 'day'] ?? 'days'
                ]];
            }

            if ($name === 'hazmat') {
                return [[
                    'aspect' => 'united_nations_regulatory_id',
                    'value' => trim($value),
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            // ── item_depth_width_height — schema only accepts "inches" ─────────
            if ($name == 'item_depth_width_height') {

                $valueString = trim((string) $value);
                return $this->parseDepthWidthHeight($valueString, $marketplaceId);
            }

            // ── item_length_width_depth — schema only accepts "inches" ─────────
            if ($name === 'item_length_width_depth') {
                $valueString = trim((string) $value);
                $valueString = str_replace(['×', '*'], 'x', $valueString);

                // Supports:
                // 78.7L x 47.2W x 7.5D inches
                // 78.7 x 47.2 x 7.5 inches
                if (!preg_match(
                    '/^\s*([\d.]+)\s*[Ll]?\s*x\s*([\d.]+)\s*[Ww]?\s*x\s*([\d.]+)\s*[Dd]?\s*(.*?)\s*$/i',
                    $valueString,
                    $m
                )) {
                    return null;
                }

                // Schema for this field accepts inches only.
                $unit = 'inches';

                return [[
                    'length' => [
                        'value' => (float) $m[1],
                        'unit' => $unit,
                    ],
                    'width' => [
                        'value' => (float) $m[2],
                        'unit' => $unit,
                    ],
                    'depth' => [
                        'value' => (float) $m[3],
                        'unit' => $unit,
                    ],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            // ── L x W  or W*H  or any comination of 2 abeled dimensions ("39L x 17.5W  Centimeters") " ──

            if (in_array($name, $this->getTwoFieldDimensionNames(), true)) {
                return [$this->parseTwoFieldsOnly($value, $marketplaceId)];
            }

            // ── seat_* — nested depth array, wide unit enum ─────────────────────
            if (in_array($name, ['seat_depth', 'seat_width', 'seat_height'])) {
                [$val, $unit] = $this->parseUnitValue($value, $unitMap);

                if ($unit === null) {
                    return null;
                }

                $field = match ($name) {
                    'seat_depth'  => 'depth',
                    'seat_width'  => 'width',
                    'seat_height' => 'height',
                };

                return [
                    $field => [[
                        'value' => $val,
                        'unit' => $unit,
                    ]],
                ];
            }

            // ── Flat { value, unit, marketplace_id } dimension attributes ──────
            if (in_array($name, $this->flatDimensionAttributes(), true)) {
                [$val, $unit] = $this->parseUnitValue($value, $unitMap);
                if ($unit === null) {
                    return null;
                }
                return [['value' => $val, 'unit' => $unit, 'marketplace_id' => $marketplaceId]];
            }

            // ── Generic L/W/H text matcher (fallback for loosely-named fields) ──
            if (preg_match('/\b(dimension|item_dimensions|dimensions|height|breadth|width|length)\b/i', $name)) {
                return $this->parseGenericDimensions($value, $unitMap);
            }

            if ($name === 'seat') {

                $raw = strtolower(trim((string) $value));

                preg_match('/([\d.]+)\s*h\b\s*\*\s*([\d.]+)\s*d\b\s*([a-z]+)/i', $raw, $m);

                $seat = [
                    'marketplace_id' => $marketplaceId,
                ];

                if (!empty($m)) {

                    $unit = $unitMap[strtolower($m[3])] ?? 'centimeters';

                    $seat['back_interior_height'] = [[
                        'value' => (float) $m[1],
                        'unit' => $unit,
                    ]];

                    $seat['height'] = [[
                        'value' => (float) $m[1],
                        'unit' => $unit,
                    ]];
                    $seat['depth'] = [[
                        'value' => (float) $m[2],
                        'unit' => $unit,
                    ]];
                }

                return [$seat];
            }



            // ── Frame ────────────────────────────────────────────────────────────
            if ($name === 'frame_material') {
                return null; // merged into 'frame' below
            }

            if ($name === 'frame') {
                $colorEnum = ['beige', 'black', 'blue', 'brown', 'gold', 'green', 'grey', 'multicolor', 'orange', 'pink', 'purple', 'red', 'silver', 'white', 'yellow'];
                $rawColor = trim((string) $value);
                $matchedColor = 'Multicolor';
                foreach ($colorEnum as $c) {
                    if (strcasecmp($rawColor, $c) === 0) {
                        $matchedColor = ucfirst($c);
                        break;
                    }
                }
                return [[
                    'color'    => [['value' => $matchedColor, 'language_tag' => 'en_US']],
                    'material' => [['value' => 'Wood', 'language_tag' => 'en_US']],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            // ── Misc single-purpose attributes ──────────────────────────────────
            if ($name === 'package_contains_sku') {
                return [['child_id' => $value, 'quantity' => 1, 'marketplace_id' => $marketplaceId]];
            }

            if ($name === 'head') {
                return [
                    [
                        'marketplace_id' => $marketplaceId,
                        'style' => [
                            [
                                'language_tag' => 'en_US',
                                'value' => trim($value),
                            ]
                        ]
                    ]
                ];
            }


            if ($name === 'title_differentiation') {
                $values = array_filter(array_map('trim', explode(',', $value)));

                //  return array_values(array_map(function ($item) {
                return [[
                    'value' => mb_substr($value, 0, 125), // Amazon limit
                    'language_tag' => 'en_US'
                ]];
                // }, $values));
            }

            if ($name === 'language') {
                return [[
                    'type' => 'published',
                    'value' => $value,
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            // only for rings
            if ($name === 'ring_size' || $name === 'ring') {
                return $this->updateRingValue($value, 6.8);
            }

            if ($name === 'stone') {
                return [[
                    'marketplace_id' => $marketplaceId,

                    'clarity' => [[
                        'language_tag' => 'en_US',
                        'value' => 'VS1'
                    ]],

                    'color' => [[
                        'language_tag' => 'en_US',
                        'value' => 'D'
                    ]],

                    'creation_method' => [[
                        'value' => 'natural'
                    ]],

                    'shape' => [[
                        'language_tag' => 'en_US',
                        'value' => 'Round'
                    ]],

                    'weight' => [[
                        'value' => 1,
                        'unit' => 'carats'
                    ]]
                ]];
            }

            if ($name === 'stones') {
                return [[
                    'id' => 1,

                    'type' => [
                        'language_tag' => 'en_US',
                        'value' => 'Diamond'
                    ],

                    'number_of_stones' => 1,

                    'creation_method' => [
                        'language_tag' => 'en_US',
                        'value' => 'Natural'
                    ],

                    'treatment_method' => [
                        'language_tag' => 'en_US',
                        'value' => 'Not Treated'
                    ],

                    'cut' => [
                        'language_tag' => 'en_US',
                        'value' => 'Excellent'
                    ],

                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'stones_creation_method') {
                return [[
                    'creation_method' => 'Natural',
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'stones_treatment_method') {
                return [[
                    'treatment_method' => 'Not Treated',
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'stone_id') {
                return [[
                    'id' => 1,
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'item_diameter' || $name === 'item_thickness') {
                preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z\s]+)?/', strtolower(trim($value)), $m);
                return [[
                    'value' => (float)($m[1] ?? $value),
                    'unit' => $unitMap[$m[2] ?? 'mm'] ?? 'millimeters',
                    'marketplace_id' => $marketplaceId,
                ]];
            }


            if ($name === 'list_price') {
                return [['value' => (float) $value, 'currency' => 'USD']];
            }

            if ($name === 'purchasable_offer') {
                return [['our_price' => [['schedule' => [['value_with_tax' => (float) $value]]]], 'currency' => 'USD']];
            }

            if ($name === 'fulfillment_availability') {
                $decoded = json_decode($value, true);
                if ($decoded && is_array($decoded)) {
                    return [$decoded];
                }
                $channelMap = ['amazon' => 'AMAZON_NA', 'amazon_na' => 'AMAZON_NA', 'fba' => 'AMAZON_NA', 'default' => 'DEFAULT', 'defualt' => 'DEFAULT', 'mfn' => 'DEFAULT', 'merchant' => 'DEFAULT'];
                $channel = $channelMap[strtolower(trim($value))] ?? 'DEFAULT';
                return [['fulfillment_channel_code' => $channel, 'quantity' => 0, 'lead_time_to_ship_max_days' => 30]];
            }

            if ($name === 'externally_assigned_product_identifier') {

                $value = trim((string) $value);

                if (preg_match('/^(EAN|GTIN|UPC)\s*:\s*(\d+)$/i', $value, $m)) {
                    return [[
                        'marketplace_id' => $marketplaceId,
                        'type' => strtolower($m[1]),
                        'value' => $m[2],
                    ]];
                }

                return [];
            }

            if ($name === 'parentage_level') {
                return in_array($value, ['parent', 'child']) ? [['value' => $value]] : null;
            }

            if ($name === 'language') {
                return [['type' => 'en_US', 'value' => 'en_US']];
            }

            if ($name === 'num_batteries') {
                return [[
                    'quantity' => (int) $value,
                    'type' => 'nonstandard_battery',
                    'marketplace_id' => $marketplaceId, // whatever you're threading through elsewhere
                ]];
            }

           if (in_array($name, ['memory_storage_capacity', 'digital_storage_capacity'])) {
                preg_match('/([\d.]+)\s*(bytes|GB|KB|MB|TB)?/i', (string) $value, $m);

                return [[
                    'value' => (float) ($m[1] ?? 0),
                    'unit' => ucfirst(strtoupper($m[2] ?? 'GB')),
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'effective_still_resolution') {
                preg_match('/([\d.]+)/', (string) $value, $m);

                return [[
                    'value' => (float) ($m[1] ?? 0),
                    'unit' => 'megapixels',
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'country_of_origin') {
                $normalized = strtolower(trim((string) $value));
                $mapped = $this->countryMap()[$normalized] ?? null;
                return [['value' => $mapped ?? 'US']];
            }

            if ($name === 'display') {
                $raw = trim((string) $value);
                $displayObject = ['marketplace_id' => $marketplaceId];

                if (preg_match('/(\d+(?:\.\d+)?)\s*(?:inch|inches|")/i', $raw, $m)) {
                    $displayObject['size'] = [[
                        'value' => (float) $m[1],
                        'unit' => 'inches'
                    ]];
                } else {
                    $displayObject['size'] = [[
                        'value' => 15.6,
                        'unit' => 'inches'
                    ]];
                }

                if (preg_match('/(\d{3,4})\s*[x×]\s*(\d{3,4})/u', $raw, $m)) {
                    $displayObject['resolution_maximum'] = [[
                        'value' => $m[1] . 'x' . $m[2],
                        'language_tag' => 'en_US'
                    ]];
                }

                if (preg_match('/(\d{2,4})\s*Hz\b/i', $raw, $m)) {
                    $displayObject['refresh_rate_in_hertz'] = [['value' => (int) $m[1]]];
                }

                foreach (['AMOLED', 'LCD', 'LED', 'OLED', 'IPS', 'TN', 'VA'] as $tech) {
                    if (preg_match('/\b' . $tech . '\b/i', $raw)) {
                        $displayObject['technology'] = [[
                            'value' => $tech,
                            'language_tag' => 'en_US'
                        ]];
                        $displayObject['type'] = [[
                            'value' => $tech,
                            'language_tag' => 'en_US'
                        ]];
                        break;
                    }
                }

                return [$displayObject];
            }

            if ($name === 'maximum_display_brightness') {
                $validUnits = ['candela_per_square_meter', 'nit'];

                preg_match('/([\d.]+)\s*([a-zA-Z_\s]+)/', trim((string) $value), $matches);

                $num = (float) ($matches[1] ?? 0);
                $unitRaw = strtolower(trim($matches[2] ?? ''));

                // normalize common phrasings to the enum
                $unitAliases = [
                    'nit' => 'nit',
                    'nits' => 'nit',
                    'cd/m2' => 'candela_per_square_meter',
                    'cd/m^2' => 'candela_per_square_meter',
                    'candela per square meter' => 'candela_per_square_meter',
                    'candela_per_square_meter' => 'candela_per_square_meter',
                ];
                $unit = $unitAliases[$unitRaw] ?? 'nit'; // fallback default

                // enforce schema constraints: 0-50000, multiple of 0.001
                $num = max(0, min(50000, $num));
                $num = round($num, 3);

                return [[
                    'value' => $num,
                    'unit' => $unit,
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'ram_memory') {

                preg_match('/([\d.]+)\s*(GB|MB|TB)\s*(DDR\d+|LPDDR\d+)?/i', trim((string) $value), $m);

                $size = isset($m[1]) ? (float) $m[1] : 8;
                $unit = isset($m[2]) ? strtoupper($m[2]) : 'GB';
                $tech = isset($m[3]) ? strtoupper($m[3]) : 'DDR4';

                $ramObject = [
                    'marketplace_id' => $marketplaceId,
                    'installed_size' => [[
                        'value' => $size,
                        'unit' => $unit,
                    ]],
                    'installed_size_unit' => [[
                        'value' => $unit,
                        'language_tag' => 'en_US',
                    ]],
                    'maximum_size' => [[
                        'value' => $size * 2,
                        'unit' => $unit,
                    ]],
                    'maximum_size_unit' => [[
                        'value' => $unit,
                        'language_tag' => 'en_US',
                    ]],
                    'technology' => [[
                        'value' => $tech,
                        'language_tag' => 'en_US',
                    ]],
                ];

                return [$ramObject];
            }

            if ($name === 'cpu_model') {

                [$raw, $speedRaw] = array_pad(
                    array_map('trim', explode(',', (string) $value, 2)),
                    2,
                    ''
                );

                $family = $this->amazonCpuFamily($raw);
                $manufacturer = '';
                $manufacturers = $this->cpuManufacturers();

                $pattern = implode('|', array_map(
                    fn($v) => preg_quote($v, '/'),
                    $manufacturers
                ));

                if (preg_match('/^(' . $pattern . ')\b/i', $raw, $m)) {
                    $manufacturer = $m[1];
                }

                preg_match(
                    '/\b((?:[A-Za-z]+\d+[-]?\d+[A-Za-z0-9-]*|\d{3,6}[A-Za-z]{1,4}|[A-Za-z]\d{1,4}[A-Za-z0-9-]*))\b/i',
                    $raw,
                    $m
                );

                preg_match('/([\d.]+)\s*(GHz|MHz|KHz|hertz)\b/i', $speedRaw ?? '2.4 Ghz', $m2);
                $modelNumber = $m[1] ?? '';

                return [[
                    'marketplace_id' => $marketplaceId,
                    'family' => [
                        ['value' => $family ?: $raw],
                    ],
                    'manufacturer' => [
                        [
                            'value' => $manufacturer,
                            'language_tag' => 'en_US',
                        ],
                    ],
                    'model_number' => [
                        [
                            'value' => $modelNumber,
                            'language_tag' => 'en_US',
                        ],
                    ],
                    'speed' => [
                        [
                            'value' => $m2[1] ?? 2.4,
                            'unit' => $m2[2] ?? 'GHz',
                        ],
                    ],
                ]];
            }

            if ($name === 'supplier_declared_dg_hz_regulation') {
                $valid = ['not_applicable', 'un3480', 'un3481', 'un3090', 'un3091', 'iata_section_ii', 'iata_section_ib'];
                return [['value' => in_array(strtolower($value), $valid) ? strtolower($value) : 'not_applicable']];
            }

            if ($name === 'contains_battery_or_cell') {
                $map = [
                    'battery' => 'battery',
                    'cell' => 'cell',
                    'contains_battery' => 'contains_battery',
                    'lithium_ion' => 'contains_lithium_ion_battery',
                    'lithium_metal' => 'contains_lithium_metal_battery',
                    'no' => 'does_not_contain_a_battery',
                    'false' => 'does_not_contain_a_battery',
                    'does_not_contain_battery' => 'does_not_contain_a_battery',
                    'none' => 'does_not_contain_a_battery'
                ];

                return [['value' => $map[strtolower($value)] ?? 'battery']];
            }

            if ($name === 'sleeve') {
                return [['type' => [['value' => $value, 'language_tag' => 'en_US']]]];
            }

            if ($name === 'tire') {
                return [[
                    'tire_type' => [[
                        'value' => trim($value),
                        'language_tag' => 'en_US',
                    ]]
                ]];
            }

            if ($name === 'neck') {
                return [['neck_style' => [['value' => $value, 'language_tag' => 'en_US']]]];
            }

            if ($name === 'deck') {
                $value = strtolower(trim($value));
                if (!preg_match(
                    '/^\s*([\d]+(?:\.\d+)?)\s*L\s*(?:\*|x|×)\s*([\d]+(?:\.\d+)?)\s*W\s*(mm|millimeter|millimeters|cm|centimeter|centimeters|m|meter|meters|in|inch|inches|ft|foot|feet)\s*$/i',
                    trim($value),
                    $m
                )) {
                    return null;
                }

                $unit = $unitMap[strtolower($m[3])] ?? strtolower($m[3]);

                return [[
                    'length' => [[
                        'value' => (float) $m[1],
                        'unit'  => $unit,
                    ]],
                    'width' => [[
                        'value' => (float) $m[2],
                        'unit'  => $unit,
                    ]]
                ]];
            }

            if ($name === 'wheel') {
                $value = strtolower(trim($value));
                if (!preg_match(
                    '/^\s*([\d]+(?:\.\d+)?)\s*(mm|millimeter|Millimetres|millimetres|millimeters|cm|centimeter|centimeters|m|meter|meters|in|inch|inches|ft|foot|feet)\s*$/i',
                    trim($value),
                    $m
                )) {
                    return null;
                }
                $unit = $unitMap[strtolower($m[2])] ?? strtolower($m[2]);
                return [[
                    'size' => [[
                        'value' => (float) $m[1],
                        'unit'  => $unit ?? 'millimeters',
                    ]]
                ]];
            }


            if ($name === 'package_level') {
                $map = ['unit' => 'each', 'each' => 'each', 'pack' => 'pack', 'set' => 'set'];
                return [['value' => $map[strtolower($value)] ?? 'each']];
            }

            if (in_array($name, ['capacity', 'liquid_volume'])) {
                preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z\s]+)?/', strtolower(trim($value)), $m);
                return [[
                    'value' => (float)($m[1] ?? $value),
                    'unit'  => $liquidUnit[$m[2] ?? 'ml'] ?? 'milliliters'
                ]];
            }

            if (in_array($name, ['voltage', 'input_voltage', 'wattage'])) {
                preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Zµ\s\.]+)?/i', strtolower(trim($value)), $m);

                $unit = strtolower(trim($m[2] ?? 'v'));

                return [[
                    'value' => (float)($m[1] ?? $value),
                    'unit'  => $voltageUnit[$unit] ?? 'volts',
                ]];
            }

            if ($name === 'map_policy') {
                $valid = ['map_policy_1', 'map_policy_2', 'no_map_policy'];
                return in_array($value, $valid) ? [['value' => $value]] : null;
            }

            if ($name === 'max_order_quantity') {
                $i = (int) $value;
                return $i >= 1 ? [['value' => $i]] : null;
            }

            if ($name === 'bullet_point') {
                $lines = array_filter(array_map('trim', preg_split('/\r\n|\n/', $value)));
                return array_values(array_map(fn($l) => ['value' => $l, 'language_tag' => 'en_US'], $lines));
            }

            if (in_array($name, ['memory_clock_speed', 'memory_speed'])) {
                $validUnits = ['GHz', 'MHz'];
                preg_match('/([\d.]+)\s*([a-zA-Z]+)?/', trim((string) $value), $matches);

                $num = (float) ($matches[1] ?? 0);
                $unitRaw = trim($matches[2] ?? '');
                $unitMatch = array_filter($validUnits, fn($u) => strtolower($u) === strtolower($unitRaw));
                $unit = $unitMatch ? array_values($unitMatch)[0] : 'GHz';
                $num = min(14700315, $num);

                $item = [
                    'value' => $num,
                    'marketplace_id' => $marketplaceId,
                    'unit' => $unit ?? 'GHz',
                ];

                return [$item];
            }

            if ($name === 'flash_memory') {
                $installed = parseUnitValue((string) $value, ['GB', 'MB', 'TB'], 'GB');
                if ($installed['value'] <= 0) {
                    return []; // nothing to submit — omit attribute entirely
                }
                return [[
                    'marketplace_id' => $marketplaceId,
                    'installed_size' => [$installed],
                ]];
            }

            if ($name === 'graphics_ram') {
                $raw = trim((string) $value);
                preg_match('/^([\d.]+\s*[a-zA-Z]+)\s+(.+)$/', $raw, $m);
                $sizePart = trim($m[1] ?? $raw);
                $size = parseUnitValue($sizePart, ['GB', 'MB', 'TB'], 'GB');

                if ($size['value'] <= 0) {
                    return []; // integrated/shared graphics, or unparseable — omit
                }

                $typePart = trim($m[2] ?? '');
                $graphicsRam = ['marketplace_id' => $marketplaceId, 'size' => [$size]];
                $typeToken = $this->mapGraphicsRamType($typePart);
                if ($typeToken !== null) {
                    $graphicsRam['type'] = [['value' => $typeToken]];
                }
                return [$graphicsRam];
            }

            if ($name === 'hard_disk') {
                $raw = trim((string) $value);

                $knownTypes = ['Emmc', 'HDD', 'SSD', 'SSHD', 'UFS'];
                $matched = array_filter($knownTypes, fn($t) => strtolower($t) === strtolower($raw));
                $normalized = $matched ? array_values($matched)[0] : $raw; // keep raw as free text if no match

                return [[
                    'marketplace_id' => $marketplaceId,
                    'description' => [
                        [
                            'value' => $normalized,
                            'language_tag' => 'en_US',
                        ],
                    ],
                ]];
            }

            if (is_array($value)) {
                $items = array_values(array_filter(array_map(function ($item) {
                    if (is_array($item)) {
                        if (isset($item['value'])) {
                            return trim((string) $item['value']);
                        }

                        if (isset($item['name'])) {
                            return trim((string) $item['name']);
                        }

                        return trim((string) json_encode($item));
                    }

                    return is_string($item) ? trim($item) : trim((string) $item);
                }, $value), fn($item) => $item !== '' && $item !== 'null'));

                if ($items === []) {
                    return null;
                }

                if ($name === 'variation_theme') {
                    return array_map(fn($item) => ['name' => strtoupper((string) $item)], $items);
                }

                if ($name === 'special_feature' || str_ends_with($name, '_feature') || str_contains($name, 'feature')) {
                    return array_map(fn($item) => [
                        'value' => (string) $item,
                        'language_tag' => 'en_US',
                        'marketplace_id' => $marketplaceId,
                    ], $items);
                }

                return array_map(fn($item) => [
                    'value' => (string) $item,
                    'language_tag' => 'en_US',
                    'marketplace_id' => $marketplaceId,
                ], $items);
            }

            if ($name === 'manufacturer') {
                return [['value' => mb_substr($value, 0, 100)]];
            }

            if ($name === 'condition_type') {
                $map = ['new_new' => 'new_new', 'new' => 'new_new', 'used_good' => 'used_good', 'used_very_good' => 'used_very_good', 'used_acceptable' => 'used_acceptable', 'collectible_good' => 'collectible_good'];
                return [['value' => $map[strtolower($value)] ?? 'new_new']];
            }
            if ($name === 'battery') {
                $raw = trim((string) $value);

                $battery = [
                    'marketplace_id' => $marketplaceId,
                ];
               
                foreach ( [ 'Lithium-Ion' => 'lithium_ion', 'Lithium-Metal' => 'lithium_metal',
                        'Lithium-Polymer' => 'lithium_polymer', 'Alkaline' => 'alkaline',
                        'NiMH' => 'NiMh',  'NiCad' => 'NiCAD',  ] as $label => $cell ) 
                {
                    
                    if (stripos($raw, $label) !== false) {
                        $battery['cell_composition'] = [['value' => $cell]];
                        break;
                    }
                }

                if (preg_match('/(\d+(?:\.\d+)?)\s*(g|grams?|kg)/i', $raw, $m)) {
                    $battery['weight'] = [[
                        'value' => (float) $m[1],
                        'unit' => strtolower($m[2]) === 'kg' ? 'kg' : 'grams',
                    ]];
                }

                if (preg_match('/(\d+(?:\.\d+)?)\s*(mAh|Ah|Wh|kWh)/i', $raw, $m)) {
                    $unit = strtolower($m[2]);

                    $battery['battery_capacity'] = [[
                        'value' => (float) $m[1],
                        'unit' => match ($unit) {
                            'mah' => 'Milliampere Hour (mAh)',
                            'ah'  => 'Ampere Hours',
                            'wh'  => 'Watt Hours',
                            'kwh' => 'Kilowatt Hours',
                        },
                    ]];
                }

                
            
                return [$battery];
            }


            // ── 50 watt_hours | 0.5 grams ─────────────────────────────
            if ($name === 'lithium_battery') {
                $valueString = trim((string) $value);

                $regex = '/^\s*([\d.]+)\s*(?:watt[_\s-]*hours?|wh)\s*\|\s*([\d.]+)\s*(grams?|g)\s*$/i';

                if (!preg_match($regex, $valueString, $m)) {
                    return null;
                }

                return [[
                    'energy_content' => [
                        [
                            'value' => (float) $m[1],
                            'unit' => 'watt_hours',
                        ]
                    ],
                    'packaging' => [
                        [
                            'value' => 'batteries_contained_in_equipment',
                        ]
                    ],
                    'weight' => [
                        [
                            'value' => (float) $m[2],
                            'unit' => 'grams',
                        ]
                    ],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'color_gamut') {
                $raw = trim((string) $value);

                // extract percentage number
                preg_match('/([\d.]+)\s*%/', $raw, $numMatch);
                $num = (float) ($numMatch[1] ?? 0);
                $num = min(150, $num); // enforce schema max

                // detect standard name
                $validNames = [
                    'adobe_rgb' => 'Adobe RGB',
                    'cie_1976' => 'CIE 1976',
                    'ntsc' => 'NTSC',
                    'rec_709' => 'Rec. 709',
                    'srgb' => 'sRGB',
                ];

                $nameToken = null;
                foreach ($validNames as $token => $label) {
                    if (stripos($raw, $token) !== false || stripos($raw, str_replace('_', ' ', $token)) !== false || stripos($raw, $label) !== false) {
                        $nameToken = $token;
                        break;
                    }
                }
                // also catch plain "sRGB" without underscore variant explicitly
                if ($nameToken === null && stripos($raw, 'srgb') !== false) {
                    $nameToken = 'srgb';
                }

                if ($nameToken === null || $num <= 0) {
                    return []; // can't produce a valid required pair — omit rather than guess
                }

                return [[
                    'marketplace_id' => $marketplaceId,
                    'name' => $nameToken,
                    'value' => $num,
                ]];
            }

            if (in_array($name, ['subject', 'subject_code'])) {
                return [['value' => $value, "type" => "bisac_description", "language_tag" => "en_US"]];
            }

            if ($name === 'regulatory_compliance_certification') {
                return [['value' => $value, "regulation_type" => "ul_cetrification_no", "marketplace_id" => $marketplaceId]];
            }

            if ($name === 'variation_theme') {
                $upper = strtoupper($value);
                $validThemes = ['COLOR', 'COMPATIBLE_DEVICES', 'KEYBOARD_LAYOUT', 'COLOR/COMPATIBLE_DEVICES', 'COLOR/KEYBOARD_LAYOUT', 'COMPATIBLE_DEVICES/KEYBOARD_LAYOUT'];
                if (in_array($upper, $validThemes)) {
                    return [['name' => $upper]];
                }
                $fallback = [
                    'COLOR/COMPATIBLE_DEVICES/KEYBOARD_LAYOUT' => 'COLOR/COMPATIBLE_DEVICES',
                    'COLOR/KEYBOARD_LAYOUT/COMPATIBLE_DEVICES' => 'COLOR/COMPATIBLE_DEVICES',
                ];
                return [['name' => $fallback[$upper] ?? 'COLOR']];
            }

            if (in_array($name, ['shirt_size', 'apparel_size'])) {
                return [['size_system' => 'as1', 'size_class' => 'alpha', 'size' => 'm', 'height_type' => 'tall', 'body_type' => 'regular']];
            }

            if ($name === 'lens') {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (is_array($decoded)) {
                    $lensPayload = [];
                    $widthValue = $decoded['width'] ?? $decoded['lens_width'] ?? null;
                    $materialValue = $decoded['material'] ?? $decoded['lens_material'] ?? null;
                    $colorValue = $decoded['color'] ?? $decoded['lens_color'] ?? null;


                    if ($widthValue !== null && $widthValue !== '') {
                        $lensPayload['width'] = [[
                            'value' => (string) $widthValue ?? 20,
                            'unit' => 'millimeters',
                            'language_tag' => 'en_US',
                        ]];

                        $lensPayload['material'] = [[
                            'value' => trim((string) $materialValue ?? 'polycarbonate'),
                            'language_tag' => 'en_US',
                        ]];

                        $lensPayload['color'] = [[
                            'value' => trim((string) $colorValue ?? 'black'),
                            'language_tag' => 'en_US',
                        ]];
                    }

                    if ($lensPayload !== []) {
                        $lensPayload['marketplace_id'] = $marketplaceId;
                        return [$lensPayload];
                    }
                }

                if (is_string($value) && $value !== '') {

                    $lensPayload['width'] = [[
                        'value' => (string) 20,
                        'unit' => 'millimeters',
                        'language_tag' => 'en_US',
                    ]];

                    $lensPayload['material'] = [[
                        'value' => trim((string) 'polycarbonate'),
                        'language_tag' => 'en_US',
                    ]];

                    $lensPayload['color'] = [[
                        'value' => trim((string) 'black'),
                        'language_tag' => 'en_US',
                    ]];

                    return [$lensPayload];

                    // return [[
                    //     'material' => [['value' => trim((string) $value), 'language_tag' => 'en_US']],
                    //     'marketplace_id' => $marketplaceId,
                    // ]];
                }

                return null;
            }

            // GUESSES ONLY — need schema confirmation before trusting these
            if ($name === 'bridge') {
                [$val, $unit] = $this->parseUnitValue($value, $this->lengthUnitMap());
                if ($unit === null) return null;
                return [[
                    'width' => [['value' => $val, 'unit' => $unit]],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if ($name === 'arm') {
                [$val, $unit] = $this->parseUnitValue($value, $this->lengthUnitMap());
                if ($unit === null) return null;
                return [[
                    'length' => [['value' => $val, 'unit' => $unit]],
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            if (in_array($name, ['color', 'colour', 'color_name'])) {
                $normalizedColor = trim((string) $value);
                $colorMap = [
                    'black' => 'Black',
                    'blue' => 'Blue',
                    'brown' => 'Brown',
                    'gold' => 'Gold',
                    'green' => 'Green',
                    'grey' => 'Grey',
                    'gray' => 'Gray',
                    'multicolor' => 'Multicolor',
                    'orange' => 'Orange',
                    'pink' => 'Pink',
                    'purple' => 'Purple',
                    'red' => 'Red',
                    'silver' => 'Silver',
                    'white' => 'White',
                    'yellow' => 'Yellow',
                ];
                $finalColor = $colorMap[strtolower($normalizedColor)] ?? ucfirst(strtolower($normalizedColor));
                return [['value' => $finalColor]];
            }

            if ($name === 'item_density') {
                return $this->parseDensity($value, $marketplaceId);
            }
            if ($name === 'polarization_type') {
                // enum unknown — passing raw value through for now
                return [['value' => strtolower(trim((string) $value))]];
            }

            if ($name === 'closure') {
                return $this->closure($value);
            }

            if ($name === 'light_source') {
                return $this->lightSource($value, $marketplaceId);
            }

            if ($name === 'maximum_height') {
                return $this->maximumHeight($value, $marketplaceId);
            }

            if ($name === 'minimum_height') {
                return $this->minimumHeight($value, $marketplaceId);
            }

            if ($name === 'data_transfer_rate') {
                return $this->dataTransferRate($value, $marketplaceId);
            }

            if ($name === 'item_volume') {
                return $this->itemVolume($value, $marketplaceId);
            }

            if ($name === 'alcohol_content') {
                return $this->alcoholContent($value, $marketplaceId);
            }

            if ($name === 'runtime') {
                return $this->runtime($value, $marketplaceId);
            }

            if ($name === 'water_resistance_level') {
                return $this->waterResistanceLevel($value, $marketplaceId);
            }


            if ($name === 'tank_volume') {
                return $this->tankVolume($value, $marketplaceId);
            }

            if ($name === 'mpaa_rating') {
                return $this->mpaaRating($value, $marketplaceId);
            }

            if ($name === 'melting_temperature') {
                return $this->meltingTemperature($value, $marketplaceId);
            }
    
            if ($name === 'esrb_rating') {
                return $this->createEsrbRating($value, [], $marketplaceId);
            }

            // createEsrbRating($rating, $descriptors, $marketplaceId)

            if ($name === 'non_lithium_battery_energy_content') {
                $input = trim($value);

                preg_match(
                    '/^\s*([0-9]+(?:\.[0-9]+)?)\s*(.*?)\s*$/i',
                    $input,
                    $matches
                );

                $energyValue = isset($matches[1]) ? (float) $matches[1] : 0;
                $unit = strtolower(trim($matches[2] ?? ''));

                $vunitMap = $this->voltageUnitMap();

                $normalizedUnit = $vunitMap[$unit] ?? null;

                return [[
                    'value' => $energyValue,
                    'unit' => $normalizedUnit ?? 'Watt Hours',
                    'marketplace_id' => $marketplaceId,
                ]];
            }

            return [['value' => $value]];
        } catch (\Exception $e) {
            return [['value' => $value]];
        }
    }

    /**
     * Parse "20 cm" / "20cm" / "20" style values into [numericValue, resolvedUnit].
     * Resolved unit is null when unrecognized — caller should skip in that case.
     */
    private function parseUnitValue(mixed $value, array $map, string $default = 'inches'): array
    {
        preg_match('/([\d.]+)\s*([a-zA-Z"\']*)/', trim((string) $value), $m);
        $num = isset($m[1]) ? (float) $m[1] : (float) $value;
        $raw = ($m[2] ?? '') ?: $default;
        return [$num, $map[strtolower(trim($raw))] ?? null];
    }

    /**
     * Parse loosely-formatted length/width/height text into an Amazon
     * dimensions payload (used as a fallback for unlisted field names).
     */
    private function parseGenericDimensions(mixed $value, array $unitMap): ?array
    {
        preg_match('/(?:length)\s*[:=]?\s*([\d.]+)/i', $value, $ml);
        preg_match('/(?:width|breadth)\s*[:=]?\s*([\d.]+)/i', $value, $mw);
        preg_match('/(?:height)\s*[:=]?\s*([\d.]+)/i', $value, $mh);

        $unit = null;
        if (preg_match('/\b(cm|centimeters?|mm|millimeters?|m|meters?|in|inches?|ft|feet|foot)\b/i', $value, $mu)) {
            $unit = $unitMap[strtolower($mu[1])] ?? strtolower($mu[1]);
        }

        $length = $ml[1] ?? null;
        $width  = $mw[1] ?? null;
        $height = $mh[1] ?? null;

        if (($length === null || $width === null || $height === null)
            && preg_match('/([\d.]+)\s*L\s*x\s*([\d.]+)\s*W\s*x\s*([\d.]+)\s*H\s*(\w+)?/i', $value, $m)
        ) {
            $length = $m[1];
            $width  = $m[2];
            $height = $m[3];
            if (!empty($m[4])) {
                $unit = $unitMap[strtolower($m[4])] ?? strtolower($m[4]);
            }
        }

        if ($length === null || $width === null || $height === null) {
            return null;
        }

        $unit = $unit ?? 'centimeters';

        return [[
            'length' => ['value' => (float) $length, 'unit' => $unit],
            'width'  => ['value' => (float) $width, 'unit' => $unit],
            'height' => ['value' => (float) $height, 'unit' => $unit],
        ]];
    }

    private function closure($value): array
    {
        $value = trim((string) $value);

        return [[
            'type' => [[
                'value' => $value,
                'marketplace_id' => 'ATVPDKIKX0DER',
            ]],
            'marketplace_id' => 'ATVPDKIKX0DER',
        ]];
    }

    // lightsource 
    private function lightSource($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        return [[
            'marketplace_id' => $marketplaceId,
            'type' => [[
                'value' => trim((string) $value),
                'language_tag' => 'en_US',
            ]],
        ]];
    }

    private function maximumHeight($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        return [[
            'value' => (float) $value,
            'unit' => 'inches',
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function minimumHeight($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        return [[
            'value' => (float) $value,
            'unit' => 'inches',
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function dataTransferRate($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        $value = trim((string) $value);

        preg_match('/([\d.]+)\s*(.*)$/i', $value, $matches);

        $number = (float) ($matches[1] ?? 0);
        $unit = strtolower(trim($matches[2] ?? ''));

        $unit = match (true) {
            str_contains($unit, 'gigabit'),
            str_contains($unit, 'gb/s'),
            str_contains($unit, 'gbps')
            => 'gigabits_per_second',

            str_contains($unit, 'megabyte'),
            str_contains($unit, 'mb/s'),
            str_contains($unit, 'mbps')
            => 'megabytes_per_second',

            str_contains($unit, 'megabit')
            => 'megabits_per_second',

            default => 'megabits_per_second',
        };

        return [[
            'value' => $number,
            'unit' => $unit,
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function itemVolume($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        $value = trim((string) $value);

        preg_match(
            '/^\s*([\d.]+)\s*(ml|milliliters?|millilitres?|l|liters?|litres?|cl|centiliters?|dl|deciliters?)\s*$/i',
            $value,
            $matches
        );

        $number = (float) ($matches[1] ?? 0);
        $rawUnit = strtolower($matches[2] ?? '');

        $unit = match (true) {
            in_array($rawUnit, ['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres']) => 'milliliters',
            in_array($rawUnit, ['l', 'liter', 'liters', 'litre', 'litres']) => 'liters',
            in_array($rawUnit, ['cl', 'centiliter', 'centiliters']) => 'centiliters',
            in_array($rawUnit, ['dl', 'deciliter', 'deciliters']) => 'deciliters',
            default => 'liters',
        };

        return [[
            'value' => $number,
            'unit' => $unit,
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function meltingTemperature($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        $value = trim((string) $value);

        preg_match(
            '/(-?[\d.]+)\s*°?\s*(c|celsius|f|fahrenheit|k|kelvin)?/i',
            $value,
            $matches
        );

        $number = (float) ($matches[1] ?? 0);
        $inputUnit = strtolower($matches[2] ?? 'celsius');

        if (in_array($inputUnit, ['f', 'fahrenheit'])) {
            $number = ($number - 32) * 5 / 9;
        } elseif (in_array($inputUnit, ['k', 'kelvin'])) {
            $number = $number - 273.15;
        }

        return [[
            'value' => round($number, 2),
            'unit' => 'degrees_celsius',
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function alcoholContent($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        $value = (float) preg_replace('/[^0-9.]/', '', (string) $value);

        return [[
            'value' => $value,
            'unit' => 'percent_by_volume',
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function runtime($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';

        preg_match( '/([\d.]+)\s*(hours?|minutes?|seconds?)/i',
            trim((string) $value),  $matches     );

        $number = (float) ($matches[1] ?? 0);
        $unit = strtolower($matches[2] ?? 'hours');
        $unit = match (rtrim($unit, 's')) {
            'hour' => 'hours',
            'minute' => 'minutes',
            'second' => 'seconds',
            default => 'hours',
        };

        return [[
            'value' => $number,
            'unit' => $unit,
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function waterResistanceLevel($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';
        $value = strtolower(trim((string) $value));

        $mapped = match ($value) {
            'moisture resistant',
            'moisture_resistant' => 'moisture_resistant',
            'not water resistant',
            'not_water_resistant' => 'not_water_resistant',
            'water repellent',
            'water_repellent' => 'water_repellent',
            'water resistant',
            'water_resistant' => 'water_resistant',
            'waterproof' => 'waterproof',
            default => 'water_resistant',
        };

        return [[
            'value' => $mapped,
            'marketplace_id' => $marketplaceId,
        ]];
    }


    private function tankVolume($value, $marketplaceId = null): array
    {
        $marketplaceId = $marketplaceId ?: 'ATVPDKIKX0DER';
        $value = trim((string) $value);
        preg_match('/^\s*([\d.]+)\s*(ml|milliliters?|l|liters?|cl|centiliters?|dl|deciliters?|gal|gallons?|imperial\s*gallons?|fl\s*oz|fluid\s*ounces?|pints?|quarts?)\s*$/i',
            $value,  $matches  );

        $number = (float) ($matches[1] ?? 0);
        $rawUnit = strtolower(trim($matches[2] ?? ''));

        $unit = match (true) {
            in_array($rawUnit, [ 'ml', 'milliliter', 'milliliters',  ]) => 'milliliters',
            in_array($rawUnit, [ 'l', 'liter',  'liters', ]) => 'liters',
            in_array($rawUnit, [ 'cl', 'centiliter', 'centiliters', ]) => 'centiliters',
            in_array($rawUnit, [ 'dl', 'deciliter', 'deciliters', ]) => 'deciliters',
            in_array($rawUnit, [ 'gal', 'gallon','gallons',  ]) => 'gallons',
            in_array($rawUnit, [ 'imperial gallons', 'imperial gallon', ]) => 'imperial_gallons',
            in_array($rawUnit, [ 'fl oz', 'fluid ounce', 'fluid ounces', ]) => 'fluid_ounces',
            str_starts_with($rawUnit, 'pint') => 'pints',
            str_starts_with($rawUnit, 'quart') => 'quarts',
            default => 'liters',
        };

        return [[
            'value' => $number,
            'unit' => $unit,
            'marketplace_id' => $marketplaceId,
        ]];
    }

    function createEsrbRating($rating, $descriptors, $marketplaceId) 
    {
        $ratings = [
            'adults_only' => 'Adults Only',
            'early_childhood' => 'Early Childhood',
            'everyone' => 'Everyone',
            'everyone_10_plus' => 'Everyone 10+',
            'kids_to_adults' => 'Kids to Adults',
            'mature' => 'Mature',
            'rating_pending' => 'Rating Pending',
            'rating_pending_mature' => 'Rating Pending - Mature',
            'teen' => 'Teen'
        ];

        $descriptorsList = [
            'alcohol_and_tobacco_reference' => 'Alcohol & Tobacco Reference',
            'alcohol_reference' => 'Alcohol Reference',
            'animated_blood' => 'Animated Blood',
            'animated_blood_and_gore' => 'Animated Blood & Gore',
            'animated_violence' => 'Animated Violence',
            'blood' => 'Blood',
            'blood_and_gore' => 'Blood & Gore',
            'cartoon_violence' => 'Cartoon Violence',
            'comic_mischief' => 'Comic Mischief',
            'crude_humor' => 'Crude Humor',
            'drug_and_alcohol_reference' => 'Drug & Alcohol Reference',
            'drug_reference' => 'Drug Reference',
            'edutainment' => 'Edutainment',
            'fantasy_violence' => 'Fantasy Violence',
            'gambling' => 'Gambling',
            'gaming' => 'Gaming',
            'graphic_violence' => 'Graphic Violence',
            'informational' => 'Informational',
            'intense_violence' => 'Intense Violence',
            'language' => 'Language',
            'lyrics' => 'Lyrics',
            'mature_humor' => 'Mature Humor',
            'mature_sexual_themes' => 'Mature Sexual Themes',
            'mild_animated_blood' => 'Mild Animated Blood',
            'mild_animated_violence' => 'Mild Animated Violence',
            'mild_blood' => 'Mild Blood',
            'mild_cartoon_violence' => 'Mild Cartoon Violence',
            'mild_fantasy_violence' => 'Mild Fantasy Violence',
            'mild_language' => 'Mild Language',
            'mild_lyrics' => 'Mild Lyrics',
            'mild_realistic_violence' => 'Mild Realistic Violence',
            'mild_sexual_themes' => 'Mild Sexual Themes',
            'mild_suggestive_themes' => 'Mild Suggestive Themes',
            'mild_violence' => 'Mild Violence',
            'nudity' => 'Nudity',
            'partial_nudity' => 'Partial Nudity',
            'real_gambling' => 'Real Gambling',
            'realistic_blood' => 'Realistic Blood',
            'realistic_blood_and_gore' => 'Realistic Blood & Gore',
            'realistic_violence' => 'Realistic Violence',
            'sexual_content' => 'Sexual Content',
            'sexual_themes' => 'Sexual Themes',
            'sexual_violence' => 'Sexual Violence',
            'simulated_gambling' => 'Simulated Gambling',
            'some_adult_assistance_may_be_needed' => 'Some Adult Assistance May be Needed',
            'strong_language' => 'Strong Language',
            'strong_lyrics' => 'Strong Lyrics',
            'strong_sexual_content' => 'Strong Sexual Content',
            'suggestive_themes' => 'Suggestive Themes',
            'suitable_for_all_users' => 'Suitable For All Users',
            'suitable_for_mature_users' => 'Suitable For Mature Users',
            'tobacco_reference' => 'Tobacco Reference',
            'use_of_alcohol' => 'Use of Alcohol',
            'use_of_alcohol_and_tobacco' => 'Use of Alcohol & Tobacco',
            'use_of_drugs' => 'Use of Drugs',
            'use_of_drugs_and_alcohol' => 'Use of Drugs & Alcohol',
            'use_of_tobacco' => 'Use of Tobacco',
            'violence' => 'Violence',
            'violent_references' => 'Violent References'
        ];

        $normalize = function (string $string): string {
            $string = strtolower($string);
            $string = str_replace(['&', '+'], [' and ', ' plus '], $string);
            $string = preg_replace('/[_-]/', ' ', $string);
            $string = preg_replace('/[^a-z0-9\s]/', '', $string);
            $string = preg_replace('/\s+/', ' ', $string);
            return trim($string);
        };

        // Helper closure: receives $input as a parameter when called below
        $matchText = function (string $input, array $options) use ($normalize): ?string {
            if (array_key_exists($input, $options)) {
                return $input;
            }

            $normalizedInput = $normalize($input);
            if (empty($normalizedInput)) return null;

            $words = array_unique(explode(' ', $normalizedInput));
            $wordsSize = count($words);
            $best = ['key' => null, 'score' => 0];

            foreach ($options as $key => $value) {
                $target = array_unique(explode(' ', $normalize($value)));
                $targetSize = count($target);

                $common = count(array_intersect($words, $target));
                $score = ($wordsSize && $targetSize) 
                    ? (2 * $common) / ($wordsSize + $targetSize) 
                    : 0;

                if ($score > $best['score']) {
                    $best = ['key' => $key, 'score' => $score];
                }
            }

            return $best['score'] >= 0.8 ? $best['key'] : null;
        };

        // 1. Process $rating input
        $matchedRating = $matchText((string) $rating, $ratings) ?: 'everyone';

        // 2. Process $descriptors input
        if (is_string($descriptors)) {
            $descriptors = array_filter(array_map('trim', explode(',', $descriptors)));
        } elseif (!is_array($descriptors)) {
            $descriptors = [];
        }

        $matchedDescriptors = [];
        foreach ($descriptors as $desc) {
            $match = $matchText((string) $desc, $descriptorsList);
            if ($match) {
                $matchedDescriptors[] = $match;
            }
        }

        $matchedDescriptors = array_slice(array_values(array_unique($matchedDescriptors)), 0, 8);

        return [[
            'rating' => $matchedRating,
            'descriptors' => !empty($matchedDescriptors) ? $matchedDescriptors : ['suitable_for_all_users'],
            'marketplace_id' => $marketplaceId,
        ]];
    }

    private function parseBatteryInfo($value, $marketplaceId = null): array
    {
        $battery = [
            'marketplace_id' => $marketplaceId,
        ];

        foreach ((array) $value as $raw) {

            $raw = trim((string) $raw);

            if ($raw === '') {
                continue;
            }

            // Cell
            $cells = [
                'Lithium-Ion'     => 'lithium_ion',
                'Lithium-Metal'   => 'lithium_metal',
                'Lithium-Polymer' => 'lithium_polymer',
                'Alkaline'        => 'alkaline',
                'NiMH'            => 'NiMh',
                'NiCad'           => 'NiCAD',
                'Lead Acid'       => 'lead_acid',
                'Sodium-Ion'      => 'sodium_ion',
                'Wet Alkali'      => 'wet_alkali',
            ];

            foreach ($cells as $label => $cell) {
                if (stripos($raw, $label) !== false) {
                    $battery['cell_composition'] = [['value' => $cell]];
                    break;
                }
            }

            // Weight
            if (preg_match(
                '/(\d+(?:\.\d+)?)\s*(mg|milligrams?|g|grams?|kg|kilograms?|oz|ounces?|lb|lbs|pounds?)/i',
                $raw,
                $m
            )) {
                $battery['weight'] = [[
                    'value' => (float) $m[1],
                    'unit' => match (strtolower($m[2])) {
                        'mg', 'milligram', 'milligrams' => 'milligrams',
                        'kg', 'kilogram', 'kilograms' => 'kilograms',
                        'oz', 'ounce', 'ounces' => 'ounces',
                        'lb', 'lbs', 'pound', 'pounds' => 'pounds',
                        default => 'grams',
                    },
                ]];
            }

            // Capacity
            if (preg_match('/(\d+(?:\.\d+)?)\s*(mAh|Ah)\b/i', $raw, $m)) {
                $battery['capacity'] = [[
                    'value' => (float) $m[1],
                    'unit' => strtolower($m[2]) === 'mah'
                        ? 'milliamp_hours'
                        : 'amp_hours',
                ]];
            }

            // Power
            if (preg_match('/(\d+(?:\.\d+)?)\s*(kWh|Wh|mAh|Ah|VA|V)\b/i', $raw, $m)) {
                $battery['power'] = [[
                    'value' => (float) $m[1],
                    'unit' => match (strtolower($m[2])) {
                        'mah' => 'milliamp_hours',
                        'ah' => 'amp_hours',
                        'wh', 'kwh' => 'watt_hours',
                        'va' => 'volt_amperes',
                        'v' => 'volts',
                    },
                ]];
            }

            // Average life
            if (preg_match(
                '/(?:average\s+life|battery\s+life)\s*:?\s*(\d+(?:\.\d+)?)\s*(seconds?|minutes?|hours?|days?|weeks?|months?|years?)/i',
                $raw,
                $m
            )) {
                $battery['average_life'] = [[
                    'value' => (float) $m[1],
                    'unit' => strtolower($m[2]),
                ]];
            }

            // Talk time
            if (preg_match(
                '/talk\s*time\s*:?\s*(\d+(?:\.\d+)?)\s*(seconds?|minutes?|hours?|days?|weeks?|months?|years?)/i',
                $raw,
                $m
            )) {
                $battery['average_life_talk_time'] = [[
                    'value' => (float) $m[1],
                    'unit' => strtolower($m[2]),
                ]];
            }

            // Charge time
            if (preg_match(
                '/(?:charge\s*time|charging\s*time|charging)\s*:?\s*(\d+(?:\.\d+)?)\s*(cycles?|days?|hours?|minutes?|months?|seconds?|weeks?|years?)/i',
                $raw,
                $m
            )) {
                $battery['charge_time'] = [[
                    'value' => (float) $m[1],
                    'unit' => strtolower($m[2]),
                ]];
            }

            // IEC
            $iec = '14500|18650|CR1025|CR11108|CR1216|CR1220|CR1225|CR123A|CR14250|CR1612|CR1616|CR1620|CR1632|CR17450|CR2|CR2012|CR2016|CR2020|CR2025|CR2032|CR2034|CR2320|CR2430|CR2450|CR927|FR03-AAA|FR6-AA|HR14-C|HR20-D|HR22-E|HR3-AAA|HR6-AA|LR03-AAA|LR1-N|LR14-C|LR20-D|LR41|LR43|LR44|LR48|LR54|LR55|LR6|R03-AAA|R14|R20|R6-AA|U9VL';

            if (preg_match(
                '/(?<![A-Z0-9])(' . $iec . ')(?![A-Z0-9])/i',
                $raw,
                $m
            )) {
                $battery['iec_code'] = [
                    ['value' => strtolower($m[1])]
                ];
            }

            // Description
            $battery['description'] = [[
                'value' => implode(', ', (array) $value),
                'language_tag' => 'en_US',
            ]];
        }

        return [$battery];
    }

}
