<style>
    /* SaaS Compact UI Styles - Synchronized with Dashboard Theme */
    .saas-modal-content {
        border-radius: 12px;
        /* Matched to dashboard cards */
        border: none;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.15);
        background-color: #f4f6f9;
        /* Matched to dashboard background */
    }

    .saas-modal-header {
        background-color: #ffffff;
        border-bottom: 1px solid #eef0f4;
        padding: 14px 18px;
        border-radius: 12px 12px 0 0;
    }

    .saas-modal-title {
        font-size: 15px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0;
    }

    .saas-modal-body {
        padding: 14px;
        background-color: transparent;
        /* Inherits from modal-content */
    }

    .saas-modal-footer {
        padding: 12px 18px;
        background-color: #ffffff;
        border-top: 1px solid #eef0f4;
        border-radius: 0 0 12px 12px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .section-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #eef0f4;
    }

    .form-label {
        font-weight: 600;
        color: #475569;
        font-size: 11px;
        margin-bottom: 2px;
    }

    .form-control,
    .select2-container .select2-selection--single {
        border-radius: 6px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 12px !important;
        height: 32px !important;
        padding: 4px 10px !important;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 22px !important;
        color: #1e293b !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 30px !important;
    }

    .form-control:focus {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1) !important;
        outline: none;
    }

    .billing-option {
        background: #ffffff;
        border: 1px solid #eef0f4;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 8px;
        transition: all 0.3s ease;
    }

    .billing-option:has(input:checked) {
        border-color: #0d6efd;
        background: #f8faff;
    }

    .switch-box {
        background: #f8fafc;
        border: 1px solid #eef0f4;
        border-radius: 8px;
        padding: 6px 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s ease;
    }

    .switch-box:hover {
        border-color: #cbd5e1;
    }

    .form-check-input {
        border: 1px solid #94a3b8;
        cursor: pointer;
        /* width: 28px; */
        height: 15px;
        margin-top: 0.1rem;
    }

    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .form-check-label {
        font-size: 12px;
    }

    .btn {
        height: 32px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
        padding: 0 14px;
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

    /* Primary button customized to match dashboard */
    .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
    }

    .saas-card {
        background: #ffffff;
        border-radius: 12px;
        /* Matched to dashboard cards */
        padding: 12px 14px;
        border: 1px solid #eef0f4;
        /* Softer border */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        margin-bottom: 12px;
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

                    <div class="saas-card">

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <div class="section-title mb-0 text-truncate pe-3 text-dark"
                                title="{{ $shop->shop_name }} ({{ $shop->shop }})">
                                {{ $shop->shop_name }}
                                <span class="text-muted fw-normal text-dark">
                                    ({{ $shop->shop }})
                                </span>
                            </div>

                            <span class="badge bg-success-subtle text-success border flex-shrink-0">
                                Selected
                            </span>

                        </div>

                        <input
                            type="hidden"
                            name="shop_id"
                            value="{{ $shop->id }}">

                    </div>

                    <!-- 2. Billing -->
                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="yearly_check"
                            onchange="toggleBillingType()">

                        <label class="form-check-label fw-semibold" for="yearly_check">
                            Enable Yearly Billing
                        </label>
                    </div>

                    <!-- Monthly -->
                    <div id="monthly_section">
                        <label class="form-label">Monthly Price</label>

                        <input
                            type="number"
                            step="0.01"
                            name="prices[EVERY_30_DAYS]"
                            id="monthly_price"
                            class="form-control"
                            placeholder="0.00">

                        <small class="text-muted">
                            Stripe Price ID will be generated automatically.
                        </small>
                    </div>

                    <!-- Yearly -->
                    <div id="yearly_section" class="d-none">
                        <label class="form-label">Yearly Price</label>

                        <input
                            type="number"
                            step="0.01"
                            name="prices[ANNUAL]"
                            id="yearly_price"
                            class="form-control"
                            placeholder="0.00"
                            disabled>

                        <small class="text-muted">
                            Stripe Price ID will be generated automatically.
                        </small>
                    </div>

                    <!-- 3. Plan Limits & 4. AI Features -->
                    <div class="row g-2">
                        <!-- Limits -->
                        <div class="col-md-12">
                            <div class="saas-card mb-0">
                                <div class="section-title">Plan Limits</div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Product Limit</label>
                                        <input type="number" name="product_limit" class="form-control" min="0" placeholder="e.g. 1000 or 0 for unlimited">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Product Sync Limit</label>
                                        <input type="number" name="sync_limit" class="form-control" min="0" placeholder="e.g. 50, 0 for unlimited">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI Features -->
                        <div class="col-md-12">
                            <div class="saas-card mb-0">
                                <div class="section-title">AI Features</div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="switch-box m-0">
                                            <strong class="text-dark" style="font-size: 11px;">AI AutoFill</strong>
                                            <div class="form-check form-switch m-0">
                                                <input type="checkbox" name="ai_autofill" value="1" class="form-check-input m-0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="switch-box m-0">
                                            <strong class="text-dark" style="font-size: 11px;">AI Single Field</strong>
                                            <div class="form-check form-switch m-0">
                                                <input type="checkbox" name="ai_single_field" value="1" class="form-check-input m-0">
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
        togglePrice('monthly');
        togglePrice('yearly');
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

    document.addEventListener('DOMContentLoaded', toggleBillingType);
</script>