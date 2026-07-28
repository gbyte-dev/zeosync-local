@php

$value = '';
$extramsg = '';

if(isset($prodAttri)){
$value = optional($prodAttri->firstWhere('attribute_name', $field['name']))->attribute_value;
$value = str_replace('"', '', $value);
}

if(isset($prodAttrijson) && isset($prodAttrijson[$field['name']])){
$value = $prodAttrijson[$field['name']];
$value = str_replace('"', '', $value);
}

if($field['name'] == 'fc_shelf_life'){
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
        style="font-size: small ; 
@if(!empty($php_errormsg))
    border:3px solid #dc3545 !important;
    background:#fff0f0 !important;
@else
 border: 1px solid ;
@endif
">{{ $value }}</textarea>

    @if(!empty($field['description']) || !empty($extramsg) || $fieldHint)
    <div class="form-text text-dark  d-flex justify-content-between align-items-start"
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
