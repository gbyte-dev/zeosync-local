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

if($field['name'] == 'child_parent_sku_relationship' && isset($productshow) && $productshow->status != 'draft'){
    $value = $productshow->sku;
}

if($field['name'] == 'item_package_dimensions'){
    $extramsg = 'Use format 39L x 17.5W x 3H Centimeters';
}

if($field['name'] == 'size'){
    $extramsg = 'Please use in format 10 cm or any length quantity';
}

if($field['name'] == 'item_package_weight'){
    $extramsg = 'Please use in format 10 grams or any weight quantity';
}

if($field['name'] == 'item_length_width_height'){
    $extramsg = 'Please use in format 39L x 17.5W x 3H Centimeters';
}

if($field['name'] == 'item_weight'){
    $extramsg = 'Please use in format 10 grams or any weight quantity';
}

if($field['name'] == 'item_dimensions'){
    $extramsg = 'Please use in format 39L x 17.5W x 3H Centimeters';
}

if($field['name'] == 'fc_shelf_life'){
    $extramsg = 'Please use only days like 100 , 365 for year and other';
}

if($field['name'] == 'ring'){
    $extramsg = 'Please use only numbers like 6.5 , 7 , 8.5 etc';
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
        style="font-size: small ;
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