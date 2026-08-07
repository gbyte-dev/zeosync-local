<style>
    /* SaaS Compact UI Styles - Synchronized with Dashboard Theme */
    .saas-modal-content {
        /* Exact font family requested */
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        border-radius: 12px;
        border: none;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.15);
        background-color: #f4f6f9;
    }

    .saas-modal-header {
        background-color: #ffffff;
        border-bottom: 1px solid #eef0f4;
        padding: 16px 20px;
        border-radius: 12px 12px 0 0;
    }

    .saas-modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0;
    }

    .saas-modal-body {
        padding: 16px;
        background-color: transparent;
    }

    .saas-modal-footer {
        padding: 14px 20px;
        background-color: #ffffff;
        border-top: 1px solid #eef0f4;
        border-radius: 0 0 12px 12px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .section-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: black;
        margin-bottom: 12px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eef0f4;
    }

    .form-label {
        font-weight: 600;
        color: black;
        font-size: 0.85rem;
        margin-bottom: 4px;
    }

    .form-control,
    .select2-container .select2-selection--single {
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 0.875rem !important;
        height: 38px !important; /* Increased for better mobile touch */
        padding: 6px 12px !important;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 26px !important;
        color: #1e293b !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }

    .form-control:focus {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1) !important;
        outline: none;
    }

    .switch-box {
        background: #f8fafc;
        border: 1px solid #eef0f4;
        border-radius: 8px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s ease;
        height: 100%;
    }

    .switch-box:hover {
        border-color: #cbd5e1;
    }

    .form-check-input {
        border: 1px solid #000000;
        cursor: pointer;
        height: 18px;
        width: 32px !important;
        margin-top: 0;
    }

    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .form-check-label {
        font-size: 0.875rem;
        cursor: pointer;
    }

    .btn {
        height: 38px; /* Matched with input height */
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .btn-light {
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
    }

    .btn-light:hover {
        background-color: #e2e8f0;
        color: #1e293b;
    }

    .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
    }

    .btn-danger {
        padding: 0 12px;
    }

    .saas-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #eef0f4;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        margin-bottom: 16px;
    }
</style>

<div class="modal fade" id="customEnterpriseModal" tabindex="-1" aria-labelledby="customEnterpriseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content saas-modal-content">
            <form method="POST" action="{{ route('custom-plans.store') }}" id="customEnterpriseForm">
                @csrf

                <!-- Hidden inputs managed automatically by backend constraints -->
                <input type="hidden" name="is_enterprise" value="1">
                <input type="hidden" name="is_trial" value="0">
                <input type="hidden" name="is_active" value="1">

                <div class="saas-modal-header d-flex justify-content-between align-items-center">
                    <h5 class="saas-modal-title" id="customEnterpriseModalLabel">Create Custom Enterprise Plan</h5>
                    <button type="button" class="btn-close btn-sm m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="saas-modal-body">

                    <!-- 1. Shop Info -->
                    <div class="saas-card py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-truncate pe-3 text-dark fw-bold" title="{{ $shop->shop_name }} ({{ $shop->shop }})">
                                {{ $shop->shop_name }}
                                <span class="  fw-normal">
                                    ({{ $shop->shop }})
                                </span>
                            </div>
                            <span class="badge bg-success-subtle text-success border flex-shrink-0">
                                Selected
                            </span>
                        </div>
                        <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                    </div>

                    <!-- 2. Billing -->
                    <div class="saas-card">
                        <div class="form-check form-switch d-flex align-items-center gap-2 mb-3 ps-0">
                            <input class="form-check-input m-0 ms-0" type="checkbox" id="yearly_check" onchange="toggleBillingType()">
                            <label class="form-check-label fw-semibold text-dark mb-0" for="yearly_check">
                                Enable Yearly Billing
                            </label>
                        </div>

                        <!-- Monthly -->
                        <div id="monthly_section">
                            <label class="form-label" for="monthly_price">Monthly Price</label>
                            <input type="number" step="0.01" name="prices[EVERY_30_DAYS]" id="monthly_price" class="form-control" placeholder="0.00">
                            <small class="  mt-1 d-block">Stripe Price ID will be generated automatically.</small>
                        </div>

                        <!-- Yearly -->
                        <div id="yearly_section" class="d-none">
                            <label class="form-label" for="yearly_price">Yearly Price</label>
                            <input type="number" step="0.01" name="prices[ANNUAL]" id="yearly_price" class="form-control" placeholder="0.00" disabled>
                            <small class="  mt-1 d-block">Stripe Price ID will be generated automatically.</small>
                        </div>
                    </div>

                    <!-- 3. Features -->
                    <div class="saas-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="section-title mb-0 border-0 pb-0">Plan Features</div>
                            <button type="button" class="btn btn-primary btn-sm" style="height: 32px;" onclick="addFeature()">
                                + Add Feature
                            </button>
                        </div>

                        <div id="features-wrapper">
                            <div class="d-flex gap-2 mb-2 feature-row">
                                <input type="text" name="features[]" class="form-control feature-input" placeholder="Enter feature" required>
                                <button type="button" class="btn btn-danger flex-shrink-0" onclick="removeFeature(this)">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Plan Limits & 5. AI Features -->
                    <div class="row g-3">
                        <!-- Limits -->
                        <div class="col-md-12">
                            <div class="saas-card mb-0 h-100">
                                <div class="section-title">Plan Limits</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Product Limit</label>
                                        <input type="number" name="product_limit" class="form-control" min="0" placeholder="e.g. 1000 (0 = unlimited)">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Product Sync Limit</label>
                                        <input type="number" name="sync_limit" class="form-control" min="0" placeholder="e.g. 50 (0 = unlimited)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI Features -->
                        <div class="col-md-12">
                            <div class="saas-card mb-0">
                                <div class="section-title">AI Features</div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="switch-box">
                                            <strong class="text-dark" style="font-size: 0.85rem;">AI AutoFill</strong>
                                            <div class="form-check form-switch m-0 ps-0">
                                                <input type="checkbox" name="ai_autofill" value="1" class="form-check-input m-0 float-end">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="switch-box">
                                            <strong class="text-dark" style="font-size: 0.85rem;">AI Single Field</strong>
                                            <div class="form-check form-switch m-0 ps-0">
                                                <input type="checkbox" name="ai_single_field" value="1" class="form-check-input m-0 float-end">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="saas-modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Select2 strictly inside the modal context
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            $('.select2-shop').select2({
                dropdownParent: $('#customEnterpriseModal'),
                width: '100%',
                placeholder: 'Search and select a shop...'
            });
        }

        // Setup initial states
        toggleBillingType();
    });

    // Toggle disabled state and dynamically hide/show inputs
    function toggleBillingType() {
        const yearly = document.getElementById('yearly_check').checked;

        document.getElementById('monthly_section').classList.toggle('d-none', yearly);
        document.getElementById('yearly_section').classList.toggle('d-none', !yearly);

        document.getElementById('monthly_price').disabled = yearly;
        document.getElementById('yearly_price').disabled = !yearly;

        if (yearly) {
            document.getElementById('monthly_price').value = '';
        } else {
            document.getElementById('yearly_price').value = '';
        }
    }

    function addFeature() {
        const html = `
        <div class="d-flex gap-2 mb-2 feature-row">
            <input type="text" name="features[]" class="form-control feature-input" placeholder="Enter feature" required>
            <button type="button" class="btn btn-danger flex-shrink-0" onclick="removeFeature(this)">✕</button>
        </div>
        `;

        document.getElementById('features-wrapper').insertAdjacentHTML('beforeend', html);
    }

    function removeFeature(btn) {
        const rows = document.querySelectorAll('#features-wrapper .feature-row');

        if (rows.length <= 1) {
            alert('At least one feature is required.');
            return;
        }

        btn.closest('.feature-row').remove();
    }

    document.getElementById('customEnterpriseForm').addEventListener('submit', function(e) {
        const inputs = document.querySelectorAll('.feature-input');
        let valid = false;

        inputs.forEach(input => {
            if (input.value.trim() !== '') {
                valid = true;
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please enter at least one plan feature.');
        }
    });
</script>