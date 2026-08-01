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
                    <!-- <strong>{{ implode(', ', $error['attributeNames'] ?? []) }}</strong>: -->
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
            ]) }}"  method="POST"   enctype="multipart/form-data">
            @else
            <form action="{{ route('admin.product.store.post', [
                'shop' => $activeShop ]) }}"  method="POST"
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
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['images']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#imageTab">
                                        Images
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['variations']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#variationTab">
                                        Variations
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['attributes']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#attributeTab">
                                        Attributes
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['product_rules']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#productRulesTab">
                                        Product Rules
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['battery_specs']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#batterySpecsTab">
                                        Battery Specs
                                    </a>
                                </li>
                                @endif
                                @if(count($tabs['other']))
                                <li class="nav-item">
                                    <a class="nav-link"
                                        data-bs-toggle="tab"
                                        href="#otherTab">
                                        Other
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
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    })
});
</script>
<script>
    const requiredFields = @json($requiredFields);

    function updateProgress() {
        let filledCount = 0;
        let html = '<ul>';
        requiredFields.forEach(field => {
            let input =
                document.querySelector(
                    `[name="attributes[${field}]"]`
                );
            let isFilled =
                input &&
                input.value &&
                input.value.trim() !== '';
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

            if ($(this).is('select')) {

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
            let input = document.querySelector(`[name="attributes[${field}]"]`);
            if (input && input.value.trim() === '') {
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
            let input =
                document.querySelector(
                    `[name="attributes[${field}]"]`
                );
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