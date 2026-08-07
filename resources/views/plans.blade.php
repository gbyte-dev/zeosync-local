@extends('layouts.app')

@section('content')

@php
$currentShop = $activeShop ?? request('shop') ?? session('active_shop');
$shop = \App\Models\Shop::where('shop', $currentShop)->first();
$shopLabel = $currentShop ?: 'your connected store';

$currentPlan = $subscription?->plan;
$rawInterval = $subscription?->billing_interval ?? 'EVERY_30_DAYS';
$selectedInterval = $rawInterval === 'ANNUAL' ? 12 : 1;

$statusValue = strtolower((string) ($subscription?->status ?? 'pending'));
$statusLabels = [
'pending' => 'Pending approval',
'active' => 'Active',
'accepted' => 'Active',
'cancelled' => 'Cancelled',
'declined' => 'Declined',
'expired' => 'Expired',
'frozen' => 'Frozen',
];
$subscriptionStatus = $statusLabels[$statusValue] ?? ucfirst($statusValue ?: 'Pending');

if (
$subscription?->is_trial == 1 &&
$subscription?->status === 'trialing' &&
$subscription?->trial_ends_at?->isFuture()
) {
$subscriptionStatus = 'Trialing';
}
@endphp

<!-- Keep existing CSS link just in case it has external dependencies -->
<link type="text/css" rel="stylesheet" href="{{ asset('css/plan.css') }}?v={{ time() }}" />

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
        margin: 0;
        padding: 0;
    }

    .saas-wrapper {
        /* max-width: 1180px; */
        /* margin: 0 auto; */
        padding: 12px 16px;
    }

    /* Page Header */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
    }

    .saas-page-subtitle {
        color: #6D7175;
        font-size: 12px;
        margin: 0;
    }

    /* Cards */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .saas-card-body {
        padding: 16px;
    }

    /* Header Grid (Hero & Status) */
    .saas-header-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    /* Hero Section */
    .saas-hero-section {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    textarea , input[type="text"] {
        font-size: small !important;
    }

    .saas-kicker {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #005BD3;
        margin-bottom: 8px;
        display: inline-block;
    }

    .saas-hero-copy {
        font-size: 13px;
        color: #4A4A4A;
        line-height: 1.5;
        margin-bottom: 12px;
    }

    .saas-hero-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }

    .saas-pill {
        background: #F4F6F8;
        border: 1px solid #E5E7EB;
        color: #4A4A4A;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 12px;
    }

    /* Status Section */
    .saas-status-section {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px;
    }

    .saas-status-header {
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #E5E7EB;
    }

    .saas-status-shop {
        font-size: 14px;
        font-weight: 650;
        color: #1A1A1A;
        margin-bottom: 4px;
    }

    .saas-status-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 12px;
        border-bottom: 1px dashed #E5E7EB;
    }

    .saas-status-row:last-child {
        border-bottom: none;
    }

    .saas-status-label {
        color: #6D7175;
        font-weight: 500;
    }

    .saas-status-value {
        color: #202223;
        font-weight: 650;
        text-align: right;
    }

    /* Plans Grid */
    .saas-plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .saas-plan-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        position: relative;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .saas-plan-card:hover {
        border-color: #C9CCCF;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.06);
    }

    .saas-plan-card.is-current {
        border: 2px solid #005BD3;
        box-shadow: 0 4px 12px rgba(0, 91, 211, 0.1);
    }

    .saas-plan-badge-active {
        position: absolute;
        top: 12px;
        right: 12px;
        background: #EBF5FA;
        color: #006FBB;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .saas-plan-name {
        font-size: 16px;
        font-weight: 650;
        color: #1A1A1A;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .saas-plan-badge {
        background: #F4F6F8;
        border: 1px solid #C9CCCF;
        color: #4A4A4A;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 650;
    }

    .saas-plan-desc {
        font-size: 12px;
        color: #6D7175;
        margin: 0 0 12px 0;
        line-height: 1.4;
        min-height: 34px;
    }

    .saas-plan-price {
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #E5E7EB;
    }

    .saas-plan-price-amount {
        font-size: 24px;
        font-weight: 750;
        color: #1A1A1A;
        line-height: 1;
    }

    .saas-plan-price-interval {
        font-size: 12px;
        color: #6D7175;
        font-weight: 500;
    }

    .saas-plan-list {
        list-style: none;
        padding: 0;
        margin: 0 0 16px 0;
        flex: 1;
    }

    .saas-plan-list li {
        font-size: 12px;
        color: #4A4A4A;
        padding: 4px 0;
        display: flex;
        align-items: flex-start;
        gap: 6px;
    }

    .saas-plan-list li::before {
        content: "✓";
        color: #008060;
        font-weight: bold;
    }

    /* Forms & Inputs */
    .saas-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 4px;
        color: #202223;
    }

    .saas-select {
        display: block;
        width: 100%;
        height: 34px;
        padding: 4px 10px;
        font-size: 13px;
        color: #202223;
        background-color: #FFFFFF;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%228%22%20viewBox%3D%220%200%2012%208%22%20fill%3D%22none%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M1.41%200.589966L6%205.16997L10.59%200.589966L12%201.99997L6%207.99997L0%201.99997L1.41%200.589966Z%22%20fill%3D%22%235C5F62%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 10px;
        outline: none;
    }

    .saas-select:focus {
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
    }

    /* Buttons */
    .saas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        height: 34px;
        width: 100%;
        text-align: center;
        text-decoration: none;
    }

    .saas-btn-primary {
        background-color: #1A1A1A;
        color: #FFFFFF;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .saas-btn-primary:hover {
        background-color: #333333;
        color: #FFFFFF;
    }

    .saas-btn-outline {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
    }

    .saas-btn-outline:hover {
        background-color: #F4F6F8;
    }

    .saas-btn-danger {
        background-color: #D82C0D;
        color: #FFFFFF;
    }

    .saas-btn-danger:hover {
        background-color: #B8250B;
        color: #FFFFFF;
    }

    .saas-btn-outline-danger {
        background-color: #FFFFFF;
        border-color: #FED3D1;
        color: #D82C0D;
    }

    .saas-btn-outline-danger:hover {
        background-color: #FFF5F5;
        border-color: #D82C0D;
    }

    .saas-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Banners / Alerts */
    .saas-banner {
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 12px;
        line-height: 1.4;
        margin-bottom: 12px;
        display: flex;
        gap: 8px;
        align-items: flex-start;
    }

    .saas-banner-warning {
        background-color: #FFF5EA;
        border: 1px solid #FFEA8A;
        color: #8A6116;
    }

    .saas-banner-danger {
        background-color: #FFF4F4;
        border: 1px solid #FED3D1;
        color: #8C1105;
    }

    .saas-banner-info {
        background-color: #EBF5FA;
        border: 1px solid #B4E1FA;
        color: #006FBB;
    }

    /* Modals */
    .saas-modal .modal-content {
        border: none;
        border-radius: 10px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .saas-modal .modal-header {
        border-bottom: 1px solid #E5E7EB;
        padding: 16px 20px;
    }

    .saas-modal .modal-title {
        font-size: 16px;
        font-weight: 650;
        color: #202223;
        margin: 0;
    }

    .saas-modal .modal-body {
        padding: 16px 20px;
        font-size: 13px;
        color: #4A4A4A;
    }

    .saas-modal .modal-footer {
        border-top: 1px solid #E5E7EB;
        padding: 12px 20px;
        background: #F9FAFB;
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
        gap: 8px;
    }

    .saas-modal .modal-footer .saas-btn {
        width: auto;
    }

    /* Payment Loader Styles */
    .payment-loader {
        position: fixed;
        inset: 0;
        display: none;
        justify-content: center;
        align-items: center;
        background: rgba(32, 34, 35, 0.7);
        backdrop-filter: blur(4px);
        z-index: 999999;
    }

    .payment-loader-card {
        background: #FFFFFF;
        border-radius: 12px;
        padding: 32px 24px;
        text-align: center;
        box-shadow: 0 24px 48px rgba(0, 0, 0, 0.15);
        max-width: 400px;
        width: 90%;
        animation: saas-popup 0.3s ease;
    }

    .loader-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #EBF5FA;
        color: #006FBB;
    }

    .loader-icon .spinner-border {
        width: 32px;
        height: 32px;
        border-width: 3px;
    }

    .loader-title {
        font-size: 18px;
        font-weight: 650;
        color: #1A1A1A;
        margin: 0 0 8px 0;
    }

    .loader-text {
        color: #6D7175;
        font-size: 13px;
        line-height: 1.5;
        margin: 0 0 16px 0;
    }

    .loader-note {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 10px;
        color: #4A4A4A;
        font-size: 12px;
        margin-bottom: 16px;
    }

    .loader-footer {
        font-size: 11px;
        color: #008060;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    @keyframes saas-popup {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Footnote */
    .saas-footnote {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 11px;
        color: #6D7175;
        line-height: 1.5;
        text-align: justify;
    }

    @media(max-width: 768px) {
        .saas-header-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Payment Loader Modal -->
<div id="paymentLoader" class="payment-loader">
    <div class="payment-loader-card">
        <div class="loader-icon">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <h3 class="loader-title">Payment Confirmation Pending</h3>
        <p class="loader-text">
            We've sent a secure payment link to your email.
            Complete the payment to activate your subscription.
        </p>
        <div class="loader-note">
            <i class="bi bi-envelope-check-fill text-primary me-1"></i>
            Please check your inbox (and spam folder if needed).
        </div>
        <div class="loader-footer">
            <i class="bi bi-shield-lock-fill"></i> Your payment is securely processed by Stripe.
        </div>
    </div>
</div>

<div class="saas-wrapper mt-3">

    {{-- Page Header --}}
    <div class="saas-page-header">
        <div>
            <h1 class="saas-page-title">Choose a plan to unlock app access </h1>
            <p class="saas-page-subtitle">Select the tier that best fits your business needs.</p>
        </div>
    </div>

    {{-- Hero & Status Grid --}}
    <div class="saas-header-grid">

        {{-- Hero Information --}}
        <div class="saas-hero-section">
            <div>
                <span class="saas-kicker"><i class="bi bi-shop me-1"></i> Shopify Managed Billing</span>
                <p class="saas-hero-copy">
                    Plans still come from your local database for <strong>{{ $shopLabel }}</strong>,
                    but checkout approval now happens through Shopify and the local record is synced from the live subscription state.
                </p>
                <div class="saas-hero-pills">
                    <span class="saas-pill">Plans from DB</span>
                    <span class="saas-pill">Per-store subscription</span>
                    <span class="saas-pill">Monthly or annual</span>
                </div>
            </div>

            @if(in_array($statusValue, ['active', 'accepted']))
            <div class="saas-banner saas-banner-warning mt-3 mb-0">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <strong>Important:</strong> Uninstalling this app will cancel your subscription immediately and no refund will be issued.
                    <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">This policy follows Shopify billing rules.</div>
                </div>
            </div>
            @endif
        </div>

        {{-- Status Card --}}
        <div class="saas-status-section">

            @if($subscription && $subscription->status === 'pending')
            <div id="paymentPendingBox" class="saas-banner saas-banner-warning fw-semibold">
                <i class="bi bi-hourglass-split"></i>
                <div>Payment pending. Please complete your payment to activate the plan.</div>
            </div>
            @endif

            <div class="saas-status-header">
                <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 650; letter-spacing: 0.5px;">Current Subscription</div>
                <div class="saas-status-shop">{{ $shopLabel }}</div>
                <div class="text-muted" style="font-size: 11px;">Pick a plan below to approve the recurring charge.</div>
            </div>

            <div class="saas-status-details mb-3">
                <div class="saas-status-row">
                    <span class="saas-status-label">Current plan</span>
                    <span class="saas-status-value">
                        @if($currentPlan)
                        @php
                        $displayPrice = $subscription?->billing_cycle_months == 12
                        ? ($currentPlan->prices['ANNUAL'] ?? 0)
                        : ($currentPlan->prices['EVERY_30_DAYS'] ?? 0);
                        @endphp
                        {{ $currentPlan->name }} (${{ number_format($displayPrice, 2) }})
                        @else
                        No active plan
                        @endif
                    </span>
                </div>
                <div class="saas-status-row">
                    <span class="saas-status-label">Billing status</span>
                    <span class="saas-status-value">{{ $subscriptionStatus }}</span>
                </div>
                <div class="saas-status-row">
                    <span class="saas-status-label">Billing interval</span>
                    <span class="saas-status-value">{{ $subscription?->billing_interval === 'ANNUAL' ? 'Annual' : ($subscription ? 'Monthly' : 'Not selected') }}</span>
                </div>
                <div class="saas-status-row">
                    <span class="saas-status-label">Trial ends</span>
                    <span class="saas-status-value">{{ $subscription?->trial_ends_at ? $subscription->trial_ends_at->format('d M Y') : 'After approval' }}</span>
                </div>
                <div class="saas-status-row">
                    <span class="saas-status-label">Period end</span>
                    <span class="saas-status-value">{{ $subscription?->current_period_end ? $subscription->current_period_end->format('d M Y') : 'Pending approval' }}</span>
                </div>
            </div>

            @if(in_array($statusValue, ['active', 'accepted']))
            <button type="button" class="saas-btn saas-btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelModal">
                Cancel Subscription
            </button>
            @endif

            @if ($subscription?->status === 'pending' && $subscription->shopify_confirmation_url)
            <a href="{{ $subscription->shopify_confirmation_url }}" class="saas-btn saas-btn-primary w-100 mt-2">
                Resume Shopify approval
            </a>
            @endif
        </div>

    </div>

    @php
    $planOrder = [
    'Starter' => 1,
    'Growth' => 2,
    'Scale' => 3,
    ];
    $currentPlanName = $currentPlan?->name ?? null;

    $plansCollection = collect($plans);
    $trialPlans = $plansCollection->filter(fn($p) => $p->is_trial == 1);
    $regularPlans = $plansCollection->filter(fn($p) => $p->is_trial != 1);
    $displayPlans = $trialPlans->isNotEmpty() ? $trialPlans->concat($regularPlans) : $plans;
    @endphp

    {{-- Plans Grid --}}
    <div class="saas-plans-grid">

        @if(!empty($customPlan))
        @php
        $plan = $customPlan;

        $isCurrentPlan = $subscription
        && $subscription->plan_id === $plan->id
        && in_array($statusValue, ['active', 'accepted'], true);

        $hasNoPlan = !$subscription || $subscription->plan_id == 0 || !in_array($statusValue, ['active', 'accepted']);

        if ($plan->is_trial) {
            $buttonText = 'Claim Free Trial';
        } else {
            $buttonText = 'Subscribe';
        }

        if (!$hasNoPlan && $isCurrentPlan) {
            $buttonText = 'Active';
        }
        @endphp

        {{-- CUSTOM PLAN CARD START --}}
        <div class="saas-plan-card {{ $isCurrentPlan ? 'is-current' : '' }}">

            @if($isCurrentPlan)
            <span class="saas-plan-badge-active">Active Plan</span>
            @endif

            <h2 class="saas-plan-name">
                {{ $plan->name }}
                @if ($plan->badge)
                <span class="saas-plan-badge">{{ $plan->badge }}</span>
                @endif
            </h2>

            <p class="saas-plan-desc">{{ $plan->description }}</p>

            @php
            $month_price = $plan->prices['EVERY_30_DAYS'] ?? 0;
            $yearly_price = $plan->prices['ANNUAL'] ?? 0;
            @endphp

            <div class="saas-plan-price">

                @if($plan->is_enterprise && empty($plan->is_custom))

                <span class="saas-plan-price-amount">Custom</span>
                <span class="saas-plan-price-interval">Contact our team</span>

                @elseif($plan->is_trial)

                <span class="saas-plan-price-amount text-success">Free</span>

                @else

                @if($month_price != 0)
                <span class="saas-plan-price-amount">${{ number_format((float) $month_price, 0) }}</span>
                <span class="saas-plan-price-interval">/ month</span>
                @endif

                @if($yearly_price != 0 && $month_price != 0)
                <div class="saas-plan-price-interval mt-1">
                    ${{ number_format((float) $yearly_price, 0) }} / year
                </div>
                @elseif($yearly_price != 0)
                <span class="saas-plan-price-amount">${{ number_format((float) $yearly_price, 0) }}</span>
                <span class="saas-plan-price-interval">/ year</span>
                @endif

                @endif

            </div>

            <ul class="saas-plan-list">
                @foreach (($plan->features ?? []) as $feature)
                <li>{{ $feature }}</li>
                @endforeach
            </ul>

            <div class="saas-plan-footer mt-auto pt-3 border-top" style="border-color: #E5E7EB;">
                <form method="POST" action="{{ route('plans.subscribe', $currentShop ? ['shop' => $currentShop] : []) }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">

                    @if ($currentShop)
                    <input type="hidden" name="shop" value="{{ $currentShop }}">
                    @endif

                    @if($plan->is_trial)

                    <div class="mb-3">
                        <label class="saas-label">Plan Type</label>
                        <div class="saas-banner saas-banner-info m-0 fw-bold" style="padding: 8px 10px;">
                            Trial Plan (Free)
                        </div>
                    </div>

                    @elseif($plan->is_enterprise && empty($plan->is_custom))

                    <div class="mb-3">
                        <label class="saas-label">Plan Type</label>
                        <div class="saas-banner saas-banner-info m-0 fw-bold" style="padding: 8px 10px;">
                            Enterprise Plan
                        </div>
                    </div>

                    @else

                    <div class="mb-3">
                        <label class="saas-label">Billing Interval</label>

                        <select
                            name="billing_interval"
                            id="billing_interval_{{ $plan->id }}"
                            class="saas-select">

                            @foreach($plan->prices as $interval => $price)

                            @php
                            if($interval == 'EVERY_30_DAYS'){
                            $months = 1;
                            $label = 'Monthly';
                            $description = 'Billed every 30 days';
                            } else {
                            $months = 12;
                            $label = 'Annual';
                            $description = 'Billed every 365 days';
                            }
                            @endphp

                            <option
                                value="{{ $months }}"
                                {{ $isCurrentPlan && (int)$selectedInterval === (int)$months ? 'selected' : '' }}>
                                {{ $label }} · {{ $description }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                    @endif

                    @php
                    $hasUsedTrial = $subscription && $subscription->trial_used == 1;
                    $isTrialActive = $subscription
                    && $subscription->is_trial == 1
                    && $subscription->status === 'trialing'
                    && $subscription->trial_ends_at
                    && now()->lt($subscription->trial_ends_at);
                    @endphp

                    @if($plan->is_trial)

                    @if($isTrialActive)
                    <button type="button" class="saas-btn saas-btn-primary" disabled>
                        Trial Active
                    </button>

                    @elseif($hasUsedTrial)
                    <button type="button" class="saas-btn saas-btn-outline" disabled>
                        Trial Expired
                    </button>

                    @else
                    <button type="submit" class="saas-btn saas-btn-primary">
                        Claim Free Trial
                    </button>
                    @endif

                    @elseif($plan->is_enterprise && empty($plan->is_custom))

                    <button
                        type="button"
                        class="saas-btn saas-btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#enterpriseModal"
                        data-plan-id="{{ $plan->id }}"
                        data-plan-name="{{ $plan->name }}">
                        {{ $plan->contact_button_text ?: 'Contact Admin' }}
                    </button>

                    @else

                    <button
                        type="submit"
                        class="saas-btn {{ $isCurrentPlan ? 'saas-btn-outline' : 'saas-btn-primary' }}"
                        {{ $isCurrentPlan ? 'disabled' : '' }}>
                        {{ $buttonText }}
                    </button>

                    @endif
                </form>
            </div>
        </div>
        @endif

        @forelse ($displayPlans as $plan)

        @if($plan->is_trial && $subscription && $subscription->trial_used == 1)
        @continue
        @endif

        @php
        $isCurrentPlan = $subscription
        && $subscription->plan_id === $plan->id
        && in_array($statusValue, ['active', 'accepted'], true);

        $currentLevel = $planOrder[$currentPlanName] ?? 0;
        $planLevel = $planOrder[$plan->name] ?? 0;
        $hasNoPlan = !$subscription || $subscription->plan_id == 0 || !in_array($statusValue, ['active', 'accepted']);

        if ($plan->is_trial) {
        $buttonText = 'Claim Free Trial';
        } else {
        $buttonText = 'Choose ' . $plan->name;
        }

        if (!$hasNoPlan) {
        if ($isCurrentPlan) {
        $buttonText = 'Active';
        } else {
        if ($planLevel > $currentLevel) {
        $buttonText = 'Upgrade';
        } elseif ($planLevel < $currentLevel) {
            $buttonText='Downgrade' ;
            }
            }
            }
            @endphp

            <div class="saas-plan-card {{ $isCurrentPlan ? 'is-current' : '' }}">

            @if($isCurrentPlan)
            <span class="saas-plan-badge-active">Active Plan</span>
            @endif

            <h2 class="saas-plan-name">
                {{ $plan->name }}
                @if ($plan->badge)
                <span class="saas-plan-badge">{{ $plan->badge }}</span>
                @endif
            </h2>

            <p class="saas-plan-desc">{{ $plan->description }}</p>

            @php
            $month_price = $plan->prices['EVERY_30_DAYS'] ?? 0;
            $yearly_price = $plan->prices['ANNUAL'] ?? 0;
            @endphp

            <div class="saas-plan-price">

                @if($plan->is_enterprise && empty($plan->is_custom))

                <span class="saas-plan-price-amount">Custom</span>
                <span class="saas-plan-price-interval">Contact our team</span>

                @elseif($plan->is_trial)

                <span class="saas-plan-price-amount text-success">Free</span>

                @else

                @if($month_price != 0)
                <span class="saas-plan-price-amount">${{ number_format((float) $month_price, 0) }}</span>
                <span class="saas-plan-price-interval">/ month</span>
                @endif

                @if($yearly_price != 0 && $month_price != 0)
                <div class="saas-plan-price-interval mt-1">
                    ${{ number_format((float) $yearly_price, 0) }} / year
                </div>
                @elseif($yearly_price != 0)
                <span class="saas-plan-price-amount">${{ number_format((float) $yearly_price, 0) }}</span>
                <span class="saas-plan-price-interval">/ year</span>
                @endif

                @endif

            </div>

            <ul class="saas-plan-list">
                @foreach (($plan->features ?? []) as $feature)
                <li>{{ $feature }}</li>
                @endforeach
            </ul>

            <div class="saas-plan-footer mt-auto pt-3 border-top" style="border-color: #E5E7EB;">
                <form method="POST" action="{{ route('plans.subscribe', $currentShop ? ['shop' => $currentShop] : []) }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">

                    @if ($currentShop)
                    <input type="hidden" name="shop" value="{{ $currentShop }}">
                    @endif

                    @if($plan->is_trial)

                    <div class="mb-3">
                        <label class="saas-label">Plan Type</label>
                        <div class="saas-banner saas-banner-info m-0 fw-bold" style="padding: 8px 10px;">
                            Trial Plan (Free)
                        </div>
                    </div>

                    @elseif($plan->is_enterprise && empty($plan->is_custom))

                    <div class="mb-3">
                        <label class="saas-label">Plan Type</label>
                        <div class="saas-banner saas-banner-info m-0 fw-bold" style="padding: 8px 10px;">
                            Enterprise Plan
                        </div>
                    </div>

                    @else

                    <div class="mb-3">
                        <label class="saas-label">Billing Interval</label>

                        <select
                            name="billing_interval"
                            id="billing_interval_{{ $plan->id }}"
                            class="saas-select">

                            @foreach($plan->prices as $interval => $price)

                            @php
                            if($interval == 'EVERY_30_DAYS'){
                            $months = 1;
                            $label = 'Monthly';
                            $description = 'Billed every 30 days';
                            } else {
                            $months = 12;
                            $label = 'Annual';
                            $description = 'Billed every 365 days';
                            }
                            @endphp

                            <option
                                value="{{ $months }}"
                                {{ $isCurrentPlan && (int)$selectedInterval === (int)$months ? 'selected' : '' }}>
                                {{ $label }} · {{ $description }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                    @endif

                    @php
                    $hasUsedTrial = $subscription && $subscription->trial_used == 1;
                    $isTrialActive = $subscription
                    && $subscription->is_trial == 1
                    && $subscription->status === 'trialing'
                    && $subscription->trial_ends_at
                    && now()->lt($subscription->trial_ends_at);
                    @endphp

                    @if($plan->is_trial)

                    @if($isTrialActive)
                    <button type="button" class="saas-btn saas-btn-primary" disabled>
                        Trial Active
                    </button>

                    @elseif($hasUsedTrial)
                    <button type="button" class="saas-btn saas-btn-outline" disabled>
                        Trial Expired
                    </button>

                    @else
                    <button type="submit" class="saas-btn saas-btn-primary">
                        Claim Free Trial
                    </button>
                    @endif

                    @elseif($plan->is_enterprise && empty($plan->is_custom))

                    <button
                        type="button"
                        class="saas-btn saas-btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#enterpriseModal"
                        data-plan-id="{{ $plan->id }}"
                        data-plan-name="{{ $plan->name }}">
                        {{ $plan->contact_button_text ?: 'Contact Admin' }}
                    </button>

                    @else

                    <button
                        type="submit"
                        class="saas-btn {{ $isCurrentPlan ? 'saas-btn-outline' : 'saas-btn-primary' }}"
                        {{ $isCurrentPlan ? 'disabled' : '' }}>
                        {{ $buttonText }}
                    </button>

                    @endif
                </form>
            </div>
        </div>
        @empty
        <div class="saas-card p-4 text-center w-100">
            <h2 class="saas-plan-name justify-content-center">No plans found</h2>
            <p class="saas-plan-desc m-0">Run the latest migrations so the plans table can be created and populated.</p>
        </div>
        @endforelse
    </div>

    {{-- Footnote --}}
    <div class="saas-footnote mb-3">
        <strong class="text-dark">Billing note:</strong> By subscribing, you agree to recurring charges based on the selected plan. Charges are processed securely through Shopify. Your subscription will automatically renew unless canceled before the billing cycle ends. You can manage or cancel your subscription anytime from your Shopify admin dashboard. No refunds will be issued for partial billing periods.
    </div>

</div>

<!-- Enterprise Enquiry Modal -->
<div class="modal fade saas-modal" id="enterpriseModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="{{ route('contact.store') }}">
                @csrf

                <input type="hidden"
                    name="enquiry_type"
                    value="enterprise_plan_enquiry">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Contact Admin for Enterprise Plan
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">  Full Name </label>

                            <input type="text" name="name" class="form-control"
                                value="{{ old('name', $shop->shop_name ?? '') }}"
                                required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">  Email Address </label>

                            <input  type="email" name="email" class="form-control"
                                value="{{ old('email', $shop->email ?? '') }}"
                                required>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">  Subject </label>
                            <input type="text"  name="subject" class="form-control"
                                value="{{ old('subject') }}"
                                placeholder="Example: Need higher product and sync limits"
                                required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">  Describe Your Requirements </label>
                            <textarea  name="message" rows="5" class="form-control"
                                placeholder="Describe your enterprise requirements, such as higher product limits, sync limits, mapping limits, dedicated support, custom integrations, or any other business requirements."
                                required>{{ old('message') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="saas-btn saas-btn-outline"
                        data-bs-dismiss="modal">  Cancel </button>

                    <button  type="submit"  class="saas-btn saas-btn-primary">
                        Send Request
                    </button>

                </div>
            </form>
        </div>
    </div>
</div>

{{-- Cancel Modal --}}
<div class="modal fade saas-modal" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i> Cancel Subscription</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2 text-dark fw-semibold">If you cancel your subscription:</p>
                <ul class="mb-0 ps-3">
                    <li class="mb-1">No refund will be issued.</li>
                    <li>Your access will stop immediately.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="saas-btn saas-btn-outline" data-bs-dismiss="modal">Keep Plan</button>
                <form method="POST" action="{{ route('plans.cancel') }}" class="m-0">
                    @csrf
                    <input type="hidden" name="shop" value="{{ $currentShop }}">
                    <button type="submit" class="saas-btn saas-btn-danger">Confirm Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Polling Script --}}
@if(session('success') && str_contains(session('success'), 'activation initiated'))
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let loader = document.getElementById('paymentLoader');
        let pendingBox = document.getElementById('paymentPendingBox');
        if (!loader) return;

        loader.style.display = 'flex';
        if (pendingBox) pendingBox.style.display = 'none';

        let shop = "{{ request('shop') }}";
        if (!shop) return;

        let startTime = Date.now();
        let interval = setInterval(() => {
            fetch(`{{ route('payment.status') }}?shop=${shop}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'active' && data.payment_status === 'paid') {
                        clearInterval(interval);
                        loader.style.display = 'none';
                        if (pendingBox) pendingBox.style.display = 'none';
                        location.reload();
                    }
                    if (Date.now() - startTime > 300000) {
                        clearInterval(interval);
                        loader.style.display = 'none';
                        if (pendingBox) pendingBox.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Polling error:', err);
                });
        }, 3000);
    });

    document.addEventListener('DOMContentLoaded', function() {

        document.querySelectorAll('[data-bs-target="#enterpriseModal"]')
            .forEach(function(button) {

                button.addEventListener('click', function() {

                    document.getElementById('enterprise_plan_id').value =
                        this.dataset.planId;

                });

            });

    });
</script>
@endif

@endsection