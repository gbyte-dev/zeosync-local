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

    .swal-confirm-small {
        padding: 6px 18px !important;
        font-size: 14px !important;
        min-width: auto !important;
    }

    /* Image Library Modal Cards */
    .library-image-card {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid #E5E7EB;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        background: #F9FAFB;
        width: 104px;
        height: 104px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        user-select: none;
    }

    .library-image-card:hover {
        border-color: #9CA3AF;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .library-image-card.is-selected {
        border-color: #2563EB !important;
        background: #EFF6FF !important;
        box-shadow: 0 0 0 1px #2563EB;
    }

    .image-library-preview-wrapper {
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
    }

    .image-library-preview {
        width: 100px;
        height: 100px;
        object-fit: contain;
        display: block;
    }

    .library-image-card .select-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.5);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        transition: all 0.15s ease;
        z-index: 2;
    }

    .library-image-card.is-selected .select-badge {
        background: #2563EB;
        color: #fff;
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
        @php
            $displayErrors = $visibleAmazonErrors ?? (session('errors_amazon') ?? []);
        @endphp
        @if(!empty($displayErrors) && count($displayErrors) > 0)
        <div class="alert alert-danger">
            <strong>Amazon Validation Errors: Please check all tabs</strong>
            <ul class="mb-0 mt-2">
                @if(is_array($displayErrors))
                @foreach($displayErrors as $error)
                <li>
                    <strong style="display:none">{{ implode(', ', $error['attributeNames'] ?? []) }} : </strong>
                    {{ is_array($error) ? ($error['message'] ?? '') : $error }}
                </li>
                @endforeach
                @else
                <li>{{ $displayErrors }}</li>
                @endif
            </ul>
        </div>
        @endif
        @if(!empty($autofillCount) && $autofillCount > 0)
        <div class="alert alert-info">
            {{ $autofillCount === 1 ? '1 field with errors was automatically filled with available information.' : $autofillCount . ' fields with errors were automatically filled with available information.' }}
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
                                        class="btn btn-primary text-nowrap"
                                        style="white-space: nowrap;">
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
                                        class="btn btn-outline-secondary d-none text-nowrap"
                                        style="white-space: nowrap;">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Previous
                                    </button>

                                    <button
                                        class="btn btn-outline-secondary text-nowrap"
                                        style="white-space: nowrap;"
                                        type="submit"
                                        name="save_draft"
                                        value="true">
                                        Save Draft
                                    </button>

                                    <button
                                        id="nextTabBtn"
                                        type="button"
                                        class="btn btn-primary text-nowrap"
                                        style="white-space: nowrap;">
                                        Next
                                        <i class="fas fa-arrow-right ms-1"></i>
                                    </button>

                                    <div id="syncAmazonBtnWrapper" class="d-inline-block d-none">
                                        <button
                                            id="syncAmazonBtn"
                                            class="btn btn-success text-nowrap"
                                            style="white-space: nowrap;"
                                            type="submit"
                                            name="sync_amazon"
                                            value="true"
                                            disabled>
                                            <i class="fab fa-amazon me-2"></i>
                                            Sync to Amazon
                                        </button>
                                    </div>

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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                        <span class="tab-error-badge" style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                min-width:20px;
                                                height:20px;
                                                padding:0 6px;
                                                margin-left:6px;
                                                border-radius:50%;
                                                background:#dc3545;
                                                color:#fff;
                                                font-size:11px;
                                                font-weight:700;
                                                line-height:1;
                                                vertical-align:middle;
                                            ">
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
                                    @include('schema.products.field', [
                                    'field' => $field,
                                    'fieldSuggestions' => $fieldSuggestions
                                    ])
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
                                    @include('schema.products.field', [
                                    'field' => $field,
                                    'fieldSuggestions' => $fieldSuggestions
                                    ])
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

<!-- Image Library Selection Modal -->
<div class="modal fade" id="imageLibraryModal" tabindex="-1" aria-labelledby="imageLibraryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 12px; border: 1px solid #E5E7EB;">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid #F3F4F6;">
                <div>
                    <h5 class="modal-title fw-semibold text-dark mb-0" id="imageLibraryModalLabel" style="font-size: 15px;">
                        Select Image
                    </h5>
                    <p class="text-muted small mb-0" id="imageLibraryModalSubtitle" style="font-size: 12px;">Choose an image from your library or upload from device</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Modal Alert Message -->
                <div id="modalAlertMessage" class="alert d-none py-2 px-3 small mb-3" role="alert"></div>

                <!-- Tab Navigation & Upload from Device Toolbar -->
                <div class="d-flex justify-content-between align-items-center border-bottom mb-3 pb-1">
                    <ul class="nav nav-tabs border-bottom-0 mb-0" id="imageLibraryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-medium px-3 py-2 border-0 bg-transparent text-dark border-bottom border-2 border-primary" id="tab-add-product" type="button" role="tab" style="font-size: 13px;">
                                <i class="bi bi-grid-fill me-1 text-primary"></i> Image Library
                            </button>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center">
                        <input type="file" id="modalDeviceUploadInput" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" id="modalDeviceUploadBtn" style="font-size: 12px;">
                            <i class="bi bi-cloud-arrow-up" id="modalUploadIcon"></i>
                            <span id="modalUploadBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            <span id="modalUploadBtnText">Upload from Device</span>
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="libraryImagesLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2 small text-muted">Loading your images...</span>
                </div>

                <!-- Images Grid (Max 10 per tab/page, fixed 100x100 contain previews) -->
                <div id="libraryImagesGrid" class="d-flex flex-wrap gap-2 justify-content-start" style="min-height: 230px;">
                    <!-- Dynamically populated -->
                </div>

                <!-- Empty State -->
                <div id="libraryEmptyState" class="text-center py-4 text-muted small d-none">
                    No images found in your library.
                </div>

                <!-- Pagination & Page Controls (10 images max per tab/page) -->
                <div id="libraryPagination" class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top d-none">
                    <span class="small text-muted" id="libraryPaginationInfo">Showing 0 - 0 of 0 images</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-dark btn-sm" id="libraryPrevPageBtn" disabled>‹ Previous</button>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="libraryNextPageBtn" disabled>Next ›</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between py-2 px-4" style="border-top: 1px solid #F3F4F6; background: #FAFAFA; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <span class="small fw-500 text-muted" id="selectedLibraryCount">Selected: 0 images</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" id="confirmLibrarySelectionBtn">Select Image</button>
                </div>
            </div>
        </div>
    </div>
</div>
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

    const $syncAmazonBtn = $('#syncAmazonBtn');
    let isSyncSubmitting = false;

    function resetSyncButton() {
        isSyncSubmitting = false;
        $syncAmazonBtn
            .removeClass('disabled')
            .css('pointer-events', '')
            .html('<i class="fab fa-amazon me-2"></i> Sync to Amazon');
        validateRequiredFields();
    }

    function validateRequiredFields() {
        if (isSyncSubmitting) {
            return;
        }

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

        $syncAmazonBtn.prop('disabled', !allFilled);
    }
    updateProgress();
    validateRequiredFields();

    function getMissingAmazonRequiredFields() {
        const fieldsToCheck = [
            { name: 'item_name', label: 'Item Name' },
            { name: 'title_differentiation', label: 'Item Highlight' },
            { name: 'brand', label: 'Brand Name' },
            { name: 'main_product_image_locator', label: 'Main Image Locator' }
        ];

        const missing = [];

        fieldsToCheck.forEach(item => {
            const inputs = getFieldInputs(item.name);
            const isFilled = inputs.length > 0 && inputs.some(input => isInputFilled(input));
            if (!isFilled) {
                missing.push(item.label);
            }
        });

        return missing;
    }

    $('#syncAmazonBtnWrapper').on('click', function(e) {
        if ($syncAmazonBtn.prop('disabled') && !isSyncSubmitting) {
            e.preventDefault();
            e.stopPropagation();

            const missing = getMissingAmazonRequiredFields();
            if (missing.length > 0) {
                const listHtml = '<ul style="text-align: left;">' +
                    missing.map(field => `<li style="margin-bottom: 4px;"><p style="font-size:14px">${field}</p></li>`).join('') +
                    '</ul>';
                Swal.fire({
                    title: '<span style="font-size: 16px;">Required Information Missing</span>',
                    html: '<p style="margin-bottom: 6px; font-size: 14px;">Please complete the following required fields before requesting Amazon submission:</p>' + listHtml,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#2563EB',
                    customClass: {
                        confirmButton: 'swal-confirm-small'
                    }
                });
            }
        }
    });

    $syncAmazonBtn.on('click', function(e) {
        if ($syncAmazonBtn.prop('disabled') || isSyncSubmitting) {
            e.preventDefault();
            return false;
        }

        isSyncSubmitting = true;
        $syncAmazonBtn
            .addClass('disabled')
            .css('pointer-events', 'none')
            .html(
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="width: 13px; height: 13px; border-width: 2px;"></span> Syncing...'
            );

        setTimeout(function() {
            $syncAmazonBtn.prop('disabled', true);
        }, 0);
    });

    window.addEventListener('pageshow', function(event) {
        resetSyncButton();
    });

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
                    showToast(
                        'AI is currently under maintenance. Please try again after some time.',
                        'danger'
                    );

                    return;
                }

                clearAiError();

                const protectedFields = [
                    'item_name',
                ];

                $.each(response.data, function(aiKey, value) {

                    const mappedField = findBestField(aiKey);

                    if (!mappedField) {
                        console.warn('No mapping found:', aiKey);
                        return;
                    }

                    // Never overwrite these fields
                    if (protectedFields.includes(mappedField)) {
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
                        return;
                    }

                    if (Array.isArray(value)) {
                        field.val(value.join("\n"));
                    } else {
                        field.val(value);
                    }

                    field.trigger('input');
                    field.trigger('change');

                });

            },

            error: function(xhr) {
                showToast(
                    'AI is currently under maintenance. Please try again after some time.',
                    'danger'
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
        $('#syncAmazonBtnWrapper').toggleClass('d-none', index !== lastIndex);
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
                    showToast(
                        'AI is currently under maintenance. Please try again after some time.',
                        'danger'
                    );
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
                showToast(
                    'AI is currently under maintenance. Please try again after some time.',
                    'danger'
                );
            },

            complete: function() {

                button
                    .prop('disabled', false)
                    .html(originalHtml);

            }

        });

    });

    // --- Amazon Image Library Modal Management ---
    const IMAGES_PER_TAB_PAGE = 10;
    let allLibraryImages = [];
    let currentLibraryPage = 1;
    let modalSelectedMap = new Map(); // url => { url, name, id, path }
    let currentAmazonImageTarget = null; // { fieldName, input }

    const libraryModalEl = document.getElementById('imageLibraryModal');
    const libraryModal = libraryModalEl ? bootstrap.Modal.getOrCreateInstance(libraryModalEl) : null;
    const libraryImagesGrid = document.getElementById('libraryImagesGrid');
    const libraryImagesLoading = document.getElementById('libraryImagesLoading');
    const libraryEmptyState = document.getElementById('libraryEmptyState');
    const selectedLibraryCount = document.getElementById('selectedLibraryCount');
    const confirmLibrarySelectionBtn = document.getElementById('confirmLibrarySelectionBtn');
    const libraryPagination = document.getElementById('libraryPagination');
    const libraryPaginationInfo = document.getElementById('libraryPaginationInfo');
    const libraryPrevPageBtn = document.getElementById('libraryPrevPageBtn');
    const libraryNextPageBtn = document.getElementById('libraryNextPageBtn');

    const modalDeviceUploadBtn = document.getElementById('modalDeviceUploadBtn');
    const modalDeviceUploadInput = document.getElementById('modalDeviceUploadInput');
    const modalUploadBtnText = document.getElementById('modalUploadBtnText');
    const modalUploadIcon = document.getElementById('modalUploadIcon');
    const modalUploadBtnSpinner = document.getElementById('modalUploadBtnSpinner');
    const modalAlertMessage = document.getElementById('modalAlertMessage');

    function showModalAlert(message, type = 'danger') {
        if (!modalAlertMessage) return;
        modalAlertMessage.textContent = message;
        modalAlertMessage.className = `alert alert-${type} py-2 px-3 small mb-3`;
    }

    function updateModalCounter() {
        if (!selectedLibraryCount) return;
        const count = modalSelectedMap.size;
        selectedLibraryCount.textContent = `Selected: ${count} image${count === 1 ? '' : 's'}`;
    }

    function fetchLibraryImages() {
        if (!libraryImagesLoading || !libraryImagesGrid) return;
        libraryImagesLoading.classList.remove('d-none');
        libraryImagesGrid.innerHTML = '';
        if (libraryEmptyState) libraryEmptyState.classList.add('d-none');
        if (libraryPagination) libraryPagination.classList.add('d-none');

        fetch("{{ route('shopify.image-picker-images') }}", {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            libraryImagesLoading.classList.add('d-none');
            if (data.success && Array.isArray(data.images) && data.images.length > 0) {
                allLibraryImages = data.images;
                currentLibraryPage = 1;
                renderLibraryTabPage();
            } else {
                if (libraryEmptyState) libraryEmptyState.classList.remove('d-none');
            }
        })
        .catch(err => {
            console.error('Failed to load library images:', err);
            libraryImagesLoading.classList.add('d-none');
            if (libraryEmptyState) {
                libraryEmptyState.textContent = 'Unable to load images. Please try again.';
                libraryEmptyState.classList.remove('d-none');
            }
        });
    }

    function renderLibraryTabPage() {
        if (!libraryImagesGrid) return;
        libraryImagesGrid.innerHTML = '';

        if (!allLibraryImages || allLibraryImages.length === 0) {
            if (libraryEmptyState) libraryEmptyState.classList.remove('d-none');
            if (libraryPagination) libraryPagination.classList.add('d-none');
            return;
        }

        if (libraryEmptyState) libraryEmptyState.classList.add('d-none');

        const totalImages = allLibraryImages.length;
        const totalPages = Math.max(1, Math.ceil(totalImages / IMAGES_PER_TAB_PAGE));
        if (currentLibraryPage > totalPages) currentLibraryPage = totalPages;
        if (currentLibraryPage < 1) currentLibraryPage = 1;

        const startIndex = (currentLibraryPage - 1) * IMAGES_PER_TAB_PAGE;
        const pageImages = allLibraryImages.slice(startIndex, startIndex + IMAGES_PER_TAB_PAGE);

        pageImages.forEach(img => {
            const card = document.createElement('div');
            const isSelected = modalSelectedMap.has(img.url);
            card.className = 'library-image-card' + (isSelected ? ' is-selected' : '');
            card.setAttribute('data-url', img.url);

            card.innerHTML = `
                <div class="image-library-preview-wrapper">
                    <img src="${img.url}" alt="${img.name || ''}" class="image-library-preview">
                </div>
                <span class="select-badge">${isSelected ? '✓' : '+'}</span>
            `;

            card.addEventListener('click', function() {
                // Single image selection mode for Amazon field
                if (modalSelectedMap.has(img.url)) {
                    modalSelectedMap.clear();
                    card.classList.remove('is-selected');
                    card.querySelector('.select-badge').textContent = '+';
                } else {
                    modalSelectedMap.clear();
                    modalSelectedMap.set(img.url, img);
                    document.querySelectorAll('#libraryImagesGrid .library-image-card').forEach(c => {
                        c.classList.remove('is-selected');
                        const b = c.querySelector('.select-badge');
                        if (b) b.textContent = '+';
                    });
                    card.classList.add('is-selected');
                    card.querySelector('.select-badge').textContent = '✓';
                }
                updateModalCounter();
            });

            libraryImagesGrid.appendChild(card);
        });

        // Update Pagination Controls
        if (libraryPagination && libraryPaginationInfo && libraryPrevPageBtn && libraryNextPageBtn) {
            if (totalImages > IMAGES_PER_TAB_PAGE) {
                libraryPagination.classList.remove('d-none');
                const endCount = Math.min(startIndex + IMAGES_PER_TAB_PAGE, totalImages);
                libraryPaginationInfo.textContent = `Showing ${startIndex + 1} - ${endCount} of ${totalImages} images (Page ${currentLibraryPage} of ${totalPages})`;
                libraryPrevPageBtn.disabled = (currentLibraryPage <= 1);
                libraryNextPageBtn.disabled = (currentLibraryPage >= totalPages);
            } else {
                libraryPagination.classList.add('d-none');
            }
        }
    }

    if (libraryPrevPageBtn) {
        libraryPrevPageBtn.addEventListener('click', function() {
            if (currentLibraryPage > 1) {
                currentLibraryPage--;
                renderLibraryTabPage();
            }
        });
    }

    if (libraryNextPageBtn) {
        libraryNextPageBtn.addEventListener('click', function() {
            const totalPages = Math.ceil(allLibraryImages.length / IMAGES_PER_TAB_PAGE);
            if (currentLibraryPage < totalPages) {
                currentLibraryPage++;
                renderLibraryTabPage();
            }
        });
    }

    // Modal Upload from Device
    if (modalDeviceUploadBtn && modalDeviceUploadInput) {
        modalDeviceUploadBtn.addEventListener('click', function() {
            modalDeviceUploadInput.value = '';
            if (modalAlertMessage) modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';
            modalDeviceUploadInput.click();
        });

        modalDeviceUploadInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showModalAlert('Please select a valid image file (JPG, PNG, or WEBP).', 'danger');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                showModalAlert('Image size must not exceed 10 MB.', 'danger');
                return;
            }

            modalDeviceUploadBtn.disabled = true;
            if (modalUploadIcon) modalUploadIcon.classList.add('d-none');
            if (modalUploadBtnSpinner) modalUploadBtnSpinner.classList.remove('d-none');
            if (modalUploadBtnText) modalUploadBtnText.textContent = 'Uploading...';
            if (modalAlertMessage) modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';

            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch("{{ route('shopify.imgupload.store') }}", {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to upload image.');
                }
                return data;
            })
            .then(data => {
                const newImg = data.image;
                allLibraryImages.unshift(newImg);
                currentLibraryPage = 1;

                modalSelectedMap.clear();
                modalSelectedMap.set(newImg.url, newImg);

                renderLibraryTabPage();
                updateModalCounter();
                showModalAlert('Image uploaded successfully and selected.', 'success');
            })
            .catch(err => {
                console.error('Modal upload failed:', err);
                showModalAlert(err.message || 'Failed to upload image. Please try again.', 'danger');
            })
            .finally(() => {
                modalDeviceUploadBtn.disabled = false;
                if (modalUploadIcon) modalUploadIcon.classList.remove('d-none');
                if (modalUploadBtnSpinner) modalUploadBtnSpinner.classList.add('d-none');
                if (modalUploadBtnText) modalUploadBtnText.textContent = 'Upload from Device';
                modalDeviceUploadInput.value = '';
            });
        });
    }

    // Open Modal for Amazon Image Field
    $(document).on('click', '.image-picker-btn', function(e) {
        e.preventDefault();

        const button = $(this);
        const fieldName = button.data('field');
        const field = $('[name="attributes[' + fieldName + ']"]');

        if (!field.length || !libraryModal) {
            return;
        }

        currentAmazonImageTarget = {
            fieldName: fieldName,
            input: field[0]
        };

        const modalTitle = document.getElementById('imageLibraryModalLabel');
        if (modalTitle) {
            modalTitle.textContent = 'Select Image';
        }
        const modalSubtitle = document.getElementById('imageLibraryModalSubtitle');
        if (modalSubtitle) {
            modalSubtitle.textContent = 'Choose an image from your library or upload from device';
        }
        if (confirmLibrarySelectionBtn) {
            confirmLibrarySelectionBtn.textContent = 'Select Image';
        }
        if (modalAlertMessage) {
            modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';
        }

        modalSelectedMap.clear();
        const currentVal = $(field[0]).val() ? String($(field[0]).val()).trim() : '';
        if (currentVal !== '') {
            modalSelectedMap.set(currentVal, { url: currentVal, name: '' });
        }
        updateModalCounter();

        libraryModal.show();

        if (allLibraryImages.length === 0) {
            fetchLibraryImages();
        } else {
            renderLibraryTabPage();
        }
    });

    // Confirm selection from modal
    if (confirmLibrarySelectionBtn) {
        confirmLibrarySelectionBtn.addEventListener('click', function() {
            if (currentAmazonImageTarget && currentAmazonImageTarget.input) {
                if (modalSelectedMap.size > 0) {
                    const selectedImg = Array.from(modalSelectedMap.values())[0];
                    currentAmazonImageTarget.input.value = selectedImg.url;
                } else {
                    currentAmazonImageTarget.input.value = '';
                }
                $(currentAmazonImageTarget.input).trigger('input').trigger('change');
            }
            if (libraryModal) {
                libraryModal.hide();
            }
            currentAmazonImageTarget = null;
            modalSelectedMap.clear();
        });
    }

    // Clear target reference on modal close without changing input
    if (libraryModalEl) {
        libraryModalEl.addEventListener('hidden.bs.modal', function() {
            currentAmazonImageTarget = null;
            modalSelectedMap.clear();
            if (modalAlertMessage) {
                modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';
            }
        });
    }

</script>
@endpush