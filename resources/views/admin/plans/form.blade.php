<style>
    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e2e8f0;
        position: relative;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 60px;
        height: 2px;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    }

    .form-label {
        font-weight: 600;
        color: #475569;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .billing-option {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
    }

    .billing-option:has(input:checked) {
        border-color: #3b82f6;
        background: #f8faff;
    }

    .feature-row {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        align-items: center;
    }

    .switch-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }

    .switch-box:hover {
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .btn-soft {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        padding: 10px 14px;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-check-input {
        border: 1px solid;
        cursor: pointer;
    }

    .form-check-input:checked {
        background-color: #3b82f6;
        border-color: #3b82f6;
    }

    .card {
        border: none;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .text-gradient {
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    @media(max-width: 576px) {
        .feature-row {
            flex-direction: column;
        }

        .switch-box {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
    }
</style>

<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="card shadow-sm border-0  overflow-hidden">
        <div class="px-3 pt-2  text-dark shadow header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1"> {{ $type ?? 'Create Plan' }}</h5>
                    <p>Create and manage subscription plan details</p>
                </div>
                <a class="btn btn-primary btn-sm" href="{{ route('admin.plans') }}">
                    ← Back
                </a>
            </div>
        </div>

        <div class="card-body p-4">

            {{-- Basic Details --}}
            <div class="mb-4">
                <div class="section-title">Basic Plan Details</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="name" class="form-control"
                            value="{{ $plan->name ?? '' }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Badge</label>
                        <input type="text" name="badge" class="form-control"
                            value="{{ $plan->badge ?? '' }}"
                            placeholder="Popular / Best Value">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control"
                            placeholder="Write short plan description">{{ $plan->description ?? '' }}</textarea>
                    </div>
                </div>

                <input type="number" name="price" class="form-control d-none"
                    value="{{ $plan->price ?? '' }}" step="0.01">
            </div>

            {{-- Trial --}}
            <div class="mb-4">
                <div class="section-title">Trial Settings</div>
                <div class="row">
                    <div class="col-sm-8">
                        <div class="switch-box">
                            <div>
                                <strong class="text-dark">Trial Plan</strong>
                                <p class="text-muted small mb-0">Enable this if the plan is free trial only.</p>
                            </div>

                            <div class="form-check form-switch">
                                <input type="checkbox" id="is_trial" name="is_trial" value="1"
                                    class="form-check-input"
                                    onchange="toggleTrial()"
                                    {{ ($plan->is_trial ?? false) ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Trial Days</label>
                        <input type="number" name="trial_days" class="form-control"
                            value="{{ $plan->trial_days ?? 0 }}">
                    </div>
                </div>

            </div>

            {{-- Enterprises --}}

            <div class="mb-4">
                <div class="section-title">Enterprise Settings</div>

                <div class="switch-box">
                    <div>
                        <strong class="text-primary">Enterprise Plan</strong>
                        <p class="text-muted small mb-0">
                            Enable this if this plan requires users to contact the admin instead of subscribing directly.
                        </p>
                    </div>

                    <div class="form-check form-switch">
                        <input
                            type="checkbox"
                            id="is_enterprise"
                            name="is_enterprise"
                            value="1"
                            class="form-check-input"
                            onchange="toggleEnterprise()"
                            {{ old('is_enterprise', $plan->is_enterprise ?? false) ? 'checked' : '' }}>
                    </div>
                </div>

                <div class="mt-3" id="enterprise_button_section">
                    <label class="form-label">Contact Button Text</label>

                    <input
                        type="text"
                        name="contact_button_text"
                        class="form-control"
                        value="{{ old('contact_button_text', $plan->contact_button_text ?? 'Contact Admin') }}"
                        placeholder="Contact Admin">
                </div>
            </div>

            {{-- Recurring Fields --}}
            <div class="mb-4">

                <div id="billing_section">

                    {{-- Monthly --}}
                    <div class="billing-option">
                        <div class="form-check mb-3">
                            <input type="checkbox" id="monthly_check"
                                class="form-check-input"
                                onchange="togglePrice('monthly')"
                                {{ !($plan->is_trial ?? false) && isset($plan->prices['EVERY_30_DAYS']) ? 'checked' : '' }}>

                            <label class="form-check-label fw-bold text-primary" for="monthly_check">
                                Monthly Billing
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Monthly Price</label>
                                <input type="number" step="0.01"
                                    name="prices[EVERY_30_DAYS]"
                                    id="monthly_price"
                                    class="form-control"
                                    placeholder="0.00"
                                    value="{{ $plan->prices['EVERY_30_DAYS'] ?? '' }}">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Stripe Price ID</label>
                                <input type="text"
                                    name="stripe_price_ids[EVERY_30_DAYS]"
                                    id="monthly_stripe_price_id"
                                    class="form-control"
                                    placeholder="price_1234567890abcdef"
                                    value="{{ $plan->stripe_price_ids['EVERY_30_DAYS'] ?? '' }}">
                            </div>
                        </div>
                    </div>

                    {{-- Yearly --}}
                    <div class="billing-option">
                        <div class="form-check mb-3">
                            <input type="checkbox" id="yearly_check"
                                class="form-check-input"
                                onchange="togglePrice('yearly')"
                                {{ !($plan->is_trial ?? false) && isset($plan->prices['ANNUAL']) ? 'checked' : '' }}>

                            <label class="form-check-label fw-bold text-primary" for="yearly_check">
                                Yearly Billing
                            </label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Yearly Price</label>
                                <input type="number" step="0.01"
                                    name="prices[ANNUAL]"
                                    id="yearly_price"
                                    class="form-control"
                                    placeholder="0.00"
                                    value="{{ $plan->prices['ANNUAL'] ?? '' }}">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Stripe Price ID</label>
                                <input type="text"
                                    name="stripe_price_ids[ANNUAL]"
                                    id="yearly_stripe_price_id"
                                    class="form-control"
                                    placeholder="price_1234567890abcdef"
                                    value="{{ $plan->stripe_price_ids['ANNUAL'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Features --}}
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title mb-0">Plan Features</div>

                    <button type="button" class="btn btn-primary btn-sm" onclick="addFeature()">
                        + Add Feature
                    </button>
                </div>

                <div id="features-wrapper">
                    @php $i = 0; @endphp

                    @if(isset($plan) && $plan->features)
                    @foreach($plan->features as $feature)
                    <div class="feature-row">
                        <input type="text" name="features[]" class="form-control"
                            value="{{ $feature }}">

                        @if($i > 0)
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeFeature(this)">
                            ✕
                        </button>
                        @endif
                    </div>
                    @php $i++; @endphp
                    @endforeach
                    @else
                    <div class="feature-row">
                        <input type="text" name="features[]" class="form-control"
                            placeholder="Enter feature">

                        <button type="button" class="btn btn-danger btn-sm" onclick="removeFeature(this)">
                            ✕
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Limits --}}
            <div class="mb-4">
                <div class="section-title">Plan Limits</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Product Sync Limit</label>

                        <input type="number"
                            name="sync_limit"
                            class="form-control"
                            min="0"
                            value="{{ old('sync_limit', $plan->sync_limit ?? '') }}"
                            placeholder="e.g. 50">

                        <small class="text-muted">Enter 0 for Unlimited.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Product Limit</label>
                        <input type="number" name="product_limit" class="form-control"
                            min="0" value="{{ old('product_limit', $plan->product_limit ?? 0) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control"
                            value="{{ $plan->sort_order ?? 0 }}">
                    </div>
                </div>
            </div>

            {{-- AI Features --}}
            <div class="mb-4">
                <div class="section-title">AI Features</div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="switch-box">
                            <div>
                                <strong class="text-primary">AI AutoFill</strong>
                                <p class="text-muted small mb-0">Allow users on this plan to use AI AutoFill.</p>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" name="ai_autofill" value="1"
                                    class="form-check-input"
                                    {{ ($plan->ai_autofill ?? false) ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="switch-box">
                            <div>
                                <strong class="text-primary">AI Single Field</strong>
                                <p class="text-muted small mb-0">Allow users on this plan to generate individual fields using AI.</p>
                            </div>

                            <div class="form-check form-switch">
                                <input type="checkbox" name="ai_single_field" value="1"
                                    class="form-check-input"
                                    {{ ($plan->ai_single_field ?? false) ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Plan Visibility --}}
                <div class="mb-4">
                    <div class="section-title">Plan Visibility</div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="switch-box">
                                <div>
                                    <strong class="text-success">Active</strong>
                                    <p class="text-muted small mb-0">Show this plan to users.</p>
                                </div>

                                <div class="form-check form-switch">
                                    <input type="checkbox" name="is_active" value="1"
                                        class="form-check-input"
                                        {{ ($plan->is_active ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="switch-box">
                                <div>
                                    <strong class="text-warning">Highlighted</strong>
                                    <p class="text-muted small mb-0">Mark this plan as recommended.</p>
                                </div>

                                <div class="form-check form-switch">
                                    <input type="checkbox" name="is_highlighted" value="1"
                                        class="form-check-input"
                                        {{ ($plan->is_highlighted ?? false) ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <script>
            function addFeature() {
                let html = `
                <div class="feature-row">
                    <input type="text" name="features[]" class="form-control" placeholder="Enter feature">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeFeature(this)">✕</button>
                </div>
            `;
                document.getElementById('features-wrapper').insertAdjacentHTML('beforeend', html);
            }

            function removeFeature(btn) {
                btn.closest('.feature-row').remove();
            }

            function togglePrice(type) {
                if (type === 'monthly') {
                    let check = document.getElementById('monthly_check');
                    let input = document.getElementById('monthly_price');
                    let stripe = document.getElementById('monthly_stripe_price_id');

                    input.disabled = !check.checked;
                    stripe.disabled = !check.checked;

                    if (!check.checked) {
                        input.value = '';
                        stripe.value = '';
                    }
                }

                if (type === 'yearly') {
                    let check = document.getElementById('yearly_check');
                    let input = document.getElementById('yearly_price');
                    let stripe = document.getElementById('yearly_stripe_price_id');

                    input.disabled = !check.checked;
                    stripe.disabled = !check.checked;

                    if (!check.checked) {
                        input.value = '';
                        stripe.value = '';
                    }
                }
            }

            function toggleTrial() {

                const isTrial = document.getElementById('is_trial').checked;

                const billingSection = document.getElementById('billing_section');

                const monthlyCheck = document.getElementById('monthly_check');
                const yearlyCheck = document.getElementById('yearly_check');

                if (isTrial) {

                    monthlyCheck.checked = false;
                    yearlyCheck.checked = false;

                    togglePrice('monthly');
                    togglePrice('yearly');

                    billingSection.style.display = 'none';

                } else {

                    billingSection.style.display = 'block';

                    togglePrice('monthly');
                    togglePrice('yearly');
                }
            }

            function toggleEnterprise() {

                const isEnterprise = document.getElementById('is_enterprise').checked;

                const billingSection = document.getElementById('billing_section');
                const buttonSection = document.getElementById('enterprise_button_section');

                const monthlyCheck = document.getElementById('monthly_check');
                const yearlyCheck = document.getElementById('yearly_check');

                if (isEnterprise) {

                    // Uncheck billing options
                    monthlyCheck.checked = false;
                    yearlyCheck.checked = false;

                    // Clear & disable billing inputs
                    togglePrice('monthly');
                    togglePrice('yearly');

                    // Hide billing section
                    billingSection.style.display = 'none';

                } else {

                    // Show billing section again
                    billingSection.style.display = 'block';

                    // Keep inputs in correct state
                    togglePrice('monthly');
                    togglePrice('yearly');
                }

                buttonSection.style.display = isEnterprise ? 'block' : 'none';
            }

            document.addEventListener('DOMContentLoaded', function() {
                togglePrice('monthly');
                togglePrice('yearly');
                toggleTrial();
                toggleEnterprise();
            });
        </script>