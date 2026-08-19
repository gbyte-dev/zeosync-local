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

if ($value === null) {
$value = '';
}

if (!is_string($value)) {
$value = is_scalar($value) ? (string) $value : '';
}

$value = str_replace('"', '', $value);

if ($field['name'] == 'fc_shelf_life') {
$extramsg = 'Please use only days like 100 , 365 for year and other';
}

@endphp

<div class="mb-2">

    <textarea
        {{ $idreq }}
        name="attributes[{{ $field['name'] }}]"
        class="form-control form-control-sm"
        rows="2"
        placeholder="{{ \Illuminate\Support\Str::limit(trim(($field['description'] ?? '') . ' ' . ($extramsg ?? '')), 55) }}"
        style="font-size: small;
@if(!empty($php_errormsg))
    border:3px solid #dc3545 !important;
    background:#fff0f0 !important;
@else
    border:1px solid #aaaaeb !important;
@endif
">{{ $value }}</textarea>

    @if(!empty($field['description']) || !empty($extramsg) || $fieldHint)
    <div class="form-text text-dark d-flex justify-content-between align-items-start"
        style="font-size:11px;">

        <div class="pe-2">
            {{ $field['description'] ?? '' }} {{ $extramsg }}
        </div>

        @if($fieldHint)
        <div class="text-primary text-end flex-shrink-0 ms-2">
            <strong>{{ $fieldHint['title'] }}:</strong>
            {{ $fieldHint['example'] }}
        </div>
        @endif

    </div>
    @endif

</div>
