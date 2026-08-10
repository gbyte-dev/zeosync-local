<style>
    .saas-modal-content {
        border-radius: 16px;
        overflow: hidden;
    }

    .saas-card {
        border-radius: 12px;
    }

    .feature-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .feature-input {
        flex: 1;
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

                <div class="modal-header px-4 py-3">
                    <h5 class="modal-title fw-bold mb-0" id="customEnterpriseModalLabel">Create Custom Enterprise Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-4 py-3">

                    <!-- 1. Shop Info -->
                    <div class="card border-0 bg-light rounded-3 mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center py-3 px-3">
                            <div class="text-truncate pe-3" title="{{ $shop->shop_name }} ({{ $shop->shop }})">
                                <div class="fw-bold text-dark">
                                    {{ $shop->shop_name }}
                                    <span class="text-muted small">({{ $shop->shop }})</span>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                Selected
                            </span>
                        </div>
                        <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                    </div>

                    <!-- 2. Billing -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <div class="text-uppercase fw-bold small text-secondary mb-0">Billing</div>
                                <div class="form-check form-switch d-flex align-items-center gap-2 m-0 ps-0">
                                    <input class="form-check-input m-0" type="checkbox" id="yearly_check" onchange="toggleBillingType()">
                                    <label class="form-check-label fw-semibold text-dark mb-0" for="yearly_check">
                                        Yearly Billing
                                    </label>
                                </div>
                            </div>

                            <div id="monthly_section">
                                <label class="form-label" for="monthly_price">Monthly Price</label>
                                <input type="number" step="0.01" name="prices[EVERY_30_DAYS]" id="monthly_price" class="form-control" placeholder="0.00">
                                <div class="small text-muted mt-2">Set the monthly plan amount for this enterprise package.</div>
                            </div>

                            <div id="yearly_section" class="d-none">
                                <label class="form-label" for="yearly_price">Yearly Price</label>
                                <input type="number" step="0.01" name="prices[ANNUAL]" id="yearly_price" class="form-control" placeholder="0.00" disabled>
                                <div class="small text-muted mt-2">Set the annual billing amount for this plan.</div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Features -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="text-uppercase fw-bold small text-secondary mb-0">Plan Features</div>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addFeature()">
                                    + Add Feature
                                </button>
                            </div>

                            <div id="features-wrapper">
                                <div class="feature-row">
                                    <input type="text" name="features[]" class="form-control feature-input" placeholder="Enter feature" required>
                                    <button type="button" class="btn btn-outline-danger btn-sm flex-shrink-0" onclick="removeFeature(this)">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Plan Limits & 5. AI Features -->
                    <div class="row g-3">
                        <!-- Limits -->
                        <div class="col-md-12">
                            <div class="card border-0 shadow-sm h-100 mb-0">
                                <div class="card-body p-3">
                                    <div class="text-uppercase fw-bold small text-secondary mb-3">Plan Limits</div>
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
                        </div>

                        <!-- AI Features -->
                        <div class="col-md-12">
                            <div class="card border-0 shadow-sm mb-0">
                                <div class="card-body p-3">
                                    <div class="text-uppercase fw-bold small text-secondary mb-3">AI Features</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center justify-content-between border rounded-3 p-3 bg-light">
                                                <strong class="text-dark small">AI AutoFill</strong>
                                                <div class="form-check form-switch m-0 ps-0">
                                                    <input type="checkbox" name="ai_autofill" value="1" class="form-check-input m-0 float-end">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center justify-content-between border rounded-3 p-3 bg-light">
                                                <strong class="text-dark small">AI Single Field</strong>
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
                </div>

                <!-- Footer -->
                <div class="modal-footer px-4 py-3 border-top-0 bg-white">
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
        <div class="feature-row">
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