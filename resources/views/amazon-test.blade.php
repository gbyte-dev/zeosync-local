@extends('layouts.app')
@section('content')
@push('css')
<style>
    /* ============================================= */
    /* MODERN ADMIN THEME - RESPONSIVE LAYOUT        */
    /* ============================================= */
    .card-shell {
        display: flex;
        flex-direction: column;
    }

    .panel {
        background: #ffffff;
        padding: 32px;
        border: 1px solid #e5e5e5;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        margin-bottom: 24px;
    }

    .section-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 16px;
    }

    .section-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #f8f9fa !important;
        display: grid;
        place-items: center;
        font-size: 16px;
        flex-shrink: 0;
        border: 1px solid #e9ecef;
        color: #000000 !important;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #111111;
    }

    .section-desc {
        font-size: 0.8rem;
        color: #666666;
        margin: 0;
    }

    /* Fluid Inputs & Typography */
    .field-wrapper {
        margin-bottom: 0.5rem;
        width: 100%;
    }

    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #333333;
        margin-bottom: 8px;
        display: block;
    }

    .form-control,
    .form-select {
        font-size: 0.85rem;
        padding: 10px 14px;
        border: 1px solid #dcdcdc;
        background: #ffffff;
        color: #111111;
        border-radius: 8px;
        width: 100%;
        /* Forces input to fill column */
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01) inset;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #000000;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    /* Image Grid */
    .image-gallery-grid {
        display: flex;
        flex-wrap: wrap;
        /* Wraps to next line when row is full */
        gap: 16px;
        /* Space between images */
        margin-bottom: 1rem;
    }

    .image-upload-card {
        width: 140px;
        /* Fixed width for each horizontal card */
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #fafafa;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #e5e5e5;
    }

    .image-upload-card label {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0;
    }

    .image-preview-box {
        width: 100%;
        height: 115px;
        border-radius: 6px;
        border: 1px solid #ddd;
        object-fit: cover;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        color: #888;
    }

    /* General Layout Utilities */
    pre {
        background: #f8f9fa !important;
        border: 1px solid #e9ecef !important;
        border-radius: 10px !important;
        padding: 20px !important;
    }

    @media (max-width: 768px) {
        .panel {
            padding: 20px;
        }

        .image-upload-card {
            width: calc(50% - 8px);
        }
    }
</style>
@endpush
<div style="max-width: 1140px !important;" class="container">
    <!-- Page header -->
    <div class="pg-header">
        <div>
            <h2 class="pg-title">Amazon Product Sync</h2>
            <p class="pg-subtitle">Configure your Amazon product fields below</p>
        </div>
    </div>
    <div class="card-shell">
        <div class="panel">
            <!-- Category Selection Section -->
            <div class="section-head">
                <div class="section-icon">📂</div>
                <div>
                    <p class="section-title">Category Selection</p>
                    <p class="section-desc">Choose category and subcategory to load fields</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Category</label>
                    <select id="category" class="form-select">
                        <option value="">Select Category</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Subcategory</label>
                    <select id="subcategory" class="form-select">
                        <option value="">Select Subcategory</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button id="loadFieldsBtn" class="btn btn-dark w-100">
                        Load Amazon Fields
                    </button>
                </div>
            </div>
            <div class="panel-divider" style="height: 1px; background: #e5e5e5; margin: 20px 0;"></div>
            <!-- Action Buttons -->
            <div class="d-flex justify-content-end mb-3">
                <button type="button" id="generatePayloadBtn" class="btn btn-success">
                    Generate Sync Payload
                </button>
            </div>
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" id="fieldTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-pane" type="button">
                        Basic Details
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="required-tab" data-bs-toggle="tab" data-bs-target="#required-pane" type="button">
                        Required Fields
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="other-tab" data-bs-toggle="tab" data-bs-target="#other-pane" type="button">
                        Other Fields
                    </button>
                </li>
            </ul>
            <!-- Tab Content -->
            <div class="tab-content">
                <div class="tab-pane fade show active" id="basic-pane">
                    <div id="mainImageFieldsWrapper" class="mb-4" style="display:none;">
                        <div class="panel">
                            <div class="section-head">
                                <div class="section-icon">📸</div>
                                <div>
                                    <p class="section-title">Main Product Images</p>
                                    <p class="section-desc">Upload your main product images in horizontal view</p>
                                </div>
                            </div>
                            <div id="dynamicMainImageInputs" class="image-gallery-grid"></div>
                            <button type="button" id="addMainImageBtn" class="btn btn-outline-dark btn-sm mt-3">
                                + Add Gallery Image
                            </button>
                        </div>
                    </div>
                    <div id="basicFields"></div>
                </div>
                <div class="tab-pane fade" id="required-pane">
                    <div id="requiredFields"></div>
                </div>
                <div class="tab-pane fade" id="other-pane">
                    <div id="offerImageFieldsWrapper" class="mb-4" style="display:none;">
                        <div class="panel" style="padding: 20px 24px;">
                            <div class="section-head">
                                <div class="section-icon">🎁</div>
                                <div>
                                    <p class="section-title">Offer Images</p>
                                    <p class="section-desc">Upload your offer images</p>
                                </div>
                            </div>
                            <div id="dynamicOfferImageInputs"></div>
                            <button type="button" id="addOfferImageBtn" class="btn btn-outline-dark btn-sm mt-2">
                                + Add Offer Image
                            </button>
                        </div>
                    </div>
                    <div id="otherFields"></div>
                </div>
            </div>
            <div class="panel-divider" style="height: 1px; background: #e5e5e5; margin: 20px 0;"></div>
            <!-- Payload Preview -->
            <div class="section-head">
                <div class="section-icon">📋</div>
                <div>
                    <p class="section-title">Generated Payload Preview</p>
                    <p class="section-desc">Review your Amazon sync payload</p>
                </div>
            </div>
            <pre id="payloadPreview" class="p-3 rounded" style="min-height:200px; background: #f5f5f5; border: 1px solid #dddddd;"></pre>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    window.validateRulesUrl = '/zeosync/amazon/validate-rules';
    console.log(window.validateRulesUrl);
    window.evaluateConditionsUrl =
        "{{ route('amazon.evaluate.conditions') }}";
    console.log(window.evaluateConditionsUrl);

    window.selectedCategory = @json($selectedCategory);
    window.selectedSubcategory = @json($selectedSubcategory);
    window.prefillData = @json($prefillData);
    let mainImageIndex = 0;
    let offerImageIndex = 0;
    window.addEventListener('DOMContentLoaded', function() {
        console.log('Selected Category:', window.selectedCategory);
        if (!window.selectedCategory) {
            return;
        }
        const categorySelect = document.getElementById('category');
        const matchedCategory = [...categorySelect.options].find(option =>
            option.text.trim().toLowerCase().includes(
                window.selectedCategory.trim().toLowerCase()
            )
        );
        if (matchedCategory) {
            console.log('Matched Category:', matchedCategory.text);
            categorySelect.value = matchedCategory.value;
            categorySelect.dispatchEvent(new Event('change'));
        } else {
            console.warn('Category not matched');
        }
    });
    document.getElementById('category').addEventListener('change', function() {
        const categoryId = this.value;
        const subcategorySelect = document.getElementById('subcategory');
        subcategorySelect.innerHTML = '<option value="">Loading...</option>';
        if (!categoryId) {
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            return;
        }
        fetch(`{{ url('amazon/subcategories') }}/${categoryId}`)
            .then(res => res.json())
            .then(data => {
                console.log('Selected Subcategory:', window.selectedSubcategory);
                subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                data.subcategories.forEach(item => {
                    subcategorySelect.innerHTML += `
                                    <option value="${item.category}">
                                        ${item.name}
                                    </option>
                                `;
                });
                if (window.selectedSubcategory) {
                    const matchedSubcategory = [...subcategorySelect.options].find(option =>
                        option.text.trim().toLowerCase() ===
                        window.selectedSubcategory.trim().toLowerCase()
                    );
                    if (matchedSubcategory) {
                        console.log('Matched Subcategory:', matchedSubcategory.text);
                        subcategorySelect.value = matchedSubcategory.value;
                        setTimeout(() => {
                            document.getElementById('loadFieldsBtn').click();
                        }, 300);
                    } else {
                        console.warn('Subcategory not matched');
                    }
                }
            })
            .catch(error => {
                console.error(error);
                subcategorySelect.innerHTML = '<option value="">Failed to load</option>';
            });
    });
    document.getElementById('loadFieldsBtn').addEventListener('click', function() {
        const slug = document.getElementById('subcategory').value;
        if (!slug) {
            alert('Please select subcategory');
            return;
        }
        showLoader('Loading Amazon Fields...');
        fetch(`{{ url('amazon/schema-fields') }}/${slug}`)
            .then(res => res.json())
            .then(data => {
                console.log('FULL RESPONSE', data);
                console.log('RULES', data.rules);
                console.log('FIELDS', data.fields);
                window.amazonRules = data.rules || [];
                renderFields(data.fields || []);


            })
            .catch(err => {
                console.error(err);
                alert('Failed to load schema');
            })
            .finally(() => {
                hideLoader();
            });
    });

    function extractEnumOptions(property) {
        if (!property) {
            return [];
        }
        if (property.enum) {
            return property.enum;
        }
        if (property.anyOf) {
            const enumOption = property.anyOf.find(item => item.enum);
            if (enumOption) {
                return enumOption.enum;
            }
        }
        return [];
    }

    function renderFields(fields) {
        window.amazonFields = fields;
        const basicWrapper = document.getElementById('basicFields');
        const requiredWrapper = document.getElementById('requiredFields');
        const otherWrapper = document.getElementById('otherFields');
        basicWrapper.innerHTML = '';
        requiredWrapper.innerHTML = '';
        otherWrapper.innerHTML = '';
        if (!fields.length) {
            basicWrapper.innerHTML = `<div class="alert alert-warning">No fields found</div>`;
            return;
        }
        const imageFields = fields.filter(field => field.type === 'image');
        if (imageFields.length) {
            renderImageInputs();
        } else {
            document.getElementById('mainImageFieldsWrapper').style.display = 'none';
            document.getElementById('offerImageFieldsWrapper').style.display = 'none';
        }
        const usedFields = [];
        // HELPER: Renders deeply nested schema properties
        function renderSchemaProperty(fieldKey, label, schema, value = '') {
            // Handle nested Object
            const prefillValue =
                window.prefillData?.[fieldKey] ??
                window.prefillData?.[fieldKey.replace(/\.value$/, '')] ??
                '';
            if (schema.type === 'object' && schema.properties) {
                let html = `
                <div class="col-12 mt-3 mb-2">
                    <h6 class="fw-bold" style="border-bottom: 1px solid #e5e5e5; padding-bottom: 8px;">${label}</h6>
                </div>
            `;
                Object.entries(schema.properties).forEach(([key, property]) => {
                    if (['marketplace_id', 'language_tag'].includes(key)) return;
                    html += renderSchemaProperty(`${fieldKey}.${key}`, property.title || key, property);
                });
                return html;
            }
            if (schema.items) {
                return renderSchemaProperty(fieldKey, label, schema.items);
            }
            const options = extractEnumOptions(schema);
            // Handle Select Dropdowns
            if (options.length) {
                return `
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="mb-3 field-wrapper">
                        <label class="form-label">${label}</label>
                       <select class="form-select dynamic-input" data-key="${fieldKey}">
    <option value="">Select</option>
    ${options.map(option => `
        <option value="${option}"
            ${option == prefillValue ? 'selected' : ''}>
            ${option}
        </option>
    `).join('')}
</select>
                    </div>
                </div>
            `;
            }
            // Handle standard text/number inputs
            return `
            <div class="col-12 col-md-6 col-lg-6">
                <div class="mb-3 field-wrapper">
                    <label class="form-label">${label}</label>
                    <input
    type="${schema.type === 'number' ? 'number' : 'text'}"
    class="form-control dynamic-input"
    data-key="${fieldKey}"
    value="${prefillValue}">
                </div>
            </div>
        `;
        }
        // HELPER: Generates the HTML Sections
        function createSection(wrapper, title, sectionFields) {
            if (!sectionFields.length) return;
            const section = document.createElement('div');
            section.className = 'panel mb-4';
            const header = document.createElement('div');
            header.className = 'section-head';
            header.innerHTML = `
            <div class="section-icon">📝</div>
            <div>
                <p class="section-title">${title}</p>
                <p class="section-desc">Configure your product details below</p>
            </div>
        `;
            const body = document.createElement('div');
            const row = document.createElement('div');
            row.className = 'row g-4'; // Added g-4 for proper Bootstrap Grid spacing
            sectionFields.forEach(field => {
                usedFields.push(field.key);
                let html = '';
                // Handle Schema arrays (Fixes the Duplicate Label & Empty Column Issue)
                if (field.schema?.items?.properties) {
                    const validKeys = Object.keys(field.schema.items.properties).filter(k => !['marketplace_id', 'language_tag'].includes(k));
                    // Only create a full-width section header if there are MULTIPLE sub-fields
                    if (validKeys.length > 1) {
                        html += `
                        <div class="col-12">
                            <p class="section-title" style="font-size:1rem; font-weight:600; margin-top:1rem; margin-bottom:0.5rem; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                                ${field.label}
                            </p>
                        </div>
                    `;
                    }
                    Object.entries(field.schema.items.properties).forEach(([key, property]) => {
                        if (['marketplace_id', 'language_tag'].includes(key)) return;
                        // If it's the primary 'value' key, inherit the parent label to avoid "Value" duplicates.
                        const inputLabel = (validKeys.length === 1 && key === 'value') ? field.label : (property.title || key);
                        const requiredMark = field.required ? '<span style="color: #c0392b;">*</span>' : '';
                        html += renderSchemaProperty(
                            `${field.key}.${key}`,
                            `${inputLabel} ${requiredMark}`,
                            property
                        );
                    });
                    row.innerHTML += html;
                    return;
                }
                // Handle Standard Single Fields
                const fieldName = field.key;
                const fieldLabel = field.name || field.label || field.key;
                const requiredMark = field.required ? '<span style="color: #c0392b;">*</span>' : '';
                const prefillValue = window.prefillData && window.prefillData[fieldName] ? window.prefillData[fieldName] : '';

                if (fieldName === 'item_name' || fieldName === 'brand') {
                    console.log({
                        fieldName,
                        prefillValue
                    });
                }
                if (field.type === 'select' && field.options?.length) {
                    html = `
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="mb-3 field-wrapper" data-field="${fieldName}">
                            <label class="form-label">${fieldLabel} ${requiredMark}</label>
                            <select class="form-select dynamic-input" data-key="${fieldName}" name="${fieldName}" ${field.required ? 'required' : ''}>
                                <option value="">Select ${fieldLabel}</option>
                                ${field.options.map(option => `<option value="${option}" ${option == prefillValue ? 'selected' : ''}>${option}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                `;
                } else if (field.type === 'checkbox') {
                    html = `
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="form-check field-wrapper d-flex align-items-center h-100" data-field="${fieldName}" style="min-height: 42px; margin-top: 1.8rem;">
                            <input type="checkbox" class="form-check-input dynamic-input me-2 mt-0" data-key="${fieldName}" name="${fieldName}" value="true" ${prefillValue ? 'checked' : ''}>
                            <label class="form-check-label form-label mb-0" style="cursor:pointer;">
                                ${fieldLabel} ${requiredMark}
                            </label>
                        </div>
                    </div>
                `;
                } else if (field.type === 'textarea') {
                    html = `
                    <div class="col-12">
                        <div class="mb-3 field-wrapper" data-field="${fieldName}">
                            <label class="form-label">${fieldLabel} ${requiredMark}</label>
                            <textarea class="form-control dynamic-input" data-key="${fieldName}" name="${fieldName}" rows="4" placeholder="Enter ${fieldLabel}" ${field.required ? 'required' : ''}>${prefillValue}</textarea>
                        </div>
                    </div>
                `;
                } else {
                    html = `
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="mb-3 field-wrapper" data-field="${fieldName}">
                            <label class="form-label">${fieldLabel} ${requiredMark}</label>
                            <input type="${field.type === 'number' ? 'number' : 'text'}" class="form-control dynamic-input" data-key="${fieldName}" name="${fieldName}" placeholder="Enter ${fieldLabel}" value="${prefillValue}" ${field.required ? 'required' : ''}>
                        </div>
                    </div>
                `;
                }
                row.innerHTML += html;
            });
            body.appendChild(row);
            section.appendChild(header);
            section.appendChild(body);
            wrapper.appendChild(section);
        }
        // Section Generation Callbacks
        const basicKeywords = [
            'item_name', 'title', 'brand', 'manufacturer', 'model', 'product_type',
            'item_type_keyword', 'description', 'bullet', 'material', 'fabric',
            'color', 'size', 'style', 'department', 'target_gender', 'pattern',
            'fit', 'theme', 'occasion', 'search_terms', 'main_product_image_locator',
            'swatch_product_image_locator', 'product_image', 'image'
        ];
        const basicFields = fields.filter(field => field.type !== 'image' && field.key !== 'skip_offer' && basicKeywords.some(keyword => field.key.includes(keyword)));
        createSection(basicWrapper, 'Basic Product Details', basicFields);
        const requiredFields = fields.filter(field => field.type !== 'image' && field.required && !basicFields.some(basic => basic.key === field.key));
        createSection(requiredWrapper, 'Required Product Fields', requiredFields);
        const otherFields = fields.filter(field => field.type !== 'image' && !basicFields.some(basic => basic.key === field.key) && !requiredFields.some(required => required.key === field.key));
        const skipOfferField = fields.find(field => field.key === 'skip_offer');
        if (skipOfferField && !otherFields.some(field => field.key === 'skip_offer')) {
            otherFields.unshift(skipOfferField);
        }
        createSection(otherWrapper, 'Other Product Fields', otherFields);
        bindPayloadPreview();
        toggleOfferFields();
        setTimeout(() => {
            applyShirtRules();
        }, 300);
    }

    function renderImageInputs() {
        renderMainImages();
        renderOfferImages();
    }

    function renderMainImages() {
        const wrapper = document.getElementById('dynamicMainImageInputs');
        if (!wrapper) {
            return;
        }
        wrapper.innerHTML = '';
        mainImageIndex = 0;
        addMainImageInput();
        Object.keys(window.prefillData || {})
            .filter(key => key.startsWith('other_product_image_locator_'))
            .forEach(() => {
                addMainImageInput();
            });
        document.getElementById('mainImageFieldsWrapper').style.display = 'block';
    }

    function renderOfferImages() {
        const wrapper = document.getElementById('dynamicOfferImageInputs');
        if (!wrapper) {
            return;
        }
        wrapper.innerHTML = '';
        offerImageIndex = 0;
        addOfferImageInput();
    }

    function addMainImageInput() {
        const wrapper = document.getElementById('dynamicMainImageInputs');
        // Automatically add the flex grid class so it displays horizontally
        if (!wrapper.classList.contains('image-gallery-grid')) {
            wrapper.classList.add('image-gallery-grid');
        }
        const fieldName = mainImageIndex === 0 ? 'main_product_image_locator' : `other_product_image_locator_${mainImageIndex}`;
        const label = mainImageIndex === 0 ? 'Main Image' : `Gallery Image ${mainImageIndex}`;
        const div = document.createElement('div');
        // Using the new horizontal card class
        div.className = 'image-upload-card';
        div.setAttribute('data-field', fieldName);
        const imageUrl = window.prefillData?.[fieldName] || '';
        div.innerHTML = `
        <label title="${label}">${label}</label>
        ${imageUrl ? `
            <img src="${imageUrl}" class="image-preview-box" alt="Preview">
        ` : `
            <div class="image-preview-box">
                <span>No Image</span>
            </div>
        `}
        <input type="file" accept="image/*" class="form-control dynamic-input" data-key="${fieldName}" data-uploaded-url="${imageUrl}" name="${fieldName}">
    `;
        wrapper.appendChild(div);
        mainImageIndex++;
        bindPayloadPreview();
    }

    function addOfferImageInput() {
        const wrapper = document.getElementById('dynamicOfferImageInputs');
        // Automatically add the flex grid class so it displays horizontally
        if (!wrapper.classList.contains('image-gallery-grid')) {
            wrapper.classList.add('image-gallery-grid');
        }
        const fieldName = offerImageIndex === 0 ? 'swatch_product_image_locator' : `offer_product_image_locator_${offerImageIndex}`;
        const label = offerImageIndex === 0 ? 'Offer Main' : `Offer Image ${offerImageIndex}`;
        const div = document.createElement('div');
        // Using the new horizontal card class
        div.className = 'image-upload-card';
        div.setAttribute('data-field', fieldName);
        div.innerHTML = `
        <label title="${label}">${label}</label>
        <div class="image-preview-box">
            <span>No Image</span>
        </div>
        <input type="file" accept="image/*" class="form-control dynamic-input" data-key="${fieldName}" name="${fieldName}">
    `;
        wrapper.appendChild(div);
        offerImageIndex++;
        bindPayloadPreview();
    }
    document.getElementById('addMainImageBtn').addEventListener('click', addMainImageInput);
    document.getElementById('addOfferImageBtn').addEventListener('click', addOfferImageInput);
    document.getElementById('generatePayloadBtn').addEventListener('click', async function() {
        generatePayload();
        const payload = JSON.parse(
            document.getElementById('payloadPreview').textContent
        );
        const productId = "{{ $product->id ?? '' }}";
        if (!productId) {
            alert('Product ID missing');
            return;
        }
        try {
            console.log(payload);
            const response = await fetch(
                "{{ route('amazon.manual.sync') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        payload: payload
                    })
                }
            );
            const data = await response.json();
            console.log(data);
            if (data.success) {
                const box =
                    document.getElementById(
                        'amazonErrorBox'
                    );
                if (box) {
                    box.remove();
                }
                alert('Amazon sync successful');
            } else {
                console.error(data);
                renderAmazonErrors(
                    data.errors || [],
                    data.issues || []
                );
            }
            // if (data.success) {
            //     alert('Amazon sync successful');
            // } else {
            //     console.error(data);
            //     alert(data.message || 'Amazon sync failed');
            // }
        } catch (e) {
            console.error(e);
            alert('Sync request failed');
        }
    });



    function collectRawPayload() {

        const payload = {};

        document.querySelectorAll('.dynamic-input').forEach(input => {

            const wrapper = input.closest('.field-wrapper');

            if (wrapper && wrapper.style.display === 'none') {
                return;
            }

            const key = input.dataset.key;

            if (!key) {
                return;
            }

            let value;

            switch (input.type) {
                case 'checkbox':
                    value = input.checked;
                    break;

                case 'file':
                    value = input.dataset.uploadedUrl || '';
                    break;

                case 'number':
                    value = input.value === '' ? null : Number(input.value);
                    break;

                default:
                    value = input.value?.trim();
            }

            if (
                value === '' ||
                value === null ||
                value === undefined
            ) {
                return;
            }

            if (key.includes('.')) {

                setNestedValue(
                    payload,
                    key.split('.'),
                    value
                );

            } else {

                payload[key] = value;

            }

        });

        return payload;
    }

    // function bindPayloadPreview() {
    //     document.querySelectorAll(
    //         '.dynamic-input'
    //     ).forEach(input => {
    //         input.oninput =
    //             generatePayload;
    //         input.onchange =
    //             function() {
    //                 generatePayload();
    //                 document
    //                     .querySelectorAll(
    //                         '.dynamic-input[data-key]'
    //                     )
    //                     .forEach(field => {
    //                         if (
    //                             field.tagName ===
    //                             'SELECT'
    //                         ) {
    //                             refreshDynamicField(
    //                                 field.dataset.key
    //                             );
    //                         }
    //                     });
    //             };
    //     });
    // }

    function bindPayloadPreview() {

        document.querySelectorAll('.dynamic-input').forEach(input => {

            input.oninput = generatePayload;

            input.onchange = function() {

                generatePayload();

                validateAmazonForm();

                evaluateConditions();

            };

        });

    }

    function toggleOfferFields() {
        const skipOfferField = document.querySelector('[data-key="skip_offer"]');
        if (!skipOfferField) {
            return;
        }
        let hideOfferFields = false;
        if (skipOfferField.type === 'checkbox') {
            hideOfferFields = skipOfferField.checked;
        } else {
            hideOfferFields = skipOfferField.value === 'true';
        }
        const offerRelatedFields = [
            'list_price', 'purchasable_offer', 'fulfillment_availability',
            'map_policy', 'condition_type', 'condition_note', 'merchant_release_date',
            'max_order_quantity', 'gift_options'
        ];
        offerRelatedFields.forEach(field => {
            const wrapper = document.querySelector(`[data-field="${field}"]`);
            if (!wrapper) {
                return;
            }
            wrapper.style.display = hideOfferFields ? 'none' : '';
        });
        const offerImageWrapper = document.getElementById('offerImageFieldsWrapper');
        if (offerImageWrapper) {
            offerImageWrapper.style.display = hideOfferFields ? 'none' : 'block';
        }
    }

    function applyShirtRules() {
        const sizeClass =
            document.querySelector('[data-key="shirt_size.size_class"]')?.value || '';
        const sizeSystem =
            document.querySelector('[data-key="shirt_size.size_system"]')?.value || '';
        const hideFields = [
            'shirt_size.height_type',
            'shirt_size.neck_size',
            'shirt_size.neck_size_to',
            'shirt_size.sleeve_length',
            'shirt_size.sleeve_length_to',
            'shirt_size.size_to'
        ];
        // reset
        hideFields.forEach(key => {
            const wrapper = document
                .querySelector(`[data-key="${key}"]`)
                ?.closest('.field-wrapper');
            if (wrapper) {
                wrapper.style.display = '';
            }
        });
        // alpha size rules
        if (
            sizeSystem === 'as1' &&
            sizeClass === 'alpha'
        ) {
            hideFields.forEach(key => {
                const input =
                    document.querySelector(
                        `[data-key="${key}"]`
                    );
                const wrapper =
                    input?.closest('.field-wrapper');
                if (wrapper) {
                    wrapper.style.display = 'none';
                }
                if (input) {
                    input.value = '';
                }
            });
        }
    }

    function setNestedValue(obj, keys, value) {
        let current = obj;
        keys.forEach((key, index) => {
            const isLast = index === keys.length - 1;
            if (isLast) {
                current[key] = value;
                return;
            }
            if (!current[key]) {
                current[key] = {};
            }
            current = current[key];
        });
    }

    function getFieldEl(key) {
        return document.querySelector(`[data-key="${key}"]`);
    }

    function getWrapperByKey(key) {
        const el = getFieldEl(key);
        return el ? el.closest('.field-wrapper') : null;
    }

    function setFieldVisible(key, visible) {
        const wrapper = getWrapperByKey(key);
        if (wrapper) {
            wrapper.style.display = visible ? '' : 'none';
        }
    }

    function clearFieldValue(key) {
        const el = getFieldEl(key);
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = false;
        } else {
            el.value = '';
        }
    }
    const USE_RAW_PAYLOAD = false; // Set to true to use raw payload collection

    function generatePayload() {

        if (USE_RAW_PAYLOAD) {

            const rawPayload = collectRawPayload();

            console.log('RAW PAYLOAD', rawPayload);

            document.getElementById('payloadPreview').textContent =
                JSON.stringify(rawPayload, null, 4);

            return rawPayload;
        }
        console.log('GENERATE PAYLOAD CALLED');
        let payload = {};

        window.amazonFields.forEach(field => {

            const node = buildNode(field.schema);

            if (field.key === 'outer') {
                console.log('BUILD NODE OUTER', node);
            }

            payload[field.key] = node;
        });
        let nestedPayload = {};
        const marketplaceId = 'ATVPDKIKX0DER';
        document.querySelectorAll('.dynamic-input').forEach(input => {
            const wrapper = input.closest('.field-wrapper');
            if (wrapper && wrapper.style.display === 'none') {
                return;
            }
            const key = input.dataset.key;
            if (!key) {
                return;
            }
            let value = '';
            if (input.type === 'checkbox') {
                value = input.checked;
            } else if (input.type === 'file') {
                value = input.dataset.uploadedUrl || '';
            } else if (input.type === 'number') {
                value = input.value !== '' ? Number(input.value) : '';
            } else {
                value = input.value ? input.value.trim() : '';
            }
            if (value === '' || value === null || value === undefined) {
                return;
            }
            if (key.includes('.')) {
                setNestedValue(nestedPayload, key.split('.'), value);
                return;
            }
            if (key === 'item_package_dimensions') {
                const length = document.querySelector('[data-key="package_length"]')?.value;
                const width = document.querySelector('[data-key="package_width"]')?.value;
                const height = document.querySelector('[data-key="package_height"]')?.value;
                if (!length || !width || !height) {
                    return;
                }
                payload[key] = [{
                    length: {
                        value: Number(length),
                        unit: 'inches'
                    },
                    width: {
                        value: Number(width),
                        unit: 'inches'
                    },
                    height: {
                        value: Number(height),
                        unit: 'inches'
                    }
                }];
                return;
            }
            if (key === 'item_weight' || key === 'item_display_weight' || key === 'item_package_weight') {
                payload[key] = [{
                    value: Number(value),
                    unit: 'pounds'
                }];
                return;
            }
            if (key.includes('image')) {
                payload[key] = [{
                    media_location: value,
                    marketplace_id: marketplaceId
                }];
                return;
            }
            payload[key] = [{
                value: value,
                marketplace_id: marketplaceId
            }];
        });
        ['item_name', 'product_description', 'bullet_point', 'care_instructions'].forEach(field => {
            if (nestedPayload[field]) {
                nestedPayload[field].language_tag = 'en_US';
                nestedPayload[field].marketplace_id = marketplaceId;
            }
        });
        if (nestedPayload.list_price) {
            nestedPayload.list_price.value = Number(nestedPayload.list_price.value || 0);
            nestedPayload.list_price.marketplace_id = marketplaceId;
        }
        if (nestedPayload.unit_count) {
            nestedPayload.unit_count.value = Number(nestedPayload.unit_count.value || 0);
            nestedPayload.unit_count.type = {
                value: 'Count',
                language_tag: 'en_US'
            };
            nestedPayload.unit_count.marketplace_id = marketplaceId;
        }
        if (nestedPayload.supplier_declared_has_product_identifier_exemption) {
            nestedPayload.supplier_declared_has_product_identifier_exemption.value =
                nestedPayload.supplier_declared_has_product_identifier_exemption.value === 'true';
        }
        if (nestedPayload.externally_assigned_product_identifier) {
            nestedPayload.externally_assigned_product_identifier.marketplace_id = marketplaceId;
        }
        if (nestedPayload.fulfillment_availability) {
            nestedPayload.fulfillment_availability.fulfillment_channel_code = 'DEFAULT';
            nestedPayload.fulfillment_availability.quantity = Number(
                nestedPayload.fulfillment_availability.quantity || 0
            );
        }
        if (nestedPayload.fulfillment_availability?.is_inventory_available) {
            nestedPayload.fulfillment_availability.is_inventory_available =
                nestedPayload.fulfillment_availability.is_inventory_available === true ||
                nestedPayload.fulfillment_availability.is_inventory_available === 'true';
        }
        if (nestedPayload.neck?.neck_style) {
            nestedPayload.neck.neck_style = [{
                value: nestedPayload.neck.neck_style,
                language_tag: 'en_US'
            }];
        }
        if (nestedPayload.sleeve?.type) {
            nestedPayload.sleeve.type = [{
                value: nestedPayload.sleeve.type,
                language_tag: 'en_US'
            }];
        }
        if (nestedPayload.sleeve?.cuff_style) {
            nestedPayload.sleeve.cuff_style = [{
                value: nestedPayload.sleeve.cuff_style,
                language_tag: 'en_US'
            }];
        }
        if (nestedPayload.rise?.style) {
            nestedPayload.rise.style = [{
                value: nestedPayload.rise.style,
                language_tag: 'en_US'
            }];
        }
        if (nestedPayload.number_of_items) {
            nestedPayload.number_of_items.value = Number(nestedPayload.number_of_items.value || 0);
            nestedPayload.number_of_items.marketplace_id = marketplaceId;
        }

        console.log(
            'Closure Type Before',
            nestedPayload.closure?.type
        );
        if (nestedPayload.closure?.type) {

            let closureValue = nestedPayload.closure.type;

            // Already wrapped?
            if (
                typeof closureValue === 'object' &&
                closureValue !== null &&
                'value' in closureValue
            ) {
                closureValue = closureValue.value;
            }

            nestedPayload.closure.type = [{
                value: closureValue,
                language_tag: 'en_US'
            }];
        }

        console.log(
            'Closure After',
            JSON.stringify(nestedPayload.closure, null, 2)
        );

        if (nestedPayload.outer?.material) {

            let material = nestedPayload.outer.material;

            if (
                typeof material === 'object' &&
                material !== null &&
                'value' in material
            ) {
                material = material.value;
            }

            nestedPayload.outer.material = [{
                value: material,
                language_tag: 'en_US'
            }];
        }
        if (nestedPayload.rise?.height) {
            nestedPayload.rise.height = [{
                value: Number(nestedPayload.rise.height),
                unit: 'in'
            }];
        }
        if (nestedPayload.sleeve?.length_description) {
            nestedPayload.sleeve.length_description = [{
                value: nestedPayload.sleeve.length_description
            }];
        }
        if (nestedPayload.special_size_type) {
            nestedPayload.special_size_type.language_tag = 'en_US';
            nestedPayload.special_size_type.marketplace_id = marketplaceId;
        }
        const marketplaceFields = [
            'item_name', 'brand', 'manufacturer', 'item_type_keyword',
            'product_description', 'bullet_point', 'target_gender', 'color',
            'fabric_type', 'fit_type', 'department', 'age_range_description',
            'style', 'country_of_origin', 'model_name', 'import_designation',
            'merchant_suggested_asin'
        ];
        marketplaceFields.forEach(field => {
            if (nestedPayload[field]) {
                nestedPayload[field].marketplace_id = marketplaceId;
            }
        });
        Object.entries(nestedPayload).forEach(([parent, values]) => {

            // Skip empty object
            if (
                values &&
                typeof values === 'object' &&
                !Array.isArray(values) &&
                Object.keys(values).length === 0
            ) {
                return;
            }

            payload[parent] = [values];
        });

        Object.keys(payload).forEach(key => {

            const value = payload[key];

            if (
                value &&
                typeof value === 'object' &&
                !Array.isArray(value) &&
                Object.keys(value).length === 0
            ) {
                delete payload[key];
            }

        });
        delete payload.compliance_chest_size;
        delete payload.compliance_warp_or_filling_coloring;
        delete payload.compliance_is_handmade;
        delete payload.variation_theme;
        applyConditionalSchemaRules(payload, nestedPayload);
        document.getElementById('payloadPreview').textContent = JSON.stringify(payload, null, 4);
        return payload;
    }

    function applyConditionalSchemaRules(payload, nestedPayload) {
        const shirtSize = nestedPayload.shirt_size || null;
        const sizeSystem = shirtSize?.size_system || '';
        const sizeClass = shirtSize?.size_class || '';
        const ageRange = nestedPayload.age_range_description?.value || '';
        const targetGender = nestedPayload.target_gender?.value || '';
        const fulfillment = nestedPayload.fulfillment_availability || null;
        const fulfillmentChannel = fulfillment?.fulfillment_channel_code || '';
        const inventoryAlways = fulfillment?.is_inventory_available === true ||
            fulfillment?.is_inventory_available === 'true';
        // const shirtHideKeys = [
        //     'shirt_size.height_type',
        //     'shirt_size.neck_size',
        //     'shirt_size.neck_size_to',
        //     'shirt_size.sleeve_length',
        //     'shirt_size.sleeve_length_to',
        //     'shirt_size.size_to'
        // ];
        // const shirtShowKeys = ['shirt_size.size'];
        // if (sizeSystem === 'as1' && sizeClass === 'alpha') {
        //     shirtHideKeys.forEach(key => {
        //         const field = key.replace('shirt_size.', '');
        //         setFieldVisible(`shirt_size.${field}`, false);
        //         clearFieldValue(`shirt_size.${field}`);
        //     });
        //     setFieldVisible('shirt_size.size', true);
        // }
        // if (sizeSystem === 'as1' && sizeClass === 'numeric') {
        //     setFieldVisible('shirt_size.size', true);
        //     setFieldVisible('shirt_size.size_to', false);
        //     clearFieldValue('shirt_size.size_to');
        //     setFieldVisible('shirt_size.neck_size', false);
        //     setFieldVisible('shirt_size.neck_size_to', false);
        //     setFieldVisible('shirt_size.sleeve_length', false);
        //     setFieldVisible('shirt_size.sleeve_length_to', false);
        //     setFieldVisible('shirt_size.height_type', false);
        //     clearFieldValue('shirt_size.neck_size');
        //     clearFieldValue('shirt_size.neck_size_to');
        //     clearFieldValue('shirt_size.sleeve_length');
        //     clearFieldValue('shirt_size.sleeve_length_to');
        //     clearFieldValue('shirt_size.height_type');
        // }
        if (fulfillmentChannel === 'DEFAULT') {
            if (inventoryAlways) {
                setFieldVisible('fulfillment_availability.quantity', false);
                setFieldVisible('fulfillment_availability.is_inventory_available', true);
                clearFieldValue('fulfillment_availability.quantity');
            } else {
                setFieldVisible('fulfillment_availability.quantity', true);
                setFieldVisible('fulfillment_availability.is_inventory_available', true);
            }
        }
        // Safety cleanup before submit
        // if (nestedPayload.shirt_size) {
        //     if (sizeClass === 'alpha') {
        //         delete nestedPayload.shirt_size.size_to;
        //         delete nestedPayload.shirt_size.height_type;
        //         delete nestedPayload.shirt_size.neck_size;
        //         delete nestedPayload.shirt_size.neck_size_to;
        //         delete nestedPayload.shirt_size.sleeve_length;
        //         delete nestedPayload.shirt_size.sleeve_length_to;
        //     }
        // }
        if (nestedPayload.fulfillment_availability) {
            if (inventoryAlways) {
                delete nestedPayload.fulfillment_availability.quantity;
            } else {
                delete nestedPayload.fulfillment_availability.is_inventory_available;
            }
        }
    }

    function getCurrentPayload() {
        try {
            return JSON.parse(
                document.getElementById('payloadPreview').textContent || '{}'
            );
        } catch (e) {
            return {};
        }
    }

    function renderAmazonErrors(
        errors = [],
        issues = []
    ) {
        const missingFields = [];
        document
            .querySelectorAll('.is-invalid')
            .forEach(el =>
                el.classList.remove(
                    'is-invalid'
                )
            );
        let html = '';
        (errors.length ? errors : issues)
        .forEach(error => {
            const field =
                error.field ||
                error.attributeNames?.[0];
            const message =
                error.message ||
                'Validation failed';
            html += `
<li>
    <strong>${message}</strong>
    ${
        error.suggestion
        ? `
        <br>
        <small class="text-dark">
            💡 ${error.suggestion}
        </small>
        `
        : ''
    }
</li>
`;
            if (field) {
                const exists =
                    document.querySelector(
                        `[data-key^="${field}"]`
                    );
                if (!exists) {
                    missingFields.push(field);
                }
                document
                    .querySelectorAll(
                        `[data-key^="${field}"]`
                    )
                    .forEach(el =>
                        el.classList.add(
                            'is-invalid'
                        )
                    );
            }
        });
        let box =
            document.getElementById(
                'amazonErrorBox'
            );
        if (!box) {
            box =
                document.createElement(
                    'div'
                );
            box.id =
                'amazonErrorBox';
            box.className =
                'alert alert-danger mt-3';
            document
                .querySelector(
                    '#payloadPreview'
                )
                .parentNode
                .insertBefore(
                    box,
                    document.getElementById(
                        'payloadPreview'
                    )
                );
        }
        box.innerHTML = `
                <h6>Amazon Validation Errors</h6>
                <ul>${html}</ul>
            `;
    }
    async function loadMissingFields(fields) {
        // Normalize indexed paths
        fields = fields.map(field =>
            field.replace(/\.\d+\./g, '.')
        );

        console.log('Loading Missing Fields', fields);
        const slug =
            document.getElementById(
                'subcategory'
            ).value;
        const response = await fetch(
            '/zeosync/amazon/load-missing-fields', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .content
                },
                body: JSON.stringify({
                    slug: slug,
                    fields: fields
                })
            }
        );
        const data =
            await response.json();
        console.log(
            'API FIELDS',
            data.fields
        );
        const missing =
            data.fields.filter(
                field =>
                fields.includes(
                    field.key
                )
            );
        console.log(
            'MISSING FILTER RESULT',
            missing
        );
        if (!missing.length) {
            console.log(
                'NO MATCH FOUND'
            );
            return;
        }
        appendMissingFields(
            missing
        );
    }

    function appendMissingFields(fields) {

        console.log('APPENDING FIELDS', fields);

        const wrapper = document.getElementById('requiredFields');

        let targetContainer = wrapper.querySelector('.row.g-4');

        if (!targetContainer) {
            wrapper.innerHTML = `
            <div class="panel">
                <div class="row g-4"></div>
            </div>
        `;
            targetContainer = wrapper.querySelector('.row.g-4');
        }

        fields.forEach(field => {

            // -------------------------
            // Skip if field already exists
            // -------------------------
            const exists = document.querySelector(
                `[data-key="${field.key}"], [data-key^="${field.key}."]`
            );

            if (exists) {
                console.log('Already rendered:', field.key);
                return;
            }

            let inputHtml = '';

            if (
                field.type === 'select' &&
                field.options &&
                field.options.length
            ) {

                inputHtml = `
                <select class="form-select dynamic-input"
                        data-key="${field.key}">
                    <option value="">Select</option>
                    ${field.options.map(option => `
                        <option value="${option.value ?? option}">
                            ${option.label ?? option}
                        </option>
                    `).join('')}
                </select>
            `;

            } else {

                inputHtml = `
                <input
                    type="text"
                    class="form-control dynamic-input"
                    data-key="${field.key}">
            `;

            }

            targetContainer.insertAdjacentHTML(
                'beforeend',
                `
            <div class="col-12 col-md-6 col-lg-6">
                <div class="mb-3 field-wrapper">
                    <label class="form-label">
                        ${field.label ?? field.name}
                    </label>

                    ${inputHtml}

                </div>
            </div>
            `
            );

            console.log('Appended:', field.key);

        });

        bindPayloadPreview();
    }

    function matchesRule(rule, payload) {
        if (!rule) {
            return true;
        }
        let result = true;
        if (rule.required) {
            result = result && rule.required.every(
                field => payload?.[field] !== undefined
            );
        }
        if (rule.properties) {
            result = result && Object.entries(
                rule.properties
            ).every(([key, value]) =>
                evaluateProperty(
                    value,
                    payload?.[key]
                )
            );
        }
        if (rule.allOf) {
            result = result && rule.allOf.every(r =>
                matchesRule(r, payload)
            );
        }
        if (rule.anyOf) {
            result = result && rule.anyOf.some(r =>
                matchesRule(r, payload)
            );
        }
        if (rule.not) {
            result = result && !matchesRule(
                rule.not,
                payload
            );
        }
        return result;
    }

    function getValue(obj, path) {
        return path.split('.')
            .reduce(
                (o, k) => o?.[k],
                obj
            );
    }

    function evaluateProperty(rule, actual) {
        if (!rule) {
            return true;
        }
        if (rule.allOf) {
            return rule.allOf.every(r =>
                matchesRule(r, actual)
            );
        }
        if (rule.anyOf) {
            return rule.anyOf.some(r =>
                matchesRule(r, actual)
            );
        }
        if (rule.not) {
            return !matchesRule(
                rule.not,
                actual
            );
        }
        if (rule.enum) {
            if (
                actual &&
                typeof actual === 'object' &&
                actual.value
            ) {
                return rule.enum.includes(
                    actual.value
                );
            }
            return rule.enum.includes(actual);
        }
        if (rule.contains) {
            if (!Array.isArray(actual)) {
                return false;
            }
            return actual.some(item =>
                matchesRule(
                    rule.contains,
                    item
                )
            );
        }
        if (rule.items) {
            if (!Array.isArray(actual)) {
                return false;
            }
            return actual.some(item =>
                matchesRule(
                    rule.items,
                    item
                )
            );
        }
        if (rule.properties) {
            return Object.entries(
                rule.properties
            ).every(([key, child]) =>
                evaluateProperty(
                    child,
                    actual?.[key]
                )
            );
        }
        return true;
    }
    $(document).on(
        'change',
        '.dynamic-input',
        function() {
            validateAmazonForm();
            evaluateConditions();
        }
    );

    function validateAmazonForm() {
        console.log('========================');
        console.log('VALIDATE AMAZON FORM');
        console.log('Current URL:', window.location.href);
        console.log('Validation URL:', window.validateRulesUrl);
        const payload = generatePayload();
        console.log('Selected Slug:', document.getElementById('subcategory')?.value);
        console.log('Generated Payload:', payload);
        $.ajax({
            url: window.validateRulesUrl,
            type: 'POST',
            data: {
                slug: document.getElementById('subcategory').value,
                payload: payload,
                _token: '{{ csrf_token() }}'
            },
            beforeSend: function() {
                console.log('AJAX REQUEST STARTED');
            },
            success: function(response) {
                console.log('AJAX SUCCESS');
                console.log('FULL RESPONSE:', response);
                console.log('ERROR COUNT:',
                    response.errors ? response.errors.length : 0
                );
                console.log('ERRORS ARRAY:',
                    response.errors
                );
                renderAmazonErrors(response.errors || []);
                toggleSubmit((response.errors || []).length === 0);
            },
            error: function(xhr, status, error) {
                console.log('AJAX ERROR');
                console.log('Status:', status);
                console.log('Error:', error);
                console.log('HTTP Status:', xhr.status);
                console.log('Response Text:', xhr.responseText);
                console.log('Response JSON:', xhr.responseJSON);
            },
            complete: function() {
                console.log('AJAX COMPLETED');
                console.log('========================');
            }
        });
    }

    function evaluateConditions() {
        console.log('========================');
        console.log('EVALUATE CONDITIONS');
        console.log('Current URL:', window.location.href);
        console.log('Evaluation URL:', window.evaluateConditionsUrl);

        const payload = generatePayload();

        console.log(
            'Selected Slug:',
            document.getElementById('subcategory')?.value
        );

        console.log('Generated Payload:', payload);

        $.ajax({
            url: window.evaluateConditionsUrl,
            type: 'POST',
            data: {
                slug: document.getElementById('subcategory').value,
                payload: payload,
                _token: '{{ csrf_token() }}'
            },

            beforeSend: function() {
                console.log('CONDITIONAL REQUEST STARTED');
            },

            success: function(response) {

                console.log('CONDITIONAL SUCCESS');
                console.log(response);

                if (!response.success || !response.state) {
                    return;
                }
                console.log('Required from backend:', response.state.required);

                // applyVisibility(response.state.visible);
                applyHidden(response.state.hidden);
                applyEnumChanges(response.state.enumChanges);
                // applyRequired(response.state.required);

                const state = response.state;

                const missingFields = state.required.filter(field => {

                    const exists = document.querySelector(
                        `[data-key="${field}"], [data-key^="${field}."]`
                    );

                    return !exists;

                });

                console.log('MISSING REQUIRED FIELDS', missingFields);

                if (missingFields.length) {
                    loadMissingFields(missingFields);
                }

                // UI manager integration (next step)
                // amazonUIState.updateState(state);
            },

            error: function(xhr, status, error) {

                console.log('CONDITIONAL ERROR');
                console.log('Status:', status);
                console.log('Error:', error);
                console.log(xhr.responseJSON);
            },

            complete: function() {
                console.log('CONDITIONAL COMPLETED');
                console.log('========================');
            }
        });
    }

    function toggleSubmit(valid) {
        $('#generatePayloadBtn')
            .prop('disabled', !valid);
    }

    function findFieldEnum(node, fieldName) {
        if (!node || typeof node !== 'object') {
            return null;
        }
        if (
            node.properties &&
            node.properties[fieldName] &&
            Array.isArray(
                node.properties[fieldName].enum
            )
        ) {
            return node.properties[fieldName].enum;
        }
        for (const value of Object.values(node)) {
            const result =
                findFieldEnum(
                    value,
                    fieldName
                );
            if (result) {
                return result;
            }
        }
        return null;
    }

    // function walkRule(
    //     rule,
    //     payload,
    //     fieldName
    // ) {
    //     if (!rule) {
    //         return null;
    //     }
    //     const matched =
    //         matchesRule(
    //             rule.if,
    //             payload
    //         );
    //     if (matched) {
    //         // direct property enum
    //         const enumValues =
    //             findFieldEnum(
    //                 rule.then,
    //                 fieldName
    //             );
    //         if (enumValues) {
    //             return enumValues;
    //         }
    //     }
    //     return walkRule(
    //         rule.else,
    //         payload,
    //         fieldName
    //     );
    // }

    // function getDynamicOptions(payload, fieldName) {
    //     for (const rule of window.amazonRules || []) {
    //         const result = walkRule(
    //             rule,
    //             payload,
    //             fieldName
    //         );
    //         if (
    //             Array.isArray(result) &&
    //             result.length
    //         ) {
    //             return result;
    //         }
    //     }
    //     return [];
    // }

    // function refreshDynamicField(fieldKey) {
    //     const payload =
    //         getCurrentPayload();
    //     const fieldName =
    //         fieldKey.split('.').pop();
    //     const options =
    //         getDynamicOptions(
    //             payload,
    //             fieldName
    //         );
    //     if (!options.length) {
    //         return;
    //     }
    //     const select =
    //         document.querySelector(
    //             `[data-key="${fieldKey}"]`
    //         );
    //     if (!select) {
    //         return;
    //     }
    //     const current =
    //         select.value;
    //     select.innerHTML =
    //         '<option value="">Select</option>';
    //     options.forEach(option => {
    //         select.innerHTML += `
    //                 <option value="${option}">
    //                     ${option}
    //                 </option>
    //             `;
    //     });
    //     if (
    //         options.includes(current)
    //     ) {
    //         select.value = current;
    //     }
    // }

    // function refreshDynamicDropdowns() {
    //     const payload =
    //         generatePayload();
    //     const bodyTypes =
    //         getDynamicOptions(
    //             payload,
    //             'body_type'
    //         );
    //     if (bodyTypes.length) {
    //         updateDropdown(
    //             'bottoms_size.body_type',
    //             bodyTypes
    //         );
    //     }
    //     const heightTypes =
    //         getDynamicOptions(
    //             payload,
    //             'height_type'
    //         );
    //     if (heightTypes.length) {
    //         updateDropdown(
    //             'bottoms_size.height_type',
    //             heightTypes
    //         );
    //     }
    // }
    // $(document).on(
    //     'change',
    //     '.dynamic-input',
    //     function() {
    //         refreshDynamicDropdowns();
    //     }
    // );

    // function updateDropdown(fieldKey, options) {
    //     const select = document.querySelector(
    //         `[data-key="${fieldKey}"]`
    //     );
    //     if (!select) {
    //         return;
    //     }
    //     const currentValue = select.value;
    //     select.innerHTML =
    //         '<option value="">Select</option>';
    //     options.forEach(option => {
    //         select.innerHTML += `
    //         <option value="${option}">
    //             ${option}
    //         </option>
    //     `;
    //     });
    //     if (
    //         options.includes(currentValue)
    //     ) {
    //         select.value = currentValue;
    //     }
    // }

    function getBodyTypeOptions(payload) {
        for (
            const rule of
                window.amazonRules || []
        ) {
            const result =
                walkRule(
                    rule,
                    payload,
                    'body_type'
                );
            if (result?.length) {
                return result;
            }
        }
        return [];
    }

    function refreshBodyTypeDropdown() {
        const payload =
            getCurrentPayload();
        const options =
            getBodyTypeOptions(payload);
        const select =
            document.querySelector(
                '[data-key="shirt_size.body_type"]'
            );
        if (!select) {
            return;
        }
        const current =
            select.value;
        select.innerHTML =
            '<option value="">Select</option>';
        options.forEach(option => {
            select.innerHTML += `
                        <option value="${option}">
                            ${option}
                        </option>
                    `;
        });
        if (
            options.includes(current)
        ) {
            select.value = current;
        }
    }

    function buildNode(schema, keyPrefix = '') {
        const result = {};
        if (schema.properties) {
            Object.entries(schema.properties).forEach(([key, child]) => {
                const fieldKey = keyPrefix ? keyPrefix + '.' + key : key;
                if (child.type === 'object') {
                    result[key] = buildNode(child, fieldKey);
                    return;
                }
                if (child.type === 'array' && child.items?.properties) {
                    result[key] = [
                        buildNode(child.items, fieldKey)
                    ];
                    return;
                }
                const input = document.querySelector(
                    `[data-key="${fieldKey}"]`
                );
                if (!input) return;
                let value =
                    input.type === 'checkbox' ?
                    input.checked :
                    input.value;
                if (value === '') return;
                result[key] = value;
            });
        }
        return result;
    }

    function applyVisibility(visibleFields) {

        console.log('=== APPLY VISIBILITY ===');
        console.log(visibleFields);

        if (!Array.isArray(visibleFields)) {
            return;
        }

        visibleFields.forEach(function(key) {

            const inputs = document.querySelectorAll(
                `[data-key="${key}"]`
            );

            if (!inputs.length) {
                console.warn('Field not found:', key);
                return;
            }

            inputs.forEach(function(input) {

                const wrapper = input.closest('.field-wrapper');

                if (!wrapper) {
                    return;
                }

                wrapper.classList.remove('d-none');
                wrapper.style.display = '';

                console.log('Visible:', key);

            });

        });

    }

    function applyHidden(hiddenFields) {

        console.log('=== APPLY HIDDEN ===');
        console.log(hiddenFields);

        if (!Array.isArray(hiddenFields)) {
            hiddenFields = [];
        }

        document.querySelectorAll('.dynamic-input').forEach(function(input) {

            const key = input.dataset.key;

            const wrapper = input.closest('.field-wrapper');

            if (!wrapper) {
                return;
            }

            const shouldHide = hiddenFields.some(function(hiddenKey) {

                return (
                    key === hiddenKey ||
                    key.startsWith(hiddenKey + '.')
                );

            });

            if (shouldHide) {

                wrapper.style.display = 'none';

                console.log('Hidden:', key);

            } else {

                wrapper.style.display = '';

                console.log('Visible:', key);

            }

        });

    }

    // function applyRequired(requiredFields) {

    //     console.log('=== APPLY REQUIRED ===');
    //     console.log(requiredFields);

    //     if (!Array.isArray(requiredFields)) {
    //         requiredFields = [];
    //     }

    //     document.querySelectorAll('.dynamic-input').forEach(function(input) {

    //         const key = input.dataset.key;
    //         const wrapper = input.closest('.field-wrapper');

    //         if (!wrapper) {
    //             return;
    //         }

    //         const label = wrapper.querySelector('.form-label');

    //         const isRequired = requiredFields.some(function(requiredKey) {
    //             return (
    //                 key === requiredKey ||
    //                 key.startsWith(requiredKey + '.')
    //             );
    //         });

    //         // HTML5 validation
    //         input.required = isRequired;

    //         if (!label) {
    //             return;
    //         }

    //         // Remove old required badge
    //         label.querySelectorAll('.required-indicator').forEach(function(el) {
    //             el.remove();
    //         });

    //         if (isRequired) {

    //             label.insertAdjacentHTML(
    //                 'beforeend',
    //                 `
    //             <span class="badge bg-danger ms-2 required-indicator">
    //                 Required
    //             </span>
    //             `
    //             );

    //             console.log('Required:', key);

    //         }

    //     });

    // }

    function applyEnumChanges(enumChanges) {

        console.log('=== APPLY ENUM CHANGES ===');
        console.log(enumChanges);

        if (!enumChanges || typeof enumChanges !== 'object') {
            return;
        }

        Object.entries(enumChanges).forEach(([fieldKey, options]) => {

            const select = document.querySelector(
                `[data-key="${fieldKey}"]`
            );

            if (!select) {
                console.warn('Enum field not found:', fieldKey);
                return;
            }

            if (select.tagName !== 'SELECT') {
                console.warn('Not a select:', fieldKey);
                return;
            }

            const currentValue = select.value;

            select.innerHTML = '<option value="">Select</option>';

            options.forEach(function(option) {

                const value =
                    typeof option === 'object' ?
                    (option.value ?? option.label) :
                    option;

                const label =
                    typeof option === 'object' ?
                    (option.label ?? option.value) :
                    option;

                select.insertAdjacentHTML(
                    'beforeend',
                    `<option value="${value}">${label}</option>`
                );

            });

            if (options.some(o => (o.value ?? o) === currentValue)) {
                select.value = currentValue;
            }

            console.log('Updated enum:', fieldKey);

        });

    }
</script>
@endpush