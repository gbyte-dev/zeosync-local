@extends('admin.layout.app')
@section('title', 'Shop Dashboard')
@section('content')
<style>
    .shop-dashboard {
        max-width: 1400px;
    }

    .hero-box {
        background: linear-gradient(135deg, #111827, #2563eb);
        color: #fff;
        border-radius: 24px;
        padding: 25px;
        margin-bottom: 24px;
        box-shadow: 0 18px 45px rgba(37, 99, 235, .18);
    }

    .hero-box h2 {
        font-weight: 800;
        margin-bottom: 6px;
    }

    .status-pill {
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 700;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #fff;
        border-radius: 20px;
        padding: 22px;
        border: 1px solid #c2c2c2;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stat-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .stat-label {
        color: #6b7280;
        font-size: 13px;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 800;
        color: #111827;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .pro-card {
        background: #fff;
        border-radius: 22px;
        border: 1px solid #eef2f7;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .pro-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #eef2f7;
        background: #fff;
    }

    .pro-card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        padding: 16px 24px;
        border-bottom: 1px solid #f1f5f9;
    }

    .info-row:last-child {
        border-bottom: 0;
    }

    .info-label {
        color: #6b7280;
        font-size: 13px;
        font-weight: 600;
    }

    .info-value {
        color: #111827;
        font-size: 14px;
        font-weight: 700;
        text-align: right;
        word-break: break-word;
    }

    .empty-box {
        padding: 40px 24px;
        text-align: center;
        color: #6b7280;
    }

    @media(max-width: 992px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width: 576px) {
        .shop-dashboard {
            padding: 14px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .hero-box {
            padding: 22px;
        }

        .info-row {
            flex-direction: column;
            gap: 4px;
        }

        .info-value {
            text-align: left;
        }
    }
</style>
<div class="shop-dashboard">
    {{-- Stats --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">📦</div>
            <div>
                <div class="stat-label">Total Products</div>
                <div class="stat-value">{{ $productCount }}</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">🛒</div>
            <div>
                <div class="stat-label">Total Orders</div>
                <div class="stat-value">{{ $orderCount }}</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">🔄</div>
            <div>
                <div class="stat-label">Sync Logs</div>
                <div class="stat-value">{{ $logCount }}</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">🔌</div>
            <div>
                <div class="stat-label">Amazon Status</div>
                <div class="stat-value" style="font-size:16px;">
                    {{ $shop->amazon_seller_id ? 'Connected' : 'Not Connected' }}
                </div>
            </div>
        </div>
    </div>
    {{-- Details --}}
    <div class="content-grid">
        {{-- Shop Info --}}
        <div class="pro-card">
            <div class="pro-card-header row">
                <h5 class="col-md-8"> Shop Information</h5>
                <button type="submit" class="btn btn-primary btn-sm col-md-4" onclick="document.getElementById('saveChangesBtn').click()">Save Changes</button>
            </div>
            <div class="info-row">
                <div class="info-label">Shop URL</div>
                <div class="info-value">{{ $shop->shop }}</div>
            </div>
            <div class="">
                <form action="{{ route('admin.shops.update', $shop->id) }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="info-row">
                                <div class="info-label"><label for="shop_name" class="form-label">Shop Name</label></div>
                                <div class="info-value">
                                    <input type="text" class="form-control" id="shop_name" name="shop_name" value="{{ old('shop_name', $shop->shop_name) }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-row">
                                <div class="info-label"><label for="email" class="form-label">Email Address</label></div>
                                <div class="info-value">
                                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $shop->email) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-none">
                        <button type="submit" class="btn btn-primary btn-sm" id="saveChangesBtn">Save Changes</button>
                    </div>
                </form>
            </div>
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    @if($shop->is_active)
                    <span class="badge bg-success">Active</span>
                    @else
                    <span class="badge bg-danger">Inactive</span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Amazon Seller ID</div>
                <div class="info-value">{{ $shop->amazon_seller_id ?? '—' }}</div>
            </div>
        </div>
        {{-- Subscription --}}
        <div class="pro-card">
            <div class="pro-card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"> Subscription Details</h5>
                <div class="d-flex align-items-center gap-2">
                @if(!$customPlan)
                    <button
                        type="button"
                        class="btn btn-dark btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#customEnterpriseModal">
                        Add Custom Plan
                    </button>
                @endif
                    @if($shop->subscription && $shop->subscription->status !== 'cancelled')
                    <form
                        action="{{ route('admin.shops.cancel', $shop->id) }}"
                        method="POST"
                        onsubmit="return confirm('Are you sure you want to cancel this subscription?')">
                        @csrf
                        <button
                            type="submit"
                            class="btn btn-danger btn-sm">
                            Cancel Subscription
                        </button>
                    </form>
                    @else
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#addPlanToShopModal">
                        Add Plan
                    </button>
                    @endif
                </div>
            </div>
            @if($shop->subscription)
            <div class="info-row">
                <div class="info-label">Plan ID</div>
                <div class="info-value">{{ $shop->subscription->plan_id?getPlanName($shop->subscription->plan_id) : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="badge bg-primary">
                        {{ ucfirst($shop->subscription->status) }}
                    </span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Price</div>
                <div class="info-value">
                    ${{ number_format($shop->subscription->price, 2) }}
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Billing Cycle</div>
                <div class="info-value">
                    {{ $shop->subscription->billing_cycle_months }} months
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Started At</div>
                <div class="info-value">
                    {{ optional($shop->subscription->started_at)->format('M d, Y') }}
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Ends At</div>
                <div class="info-value">
                    {{ optional($shop->subscription->ended_at)->format('M d, Y') }}
                </div>
            </div>
            @else
            <div class="empty-box">
                No active subscription found for this shop.
            </div>
            @endif
        </div>
    </div>
    {{-- System Info --}}
    <!-- <div class="pro-card mt-4">
        <div class="pro-card-header">
            <h5>🔐 System Information</h5>
        </div>
        <div class="info-row">
            <div class="info-label">Shop ID</div>
            <div class="info-value">#{{ $shop->id }}</div>
        </div>
    </div> -->
    @if($customPlan)

    <h5 class="mt-3" style="padding-left: 8px; padding-right: 8px;">
        Custom Plan Overview
    </h5>

    <div class="card mt-4">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Billing</th>
                        <th>Price</th>
                        <th>Limits</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>{{ $customPlan->name }}</td>
                        <td>Yearly</td>
                        <td>$999 / Year</td>
                        <td>
                            Products: Unlimited<br>
                            Sync: Unlimited
                        </td>
                        <td>
                            <span class="badge bg-success">Assigned</span>
                        </td>
                        <td class="text-center">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#customPlanDetailsModal">
                                <i class="bi bi-eye me-1"></i>
                                 Details
                            </button>

                            <form
                                action="{{ route('admin.plans.delete', $customPlan->id) }}"
                                method="POST"
                                class="d-inline"
                                onsubmit="return confirm('Are you sure you want to delete this custom plan?')">
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i>
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @else

    <h5 class="mt-3 " style="padding-left: 8px; padding-right: 8px;">
        Custom Plan Overview
    </h5>

    <div class="card mt-4">
        <div class="card-body text-center text-muted py-4">
            No custom plan assigned to this shop.
        </div>
    </div>

    @endif
</div>
<!-- Add Plan to Shop Modal -->
<div class="modal fade" id="addPlanToShopModal" tabindex="-1" aria-labelledby="addPlanToShopModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPlanToShopModalLabel">Assign Plan to Shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignPlanForm">
                <div class="modal-body">
                    <input type="hidden" name="shop_id" value="{{$shop->id}}">
                    <!-- Select Created Plan -->
                    <div class="mb-3">
                        <label for="planSelect" class="form-label font-weight-bold">Select Created Plan</label>
                        <select class="form-select" id="planSelect" required>
                            <option value="" selected disabled>Choose a plan...</option>
                            @foreach(getAllPlan() as $plandata)
                            <option value="{{$plandata->id}}">{{$plandata->name}} (${{$plandata->price}} / mo)</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Test Mode Checkbox (Optional) -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="testMode" checked />
                        <label class="form-check-label" for="testMode">
                            Enable Test Charge (Sandbox)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assignBtn">Assign Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('admin.plans.custom-enterprise-modal')

@if($customPlan)
@include('admin.plans.custom-plan-details-modal')
@endif
@endsection