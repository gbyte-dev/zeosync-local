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
        gap: 6px;
        margin-top: 6px;
    }

    .file-preview img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #E5E7EB;
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
                                disabled>

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
                    <label class="form-label">Product Images (Multiple)</label>
                    <input type="file" name="images[]" class="form-control" style="padding-top:4px;" multiple accept="image/*" id="imageUpload">
                    <div class="file-preview" id="imagePreview"></div>
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
                        <input type="text" name="product_type" class="form-control" placeholder="e.g., Clothing, Electronics" value="{{ old('product_type') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vendor</label>
                        <input type="text" name="vendor" class="form-control" placeholder="e.g., Nike, Apple" value="{{ old('vendor') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Collections</label>
                        <input type="text" name="collections" class="form-control" placeholder="Comma separated collections" value="{{ old('collections') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="Comma separated tags" value="{{ old('tags') }}">
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
                    <span class="remove-btn" onclick="this.parentElement.remove()">✕</span>
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
    <button type="submit" class="btn btn-success">
        Save Product
    </button>
</div>

</div><!-- /card-shell -->
</form>
</div><!-- /pg-wrap -->
@endsection

@push('scripts')
<script>
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

    document.getElementById('imageUpload').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = ev => {
                const img = document.createElement('img');
                img.src = ev.target.result;
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
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

        console.log('SEARCH:', search);

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

        console.log('REQUEST URL:', url);

        const xhr = new XMLHttpRequest();

        xhr.open('GET', url, true);

        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onreadystatechange = function() {

            if (xhr.readyState !== XMLHttpRequest.DONE) {
                return;
            }

            console.log('XHR STATUS:', xhr.status);
            console.log('XHR RESPONSE:', xhr.responseText);

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

                    subCategoryResults.innerHTML = '';
                    subCategoryResults.style.display = 'none';
                });

                subCategoryResults.appendChild(item);
            });

            subCategoryResults.style.display = 'block';

            console.log('RESULTS:', categories.length);
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



    function addVariantType() {
        const container = document.getElementById('variantTypesContainer');
        const div = document.createElement('div');
        div.classList.add('variant-type-box');
        div.innerHTML = `
            <span class="remove-btn" onclick="this.parentElement.remove()">✕</span>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Type Name</label>
                    <input type="text" class="form-control variant-type-name" placeholder="e.g., Material">
                </div>
                <div class="col-md-8 mb-2">
                    <label class="form-label">Possible Values</label>
                    <input type="text" class="form-control variant-type-values" placeholder="e.g., Cotton, Polyester">
                </div>
            </div>`;
        container.appendChild(div);
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
    }

    function removeMetaField(button) {
        button.closest('.meta-field-row').remove();
    }

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
        matrixDiv.style.padding = '0'; // reset padding for table

        if (combinations.length === 0) {
            matrixDiv.style.padding = '12px';
            matrixDiv.innerHTML = '<p class="text-muted">No combinations generated.</p>';
            return;
        }
        const table = document.createElement('table');
        let thead = '<thead><tr>';
        variantTypes.forEach(t => thead += `<th>${t.name}</th>`);
        thead += '<th>Image</th><th>Price</th><th>SKU</th><th style="width:70px;">Qty</th></tr></thead>';
        table.innerHTML = thead;
        const tbody = document.createElement('tbody');
        combinations.forEach((combo, idx) => {
            const row = document.createElement('tr');
            let cells = '';
            combo.forEach((c, i) => {
                cells += `<td>
                    ${c.value}
                    <input type="hidden" name="variants[${idx}][option${i+1}]" value="${c.value}">
                </td>`;
            });
            cells += `
                <td>
                    <input type="file" name="variants[${idx}][image]" class="form-control form-control-sm" style="padding-top:2px;">
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
    }

    function updatecategory(category) {
        subCategorySearch.value = '';
        subCategoryInput.value = '';
        subCategoryResults.innerHTML = '';
        subCategoryResults.style.display = 'none';

        subCategorySearch.disabled = !category;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('productForm');
        if (!form) return;
        form.addEventListener('submit', function() {
            if (typeof showLoader === "function") {
                showLoader('Creating product...');
            }
        });
    });
</script>
@endpush