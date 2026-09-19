@extends('layouts.app')
@section('content')
<style nonce="{{ $cspNonce }}">
    /* 
     * Shopify Admin Inspired UI - Ultra Tight & Compact
     */
    .sp-page {
        background-color: #F6F6F7;
        padding: 16px 20px;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
    }

    /* ── Header ── */
    .sp-header {
        margin-bottom: 16px;
    }

    .sp-title {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 4px 0;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }

    .sp-subtitle {
        font-size: 13px;
        color: #6B7280;
        margin: 0;
    }

    /* ── Alerts ── */
    .sp-page .alert-danger {
        background-color: #FEF2F2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 13px;
        margin: 0 0 16px 0;
        max-width: 100%;
        box-shadow: none;
    }

    .sp-page .alert-danger ul {
        margin: 0;
        padding-left: 16px;
    }

    .sp-page .alert-danger li {
        margin-bottom: 2px;
    }

    /* ── Layout & Grid (Tight overrides) ── */
    .sp-page .card-shell {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sp-page .row {
        margin-left: -6px;
        margin-right: -6px;
    }

    .sp-page .col-md-4,
    .sp-page .col-md-6,
    .sp-page .col-md-5,
    .sp-page .col-md-2,
    .sp-page .col-md-8,
    .sp-page .col-12 {
        padding-left: 6px;
        padding-right: 6px;
    }

    .sp-page .mb-3 {
        margin-bottom: 12px !important;
    }

    .sp-page .mb-2 {
        margin-bottom: 8px !important;
    }

    .sp-page .mt-3 {
        margin-top: 12px !important;
    }

    /* ── Panels / Cards ── */
    .sp-page .panel {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02) !important;
        transition: none;
    }

    .sp-page .section-head {
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #E5E7EB;
        display: block;
    }

    .sp-page .section-title {
        font-size: 14px !important;
        font-weight: 600 !important;
        color: #111827 !important;
        margin: 0;
        font-family: inherit !important;
    }

    .sp-page .section-desc {
        font-size: 12px;
        color: #6B7280;
        margin: 2px 0 0 0;
    }

    /* ── Forms ── */
    .sp-page .form-label {
        font-size: 13px;
        font-weight: 500;
        color: #111827;
        margin-bottom: 4px;
        display: block;
        text-transform: none;
    }

    .sp-page .form-control,
    .sp-page .form-select {
        width: 100%;
        height: 32px;
        padding: 4px 8px;
        font-size: 13px;
        border: 1px solid #D1D5DB;
        border-radius: 6px;
        background: #FFFFFF;
        color: #111827;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.01);
    }

    .sp-page textarea.form-control {
        min-height: 80px;
        height: auto;
        padding: 8px;
    }

    .sp-page .form-control:focus,
    .sp-page .form-select:focus {
        border-color: #2563EB;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        outline: none;
    }

    .sp-page .form-control::placeholder {
        color: #9CA3AF;
    }

    .sp-page .form-control[readonly] {
        background: #F9FAFB;
        color: #6B7280;
        cursor: not-allowed;
    }

    /* Small inputs used by JS */
    .sp-page .form-control-sm {
        height: 28px !important;
        padding: 2px 8px !important;
        font-size: 12px !important;
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
        box-sizing: border-box;
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

    /* ── Buttons ── */
    .sp-page .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 12px;
        font-size: 13px;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        white-space: nowrap;
    }

    /* "Add Variant/Metafield" style */
    .sp-page .btn-outline-dark {
        background: #FFFFFF;
        color: #111827;
        border: 1px solid #D1D5DB;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-page .btn-outline-dark:hover {
        background: #F9FAFB;
        border-color: #9CA3AF;
    }

    /* "Remove" style */
    .sp-page .btn-outline-danger {
        background: #FFFFFF;
        color: #DC2626;
        border: 1px solid #FECACA;
        width: 100%;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-page .btn-outline-danger:hover {
        background: #FEF2F2;
        border-color: #FCA5A5;
    }

    /* Generate Combinations */
    .sp-page .btn-primary {
        background: #111827;
        color: #FFFFFF;
        border: 1px solid #111827;
    }

    .sp-page .btn-primary:hover {
        background: #374151;
        border-color: #374151;
    }

    /* Footer Action Buttons mapping (Back = btn-primary in HTML, Update = btn-success in HTML) */
    .sp-page .float-end {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        align-items: center;
        border-top: 1px solid #E5E7EB;
        padding-top: 16px;
        margin-top: 16px !important;
    }

    .sp-page .float-end .btn-primary {
        background: #FFFFFF;
        color: #111827 !important;
        border: 1px solid #D1D5DB;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-page .float-end .btn-primary:hover {
        background: #F9FAFB;
        border-color: #9CA3AF;
    }

    .sp-page .float-end .btn-success {
        background: #16A34A;
        color: #FFFFFF;
        border: 1px solid #16A34A;
    }

    .sp-page .float-end .btn-success:hover {
        background: #15803d;
        border-color: #15803d;
    }

    /* ── JS Generated Components ── */
    .sp-page .meta-field-row {
        background: #FAFAFA;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 8px;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .sp-page .variant-type-box {
        background: #FAFAFA;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 8px;
        position: relative;
    }

    .sp-page .variant-type-box:hover {
        border-color: #D1D5DB;
    }

    .sp-page .remove-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        cursor: pointer;
        color: #6B7280;
        background: #F3F4F6;
        font-size: 12px;
        line-height: 1;
        padding: 4px 6px;
        border-radius: 4px;
        transition: 0.15s;
    }

    .sp-page .remove-btn:hover {
        background: #E5E7EB;
        color: #111827;
    }

    /* ── Matrix Table ── */
    .sp-page .matrix-table {
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        overflow-x: auto;
    }

    .sp-page .matrix-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .sp-page .matrix-table th {
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 500;
        color: #6B7280;
        border-bottom: 1px solid #E5E7EB;
        background: #F9FAFB;
        text-align: left;
        white-space: nowrap;
    }

    .sp-page .matrix-table td {
        padding: 6px 12px;
        font-size: 13px;
        color: #111827;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: middle;
    }

    .sp-page .matrix-table tbody tr:last-child td {
        border-bottom: none;
    }

    .sp-page .matrix-table tbody tr:hover td {
        background-color: #F9FAFB;
    }

    .sp-page #combinationMatrix[style] {
        padding: 0 !important;
        /* Override JS padding */
    }

    .sp-page #combinationMatrix p {
        padding: 12px 16px;
        margin: 0;
        font-size: 13px;
        color: #6B7280;
    }

    /* ── Images ── */
    .sp-page .file-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }

    .sp-page .image-container {
        position: relative;
        display: inline-block;
    }

    .sp-page .image-container img {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
    }

    .sp-page .delete-image-btn {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #FFFFFF;
        color: #6B7280;
        border: 1px solid #D1D5DB;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: 0.15s;
    }

    .sp-page .delete-image-btn:hover {
        color: #DC2626;
        border-color: #FCA5A5;
        background: #FEF2F2;
    }

    /* ── Misc ── */
    .sp-page .gen-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .sp-page .panel-divider {
        height: 1px;
        background: #E5E7EB;
        margin: 16px 0;
    }

    .sp-page .text-muted {
        color: #6B7280 !important;
        font-size: 12px;
        margin: 0;
    }

    .dashboard-header-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 20px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    /* ── Image Library Modal & Selection Styles ── */
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

    .sp-page .image-container .library-badge {
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

    .sp-page .preview-device-item {
        position: relative;
        display: inline-block;
        width: 64px;
        height: 64px;
    }

    .sp-page .preview-device-item img {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
    }

    .sp-page .preview-device-item .remove-device-btn {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #DC2626;
        color: #fff;
        border-radius: 50%;
        border: 2px solid #fff;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        line-height: 1;
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
</style>

@php
$currentShop = $activeShop ?? request('shop');
$shopQuery = $currentShop ? '?shop=' . urlencode($currentShop) : '';
@endphp

<div class="sp-page container-fluid">
    <div class="sp-header dashboard-header-card">
        <h1 class="sp-title" style="font-size:medium">Edit Product</h1>
        <p class="sp-subtitle">Update the details below to modify your product</p>
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

    <form method="POST" action="{{ route('shopify.product.update.post', ['id' => $product['id']]) }}{{ $shopQuery }}"
        enctype="multipart/form-data" id="productForm">
        @csrf
        @method('PUT')
        @if ($currentShop)
        <input type="hidden" name="shop" value="{{ $currentShop }}">
        @endif

        <input type="hidden" name="amazon_title" id="amazonTitle" value="">

        <div class="card-shell">
            <div class="panel">
                <div class="section-head">
                    <p class="section-title">Core Details</p>
                    <p class="section-desc">Basic product information</p>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Product Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Enter product title"
                            value="{{ old('title', $product['title'] ?? '') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="category" class="form-label">Product Category</label>

                        <select
                            id="category"
                            name="category"
                            class="form-control form-select"
                            onchange="updatecategory(this.value)"
                            required>
                            <option value="">Select Category</option>

                            @php
                            $selectedCategory = old('category', $dbProduct['category_id'] ?? '');
                            @endphp

                            @foreach(getCategorires() as $categories)
                            <option value="{{ $categories['id'] }}"
                                {{ $selectedCategory == $categories['id'] ? 'selected' : '' }}>
                                {{ $categories['name'] }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="sub_category_search" class="form-label">
                            Sub Category
                        </label>

                        @php
                        $selectedsubCategory = old(
                        'sub_category',
                        $dbProduct['sub_category_id'] ?? ''
                        );

                        $selectedsubCategoryName = '';

                        if (isset($dbProduct['category_id'])) {
                        foreach (getCategorires($dbProduct['category_id']) as $collection) {
                        if ($selectedsubCategory == $collection['id']) {
                        $selectedsubCategoryName = $collection['name'];
                        break;
                        }
                        }
                        }
                        @endphp

                        <div class="position-relative">

                            <input
                                type="text"
                                id="sub_category_search"
                                class="form-control"
                                placeholder="Search sub category..."
                                autocomplete="off"
                                value="{{ $selectedsubCategoryName }}">

                            <input
                                type="hidden"
                                name="sub_category"
                                id="sub_category"
                                value="{{ $selectedsubCategory }}">

                            <div
                                id="sub_category_results"
                                class="subcategory-dropdown">
                            </div>

                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Product description"
                        required>{{ old('description', isset($product['body']) ? $product['body'] : (isset($product['body_html']) ? strip_tags(html_entity_decode($product['body_html'])) : '')) }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select" required>
                            <option value="active" {{ (old('status', $product['status'] ?? '' )=='active' ) ? 'selected' : '' }}>Active</option>
                            <option value="draft" {{ (old('status', $product['status'] ?? '' )=='draft' ) ? 'selected' : '' }}>Draft</option>
                            <option value="archived" {{ (old('status', $product['status'] ?? '' )=='archived' ) ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Base Price (₹)</label>
                        <input type="number" step="0.01" name="base_price" class="form-control" placeholder="0.00"
                            value="{{ old('base_price', $product['variants'][0]['price'] ?? '') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="Enter SKU"
                            value="{{ old('sku', $product['variants'][0]['sku'] ?? '') }}">
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                        <label class="form-label mb-0">Product Images (Multiple)</label>
                        <button type="button" class="btn btn-outline-dark btn-sm" id="openImageLibraryBtn" style="height: 28px; font-size: 12px; padding: 0 10px;">
                            <i class="bi bi-images me-1"></i> Select from Image Upload
                        </button>
                    </div>
                    <input type="file" name="images[]" class="form-control" style="padding-top:4px;" multiple accept="image/*" id="imageUpload">
                    <div class="file-preview" id="imagePreview">
                        @if(isset($product['images']) && count($product['images']) > 0)
                        @foreach($product['images'] as $index => $image)
                        <div class="image-container" data-origin="shopify">
                            <img src="{{ $image['src'] }}" alt="Product Image"
                                data-image-id="{{ $image['id'] ?? $image['src'] }}">
                            <button type="button" class="delete-image-btn"
                                data-image-id="{{ $image['id'] ?? $image['src'] }}"
                                data-image-src="{{ $image['src'] }}">×</button>
                            <input type="hidden" name="existing_images[]" value="{{ $image['src'] ?? $image['id'] }}">
                        </div>
                        @endforeach
                        @endif
                    </div>
                    <div id="newDevicePreviews" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div id="selectedLibraryImagesContainer" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div id="deletedImagesContainer"></div>
                </div>
            </div>

            <div class="panel">
                <div class="section-head">
                    <p class="section-title">Product Organization</p>
                    <p class="section-desc">Type, vendor, collections, and tags</p>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Product Type</label>
                        <input type="text" name="product_type" class="form-control"
                            placeholder="e.g., Clothing, Electronics"
                            value="{{ old('product_type', $product['product_type'] ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vendor</label>
                        <input type="text" name="vendor" class="form-control" placeholder="e.g., Nike, Apple"
                            value="{{ old('vendor', $product['vendor'] ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Collections</label>
                        <input type="text" name="collections" class="form-control"
                            placeholder="Comma separated collections"
                            value="{{ old('collections', $product['metafields']['collections'] ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="Comma separated tags"
                            value="{{ old('tags', $product['tags'] ?? '') }}">
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="section-head">
                    <p class="section-title">Custom Metafields</p>
                    <p class="section-desc">Add name-value pairs for extended product data</p>
                </div>
                <div id="metaFieldsContainer">
                </div>
                <button type="button" onclick="addMetaField()" class="btn btn-outline-dark mt-2">
                    + Add Metafield
                </button>
            </div>

            <div class="panel">
                <div class="section-head">
                    <p class="section-title">Variant Types</p>
                    <p class="section-desc">Define Color, Size etc. — values comma-separated</p>
                </div>
                <div id="variantTypesContainer">
                </div>
                <button type="button" onclick="addVariantType()" class="btn btn-outline-dark mt-2 mb-3">
                    + Add Variant Type
                </button>
                <div class="panel-divider"></div>
                <div class="gen-row">
                    <div>
                        <p class="section-title"
                            style="font-family:'DM Sans',sans-serif; font-size:0.95rem; font-weight:600; margin:0;">
                            Variant Combinations</p>
                        <p class="text-muted">Click Generate to build the combination matrix</p>
                    </div>
                    <button type="button" onclick="generateCombinations()" class="btn btn-primary">
                        ⚡ Generate Combinations
                    </button>
                </div>
                <div class="matrix-table">
                    <div id="combinationMatrix" style="padding: 14px 16px;">
                        <p class="text-muted" style="margin:0;">No combinations yet — click Generate above.</p>
                    </div>
                </div>
            </div>

            <div class="float-end">
                <a href="{{ route('shopify.products') }}{{ $shopQuery }}" class="btn btn-primary"> Back </a>
                <button type="submit" class="btn btn-success" id="updateProductBtn">
                    Update Product
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Image Library Selection Modal -->
<div class="modal fade" id="imageLibraryModal" tabindex="-1" aria-labelledby="imageLibraryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 12px; border: 1px solid #E5E7EB;">
            <div class="modal-header py-3 px-4" style="border-bottom: 1px solid #F3F4F6;">
                <div>
                    <h5 class="modal-title fw-semibold text-dark mb-0" id="imageLibraryModalLabel" style="font-size: 15px;">
                        Image Library
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
    const updateProductBtn = document.getElementById('updateProductBtn');
    const productTitleInput = document.querySelector('input[name="title"]');
    const amazonTitleInput = document.getElementById('amazonTitle');

    // Product data passed from controller
    const productData = @json($product);

    productForm.addEventListener('submit', function() {
        if (updateProductBtn.disabled) {
            return;
        }

        updateProductBtn.disabled = true;
        updateProductBtn.innerHTML = 'Updating...';
    });

    // Sync Amazon title with product title silently
    if (productTitleInput && amazonTitleInput) {
        const syncAmazonTitle = () => {
            amazonTitleInput.value = productTitleInput.value;
        };
        syncAmazonTitle();
        productTitleInput.addEventListener('input', syncAmazonTitle);
    }

    // --- Image Library & Device Upload Management ---
    const imageUpload = document.getElementById('imageUpload');
    const newDevicePreviews = document.getElementById('newDevicePreviews');
    const selectedLibraryImagesContainer = document.getElementById('selectedLibraryImagesContainer');
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
    const variantImageMap = {}; // idx => { url, name, imageId, key }
    const savedComboImageMap = {}; // comboKey => { url, name, imageId }

    // Upload from Device inside modal
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
            modalUploadIcon.classList.add('d-none');
            modalUploadBtnSpinner.classList.remove('d-none');
            modalUploadBtnText.textContent = 'Uploading...';
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
                    ${current.imageId ? `<input type="hidden" name="existing_variant_image[${idx}]" id="variant_existing_img_input_${idx}" value="${current.imageId}">` : ''}
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

    function setVariantImage(idx, url, name, imageId = null) {
        const comboKey = getVariantComboKey(idx);
        variantImageMap[idx] = { url, name: name || '', imageId: imageId, key: comboKey };
        if (comboKey) {
            savedComboImageMap[comboKey] = { url, name: name || '', imageId: imageId };
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
        const subtitle = document.getElementById('imageLibraryModalSubtitle');
        if (subtitle) subtitle.textContent = 'Choose an image from your library or upload from device';
        confirmLibrarySelectionBtn.textContent = 'Select Image';
        if (modalAlertMessage) modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';

        modalSelectedMap.clear();
        if (variantImageMap[idx]) {
            const curr = variantImageMap[idx];
            modalSelectedMap.set(curr.url, { url: curr.url, name: curr.name, id: curr.imageId });
        }
        updateModalCounter();
        libraryModal.show();

        if (allLibraryImages.length === 0) {
            fetchLibraryImages();
        } else {
            renderLibraryTabPage();
        }
    }

    // Render newly selected library images into Edit Product view
    function renderSelectedLibraryImages() {
        selectedLibraryImagesContainer.innerHTML = '';
        selectedLibraryImages.forEach((libImg, index) => {
            const container = document.createElement('div');
            container.className = 'image-container';

            const img = document.createElement('img');
            img.src = libImg.url;
            img.alt = libImg.name || 'Library Image';

            const badge = document.createElement('span');
            badge.className = 'library-badge';
            badge.textContent = 'Lib';

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'delete-image-btn';
            delBtn.innerHTML = '×';
            delBtn.title = 'Remove library image';
            delBtn.addEventListener('click', () => {
                removeLibraryImage(index);
            });

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'existing_images[]';
            hiddenInput.value = libImg.url;

            container.appendChild(img);
            container.appendChild(badge);
            container.appendChild(delBtn);
            container.appendChild(hiddenInput);
            selectedLibraryImagesContainer.appendChild(container);
        });
    }

    function removeLibraryImage(indexToRemove) {
        selectedLibraryImages.splice(indexToRemove, 1);
        renderSelectedLibraryImages();
    }

    // Render device upload previews
    function renderDevicePreviews() {
        newDevicePreviews.innerHTML = '';
        if (imageUpload.files && imageUpload.files.length > 0) {
            Array.from(imageUpload.files).forEach((file, index) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'preview-device-item';

                const img = document.createElement('img');
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = ev => { img.src = ev.target.result; };
                reader.readAsDataURL(file);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-device-btn';
                removeBtn.innerHTML = '×';
                removeBtn.title = 'Remove file';
                removeBtn.addEventListener('click', () => {
                    removeDeviceFile(index);
                });

                itemDiv.appendChild(img);
                itemDiv.appendChild(removeBtn);
                newDevicePreviews.appendChild(itemDiv);
            });
        }
    }

    function removeDeviceFile(indexToRemove) {
        if (!imageUpload.files) return;
        const dt = new DataTransfer();
        Array.from(imageUpload.files).forEach((file, idx) => {
            if (idx !== indexToRemove) {
                dt.items.add(file);
            }
        });
        imageUpload.files = dt.files;
        renderDevicePreviews();
    }

    imageUpload.addEventListener('change', function() {
        renderDevicePreviews();
    });

    // Open Library Modal
    openImageLibraryBtn.addEventListener('click', function() {
        currentModalTarget = 'gallery';
        document.getElementById('imageLibraryModalLabel').textContent = 'Image Library';
        const subtitle = document.getElementById('imageLibraryModalSubtitle');
        if (subtitle) subtitle.textContent = 'Choose existing images previously uploaded to your library';
        confirmLibrarySelectionBtn.textContent = 'Add Selected Images';
        if (modalAlertMessage) modalAlertMessage.className = 'alert d-none py-2 px-3 small mb-3';

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

    // Render Modal Images for current tab page (10 max)
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
            renderSelectedLibraryImages();
        } else if (currentModalTarget && currentModalTarget.type === 'variant') {
            const idx = currentModalTarget.index;
            if (modalSelectedMap.size > 0) {
                const selectedImg = Array.from(modalSelectedMap.values())[0];
                setVariantImage(idx, selectedImg.url, selectedImg.name, selectedImg.id);
            }
        }
        libraryModal.hide();
    });

    // Function to delete existing image
    function deleteExistingImage(button) {
        const container = button.closest('.image-container');
        const imageId = button.getAttribute('data-image-id');
        const imageSrc = button.getAttribute('data-image-src');
        const deletedContainer = document.getElementById('deletedImagesContainer');
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'deleted_images[]';
        hiddenInput.value = imageId || imageSrc;
        deletedContainer.appendChild(hiddenInput);
        container.remove();
    }

    // Attach delete event listeners to existing image buttons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.delete-image-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteExistingImage(this);
            });
        });
    });

    function initVariantTypes() {
        const container = document.getElementById('variantTypesContainer');
        container.innerHTML = '';
        if (productData.options && productData.options.length > 0) {
            productData.options.forEach(option => {
                const div = document.createElement('div');
                div.classList.add('variant-type-box');
                div.innerHTML = `
                    <span class="remove-btn" onclick="this.parentElement.remove()">✖</span>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Type Name</label>
                            <input type="text" class="form-control variant-type-name" name="variant_names[]" placeholder="e.g., Color" value="${option.name}">
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="form-label">Possible Values</label>
                            <input type="text" class="form-control variant-type-values" name="variant_values[]" placeholder="e.g., Red, Blue, Green" value="${option.values ? option.values.join(', ') : ''}">
                        </div>
                    </div>
                `;
                container.appendChild(div);
            });
        } else {
            // Default two variant types
            container.innerHTML = `
                <div class="variant-type-box">
                    <span class="remove-btn" onclick="this.parentElement.remove()">✖</span>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Type Name</label>
                            <input type="text" class="form-control variant-type-name" name="variant_names[]" placeholder="e.g., Color" value="Color">
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="form-label">Possible Values</label>
                            <input type="text" class="form-control variant-type-values" name="variant_values[]" placeholder="e.g., Red, Blue, Green" value="Red, Blue, Green">
                        </div>
                    </div>
                </div>
                <div class="variant-type-box">
                    <span class="remove-btn" onclick="this.parentElement.remove()">✖</span>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Type Name</label>
                            <input type="text" class="form-control variant-type-name" name="variant_names[]" placeholder="e.g., Size" value="Size">
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="form-label">Possible Values</label>
                            <input type="text" class="form-control variant-type-values" name="variant_values[]" placeholder="e.g., S, M, L" value="S, M, L">
                        </div>
                    </div>
                </div>
            `;
        }
    }

    // Initialize metafields from product data
    function initMetafields() {
        const container = document.getElementById('metaFieldsContainer');
        container.innerHTML = '';
        // Check if product has metafields
        let metafields = [];
        if (productData.metafields && typeof productData.metafields === 'object') {
            for (const [key, value] of Object.entries(productData.metafields)) {
                if (key !== 'collections') {
                    metafields.push({
                        name: key,
                        value: value
                    });
                }
            }
        }
        if (metafields.length > 0) {
            metafields.forEach(meta => {
                const div = document.createElement('div');
                div.classList.add('meta-field-row', 'row', 'mb-3');
                div.innerHTML = `
                    <div class="col-md-5">
                        <label class="form-label">Metafield Name</label>
                        <input type="text" name="meta_name[]" class="form-control" placeholder="e.g., material, warranty" value="${escapeHtml(meta.name)}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Metafield Value</label>
                        <input type="text" name="meta_value[]" class="form-control" placeholder="e.g., Cotton, 2 years" value="${escapeHtml(meta.value)}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger" onclick="removeMetaField(this)">Remove</button>
                    </div>
                `;
                container.appendChild(div);
            });
        } else {
            // Default empty metafield row
            const div = document.createElement('div');
            div.classList.add('meta-field-row', 'row', 'mb-3');
            div.innerHTML = `
                <div class="col-md-5">
                    <label class="form-label">Metafield Name</label>
                    <input type="text" name="meta_name[]" class="form-control" placeholder="e.g., material, warranty">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Metafield Value</label>
                    <input type="text" name="meta_value[]" class="form-control" placeholder="e.g., Cotton, 2 years">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger" onclick="removeMetaField(this)">Remove</button>
                </div>
            `;
            container.appendChild(div);
        }
    }

    // Helper function to escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Add variant type
    function addVariantType() {
        const container = document.getElementById('variantTypesContainer');
        const div = document.createElement('div');
        div.classList.add('variant-type-box');
        div.innerHTML = `
            <span class="remove-btn" onclick="this.parentElement.remove()">✖</span>
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
    }

    // Add metafield row
    function addMetaField() {
        const container = document.getElementById('metaFieldsContainer');
        const div = document.createElement('div');
        div.classList.add('meta-field-row', 'row', 'mb-3');
        div.innerHTML = `
            <div class="col-md-5">
                <label class="form-label">Metafield Name</label>
                <input type="text" name="meta_name[]" class="form-control" placeholder="e.g., material, warranty">
            </div>
            <div class="col-md-5">
                <label class="form-label">Metafield Value</label>
                <input type="text" name="meta_value[]" class="form-control" placeholder="e.g., Cotton, 2 years">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger" onclick="removeMetaField(this)">Remove</button>
            </div>`;
        container.appendChild(div);
    }

    // Remove metafield row
    function removeMetaField(button) {
        button.closest('.meta-field-row').remove();
    }

    // Generate combinations
    function generateCombinations() {
        const variantTypes = [];
        document.querySelectorAll('.variant-type-box').forEach(box => {
            const name = box.querySelector('.variant-type-name').value.trim();
            const values = box.querySelector('.variant-type-values').value
                .split(',').map(v => v.trim()).filter(v => v);
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
        if (combinations.length === 0) {
            matrixDiv.innerHTML = '<p style="color:#c0392b;margin:14px 16px;">No combinations generated.</p>';
            return;
        }

        // Create a map of existing variants for easy lookup
        const existingVariantsMap = {};
        if (productData.variants && productData.variants.length > 0) {
            productData.variants.forEach(variant => {
                const key = [];
                if (variant.option1) key.push(variant.option1.trim());
                if (variant.option2) key.push(variant.option2.trim());
                if (variant.option3) key.push(variant.option3.trim());
                existingVariantsMap[key.join('|')] = variant;
            });
        }

        // Create a map of image_id to image src
        const imageIdToSrc = {};
        if (productData.images && productData.images.length > 0) {
            productData.images.forEach(image => {
                if (image.id) {
                    imageIdToSrc[image.id] = image.src;
                }
            });
        }

        const table = document.createElement('table');
        let thead = '<thead><tr>';
        variantTypes.forEach(t => thead += `<th>${t.name}</th>`);
        thead += '<th>Image</th><th>Price (₹)</th><th>SKU</th><th>Qty</th></tr></thead>';
        table.innerHTML = thead;
        const tbody = document.createElement('tbody');
        combinations.forEach((combo, idx) => {
            const row = document.createElement('tr');
            let cells = '';
            const comboValues = [];
            combo.forEach(c => {
                cells += `<td>${c.value}</td>`;
                comboValues.push(c.value.trim());
            });
            // Find matching existing variant
            const comboKey = comboValues.join('|');
            const existingVariant = existingVariantsMap[comboKey];
            let existingPrice = existingVariant ? existingVariant.price : '';
            let existingQuantity = existingVariant ?
                (existingVariant.inventory_quantity !== undefined ?
                    existingVariant.inventory_quantity :
                    '') :
                '';
            let existingSku = existingVariant ? (existingVariant.sku || '') : '';

            // Handle variant image state
            if (!variantImageMap[idx]) {
                let resolvedUrl = null;
                let resolvedImageId = null;
                let resolvedMediaId = null;

                if (existingVariant) {
                    if (existingVariant.image && existingVariant.image.src) {
                        resolvedUrl = existingVariant.image.src;
                        resolvedImageId = existingVariant.image.id || existingVariant.image_id || null;
                    } else if (existingVariant.image && existingVariant.image.url) {
                        resolvedUrl = existingVariant.image.url;
                        resolvedImageId = existingVariant.image.id || existingVariant.image_id || null;
                    } else if (existingVariant.image_src) {
                        resolvedUrl = existingVariant.image_src;
                        resolvedImageId = existingVariant.image_id || null;
                    } else if (existingVariant.image_id && imageIdToSrc[existingVariant.image_id]) {
                        resolvedUrl = imageIdToSrc[existingVariant.image_id];
                        resolvedImageId = existingVariant.image_id;
                    }
                    resolvedMediaId = existingVariant.media_id || (existingVariant.image && existingVariant.image.id) || existingVariant.image_id || null;
                }

                if (resolvedUrl) {
                    variantImageMap[idx] = {
                        url: resolvedUrl,
                        imageId: resolvedImageId || resolvedMediaId,
                        mediaId: resolvedMediaId,
                        name: 'Variant Image',
                        key: comboKey
                    };
                } else if (savedComboImageMap[comboKey]) {
                    variantImageMap[idx] = { ...savedComboImageMap[comboKey], key: comboKey };
                }
            } else {
                variantImageMap[idx].key = comboKey;
                savedComboImageMap[comboKey] = {
                    url: variantImageMap[idx].url,
                    imageId: variantImageMap[idx].imageId,
                    mediaId: variantImageMap[idx].mediaId,
                    name: variantImageMap[idx].name
                };
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
                    ${current.imageId ? `<input type="hidden" name="existing_variant_image[${idx}]" id="variant_existing_img_input_${idx}" value="${current.imageId}">` : ''}
                    <input type="hidden" name="variants[${idx}][image]" id="variant_image_input_${idx}" value="${current.url}">
                </div>`
                : `<div class="variant-img-cell" id="variant_img_cell_${idx}">
                    <button type="button" class="btn btn-outline-dark btn-sm select-variant-img-btn" onclick="openVariantImageModal(${idx})" style="font-size: 11px; padding: 4px 8px; white-space: nowrap;">
                        <i class="bi bi-image me-1"></i> Select Image
                    </button>
                    <input type="hidden" name="variants[${idx}][image]" id="variant_image_input_${idx}" value="">
                </div>`;

            cells += `
                <input type="hidden" name="variant_ids[]" value="${existingVariant ? existingVariant.id : ''}">
                <input type="hidden" name="inventory_item_id[]" value="${existingVariant ? existingVariant.inventory_item_id : ''}">
                <td id="variant_img_td_${idx}">
                    ${imgCellHtml}
                </td>
                <td>
                    <input type="number" step="0.01" name="variant_price[${idx}]" class="form-control form-control-sm" placeholder="0.00" value="${existingPrice}">
                </td>
                <td>
                    <input type="text" name="variant_sku[${idx}]" class="form-control form-control-sm" placeholder="SKU" value="${existingSku}">
                </td>
                <td>
                    <input type="number" name="variant_quantity[${idx}]" class="form-control form-control-sm" placeholder="0" min="0" value="${existingQuantity}">
                </td>
            `;
            row.innerHTML = cells;
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        matrixDiv.appendChild(table);

        // Hidden combo data
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
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initVariantTypes();
        initMetafields();
        if (
            productData.variants &&
            productData.variants.length > 0 &&
            productData.options &&
            productData.options.length > 0
        ) {
            setTimeout(() => {
                generateCombinations();
            }, 100);
        }
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
                console.error(
                    'Subcategory request failed:',
                    xhr.status
                );
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
                item.className =
                    'list-group-item list-group-item-action';

                item.textContent = category.name;

                item.addEventListener('click', function() {

                    subCategorySearch.value = category.name;
                    subCategoryInput.value = category.id;

                    subCategoryResults.innerHTML = '';
                    subCategoryResults.style.display = 'none';
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


    function updatecategory(category) {

        clearTimeout(subCategoryTimer);

        subCategorySearch.value = '';
        subCategoryInput.value = '';

        subCategoryResults.innerHTML = '';
        subCategoryResults.style.display = 'none';

        if (!category) {
            subCategorySearch.disabled = true;
            return;
        }

        subCategorySearch.disabled = false;
    }
</script>
@endpush