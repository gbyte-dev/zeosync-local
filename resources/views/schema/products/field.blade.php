@php
$idreq = '';
$php_errormsg = '';

if (session('errors_amazon')) {

$errors = session('errors_amazon');

if (is_array($errors)) {
$fieldName = strtolower($field['name'] ?? '');

$fieldAlias = [
'externally_assigned_product_identifier' => [
'external_product_id',
'external_product_identifier'
],

'material' => [
'fabric_type'
],

'apparel_size_class' => [
'apparel_size'
],
];

foreach ($errors as $error) {

if (is_array($error)) {

$message = strtolower($error['message'] ?? '');
$path = strtolower($error['path'] ?? '');

$attributeNames = array_map(
'strtolower',
$error['attributeNames'] ?? []
);

$aliases = $fieldAlias[$fieldName] ?? [];

$matched = false;

foreach ($attributeNames as $attribute) {

if (
$attribute === $fieldName ||
in_array($attribute, $aliases, true) ||
str_contains($fieldName, $attribute) ||
str_contains($attribute, $fieldName)
) {
$matched = true;
break;
}
}

if (
$matched ||
$path === $fieldName ||
str_contains($path, $fieldName) ||
str_contains($message, $fieldName)
) {
$php_errormsg = $error['message'] ?? '';
break;
}

} elseif (is_string($error)) {

if (str_contains(strtolower($error), $fieldName)) {
$php_errormsg = $error;
break;
}

}
}
}
}

if (in_array($field['name'], ['item_name', 'item_display_weight', 'region_of_origin'])) {
$field['type'] = 'text';
}

if (in_array($field['name'], ['product_description', 'bullet_point'])) {
$field['type'] = 'textarea';
}

$fieldHint = \App\Support\AmazonFieldHint::get($field['name']);
@endphp

<div class="card-body p-2 row">

    <div class="col-sm-2">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center flex-wrap gap-2">

                    <label class="form-label mb-0 small">
                        {{ $field['title'] }}
                        @if($field['required'])
                        <span class="text-danger">*</span>
                        @endif
                    </label>

                    {{-- Mobile AI Button --}}
                    @if($canUseAiSingleField && in_array($field['type'], ['text','textarea']) && !\Illuminate\Support\Str::contains($field['name'], 'image_locator'))

                    <button type="button"
                        class="btn btn-sm btn-outline-primary ai-field-btn d-inline-block d-md-none"
                        data-field="{{ $field['name'] }}"
                        data-title="{{ $field['title'] }}"
                        data-description="{{ $field['description'] ?? '' }}"
                        data-hint="{{ $fieldHint['example'] ?? '' }}">
                        <img src="{{ asset('images/ai-icon.png') }}" width="18" height="18">
                        Auto Fill
                    </button>

                    @endif

                    {{-- Mobile Error Info --}}
                    @if(!empty($php_errormsg))
                    <span class="ms-2 d-inline-block d-md-none"
                        style="cursor:pointer; font-size:18px;"
                        data-bs-toggle="popover"
                        data-bs-trigger="hover focus"
                        data-bs-placement="top"
                        data-bs-content="{{ $php_errormsg }}"
                        data-bs-template='<div class="popover bg-dark border-secondary shadow" role="tooltip"><div class="popover-arrow"></div><div class="popover-body text-white"></div></div>'>
                        <i class="bi bi-info-circle-fill text-danger"></i>
                    </span>
                    @endif

                </div>
            </div>
        </div>
    </div>

    @if($canUseAiSingleField && in_array($field['type'], ['text','textarea']) && !\Illuminate\Support\Str::contains($field['name'], 'image_locator'))
    <div class="col-sm-8">
        @else
        <div class="col-sm-8">
            @endif

            @switch($field['type'])
            @case('select')
            @include('schema.products.fields.select')
            @break

            @case('textarea')
            @include('schema.products.fields.textarea')
            @break

            @case('boolean')
            @include('schema.products.fields.boolean')
            @break

            @default
            @include('schema.products.fields.text')
            @endswitch

        </div>

        <div class="col-sm-2 d-flex align-items-center" style="margin-top: -29px;">

            @if($canUseAiSingleField && in_array($field['type'], ['text','textarea']) && !\Illuminate\Support\Str::contains($field['name'], 'image_locator'))
            <button type="button"
                class="btn btn-sm btn-outline-primary ai-field-btn d-none d-md-inline-block"
                data-field="{{ $field['name'] }}"
                data-title="{{ $field['title'] }}"
                data-description="{{ $field['description'] ?? '' }}"
                data-hint="{{ $fieldHint['example'] ?? '' }}">
                <img src="{{ asset('images/ai-icon.png') }}" width="18" height="18">
                Auto Fill
            </button>
            @endif

            @if(!empty($php_errormsg))
            <span class="ms-2 d-none d-md-inline-block"
                style="cursor:pointer;font-size:18px;"
                data-bs-toggle="popover"
                data-bs-trigger="hover focus"
                data-bs-placement="left"
                data-bs-content="{{ $php_errormsg }}"
                data-bs-template='<div class="popover bg-dark border-secondary shadow" role="tooltip"><div class="popover-arrow"></div><div class="popover-body text-white"></div></div>'>
                <i class="bi bi-info-circle-fill text-danger"></i>
            </span>
            @endif

        </div>
    </div>