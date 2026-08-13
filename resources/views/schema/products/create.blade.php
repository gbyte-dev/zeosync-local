@extends('layouts.app')
@push('styles')
<style>
    .form-control-sm::placeholder {
        font-size: 10px;
        color: #9CA3AF;
        opacity: 1;
    }

    .amazon-tabs {
        border-bottom: 1px solid #dee2e6;
    }

    .tab-error-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        margin-left: 5px;
        border-radius: 999px;
        background: #dc3545;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
    }

    .amazon-tabs .nav-link {
        border: none;
        color: #495057;
        font-weight: 600;
        padding: 15px 20px;
    }

    .amazon-tabs .nav-link.active {
        color: #ff9900;
        background: #fff;
        border-bottom: 3px solid #ff9900;
    }

    .amazon-tabs .nav-link:hover {
        color: #ff9900;
    }

    .tab-content {
        min-height: 700px;
    }

    .tab-pane {
        padding-top: 15px;
    }

    .amazon-sidebar {
        top: 20px;
    }

    .field-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 15px;
        padding: 15px;
        background: #fff;
    }

    .required-badge {
        background: #ff9900;
        color: #fff;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 20px;
    }

    .field-valid {
        color: #28a745;
    }

    .field-missing {
        color: #dc3545;
    }

    #validationList ul {
        padding-left: 15px;
    }

    #validationList li {
        margin-bottom: 8px;
        cursor: pointer;
    }
</style>
@endpush
@section('content')
@php
if(isset($productshow->filled_json)) {
$prodAttrijson = json_decode($productshow->filled_json, true);
}
@endphp
<div class="container-fluid">
    <div class="row">
        {{-- RIGHT CONTENT --}}
        @if(session('errors_amazon'))
        <div class="alert alert-danger">
            <strong>Amazon Validation Errors: Please check all tabs</strong>
            <ul class="mb-0 mt-2">
                @if(is_array(session('errors_amazon')))
                @foreach(session('errors_amazon') as $error)
                <li>
                    <strong style="display:none">{{ implode(', ', $error['attributeNames'] ?? []) }} : </strong>
                    {{ $error['message'] }}
                </li>
                @endforeach
                @else
                <li>{{ session('errors_amazon') }}</li>
                @endif
            </ul>
        </div>
        @endif
        <div class="col-md-12">
            @if(isset($productshow->id) && ($productshow->status == 'draft' ))
            <form action="{{ route('admin.product.edit.post', [
                'product_id' => $productshow->id,
                'shop' => $activeShop,
            ]) }}" method="POST" enctype="multipart/form-data">
                @else
                <form action="{{ route('admin.product.store.post', [
                'shop' => $activeShop ]) }}" method="POST"
                    enctype="multipart/form-data">
                    @endif
                    @csrf
                    <input type="hidden" name="shop" value="{{ request('shop') }}">
                    @if(isset($productshow) && ($productshow->status != 'draft' && $productshow->status != 'failed'))
                    <input type="hidden" name="parent_id" value="{{ $productshow->id }}">
                    @endif
                    <div class="card mt-3">
                        <div class="card-body d-flex justify-content-between align-items-center row" style="padding: 5px 10px;">

                            <div class="col-sm-6">
                                <h6 class="mb-1">Create Product</h6>
                                <small class="text-muted">
                                    Complete all required fields.
                                </small>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-2 float-end">

                                    @if($canUseAiAutoFill)
                                    <button
                                        type="button"
                                        id="aiAutofillBtn"
                                        class="btn btn-primary">
                                        <i class="fas fa-magic me-2"></i>
                                        Auto Fill
                                    </button>
                                    @endif

                                    @if(isset($productshow->status) && ($productshow->status != 'draft') && ($productshow->parent_id != null))

                                    <span class="badge bg-warning text-dark">
                                        Product Already {{ $productshow->status }}
                                    </span>

                                    @else

                                    <button
                                        id="prevTabBtn"
                                        type="button"
                                        class="btn btn-outline-secondary d-none">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Previous
                                    </button>

                                    <button
                                        class="btn btn-outline-secondary"
                                        type="submit"
                                        name="save_draft"
                                        value="true">
                                        Save Draft
                                    </button>

                                    <button
                                        id="nextTabBtn"
                                        type="button"
                                        class="btn btn-primary">
                                        Next
                                        <i class="fas fa-arrow-right ms-1"></i>
                                    </button>

                                    <button
                                        id="syncAmazonBtn"
                                        class="btn btn-success d-none"
                                        type="submit"
                                        name="sync_amazon"
                                        value="true"
                                        disabled>
                                        <i class="fab fa-amazon me-2"></i>
                                        Sync to Amazon
                                    </button>

                                    @endif

                                </div>
                            </div>
                        </div>
                    </div>
                    <input
                        type="hidden"
                        name="schema_id"
                        value="{{ $schema->id }}">
                    <div class="card shadow-sm mt-3">
                        <div class="card-header bg-white p-0">
                            <ul class="nav nav-tabs amazon-tabs">
                                @if(count($tabs['product']))
                                <li class="nav-item">
                                    <a class="nav-link active"
                                        data-bs-toggle="tab"
                                        href="#productTab">
                                        Product Info

                                        @if(($tabErrorCounts['product'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['product'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['images']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#imageTab">
                                        Images

                                        @if(($tabErrorCounts['images'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['images'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['variations']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#variationTab">
                                        Variations

                                        @if(($tabErrorCounts['variations'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['variations'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['attributes']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#attributeTab">
                                        Attributes

                                        @if(($tabErrorCounts['attributes'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['attributes'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['product_rules']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#productRulesTab">
                                        Product Rules

                                        @if(($tabErrorCounts['product_rules'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['product_rules'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['battery_specs']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#batterySpecsTab">
                                        Battery Specs

                                        @if(($tabErrorCounts['battery_specs'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['battery_specs'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['other']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#otherTab">
                                        Other

                                        @if(($tabErrorCounts['other'] ?? 0) > 0)
                                        <span class="tab-error-badge">
                                            {{ $tabErrorCounts['other'] }}
                                        </span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                {{-- PRODUCT INFO --}}
                                <div
                                    class="tab-pane fade show active"
                                    id="productTab">
                                    @foreach($fields as $field)
                                    @if(
                                    in_array(
                                    $field['name'],
                                    [
                                    'item_name',
                                    'brand',
                                    'product_description',
                                    'bullet_point',
                                    'item_type_keyword',
                                    'externally_assigned_product_identifier',
                                    'supplier_declared_has_product_identifier_exemption',
                                    'merchant_suggested_asin',
                                    'model_number',
                                    'part_number',
                                    'generic_keyword',
                                    'department',
                                    'target_gender',
                                    'age_range_description',
                                    'number_of_items',
                                    'item_package_quantity',
                                    'product_site_launch_date',
                                    'merchant_release_date',
                                    'title_differentiation',
                                    ]
                                    )
                                    )
                                    @include(
                                    'schema.products.field',
                                    ['field'=>$field]
                                    )
                                    @endif
                                    @endforeach
                                </div>
                                {{-- IMAGES --}}
                                <div
                                    class="tab-pane fade"
                                    id="imageTab">
                                    @foreach($fields as $field)
                                    @if(
                                    str_contains(
                                    strtolower($field['name']),
                                    'image'
                                    )
                                    )
                                    @include(
                                    'schema.products.field',
                                    ['field'=>$field]
                                    )
                                    @endif
                                    @endforeach
                                </div>
                                {{-- VARIATIONS --}}
                                <div
                                    class="tab-pane fade"
                                    id="variationTab">
                                    @foreach($fields as $field)
                                    @if(
                                    str_contains(
                                    strtolower($field['name']),
                                    'variation'
                                    )
                                    ||
                                    str_contains(
                                    strtolower($field['name']),
                                    'parent'
                                    )
                                    )
                                    @include(
                                    'schema.products.field',
                                    ['field'=>$field]
                                    )
                                    @endif
                                    @endforeach
                                </div>
                                {{-- ATTRIBUTES --}}
                                <div
                                    class="tab-pane fade"
                                    id="attributeTab">
                                    @foreach($fields as $field)
                                    @if(
                                    in_array(
                                    strtolower($field['name']),
                                    [
                                    'color',
                                    'size',
                                    'material',
                                    'style',
                                    'pattern',
                                    'flavor',
                                    'manufacturer',
                                    'model_name',
                                    'item_weight',
                                    'item_package_dimensions',
                                    'item_package_weight',
                                    'item_display_weight',
                                    ]
                                    )
                                    )
                                    @include(
                                    'schema.products.field',
                                    ['field'=>$field]
                                    )
                                    @endif
                                    @endforeach
                                </div>
                                {{-- productRulesTab --}}
                                <div class="tab-pane fade" id="productRulesTab">
                                    @foreach($fields as $field)
                                    @if(
                                    in_array(
                                    strtolower($field['name']),
                                    [
                                    'country_of_origin',
                                    'supplier_declared_dg_hz_regulation',
                                    'ghs',
                                    'hazmat',
                                    'safety_data_sheet_url',
                                    'is_this_product_subject_to_buyer_age_restrictions',
                                    'california_proposition_65',
                                    'pesticide_marking',
                                    'fcc_radio_frequency_emission_compliance',
                                    'regulatory_compliance_certification',
                                    'dsa_responsible_party_address',
                                    'compliance_media',
                                    'gpsr_safety_attestation',
                                    'gpsr_manufacturer_reference',
                                    'contains_pfas',
                                    'ships_globally',
                                    'ghs_chemical_h_code',
                                    'baa_taa_regulation_compliance',
                                    'baa_taa_compliance_acknowledgement',
                                    'taa_compliant_country',
                                    'list_price',
                                    'merchant_shipping_group',
                                    'max_order_quantity',
                                    'gift_options',
                                    'condition_type',
                                    'condition_note',
                                    'product_tax_code',
                                    'fulfillment_availability',
                                    'purchasable_offer',
                                    'import_designation',
                                    ]
                                    )
                                    )
                                    @include('schema.products.field', ['field' => $field])
                                    @endif
                                    @endforeach
                                </div>
                                {{-- batterySpecsTab --}}
                                <div class="tab-pane fade" id="batterySpecsTab">
                                    @foreach($fields as $field)
                                    @if(
                                    in_array(
                                    strtolower($field['name']),
                                    [
                                    'batteries_required',
                                    'batteries_included',
                                    'battery',
                                    'num_batteries',
                                    'number_of_lithium_metal_cells',
                                    'number_of_lithium_ion_cells',
                                    'lithium_battery',
                                    'has_multiple_battery_powered_components',
                                    'contains_battery_or_cell',
                                    'battery_contains_free_unabsorbed_liquid',
                                    'is_battery_non_spillable',
                                    'non_lithium_battery_packaging',
                                    'has_replaceable_battery',
                                    'non_lithium_battery_energy_content',
                                    'has_less_than_30_percent_state_of_charge',
                                    'battery_installation_device_type',
                                    ]
                                    )
                                    )
                                    @include('schema.products.field', ['field' => $field])
                                    @endif
                                    @endforeach
                                </div>
                                {{-- OTHER --}}
                                <div
                                    class="tab-pane fade"
                                    id="otherTab">
                                    @foreach($fields as $field)
                                    @if(
                                    !str_contains(strtolower($field['name']),'image')
                                    &&
                                    !str_contains(strtolower($field['name']),'variation')
                                    &&
                                    !str_contains(strtolower($field['name']),'parent')
                                    &&
                                    !in_array(
                                    strtolower($field['name']),
                                    [
                                    'item_name',
                                    'brand',
                                    'product_description',
                                    'bullet_point',
                                    'item_type_keyword',
                                    'color',
                                    'size',
                                    'material',
                                    'style',
                                    'pattern',
                                    'flavor',
                                    'manufacturer',
                                    'model_name',
                                    'country_of_origin',
                                    'supplier_declared_dg_hz_regulation',
                                    'ghs',
                                    'hazmat',
                                    'safety_data_sheet_url',
                                    'is_this_product_subject_to_buyer_age_restrictions',
                                    'california_proposition_65',
                                    'pesticide_marking',
                                    'fcc_radio_frequency_emission_compliance',
                                    'regulatory_compliance_certification',
                                    'dsa_responsible_party_address',
                                    'compliance_media',
                                    'gpsr_safety_attestation',
                                    'gpsr_manufacturer_reference',
                                    'contains_pfas',
                                    'ships_globally',
                                    'ghs_chemical_h_code',
                                    'baa_taa_regulation_compliance',
                                    'baa_taa_compliance_acknowledgement',
                                    'taa_compliant_country',
                                    'externally_assigned_product_identifier',
                                    'supplier_declared_has_product_identifier_exemption',
                                    'merchant_suggested_asin',
                                    'model_number',
                                    'part_number',
                                    'generic_keyword',
                                    'department',
                                    'target_gender',
                                    'age_range_description',
                                    'number_of_items',
                                    'item_package_quantity',
                                    'product_site_launch_date',
                                    'merchant_release_date',
                                    'item_weight',
                                    'item_package_dimensions',
                                    'item_package_weight',
                                    'item_display_weight',
                                    'list_price',
                                    'merchant_shipping_group',
                                    'max_order_quantity',
                                    'gift_options',
                                    'condition_type',
                                    'condition_note',
                                    'product_tax_code',
                                    'fulfillment_availability',
                                    'purchasable_offer',
                                    'import_designation',
                                    'title_differentiation',
                                    'batteries_required',
                                    'batteries_included',
                                    'battery',
                                    'num_batteries',
                                    'number_of_lithium_metal_cells',
                                    'number_of_lithium_ion_cells',
                                    'lithium_battery',
                                    'has_multiple_battery_powered_components',
                                    'contains_battery_or_cell',
                                    'battery_contains_free_unabsorbed_liquid',
                                    'is_battery_non_spillable',
                                    'non_lithium_battery_packaging',
                                    'has_replaceable_battery',
                                    'non_lithium_battery_energy_content',
                                    'has_less_than_30_percent_state_of_charge',
                                    'battery_installation_device_type',
                                    ]
                                    )
                                    )
                                    @include(
                                    'schema.products.field',
                                    ['field'=>$field]
                                    )
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
        </div>
    </div>
</div>

<div id="aiError" class="alert alert-danger d-none mb-3"></div>
@endsection
@push('scripts')
<script>
    window.amazonFields = @json($fields);

    function normalize(text) {
        return String(text ?? '')
            .toLowerCase()
            .replace(/[_-]/g, ' ')
            .replace(/[^\w\s]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getAmazonFieldMap() {

        const map = {};

        window.amazonFields.forEach(field => {

            if (field.name) {
                map[normalize(field.name)] = field.name;
            }

            if (field.title) {
                map[normalize(field.title)] = field.name;
            }

            if (field.description) {
                map[normalize(field.description)] = field.name;
            }

        });

        return map;
    }

    window.amazonFieldMap = getAmazonFieldMap();

    console.log('Amazon Fields', window.amazonFields);
    console.log('Amazon Field Map', window.amazonFieldMap);

    const fieldSynonyms = {
        "product name": "item_name",
        "description": "product_description",
        "bullet points": "bullet_point",
        "bullet point": "bullet_point",
        "search keywords": "generic_keyword",
        "keywords": "generic_keyword",
        "special features": "special_feature",
        "product type": "item_type_keyword"
    };

    function findBestField(aiKey) {

        aiKey = normalize(aiKey);

        // 1. Exact Match
        if (window.amazonFieldMap[aiKey]) {
            return window.amazonFieldMap[aiKey];
        }

        // 2. Synonym Match
        if (fieldSynonyms[aiKey]) {
            return fieldSynonyms[aiKey];
        }

        // 3. Best Similarity Match
        let bestField = null;
        let bestScore = 0;

        window.amazonFields.forEach(field => {

            const candidates = [
                field.name,
                field.title,
                field.description
            ];

            candidates.forEach(candidate => {

                candidate = normalize(candidate);

                if (!candidate) {
                    return;
                }

                let score = 0;

                if (candidate === aiKey) {
                    score = 100;
                } else {

                    if (candidate.includes(aiKey)) {
                        score += 60;
                    }

                    if (aiKey.includes(candidate)) {
                        score += 60;
                    }

                    aiKey.split(' ').forEach(word => {
                        if (word.length > 2 && candidate.includes(word)) {
                            score += 10;
                        }
                    });
                }

                if (score > bestScore) {
                    bestScore = score;
                    bestField = field.name;
                }

            });

        });

        return bestScore >= 50 ? bestField : null;
    }

    document.addEventListener("DOMContentLoaded", function() {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        })
    });
</script>
<script>
    const requiredFields = @json($requiredFields);

    function getFieldInputs(fieldName) {
        const direct = document.querySelector(`[name="attributes[${fieldName}]"]`);
        const multi = document.querySelectorAll(`[name="attributes[${fieldName}][]"]`);

        if (multi.length) {
            return Array.from(multi);
        }

        return direct ? [direct] : [];
    }

    function isInputFilled(input) {
        if (!input) {
            return false;
        }

        if (input.multiple) {
            const values = Array.isArray($(input).val()) ? $(input).val() : [];
            return values.some(value => value !== null && value !== undefined && String(value).trim() !== '');
        }

        return input.value && input.value.trim() !== '';
    }

    function updateProgress() {
        let filledCount = 0;
        let html = '<ul>';
        requiredFields.forEach(field => {
            const inputs = getFieldInputs(field);
            const isFilled = inputs.some(input => isInputFilled(input));
            if (isFilled) {
                filledCount++;
                html += `
                <li class="field-valid">
                    ✓ ${field}
                </li>
            `;
            } else {
                html += `
                <li
                    class="field-missing jump-field"
                    data-field="${field}">
                    ⚠ ${field}
                </li>
            `;
            }
        });
        html += '</ul>';
        $('#validationList').html(html);
        let percent =
            Math.round(
                (filledCount / requiredFields.length) *
                100
            );
        $('#requiredProgress')
            .css('width', percent + '%');
        $('#requiredCount')
            .text(
                filledCount +
                ' / ' +
                requiredFields.length
            );
    }

    function validateRequiredFields() {

        let allFilled = true;

        $('form [required][name^="attributes["]').each(function() {

            let value = $(this).val();

            if ($(this).is('select[multiple]')) {

                const values = Array.isArray(value) ? value : [];
                if (!values.some(v => v !== null && v !== undefined && String(v).trim() !== '')) {
                    allFilled = false;
                    return false;
                }

            } else if ($(this).is('select')) {

                if (!value) {
                    allFilled = false;
                    return false;
                }

            } else {

                if (!value || value.trim() === '') {
                    allFilled = false;
                    return false;
                }

            }

        });

        $('#syncAmazonBtn').prop('disabled', !allFilled);
    }
    updateProgress();
    validateRequiredFields();
    $(document).on(
        'input change',
        'input, textarea, select',
        function() {
            updateProgress();
            validateRequiredFields();
        }
    );
    $('#jumpToMissing').click(function() {
        let found = false;
        requiredFields.forEach(field => {
            if (found) return;
            const inputs = getFieldInputs(field);
            const input = inputs.find(item => !isInputFilled(item));
            if (input) {
                found = true;
                $('html,body').animate({
                    scrollTop: $(input).closest('.field-card').offset().top - 100
                }, 500);
                input.focus();
            }
        });
    });
    $(document).on(
        'click',
        '.jump-field',
        function() {
            let field =
                $(this).data('field');
            const inputs = getFieldInputs(field);
            const input = inputs[0] || null;
            if (input) {
                let pane =
                    $(input).closest('.tab-pane');
                if (pane.length) {
                    $('.nav-tabs a[href="#' +
                        pane.attr('id') +
                        '"]').tab('show');
                }
                $('html,body').animate({
                    scrollTop: $(input)
                        .closest('.field-card')
                        .offset().top - 100
                }, 500);
                input.focus();
            }
        }
    );
    $('#fieldSearch').on(
        'keyup',
        function() {
            let value =
                $(this)
                .val()
                .toLowerCase();
            $('.field-card').each(function() {
                let text =
                    $(this)
                    .text()
                    .toLowerCase();
                $(this).toggle(
                    text.indexOf(value) > -1
                );
            });
        }
    );

    function showAiError(message) {
        $('#aiError')
            .removeClass('d-none')
            .text(message);
    }

    function clearAiError() {
        $('#aiError')
            .addClass('d-none')
            .text('');
    }

    $('#aiAutofillBtn').click(function() {

        let productName = $('[name="attributes[item_name]"]').val().trim();

        let productDescription = $('[name="attributes[product_description]"]').val() ?? '';
        productDescription = productDescription.trim();

        let category = "{{ $schema->product_type }}";

        $('#aiError').addClass('d-none').text('');

        if (productName === '') {

            $('#aiError')
                .removeClass('d-none')
                .text('Please enter Product Name first.');

            return;
        }

        $.ajax({

            url: "{{ route('ai.autofill', ['shop' => request('shop')]) }}",

            type: "POST",

            dataType: "json",

            data: {
                _token: "{{ csrf_token() }}",
                product_name: productName,
                product_description: productDescription,
                category: category,
                shop: "{{ request('shop') }}"
            },

            beforeSend: function() {

                $('#aiAutofillBtn')
                    .prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-1"></i> AI Auto Filling...');

            },

            success: function(response) {

                if (!response.success) {

                    $('#aiError')
                        .removeClass('d-none')
                        .text(response.message ?? 'AI generation failed.');

                    return;
                }

                clearAiError();

                const protectedFields = [
                    'item_name',
                    'product_description'
                ];

                $.each(response.data, function(aiKey, value) {

                    const mappedField = findBestField(aiKey);

                    if (!mappedField) {
                        console.warn('No mapping found:', aiKey);
                        return;
                    }

                    // Never overwrite these fields
                    if (protectedFields.includes(mappedField)) {
                        console.log('Protected field skipped:', mappedField);
                        return;
                    }

                    const field = $('[name="attributes[' + mappedField + ']"]');

                    if (!field.length) {
                        console.warn('Rendered field not found:', mappedField);
                        return;
                    }

                    // Skip if user already entered value
                    const currentValue = String(field.val() ?? '').trim();

                    if (currentValue !== '') {
                        console.log('Already filled:', mappedField);
                        return;
                    }

                    if (Array.isArray(value)) {
                        field.val(value.join("\n"));
                    } else {
                        field.val(value);
                    }

                    field.trigger('input');
                    field.trigger('change');

                    console.log('Filled:', mappedField);

                });

            },

            error: function(xhr) {

                $('#aiError')
                    .removeClass('d-none')
                    .text(
                        xhr.responseJSON?.message ??
                        'Something went wrong while generating AI data.'
                    );

            },

            complete: function() {

                $('#aiAutofillBtn')
                    .prop('disabled', false)
                    .html('<i class="fas fa-magic me-1"></i> AI Auto Fill');

            }

        });

    });

    const tabs = $('.amazon-tabs .nav-link');

    function updateNavigationButtons() {

        const index = tabs.index($('.amazon-tabs .nav-link.active'));
        const lastIndex = tabs.length - 1;

        $('#prevTabBtn').toggleClass('d-none', index === 0);
        $('#nextTabBtn').toggleClass('d-none', index === lastIndex);
        $('#syncAmazonBtn').toggleClass('d-none', index !== lastIndex);
        validateRequiredFields();
    }

    updateNavigationButtons();

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        updateNavigationButtons();
        validateRequiredFields();
    });

    $('#nextTabBtn').on('click', function() {
        const next = $('.amazon-tabs .nav-link.active')
            .parent()
            .next()
            .find('.nav-link');

        if (next.length) {
            bootstrap.Tab.getOrCreateInstance(next[0]).show();
        }
    });

    $('#prevTabBtn').on('click', function() {
        const prev = $('.amazon-tabs .nav-link.active')
            .parent()
            .prev()
            .find('.nav-link');

        if (prev.length) {
            bootstrap.Tab.getOrCreateInstance(prev[0]).show();
        }
    });

    $(document).on('click', '.ai-field-btn', function() {
        console.log('CLICKED');

        const button = $(this);
        const originalHtml = button.html();

        const fieldName = button.data('field');
        const fieldTitle = button.data('title');
        const fieldDescription = button.data('description');
        const fieldHint = button.data('hint') ?? '';

        const productName = $('[name="attributes[item_name]"]').val().trim();
        const category = "{{ $schema->product_type }}";

        if (productName === '') {
            showAiError('Please enter Product Name first.');
            return;
        }

        button
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-1"></i> Generating...');

        $.ajax({

            url: "{{ route('ai.generate-field') }}",

            type: "POST",

            dataType: "json",

            data: {
                _token: "{{ csrf_token() }}",
                product_name: productName,
                category: category,
                field: fieldTitle,
                field_description: fieldDescription,
                field_hint: fieldHint,
                shop: "{{ request('shop') }}"
            },

            success: function(response) {

                if (!response.success) {

                    showAiError(response.message ?? 'Unable to generate.');

                    return;
                }

                clearAiError();

                const field = $('[name="attributes[' + fieldName + ']"]');

                if (!field.length) {
                    return;
                }

                // Don't overwrite existing value
                if (String(field.val() ?? '').trim() !== '') {
                    return;
                }

                if (Array.isArray(response.data)) {

                    field.val(response.data.join("\n"));

                } else {

                    field.val(response.data);

                }

                field.trigger('input');
                field.trigger('change');

            },

            error: function(xhr) {

                showAiError(
                    xhr.responseJSON?.message ??
                    'Something went wrong.'
                );

            },

            complete: function() {

                button
                    .prop('disabled', false)
                    .html(originalHtml);

            }

        });

    });

    function renderImagePickerPage(images, fieldName, page, itemsEl, paginationEl) {
        const perPage = 6;
        const totalPages = Math.max(1, Math.ceil(images.length / perPage));
        const safePage = Math.min(page, totalPages);
        const start = (safePage - 1) * perPage;
        const visibleImages = images.slice(start, start + perPage);

        const html = visibleImages.map(function(image) {
            return `
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="position-relative rounded overflow-hidden shadow-sm border image-picker-hover-card" style="height: 82px; background: #f8f9fa;">
                        <img src="${image.url}" alt="${image.name}" style="width: 100%; height: 100%; object-fit: cover;">
                        <button type="button"
                            class="btn btn-sm btn-primary position-absolute top-50 start-50 translate-middle opacity-0 select-image-item image-picker-select-btn"
                            style="transition: opacity 0.2s ease; padding: 2px 8px; font-size: 11px;"
                            data-field-name="${fieldName}"
                            data-image-url="${image.url}">Select</button>
                    </div>
                </div>`;
        }).join('');

        itemsEl.innerHTML = html;

        if (totalPages > 1) {
            const pageNumbers = [];

            for (let i = 1; i <= totalPages; i++) {
                pageNumbers.push(`<button type="button" class="btn btn-sm ${i === safePage ? 'btn-primary' : 'btn-outline-secondary'} image-picker-page" data-page="${i}">${i}</button>`);
            }

            paginationEl.innerHTML = `<div class="d-flex justify-content-center gap-2 mt-3">${pageNumbers.join('')}</div>`;
        } else {
            paginationEl.innerHTML = '';
        }
    }

    function loadImagePickerImages(fieldName, pickerUrl, itemsEl, loadingEl, paginationEl) {
        loadingEl.style.display = 'block';
        itemsEl.innerHTML = '';
        paginationEl.innerHTML = '';

        $.ajax({
            url: pickerUrl,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success || !response.images || !response.images.length) {
                    itemsEl.innerHTML = '<div class="col-12 text-center text-muted py-3">No saved images found. Upload images first.</div>';
                    loadingEl.style.display = 'none';
                    return;
                }

                const images = response.images;
                window.imagePickerImages = images;
                renderImagePickerPage(images, fieldName, 1, itemsEl, paginationEl);
                loadingEl.style.display = 'none';
            },
            error: function() {
                itemsEl.innerHTML = '<div class="col-12 text-center text-danger py-3">Unable to load images.</div>';
                loadingEl.style.display = 'none';
            }
        });
    }

    $(document).on('click', '.image-picker-btn', function(e) {
        e.preventDefault();

        const button = $(this);
        const originalHtml = button.html();
        const fieldName = button.data('field');
        const pickerUrl = button.data('picker-url') || "{{ route('shopify.image-picker-images') }}";
        const field = $('[name="attributes[' + fieldName + ']"]');

        if (!field.length) {
            return;
        }

        let modalElement = document.getElementById('image-picker-modal');

        if (!modalElement) {
            modalElement = document.createElement('div');
            modalElement.id = 'image-picker-modal';
            modalElement.className = 'modal fade';
            modalElement.tabIndex = -1;
            modalElement.innerHTML = `
                <style>
                    .image-picker-hover-card:hover .image-picker-select-btn { opacity: 1 !important; }
                    .image-picker-select-btn { pointer-events: none; }
                    .image-picker-hover-card:hover .image-picker-select-btn { pointer-events: auto; }
                </style>
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h5 class="modal-title small fw-bold">Select an image</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-3">
                            <form id="image-picker-upload-form" class="border rounded p-2 mb-3" enctype="multipart/form-data">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label small mb-1">Upload a new image</label>
                                        <input type="file" name="image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Upload</button>
                                    </div>
                                </div>
                                <div id="image-picker-upload-status" class="small mt-2"></div>
                            </form>
                            <div id="image-picker-loading" class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <div class="mt-2 small">Loading images...</div>
                            </div>
                            <div id="image-picker-items" class="row g-2"></div>
                            <div id="image-picker-pagination"></div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modalElement);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const loadingEl = document.getElementById('image-picker-loading');
        const itemsEl = document.getElementById('image-picker-items');
        const paginationEl = document.getElementById('image-picker-pagination');
        const uploadForm = $('#image-picker-upload-form');

        uploadForm.data('field-name', fieldName);

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Loading...');
        loadImagePickerImages(fieldName, pickerUrl, itemsEl, loadingEl, paginationEl);

        modal.show();
        button.prop('disabled', false).html(originalHtml);
    });

    $(document).on('click', '.image-picker-page', function(e) {
        e.preventDefault();

        const button = $(this);
        const page = parseInt(button.data('page'), 10);
        const fieldName = $('#image-picker-upload-form').data('field-name') || '';
        const itemsEl = document.getElementById('image-picker-items');
        const paginationEl = document.getElementById('image-picker-pagination');
        const allImages = window.imagePickerImages || [];

        if (!allImages.length) {
            return;
        }

        renderImagePickerPage(allImages, fieldName, page, itemsEl, paginationEl);
    });

    $(document).on('submit', '#image-picker-upload-form', function(e) {
        e.preventDefault();

        const form = $(this);
        const statusEl = $('#image-picker-upload-status');
        const itemsEl = $('#image-picker-items');
        const loadingEl = $('#image-picker-loading');
        const paginationEl = $('#image-picker-pagination');
        const submitButton = form.find('button[type="submit"]');
        const originalText = submitButton.html();

        const formData = new FormData(this);

        statusEl.text('Uploading...');
        submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Uploading...');

        $.ajax({
            url: "{{ route('shopify.imgupload.store') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    statusEl.html('<span class="text-danger">' + (response.message || 'Upload failed.') + '</span>');
                    return;
                }

                statusEl.html('<span class="text-success">Image uploaded successfully.</span>');
                form[0].reset();
                loadImagePickerImages(form.data('field-name') || $('[name="attributes[main_product_image_locator]"]').attr('name')?.replace(/^attributes\[/, '').replace(/\]$/, '') || '', "{{ route('shopify.image-picker-images') }}", itemsEl[0], loadingEl[0], paginationEl[0]);
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Unable to upload image.';
                statusEl.html('<span class="text-danger">' + message + '</span>');
            },
            complete: function() {
                submitButton.prop('disabled', false).html(originalText);
            }
        });
    });

    $(document).on('click', '.select-image-item', function(e) {
        e.preventDefault();

        const button = $(this);
        const fieldName = button.data('field-name');
        const imageUrl = button.data('image-url');
        const field = $('[name="attributes[' + fieldName + ']"]');

        if (field.length) {
            field.val(imageUrl);
            field.trigger('input');
            field.trigger('change');
        }

        const modalElement = document.getElementById('image-picker-modal');

        if (modalElement) {
            bootstrap.Modal.getInstance(modalElement)?.hide();
        }
    });

    // $(document).on('click', '.ai-field-btn', function() {

    //     console.log('================ AI FIELD START ================');

    //     const button = $(this);
    //     const originalHtml = button.html();

    //     console.log('Button Found:', button);

    //     const fieldName = button.data('field');
    //     console.log('fieldName:', fieldName);

    //     const fieldTitle = button.data('title');
    //     console.log('fieldTitle:', fieldTitle);

    //     const fieldDescription = button.data('description');
    //     console.log('fieldDescription:', fieldDescription);

    //     const fieldHint = button.data('hint') ?? '';
    //     console.log('fieldHint:', fieldHint);

    //     const productName = $('[name="attributes[item_name]"]').val().trim();
    //     console.log('productName:', productName);

    //     const category = "{{ $schema->product_type }}";
    //     console.log('category:', category);

    //     console.log('Route:', "{{ route('ai.generate-field') }}");

    //     if (productName === '') {
    //         console.error('Product name is empty.');
    //         showAiError('Please enter Product Name first.');
    //         return;
    //     }

    //     console.log('Before Disable Button');

    //     button
    //         .prop('disabled', true)
    //         .html('<i class="fas fa-spinner fa-spin me-1"></i> Generating...');

    //     console.log('Before AJAX');

    //     $.ajax({

    //         url: "{{ route('ai.generate-field') }}",

    //         type: "POST",

    //         dataType: "json",

    //         data: {
    //             _token: "{{ csrf_token() }}",
    //             product_name: productName,
    //             category: category,
    //             field: fieldTitle,
    //             field_description: fieldDescription,
    //             field_hint: fieldHint,
    //             shop: "{{ request('shop') }}"
    //         },

    //         beforeSend: function() {
    //             console.log('AJAX beforeSend');
    //         },

    //         success: function(response) {

    //             console.log('AJAX Success:', response);

    //             if (!response.success) {

    //                 console.error('AI returned success = false');

    //                 showAiError(response.message ?? 'Unable to generate.');
    //                 return;
    //             }

    //             clearAiError();

    //             const field = $('[name="attributes[' + fieldName + ']"]');

    //             console.log('Target Field:', field);

    //             if (!field.length) {
    //                 console.error('Target field not found');
    //                 return;
    //             }

    //             if (String(field.val() ?? '').trim() !== '') {
    //                 console.warn('Field already contains value');
    //                 return;
    //             }

    //             if (Array.isArray(response.data)) {
    //                 field.val(response.data.join("\n"));
    //             } else {
    //                 field.val(response.data);
    //             }

    //             field.trigger('input');
    //             field.trigger('change');

    //             console.log('Field Filled Successfully');

    //         },

    //         error: function(xhr, status, error) {

    //             console.error('AJAX ERROR');
    //             console.error('Status:', status);
    //             console.error('Error:', error);
    //             console.error('XHR:', xhr);
    //             console.error('Response:', xhr.responseText);

    //             showAiError(
    //                 xhr.responseJSON?.message ??
    //                 'Something went wrong.'
    //             );

    //         },

    //         complete: function() {

    //             console.log('AJAX Complete');

    //             button
    //                 .prop('disabled', false)
    //                 .html(originalHtml);

    //             console.log('================ AI FIELD END ================');

    //         }

    //     });

    // });
</script>
@endpush