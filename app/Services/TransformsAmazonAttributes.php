<?php
namespace App\Services;

use App\Models\Shop;

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
    /**
     * Transform a raw (name, value) pair into Amazon's expected attribute array.
     * Returns null when the value can't be mapped/validated and should be skipped.
     */
    public function transformAttribute(string $name, mixed $value): ?array
    {
        if(session('active_shop')) {
            $shop = Shop::where('shop', session('active_shop'))->first();
        } else {
            $shop = Shop::where('shop', '!=', '')->first();
        }
  
        $marketplaceId = $shop?->amazon_marketplace_id??'AB8Z5GI65VK9X';
        $weightUnitMap = $this->weightUnitMap();
        $unitMap = $this->lengthUnitMap();
        $voltageUnit = $this->voltageUnitMap();
        $liguidunit = [
            'ml'=>'milliliters','milliliter'=>'milliliters','milliliters'=>'milliliters',
            'l'=>'liters','ltr'=>'liters','liter'=>'liters','liters'=>'liters',
            'fl oz'=>'fluid ounces','fluid ounce'=>'fluid ounces','fluid ounces'=>'fluid ounces',
            'oz'=>'ounces','ounce'=>'ounces','ounces'=>'ounces',
            'gal'=>'gallons','gallon'=>'gallons','gallons'=>'gallons'
        ];

        try{
        // ── Simple flag/type lookups ──────────────────────────────────────
        if (in_array($name, $this->booleanFields())) {
            return [['value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)]];
        }

        if ($name === 'unit_count') {
            preg_match('/([\d.]+)\s*(.*)/i', trim($value), $m);

            return [[
                'value' => (int)($m[1] ?? $value),
                'type' => [
                    'value' => [
                        'count'=>'Count','unit'=>'Count','each'=>'Count','piece'=>'Count','pieces'=>'Count','pc'=>'Count','pcs'=>'Count',
                        'fl oz'=>'Fl Oz','floz'=>'Fl Oz',
                        'oz'=>'Ounce','ounce'=>'Ounce','ounces'=>'Ounce'
                    ][strtolower(trim($m[2] ?? ''))] ?? 'Count',
                    'language_tag' => 'en_US'
                ]
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
        if (in_array($name, ['item_package_weight', 'item_weight', 'item_display_weight', 'maximum_weight_recommendation'])) {
            if (!preg_match('/^([\d.]+)\s*(\w+)$/i', $value, $m)) {
                return null;
            }
            $unit = $weightUnitMap[strtolower(trim($m[2]))] ?? strtolower($m[2]);
            return [['value' => (float) $m[1], 'unit' => $unit]];
        }

         // ── length like  50mm , 50 mm ─────────────────────────────────────────────────────────
        if (in_array($name, ['min_focal_length'])) {
            if (!preg_match('/^\s*([\d]+(?:\.\d+)?)\s*(mm|millimeter|millimeters|cm|centimeter|centimeters|m|meter|meters|in|inch|inches|ft|foot|feet)\s*$/i',
            $value,  $m )) {
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
            return [[ 'diameter' => [[
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
                dd($valueString);
            }

            $unit = $unitMap[strtolower(trim($m[4] ?? ''))] ?? strtolower(trim($m[4] ?? 'inches'));

            return [[
                'length' => ['value' => (float) $m[1], 'unit' => $unit],
                'width'  => ['value' => (float) $m[2], 'unit' => $unit],
                'height' => ['value' => (float) $m[3], 'unit' => $unit],
                'marketplace_id' => $marketplaceId,
            ]];
        }
  
        if($name === 'included_components'){
            $values = array_filter(array_map('trim', explode(',', $value)));
            return array_map(fn($v) => [
                'value' => $v,
                'language_tag' => 'en_US'
            ], $values);
        }

        if (in_array($name ,['fc_shelf_life','maximum_reading_interest_age','minimum_reading_interest_age'])) {
            preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z]+)?/', strtolower(trim($value)), $m);

            return [[
                'value' => (float)($m[1] ?? $value),
                'unit'  => [
                    'day'=>'days','days'=>'days',
                    'week'=>'weeks','weeks'=>'weeks',
                    'month'=>'months','months'=>'months',
                    'year'=>'years','years'=>'years'
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
        if ($name === 'item_depth_width_height') {
            $toInches = [
                'inches' => 1, 'in' => 1,
                'centimeters' => 0.393701, 'cm' => 0.393701,
                'millimeters' => 0.0393701, 'mm' => 0.0393701,
                'feet' => 12, 'ft' => 12,
                'meters' => 39.3701, 'm' => 39.3701,
            ];

            if (!preg_match(
                '/^\s*([\d.]+)\s*[d]?\s*[x×]\s*([\d.]+)\s*[w]?\s*[x×]\s*([\d.]+)\s*[h]?\s*([a-zA-Z"\']+)?\s*$/i',
                $value,  $m   )) {  return null; }

            $unit = strtolower(trim($m[4] ?: 'inches'));
            if (!isset($unitMap[$unit])) {
               $unit = 'inches';
            }

            $f = (int) $toInches[$unit]??1;

            return [[
                'depth'  => ['value' => round((int)$m[1] * $f, 2), 'unit' =>  $unitMap[$unit]??'inches'],
                'width'  => ['value' => round((int)$m[2] * $f, 2), 'unit' =>  $unitMap[$unit]??'inches'],
                'height' => ['value' => round((int)$m[3] * $f, 2), 'unit' =>  $unitMap[$unit]??'inches'],
                'marketplace_id' => $marketplaceId,
            ]];
        }

            // ── L x W  or W*H  or any comination of 2 abeled dimensions ("39L x 17.5W  Centimeters") " ──

        if (in_array($name, $this->getTwoFieldDimensionNames(), true)) {
           return [$this->parseTwoFieldsOnly($value,$marketplaceId)];
        }
        
        // ── seat_* — nested depth array, wide unit enum ─────────────────────
        if (in_array($name, ['seat_depth', 'seat_width', 'seat_height', 'seat'])) {
            [$val, $unit] = $this->parseUnitValue($value, $unitMap);
            if ($unit === null) {
                return null;
            }
            return [[
                'depth' => [['value' => $val, 'unit' => $unit]],
                'marketplace_id' => $marketplaceId,
            ]];
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



        // ── Frame ────────────────────────────────────────────────────────────
        if ($name === 'frame_material') {
            return null; // merged into 'frame' below
        }

        if ($name === 'frame') {
            $colorEnum = ['beige','black','blue','brown','gold','green','grey','multicolor','orange','pink','purple','red','silver','white','yellow'];
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
            $channelMap = ['amazon'=>'AMAZON_NA','amazon_na'=>'AMAZON_NA','fba'=>'AMAZON_NA','default'=>'DEFAULT','defualt'=>'DEFAULT','mfn'=>'DEFAULT','merchant'=>'DEFAULT'];
            $channel = $channelMap[strtolower(trim($value))] ?? 'DEFAULT';
            return [['fulfillment_channel_code' => $channel, 'quantity' => 0, 'lead_time_to_ship_max_days' => 30]];
        }

        if ($name === 'externally_assigned_product_identifier') {
            $decoded = json_decode($value, true);
            return $decoded && isset($decoded['type'], $decoded['value'])
                ? [['type' => $decoded['type'], 'value' => $decoded['value']]]
                : [['type' => 'upc', 'value' => '09785512' . random_int(1000, 9999)]];
        }

        if ($name === 'parentage_level') {
            return in_array($value, ['parent', 'child']) ? [['value' => $value]] : null;
        }

        if ($name === 'language') {
            return [['type' => 'en_US', 'value' => 'en_US']];
        }

        if ($name === 'num_batteries') {
            $d = json_decode($value, true);
            return $d ? [[$d]] : null;
        }

        if ($name === 'country_of_origin') {
            $normalized = strtolower(trim((string) $value));
            $mapped = $this->countryMap()[$normalized] ?? null;
            return [['value' => $mapped ?? 'US']];
        }

        if ($name === 'water_resistance_level') {
            $valid = ['water_resistant','waterproof','ipx4','ipx5','ipx6','ipx7','ipx8','ip67','ip68','not_water_resistant'];
            $normalized = ['waterproof' => 'ipx8', 'water proof' => 'ipx8'][$value] ?? $value;
            return in_array($normalized, $valid) ? [['value' => $normalized]] : null;
        }

        if ($name === 'supplier_declared_dg_hz_regulation') {
            $valid = ['not_applicable','un3480','un3481','un3090','un3091','iata_section_ii','iata_section_ib'];
            return [['value' => in_array(strtolower($value), $valid) ? strtolower($value) : 'not_applicable']];
        }

        if ($name === 'contains_battery_or_cell') {
            $map = ['battery'=>'contains_battery','contains_battery'=>'contains_battery','lithium_ion'=>'contains_lithium_ion_battery','lithium_metal'=>'contains_lithium_metal_battery','no'=>'does_not_contain_a_battery','false'=>'does_not_contain_a_battery','does_not_contain_battery'=>'does_not_contain_a_battery','none'=>'does_not_contain_a_battery'];
            return [['value' => $map[strtolower($value)] ?? 'contains_battery']];
        }

        if ($name === 'sleeve') {
            return [['type' => [['value' => $value, 'language_tag' => 'en_US']]]];
        }

        if ($name === 'neck') {
            return [['neck_style' => [['value' => $value, 'language_tag' => 'en_US']]]];
        }

        if ($name === 'package_level') {
            $map = ['unit'=>'each','each'=>'each','pack'=>'pack','set'=>'set'];
            return [['value' => $map[strtolower($value)] ?? 'each']];
        }

        if (in_array($name, ['capacity','liquid_volume'])) {
            preg_match('/(\d+(?:\.\d+)?)\s*([a-zA-Z\s]+)?/', strtolower(trim($value)), $m);
            return [[
                'value' => (float)($m[1] ?? $value),
                'unit'  => $liguidunit[$m[2] ?? 'ml'] ?? 'milliliters'
            ]];
        }

        if (in_array($name, ['voltage', 'input_voltage','wattage'])) {
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

        if ($name === 'manufacturer') {
            return [['value' => mb_substr($value, 0, 100)]];
        }

        if ($name === 'condition_type') {
            $map = ['new_new'=>'new_new','new'=>'new_new','used_good'=>'used_good','used_very_good'=>'used_very_good','used_acceptable'=>'used_acceptable','collectible_good'=>'collectible_good'];
            return [['value' => $map[strtolower($value)] ?? 'new_new']];
        }


        if (in_array($name, ['subject', 'subject_code'])) {
            return [['value' => $value , "type" => "bisac_description", "language_tag"=>"en_US"]];
        }

        if ($name === 'regulatory_compliance_certification') {
            return [['value' => $value , "regulation_type" => "ul_cetrification_no", "marketplace_id"=>$marketplaceId]];
        }
        
        if ($name === 'variation_theme') {
            $upper = strtoupper($value);
            $validThemes = ['COLOR','COMPATIBLE_DEVICES','KEYBOARD_LAYOUT','COLOR/COMPATIBLE_DEVICES','COLOR/KEYBOARD_LAYOUT','COMPATIBLE_DEVICES/KEYBOARD_LAYOUT'];
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
                        'value' => (string) $widthValue??20,
                        'unit' => 'millimeters',
                        'language_tag' => 'en_US',
                    ]];
                
                    $lensPayload['material'] = [[
                        'value' => trim((string) $materialValue??'polycarbonate'),
                        'language_tag' => 'en_US',
                    ]];
              
                    $lensPayload['color'] = [[
                        'value' => trim((string) $colorValue ??'black'),
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
                'black' => 'Black', 'blue' => 'Blue', 'brown' => 'Brown', 'gold' => 'Gold',
                'green' => 'Green', 'grey' => 'Grey', 'gray' => 'Gray', 'multicolor' => 'Multicolor',
                'orange' => 'Orange', 'pink' => 'Pink', 'purple' => 'Purple', 'red' => 'Red',
                'silver' => 'Silver', 'white' => 'White', 'yellow' => 'Yellow',
            ];
            $finalColor = $colorMap[strtolower($normalizedColor)] ?? ucfirst(strtolower($normalizedColor));
            return [['value' => $finalColor]];
        }

        if ($name === 'polarization_type') {
            // enum unknown — passing raw value through for now
            return [['value' => strtolower(trim((string) $value))]];
        }

        return [['value' => $value]];
        }catch(\Exception $e){
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

    private function parseTwoFieldsOnly(string $value , $marketplaceId): ?array
    {
        if (!preg_match(
                '/([\d.]+)\s*(L|W|H|D)\s*[x×*]\s*([\d.]+)\s*(L|W|H|D)\s*(cm|centimeters?|mm|millimeters?|m|meters?|in|inches?|ft|feet|foot)?/i',
                $value,
                $m
            )) {
                return null;
            }

        $unit = strtolower($m[5] ?? 'cm');
        $unitMap = $this->lengthUnitMap(); 
        $unit = $unitMap[$unit] ?? 'centimeters';
        $result = [];
        $map = [ 'L' => 'length',  'W' => 'width',  'H' => 'height' , 'D' => 'depth', ];
        $result[$map[strtoupper($m[2])]] = [
            'value' => (float) $m[1],
            'unit'  => $unit,
        ];

        $result[$map[strtoupper($m[4])]] = [
            'value' => (float) $m[3],
            'unit'  => $unit,
        ];

        $result['marketplace_id'] = $marketplaceId;

        return $result;
    }

    private function booleanFields(): array
    {
        return [
            'supplier_declared_has_product_identifier_exemption',
            'batteries_required',
            'batteries_included',
            'is_refurbished',
            'has_replaceable_battery',
            'is_battery_non_spillable',
            'battery_contains_free_unabsorbed_liquid',
            'has_multiple_battery_powered_components',
            'ships_globally',
            'gpsr_safety_attestation',
            'is_oem_sourced_product',
            'is_this_product_subject_to_buyer_age_restrictions',
            'is_green_purchasing_law_compliant',
            'has_less_than_30_percent_state_of_charge',
            'skip_offer',
        ];
    }

    private function integerFields(): array
    {
        return [
            'number_of_items',
            'item_package_quantity',
            'button_quantity',
            'number_of_batteries',
            'number_of_lithium_metal_cells',
            'number_of_lithium_ion_cells',
            'number_of_packs',
            'total_usb_2_0_ports',
            'unit_count',
        ];
    }

    private function languageTagFields(): array
    {
        return ['item_name', 'product_description', 'care_instructions', 'generic_keyword'];
    }

    private function flatDimensionAttributes(): array
    {
        return [
            'item_length',
            'item_width',
            'item_height',
            'adjustable_seat_depth_maximum',
            'adjustable_seat_depth_minimum',
            'adjustable_seat_width_maximum',
            'adjustable_seat_width_minimum',
            'adjustable_seat_height_maximum',
            'adjustable_seat_height_minimum',
        ];
    }

    private function weightUnitMap(): array
    {
        return [
            'grams'=>'grams','gram'=>'grams','g'=>'grams','gr'=>'grams','gm'=>'grams','gms'=>'grams',
            'kilograms'=>'kilograms','kilogram'=>'kilograms','kilo'=>'kilograms','kilos'=>'kilograms','kg'=>'kilograms','kgs'=>'kilograms',
            'pounds'=>'pounds','pound'=>'pounds','lb'=>'pounds','lbs'=>'pounds',
            'ounces'=>'ounces','ounce'=>'ounces','oz'=>'ounces',
            'milligrams'=>'milligrams','mg'=>'milligrams',
            'micrograms'=>'micrograms','mcg'=>'micrograms',
            'metric tons'=>'metric_tons','tonne'=>'metric_tons','t'=>'metric_tons',
        ];
    }

    private function voltageUnitMap(): array
    {
        return [
            'volt' => 'volts',
            'volts' => 'volts',
            'v' => 'volts',
            'vac' => 'volts_of_alternating_current',
            'vac.' => 'volts_of_alternating_current',
            'ac volt' => 'volts_of_alternating_current',
            'ac volts' => 'volts_of_alternating_current',
            'volt ac' => 'volts_of_alternating_current',
            'volts ac' => 'volts_of_alternating_current',
            'volts of alternating current' => 'volts_of_alternating_current',
            'vdc' => 'volts_of_direct_current',
            'vdc.' => 'volts_of_direct_current',
            'dc volt' => 'volts_of_direct_current',
            'dc volts' => 'volts_of_direct_current',
            'volt dc' => 'volts_of_direct_current',
            'volts dc' => 'volts_of_direct_current',
            'volts of direct current' => 'volts_of_direct_current',
            'millivolt' => 'millivolts',
            'millivolts' => 'millivolts',
            'mv' => 'millivolts',
            'microvolt' => 'microvolts',
            'microvolts' => 'microvolts',
            'µv' => 'microvolts',
            'uv' => 'microvolts',
            'nanovolt' => 'nanovolts',
            'nanovolts' => 'nanovolts',
            'nv' => 'nanovolts',
            'kilovolt' => 'kilovolts',
            'kilovolts' => 'kilovolts',
            'kv' => 'kilovolts',
             // Watts
            'watt' => 'watts',
            'watts' => 'watts',
            'w' => 'watts',
            'kilowatt' => 'kilowatts',
            'kilowatts' => 'kilowatts',
            'kw' => 'kilowatts',
            'watt hour' => 'watt_hours',
            'watt hours' => 'watt_hours',
            'wh' => 'watt_hours',
            'kilowatt hour' => 'kilowatt_hours',
            'kilowatt hours' => 'kilowatt_hours',
            'kwh' => 'kilowatt_hours',
            'milliwatt' => 'milliwatts',
            'milliwatts' => 'milliwatts',
            'mw' => 'milliwatts',
            'microwatt' => 'microwatts',
            'microwatts' => 'microwatts',
            'µw' => 'microwatts',
            'uw' => 'microwatts',
            'nanowatt' => 'nanowatts',
            'nanowatts' => 'nanowatts',
            'nw' => 'nanowatts',
            'picowatt' => 'picowatts',
            'picowatts' => 'picowatts',
            'pw' => 'picowatts',
            'mah' => 'milliamp_hours',
            'milliamp hour' => 'milliamp_hours',
            'milliamp hours' => 'milliamp_hours',
        ];
    }

    private function lengthUnitMap(): array
    {
        return [

            // Millimeters
            'mm' => 'millimeters',
            'millimeter' => 'millimeters',
            'millimeters' => 'millimeters',
            'millimetre' => 'millimeters',
            'millimetres' => 'millimeters',
            'millimeters.' => 'millimeters',
            'millimetre.' => 'millimeters',
            'mms' => 'millimeters',
            'milli meter' => 'millimeters',
            'milli meters' => 'millimeters',

            // Centimeters
            'cm' => 'centimeters',
            'cms' => 'centimeters',
            'centimeter' => 'centimeters',
            'centimeters' => 'centimeters',
            'centimetre' => 'centimeters',
            'centimetres' => 'centimeters',
            'centi meter' => 'centimeters',
            'centi meters' => 'centimeters',
            'cm.' => 'centimeters',

            // Meters
            'm' => 'meters',
            'mt' => 'meters',
            'mts' => 'meters',
            'mtr' => 'meters',
            'mtrs' => 'meters',
            'meter' => 'meters',
            'meters' => 'meters',
            'metre' => 'meters',
            'metres' => 'meters',
            'meter.' => 'meters',
            'metre.' => 'meters',

            // Inches
            'in' => 'inches',
            'ins' => 'inches',
            'inch' => 'inches',
            'inches' => 'inches',
            'inch.' => 'inches',
            '"' => 'inches',

            // Feet
            'ft' => 'feet',
            'fts' => 'feet',
            'foot' => 'feet',
            'feet' => 'feet',
            'ft.' => 'feet',
            "'" => 'feet',

            // Yards
            'yd' => 'yards',
            'yds' => 'yards',
            'yard' => 'yards',
            'yards' => 'yards',
            'yd.' => 'yards',
        ];
    }

    private function countryMap(): array
    {
        return [
            'cn' => 'CN', 'china' => 'CN',
            'us' => 'US', 'usa' => 'US', 'united states' => 'US', 'united states of america' => 'US',
            'in' => 'IN', 'india' => 'IN',
            'de' => 'DE', 'germany' => 'DE',
            'jp' => 'JP', 'japan' => 'JP',
            'kr' => 'KR', 'republic of korea' => 'KR',
            'tw' => 'TW', 'taiwan' => 'TW',
            'vn' => 'VN', 'vietnam' => 'VN',
            'th' => 'TH', 'thailand' => 'TH',
            'mx' => 'MX', 'mexico' => 'MX',
            'gb' => 'GB', 'united kingdom' => 'GB', 'united kingdom of great britain and northern ireland' => 'GB',
        ];
    }

    private function getTwoFieldDimensionNames(): array
    {
        return [
            'item_length_width',
            'item_width_length',
            'item_length_height',
            'item_height_length',
            'item_length_depth',
            'item_depth_length',
            'item_width_height',
            'item_height_width',
            'item_width_depth',
            'item_depth_width',
            'item_height_depth',
            'item_depth_height',
        ];
    }
}
