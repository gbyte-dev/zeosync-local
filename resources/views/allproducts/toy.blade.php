@extends('layouts.app')
@section('content')
@push('css')
<style>
.amazon-panel {
    border: 2px solid #f0d9a8;
    border-radius: 8px;
    overflow: hidden;
}

.amazon-panel-header {
    background: linear-gradient(135deg, #ff9900 0%, #ffb347 100%);
    padding: 12px 24px;
}

.amazon-panel-title {
    color: #fff;
    font-weight: 600;
    margin: 0;
}

.amazon-panel-note {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.8rem;
    margin: 0;
}

.amazon-badge {
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 20px;
}

.amazon-body {
    padding: 24px;
    background: #fff;
}

.sub-section-title {
    font-weight: 600;
    color: #212529;
    border-bottom: 2px solid #f0d9a8;
    padding-bottom: 8px;
    margin-bottom: 16px;
}
</style>
@endpush

<div class="container py-4">
    <div class="mb-4">
        <h2>TOY_GUN Amazon Attributes</h2>
        <p class="text-muted">Configure Amazon-specific attributes for toy gun products</p>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="amazon-panel">
        <div class="amazon-panel-header d-flex justify-content-between align-items-center">
            <div>
                <p class="amazon-panel-title mb-0">TOY_GUN Amazon Listing Attributes</p>
                <p class="amazon-panel-note mb-0">Complete all required fields for Amazon listing</p>
            </div>
            <span class="amazon-badge">TOY_GUN</span>
        </div>

            <div class="amazon-body">
                <form method="POST" action="{{ request()->url() }}" id="toyAmazonForm">
                    @csrf

                    <!-- Product Identification -->
                    <div class="mb-5">
                        <h6 class="sub-section-title">Product Identification</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Part Number</label>
                                <input type="text" name="part_number" class="form-control" placeholder="e.g., TB-FB3000" value="{{ old('part_number') }}">
                                <small class="text-muted">Manufacturer part number</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model Name</label>
                                <input type="text" name="model_name" class="form-control" placeholder="e.g., Foam Blaster 3000" value="{{ old('model_name') }}">
                                <small class="text-muted">Product model name</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">EAN/UPC</label>
                                <input type="text" name="ean" class="form-control" placeholder="e.g., 4006381333931" value="{{ old('ean') }}">
                                <small class="text-muted">External product identifier</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Condition Type</label>
                                <select name="condition_type" class="form-select">
                                    <option value="new_new" {{ old('condition_type') == 'new_new' ? 'selected' : '' }}>New</option>
                                    <option value="new_refurbished" {{ old('condition_type') == 'new_refurbished' ? 'selected' : '' }}>Refurbished</option>
                                    <option value="used_like_new" {{ old('condition_type') == 'used_like_new' ? 'selected' : '' }}>Used - Like New</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Package Information -->
                    <div class="mb-5">
                        <h6 class="sub-section-title">Package Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Package Dimensions</label>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <label class="form-label small">Length</label>
                                        <input type="number" step="0.1" name="package_length" class="form-control" placeholder="12" value="{{ old('package_length', 12) }}">
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label small">Width</label>
                                        <input type="number" step="0.1" name="package_width" class="form-control" placeholder="6" value="{{ old('package_width', 6) }}">
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label small">Height</label>
                                        <input type="number" step="0.1" name="package_height" class="form-control" placeholder="3" value="{{ old('package_height', 3) }}">
                                    </div>
                                    <div style="width: 80px;">
                                        <label class="form-label small">Unit</label>
                                        <select name="package_unit" class="form-select">
                                            <option value="inches" {{ old('package_unit') == 'inches' ? 'selected' : '' }}>inches</option>
                                            <option value="centimeters" {{ old('package_unit') == 'centimeters' ? 'selected' : '' }}>cm</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Package Weight</label>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <input type="number" step="0.1" name="package_weight" class="form-control" placeholder="1.0" value="{{ old('package_weight', 1.0) }}">
                                    </div>
                                    <div style="width: 100px;">
                                        <select name="weight_unit" class="form-select">
                                            <option value="pounds" {{ old('weight_unit') == 'pounds' ? 'selected' : '' }}>pounds</option>
                                            <option value="ounces" {{ old('weight_unit') == 'ounces' ? 'selected' : '' }}>ounces</option>
                                            <option value="kilograms" {{ old('weight_unit') == 'kilograms' ? 'selected' : '' }}>kg</option>
                                            <option value="grams" {{ old('weight_unit') == 'grams' ? 'selected' : '' }}>g</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Number of Items</label>
                                <input type="number" name="number_of_items" class="form-control" placeholder="1" value="{{ old('number_of_items', 1) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Number of Boxes</label>
                                <input type="number" name="number_of_boxes" class="form-control" placeholder="1" value="{{ old('number_of_boxes', 1) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit Count</label>
                                <input type="number" name="unit_count" class="form-control" placeholder="1" value="{{ old('unit_count', 1) }}">
                            </div>
                        </div>
                    </div>

                    <!-- Product Details -->
                    <div class="mb-5">
                        <h6 class="sub-section-title">Product Details</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control" placeholder="e.g., Black" value="{{ old('color') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Material</label>
                                <input type="text" name="material" class="form-control" placeholder="e.g., plastic" value="{{ old('material') }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Theme</label>
                                <input type="text" name="theme" class="form-control" placeholder="e.g., Action" value="{{ old('theme') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country of Origin</label>
                                <select name="country_of_origin" class="form-select">
                                    <option value="US" {{ old('country_of_origin') == 'US' ? 'selected' : '' }}>United States</option>
                                    <option value="CN" {{ old('country_of_origin') == 'CN' ? 'selected' : '' }}>China</option>
                                    <option value="IN" {{ old('country_of_origin') == 'IN' ? 'selected' : '' }}>India</option>
                                    <option value="DE" {{ old('country_of_origin') == 'DE' ? 'selected' : '' }}>Germany</option>
                                    <option value="JP" {{ old('country_of_origin') == 'JP' ? 'selected' : '' }}>Japan</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Included Components</label>
                            <input type="text" name="included_components" class="form-control" placeholder="e.g., 1 Toy Gun, 10 Foam Bullets" value="{{ old('included_components') }}">
                            <small class="text-muted">List all items included in the package</small>
                        </div>
                    </div>

                    <!-- Safety & Age Information -->
                    <div class="mb-5">
                        <h6 class="sub-section-title">Safety & Age Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Age Range Description</label>
                                <input type="text" name="age_range_description" class="form-control" placeholder="e.g., 8-13 years" value="{{ old('age_range_description') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Safety Warning</label>
                                <textarea name="safety_warning" class="form-control" rows="2" placeholder="e.g., Choking Hazard - Small parts. Not for children under 3 years.">{{ old('safety_warning') }}</textarea>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Manufacturer Minimum Age</label>
                                <div class="d-flex gap-2">
                                    <input type="number" name="min_age" class="form-control" placeholder="96" value="{{ old('min_age', 96) }}">
                                    <select name="min_age_unit" class="form-select" style="width: 100px;">
                                        <option value="months" {{ old('min_age_unit') == 'months' ? 'selected' : '' }}>months</option>
                                        <option value="years" {{ old('min_age_unit') == 'years' ? 'selected' : '' }}>years</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Manufacturer Maximum Age</label>
                                <div class="d-flex gap-2">
                                    <input type="number" name="max_age" class="form-control" placeholder="156" value="{{ old('max_age', 156) }}">
                                    <select name="max_age_unit" class="form-select" style="width: 100px;">
                                        <option value="months" {{ old('max_age_unit') == 'months' ? 'selected' : '' }}>months</option>
                                        <option value="years" {{ old('max_age_unit') == 'years' ? 'selected' : '' }}>years</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">CPSIA Cautionary Statement</label>
                                <select name="cpsia_cautionary_statement" class="form-select">
                                    <option value="choking_hazard_small_parts" {{ old('cpsia_cautionary_statement') == 'choking_hazard_small_parts' ? 'selected' : '' }}>Choking Hazard - Small Parts</option>
                                    <option value="choking_hazard_balloon" {{ old('cpsia_cautionary_statement') == 'choking_hazard_balloon' ? 'selected' : '' }}>Choking Hazard - Balloon</option>
                                    <option value="choking_hazard_marbles" {{ old('cpsia_cautionary_statement') == 'choking_hazard_marbles' ? 'selected' : '' }}>Choking Hazard - Marbles</option>
                                    <option value="no_warning_applicable" {{ old('cpsia_cautionary_statement') == 'no_warning_applicable' ? 'selected' : '' }}>No Warning Applicable</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Regulatory & Technical -->
                    <div class="mb-5">
                        <h6 class="sub-section-title">Regulatory & Technical</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batteries Required</label>
                                <select name="batteries_required" class="form-select">
                                    <option value="true" {{ old('batteries_required') == 'true' ? 'selected' : '' }}>Yes</option>
                                    <option value="false" {{ old('batteries_required') == 'false' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assembly Required</label>
                                <select name="is_assembly_required" class="form-select">
                                    <option value="true" {{ old('is_assembly_required') == 'true' ? 'selected' : '' }}>Yes</option>
                                    <option value="false" {{ old('is_assembly_required') == 'false' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Declared DG HZ Regulation</label>
                                <select name="supplier_declared_dg_hz_regulation" class="form-select">
                                    <option value="not_applicable" {{ old('supplier_declared_dg_hz_regulation') == 'not_applicable' ? 'selected' : '' }}>Not Applicable</option>
                                    <option value="not_regulated" {{ old('supplier_declared_dg_hz_regulation') == 'not_regulated' ? 'selected' : '' }}>Not Regulated</option>
                                    <option value="excepted_quantity" {{ old('supplier_declared_dg_hz_regulation') == 'excepted_quantity' ? 'selected' : '' }}>Excepted Quantity</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Type Keyword</label>
                                <input type="text" name="item_type_keyword" class="form-control" placeholder="Toys & Games > Toy Guns & Blasters" value="{{ old('item_type_keyword', 'Toys & Games > Toy Guns & Blasters') }}">
                            </div>
                        </div>
                    </div>

                    <!-- Fulfillment -->
                    <div class="mb-5">
                        <h6 class="sub-section-title">Fulfillment</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fulfillment Channel Code</label>
                                <select name="fulfillment_channel_code" class="form-select">
                                    <option value="AMAZON_NA" {{ old('fulfillment_channel_code') == 'AMAZON_NA' ? 'selected' : '' }}>Amazon North America</option>
                                    <option value="DEFAULT" {{ old('fulfillment_channel_code') == 'DEFAULT' ? 'selected' : '' }}>Default</option>
                                    <option value="MFN" {{ old('fulfillment_channel_code') == 'MFN' ? 'selected' : '' }}>Merchant Fulfilled</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fulfillment Quantity</label>
                                <input type="number" name="fulfillment_quantity" class="form-control" placeholder="100" value="{{ old('fulfillment_quantity', 100) }}">
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-dark">Back</a>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="submit" class="btn btn-success">Save Amazon Attributes</button>
                        </div>
                    </div>
                </form>
            </div>
    </div>
</div>
@endsection