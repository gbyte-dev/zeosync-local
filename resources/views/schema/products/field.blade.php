@php
$hiddenFields = [
'merchant_shipping_group',
'fulfillment_availability',
'taa_compliant_country',
'baa_taa_compliance_acknowledgement',
'baa_taa_regulation_compliance',
'government_contract_information',
'is_green_purchasing_law_compliant',
'league_name',
'team_name',
'government_contract_information',
];

$hiddenAiFields = [
'item_name',
'externally_assigned_product_identifier',
'merchant_suggested_asin',
'list_price',
'product_tax_code',
'safety_data_sheet_url',
'dsa_responsible_party_address',
];

if (in_array($field['name'], $hiddenFields, true)) {
return;
}
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
$attributeNames = array_map('strtolower', $error['attributeNames'] ?? [] );
$aliases = $fieldAlias[$fieldName] ?? [];
$matched = false;

foreach ($attributeNames as $attribute) {
if (
$attribute === $fieldName ||
in_array($attribute, $aliases, true)
) {
$matched = true;
break;
}
}

if (
$matched ||
$path === $fieldName
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
$isImageField = \Illuminate\Support\Str::contains($field['name'], 'image_locator');
$showAiButton = $canUseAiSingleField
&& in_array($field['type'], ['text', 'textarea'])
&& !$isImageField
&& !in_array($field['name'], $hiddenAiFields, true);
$showImagePickerButton = $isImageField;
@endphp

<style>
    .amazon-field-suggestions {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 5px;
        line-height: 1.2;
    }

    .amazon-suggestion-label {
        flex: 0 0 auto;
        font-size: 11px;
        font-weight: 500;
        color: #6c757d;
    }

    .amazon-suggestion-list {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
        min-width: 0;
    }

    .amazon-suggestion-text {
        display: inline-block;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 11px;
        font-weight: 500;
        color: #2878c8;
        cursor: pointer;
    }

    .amazon-suggestion-text:hover {
        color: #155fa0;
        text-decoration: underline;
    }

    .amazon-suggestion-separator {
        color: #adb5bd;
        font-size: 10px;
    }

    .amazon-suggestion-separator {
        color: #adb5bd;
        font-size: 10px;
        margin: 0 1px;
    }

    .amazon-custom-tooltip {
        position: fixed;
        z-index: 999999;
        width: auto;
        max-width: min(360px, calc(100vw - 24px));
        padding: 7px 9px;
        background: #212529;
        color: #fff;
        border-radius: 4px;
        font-size: 11px;
        line-height: 1.4;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
        text-align: left;
        pointer-events: none;
        box-sizing: border-box;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }
</style>

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

                    {{-- Mobile AI / Image Picker Button --}}
                    @if($showAiButton)

                    <button type="button"
                        class="btn btn-sm btn-outline-primary ai-field-btn d-inline-block d-md-none"
                        data-field="{{ $field['name'] }}"
                        data-title="{{ $field['title'] }}"
                        data-description="{{ $field['description'] ?? '' }}"
                        data-hint="{{ $fieldHint['example'] ?? '' }}">
                        <img src="{{ asset('images/ai-icon.png') }}" width="18" height="18">
                        Auto Fill
                    </button>

                    @elseif($showImagePickerButton)

                    <button type="button"
                        class="btn btn-sm btn-outline-success image-picker-btn d-inline-block d-md-none"
                        data-field="{{ $field['name'] }}"
                        data-picker-url="{{ route('shopify.image-picker-images') }}">
                        <i class="bi bi-images"></i>
                        Select Image
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

    @if($showAiButton || $showImagePickerButton)
    <div class="col-sm-8">
        @else
        <div class="col-sm-8">
            @endif
            @if(
            in_array($field['type'], ['text', 'textarea'], true) &&
            !empty($fieldSuggestions[$field['name']])
            )
            <div class="amazon-field-suggestions">
                <span class="amazon-suggestion-label">Suggestions:</span>

                <div class="amazon-suggestion-list">
                    @foreach($fieldSuggestions[$field['name']] as $suggestion)
                    <span
                        class="amazon-suggestion-text field-suggestion-btn"
                        data-field="{{ $field['name'] }}"
                        data-value="{{ $suggestion }}"
                        data-tooltip="{{ $suggestion }}">
                        {{ \Illuminate\Support\Str::limit($suggestion, 20) }}
                    </span>

                    @if(!$loop->last)
                    <span class="amazon-suggestion-separator">||</span>
                    @endif
                    @endforeach
                </div>
            </div>
            @endif

            @php
            $customMultiselectFields = [];
            @endphp

            @if(in_array($field['name'], $customMultiselectFields, true))

            @include('schema.products.fields.multiselect')

            @else

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
            @break

            @endswitch

            @endif
        </div>

        <div class="col-sm-2 d-flex align-items-center" style="margin-top: -29px;">

            @if($showAiButton)
            <button type="button"
                class="btn btn-sm btn-outline-primary ai-field-btn d-none d-md-inline-block"
                data-field="{{ $field['name'] }}"
                data-title="{{ $field['title'] }}"
                data-description="{{ $field['description'] ?? '' }}"
                data-hint="{{ $fieldHint['example'] ?? '' }}">
                <img src="{{ asset('images/ai-icon.png') }}" width="18" height="18">
                Auto Fill
            </button>
            @elseif($showImagePickerButton)
            <button type="button"
                class="btn btn-sm btn-outline-success image-picker-btn d-none d-md-inline-block"
                data-field="{{ $field['name'] }}"
                data-picker-url="{{ route('shopify.image-picker-images') }}">
                <i class="bi bi-images"></i>
                Select Image
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let tooltip = null;

            document.querySelectorAll('.amazon-suggestion-text').forEach(function(element) {

                element.addEventListener('mouseenter', function() {
                    const text = this.dataset.tooltip;

                    if (!text) {
                        return;
                    }

                    if (tooltip) {
                        tooltip.remove();
                        tooltip = null;
                    }

                    tooltip = document.createElement('div');
                    tooltip.className = 'amazon-custom-tooltip';
                    tooltip.textContent = text;

                    document.body.appendChild(tooltip);

                    const rect = this.getBoundingClientRect();
                    const tooltipRect = tooltip.getBoundingClientRect();
                    const padding = 12;

                    let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                    let top = rect.top - tooltipRect.height - 8;

                    if (left < padding) {
                        left = padding;
                    }

                    if (left + tooltipRect.width > window.innerWidth - padding) {
                        left = window.innerWidth - tooltipRect.width - padding;
                    }

                    if (top < padding) {
                        top = rect.bottom + 8;
                    }

                    tooltip.style.left = `${left}px`;
                    tooltip.style.top = `${top}px`;
                });

                element.addEventListener('mouseleave', function() {
                    if (tooltip) {
                        tooltip.remove();
                        tooltip = null;
                    }
                });
            });
        });

        document.querySelectorAll('.field-suggestion-btn').forEach(function(suggestion) {
            suggestion.addEventListener('click', function() {
                const fieldName = this.dataset.field;
                const value = this.dataset.value;

                const field = document.querySelector(
                    '[name="attributes[' + fieldName + ']"]'
                );

                if (!field) {
                    return;
                }

                field.value = value;

                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));

                field.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            });
        });
    </script>