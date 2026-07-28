@php

$value = '';

if(isset($prodAttri)){
$value = optional($prodAttri->firstWhere('attribute_name', $field['name']))->attribute_value;
$value = str_replace('"', '', $value);
}

if(isset($prodAttrijson) && isset($prodAttrijson[$field['name']])){
$value = $prodAttrijson[$field['name']];
$value = str_replace('"', '', $value);
}

@endphp

<div class="mb-2">

    <select
        name="attributes[{{ $field['name'] }}]"
        {{ $idreq }}
        class="form-select form-select-sm"
        style="font-size: small ;
    @if(!empty($php_errormsg))
        border:3px solid #dc3545 !important;
        background:#fff0f0 !important;
    @else
        border:1px solid;
    @endif
">

        <option value="">
            {{ \Illuminate\Support\Str::limit($field['description'] ?? 'Select', 55) }}
        </option>

        <option value="true" {{ $value == 'true' ? 'selected' : '' }}>
            Yes
        </option>

        <option value="false" {{ $value == 'false' ? 'selected' : '' }}>
            No
        </option>

    </select>

    @if(!empty($field['description']) || $fieldHint)

    <div class="form-text mt-1 d-flex justify-content-between align-items-start"
        style="font-size:11px;">

        <div class="text-dark pe-2">
            {{ $field['description'] }}
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