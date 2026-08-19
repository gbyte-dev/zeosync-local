php
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

if ($field['name'] == 'child_parent_sku_relationship' && isset($productshow) && $productshow->status != 'draft') {
$value = $productshow->sku;
}

if (is_array($value)) {
$value = collect($value)
->flatten()
->filter(fn ($item) => $item !== null && $item !== '')
->first() ?? '';
}

if (is_object($value)) {
$value = (string) ($value->value ?? $value->name ?? '');
}

$value = is_scalar($value) ? (string) $value : '';

if ($field['name'] == 'item_package_dimensions') {
$extramsg = 'Use format 39L x 17.5W x 3H Centimeters';
}

if ($field['name'] == 'size') {
$extramsg = 'Please use in format 10 cm or any length quantity';
}

if ($field['name'] == 'item_package_weight') {
$extramsg = 'Please use in format 10 grams or any weight quantity';
}

if ($field['name'] == 'item_length_width_height') {
$extramsg = 'Please use in format 39L x 17.5W x 3H Centimeters';
}

if ($field['name'] == 'item_weight') {
$extramsg = 'Please use in format 10 grams or any weight quantity';
}

if ($field['name'] == 'item_dimensions' || $field['name'] == 'item_display_dimensions') {
$extramsg = 'Please use in format 39L x 17.5W x 3H Centimeters';
}

if ($field['name'] == 'fc_shelf_life') {
$extramsg = 'Please use only days like 100 , 365 for year and other';
}

if ($field['name'] == 'ring') {
$extramsg = 'Please use only numbers like 6.5 , 7 , 8.5 etc';
}

if ($field['name'] == 'deck') {
$extramsg = 'Please use in format 39L x 17.5W Inches';
}

if ($field['name'] == 'wheel') {
$extramsg = 'Please use Wheel Size for this in format 60 Millimetres';
}

if ($field['name'] == 'lens') {
$extramsg = 'Please use in format {"width":20,"material":"polycarbonate","color":"black"}';
}

if ($field['name'] == 'memory_storage_capacity') {
$extramsg = "Please use in format like 16GB ['TB' ,'bytes']";
}

if ($field['name'] == 'maximum_display_brightness') {
$extramsg = 'Please use in format like 350 nits';
}

if ($field['name'] == 'ram_memory') {
$extramsg = 'Please use in format like 16GB DDR4';
}

if ($field['name'] == 'memory_type') {
$extramsg = 'Please use in format like DDR4 , DDR5 , LPDDR4X etc';
}

if ($field['name'] == 'memory_clock_speed') {
$extramsg = 'Please use in format like 3200 MHz';
}

if ($field['name'] == 'flash_memory') {
$extramsg = 'Please use in format like 16GB';
}

if ($field['name'] == 'graphics_ram') {
$extramsg = 'Please use in format like 16GB ddr4';
}

if ($field['name'] == 'hard_disk') {
$extramsg = 'Please use in format like 1TB HDD or 512GB SSD';
}

if ($field['name'] == 'cpu_model') {
$extramsg = 'Please use in format like Intel Core i7-1165G7 or AMD Ryzen 5 5600X';
}

if ($field['name'] == 'battery') {
$extramsg = "Please use in format like 'Lithium-Ion";
}

if ($field['name'] == 'display') {
$extramsg = 'Please use in format like Full HD 1920 × 1080 IPS Anti-Glare';
}

if ($field['name'] == 'color_gamut') {
$extramsg = 'Please use in format like 70% NTSC';
}

$idreq = $field['required'] ? 'required' : '';
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

    @if(!empty($field['description']) || !empty($extramsg) || $fieldHint)
    <div class="form-text text-dark mt-1 clearfix" style="font-size:11px;">
        <span>
            {{ $field['description'] ?? '' }} {{ $extramsg }}
        </span>

        @if($fieldHint)
        <span class="float-end text-primary">
            <strong>{{ $fieldHint['title'] }}:</strong>
            {{ $fieldHint['example'] }}
        </span>
        @endif
    </div>
    @endif

</div>