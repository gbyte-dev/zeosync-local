<style>
    /* ===========================
   Custom Plan Details Modal
=========================== */

    #customPlanDetailsModal .modal-dialog {
        max-width: 980px;
    }

    #customPlanDetailsModal .modal-content {
        border: none;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0, 0, 0, .12);
    }

    #customPlanDetailsModal .modal-header {
        border-bottom: 1px solid #eef2f7;
        background: #fff;
    }

    #customPlanDetailsModal .modal-title {
        font-weight: 700;
        color: #1f2937;
    }

    #customPlanDetailsModal .modal-body {
        padding: 28px;
        background: #f8fafc;
    }

    #customPlanDetailsModal .modal-footer {
        border-top: 1px solid #eef2f7;
        padding: 18px 28px;
        background: #fff;
    }

    /* Cards */

    #customPlanDetailsModal .card {
        border: 1px solid #edf2f7;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .04);
        transition: .25s;
    }

    #customPlanDetailsModal .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(0, 0, 0, .08);
    }

    #customPlanDetailsModal .card-header {
        background: #fff;
        border-bottom: 1px solid #edf2f7;
        padding: 16px 22px;
    }

    #customPlanDetailsModal .card-header h5 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }

    #customPlanDetailsModal .card-body {
        padding: 20px 22px;
    }

    /* Tables */

    #customPlanDetailsModal table {
        margin: 0;
    }

    #customPlanDetailsModal table tr:last-child td,
    #customPlanDetailsModal table tr:last-child th {
        border-bottom: none;
    }

    #customPlanDetailsModal table th {
        width: 42%;
        font-weight: 600;
        color: #6b7280;
        border-bottom: 1px solid #f1f5f9;
    }

    #customPlanDetailsModal table td {
        color: #111827;
        font-weight: 600;
        border-bottom: 1px solid #f1f5f9;
    }

    /* Features */

    #customPlanDetailsModal .card-body .mb-2 {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        margin-bottom: 12px !important;
        border: 1px solid #edf2f7;
        border-radius: 10px;
        background: #fff;
        transition: .2s;
    }

    #customPlanDetailsModal .card-body .mb-2:hover {
        background: #f9fbff;
        border-color: #dbeafe;
    }

    #customPlanDetailsModal .bi-check-circle-fill {
        color: #16a34a !important;
        font-size: 18px;
    }

    /* Description */

    #customPlanDetailsModal .card-body p:last-child {
        margin-bottom: 0;
    }

    /* Badge */

    #customPlanDetailsModal .badge {
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }


    /* Close Button */

    #customPlanDetailsModal .btn-secondary {
        border-radius: 10px;
        padding: 9px 18px;
    }

    /* Empty */

    #customPlanDetailsModal .text-muted {
        color: #94a3b8 !important;
    }

    /* Responsive */

    @media (max-width:991px) {

        #customPlanDetailsModal .modal-body {
            padding: 20px;
        }

        #customPlanDetailsModal table th {
            width: 45%;
        }

    }

    @media (max-width:768px) {

        #customPlanDetailsModal .modal-header {
            padding: 18px;
        }

        #customPlanDetailsModal .modal-body {
            padding: 18px;
        }

        #customPlanDetailsModal .modal-footer {
            padding: 18px;
        }

        #customPlanDetailsModal table th,
        #customPlanDetailsModal table td {
            display: block;
            width: 100%;
        }

        #customPlanDetailsModal table tr {
            display: block;
            padding: 12px 0;
            border-bottom: 1px solid #edf2f7;
        }

    }
</style>

<div class="modal fade"
    id="customPlanDetailsModal"
    tabindex="-1"
    aria-labelledby="customPlanDetailsModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title mb-1" id="customPlanDetailsModalLabel">
                        {{ $customPlan->name }}
                    </h5>

                    <small class="text-muted">
                        Complete Custom Enterprise Plan Details
                    </small>
                </div>

                <div class="ms-auto d-flex align-items-center gap-3">

                    <span class="badge bg-success">
                        Assigned
                    </span>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

            </div>

            <div class="modal-body">

                @php

                $features = [];

                if(!empty($customPlan->features)){
                $features = is_array($customPlan->features)
                ? $customPlan->features
                : json_decode($customPlan->features,true);
                }

                $prices = is_array($customPlan->prices)
                ? $customPlan->prices
                : json_decode($customPlan->prices,true);

                $stripePriceIds = is_array($customPlan->stripe_price_ids)
                ? $customPlan->stripe_price_ids
                : json_decode($customPlan->stripe_price_ids,true);

                @endphp

                <div class="row g-4">

                    {{-- Plan Information --}}

                    <div class="col-lg-6">

                        <div class="card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Plan Information
                                </h5>

                            </div>

                            <div class="card-body">

                                <table class="table table-borderless mb-0">

                                    <tr>
                                        <th width="45%">Plan Name</th>
                                        <td>{{ $customPlan->name }}</td>
                                    </tr>

                                    <tr>
                                        <th>Billing</th>

                                        <td>

                                            @if(!empty($prices['EVERY_30_DAYS']))
                                            Monthly
                                            @elseif(!empty($prices['ANNUAL']))
                                            Yearly
                                            @else
                                            —
                                            @endif

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Price</th>

                                        <td>

                                            @if(!empty($prices['EVERY_30_DAYS']))
                                            ${{ number_format($prices['EVERY_30_DAYS'],2) }}/Month
                                            @elseif(!empty($prices['ANNUAL']))
                                            ${{ number_format($prices['ANNUAL'],2) }}/Year
                                            @else
                                            —
                                            @endif

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Product Limit</th>

                                        <td>

                                            {{ $customPlan->product_limit == 0 ? 'Unlimited' : $customPlan->product_limit }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Sync Limit</th>

                                        <td>

                                            {{ $customPlan->sync_limit == 0 ? 'Unlimited' : $customPlan->sync_limit }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Trial</th>

                                        <td>

                                            {{ $customPlan->is_trial ? 'Yes' : 'No' }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Trial Days</th>

                                        <td>

                                            {{ $customPlan->trial_days ?: '—' }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Enterprise</th>

                                        <td>

                                            {{ $customPlan->is_enterprise ? 'Yes' : 'No' }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>Contact Button</th>

                                        <td>

                                            {{ $customPlan->contact_button_text ?: '—' }}

                                        </td>

                                    </tr>
                                    <tr>
                                        <th>Plan Status</th>
                                        <td>
                                            @if($shop->subscription && $shop->subscription->status === 'active')
                                            <span class="badge bg-success">
                                                Active
                                            </span>
                                            @elseif($shop->subscription && $shop->subscription->status === 'cancelled')
                                            <span class="badge bg-danger">
                                                Cancelled
                                            </span>
                                            @elseif($shop->subscription)
                                            <span class="badge bg-warning text-dark">
                                                {{ ucfirst($shop->subscription->status) }}
                                            </span>
                                            @else
                                            <span class="badge bg-secondary">
                                                Not Assigned
                                            </span>
                                            @endif
                                        </td>
                                    </tr>

                                </table>

                            </div>

                        </div>

                    </div>

                    {{-- Features --}}

                    <div class="col-lg-6">

                        <div class="card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">

                                    Plan Features

                                </h5>

                            </div>

                            <div class="card-body">

                                @forelse($features as $feature)

                                <div class="mb-2">

                                    <i class="bi bi-check-circle-fill text-success me-2"></i>

                                    {{ $feature }}

                                </div>

                                @empty

                                <div class="text-muted">

                                    No Features Available

                                </div>

                                @endforelse

                            </div>

                        </div>

                    </div>

                    {{-- AI --}}

                    <div class="col-lg-6">

                        <div class="card">

                            <div class="card-header">

                                <h5 class="mb-0">

                                    AI Features

                                </h5>

                            </div>

                            <div class="card-body">

                                <table class="table table-borderless mb-0">

                                    <tr>

                                        <th width="45%">

                                            AI AutoFill

                                        </th>

                                        <td>

                                            {{ $customPlan->ai_autofill ? 'Enabled' : 'Disabled' }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>

                                            AI Single Field

                                        </th>

                                        <td>

                                            {{ $customPlan->ai_single_field ? 'Enabled' : 'Disabled' }}

                                        </td>

                                    </tr>

                                </table>

                            </div>

                        </div>

                    </div>

                    {{-- Stripe --}}

                    <div class="col-lg-6">

                        <div class="card">

                            <div class="card-header">

                                <h5 class="mb-0">

                                    Stripe Billing

                                </h5>

                            </div>

                            <div class="card-body">

                                <table class="table table-borderless mb-0">

                                    <tr>

                                        <th width="45%">

                                            Monthly Price ID

                                        </th>

                                        <td>

                                            {{ $stripePriceIds['EVERY_30_DAYS'] ?? '—' }}

                                        </td>

                                    </tr>

                                    <tr>

                                        <th>

                                            Yearly Price ID

                                        </th>

                                        <td>

                                            {{ $stripePriceIds['ANNUAL'] ?? '—' }}

                                        </td>

                                    </tr>

                                </table>

                            </div>

                        </div>

                    </div>

                    {{-- Description --}}

                    <div class="col-12">

                        <div class="card">

                            <div class="card-header">

                                <h5 class="mb-0">

                                    Description

                                </h5>

                            </div>

                            <div class="card-body">

                                {!! $customPlan->description ?: '<span class="text-muted">No Description</span>' !!}

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <small class="text-muted me-auto">

                    Created :
                    {{ optional($customPlan->created_at)->format('d M Y h:i A') }}

                    |

                    Updated :
                    {{ optional($customPlan->updated_at)->format('d M Y h:i A') }}

                </small>

                <button
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Close

                </button>

            </div>

        </div>

    </div>

</div>