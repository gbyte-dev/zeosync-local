@extends('layouts.app')
@section('content')
<style>
    /* ── Page Header ── */
    .pg-subtitle {
        font-size: 0.82rem;
        color: #6b6b5f;
        margin: 0;
        font-weight: 400;
        letter-spacing: 0.02em;
    }

    /* ── Card shell ── */
    .panel {
        background: #ffffff;
        border-radius: 18px;
        padding: 30px 32px;
        border: 1px solid #e8e7e1;
        transition: box-shadow 0.2s ease;
    }

    .panel:hover {
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    }

    /* ── Section heading ── */
    .section-head {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-title {
        margin: 0;
        color: #1a1a18;
    }

    .section-desc {
        font-size: 0.75rem;
    }

    /* ── Labels & inputs ── */
    .form-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
    }

    .form-control {
        font-size: small;
    }

    .form-control[readonly] {
        background: #f0f0ea;
        color: #7a7a6a;
        cursor: not-allowed;
    }

    .form-control-sm {
        padding: 7px 10px;
        font-size: 0.82rem;
        border-radius: 8px;
    }

    /* ── Buttons ── */
    .btn-outline-dark {
        background: transparent;
        color: #1a1a18;
        border: 1.5px solid #ccc9c0;
    }

    .btn-outline-dark:hover {
        background: #1a1a18;
        color: #fff;
        border-color: #1a1a18;
    }

    .btn-outline-danger {
        background: transparent;
        color: #c0392b;
        border: 1.5px solid #f5c6c2;
        font-size: 0.78rem;
        padding: 8px 12px;
        width: 100%;
    }

    .btn-outline-danger:hover {
        background: #fde8e8;
    }

    /* ── Metafield rows ── */
    .meta-field-row {
        background: #f8f8f4;
        border: 1.5px solid #eae9e2;
        border-radius: 12px;
        padding: 14px 14px 6px;
        margin-bottom: 10px;
    }

    /* ── Variant type box ── */
    .variant-type-box {
        background: #f8f8f4;
        border: 1.5px solid #eae9e2;
        padding: 18px 18px 8px;
        border-radius: 14px;
        margin-bottom: 12px;
        position: relative;
        transition: border-color 0.18s;
    }

    .variant-type-box:hover {
        border-color: #c5c2b5;
    }

    .remove-btn {
        position: absolute;
        top: 10px;
        right: 14px;
        cursor: pointer;
        color: #c0392b;
        font-size: 16px;
        line-height: 1;
        padding: 2px 6px;
        border-radius: 6px;
        transition: background 0.15s;
    }

    .remove-btn:hover {
        background: #fde8e8;
    }

    /* ── Variant combo table ── */
    .matrix-table {
        overflow-x: auto;
        border-radius: 12px;
        border: 1.5px solid #e8e7e1;
    }

    .matrix-table table {
        width: 100%;
        min-width: 580px;
        border-collapse: collapse;
        font-size: 0.83rem;
    }

    .matrix-table thead tr {
        background: #f2f1eb;
    }

    .matrix-table th {
        padding: 11px 14px;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #6b6b5f;
        border-bottom: 1.5px solid #e8e7e1;
    }

    .matrix-table td {
        padding: 9px 12px;
        border-bottom: 1px solid #f0efe9;
        vertical-align: middle;
    }

    .matrix-table tbody tr:last-child td {
        border-bottom: none;
    }

    .matrix-table tbody tr:hover {
        background: #fafaf6;
    }

    .matrix-table td:first-child {
        font-weight: 600;
        color: #1a1a18;
    }

    /* ── Generate button row ── */
    .gen-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 10px;
    }

    /* ── Image preview ── */
    .file-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .file-preview img {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 10px;
        border: 2px solid #e2e1db;
    }

    /* ── Divider ── */
    .panel-divider {
        height: 1px;
        background: #eae9e2;
        margin: 20px 0;
    }

    /* ── Helpers ── */
    .text-muted {
        color: #8a8a7a;
        font-size: 0.75rem;
        margin: 0 0 6px;
    }

    .d-flex {
        display: flex;
    }

    .align-items-end {
        align-items: flex-end;
    }

    .w-100 {
        width: 100%;
    }

    .mt-4 {
        margin-top: 24px;
    }

    small.text-muted {
        display: block;
        margin-top: 5px;
        font-size: 0.71rem;
        color: #a0a090;
    }

    /* ============================================= */
    /* ONLY FORM CONTENT - BLACK & WHITE THEME */
    /* NO SIDEBAR / LAYOUT EFFECTS */
    /* ============================================= */
    /* Panels / Cards */
    .panel {
        background: #ffffff;
        border: 1px solid #dddddd;
        border-radius: 0px;
        box-shadow: none !important;
    }

    .panel:hover {
        box-shadow: none !important;
    }

    .section-title {
        color: #000000;
    }

    .section-desc {
        color: #666666;
    }

    /* Form Labels */
    .form-label {
        color: #000000;
        font-weight: 500;
    }

    /* Form Controls */
    .form-control,
    .form-select {
        border: 1px solid #cccccc;
        background: #ffffff;
        color: #000000;
        border-radius: 0px;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #000000;
        outline: none;
        box-shadow: none;
    }

    .form-control[readonly] {
        background: #f5f5f5;
        color: #555555;
    }

    /* Buttons */
    .btn-outline-dark {
        background: transparent;
        color: #000000;
        border: 1px solid #aaaaaa;
    }

    .btn-outline-dark:hover {
        background: #000000;
        color: #ffffff;
        border-color: #000000;
    }

    .btn-outline-danger {
        background: transparent;
        color: #000000;
        border: 1px solid #cccccc;
    }

    .btn-outline-danger:hover {
        background: #eeeeee;
        color: #000000;
    }

    .btn-primary {
        background: #000000;
        border: 1px solid #000000;
        color: #ffffff;
    }

    .btn-primary:hover {
        background: #333333;
        border-color: #333333;
    }

    .btn-success {
        background: #000000;
        border: 1px solid #000000;
        color: #ffffff;
    }

    .btn-success:hover {
        background: #333333;
    }

    /* Meta Field Rows */
    .meta-field-row {
        background: #fafafa;
        border: 1px solid #e5e5e5;
        border-radius: 0px;
    }

    /* Variant Type Boxes */
    .variant-type-box {
        background: #fafafa;
        border: 1px solid #e5e5e5;
        border-radius: 0px;
    }

    .variant-type-box:hover {
        border-color: #aaaaaa;
    }

    .remove-btn {
        color: #000000;
    }

    .remove-btn:hover {
        background: #eeeeee;
    }

    /* Matrix Table */
    .matrix-table {
        border: 1px solid #dddddd;
        border-radius: 0px;
    }

    .matrix-table thead tr {
        background: #f5f5f5;
    }

    .matrix-table th {
        color: #000000;
        border-bottom: 1px solid #dddddd;
    }

    .matrix-table td {
        border-bottom: 1px solid #eeeeee;
    }

    .matrix-table tbody tr:hover {
        background: #fafafa;
    }

    /* File Preview */
    .file-preview img {
        border: 1px solid #dddddd;
        border-radius: 0px;
    }

    .delete-image-btn {
        background: #000000;
        color: #ffffff;
        border-radius: 0px;
    }

    .delete-image-btn:hover {
        background: #333333;
    }

    /* Dividers */
    .panel-divider {
        background: #e5e5e5;
    }

    /* Text Muted */
    .text-muted,
    small.text-muted {
        color: #777777 !important;
    }

    /* Alert Box */
    .alert-danger {
        background: #f8f8f8;
        border: 1px solid #cccccc;
        color: #000000;
    }

    /* Page Header (inside form area) */
    .pg-title {
        color: #000000;
    }

    .pg-subtitle {
        color: #666666;
    }

    /* Back Button Link */
    .btn-primary a {
        color: #ffffff !important;
        text-decoration: none;
    }

    /* Remove all box shadows and gradients */
    .panel,
    .matrix-table,
    .variant-type-box,
    .meta-field-row {
        box-shadow: none !important;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {

        .col-md-4,
        .col-md-5,
        .col-md-6,
        .col-md-8,
        .col-md-2 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .panel {
            padding: 20px 18px;
        }

        .pg-header {
            margin-bottom: 22px;
        }
    }

    @media (max-width: 480px) {
        .pg-wrap {
            padding: 20px 10px 50px;
        }

        .panel {
            padding: 16px 14px;
        }

        .gen-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .gen-row button {
            width: 100%;
            justify-content: center;
        }
    }

    /* Additional styles for image containers */
    .image-container {
        position: relative;
        display: inline-block;
    }

    .delete-image-btn {
        position: absolute;
        top: 2px;
        right: 2px;
        padding: 2px 6px;
        font-size: 12px;
        border-radius: 20px;
        background: #c0392b;
        color: white;
        border: none;
        cursor: pointer;
    }

    .delete-image-btn:hover {
        background: #a93226;
    }

    .pg-header {
        margin-bottom: 20px;
    }
</style>

@php
$currentShop = $activeShop ?? request('shop');
$shopQuery = $currentShop ? '?shop=' . urlencode($currentShop) : '';
@endphp

<div class="container">
    <div class="pg-header">
        <div>
            <h4 class="pg-title fw-bold mb-1">Sync Product</h4>
            <p class="pg-subtitle">Fill in the details below to create your product</p>
        </div>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger" style="max-width:1100px; margin:0 auto 20px;">
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('shopify.product.create.post', ['shop' => $activeShop]) }}" enctype="multipart/form-data" id="productForm">
        @csrf
        @if ($currentShop)
        <input type="hidden" name="shop" value="{{ $currentShop }}">
        @endif

        <input type="hidden" name="amazon_title" id="amazonTitle" value="">
        <input type="hidden" name="sync_id" id="syncId" value="{{ $syncid ?? '' }}">

        <div class="card-shell">
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
                        <input type="text" name="title" class="form-control" placeholder="Enter product title"
                            value="{{ old('title', $product['title'] ?? '') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Product Category</label>
                        <select name="category" class="form-control" onchange="updatecategory(this.value)" required>
                            <option value="">Select Category</option>
                            @php
                            $selectedCategory = old('category', $dbProduct['category_id'] ?? '');
                            @endphp
                            @foreach(getCategorires() as $categories)
                            <option value="{{ $categories['id'] }}" {{ $selectedCategory==$categories['id'] ? 'selected' : '' }}>{{ $categories['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sub Category</label>
                        <select name="sub_category" id="sub_category" class="form-control" required>
                            <option value="">Select Sub Category</option>
                            @php
                            $selectedsubCategory = old('sub_category', $dbProduct['sub_category_id'] ?? '');
                            @endphp
                            <input type="hidden" id="selected_sub_category" value="{{ $selectedsubCategory }}">
                            @if(isset($dbProduct['category_id']))
                            @foreach(getCategorires($dbProduct['category_id']) as $collections)
                            <option value="{{ $collections['id'] }}" {{ $selectedsubCategory==$collections['id'] ? 'selected' : '' }}>{{ $collections['name'] }}</option>
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
                        <select name="status" class="form-control" required>
                            <option value="active" {{ (old('status', $product['status'] ?? '' )=='active' ) ? 'selected' : '' }}>Active</option>
                            <option value="draft" {{ (old('status', $product['status'] ?? '' )=='draft' ) ? 'selected' : '' }}>Draft</option>
                            <option value="archived" {{ (old('status', $product['status'] ?? '' )=='archived' ) ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Base Price (₹)</label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00"
                            value="{{ old('price', $product['variants'][0]['price'] ?? $product['price']) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="Enter SKU"
                            value="{{ old('sku', $product['variants'][0]['sku'] ?? $product['sku']) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Product Images (Multiple)</label>
                    <input type="file" name="images[]" class="form-control" multiple accept="image/*" id="imageUpload">
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

            <div class="panel mt-3">
                <div class="section-head">
                    <div>
                        <p class="section-title">Product Organization</p>
                        <p class="section-desc">Type, vendor, collections, and tags</p>
                    </div>
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

            <div class="panel mt-3">
                <div class="section-head">
                    <div>
                        <p class="section-title">Custom Metafields</p>
                        <p class="section-desc">Add name-value pairs for extended product data</p>
                    </div>
                </div>
                <div id="metaFieldsContainer">
                </div>
                <button type="button" onclick="addMetaField()" class="btn btn-outline-dark">
                    + Add Metafield
                </button>
            </div>

            <div class="panel mt-3">
                <div class="section-head">
                    <div>
                        <p class="section-title">Variant Types</p>
                        <p class="section-desc">Define Color, Size etc. — values comma-separated</p>
                    </div>
                </div>
                <div id="variantTypesContainer">
                </div>
                <button type="button" onclick="addVariantType()" class="btn btn-outline-dark"
                    style="margin-bottom: 28px;">
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

            <div class="float-end mt-3">
                <a href="{{ route('shopify.products') }}{{ $shopQuery }}" class="btn btn-primary"> Back </a>
                <button type="submit" class="btn btn-success"> Update Product </button>
            </div>
        </div>
    </form>
</div>@endsection

@push('scripts')
<script>
   const productForm = document.getElementById('productForm');
const productTitleInput = document.querySelector('input[name="title"]');
const amazonTitleInput = document.getElementById('amazonTitle');

// Product data passed from controller
const productData = @json($product);

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

            if((value.trim()=='{') || (value.trim()=='}') || (value.trim()=='[') || (value.trim()==']') || (value.trim()=='') || (value.trim()=='null')){
                continue; // Skip invalid or empty values;
            }

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
                    <input type="text" name="meta_name[]" class="form-control"
                        placeholder="e.g., material, warranty"
                        value="${escapeHtml(
                            meta.name
                                .replace(/_/g, ' ')
                                .toLowerCase()
                                .replace(/\b\w/g, c => c.toUpperCase())
                        )}">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Metafield Value</label>
                    <input type="text" name="meta_value[]" class="form-control"
                        placeholder="e.g., Cotton, 2 years"
                        value="${escapeHtml(meta.value)}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger" onclick="removeMetaField(this)">
                        Remove
                    </button>
                </div> `;
 
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

function generateCombinations() {

    const variantTypes = [];

    document.querySelectorAll('.variant-type-box').forEach(box => {
        const name = box.querySelector('.variant-type-name').value.trim();
        const values = box.querySelector('.variant-type-values').value
            .split(',')
            .map(v => v.trim())
            .filter(v => v !== '');

        if (name && values.length) {
            variantTypes.push({
                name,
                values
            });
        }
    });

    if (!variantTypes.length) {
        alert('Please define at least one variant type with values.');
        return;
    }

    let combinations = [[]];

    for (const type of variantTypes) {
        const next = [];
        for (const combo of combinations) {
            for (const value of type.values) {
                next.push([...combo, { type: type.name, value }]);
            }
        }
        combinations = next;
    }

    // Build a lookup of existing variants keyed by ALL of their option values,
    // joined in order (option1|option2|option3|option4|...).
    // Built dynamically so it works no matter how many options
    // (Size, Style, Material, Pattern, etc.) the product actually has.
    const existingVariantsMap = {};

    if (productData.variants && productData.variants.length) {
        productData.variants.forEach(variant => {
            const optionValues = [];
            let i = 1;
            while (variant['option' + i] !== undefined) {
                optionValues.push(variant['option' + i] || '');
                i++;
            }
            const key = optionValues.join('|');
            existingVariantsMap[key] = variant;
        });
    }

    const imageIdToSrc = {};
    if (productData.images) {
        productData.images.forEach(image => {
            if (image.id) {
                imageIdToSrc[image.id] = image.src;
            }
        });
    }

    const matrixDiv = document.getElementById('combinationMatrix');
    matrixDiv.innerHTML = '';

    if (combinations.length === 0) {
        matrixDiv.innerHTML = '<p style="color:#c0392b;margin:14px 16px;">No combinations generated.</p>';
        return;
    }

    const table = document.createElement('table');
    let thead = '<thead><tr>';
    variantTypes.forEach(t => thead += `<th>${t.name}</th>`);
    thead += '<th>Image</th><th>Price (₹)</th><th>SKU</th><th>Qty</th></tr></thead>';
    table.innerHTML = thead;

    const tbody = document.createElement('tbody');

    combinations.forEach((combo, idx) => {

        const values = combo.map(c => c.value);
        const comboKey = values.join('|');
        const existingVariant = existingVariantsMap[comboKey] || null;

        let price = existingVariant
            ? existingVariant.price
            : (productData.price || document.querySelector('[name="base_price"]').value || '');

        let qty = existingVariant
            ? (existingVariant.inventory_quantity ?? 0)
            : 0;

        let sku = existingVariant
            ? (existingVariant.sku || '')
            : '';

        if (!sku) {
            
            sku = productData.sku || 'SKU';
            if (values.length) {
                sku += '-' + values
                    .map(v => v.replace(/\s+/g, '-').toUpperCase())
                    .join('-');
            }
        }

        let imageHtml = '';

        if (
            existingVariant &&
            existingVariant.image_id &&
            imageIdToSrc[existingVariant.image_id]
        ) {
            imageHtml = `
                <div class="mt-2">
                    <img src="${imageIdToSrc[existingVariant.image_id]}"
                         style="width:45px;height:45px;object-fit:cover">
                    <input type="hidden"
                        name="variants[${idx}][existing_image_id]"
                        value="${existingVariant.image_id}">
                </div>
            `;
        }

        const row = document.createElement('tr');
        let cells = '';

        combo.forEach((c, i) => {
            cells += `<td>
                ${c.value}
                <input type="hidden" name="variants[${idx}][option${i+1}]" value="${c.value}">
            </td>`;
        });

        cells += `
            <input type="hidden"
                name="variants[${idx}][variant_id]"
                value="${existingVariant ? existingVariant.id : ''}">

            <input type="hidden"
                name="variants[${idx}][inventory_item_id]"
                value="${existingVariant ? existingVariant.inventory_item_id : ''}">

            <td>
                <input
                    type="file"
                    class="form-control form-control-sm"
                    name="variants[${idx}][image]"
                    accept="image/*">
                ${imageHtml}
            </td>

            <td>
                <input
                    type="number"
                    step="0.01"
                    class="form-control form-control-sm"
                    name="variants[${idx}][price]"
                    value="${price}">
            </td>

            <td>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    name="variants[${idx}][sku]"
                    value="${sku}">
            </td>

            <td>
                <input
                    type="number"
                    class="form-control form-control-sm"
                    name="variants[${idx}][qty]"
                    min="0"
                    value="${qty}">
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
}

document.addEventListener('DOMContentLoaded', function () {

    initVariantTypes();

    initMetafields();

    const category = document.querySelector('[name="category"]');

    if (category && category.value) {
        updatecategory(category.value);
    }

    // Automatically generate combinations when options already exist
    setTimeout(function () {

        const hasOptions =
            document.querySelectorAll('.variant-type-box').length > 0 &&
            [...document.querySelectorAll('.variant-type-values')]
            .some(i => i.value.trim() !== '');

        if (hasOptions) {
            generateCombinations();
        }

    }, 100);

});

function updatecategory(category) {

    if (!category) {

        document.getElementById('sub_category').innerHTML =
            '<option value="">Select Sub Category</option>';

        return;

    }

    fetch(`{{ route('shopify.product.category') }}?parent_id=${category}`, {

        headers: {

            'Accept': 'application/json',

            'X-Requested-With': 'XMLHttpRequest'

        }

    })
    .then(response => response.json())

    .then(data => {

        let subSelect = document.getElementById('sub_category');

        subSelect.innerHTML =
            '<option value="">Select Sub Category</option>';

        const selectedSubCategory =
            document.getElementById('selected_sub_category').value;

        data.forEach(item => {
            let option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            if (selectedSubCategory == item.id) {
                option.selected = true;
            }

            subSelect.appendChild(option);

        });
    })

    .catch(error => {

        console.error(error);

    });

}
</script>
@endpush