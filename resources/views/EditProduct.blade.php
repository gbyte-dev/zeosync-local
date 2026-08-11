@extends('layouts.app')
@section('content')
<style>
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
                        <label for="sub_category" class="form-label">Sub Category</label>

                        <input
                            type="hidden"
                            id="selected_sub_category"
                            value="{{ old('sub_category', $dbProduct['sub_category_id'] ?? '') }}">

                        <select
                            id="sub_category"
                            name="sub_category"
                            class="form-control form-select"
                            required>
                            <option value="">Select Sub Category</option>

                            @php
                            $selectedsubCategory = old('sub_category', $dbProduct['sub_category_id'] ?? '');
                            @endphp

                            @if(isset($dbProduct['category_id']))
                            @foreach(getCategorires($dbProduct['category_id']) as $collections)
                            <option value="{{ $collections['id'] }}"
                                {{ $selectedsubCategory == $collections['id'] ? 'selected' : '' }}>
                                {{ $collections['name'] }}
                            </option>
                            @endforeach
                            @endif
                        </select>
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
                    <label class="form-label">Product Images (Multiple)</label>
                    <input type="file" name="images[]" class="form-control" style="padding-top:4px;" multiple accept="image/*" id="imageUpload">
                    <div class="file-preview" id="imagePreview">
                        @if(isset($product['images']) && count($product['images']) > 0)
                        @foreach($product['images'] as $index => $image)
                        <div class="image-container">
                            <img src="{{ $image['src'] }}" alt="Product Image"
                                data-image-id="{{ $image['id'] ?? $image['src'] }}">
                            <button type="button" class="delete-image-btn"
                                data-image-id="{{ $image['id'] ?? $image['src'] }}"
                                data-image-src="{{ $image['src'] }}">×</button>
                            <input type="hidden" name="existing_images[]" value="{{ $image['id'] ?? $image['src'] }}">
                        </div>
                        @endforeach
                        @endif
                    </div>
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
@endsection

@push('scripts')
<script>
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

    // Image preview for new uploads
    document.getElementById('imageUpload').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        const files = e.target.files;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(event) {
                const container = document.createElement('div');
                container.className = 'image-container';
                const img = document.createElement('img');
                img.src = event.target.result;
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'delete-image-btn';
                deleteBtn.textContent = '×';
                deleteBtn.onclick = function() {
                    container.remove();
                };
                container.appendChild(img);
                container.appendChild(deleteBtn);
                preview.appendChild(container);
            };
            reader.readAsDataURL(file);
        }
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
            let existingImageHtml = '';
            if (existingVariant && existingVariant.image_id && imageIdToSrc[existingVariant.image_id]) {
                const src = imageIdToSrc[existingVariant.image_id];
                existingImageHtml = `
                    <div class="mt-2">
                        <small class="text-muted" style="display:block;">Existing image:</small>
                        <img src="${src}" alt="Variant image" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                        <input type="hidden" name="existing_variant_image[${idx}]" value="${existingVariant.image_id}">
                    </div>
                `;
            }
            cells += `
                <input type="hidden" name="variant_ids[]" value="${existingVariant ? existingVariant.id : ''}">
                <input type="hidden" name="inventory_item_id[]" value="${existingVariant ? existingVariant.inventory_item_id : ''}">
                <td>
                    <input type="file" name="variant_image[${idx}]" class="form-control form-control-sm" style="padding-top:2px;" accept="image/*">
                    ${existingImageHtml}
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
        const category = document.querySelector('[name="category"]').value;
        if (category) {
            updatecategory(category);
        }
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

    function updatecategory(category) {
        if (!category) {
            document.getElementById('sub_category').innerHTML = '<option value="">Select Sub Category</option>';
            return;
        }
        fetch(`{{route('shopify.product.category')}}?parent_id=${category}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                let subSelect = document.getElementById('sub_category');
                subSelect.innerHTML = '<option value="">Select Sub Category</option>';
                const selectedSubCategory = document.getElementById('selected_sub_category').value;
                data.forEach(item => {
                    let option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    if (item.id == selectedSubCategory) {
                        option.selected = true;
                    }
                    subSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching subcategories:', error));
    }
</script>
@endpush