@extends('layouts.app')
@section('content')
@push('css')
<style>
    /* Shopify Admin Inspired UI - Ultra Tight Spacing */
    .pg-wrap {
        background-color: #F6F6F7;
        padding: 16px 20px;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
    }

    .pg-header {
        margin-bottom: 12px;
    }

    .pg-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        letter-spacing: -0.01em;
        margin: 0 0 2px 0;
        line-height: 1.2;
    }

    .pg-subtitle {
        font-size: 13px;
        color: #6B7280;
        margin: 0;
    }

    .card-shell {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .subcategory-dropdown {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        width: 100%;
        box-sizing: border-box;

        z-index: 99999;

        display: none;
        background: #fff;
        border: 1px solid #D1D5DB;
        border-radius: 6px;

        box-shadow:
            0 4px 6px rgba(0, 0, 0, 0.08),
            0 10px 20px rgba(0, 0, 0, 0.06);

        max-height: 240px;
        overflow-y: auto;
        padding: 4px 0;
    }

    .subcategory-dropdown .list-group-item {
        display: block;
        width: 100%;

        padding: 8px 10px;

        border: 0;
        border-bottom: 1px solid #F3F4F6;

        background: #fff;
        color: #111827;

        font-size: 13px;
        text-align: left;

        cursor: pointer;
    }

    .subcategory-dropdown .list-group-item:last-child {
        border-bottom: 0;
    }

    .subcategory-dropdown .list-group-item:hover {
        background: #F9FAFB;
    }

    .subcategory-dropdown .list-group-item:focus {
        background: #F3F4F6;
        outline: none;
    }

    .subcategory-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .subcategory-dropdown::-webkit-scrollbar-thumb {
        background: #D1D5DB;
        border-radius: 10px;
    }

    .panel {
        background: #FFFFFF;
        padding: 16px;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
        transition: none;
    }

    .section-head {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }

    .section-icon {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: grid;
        place-items: center;
        font-size: 13px;
        flex-shrink: 0;
        background: #F3F4F6 !important;
        color: #4B5563 !important;
    }

    .section-title {
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0;
        margin: 0;
        color: #111827;
        line-height: 1.2;
    }

    .section-desc {
        font-size: 12px;
        /* color: #6B7280; */
        margin: 2px 0 0 0;
        font-weight: 400;
    }

    /* Form Controls */
    .form-label {
        font-size: 12px;
        font-weight: 500;
        /* color: #374151; */
        margin-bottom: 4px;
    }

    .form-control,
    .form-select {
        font-size: 13px;
        border: 1px solid #D1D5DB;
        background: #FFFFFF;
        color: #111827;
        border-radius: 6px;
        padding: 6px 10px;
        height: 32px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01) inset;
    }

    textarea.form-control {
        height: auto;
        min-height: 80px;
        padding: 8px 10px;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #2563EB;
        outline: none;
        box-shadow: 0 0 0 1px #2563EB;
    }

    .form-control::placeholder {
        color: #9CA3AF;
    }

    .form-control[readonly] {
        background: #F9FAFB;
        color: #6B7280;
        cursor: not-allowed;
    }

    .form-control-sm {
        height: 28px;
        padding: 4px 8px;
        font-size: 12px;
        border-radius: 4px;
    }

    .mb-3 {
        margin-bottom: 12px !important;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        transition: background-color 0.15s, border-color 0.15s, color 0.15s;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid transparent;
        box-sizing: border-box;
        line-height: 1;
    }

    .btn-outline-dark {
        background: #FFFFFF;
        color: #111827;
        border-color: #D1D5DB;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .btn-outline-dark:hover {
        background: #F9FAFB;
        color: #111827;
        border-color: #9CA3AF;
    }

    .btn-outline-danger {
        background: transparent;
        color: #DC2626;
        border-color: #DC2626;
        height: 32px;
        width: 100%;
    }

    .btn-outline-danger:hover {
        background: #FEF2F2;
        color: #DC2626;
    }

    .btn-primary,
    .btn-success {
        background: #111827;
        border-color: #111827;
        color: #FFFFFF;
    }

    .btn-primary:hover,
    .btn-success:hover {
        background: #374151;
        border-color: #374151;
        color: #FFFFFF;
    }

    .btn-success {
        background: #16A34A;
        border-color: #16A34A;
    }

    .btn-success:hover {
        background: #15803d;
        border-color: #15803d;
    }

    .btn a {
        color: inherit !important;
        text-decoration: none;
    }

    /* Custom Sections */
    .meta-field-row {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 10px;
        margin-bottom: 8px;
    }

    .variant-type-box {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 8px;
        position: relative;
    }

    .variant-type-box:hover {
        border-color: #D1D5DB;
    }

    .remove-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        cursor: pointer;
        color: #6B7280;
        font-size: 14px;
        line-height: 1;
        padding: 2px 4px;
        border-radius: 4px;
        background: #F3F4F6;
    }

    .remove-btn:hover {
        background: #E5E7EB;
        color: #111827;
    }

    /* Matrix Table */
    .matrix-table {
        overflow-x: auto;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
    }

    .matrix-table table {
        width: 100%;
        min-width: 580px;
        border-collapse: collapse;
    }

    .matrix-table thead tr {
        background: #F9FAFB;
    }

    .matrix-table th {
        padding: 8px 12px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #4B5563;
        border-bottom: 1px solid #E5E7EB;
        white-space: nowrap;
    }

    .matrix-table td {
        padding: 6px 12px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: middle;
        font-size: 12px;
        color: #111827;
    }

    .matrix-table tbody tr:last-child td {
        border-bottom: none;
    }

    .matrix-table tbody tr:hover td {
        background-color: #F9FAFB;
    }

    .matrix-table td:first-child {
        font-weight: 500;
    }

    /* Misc */
    .gen-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .file-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
    }

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

    .preview-image-item {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 52px;
        margin-right: 2px;
        margin-bottom: 2px;
    }

    .preview-image-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
    }

    .preview-image-item .remove-preview-btn {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 18px;
        height: 18px;
        background: #DC2626;
        color: #fff;
        border-radius: 50%;
        border: 2px solid #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
        line-height: 1;
        padding: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .variant-image-preview {
        width: 36px;
        height: 36px;
        object-fit: contain;
        display: block;
    }

    .variant-img-cell {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #DC2626 !important;
        box-shadow: 0 0 0 1px #DC2626 !important;
        background-color: #FEF2F2 !important;
    }

    #saveProductBtn:disabled,
    #saveProductBtn[disabled] {
        background: #9CA3AF !important;
        border-color: #9CA3AF !important;
        cursor: not-allowed !important;
        opacity: 0.65 !important;
        box-shadow: none !important;
        pointer-events: none !important;
    }

    .preview-image-item .remove-preview-btn:hover {
        background: #B91C1C;
    }

    .preview-image-item .library-badge {
        position: absolute;
        bottom: 2px;
        left: 2px;
        background: rgba(37, 99, 235, 0.85);
        color: #fff;
        font-size: 8px;
        padding: 1px 3px;
        border-radius: 3px;
        line-height: 1;
        font-weight: 600;
        text-transform: uppercase;
        pointer-events: none;
    }

    .alert-danger {
        background: #FEF2F2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 13px;
        margin-bottom: 12px;
    }

    .alert-danger ul {
        margin: 0;
        padding-left: 20px;
    }

    .panel-divider {
        height: 1px;
        background: #E5E7EB;
        margin: 16px 0;
    }

    .text-muted {
        color: #6B7280 !important;
        font-size: 12px;
        margin: 0;
    }

    .mt-3 {
        margin-top: 12px !important;
    }

    .mt-4 {
        margin-top: 16px !important;
    }

    /* Footer actions */
    .footer-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #E5E7EB;
    }

    @media (max-width: 768px) {
        .gen-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .gen-row button {
            width: 100%;
        }
    }
</style>
@endpush

<div class="pg-wrap container-fluid">
    <!-- Page header -->
    <div class="saas-page-header">
        <h4 class="pg-title">Create Product</h4>
        <p class="pg-subtitle">Fill in the details below to list your product</p>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('shopify.product.create.post', ['shop' => $activeShop]) }}" enctype="multipart/form-data" id="productForm">
        @csrf
        <div class="card-shell">

            <!-- ── 1. Core Details ── -->
            <div class="panel">
                <div class="section-head">
                    <div>
                        <p class="section-title">Core Details</p>
                        <p class="section-desc">Basic product information</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Product Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Enter product title" value="{{ old('title') }}" required>
                    </div>
                    @php $n = old('category',0); @endphp
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Product Category</label>
                        <select
                            name="category"
                            id="category"
                            class="form-select"
                            onchange="updatecategory(this.value)"
                            required>
                            <option value="">Select Category</option>
                            @foreach(getCategorires() as $categories)
                            @php if($n == 0){ $n = $categories['id']; } @endphp
                            <option value="<?= $categories['id'] ?>" {{ old('category') == $categories['id'] ? 'selected' : '' }}><?= $categories['name'] ?></option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 col-md-4">
                        <label for="sub_category_search" class="form-label">
                            Sub Category
                        </label>

                        <div class="position-relative">
                            <input
                                type="text"
                                id="sub_category_search"
                                class="form-control"
                                placeholder="Search sub category..."
                                autocomplete="off"
                                disabled
                                required>

                            <input
                                type="hidden"
                                name="sub_category"
                                id="sub_category"
                                value="{{ old('sub_category') }}">

                            <div
                                id="sub_category_results"
                                class="subcategory-dropdown">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Product description" required>{{ old('description') }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            Base Price ({{ $currency }})
                        </label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" value="{{ old('price') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="Enter SKU" value="{{ old('sku') }}">
                    </div>
                </div>

                <input type="hidden" name="amazon_title" id="amazonTitle" value="{{ old('amazon_title') }}">

                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                        <label class="form-label mb-0">Product Images (Multiple)</label>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="openImageLibraryBtn" style="height: 28px; font-size: 12px; padding: 0 10px;">
                            <i class="bi bi-images me-1"></i> Select from Image Upload
                        </button>
                    </div>
                    <input type="file" name="images[]" class="form-control" style="padding-top:4px;" multiple accept="image/*" id="imageUpload">
                    <div class="file-preview mt-2" id="imagePreview"></div>
                    <div id="libraryHiddenInputs"></div>
                </div>
            </div>

            <!-- ── 2. Organization ── -->
            <div class="panel">
                <div class="section-head">
                    <div>
                        <p class="section-title">Product Organization</p>
                        <p class="section-desc">Type, vendor, collections, and tags</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Product Type</label>
                        <input type="text" name="product_type" class="form-control" placeholder="e.g., Clothing, Electronics" value="{{ old('product_type') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vendor</label>
                        <input type="text" name="vendor" class="form-control" placeholder="e.g., Nike, Apple" value="{{ old('vendor') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Collections</label>
                        <input type="text" name="collections" class="form-control" placeholder="Comma separated collections" value="{{ old('collections') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="Comma separated tags" value="{{ old('tags') }}" required>
                    </div>
                </div>
            </div>

            <!-- ── 3. Metafields ── -->
            <div class="panel">
                <div class="section-head">
                    <div>
                        <p class="section-title">Custom Metafields</p>
                        <p class="section-desc">Add name-value pairs for extended data</p>
                    </div>
                </div>
                <div id="metaFieldsContainer">
                    @php
                    $metaNames = old('meta_name', []);
                    $metaValues = old('meta_value', []);
                    $metaCount = max(count($metaNames), count($metaValues), 1);
                    @endphp
                    @for($i = 0; $i < $metaCount; $i++)
                        <div class="meta-field-row row">
                        <div class="col-md-5">
                            <label class="form-label">Name</label>
                            <input type="text" name="meta_name[]" class="form-control" placeholder="e.g., material" value="{{ $metaNames[$i] ?? '' }}">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Value</label>
                            <input type="text" name="meta_value[]" class="form-control" placeholder="e.g., Cotton" value="{{ $metaValues[$i] ?? '' }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger" onclick="removeMetaField(this)">Remove</button>
                        </div>
                </div>
                @endfor
            </div>
            <button type="button" onclick="addMetaField()" class="btn btn-outline-dark mt-2">
                Add Metafield
            </button>
        </div>

        <!-- ── 4. Variants ── -->
        <div class="panel">
            <div class="section-head">
                <div>
                    <p class="section-title">Variants</p>
                    <p class="section-desc">Define Color, Size etc. (comma-separated)</p>
                </div>
            </div>

            <div id="variantTypesContainer">
                @php
                $variantNames = old('variant_names', ['Color', 'Size']);
                $variantValues = old('variant_values', ['Red, Blue', 'S, M']);
                $variantCount = max(count($variantNames), count($variantValues), 2);
                @endphp
                @for($i = 0; $i < $variantCount; $i++)
                    <div class="variant-type-box">
                    <span class="remove-btn" onclick="removeVariantType(this)">✕</span>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Type Name</label>
                            <input type="text" class="form-control variant-type-name" name="variant_names[]" placeholder="e.g., Color" value="{{ $variantNames[$i] ?? '' }}">
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="form-label">Possible Values</label>
                            <input type="text" class="form-control variant-type-values" name="variant_values[]" placeholder="e.g., Red, Blue" value="{{ $variantValues[$i] ?? '' }}">
                        </div>
                    </div>
            </div>
            @endfor
        </div>

        <button type="button" onclick="addVariantType()" class="btn btn-outline-dark mt-2 mb-3">
            Add Variant Type
        </button>

        <div class="panel-divider"></div>

        <div class="gen-row">
            <div>
                <p class="section-title">Variant Combinations</p>
                <p class="text-muted" style="margin-top:2px;">Click generate to build the matrix</p>
            </div>
            <button type="button" onclick="generateCombinations()" class="btn btn-primary">
                Generate
            </button>
        </div>

        <div class="matrix-table">
            <div id="combinationMatrix" style="padding: 12px;">
                <p class="text-muted">No combinations yet.</p>
            </div>
        </div>
</div>

<!-- ── Submit ── -->
<div class="footer-actions">
    <a href="{{ route('shopify.products', ['shop' => $activeShop]) }}" class="btn btn-outline-dark">
        Cancel
    </a>
    <button type="submit" class="btn btn-success" id="saveProductBtn" disabled>
        Save Product
    </button>
</div>

</div><!-- /card-shell -->
</form>
</div><!-- /pg-wrap -->

<!-- Image Library Selection Modal -->
<div class="modal fade" id="imageLibraryModal" tabindex="-1" aria-labelledby="imageLibraryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 12px; border: 1px solid #E5E7EB;">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid #F3F4F6;">
                <div>
                    <h5 class="modal-title fw-semibold text-dark mb-0" id="imageLibraryModalLabel" style="font-size: 15px;">
                        Select Images from Upload Library
                    </h5>
                    <p class="text-muted small mb-0" id="imageLibraryModalSubtitle" style="font-size: 12px;">Choose existing images previously uploaded to your library</p>
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
                                <i class="bi bi-grid-fill me-1 text-primary"></i> Add Product
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
                    <button type="button" class="btn btn-success btn-sm" id="confirmLibrarySelectionBtn">Add Selected Images</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
    const productForm = document.getElementById('productForm');
    const productTitleInput = document.querySelector('input[name="title"]');
    const amazonTitleInput = document.getElementById('amazonTitle');
    const currency = @json($currency);


    // Amazon Title Sync Logic Restored
    if (productTitleInput && amazonTitleInput) {
        const syncAmazonTitle = () => {
            amazonTitleInput.value = productTitleInput.value;
        };
        syncAmazonTitle();
        productTitleInput.addEventListener('input', syncAmazonTitle);
    }

    // --- Image Library & Device Upload Management ---
    const imageUpload = document.getElementById('imageUpload');
    const imagePreview = document.getElementById('imagePreview');
    const libraryHiddenInputs = document.getElementById('libraryHiddenInputs');
    const openImageLibraryBtn = document.getElementById('openImageLibraryBtn');
    const libraryModalEl = document.getElementById('imageLibraryModal');
    const libraryModal = new bootstrap.Modal(libraryModalEl);
    const libraryImagesGrid = document.getElementById('libraryImagesGrid');
    const libraryImagesLoading = document.getElementById('libraryImagesLoading');
    const libraryEmptyState = document.getElementById('libraryEmptyState');
    const selectedLibraryCount = document.getElementById('selectedLibraryCount');
    const confirmLibrarySelectionBtn = document.getElementById('confirmLibrarySelectionBtn');
    const libraryPagination = document.getElementById('libraryPagination');
    const libraryPaginationInfo = document.getElementById('libraryPaginationInfo');
    const libraryPrevPageBtn = document.getElementById('libraryPrevPageBtn');
    const libraryNextPageBtn = document.getElementById('libraryNextPageBtn');

    // Modal Upload from Device Controls
    const modalDeviceUploadBtn = document.getElementById('modalDeviceUploadBtn');
    const modalDeviceUploadInput = document.getElementById('modalDeviceUploadInput');
    const modalUploadBtnText = document.getElementById('modalUploadBtnText');
    const modalUploadIcon = document.getElementById('modalUploadIcon');
    const modalUploadBtnSpinner = document.getElementById('modalUploadBtnSpinner');
    const modalAlertMessage = document.getElementById('modalAlertMessage');

    const IMAGES_PER_TAB_PAGE = 10;
    let allLibraryImages = [];
    let currentLibraryPage = 1;
    let selectedLibraryImages = []; // Array of { id, url, name, path }
    let modalSelectedMap = new Map(); // Map of url => image object currently toggled in modal

    // Target Management: 'gallery' OR { type: 'variant', index: idx }
    let currentModalTarget = 'gallery';
    const variantImageMap = {}; // idx => { url, name, key }
    const savedComboImageMap = {}; // comboKey => { url, name }

    // Upload from Device inside modal
    if (modalDeviceUploadBtn && modalDeviceUploadInput) {
        modalDeviceUploadBtn.addEventListener('click', function() {
            modalDeviceUploadInput.value = '';
            modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';
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
            modalUploadIcon.classList.add('d-none');
            modalUploadBtnSpinner.classList.remove('d-none');
            modalUploadBtnText.textContent = 'Uploading...';
            modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';

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

                if (currentModalTarget === 'gallery') {
                    modalSelectedMap.set(newImg.url, newImg);
                } else if (currentModalTarget && currentModalTarget.type === 'variant') {
                    modalSelectedMap.clear();
                    modalSelectedMap.set(newImg.url, newImg);
                    setVariantImage(currentModalTarget.index, newImg.url, newImg.name);
                }

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
                modalUploadIcon.classList.remove('d-none');
                modalUploadBtnSpinner.classList.add('d-none');
                modalUploadBtnText.textContent = 'Upload from Device';
                modalDeviceUploadInput.value = '';
            });
        });
    }

    function showModalAlert(message, type = 'danger') {
        if (!modalAlertMessage) return;
        modalAlertMessage.textContent = message;
        modalAlertMessage.className = `alert alert-${type} py-2 px-3 small mb-3`;
    }

    function getVariantComboKey(idx) {
        const hiddenCombo = document.querySelector(`input[name="variant_combo[${idx}]"]`);
        if (hiddenCombo && hiddenCombo.value) {
            try {
                const parsed = JSON.parse(hiddenCombo.value);
                return parsed.map(c => `${c.type}:${c.value}`).join('|');
            } catch (e) {}
        }
        return `variant_${idx}`;
    }

    function renderVariantImage(idx) {
        const td = document.getElementById(`variant_img_td_${idx}`);
        if (!td) return;
        const current = variantImageMap[idx];
        if (current && current.url) {
            td.innerHTML = `
                <div class="variant-img-cell d-flex align-items-center gap-2" id="variant_img_cell_${idx}">
                    <div style="position: relative; width: 36px; height: 36px; border: 1px solid #D1D5DB; border-radius: 6px; overflow: hidden; background: #F9FAFB; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                        <img src="${current.url}" alt="${current.name || ''}" class="variant-image-preview">
                    </div>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <button type="button" class="btn btn-link p-0 text-primary text-decoration-none" style="font-size: 11px; text-align: left;" onclick="openVariantImageModal(${idx})">Change</button>
                        <button type="button" class="btn btn-link p-0 text-danger text-decoration-none" style="font-size: 11px; text-align: left;" onclick="removeVariantImage(${idx})">Remove</button>
                    </div>
                    <input type="hidden" name="variants[${idx}][image]" id="variant_image_input_${idx}" value="${current.url}">
                </div>
            `;
        } else {
            td.innerHTML = `
                <div class="variant-img-cell" id="variant_img_cell_${idx}">
                    <button type="button" class="btn btn-outline-dark btn-sm select-variant-img-btn" onclick="openVariantImageModal(${idx})" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">
                        <i class="bi bi-image me-1"></i> Select Image
                    </button>
                    <input type="hidden" name="variants[${idx}][image]" id="variant_image_input_${idx}" value="">
                </div>
            `;
        }
    }

    function setVariantImage(idx, url, name) {
        const comboKey = getVariantComboKey(idx);
        variantImageMap[idx] = { url, name: name || '', key: comboKey };
        if (comboKey) {
            savedComboImageMap[comboKey] = { url, name: name || '' };
        }
        renderVariantImage(idx);
    }

    function removeVariantImage(idx) {
        const comboKey = getVariantComboKey(idx);
        delete variantImageMap[idx];
        if (comboKey && savedComboImageMap[comboKey]) {
            delete savedComboImageMap[comboKey];
        }
        renderVariantImage(idx);
    }

    function openVariantImageModal(idx) {
        currentModalTarget = { type: 'variant', index: idx };
        document.getElementById('imageLibraryModalLabel').textContent = 'Select Image for Variant';
        document.getElementById('imageLibraryModalSubtitle').textContent = 'Choose an image from your library or upload from device';
        confirmLibrarySelectionBtn.textContent = 'Select Image';
        modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';

        modalSelectedMap.clear();
        if (variantImageMap[idx]) {
            const curr = variantImageMap[idx];
            modalSelectedMap.set(curr.url, { url: curr.url, name: curr.name });
        }
        updateModalCounter();
        libraryModal.show();

        if (allLibraryImages.length === 0) {
            fetchLibraryImages();
        } else {
            renderLibraryTabPage();
        }
    }

    // Render unified previews (device files + library images)
    function renderAllImagePreviews() {
        imagePreview.innerHTML = '';

        // 1. Render Device Uploaded Images
        if (imageUpload.files && imageUpload.files.length > 0) {
            Array.from(imageUpload.files).forEach((file, index) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'preview-image-item';

                const img = document.createElement('img');
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = ev => { img.src = ev.target.result; };
                reader.readAsDataURL(file);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-preview-btn';
                removeBtn.innerHTML = '×';
                removeBtn.title = 'Remove image';
                removeBtn.addEventListener('click', () => {
                    removeDeviceFile(index);
                });

                itemDiv.appendChild(img);
                itemDiv.appendChild(removeBtn);
                imagePreview.appendChild(itemDiv);
            });
        }

        // 2. Render Selected Library Images
        selectedLibraryImages.forEach((libImg, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'preview-image-item';

            const img = document.createElement('img');
            img.src = libImg.url;
            img.alt = libImg.name;

            const badge = document.createElement('span');
            badge.className = 'library-badge';
            badge.textContent = 'Lib';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-preview-btn';
            removeBtn.innerHTML = '×';
            removeBtn.title = 'Remove library image';
            removeBtn.addEventListener('click', () => {
                removeLibraryImage(index);
            });

            itemDiv.appendChild(img);
            itemDiv.appendChild(badge);
            itemDiv.appendChild(removeBtn);
            imagePreview.appendChild(itemDiv);
        });

        // Sync hidden inputs for library images
        syncLibraryHiddenInputs();
    }

    // Remove a device file from imageUpload input using DataTransfer
    function removeDeviceFile(indexToRemove) {
        if (!imageUpload.files) return;
        const dt = new DataTransfer();
        Array.from(imageUpload.files).forEach((file, idx) => {
            if (idx !== indexToRemove) {
                dt.items.add(file);
            }
        });
        imageUpload.files = dt.files;
        renderAllImagePreviews();
    }

    // Remove a library image from selection
    function removeLibraryImage(indexToRemove) {
        selectedLibraryImages.splice(indexToRemove, 1);
        renderAllImagePreviews();
    }

    // Sync hidden existing_images[] inputs for backend store()
    function syncLibraryHiddenInputs() {
        libraryHiddenInputs.innerHTML = '';
        selectedLibraryImages.forEach(libImg => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'existing_images[]';
            hidden.value = libImg.url;
            libraryHiddenInputs.appendChild(hidden);
        });
    }

    // Handle device file input change
    imageUpload.addEventListener('change', function() {
        renderAllImagePreviews();
    });

    // Open Library Modal
    openImageLibraryBtn.addEventListener('click', function() {
        currentModalTarget = 'gallery';
        document.getElementById('imageLibraryModalLabel').textContent = 'Select Images from Upload Library';
        document.getElementById('imageLibraryModalSubtitle').textContent = 'Choose existing images previously uploaded to your library';
        confirmLibrarySelectionBtn.textContent = 'Add Selected Images';
        modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';

        modalSelectedMap.clear();
        selectedLibraryImages.forEach(img => {
            modalSelectedMap.set(img.url, img);
        });
        updateModalCounter();

        libraryModal.show();

        if (allLibraryImages.length === 0) {
            fetchLibraryImages();
        } else {
            renderLibraryTabPage();
        }
    });

    // Fetch images from API endpoint
    function fetchLibraryImages() {
        libraryImagesLoading.classList.remove('d-none');
        libraryImagesGrid.innerHTML = '';
        libraryEmptyState.classList.add('d-none');
        libraryPagination.classList.add('d-none');

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
                libraryEmptyState.classList.remove('d-none');
            }
        })
        .catch(err => {
            console.error('Failed to load library images:', err);
            libraryImagesLoading.classList.add('d-none');
            libraryEmptyState.textContent = 'Unable to load images. Please try again.';
            libraryEmptyState.classList.remove('d-none');
        });
    }

    // Render Modal Images for the current tab page (Strictly 10 max per page/tab)
    function renderLibraryTabPage() {
        libraryImagesGrid.innerHTML = '';

        if (!allLibraryImages || allLibraryImages.length === 0) {
            libraryEmptyState.classList.remove('d-none');
            libraryPagination.classList.add('d-none');
            return;
        }

        libraryEmptyState.classList.add('d-none');

        const totalImages = allLibraryImages.length;
        const totalPages = Math.max(1, Math.ceil(totalImages / IMAGES_PER_TAB_PAGE));
        if (currentLibraryPage > totalPages) {
            currentLibraryPage = totalPages;
        }
        if (currentLibraryPage < 1) {
            currentLibraryPage = 1;
        }

        const startIndex = (currentLibraryPage - 1) * IMAGES_PER_TAB_PAGE;
        const pageImages = allLibraryImages.slice(startIndex, startIndex + IMAGES_PER_TAB_PAGE);

        pageImages.forEach(img => {
            const card = document.createElement('div');
            const isSelected = modalSelectedMap.has(img.url);
            card.className = 'library-image-card' + (isSelected ? ' is-selected' : '');
            card.setAttribute('data-url', img.url);

            card.innerHTML = `
                <div class="image-library-preview-wrapper">
                    <img src="${img.url}" alt="${img.name}" class="image-library-preview">
                </div>
                <span class="select-badge">${isSelected ? '✓' : '+'}</span>
            `;

            card.addEventListener('click', function() {
                if (currentModalTarget === 'gallery') {
                    if (modalSelectedMap.has(img.url)) {
                        modalSelectedMap.delete(img.url);
                        card.classList.remove('is-selected');
                        card.querySelector('.select-badge').textContent = '+';
                    } else {
                        modalSelectedMap.set(img.url, img);
                        card.classList.add('is-selected');
                        card.querySelector('.select-badge').textContent = '✓';
                    }
                } else if (currentModalTarget && currentModalTarget.type === 'variant') {
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
                }
                updateModalCounter();
            });

            libraryImagesGrid.appendChild(card);
        });

        // Update Pagination Controls
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

    // Pagination Click Listeners
    libraryPrevPageBtn.addEventListener('click', function() {
        if (currentLibraryPage > 1) {
            currentLibraryPage--;
            renderLibraryTabPage();
        }
    });

    libraryNextPageBtn.addEventListener('click', function() {
        const totalPages = Math.ceil(allLibraryImages.length / IMAGES_PER_TAB_PAGE);
        if (currentLibraryPage < totalPages) {
            currentLibraryPage++;
            renderLibraryTabPage();
        }
    });

    function updateModalCounter() {
        const count = modalSelectedMap.size;
        selectedLibraryCount.textContent = `Selected: ${count} image${count === 1 ? '' : 's'}`;
    }

    // Confirm selection from modal
    confirmLibrarySelectionBtn.addEventListener('click', function() {
        if (currentModalTarget === 'gallery') {
            selectedLibraryImages = Array.from(modalSelectedMap.values());
            renderAllImagePreviews();
        } else if (currentModalTarget && currentModalTarget.type === 'variant') {
            const idx = currentModalTarget.index;
            if (modalSelectedMap.size > 0) {
                const selectedImg = Array.from(modalSelectedMap.values())[0];
                setVariantImage(idx, selectedImg.url, selectedImg.name);
            } else {
                removeVariantImage(idx);
            }
        }
        libraryModal.hide();
    });

    const subCategorySearch = document.getElementById('sub_category_search');
    const subCategoryInput = document.getElementById('sub_category');
    const subCategoryResults = document.getElementById('sub_category_results');

    let subCategoryTimer = null;


    subCategorySearch.addEventListener('input', function() {

        const search = this.value.trim();

        clearTimeout(subCategoryTimer);

        subCategoryResults.innerHTML = '';
        subCategoryResults.style.display = 'none';

        subCategoryInput.value = '';

        if (search.length < 2) {
            return;
        }

        subCategoryTimer = setTimeout(() => {
            searchSubCategories(search);
        }, 500);
    });

    function searchSubCategories(search) {

        const categoryElement = document.getElementById('category');

        if (!categoryElement) {
            console.error('Category element not found');
            return;
        }

        const categoryId = categoryElement.value;

        if (!categoryId) {
            console.warn('No category selected');
            return;
        }

        const url =
            "{{ route('shopify.categories.search') }}" +
            "?parent_id=" + encodeURIComponent(categoryId) +
            "&search=" + encodeURIComponent(search);

        

        const xhr = new XMLHttpRequest();

        xhr.open('GET', url, true);

        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onreadystatechange = function() {

            if (xhr.readyState !== XMLHttpRequest.DONE) {
                return;
            }

            

            if (xhr.status !== 200) {
                console.error('Subcategory request failed:', xhr.status);
                return;
            }

            let categories;

            try {
                categories = JSON.parse(xhr.responseText);
            } catch (error) {
                console.error('Invalid JSON response:', error);
                return;
            }

            subCategoryResults.innerHTML = '';

            if (!Array.isArray(categories) || categories.length === 0) {
                subCategoryResults.innerHTML = `
                <div class="list-group-item text-muted">
                    No sub category found
                </div>
            `;

                subCategoryResults.style.display = 'block';
                return;
            }

            categories.forEach(category => {

                const item = document.createElement('button');

                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = category.name;

                item.addEventListener('click', function() {

                    subCategorySearch.value = category.name;
                    subCategoryInput.value = category.id;
                    subCategorySearch.setCustomValidity('');

                    subCategoryResults.innerHTML = '';
                    subCategoryResults.style.display = 'none';
                    updateSubmitButtonState();
                });

                subCategoryResults.appendChild(item);
            });

            subCategoryResults.style.display = 'block';
        };

        xhr.onerror = function() {
            console.error('XHR NETWORK ERROR');
        };

        xhr.ontimeout = function() {
            console.error('XHR TIMEOUT');
        };

        xhr.timeout = 10000;

        xhr.send();
    }



    function invalidateMatrix() {
        const matrixDiv = document.getElementById('combinationMatrix');
        if (matrixDiv) {
            matrixDiv.style.padding = '12px';
            matrixDiv.innerHTML = '<p class="text-muted">Variant types have changed. Click Generate to build the matrix.</p>';
        }
        const hiddenDiv = document.getElementById('hiddenVariantData');
        if (hiddenDiv) {
            hiddenDiv.innerHTML = '';
        }
        updateSubmitButtonState();
    }

    function addVariantType() {
        const container = document.getElementById('variantTypesContainer');
        const div = document.createElement('div');
        div.classList.add('variant-type-box');
        div.innerHTML = `
            <span class="remove-btn" onclick="removeVariantType(this)">✕</span>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Type Name</label>
                    <input type="text" class="form-control variant-type-name" name="variant_names[]" placeholder="e.g., Material">
                </div>
                <div class="col-md-8 mb-2">
                    <label class="form-label">Possible Values</label>
                    <input type="text" class="form-control variant-type-values" name="variant_values[]" placeholder="e.g., Cotton, Polyester">
                </div>
            </div>`;
        container.appendChild(div);
        invalidateMatrix();
    }

    function removeVariantType(button) {
        const box = button.closest('.variant-type-box');
        if (box) {
            box.remove();
        }
        invalidateMatrix();
    }

    function addMetaField() {
        const container = document.getElementById('metaFieldsContainer');
        const div = document.createElement('div');
        div.classList.add('meta-field-row', 'row');
        div.innerHTML = `
            <div class="col-md-5">
                <label class="form-label">Name</label>
                <input type="text" name="meta_name[]" class="form-control" placeholder="e.g., material">
            </div>
            <div class="col-md-5">
                <label class="form-label">Value</label>
                <input type="text" name="meta_value[]" class="form-control" placeholder="e.g., Cotton">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger" onclick="removeMetaField(this)">Remove</button>
            </div>`;
        container.appendChild(div);
        updateSubmitButtonState();
    }

    function removeMetaField(button) {
        button.closest('.meta-field-row').remove();
        updateSubmitButtonState();
    }

    function generateCombinations() {
        const variantTypes = [];
        document.querySelectorAll('.variant-type-box').forEach(box => {
            const nameEl = box.querySelector('.variant-type-name');
            const valuesEl = box.querySelector('.variant-type-values');
            const name = nameEl ? nameEl.value.trim() : '';
            const values = valuesEl ? valuesEl.value.split(',').map(v => v.trim()).filter(v => v) : [];
            if (name && values.length > 0) variantTypes.push({
                name,
                values
            });
        });
        if (variantTypes.length === 0) {
            alert('Please define at least one variant type with values.');
            return;
        }
        let combinations = [
            []
        ];
        for (const type of variantTypes) {
            const next = [];
            for (const combo of combinations)
                for (const value of type.values)
                    next.push([...combo, {
                        type: type.name,
                        value
                    }]);
            combinations = next;
        }
        const matrixDiv = document.getElementById('combinationMatrix');
        matrixDiv.innerHTML = '';
        matrixDiv.style.padding = '0'; // reset padding for table

        if (combinations.length === 0) {
            matrixDiv.style.padding = '12px';
            matrixDiv.innerHTML = '<p class="text-muted">No combinations generated.</p>';
            updateSubmitButtonState();
            return;
        }
        const table = document.createElement('table');
        let thead = '<thead><tr>';
        variantTypes.forEach(t => thead += `<th>${t.name}</th>`);
        thead += '<th>Image</th><th>Price</th><th>SKU</th><th style="width:70px;">Qty</th></tr></thead>';
        table.innerHTML = thead;
        const tbody = document.createElement('tbody');
        combinations.forEach((combo, idx) => {
            const comboKey = combo.map(c => `${c.type}:${c.value}`).join('|');
            if (!variantImageMap[idx] && savedComboImageMap[comboKey]) {
                variantImageMap[idx] = { ...savedComboImageMap[comboKey], key: comboKey };
            } else if (variantImageMap[idx]) {
                variantImageMap[idx].key = comboKey;
                savedComboImageMap[comboKey] = { url: variantImageMap[idx].url, name: variantImageMap[idx].name };
            }

            const current = variantImageMap[idx];
            const imgCellHtml = (current && current.url)
                ? `<div class="variant-img-cell d-flex align-items-center gap-2" id="variant_img_cell_${idx}">
                    <div style="position: relative; width: 36px; height: 36px; border: 1px solid #D1D5DB; border-radius: 6px; overflow: hidden; background: #F9FAFB; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                        <img src="${current.url}" alt="${current.name || ''}" class="variant-image-preview">
                    </div>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <button type="button" class="btn btn-link p-0 text-primary text-decoration-none" style="font-size: 11px; text-align: left;" onclick="openVariantImageModal(${idx})">Change</button>
                        <button type="button" class="btn btn-link p-0 text-danger text-decoration-none" style="font-size: 11px; text-align: left;" onclick="removeVariantImage(${idx})">Remove</button>
                    </div>
                    <input type="hidden" name="variants[${idx}][image]" id="variant_image_input_${idx}" value="${current.url}">
                </div>`
                : `<div class="variant-img-cell" id="variant_img_cell_${idx}">
                    <button type="button" class="btn btn-outline-dark btn-sm select-variant-img-btn" onclick="openVariantImageModal(${idx})" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">
                        <i class="bi bi-image me-1"></i> Select Image
                    </button>
                    <input type="hidden" name="variants[${idx}][image]" id="variant_image_input_${idx}" value="">
                </div>`;

            const row = document.createElement('tr');
            let cells = '';
            combo.forEach((c, i) => {
                cells += `<td>
                    ${c.value}
                    <input type="hidden" name="variants[${idx}][option${i+1}]" value="${c.value}">
                </td>`;
            });
            cells += `
                <td id="variant_img_td_${idx}">
                    ${imgCellHtml}
                </td>
                <td>
                    <input type="number" step="0.01" name="variants[${idx}][price]" class="form-control form-control-sm" placeholder="0.00">
                </td>
                <td>
                    <input type="text" name="variants[${idx}][sku]" class="form-control form-control-sm" placeholder="SKU">
                </td>
                <td>
                    <input type="number" name="variants[${idx}][qty]" class="form-control form-control-sm" placeholder="0" min="0">
                </td>
            `;
            row.innerHTML = cells;
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        matrixDiv.appendChild(table);

        let hiddenDiv = document.getElementById('hiddenVariantData');
        if (!hiddenDiv) {
            hiddenDiv = document.createElement('div');
            hiddenDiv.id = 'hiddenVariantData';
            hiddenDiv.style.display = 'none';
            matrixDiv.parentNode.insertBefore(hiddenDiv, matrixDiv.nextSibling);
        }
        hiddenDiv.innerHTML = '';
        combinations.forEach((combo, idx) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `variant_combo[${idx}]`;
            hidden.value = JSON.stringify(combo);
            hiddenDiv.appendChild(hidden);
        });

        updateSubmitButtonState();
    }

    function updatecategory(category) {
        subCategorySearch.value = '';
        subCategoryInput.value = '';
        subCategoryResults.innerHTML = '';
        subCategoryResults.style.display = 'none';

        subCategorySearch.disabled = !category;
        if (!category) {
            subCategorySearch.setCustomValidity('');
        }
        updateSubmitButtonState();
    }

    // --- Validation and Submit Button Management ---
    function validateCreateProductForm() {
        // 1. Product Title (required)
        const titleInput = document.querySelector('input[name="title"]');
        if (!titleInput || !titleInput.value.trim()) {
            return false;
        }

        // 2. Product Category (required)
        const categorySelect = document.getElementById('category');
        if (!categorySelect || !categorySelect.value.trim()) {
            return false;
        }

        // 3. Sub Category (required if category is selected)
        const subCatInput = document.getElementById('sub_category');
        if (categorySelect.value.trim() && (!subCatInput || !subCatInput.value.trim())) {
            return false;
        }

        // 4. Description (required)
        const descInput = document.querySelector('textarea[name="description"]');
        if (!descInput || !descInput.value.trim()) {
            return false;
        }

        // 5. Status (required)
        const statusSelect = document.querySelector('select[name="status"]');
        if (!statusSelect || !statusSelect.value.trim()) {
            return false;
        }

        // 6. Base Price (required, valid number >= 0)
        const priceInput = document.querySelector('input[name="price"]');
        if (!priceInput || priceInput.value.trim() === '' || isNaN(priceInput.value) || parseFloat(priceInput.value) < 0) {
            return false;
        }

        // 7. Product Type (required)
        const productTypeInput = document.querySelector('input[name="product_type"]');
        if (!productTypeInput || !productTypeInput.value.trim()) {
            return false;
        }

        // 8. Vendor (required)
        const vendorInput = document.querySelector('input[name="vendor"]');
        if (!vendorInput || !vendorInput.value.trim()) {
            return false;
        }

        // 9. Collections (required)
        const collectionsInput = document.querySelector('input[name="collections"]');
        if (!collectionsInput || !collectionsInput.value.trim()) {
            return false;
        }

        // 10. Tags (required)
        const tagsInput = document.querySelector('input[name="tags"]');
        if (!tagsInput || !tagsInput.value.trim()) {
            return false;
        }

        // 11. Variant matrix validation: Must have generated rows and all rows must be filled and valid
        const combinationRows = document.querySelectorAll('#combinationMatrix tbody tr');
        if (!combinationRows || combinationRows.length === 0) {
            return false;
        }

        for (const row of combinationRows) {
            const vPrice = row.querySelector('input[name$="[price]"]');
            if (!vPrice || vPrice.value.trim() === '' || isNaN(vPrice.value) || parseFloat(vPrice.value) < 0) {
                return false;
            }

            const vSku = row.querySelector('input[name$="[sku]"]');
            if (!vSku || vSku.value.trim() === '') {
                return false;
            }

            const vQty = row.querySelector('input[name$="[qty]"]');
            if (!vQty || vQty.value.trim() === '' || isNaN(vQty.value) || parseInt(vQty.value, 10) < 0) {
                return false;
            }
        }

        // Note: Custom metafields (meta_name[], meta_value[]) are strictly OPTIONAL.

        return true;
    }

    function updateSubmitButtonState() {
        const saveProductBtn = document.getElementById('saveProductBtn');
        if (!saveProductBtn) return;

        const isValid = validateCreateProductForm();
        saveProductBtn.disabled = !isValid;

        if (isValid) {
            hideValidationAlert();
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        }
    }

    function highlightAndFocusFirstInvalidField() {
        const requiredChecks = [
            {
                element: document.querySelector('input[name="title"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please enter a product title.'
            },
            {
                element: document.getElementById('category'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please select a product category.'
            },
            {
                element: document.getElementById('sub_category_search'),
                isValid: () => {
                    const subCat = document.getElementById('sub_category');
                    return subCat && subCat.value.trim() !== '';
                },
                message: 'Please search and select a sub category.'
            },
            {
                element: document.querySelector('textarea[name="description"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please enter a product description.'
            },
            {
                element: document.querySelector('select[name="status"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please select a status.'
            },
            {
                element: document.querySelector('input[name="price"]'),
                isValid: (el) => el && el.value.trim() !== '' && !isNaN(el.value) && parseFloat(el.value) >= 0,
                message: 'Please enter a valid base price (≥ 0.00).'
            },
            {
                element: document.querySelector('input[name="product_type"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please enter a product type.'
            },
            {
                element: document.querySelector('input[name="vendor"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please enter a vendor name.'
            },
            {
                element: document.querySelector('input[name="collections"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please enter collections.'
            },
            {
                element: document.querySelector('input[name="tags"]'),
                isValid: (el) => el && el.value.trim() !== '',
                message: 'Please enter tags.'
            }
        ];

        // Check if combination matrix has generated rows
        const combinationRows = document.querySelectorAll('#combinationMatrix tbody tr');
        if (combinationRows.length === 0) {
            const genBtn = document.querySelector('.gen-row button');
            if (genBtn) {
                genBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            showValidationAlert('Please click Generate to create the variant combination matrix.');
            return false;
        }

        // Validate variant matrix row inputs
        for (const row of combinationRows) {
            const vPrice = row.querySelector('input[name$="[price]"]');
            const vSku = row.querySelector('input[name$="[sku]"]');
            const vQty = row.querySelector('input[name$="[qty]"]');

            if (vPrice) {
                requiredChecks.push({
                    element: vPrice,
                    isValid: (el) => el && el.value.trim() !== '' && !isNaN(el.value) && parseFloat(el.value) >= 0,
                    message: 'Please enter a valid variant price (≥ 0.00).'
                });
            }
            if (vSku) {
                requiredChecks.push({
                    element: vSku,
                    isValid: (el) => el && el.value.trim() !== '',
                    message: 'Please enter a variant SKU.'
                });
            }
            if (vQty) {
                requiredChecks.push({
                    element: vQty,
                    isValid: (el) => el && el.value.trim() !== '' && !isNaN(el.value) && parseInt(el.value, 10) >= 0,
                    message: 'Please enter a valid variant quantity (≥ 0).'
                });
            }
        }

        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        for (const check of requiredChecks) {
            if (!check.isValid(check.element)) {
                if (check.element) {
                    check.element.classList.add('is-invalid');
                    check.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof check.element.focus === 'function' && !check.element.disabled) {
                        check.element.focus();
                    }
                    if (typeof check.element.reportValidity === 'function') {
                        check.element.setCustomValidity(check.message);
                        check.element.reportValidity();
                    }
                }
                showValidationAlert(check.message || 'Please fill all required fields before saving the product.');
                return false;
            }
        }

        return true;
    }

    function showValidationAlert(message) {
        let alertBox = document.getElementById('formValidationAlert');
        if (!alertBox) {
            alertBox = document.createElement('div');
            alertBox.id = 'formValidationAlert';
            alertBox.className = 'alert alert-danger py-2 px-3 mb-3';
            const cardShell = document.querySelector('.card-shell');
            if (cardShell) {
                cardShell.insertBefore(alertBox, cardShell.firstChild);
            }
        }
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideValidationAlert() {
        const alertBox = document.getElementById('formValidationAlert');
        if (alertBox) {
            alertBox.classList.add('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('productForm');
        if (!form) return;

        if (subCategoryInput && subCategoryInput.value) {
            subCategorySearch.disabled = false;
        }

        // Live validation listeners on the form
        form.addEventListener('input', updateSubmitButtonState);
        form.addEventListener('change', updateSubmitButtonState);
        form.addEventListener('blur', updateSubmitButtonState, true);

        // Listen for changes in variant types container to invalidate outdated combination matrix
        const variantTypesContainer = document.getElementById('variantTypesContainer');
        if (variantTypesContainer) {
            variantTypesContainer.addEventListener('input', function(e) {
                if (e.target.classList.contains('variant-type-name') || e.target.classList.contains('variant-type-values')) {
                    const matrixRows = document.querySelectorAll('#combinationMatrix tbody tr');
                    if (matrixRows.length > 0) {
                        invalidateMatrix();
                    }
                }
            });
        }

        // Prevent accidental form submission when pressing Enter (allow in textareas)
        form.addEventListener('keydown', function(event) {
            if (event.key !== 'Enter') {
                return;
            }

            const target = event.target;
            if (
                target.tagName === 'TEXTAREA' ||
                target.closest('[contenteditable="true"]')
            ) {
                return;
            }

            event.preventDefault();
            updateSubmitButtonState();
        });

        // Submit protection handler
        form.addEventListener('submit', function(e) {
            const categoryElement = document.getElementById('category');
            if (categoryElement && categoryElement.value && (!subCategoryInput || !subCategoryInput.value)) {
                subCategorySearch.setCustomValidity('Please select a sub category from the dropdown.');
                subCategorySearch.reportValidity();
                e.preventDefault();
                updateSubmitButtonState();
                highlightAndFocusFirstInvalidField();
                return false;
            } else if (subCategorySearch) {
                subCategorySearch.setCustomValidity('');
            }

            if (!validateCreateProductForm()) {
                e.preventDefault();
                updateSubmitButtonState();
                highlightAndFocusFirstInvalidField();
                return false;
            }

            if (typeof showLoader === "function") {
                showLoader('Creating product...');
            }
        });

        // Initialize submit button state on page load
        updateSubmitButtonState();
    });
</script>
@endpush