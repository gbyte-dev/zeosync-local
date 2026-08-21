@php
$value = '';
$extramsg = '';

if (isset($prodAttri)) {
$value = optional(
$prodAttri->firstWhere('attribute_name', $field['name'])
)->attribute_value;
}

if (
isset($prodAttrijson) &&
array_key_exists($field['name'], $prodAttrijson)
) {
$value = $prodAttrijson[$field['name']];
}

if (
$field['name'] === 'child_parent_sku_relationship' &&
isset($productshow) &&
$productshow->status !== 'draft'
) {
$value = $productshow->sku;
}

if (is_array($value)) {
$extractValue = function ($data) use (&$extractValue) {
if (is_scalar($data)) {
return (string) $data;
}

if (is_array($data)) {
foreach ($data as $item) {
$result = $extractValue($item);

if ($result !== '') {
return $result;
}
}
}

if (is_object($data)) {
foreach (get_object_vars($data) as $item) {
$result = $extractValue($item);

if ($result !== '') {
return $result;
}
}
}

return '';
};

$value = $extractValue($value);
}

if (is_object($value)) {
$value = '';
}

if ($value === null) {
$value = '';
}

if (!is_string($value)) {
$value = is_scalar($value) ? (string) $value : '';
}

if ($field['name'] === 'item_package_dimensions') {
$extramsg = 'Use format 39L x 17.5W x 3H Centimeters';
}

if ($field['name'] === 'size') {
$extramsg = 'Please use in format 10 cm or any length quantity';
}

if ($field['name'] === 'item_package_weight') {
$extramsg = 'Please use in format 10 grams or any weight quantity';
}

if ($field['name'] === 'item_length_width_height') {
$extramsg = 'Please use in format 39L x 17.5W x 3H Centimeters';
}

if ($field['name'] === 'item_weight') {
$extramsg = 'Please use in format 10 grams or any weight quantity';
}

if (
$field['name'] === 'item_dimensions' ||
$field['name'] === 'item_display_dimensions'
) {
$extramsg = 'Please use in format 39L x 17.5W x 3H Centimeters';
}

if ($field['name'] === 'fc_shelf_life') {
$extramsg = 'Please use only days like 100 , 365 for year and other';
}

if ($field['name'] === 'ring') {
$extramsg = 'Please use only numbers like 6.5 , 7 , 8.5 etc';
}

if ($field['name'] === 'deck') {
$extramsg = 'Please use in format 39L x 17.5W Inches';
}

if ($field['name'] === 'wheel') {
$extramsg = 'Please use Wheel Size for this in format 60 Millimetres';
}

if ($field['name'] === 'lens') {
$extramsg = 'Please use in format {"width":20,"material":"polycarbonate","color":"black"}';
}

if ($field['name'] === 'memory_storage_capacity') {
$extramsg = "Please use in format like 16GB ['TB' ,'bytes']";
}

if ($field['name'] === 'maximum_display_brightness') {
$extramsg = 'Please use in format like 350 nits';
}

if ($field['name'] === 'ram_memory') {
$extramsg = 'Please use in format like 16GB DDR4';
}

if ($field['name'] === 'memory_type') {
$extramsg = 'Please use in format like DDR4 , DDR5 , LPDDR4X etc';
}

if ($field['name'] === 'memory_clock_speed') {
$extramsg = 'Please use in format like 3200 MHz';
}

if ($field['name'] === 'flash_memory') {
$extramsg = 'Please use in format like 16GB';
}

if ($field['name'] === 'graphics_ram') {
$extramsg = 'Please use in format like 16GB ddr4';
}

if ($field['name'] === 'hard_disk') {
$extramsg = 'Please use in format like 1TB HDD or 512GB SSD';
}

if ($field['name'] === 'cpu_model') {
$extramsg = 'Please use in format like `Intel Core i7 1165G7 , 3.2GHz` or `AMD Ryzen 5 5600X , 3.2 GHz` (comma separated )';
}

if ($field['name'] === 'battery') {
$extramsg = "Please use in format like 'Lithium-Ion, 50g, 5000mAh";
}

if ($field['name'] === 'item_length_width_thickness') {
$extramsg = 'Please use in format 39L x 17.5W x 3T Centimeters';
}

if ($field['name'] === 'display') {
$extramsg = 'Please use in format like 15.6 inch FHD IPS 1920x1080 144Hz';
}


if ($field['name'] === 'lithium_battery') {
$extramsg = 'Please use format 50 watt_hours | 0.5 grams';
}

if ($field['name'] === 'color_gamut') {
$extramsg = 'Please use in format like 70% NTSC';
}

if ($field['name'] === 'externally_assigned_product_identifier') {
$extramsg = 'Please use in format like EAN:1234567890123 or UPC:123456789012 or ISBN:978-3-16-148410-0';
}

if ($field['name'] === 'item_density') {
$extramsg = 'Please use in format like 27 lbs per cubic ft';
}

$idreq = !empty($field['required']) ? 'required' : '';
@endphp

<div class="mb-2">

    <input
        {{ $idreq }}
        type="text"
        name="attributes[{{ $field['name'] }}]"
        class="form-control form-control-sm"
        value="{{ $value }}"
        placeholder="{{ \Illuminate\Support\Str::limit(trim(($field['description'] ?? '') . ' ' . ($extramsg ?? '')), 55) }}"
        style="font-size: small;
        @if(!empty($php_errormsg))
            border:3px solid #dc3545 !important;
            background:#fff0f0 !important;
        @else
            border:1px solid #aaaaeb !important;
        @endif
        ">

    @if(!empty($field['description']) || !empty($extramsg) || !empty($fieldHint))
    <div class="form-text text-dark mt-1 clearfix">

        <span>
            {{ $field['description'] ?? '' }} {{ $extramsg }}
        </span>

        @if(!empty($fieldHint))
        <span class="float-end text-primary">
            <strong>{{ $fieldHint['title'] }}:</strong>
            {{ $fieldHint['example'] }}
        </span>
        @endif

    </div>
    @endif

</div>