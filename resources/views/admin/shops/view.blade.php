@extends('admin.layout.app')
@section('title', 'Shop Dashboard')
@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap');

    .shop-dashboard {
        --bg: #EEF0F2;
        --surface: #FFFFFF;
        --surface-2: #F7F8F9;
        --ink: #1A1D24;
        --ink-muted: #6B7280;
        --border: #DEE2E6;
        --accent: #2454C7;
        --accent-ink: #FFFFFF;
        --success: #197A56;
        --success-bg: #E6F4EC;
        --danger: #C33B3B;
        --danger-bg: #FBEAEA;
        --warning: #B4740E;
        --warning-bg: #FBF0DF;

        max-width: 1280px;
        font-family: 'Inter', system-ui, sans-serif;
        color: var(--ink);
    }

    .shop-dashboard .mono {
        font-family: 'IBM Plex Mono', ui-monospace, monospace;
    }

    /* ---------- Header ---------- */
    .dash-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        padding: 22px 0 20px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 22px;
    }

    .dash-header__name {
        font-size: 22px;
        font-weight: 600;
        line-height: 1.2;
        margin: 0 0 4px;
    }

    .dash-header__url {
        font-size: 13px;
        color: var(--ink-muted);
    }

    .dash-header__meta {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }

    .chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        font-weight: 500;
        padding: 4px 10px 4px 8px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink-muted);
    }

    .chip__dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .chip--on {
        color: var(--success);
        border-color: #CBE6D6;
        background: var(--success-bg);
    }

    .chip--on .chip__dot {
        background: var(--success);
    }

    .chip--off {
        color: var(--ink-muted);
    }

    .chip--off .chip__dot {
        background: #9CA3AF;
    }

    .btn-line {
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink);
        border-radius: 6px;
        padding: 7px 14px;
        font-size: 13.5px;
        font-weight: 500;
        transition: border-color .15s ease;
    }

    .btn-line:hover {
        border-color: #B9C0C9;
    }

    .btn-solid {
        border: 1px solid var(--accent);
        background: var(--accent);
        color: var(--accent-ink);
        border-radius: 6px;
        padding: 7px 14px;
        font-size: 13.5px;
        font-weight: 500;
    }

    .btn-solid:hover {
        background: #1E45AB;
        color: var(--accent-ink);
    }

    .btn-danger-line {
        border: 1px solid #E7B8B8;
        background: var(--surface);
        color: var(--danger);
        border-radius: 6px;
        padding: 7px 14px;
        font-size: 13.5px;
        font-weight: 500;
    }

    .btn-danger-line:hover {
        background: var(--danger-bg);
        color: var(--danger);
    }

    /* ---------- Stat strip ---------- */
    .stat-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        margin-bottom: 24px;
        overflow: hidden;
    }

    .stat {
        padding: 18px 22px;
        border-right: 1px solid var(--border);
    }

    .stat:last-child {
        border-right: none;
    }

    .stat__label {
        font-size: 12.5px;
        color: var(--ink-muted);
        margin-bottom: 8px;
    }

    .stat__value {
        font-size: 26px;
        font-weight: 600;
        line-height: 1;
        font-family: 'IBM Plex Mono', ui-monospace, monospace;
    }

    .stat__value--text {
        font-size: 15px;
        font-weight: 600;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* ---------- Panels ---------- */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
    }

    .panel__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
    }

    .panel__title {
        font-size: 14.5px;
        font-weight: 600;
        margin: 0;
    }

    .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--surface-2);
    }

    .row:last-child {
        border-bottom: none;
    }

    .row__label {
        font-size: 13px;
        color: var(--ink-muted);
        flex-shrink: 0;
    }

    .row__value {
        font-size: 13.5px;
        font-weight: 500;
        text-align: right;
        word-break: break-word;
    }

    .field-input {
        width: 100%;
        max-width: 240px;
        border: none;
        border-bottom: 1px solid var(--border);
        background: transparent;
        text-align: right;
        font-size: 13.5px;
        font-weight: 500;
        color: var(--ink);
        padding: 3px 0;
    }

    .field-input:focus {
        outline: none;
        border-bottom-color: var(--accent);
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        font-weight: 500;
        padding: 3px 9px;
        border-radius: 6px;
    }

    .badge-status--active {
        color: var(--success);
        background: var(--success-bg);
    }

    .badge-status--inactive {
        color: var(--danger);
        background: var(--danger-bg);
    }

    .badge-status--plan {
        color: var(--accent);
        background: #E9EFFC;
    }

    .empty-box {
        padding: 34px 20px;
        text-align: center;
        color: var(--ink-muted);
        font-size: 13.5px;
    }

    @media(max-width: 992px) {
        .stat-strip {
            grid-template-columns: repeat(2, 1fr);
        }

        .stat:nth-child(2) {
            border-right: none;
        }

        .stat:nth-child(1), .stat:nth-child(2) {
            border-bottom: 1px solid var(--border);
        }

        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width: 576px) {
        .dash-header {
            flex-direction: column;
        }

        .stat-strip {
            grid-template-columns: 1fr;
        }

        .stat {
            border-right: none;
            border-bottom: 1px solid var(--border);
        }

        .row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .row__value, .field-input {
            text-align: left;
        }
    }
</style>

<div class="shop-dashboard">

    {{-- Identity + status --}}
    <div class="dash-header">
        <div>
            <div class="dash-header__name">{{ $shop->shop_name }}</div>
            <div class="dash-header__url mono">{{ $shop->shop }}</div>
            <div class="dash-header__meta">
                @if($shop->is_active)
                <span class="chip chip--on"><span class="chip__dot"></span>Active</span>
                @else
                <span class="chip chip--off"><span class="chip__dot"></span>Inactive</span>
                @endif

                @if($shop->amazon_seller_id)
                <span class="chip chip--on"><span class="chip__dot"></span>Amazon connected</span>
                @else
                <span class="chip chip--off"><span class="chip__dot"></span>Amazon not connected</span>
                @endif
            </div>
        </div>
        <button type="button" class="btn-solid" onclick="document.getElementById('saveChangesBtn').click()">
            Save changes
        </button>
    </div>

    {{-- Stats --}}
    <div class="stat-strip">
        <div class="stat">
            <div class="stat__label">Total products</div>
            <div class="stat__value">{{ $productCount }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Total orders</div>
            <div class="stat__value">{{ $orderCount }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Sync logs</div>
            <div class="stat__value">{{ $logCount }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Amazon status</div>
            <div class="stat__value stat__value--text">
                {{ $shop->amazon_seller_id ? 'Connected' : 'Not connected' }}
            </div>
        </div>
    </div>

    <div class="content-grid">

        {{-- Shop info --}}
        <div class="panel">
            <div class="panel__header">
                <h5 class="panel__title">Shop information</h5>
            </div>
            <form action="{{ route('admin.shops.update', $shop->id) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="row__label">Shop URL</div>
                    <div class="row__value mono">{{ $shop->shop }}</div>
                </div>
                <div class="row">
                    <label for="shop_name" class="row__label">Shop name</label>
                    <input type="text" class="field-input" id="shop_name" name="shop_name"
                        value="{{ old('shop_name', $shop->shop_name) }}" required>
                </div>
                <div class="row">
                    <label for="email" class="row__label">Email address</label>
                    <input type="email" class="field-input" id="email" name="email"
                        value="{{ old('email', $shop->email) }}" required>
                </div>
                <button type="submit" class="d-none" id="saveChangesBtn">Save changes</button>
            </form>
            <div class="row">
                <div class="row__label">Status</div>
                <div class="row__value">
                    @if($shop->is_active)
                    <span class="badge-status badge-status--active">Active</span>
                    @else
                    <span class="badge-status badge-status--inactive">Inactive</span>
                    @endif
                </div>
            </div>
            <div class="row">
                <div class="row__label">Amazon seller ID</div>
                <div class="row__value mono">{{ $shop->amazon_seller_id ?? '—' }}</div>
            </div>
        </div>

        {{-- Subscription --}}
        <div class="panel">
            <div class="panel__header">
                <h5 class="panel__title">Subscription details</h5>

                @if($shop->subscription && $shop->subscription->status !== 'cancelled')
                <form action="{{ route('admin.shops.cancel', $shop->id) }}" method="POST" class="m-0"
                    onsubmit="return confirm('Are you sure you want to cancel this subscription?')">
                    @csrf
                    <button type="submit" class="btn-danger-line">Cancel subscription</button>
                </form>
                @else
                <button type="button" class="btn-line" data-bs-toggle="modal" data-bs-target="#addPlanToShopModal">
                    Add plan
                </button>
                @endif
            </div>

            @if($shop->subscription)
            <div class="row">
                <div class="row__label">Plan</div>
                <div class="row__value">
                    {{ $shop->subscription->plan_id ? getPlanName($shop->subscription->plan_id) : 'N/A' }}
                </div>
            </div>
            <div class="row">
                <div class="row__label">Status</div>
                <div class="row__value">
                    <span class="badge-status badge-status--plan">{{ ucfirst($shop->subscription->status) }}</span>
                </div>
            </div>
            <div class="row">
                <div class="row__label">Price</div>
                <div class="row__value mono">${{ number_format($shop->subscription->price, 2) }}</div>
            </div>
            <div class="row">
                <div class="row__label">Billing cycle</div>
                <div class="row__value">{{ $shop->subscription->billing_cycle_months }} months</div>
            </div>
            <div class="row">
                <div class="row__label">Started at</div>
                <div class="row__value mono">{{ optional($shop->subscription->started_at)->format('M d, Y') }}</div>
            </div>
            <div class="row">
                <div class="row__label">Ends at</div>
                <div class="row__value mono">{{ optional($shop->subscription->ended_at)->format('M d, Y') }}</div>
            </div>
            @else
            <div class="empty-box">No active subscription found for this shop.</div>
            @endif
        </div>
    </div>
</div>

{{-- Add Plan to Shop Modal --}}
<div class="modal fade" id="addPlanToShopModal" tabindex="-1" aria-labelledby="addPlanToShopModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPlanToShopModalLabel">Assign plan to shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignPlanForm">
                <div class="modal-body">
                    <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                    <div class="mb-3">
                        <label for="planSelect" class="form-label">Select a plan</label>
                        <select class="form-select" id="planSelect" required>
                            <option value="" selected disabled>Choose a plan...</option>
                            @foreach(getAllPlan() as $plandata)
                            <option value="{{ $plandata->id }}">{{ $plandata->name }} (${{ $plandata->price }} / mo)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="testMode" checked>
                        <label class="form-check-label" for="testMode">Enable test charge (sandbox)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assignBtn">Assign plan</button>
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