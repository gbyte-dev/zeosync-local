@extends('layouts.app')

@section('content')

@include('inventory.partials.map-shopify-product-modal')
@include('inventory.partials.map-amazon-product-modal')

@push('css')
<!-- Bootstrap Icons -->
<link nonce="{{ $cspNonce }}" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style nonce="{{ $cspNonce }}">
    /* Page Header & Usage Card */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .saas-page-title-wrap {
        flex: 1;
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

    .saas-usage-box {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 10px 14px;
        min-width: 280px;
    }

    .in-iframe .saas-usage-box {
        padding: 0px 0px;
        border: 0px solid #E5E7EB;
        background: none
    }

    .saas-usage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        font-size: 12px;
    }

    .saas-usage-progress {
        background-color: #E5E7EB;
        border-radius: 999px;
        height: 6px;
        overflow: hidden;
        margin-bottom: 6px;
    }

    .saas-usage-progress-bar {
        height: 100%;
        border-radius: 999px;
    }

    /* Stats Grid */
    .saas-stats-grid {
        display: flex;
        flex-wrap: nowrap;
        gap: 12px;
        margin-bottom: 16px;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .saas-stat-card {
        flex: 1;
        min-width: 160px;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 10px 14px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .saas-stat-label {
        color: #6D7175;
        font-size: 12px;
        font-weight: 600;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .saas-stat-value {
        font-size: 16px;
        font-weight: 700;
        color: #1A1A1A;
        line-height: 1;
    }

    /* Tabs & Card */
    .saas-inventory-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .saas-tabs-container {
        border-bottom: 1px solid #E5E7EB;
        padding: 0 16px;
        background: #F9FAFB;
    }

    .saas-tabs {
        display: flex;
        gap: 20px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .saas-tabs .nav-link {
        border: none;
        background: transparent;
        padding: 14px 4px;
        font-size: 13px;
        font-weight: 600;
        color: #6D7175;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        cursor: pointer;
        transition: color 0.2s, border-color 0.2s;
    }

    .saas-tabs .nav-link:hover {
        color: #1A1A1A;
    }

    .saas-tabs .nav-link.active {
        color: #1A1A1A;
        border-bottom-color: #1A1A1A;
    }

    /* Toolbar & Inputs */
    .saas-toolbar {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
        background: #FFFFFF;
    }

    .saas-input,
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
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .saas-input:focus,
    .saas-select:focus {
        outline: none;
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
    }

    /* Tables */
    .saas-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .saas-table th {
        background: #F9FAFB;
        color: #6D7175;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 16px;
        border-bottom: 1px solid #E5E7EB;
        white-space: nowrap;
        cursor: pointer;
        /* Makes it clear they are sortable */
    }

    .saas-table td {
        padding: 6px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #E5E7EB;
        color: #202223;
    }

    .saas-table .product-title-clamp {
        display: block;
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Override Bootstrap modal position for Shopify iframe */
    #amazonProductActionModal .modal-dialog,
    #mapAmazonProductModal .modal-dialog,
    #productActionModal .modal-dialog,
    #mapShopifyProductModal .modal-dialog {
        transform: translateX(140px) !important;
    }

    #mapAmazonProductModal .modal-dialog,
    #mapShopifyProductModal .modal-dialog {
        max-width: 700px !important;
    }

    #amazonProductActionModal.fade .modal-dialog,
    #mapAmazonProductModal.fade .modal-dialog,
    #productActionModal.fade .modal-dialog,
    #mapShopifyProductModal.fade .modal-dialog {
        transition: none !important;
    }

    @media (min-width: 768px) {
        .saas-table .product-title-clamp {
            max-width: 280px;
        }
    }

    @media (min-width: 992px) {
        .saas-table .product-title-clamp {
            max-width: 380px;
        }
    }

    div:where(.swal2-container) div:where(.swal2-popup) {
        padding: 0px !important;
    }

    div:where(.swal2-container) button:where(.swal2-styled):not([disabled]) {
        font-size: small;
    }

    div:where(.swal2-container) div:where(.swal2-html-container) {
        font-size: small;
    }

    /* Overriding JS-Rendered Elements */
    .saas-table .product-img {
        width: 34px;
        height: 34px;
        border-radius: 6px;
        object-fit: cover;
        border: 1px solid #E5E7EB;
        background: #F9FAFB;
    }

    .saas-table .qty-input {
        width: 70px;
        height: 30px;
        padding: 2px 6px;
        text-align: center;
        border-radius: 6px;
        border: 1px solid #C9CCCF;
        font-size: 13px;
    }

    .saas-table .qty-input:focus {
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
        outline: none;
    }

    .saas-table .soft-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
    }

    .saas-table .bg-success-subtle {
        background-color: #AEE9D1 !important;
        color: #005C3B !important;
    }

    .saas-table .bg-warning-subtle {
        background-color: #FFEA8A !important;
        color: #8A6116 !important;
    }

    .saas-table .bg-danger-subtle {
        background-color: #FED3D1 !important;
        color: #8C1105 !important;
    }

    /* Buttons Override */
    .saas-wrapper .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        height: 34px;
    }

    .saas-table .btn-sm {
        height: 28px;
        padding: 2px 10px;
        font-size: 12px;
    }

    .saas-table .btn-icon-only {
        width: 28px !important;
        height: 28px !important;
        padding: 0 !important;
    }

    .saas-table .btn-icon-only i {
        font-size: 16px;
        line-height: 1;
    }

    .saas-wrapper .btn-primary {
        background-color: #2C6ECB;
        color: #FFFFFF;
    }

    .saas-wrapper .btn-dark {
        background-color: #1A1A1A;
        color: #FFFFFF;
    }

    .saas-wrapper .btn-primary:hover,
    .saas-wrapper .btn-dark:hover {
        background-color: #333333;
        color: #FFFFFF;
    }

    .saas-wrapper .btn-success {
        background-color: #008060;
        color: #FFFFFF;
    }

    .saas-wrapper .btn-success:hover {
        background-color: #006e52;
        color: #FFFFFF;
    }

    .saas-wrapper .btn-warning {
        background-color: #E2A500;
        color: #202223;
    }

    .saas-wrapper .btn-light {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
    }

    .saas-wrapper .btn-light:hover {
        background-color: #F4F6F8;
    }

    /* DataTables customized pagination area */
    .saas-pagination-wrapper {
        padding: 12px 16px;
        background: #FFFFFF;
        border-top: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 12px;
    }

    .dataTables_info {
        color: #6D7175 !important;
        padding-top: 0 !important;
    }

    /* Loader */
    .amazon-loader {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 14px 18px;
        margin-bottom: 12px;
        background: #f8f9fa;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }

    .amazon-loader.d-none {
        display: none;
    }

    .amazon-loader .loader {
        transform: scale(0.55);
        transform-origin: center;
        flex-shrink: 0;
    }

    .amazon-loader .progress-percent {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin: 0 !important;
    }

    .amazon-loader .progress-message {
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        margin: 0 !important;
    }

    .no-data-msg {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
        text-align: center;
    }

    .no-data-msg i {
        font-size: 40px;
        color: #C9CCCF;
        margin-bottom: 12px;
    }

    .no-data-msg h4 {
        font-size: 16px;
        font-weight: 600;
        color: #1A1A1A;
        margin: 0 0 4px 0;
    }

    .no-data-msg p {
        font-size: 13px;
        color: #6D7175;
        margin: 0;
    }

    @media(max-width:576px) {
        .saas-page-header {
            flex-direction: column;
            align-items: stretch;
        }

        .saas-usage-box {
            min-width: auto;
        }

        .saas-pagination-wrapper {
            flex-direction: column;
            text-align: center;
            justify-content: center;
        }
    }

    a {
        text-decoration: none !important;
    }

    /* Viewport-Aware Custom Tooltip */
    .zeosync-custom-tooltip {
        position: fixed;
        z-index: 9999999;
        width: auto;
        max-width: min(320px, calc(100vw - 24px));
        padding: 6px 10px;
        background: #1A1A1A;
        color: #FFFFFF;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1.4;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
        text-align: left;
        pointer-events: none;
        box-sizing: border-box;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        opacity: 0;
        transition: opacity 0.12s ease;
    }

    .zeosync-custom-tooltip.visible {
        opacity: 1;
    }

    .s2-name { font-size: small; font-weight: 500; line-height: 1.25; white-space: normal; }
    .s2-sku  { font-size: .70rem;  margin-top: 2px; }
    .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field{
        font-size: .75rem !important;
    }

    /* Select2 UI Polishing for Map Amazon Product & Map Shopify Product Modals */
    #mapAmazonProductModal .select2-container--bootstrap-5 .select2-selection,
    #mapShopifyProductModal .select2-container--bootstrap-5 .select2-selection {
        border-color: #dee2e6;
        font-size: 0.85rem;
        min-height: 38px;
        border-radius: 6px;
    }
    #mapAmazonProductModal .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    #mapShopifyProductModal .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
    #mapAmazonProductModal .select2-dropdown,
    #mapShopifyProductModal .select2-dropdown {
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border: 1px solid #e2e8f0;
        z-index: 1060;
    }
    #mapAmazonProductModal .select2-results__option,
    #mapShopifyProductModal .select2-results__option {
        padding: 6px 10px;
        border-bottom: 1px solid #f1f5f9;
    }
    #mapAmazonProductModal .select2-results__option:last-child,
    #mapShopifyProductModal .select2-results__option:last-child {
        border-bottom: none;
    }
    #mapAmazonProductModal .select2-results__option--highlighted[aria-selected],
    #mapShopifyProductModal .select2-results__option--highlighted[aria-selected] {
        background-color: #f0f7ff !important;
        color: #1e293b !important;
    }
    #mapAmazonProductModal .select2-results__option--highlighted .amazon-option-title,
    #mapShopifyProductModal .select2-results__option--highlighted .shopify-option-title {
        color: #0d6efd !important;
    }
    #mapAmazonProductModal .select2-results__option--selected,
    #mapShopifyProductModal .select2-results__option--selected {
        background-color: #e2eeff !important;
    }
    .amazon-option-item,
    .shopify-option-item {
        line-height: 1.3;
    }
    .amazon-option-title,
    .shopify-option-title {
        font-size: 0.83rem;
        color: #212529;
        font-weight: 500;
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .amazon-option-meta,
    .shopify-option-meta {
        font-size: 0.72rem;
    }
</style>
<link nonce="{{ $cspNonce??'' }}" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}?v={{ time() }}" rel="stylesheet">
<link nonce="{{ $cspNonce??'' }}" href="{{ asset('assets/vendor/select2/css/select2-bootstrap-5-theme.min.css') }}?v={{ time() }}" rel="stylesheet">
@endpush

<div class="container-fluid py-3 px-3 saas-wrapper">

    {{-- Header & Usage --}}
    <div class="saas-page-header">
        <div class="saas-page-title-wrap">
            <h1 class="saas-page-title">Inventory</h1>
            <p class="saas-page-subtitle">Manage Shopify and Amazon inventory stock levels</p>
        </div>

        @if($syncUsage['limit'] == 0)
        <div class="saas-usage-box">
            <div class="saas-usage-header text-muted">
                <span class="fw-semibold">Product Mapping Usage</span>
            </div>
            <div class="fw-bold text-success" style="font-size: 14px;">Unlimited</div>
        </div>
        @else
        @php
        $percentage = ($syncUsage['used'] / $syncUsage['limit']) * 100;
        if ($percentage >= 100) { $progressClass = 'bg-danger'; }
        elseif ($percentage >= 80) { $progressClass = 'bg-warning'; }
        else { $progressClass = 'bg-success'; }
        @endphp

        <div class="saas-usage-box" id="mappingUsageBox">
            <div class="saas-usage-header">
                <div>
                    <span class="fw-semibold text-dark">Mapping Usage</span>
                    @if(!empty($syncUsage['plan_name']))
                    <span class="text-muted ms-1" id="mappingUsagePlanName">({{ $syncUsage['plan_name'] }})</span>
                    @endif
                </div>
                <span class="fw-bold text-dark" id="mappingUsageCounts">{{ $syncUsage['used'] }} / {{ $syncUsage['limit'] }}</span>
            </div>
            <div class="saas-usage-progress">
                <div class="saas-usage-progress-bar {{ $progressClass }}" id="mappingUsageProgressBar" role="progressbar" style="width: {{ min($percentage, 100) }}%"></div>
            </div>
            <div id="mappingUsageStatus">
                @if($syncUsage['remaining'] == 0)
                <div class="text-danger fw-bold" style="font-size: 11px;">Sync limit reached</div>
                @elseif($syncUsage['remaining'] <= 10)
                <div class="text-warning fw-bold" style="font-size: 11px;">Running low</div>
                @else
                <div class="text-muted" style="font-size: 11px;">Remaining: <span class="fw-bold text-dark">{{ $syncUsage['remaining'] }}</span></div>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- Stats Grid --}}
<div class="saas-stats-grid">
    <div class="saas-stat-card">
        <div class="saas-stat-label">Total Items</div>
        <div class="saas-stat-value" id="totalCount">0</div>
    </div>
    <div class="saas-stat-card">
        <div class="saas-stat-label">Synced</div>
        <div class="saas-stat-value text-success" id="syncedCount">0</div>
    </div>
    <div class="saas-stat-card">
        <div class="saas-stat-label">Pending</div>
        <div class="saas-stat-value text-warning" id="pendingCount">0</div>
    </div>
    <div class="saas-stat-card">
        <div class="saas-stat-label">Errors</div>
        <div class="saas-stat-value text-danger" id="errorCount">0</div>
    </div>
</div>

{{-- Main Inventory Card --}}
<div class="saas-inventory-card">

    <div class="saas-tabs-container">
        @php
            $requestedTab = strtolower((string) request('tab', 'shopify'));
            $isMappedActive = in_array($requestedTab, ['mapped', 'mappings'], true);
            $isAmazonActive = ($requestedTab === 'amazon');
            $isShopifyActive = !$isMappedActive && !$isAmazonActive;
        @endphp
        <ul class="nav nav-tabs saas-tabs">
            <li class="nav-item">
                <button class="nav-link {{ $isShopifyActive ? 'active' : '' }}"
                    data-bs-toggle="tab"
                    data-bs-target="#shopifyTab"
                    onclick="switchToShopifyTab();">
                    Shopify
                </button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link {{ $isAmazonActive ? 'active' : '' }}"
                    id="amazon-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#amazonTab"
                    onclick="switchToAmazonTab();">
                    Amazon
                </button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link {{ $isMappedActive ? 'active' : '' }}"
                    id="mapped-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#mappedAmazonTab"
                    onclick="switchToMappedTab();">
                    Mappings
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">

        {{-- Shopify Tab --}}
        <div class="tab-pane fade {{ $isShopifyActive ? 'show active' : '' }}" id="shopifyTab">
            <div class="saas-toolbar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-12">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Search Product</label>
                        <input type="text" id="dtSearchShopify" class="saas-input" placeholder="Search SKU / Product...">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Status Filter</label>
                        <select id="dtStatusShopify" class="saas-select">
                            <option value="">All Status</option>
                            <option value="synced">Synced</option>
                            <option value="oversold">Oversold</option>
                            <option value="out_of_stock">Out of Stock</option>
                            <option value="pending">Pending</option>
                            <option value="unknown">Unknown</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Rows Per Page</label>
                        <select id="dtLengthShopify" class="saas-select">
                            <option value="10" {{ $shopifyPageLength === 10 ? 'selected' : '' }}>10 Rows</option>
                            <option value="25" {{ $shopifyPageLength === 25 ? 'selected' : '' }}>25 Rows</option>
                            <option value="50" {{ $shopifyPageLength === 50 ? 'selected' : '' }}>50 Rows</option>
                            <option value="100" {{ $shopifyPageLength === 100 ? 'selected' : '' }}>100 Rows</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button onclick="refreshCache(event)" class="btn btn-light w-100">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Shopify Location</label>
                        @php
                            $locations = $shop->shopify_locations ?? [];
                            $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
                                ? (int) $shop->selected_location_index : 0;
                        @endphp
                        <select id="dtLocationShopify" class="saas-select">
                            @if(!empty($locations))
                                @foreach($locations as $index => $location)
                                    <option value="{{ $index }}" {{ $selectedIndex === $index ? 'selected' : '' }}>
                                        {{ $location['name'] ?? 'Location ' . ($index + 1) }}
                                    </option>
                                @endforeach
                            @else
                                <option value="" selected>No Location Available</option>
                            @endif
                        </select>
                    </div>
                </div>
            </div>

            <!-- Custom No Data Message -->
            <div id="shopifyNoDataMsg" class="no-data-msg" style="display: none;">
                <i class="bi bi-inboxes"></i>
                <h4>No Inventory Found</h4>
                <p>It looks like there are no Shopify products available to display.</p>
            </div>

            <div class="table-responsive" id="shopifyTableWrapper">
                <table class="saas-table" id="shopifyTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-nowrap">SKU</th>
                            <th class="text-nowrap">Mapped To</th>
                            <th class="text-nowrap">Available</th>
                            <th class="text-nowrap">Status</th>
                            <th class="text-nowrap text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: small;"></tbody>
                </table>
            </div>
        </div>

        {{-- Amazon Tab --}}
        <div class="tab-pane fade {{ $isAmazonActive ? 'show active' : '' }}" id="amazonTab">
            @if($shop->amazon_refresh_token)
            <div class="saas-toolbar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Search Product</label>
                        <input type="text" id="dtSearchAmazon" class="saas-input" placeholder="Search SKU / Product...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Status Filter</label>
                        <select id="dtStatusAmazon" class="saas-select">
                            <option value="">All Status</option>
                            <option value="synced">Synced</option>
                            <option value="pending">Pending</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Rows Per Page</label>
                        <select id="dtLengthAmazon" class="saas-select">
                            <option value="10" {{ $amazonPageLength === 10 ? 'selected' : '' }}>10 Rows</option>
                            <option value="25" {{ $amazonPageLength === 25 ? 'selected' : '' }}>25 Rows</option>
                            <option value="50" {{ $amazonPageLength === 50 ? 'selected' : '' }}>50 Rows</option>
                            <option value="100" {{ $amazonPageLength === 100 ? 'selected' : '' }}>100 Rows</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button onclick="refreshCache(event)" class="btn btn-light w-100">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Custom No Data Message -->
            <div id="amazonNoDataMsg" class="no-data-msg" style="display: none;">
                <i class="bi bi-inboxes"></i>
                <h4>No Inventory Found</h4>
                <p>It looks like there are no Amazon products available to display.</p>
            </div>

            <div id="amazonLoader" class="amazon-loader d-none">
                <div class="loader">
                    <div class="loader-square"></div>
                    <div class="loader-square"></div>
                    <div class="loader-square"></div>
                    <div class="loader-square"></div>
                    <div class="loader-square"></div>
                    <div class="loader-square"></div>
                    <div class="loader-square"></div>
                </div>

                <div class="progress-percent">
                    <span id="amazonProgressPercent">0%</span>
                </div>

                <div class="progress-message">
                    <span id="amazonProgressMessage">
                        Calling Amazon, you can continue working...
                    </span>
                </div>
            </div>

            <div class="table-responsive" id="amazonTableWrapper">
                <table class="saas-table" id="amazonTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-nowrap">SKU</th>
                            <th class="text-nowrap">Mapped To</th>
                            <th class="text-nowrap">Qty</th>
                            <th class="text-nowrap">Status</th>
                            <th class="text-nowrap text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 12px;"></tbody>
                </table>
            </div>
            @else
            <div class="p-3">
                <div class="alert alert-warning mb-0 border-0" style="border-radius: 8px; font-size: 13px;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Please connect your Amazon account first.
                    <a href="{{ route('amazon.connect') }}" class="fw-bold ms-1 text-dark text-decoration-underline">Connect Amazon</a>
                </div>
            </div>
            @endif
        </div>

        <div class="tab-pane fade {{ $isMappedActive ? 'show active' : '' }}" id="mappedAmazonTab">
            @php $mappedproducts = $mappedproducts ?? collect(); @endphp
            <div class="saas-toolbar" id="mappedToolbar" style="{{ $mappedproducts->isEmpty() ? 'display: none;' : '' }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4 col-12">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Search Mappings</label>
                        <input type="text" id="dtSearchMapped" class="saas-input" placeholder="Search Product / Variant / SKU / Location...">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Status Filter</label>
                        <select id="dtStatusMapped" class="saas-select">
                            <option value="">All Status</option>
                            <option value="synced">Synced</option>
                            <option value="pending">Pending</option>
                            <option value="error">Error</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Rows Per Page</label>
                        <select id="dtLengthMapped" class="saas-select">
                            <option value="10" selected>10 Rows</option>
                            <option value="25">25 Rows</option>
                            <option value="50">50 Rows</option>
                            <option value="100">100 Rows</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-12">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button onclick="refreshMappingUI()" class="btn btn-light w-100">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            {{-- Loading State --}}
            <div id="mappedLoadingMsg" class="no-data-msg" style="display: none;">
                <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4>Loading Product Mappings...</h4>
                <p>Fetching active product mappings.</p>
            </div>

            {{-- Empty State --}}
            <div id="mappedNoDataMsg" class="no-data-msg" style="{{ $mappedproducts->isEmpty() ? '' : 'display: none;' }}">
                <i class="bi bi-link-45deg"></i>
                <h4>No Product Mappings Found</h4>
                <p>Map a Shopify product to an Amazon SKU to start syncing inventory.</p>
            </div>

            {{-- Table Wrapper --}}
            <div id="mappedTableWrapper" class="table-responsive" style="{{ $mappedproducts->isEmpty() ? 'display: none;' : '' }}">
                <table class="saas-table" id="mappedTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Shopify Product</th>
                            <th class="text-nowrap">Variant</th>
                            <th class="text-nowrap">Amazon SKU</th>
                            <th class="text-nowrap">Location</th>
                            <th class="text-nowrap">Status</th>
                            <th class="text-nowrap">Last Synced</th>
                            <th class="text-nowrap text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 12px;">
                        @foreach($mappedproducts as $mapping)
                            @if(!empty($mapping->amazon_sku) && !empty($mapping->shopify_variant_id))
                            @php
                                $product = $mapping->product ?? null;
                                if (!$product && $mapping->shopify_product_id) {
                                    $product = \App\Models\Product::where('shop_id', $shop->id)->where('shopify_id', $mapping->shopify_product_id)->first();
                                }
                                $shopifyProductId = $mapping->shopify_product_id ?? ($product ? ($product->shopify_id ?: $product->id) : $mapping->product_id);
                                $shopifyProductTitle = $product ? $product->title : null;
                                if (empty($shopifyProductTitle) && !empty($shopifyProductId)) {
                                    $shopifyProductTitle = 'Shopify Product #' . $shopifyProductId;
                                }

                                $shopifyProductLink = !empty($shopifyProductId)
                                    ? route('shopify.product.view', ['id' => $shopifyProductId, 'shop' => request('shop') ?? session('active_shop')])
                                    : null;

                                $shopifyVariantTitle = null;
                                if ($product && !empty($product->variants)) {
                                    $rawVariants = is_array($product->variants) ? $product->variants : (json_decode($product->variants, true) ?? []);
                                    $foundVariant = collect($rawVariants)->first(fn($v) => (string) ($v['id'] ?? '') === (string) $mapping->shopify_variant_id);
                                    if ($foundVariant) {
                                        $shopifyVariantTitle = $foundVariant['title'] ?? null;
                                    }
                                }
                                if (empty($shopifyVariantTitle)) {
                                    if ((string) $mapping->shopify_variant_id === (string) $shopifyProductId) {
                                        $shopifyVariantTitle = 'Default';
                                    } else {
                                        $shopifyVariantTitle = $mapping->shopify_variant_id;
                                    }
                                }

                                $locations = is_array($shop->shopify_locations) ? $shop->shopify_locations : (json_decode($shop->shopify_locations, true) ?? []);
                                $locName = 'Default';
                                if (!empty($mapping->shopify_location_id)) {
                                    foreach ($locations as $loc) {
                                        $locId = (string) ($loc['id'] ?? '');
                                        if ($locId === (string) $mapping->shopify_location_id || (!empty($locId) && str_ends_with($locId, (string) $mapping->shopify_location_id))) {
                                            $locName = $loc['name'] ?? 'Default';
                                            break;
                                        }
                                    }
                                } elseif (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index])) {
                                    $locName = $locations[$shop->selected_location_index]['name'] ?? 'Default';
                                }

                                $amazonProductLink = !empty($mapping->amazon_sku)
                                    ? route('user.product.amazonView', ['sku' => $mapping->amazon_sku, 'shop' => request('shop') ?? session('active_shop')])
                                    : null;

                                $rawProductTitle = $shopifyProductTitle ?? 'N/A';
                                $isProductTruncated = mb_strlen($rawProductTitle) > 20;
                                $displayProductTitle = $isProductTruncated ? mb_substr($rawProductTitle, 0, 17) . '...' : $rawProductTitle;
                                $productTooltip = $isProductTruncated ? ' title="' . e($rawProductTitle) . '" data-bs-toggle="tooltip" data-bs-container="body" data-bs-placement="top"' : '';

                                $rawSku = $mapping->amazon_sku ?? '—';
                                $isSkuTruncated = (!empty($mapping->amazon_sku) && mb_strlen($rawSku) > 20);
                                $displaySku = $isSkuTruncated ? mb_substr($rawSku, 0, 17) . '...' : $rawSku;
                                $skuTooltip = $isSkuTruncated ? ' title="' . e($rawSku) . '" data-bs-toggle="tooltip" data-bs-container="body" data-bs-placement="top"' : '';

                                $status = strtolower((string) ($mapping->sync_status ?? 'active'));
                                $statusClass = match ($status) {
                                    'synced' => 'bg-success-subtle text-success',
                                    'pending' => 'bg-warning-subtle text-warning',
                                    'error' => 'bg-danger-subtle text-danger',
                                    default => 'bg-secondary-subtle text-secondary',
                                };
                                $statusLabel = ucfirst($status ?: 'Active');
                            @endphp
                            <tr>
                                <td>
                                    @if($shopifyProductLink)
                                        <a href="{{ $shopifyProductLink }}" class="fw-semibold text-dark text-decoration-none"{!! $productTooltip !!}>
                                            {{ $displayProductTitle }}
                                        </a>
                                    @else
                                        <div class="fw-semibold text-dark"{!! $productTooltip !!}>{{ $displayProductTitle }}</div>
                                    @endif
                                    <small class="text-muted d-block">ID: {{ $shopifyProductId ?? '—' }}</small>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark">{{ $shopifyVariantTitle }}</span>
                                    <small class="text-muted d-block">{{ $mapping->shopify_variant_id ?? '—' }}</small>
                                </td>
                                <td>
                                    @if($amazonProductLink)
                                        <a href="{{ $amazonProductLink }}" class="text-dark fw-semibold text-decoration-none"{!! $skuTooltip !!}>
                                            {{ $displaySku }}
                                        </a>
                                    @else
                                        <span class="text-muted"{!! $skuTooltip !!}>{{ $displaySku }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-dark">{{ $locName }}</span>
                                </td>
                                <td>
                                    <span class="soft-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="text-muted">
                                    {{ $mapping->last_synced_at ? $mapping->last_synced_at->format('M d, Y h:i A') : '—' }}
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-danger btn-sm unmap-product" data-mapping-id="{{ $mapping->id }}" title="Unmap Product" data-bs-toggle="tooltip" data-bs-placement="top">
                                        <i class="bi bi-link"></i>
                                    </button>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mapped Tab --}}

    </div>
</div>
</div>

<!-- {{-- Global Full Screen Loader --}}
<div id="amazonLoader" class="amazon-loader d-none">
    <div class="loader">
        <div class="loader-square"></div>
        <div class="loader-square"></div>
        <div class="loader-square"></div>
        <div class="loader-square"></div>
        <div class="loader-square"></div>
        <div class="loader-square"></div>
        <div class="loader-square"></div>
    </div>
    <div class="progress-percent mt-3">
        <span id="amazonProgressPercent">0%</span>
    </div>
    <div class="progress-message mt-1">
        <span id="amazonProgressMessage">Preparing...</span>
    </div>
</div> -->

@endsection

@push('scripts')
<script nonce="{{ $cspNonce??'' }}" src="{{ asset('assets/vendor/select2/js/select2.min.js') }}?v={{ time() }}"></script>
<script nonce="{{ $cspNonce }}">
    const amazonConnected = @json(!empty($shop -> amazon_refresh_token));
    let selectedAmazonSku = null;
    let selectedShopifyVariantId = null;
    let selectedShopifyProductId = null;
    let activeTab = 'shopify';

    let dtShopify = null;
    let dtAmazon = null;
    let dtMapped = null;
    let progressTimer = null;
    let amazonProductsCache = null;
    let amazonLoading = false;

    // Saved page length from Laravel session (default to 10)
    let savedShopifyLength = {{ $shopifyPageLength ?? 10 }};
    let savedAmazonLength = {{ $amazonPageLength ?? 10 }};
    let savedMappedLength = 10;

    function persistPageLength(type, length) {
        $.ajax({
            url: "{{ route('shopify.inventory.page_length') }}",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                type: type,
                length: length
            },
            error: function(xhr) {
                console.warn(`Failed to save ${type} page length preference to session:`, xhr.responseText);
            }
        });
    }

    // On load, update the dropdown UI to match the saved preference
    document.addEventListener('DOMContentLoaded', function() {
        $('#dtLengthShopify').val(savedShopifyLength);
        $('#dtLengthAmazon').val(savedAmazonLength);
        $('#dtLengthMapped').val(savedMappedLength);

        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = (urlParams.get('tab') || '').toLowerCase();

        const amazonTab = document.querySelector(
            '[data-bs-target="#amazonTab"]'
        );

        if (amazonTab) {
            amazonTab.addEventListener('shown.bs.tab', function() {
                switchToAmazonTab();
            });
        }

        const mappedTabEl = document.querySelector(
            '[data-bs-target="#mappedAmazonTab"]'
        );
        if (mappedTabEl) {
            mappedTabEl.addEventListener('shown.bs.tab', function() {
                switchToMappedTab();
            });
        }

        if (tabParam === 'mapped' || tabParam === 'mappings' || window.location.hash === '#mappedAmazonTab' || window.location.hash === '#mapped') {
            if (mappedTabEl) {
                const bsTab = bootstrap.Tab.getOrCreateInstance(mappedTabEl);
                bsTab.show();
            }
            switchToMappedTab();
        } else if (tabParam === 'amazon' || window.location.hash === '#amazonTab' || window.location.hash === '#amazon') {
            if (amazonTab) {
                const bsTab = bootstrap.Tab.getOrCreateInstance(amazonTab);
                bsTab.show();
            }
            switchToAmazonTab();
        } else {
            loadShopify();
        }
    });

    function getMappedStatus(rawStatus, tab) {
        let original = (rawStatus || '').toLowerCase();
        if (tab === 'amazon') {
            if (original === 'active') return 'synced';
            if (original === 'inactive' || original === 'incomplete') return 'pending';
        }
        if (original === 'synced') return 'synced';
        if (original === 'oversold') return 'oversold';
        if (original === 'out_of_stock') return 'out_of_stock';
        if (original === 'pending') return 'pending';
        if (original === 'unknown') return 'unknown';
        return 'error';
    }

    function refreshMappingState() {
        const shop = new URLSearchParams(window.location.search).get('shop');

        return $.ajax({
            url: "{{ route('inventory.mappings') }}",
            type: "GET",
            data: {
                shop: shop,
                _: Date.now()
            },
            cache: false
        });
    }

    function updateMappingUsageUI(usage) {
        if (!usage || usage.limit === 0 || typeof usage.limit === 'undefined') {
            return;
        }

        const used = parseInt(usage.used, 10) || 0;
        const limit = parseInt(usage.limit, 10) || 0;
        const remaining = typeof usage.remaining !== 'undefined' ? parseInt(usage.remaining, 10) : Math.max(0, limit - used);

        if (limit > 0) {
            $('#mappingUsageCounts').text(used + ' / ' + limit);

            const percentage = (used / limit) * 100;
            const $progressBar = $('#mappingUsageProgressBar');
            $progressBar.css('width', Math.min(percentage, 100) + '%');
            $progressBar.removeClass('bg-success bg-warning bg-danger');

            if (percentage >= 100) {
                $progressBar.addClass('bg-danger');
            } else if (percentage >= 80) {
                $progressBar.addClass('bg-warning');
            } else {
                $progressBar.addClass('bg-success');
            }

            let statusHtml = '';
            if (remaining === 0) {
                statusHtml = '<div class="text-danger fw-bold" style="font-size: 11px;">Sync limit reached</div>';
            } else if (remaining <= 10) {
                statusHtml = '<div class="text-warning fw-bold" style="font-size: 11px;">Running low</div>';
            } else {
                statusHtml = '<div class="text-muted" style="font-size: 11px;">Remaining: <span class="fw-bold text-dark">' + remaining + '</span></div>';
            }
            $('#mappingUsageStatus').html(statusHtml);
        }
    }

    function switchToMappedTab() {
        activeTab = 'mapped';
        if (window._amazonVerifyPollTimer) {
            clearTimeout(window._amazonVerifyPollTimer);
            window._amazonVerifyPollTimer = null;
        }
        hideAmazonLoader();
        renderMappedTable([], true);
        refreshMappingUI();
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderMappedTable(data, isLoading = false) {
        if (isLoading) {
            $('#mappedToolbar').hide();
            $('#mappedTableWrapper').hide();
            $('#mappedNoDataMsg').hide();
            $('#mappedLoadingMsg').show();
            return;
        }

        $('#mappedLoadingMsg').hide();

        const validMappings = (Array.isArray(data) ? data : []).filter(m => !!(m.amazon_sku && m.shopify_variant_id));

        if (validMappings.length === 0) {
            $('#mappedToolbar').hide();
            $('#mappedTableWrapper').hide();
            $('#mappedNoDataMsg').show();

            if ($.fn.DataTable.isDataTable('#mappedTable')) {
                dtMapped.destroy();
                dtMapped = null;
            }
            $('#mappedTable tbody').empty();
            return;
        }

        $('#mappedNoDataMsg').hide();
        $('#mappedToolbar').show();
        $('#mappedTableWrapper').show();

        const currentShop = new URLSearchParams(window.location.search).get('shop') || '{{ $shop->shop }}';

        if (!$.fn.DataTable.isDataTable('#mappedTable')) {
            dtMapped = $('#mappedTable').DataTable({
                pageLength: parseInt(savedMappedLength, 10) || 10,
                ordering: true,
                dom: 'rt<"saas-pagination-wrapper"ip>',
                language: {
                    emptyTable: "No matching records found"
                },
                columns: [
                    {
                        data: 'shopify_product_title',
                        render: function(data, type, row) {
                            let fullTitle = String(row.shopify_product_title || ('Shopify Product #' + (row.shopify_product_id || '')));
                            let sub = 'ID: ' + (row.shopify_product_id || '—');
                            if (type === 'sort' || type === 'filter') {
                                return fullTitle + ' ' + (row.shopify_product_id || '');
                            }
                            let isTruncated = fullTitle.length > 20;
                            let displayTitle = isTruncated ? fullTitle.substring(0, 17) + '...' : fullTitle;
                            let escapedFull = escapeHtml(fullTitle);
                            let escapedDisplay = escapeHtml(displayTitle);
                            let tooltipAttr = isTruncated ? ` title="${escapedFull}" data-bs-toggle="tooltip" data-bs-container="body" data-bs-placement="top"` : '';
                            let link = row.shopify_product_url;
                            let titleHtml = link
                                ? `<a href="${link}" class="fw-semibold text-dark text-decoration-none"${tooltipAttr}>${escapedDisplay}</a>`
                                : `<div class="fw-semibold text-dark"${tooltipAttr}>${escapedDisplay}</div>`;
                            return `${titleHtml}<small class="text-muted d-block">${escapeHtml(sub)}</small>`;
                        }
                    },
                    {
                        data: 'shopify_variant_title',
                        render: function(data, type, row) {
                            let variantTitle = row.shopify_variant_title || row.shopify_variant_id || '—';
                            let sub = row.shopify_variant_id || '—';
                            if (type === 'sort' || type === 'filter') {
                                return variantTitle + ' ' + sub + ' ' + (row.shopify_variant_sku || '');
                            }
                            return `<span class="fw-medium text-dark">${escapeHtml(variantTitle)}</span><small class="text-muted d-block">${escapeHtml(sub)}</small>`;
                        }
                    },
                    {
                        data: 'amazon_sku',
                        render: function(data, type, row) {
                            let rawSku = row.amazon_sku;
                            if (type === 'sort' || type === 'filter') {
                                return rawSku || '';
                            }
                            if (!rawSku) {
                                return `<span class="text-muted">—</span>`;
                            }
                            let fullSku = String(rawSku);
                            let isTruncated = fullSku.length > 20;
                            let displaySku = isTruncated ? fullSku.substring(0, 17) + '...' : fullSku;
                            let escapedFull = escapeHtml(fullSku);
                            let escapedDisplay = escapeHtml(displaySku);
                            let tooltipAttr = isTruncated ? ` title="${escapedFull}" data-bs-toggle="tooltip" data-bs-container="body" data-bs-placement="top"` : '';
                            let url = row.amazon_product_url || ("{{ route('user.product.amazonView', ['sku' => '__SKU__', 'shop' => '__SHOP__']) }}".replace('__SKU__', encodeURIComponent(fullSku)).replace('__SHOP__', encodeURIComponent(currentShop)));
                            return `<a href="${url}" class="text-dark fw-semibold text-decoration-none"${tooltipAttr}>${escapedDisplay}</a>`;
                        }
                    },
                    {
                        data: 'shopify_location_name',
                        render: function(data, type, row) {
                            let loc = row.shopify_location_name || 'Default';
                            if (type === 'sort' || type === 'filter') {
                                return loc;
                            }
                            return `<span class="text-dark">${escapeHtml(loc)}</span>`;
                        }
                    },
                    {
                        data: 'sync_status',
                        render: function(data, type, row) {
                            let status = (row.sync_status || 'active').toLowerCase();
                            if (type === 'filter' || type === 'sort') {
                                return status;
                            }
                            let statusClass = 'bg-secondary-subtle text-secondary';
                            if (status === 'synced') statusClass = 'bg-success-subtle text-success';
                            else if (status === 'pending') statusClass = 'bg-warning-subtle text-warning';
                            else if (status === 'error') statusClass = 'bg-danger-subtle text-danger';
                            let statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
                            return `<span class="soft-badge ${statusClass}">${statusLabel}</span>`;
                        }
                    },
                    {
                        data: 'last_synced_at',
                        render: function(data, type, row) {
                            if (type === 'sort') {
                                return row.last_synced_at_raw || row.last_synced_at || '';
                            }
                            if (type === 'filter') {
                                return row.last_synced_at || '';
                            }
                            return `<span class="text-muted">${row.last_synced_at || '—'}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: function(data, type, row) {
                            return `
                                <button class="btn btn-danger btn-sm unmap-product" data-mapping-id="${row.id}" title="Unmap Product" data-bs-toggle="tooltip" data-bs-placement="top">
                                    <i class="bi bi-link"></i>
                                </button>
                            `;
                        }
                    }
                ],
                drawCallback: function() {
                    initTooltips();
                }
            });
        }

        dtMapped.clear().rows.add(validMappings).draw();
    }

    function refreshMappingUI() {
        return refreshMappingState().then(function(response) {

            if (!response.success) {
                return;
            }

            if (response.sync_usage) {
                updateMappingUsageUI(response.sync_usage);
            }

            const mappings = response.mappings || [];

            // Update Mappings Tab Table dynamically
            renderMappedTable(mappings, false);

            const shopifyMappings = {};
            const amazonMappings = {};

            mappings.forEach(function(mapping) {
                shopifyMappings[String(mapping.shopify_variant_id)] = mapping;

                if (mapping.amazon_sku) {
                    amazonMappings[String(mapping.amazon_sku)] = mapping;
                }
            });

            // Update Shopify DataTable rows
            if (dtShopify) {
                dtShopify.rows().every(function() {
                    const row = this.data();
                    const mapping = shopifyMappings[String(row.vid)];

                    row.is_mapped = !!mapping;
                    row.mapping_id = mapping ? mapping.id : null;
                    row.mapped_sku = mapping ? mapping.amazon_sku : null;

                    this.data(row);
                });

                dtShopify.draw(false);
            }

            // Update Amazon DataTable rows
            if (dtAmazon) {
                dtAmazon.rows().every(function() {
                    const row = this.data();
                    const mapping = amazonMappings[String(row.sku)];

                    row.is_mapped = !!mapping;
                    row.mapping_id = mapping ? mapping.id : null;
                    row.mapped_shopify_variant_id = mapping ?
                        mapping.shopify_variant_id :
                        null;

                    this.data(row);
                });

                dtAmazon.draw(false);
            }

            // Synchronize in-memory amazonProductsCache if present
            if (Array.isArray(amazonProductsCache)) {
                amazonProductsCache.forEach(function(item) {
                    const mapping = amazonMappings[String(item.sku)];
                    item.is_mapped = !!mapping;
                    item.mapping_id = mapping ? mapping.id : null;
                    item.mapped_shopify_variant_id = mapping ?
                        mapping.shopify_variant_id :
                        null;
                });
            }
        });
    }

    function calculateStats(dtInstance, tab) {
        if (!dtInstance) return;

        let total = 0,
            synced = 0,
            pending = 0,
            error = 0;

        // Use DataTables API to get currently filtered rows for stats computation
        dtInstance.rows({
            search: 'applied'
        }).every(function() {
            let rowData = this.data();
            let status = getMappedStatus(rowData.status, tab);
            total++;
            if (status === 'synced') synced++;
            else if (status === 'pending') pending++;
            else error++;
        });

        $('#totalCount').text(total);
        $('#syncedCount').text(synced);
        $('#pendingCount').text(pending);
        $('#errorCount').text(error);
    }

    function badge(status, tab) {
        let mapped = getMappedStatus(status, tab);
        switch (mapped) {
            case 'synced':
                return `<span class="soft-badge bg-success-subtle text-success">Synced</span>`;
            case 'oversold':
                return `<span class="soft-badge bg-danger-subtle text-danger">Oversold</span>`;
            case 'out_of_stock':
                return `<span class="soft-badge bg-danger-subtle text-danger">Out of Stock</span>`;
            case 'pending':
                return `<span class="soft-badge bg-warning-subtle text-warning">Pending</span>`;
            case 'unknown':
                return `<span class="soft-badge bg-secondary-subtle text-secondary">Unknown</span>`;
            default:
                return `<span class="soft-badge bg-danger-subtle text-danger">Error</span>`;
        }
    }

    function loadShopify() {
        activeTab = 'shopify';
        fetch(`{{ route('shopify.inventory.shopify') }}?shop={{ $shop->shop }}`)
            .then(res => res.json())
            .then(data => {
                let items = Array.isArray(data) ? data : [];
                renderShopifyTable(items);
            })
            .catch(() => {
                if ($('#shopifyTable tbody tr').length === 0) {
                    renderShopifyTable([]);
                }
            });
    }

    function loadAmazon(force = false) {
        if (!amazonConnected) return;

        activeTab = 'amazon';

        // Browser cache hit
        if (!force && amazonProductsCache !== null) {
            renderAmazonTable(amazonProductsCache, false);

            requestAnimationFrame(() => {
                if (dtAmazon) {
                    dtAmazon.columns.adjust().draw(false);
                }
            });

            hideAmazonLoader();
            return;
        }

        // Prevent duplicate requests
        if (amazonLoading) {
            return;
        }

        amazonLoading = true;
        // Only display full-table loader on initial load when no data exists yet
        if (!amazonProductsCache && (!dtAmazon || dtAmazon.rows().count() === 0)) {
            showAmazonLoader();
        }

        const shop = new URLSearchParams(window.location.search).get('shop');

        $.ajax({
            url: "{{ route('shopify.inventory.amazon') }}",
            type: 'GET',
            data: {
                shop: shop
            },
            success: function(response) {
                const items = Array.isArray(response.products) ?
                    response.products : [];
                const isRefreshing = response.status?.refreshing === true;
                const syncCompleted = response.status?.sync_completed === true;

                /*
                 * Server sync is still running or pending completion.
                 * Do not treat empty response as final browser cache.
                 */
                if ((isRefreshing || !syncCompleted) && items.length === 0) {
                    amazonProductsCache = null;
                    renderAmazonTable([], true);
                    showAmazonLoader();
                    startProgress();
                    return;
                }

                /* Final server result.  */
                amazonProductsCache = items;

                renderAmazonTable(items, false);

                /*
                 * DataTables needs the tab to have its final dimensions.
                 */
                requestAnimationFrame(() => {
                    if (dtAmazon) {
                        dtAmazon.columns.adjust().draw(false);
                    }
                });

                hideAmazonLoader();

                if (progressTimer) {
                    clearInterval(progressTimer);
                    progressTimer = null;
                }
            },

            error: function(xhr) {
                console.error(
                    'Failed to load Amazon inventory:',
                    xhr.responseText
                );

                renderAmazonTable([], false);
                hideAmazonLoader();

                if (progressTimer) {
                    clearInterval(progressTimer);
                    progressTimer = null;
                }
            },
            complete: function() {
                amazonLoading = false;
            }
        });
    }

    function switchToAmazonTab() {
        activeTab = 'amazon';

        // Products already loaded in browser cache
        if (amazonProductsCache !== null) {
            renderAmazonTable(amazonProductsCache, false);

            requestAnimationFrame(() => {
                if (dtAmazon) {
                    dtAmazon.columns.adjust().draw(false);
                }
            });

            hideAmazonLoader();
            return;
        }

        // No browser cache → fetch in background (only show full loader if table empty)
        if (!dtAmazon || dtAmazon.rows().count() === 0) {
            showAmazonLoader();
        }
        loadAmazon();
    }

    function switchToShopifyTab() {
        activeTab = 'shopify';
        if (window._amazonVerifyPollTimer) {
            clearTimeout(window._amazonVerifyPollTimer);
            window._amazonVerifyPollTimer = null;
        }

        // Hide Amazon loader when user leaves Amazon tab
        hideAmazonLoader();

        // Do NOT stop background request/progress
        loadShopify();
    }

    function renderShopifyTable(data) {
        // Destroy & show message if NO data is loaded from the backend
        if (!data || data.length === 0) {
            $('#shopifyTableWrapper').hide();
            $('#shopifyNoDataMsg').show();
            if ($.fn.DataTable.isDataTable('#shopifyTable')) {
                dtShopify.destroy();
                dtShopify = null;
            }
            $('#totalCount, #syncedCount, #pendingCount, #errorCount').text('0');
            return;
        }

        // Show table wrapper, hide message
        $('#shopifyNoDataMsg').hide();
        $('#shopifyTableWrapper').show();

        if ($.fn.DataTable.isDataTable('#shopifyTable')) {
            // Update table elegantly via AJAX
            dtShopify.clear().rows.add(data).draw();
        } else {
            // Initialize DataTables
            dtShopify = $('#shopifyTable').DataTable({
                data: data,
                pageLength: parseInt(savedShopifyLength),
                ordering: true, // Enables Asc/Desc clicking
                dom: 'rt<"saas-pagination-wrapper"ip>', // Hides default search/length, uses ours
                language: {
                    emptyTable: "No matching records found"
                },
                columns: [{
                        data: 'product',
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'filter') return (row.product || '') + ' ' + (row.variant || '');
                            return `
                            <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                <img src="${row.image || 'https://via.placeholder.com/46'}" class="product-img flex-shrink-0">
                                <div style="min-width: 0;">
                                    <div class="fw-bold text-dark product-title-clamp" title="${row.product || 'Product'}">
                                        ${(row.product || 'Product').length > 20 ? (row.product || 'Product').substring(0, 20) + '...' : (row.product || 'Product')}
                                    </div>
                                    <small class="text-muted product-title-clamp">${row.variant || ''}</small>
                                </div>
                            </div>`;
                        }
                    },
                    {
                        data: 'sku',
                        render: function(data, type, row) {
                            let sku = row.sku || 'No SKU';
                            if (type === 'sort' || type === 'filter') return sku;
                            let display = sku.length > 20 ? sku.substring(0, 20) + '...' : sku;
                            return `<span title="${sku}" data-bs-toggle="tooltip" data-bs-container="body" data-bs-placement="top" style="cursor:pointer;">${display}</span>`;
                        }
                    },
                    {
                        data: 'mapped_sku',
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'filter') return row.mapped_sku || '';
                            if (row.mapped_sku) {
                                let url = "{{ route('user.product.amazonView', ['sku' => '__SKU__']) }}".replace('__SKU__', encodeURIComponent(row.mapped_sku));
                                return `<a href="${url}" class="text-decoration-none">${row.mapped_sku}</a>`;
                            }
                            return `<span class="text-muted">N/A</span>`;
                        }
                    },
                    {
                        data: 'available',
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'filter') return (row.available !== null && row.available !== undefined) ? row.available : -999999;
                            const availableVal = (row.available !== null && row.available !== undefined) ? row.available : '';
                            const availablePlaceholder = (row.available === null || row.available === undefined) ? 'Unknown' : '';
                            return `<input type="number" value="${availableVal}" data-original="${availableVal}" placeholder="${availablePlaceholder}" class="form-control form-control-sm qty-input" min="0">`;
                        }
                    },
                    {
                        data: 'status',
                        render: function(data, type, row) {
                            if (type === 'filter') return getMappedStatus(row.status, 'shopify');
                            return badge(row.status, 'shopify');
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: function(data, type, row) {
                            const availableVal = (row.available !== null && row.available !== undefined) ? row.available : '';
                            let mapBtn = row.is_mapped ?
                                `<button class="btn btn-danger btn-sm unmap-product" data-mapping-id="${row.mapping_id}" title="Unmap Product" data-bs-toggle="tooltip" data-bs-placement="top"><i class="bi bi-link"></i></button>` :
                                `<button class="btn btn-primary btn-sm map-amazon-product btn-icon-only" data-product="${row.pid}" data-variant="${row.vid}" data-inventory-item="${row.inventory_item_id}" title="Map Product" data-bs-toggle="tooltip" data-bs-placement="top"><i class="bi bi-link-45deg"></i></button>`;

                            return `
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <button class="btn btn-success btn-sm update-shopify-inventory" data-baseline="${availableVal}" data-product="${row.pid}" data-variant="${row.vid}" data-inventory-item="${row.inventory_item_id}" title="Update Stock" data-bs-toggle="tooltip" data-bs-placement="top">Update</button>
                                ${mapBtn}
                            </div>`;
                        }
                    }
                ],
                drawCallback: function() {
                    initTooltips();
                    calculateStats(this.api(), 'shopify');
                }
            });
        }

        refreshMappingUI();
    }

    function renderAmazonTable(data, isLoading = false) {
    
        if (!data || data.length === 0) {
            $('#amazonTableWrapper').hide();

            if (isLoading) {
                $('#amazonNoDataMsg').hide();
            } else {
                $('#amazonNoDataMsg').show();
            }

            if ($.fn.DataTable.isDataTable('#amazonTable')) {
                dtAmazon.destroy();
                dtAmazon = null;
            }

            $('#totalCount, #syncedCount, #pendingCount, #errorCount').text('0');

            return;
        }

        $('#amazonNoDataMsg').hide();
        $('#amazonTableWrapper').show();

        // 1. Initialize the structure ONLY (Do NOT pass data: data here)
        if (!$.fn.DataTable.isDataTable('#amazonTable')) {
            dtAmazon = $('#amazonTable').DataTable({
                pageLength: parseInt(savedAmazonLength),
                ordering: true, // Enables Asc/Desc
                dom: 'rt<"saas-pagination-wrapper"ip>', // Hides default UI components
                language: {
                    emptyTable: "No matching records found"
                },
                columns: [{
                        data: 'title',
                        render: function(data, type, row) {
                            let title = row.title || '-';
                            if (type === 'sort' || type === 'filter') return title;
                            let display = title.length > 20 ? title.substring(0, 20) + '...' : title;
                            return `<span class="product-title-clamp" title="${title}" data-bs-toggle="tooltip" data-bs-container="body" data-bs-placement="top" style="cursor:pointer;">${display}</span>`;
                        }
                    },
                    {
                        data: 'sku',
                        render: function(data, type, row) {
                            let sku = row.sku || '-';
                            if (type === 'sort' || type === 'filter') return sku;
                            let url = "{{ route('user.product.amazonView', ['sku' => '__SKU__']) }}".replace('__SKU__', encodeURIComponent(sku));
                            return `<a class="text-dark" href="${url}">${sku}</a>`;
                        }
                    },
                    {
                        data: 'mapped_shopify_variant_id',
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'filter') return row.mapped_shopify_variant_id || '';
                            return row.mapped_shopify_variant_id ? row.mapped_shopify_variant_id : '<span class="text-muted">NA</span>';
                        }
                    },
                    {
                        data: 'qty',
                        render: function(data, type, row) {
                            let qty = (row.quantity !== null && row.quantity !== undefined) ? row.quantity : (row.qty ?? 0);
                            if (type === 'sort' || type === 'filter') return qty;
                            let isUpdatingOrVerifying = row.is_verifying || (typeof activeAmazonUpdatingSkus !== 'undefined' && !!activeAmazonUpdatingSkus[String(row.sku)]);
                            let spinner = isUpdatingOrVerifying ? `
                                <span class="spinner-border spinner-border-sm text-primary flex-shrink-0 ms-1 zeosync-row-spinner"
                                      role="status"
                                      data-bs-toggle="tooltip"
                                      data-bs-placement="top"
                                      title="Amazon verification in progress"
                                      style="width: 14px; height: 14px; border-width: 2px; cursor: help;">
                                    <span class="visually-hidden">Amazon verification in progress Check after few minutes</span>
                                </span>` : '';
                            return `
                                <div class="d-flex align-items-center">
                                    <input type="number" class="form-control form-control-sm qty-input amazon-qty" value="${qty}" data-sku="${row.sku}">
                                    ${spinner}
                                </div>`;
                        }
                    },
                    {
                        data: 'status',
                        render: function(data, type, row) {
                            if (type === 'filter') return getMappedStatus(row.status, 'amazon');
                            return badge(row.status, 'amazon');
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: function(data, type, row) {
                            let mapBtn = row.is_mapped ?
                                `<button class="btn btn-danger btn-sm unmap-product" data-mapping-id="${row.mapping_id}" title="Unmap Product" data-bs-toggle="tooltip" data-bs-placement="top"><i class="bi bi-link"></i></button>` :
                                `<button class="btn btn-primary btn-sm map-shopify-product btn-icon-only" data-sku="${row.sku}" title="Map Product" data-bs-toggle="tooltip" data-bs-placement="top"><i class="bi bi-link-45deg"></i></button>`;

                            return `
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <button class="btn btn-primary btn-sm update-amazon-qty" data-sku="${row.sku}" title="Update Stock" data-bs-toggle="tooltip" data-bs-placement="top">Update</button>
                            ${mapBtn}
                        </div>`;
                        }
                    }
                ],
                drawCallback: function() {
                    initTooltips();
                    calculateStats(this.api(), 'amazon');
                }
            });
        }

        if (Array.isArray(data) && typeof activeAmazonUpdatingSkus !== 'undefined') {
            data.forEach(item => {
                if (!item.is_verifying && activeAmazonUpdatingSkus[String(item.sku)]) {
                    delete activeAmazonUpdatingSkus[String(item.sku)];
                }
            });
        }

        // 2. Universally clear existing DOM rows and inject the new data array for every load (preserve pagination)
        dtAmazon.clear().rows.add(data).draw(false);

        const hasVerifying = Array.isArray(data) && (data.some(item => item.is_verifying === true) || (typeof activeAmazonUpdatingSkus !== 'undefined' && Object.keys(activeAmazonUpdatingSkus).length > 0));
        if (hasVerifying && activeTab === 'amazon') {
            if (window._amazonVerifyPollTimer) {
                clearTimeout(window._amazonVerifyPollTimer);
            }
            window._amazonVerifyPollTimer = setTimeout(function() {
                if (activeTab === 'amazon') {
                    loadAmazon(true);
                }
            }, 10000);
        } else if (!hasVerifying && window._amazonVerifyPollTimer) {
            clearTimeout(window._amazonVerifyPollTimer);
            window._amazonVerifyPollTimer = null;
        }

        if (!isLoading) {
            refreshMappingUI();
        }
    }

    // ==========================================
    // Custom UI Interfacing with DataTables API
    // ==========================================

    // Shopify Inputs
    $('#dtSearchShopify').on('keyup', function() {
        if (dtShopify) dtShopify.search(this.value).draw();
    });
    $('#dtStatusShopify').on('change', function() {
        if (dtShopify) dtShopify.column(4).search(this.value).draw();
    });
    $('#dtLengthShopify').on('change', function() {
        let val = parseInt(this.value, 10);
        savedShopifyLength = val; // Sync active variable
        if (dtShopify) dtShopify.page.len(val).draw();
        persistPageLength('shopify', val);
    });
    $('#dtLocationShopify').on('change', function() {
        const selectedIndex = $(this).val();
        if (selectedIndex === '' || selectedIndex === null) return;
        const shop = new URLSearchParams(window.location.search).get('shop') || '{{ $shop->shop }}';

        $.ajax({
            url: "{{ route('settings.update') }}",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                shop: shop,
                selected_location_index: selectedIndex
            },
            success: function(response) {
                if (response.success) {
                    fetch(`{{ route('shopify.inventory.refresh') }}?shop=${encodeURIComponent(shop)}&type=shopify`)
                        .then(() => {
                            loadShopify();
                        })
                        .catch(() => {
                            loadShopify();
                        });
                }
            },
            error: function(xhr) {
                showToast(xhr.responseJSON?.message ?? 'Failed to update Shopify location.', 'danger');
            }
        });
    });

    // Amazon Inputs
    $('#dtSearchAmazon').on('keyup', function() {
        if (dtAmazon) dtAmazon.search(this.value).draw();
    });
    $('#dtStatusAmazon').on('change', function() {
        if (dtAmazon) dtAmazon.column(4).search(this.value).draw();
    });
    $('#dtLengthAmazon').on('change', function() {
        let val = parseInt(this.value, 10);
        savedAmazonLength = val; // Sync active variable
        if (dtAmazon) dtAmazon.page.len(val).draw();
        persistPageLength('amazon', val);
    });

    // Mappings Inputs
    $('#dtSearchMapped').on('keyup', function() {
        if (dtMapped) dtMapped.search(this.value).draw();
    });
    $('#dtStatusMapped').on('change', function() {
        if (dtMapped) dtMapped.column(4).search(this.value).draw();
    });
    $('#dtLengthMapped').on('change', function() {
        let val = parseInt(this.value, 10);
        savedMappedLength = val; // Sync active variable
        if (dtMapped) dtMapped.page.len(val).draw();
    });
    // ==========================================
    // Viewport-Aware Tooltip System
    // ==========================================

    let activeZeoTooltip = null;
    let activeZeoTarget = null;

    function removeZeoTooltip() {
        if (activeZeoTooltip) {
            activeZeoTooltip.remove();
            activeZeoTooltip = null;
            activeZeoTarget = null;
        }
    }

    function showZeoTooltip(targetEl, text) {
        if (!text || !targetEl) return;
        removeZeoTooltip();

        const tooltip = document.createElement('div');
        tooltip.className = 'zeosync-custom-tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);

        activeZeoTooltip = tooltip;
        activeZeoTarget = targetEl;

        positionZeoTooltip(targetEl, tooltip);
        requestAnimationFrame(() => {
            if (activeZeoTooltip === tooltip) {
                tooltip.classList.add('visible');
            }
        });
    }

    function positionZeoTooltip(targetEl, tooltip) {
        if (!targetEl || !tooltip) return;

        const targetRect = targetEl.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const padding = 8;
        const spacing = 6;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        // 1. Horizontal Positioning:
        // Default: center relative to target element
        let left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);

        // If target is near the left edge or centered overflows left: open toward the right
        if (left < padding) {
            left = Math.max(padding, targetRect.left);
        }

        // If target is near the right edge or centered overflows right: open toward the left
        if (left + tooltipRect.width > viewportWidth - padding) {
            left = Math.min(viewportWidth - padding - tooltipRect.width, targetRect.right - tooltipRect.width);
        }

        // Final boundary clamp to guarantee tooltip stays strictly within [padding, viewportWidth - padding]
        left = Math.max(padding, Math.min(left, viewportWidth - tooltipRect.width - padding));

        // 2. Vertical Positioning:
        const spaceBelow = viewportHeight - targetRect.bottom;
        const spaceAbove = targetRect.top;

        let top;
        // Prefer below the element if there is enough space
        if (spaceBelow >= tooltipRect.height + spacing + padding) {
            top = targetRect.bottom + spacing;
        } else if (spaceAbove >= tooltipRect.height + spacing + padding) {
            // Open above
            top = targetRect.top - tooltipRect.height - spacing;
        } else {
            // Pick whichever side has more room
            if (spaceBelow >= spaceAbove) {
                top = targetRect.bottom + spacing;
            } else {
                top = targetRect.top - tooltipRect.height - spacing;
            }
        }

        // Final vertical boundary clamp
        top = Math.max(padding, Math.min(top, viewportHeight - tooltipRect.height - padding));

        tooltip.style.left = `${Math.round(left)}px`;
        tooltip.style.top = `${Math.round(top)}px`;
    }

    // Delegated event handlers for all tooltips
    $(document).on('mouseenter', '[data-bs-toggle="tooltip"], [data-tooltip], .zeosync-tooltip', function() {
        let $el = $(this);
        let title = $el.attr('data-tooltip') || $el.attr('data-original-title') || $el.attr('title') || $el.attr('data-bs-title');
        if (title && String(title).trim() !== '') {
            $el.attr('data-original-title', title);
            $el.removeAttr('title');
            showZeoTooltip(this, title);
        }
    });

    $(document).on('mouseleave', '[data-bs-toggle="tooltip"], [data-tooltip], .zeosync-tooltip', function() {
        removeZeoTooltip();
    });

    $(document).on('click', '[data-bs-toggle="tooltip"], [data-tooltip], .zeosync-tooltip', function() {
        removeZeoTooltip();
    });

    window.addEventListener('scroll', function() {
        removeZeoTooltip();
    }, { passive: true });

    window.addEventListener('resize', function() {
        if (activeZeoTooltip && activeZeoTarget) {
            positionZeoTooltip(activeZeoTarget, activeZeoTooltip);
        }
    }, { passive: true });

    function initTooltips() {
        removeZeoTooltip();
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                const instance = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (instance) instance.dispose();
            });
        }
    }

    function refreshCache(event) {
        const btn = event.currentTarget;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = 'Refreshing...';
        btn.disabled = true;

        const shop = new URLSearchParams(window.location.search).get('shop');
        const type = activeTab;

        fetch(`{{ route('shopify.inventory.refresh') }}?shop=${encodeURIComponent(shop)}&type=${type}`)
            .then(() => {
                if (activeTab === 'shopify') {
                    loadShopify();
                } else {
                    amazonProductsCache = null;
                    loadAmazon(true);
                }
            })
            .finally(() => {
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            });
    }

    function showAmazonLoader() {
        $('#amazonLoader').removeClass('d-none');
        $('#amazonProgressPercent').text('0%');
        $('#amazonProgressMessage').text(
            'Calling Amazon... You can continue working while this is running.'
        );
    }

    function hideAmazonLoader() {
        $('#amazonLoader').addClass('d-none');
    }

    function startProgress() {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }

        progressTimer = setInterval(function() {
            $.ajax({
                url: "{{ route('inventory.amazon.progress') }}",
                type: "GET",
                data: {
                    shop: new URLSearchParams(window.location.search).get("shop")
                },
                success: function(res) {
                    const percent = res.percent ?? 0;

                    $("#amazonProgressPercent").text(percent + "%");
                    $("#amazonProgressMessage").text(
                        res.message ?? "Preparing..."
                    );

                    if (percent >= 100) {
                        clearInterval(progressTimer);
                        progressTimer = null;

                        // Force fresh Amazon inventory request.
                        amazonProductsCache = null;

                        loadAmazon(true);
                    }
                }
            });
        }, 1000);
    }

    // ==========================================
    // Ajax Action Handlers
    // ==========================================

    $(document).on('click', '.unmap-product', function() {
        if (!confirm('Are you sure you want to unmap this product?')) return;
        const mappingId = $(this).data('mapping-id');
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.ajax({
            url: "{{ route('inventory.unmap', ':id') }}".replace(':id', mappingId),
            type: "DELETE",
            data: {
                _token: "{{ csrf_token() }}",
                shop: shop
            },
            success: function(response) {
                alert(response.message);
                refreshMappingUI();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message ?? 'Failed to unmap product.');
            }
        });
    });

    const activeAmazonUpdatingSkus = {};

    $(document).on('click', '.update-amazon-qty', function() {

        const button = $(this);
        const row = button.closest('tr');
        const qtyInput = row.find('.amazon-qty');
        const sku = button.data('sku');
        const quantity = qtyInput.val();

        if (quantity === '' || quantity === null || quantity === undefined) {
            showToast('Please enter a valid numeric quantity before updating.', 'warning');
            qtyInput.focus();
            return;
        }

        button.prop('disabled', true).text('Updating...');
        qtyInput.prop('disabled', true);

        // Immediate row-level spinner
        activeAmazonUpdatingSkus[String(sku)] = true;
        let inputContainer = qtyInput.parent();
        if (inputContainer.find('.zeosync-row-spinner').length === 0) {
            inputContainer.append(`
                <span class="spinner-border spinner-border-sm text-primary flex-shrink-0 ms-1 zeosync-row-spinner"
                      role="status"
                      data-bs-toggle="tooltip"
                      data-bs-placement="top"
                      title="Amazon verification in progress"
                      style="width: 14px; height: 14px; border-width: 2px; cursor: help;">
                    <span class="visually-hidden">Amazon verification in progress</span>
                </span>
            `);
            initTooltips();
        }

        const shop = new URLSearchParams(window.location.search).get('shop');

        $.ajax({
            url: `${window.location.origin}/inventory/amazon/${encodeURIComponent(sku)}/update-quantity?shop=${encodeURIComponent(shop)}`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                quantity: quantity
            },
            success: function(response) {
                showToast('Amazon inventory update submitted. Verification in progress.', 'success');
                // Silent refresh of Amazon data
                loadAmazon(true);
            },
            error: function(xhr) {
                delete activeAmazonUpdatingSkus[String(sku)];
                inputContainer.find('.zeosync-row-spinner').remove();
                showToast(
                    xhr.responseJSON?.message ?? 'Inventory update failed.',
                    'danger'
                );
            },
            complete: function() {
                button.prop('disabled', false).text('Update');
                qtyInput.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.update-shopify-inventory', function() {

        const button = $(this);
        const row = button.closest('tr');
        const qtyInput = row.find('.qty-input');

        const inventoryItemId = button.data('inventory-item');
        const quantity = qtyInput.val();
        const shop = new URLSearchParams(window.location.search).get('shop');

        if (quantity === '' || quantity === null || quantity === undefined) {
            showToast('Please enter a valid numeric quantity before updating.', 'warning');
            qtyInput.focus();
            return;
        }

        button.prop('disabled', true).text('Updating...');
        qtyInput.prop('disabled', true);

        const baseline = button.attr('data-baseline') || qtyInput.attr('data-original') || '';

        const requestData = {
            shop: shop,
            inventory_item_id: inventoryItemId,
            quantity: quantity,
            baseline_quantity: baseline !== '' ? baseline : null,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        function sendInventoryUpdate(retryCount = 0) {
            // console.log('[Shopify Inventory] Update started', {
            //     shop: shop,
            //     inventory_item_id: inventoryItemId,
            //     quantity: quantity
            // });

            $.ajax({
                url: `{{ route('inventory.shopify.update') }}?shop=${encodeURIComponent(shop)}`,
                type: 'POST',
                data: requestData,

                success: function(response) {
                    // Show success toast immediately
                    showToast(response.message, 'success');

                    // Step 2: Wait 2 seconds, then fetch fresh Shopify data
                    setTimeout(function() {
                        $.ajax({
                            url: "{{ route('shopify.inventory.shopify') }}",
                            type: 'GET',
                            data: {
                                shop: shop
                            },
                            success: function(data) {
                                const items = Array.isArray(data) ? data : [];
                                renderShopifyTable(items);
                            },
                            error: function(xhr) {
                                showToast('Inventory updated, but latest Shopify data could not be loaded.',
                                    'danger');
                            }
                        });
                    }, 2000);

                    // Refresh Amazon data if Amazon tab is active, or invalidate browser cache
                    amazonProductsCache = null;
                    if (activeTab === 'amazon') {
                        loadAmazon(true);
                    }
                },

                error: async function(xhr) {
                    console.error('[Shopify Inventory] Request failed', {
                        status: xhr.status,
                        response: xhr.responseText
                    });

                    const isRetryHeader = xhr.getResponseHeader('X-Shopify-Retry-Invalid-Session-Request') === '1'
                        || xhr.getResponseHeader('x-shopify-retry-invalid-session-request') === '1';

                    if (xhr.status === 401 && isRetryHeader && retryCount === 0 && typeof shopify !== 'undefined' && shopify.idToken) {
                        try {
                            const freshToken = await shopify.idToken();
                            if (freshToken) {
                                sendInventoryUpdate(1);
                                return;
                            }
                        } catch (tokenErr) {
                            console.warn('App Bridge token retrieval failed on 401 retry:', tokenErr);
                        }
                    }

                    showToast(
                        xhr.responseJSON?.message ?? 'Inventory update failed.',
                        'danger'
                    );
                },

                complete: function() {
                    button.prop('disabled', false).text('Update');
                    qtyInput.prop('disabled', false);
                }
            });
        }

        // Step 1: Update Shopify inventory
        sendInventoryUpdate(0);
    });


    // ==========================================
    // Modals & Mappings Logic
    // ==========================================

    function destroyShopifySelect2() {
        const $select = $('#shopifyProduct');
        if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }

    function destroyShopifyVariantSelect2() {
        const $select = $('#shopifyVariant');
        if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }

    function initOrRefreshShopifyProductSelect2() {
        const $select = $('#shopifyProduct');
        if (typeof $.fn.select2 !== 'undefined') {
            destroyShopifySelect2();
            $select.select2({
                dropdownParent: $('#mapShopifyProductModal'),
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Search / Select Shopify Product...',
                allowClear: true
            });
        }
    }

    function initOrRefreshShopifyVariantSelect2() {
        const $select = $('#shopifyVariant');
        if (typeof $.fn.select2 !== 'undefined') {
            destroyShopifyVariantSelect2();
            $select.select2({
                dropdownParent: $('#mapShopifyProductModal'),
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Select Shopify Variant...',
                allowClear: true
            });
        }
    }

    $(document).on('click', '.map-shopify-product', function() {
        $('#saveProductMapping').prop('disabled', true);
        selectedAmazonSku = $(this).data('sku');
        var routetoadd = "{{route('user.product.syncAmazonToShopify',['sku' => 'SKU_PLACEHOLDER'])}}".replace('SKU_PLACEHOLDER', encodeURIComponent(selectedAmazonSku));
        document.getElementById('newProductBtn').setAttribute('href', routetoadd);
        if (selectedAmazonSku) {
            $('#displayTargetAmazonSku').text(selectedAmazonSku);
            $('#targetAmazonProductCard').show();
        } else {
            $('#targetAmazonProductCard').hide();
        }
        $('#productActionModal').modal('show');
    });

    let currentProductHasVariants = false;
    let currentShopifyProductId = null;

    $(document).on('change', '#shopifyVariant', function() {
        if (currentProductHasVariants) {
            $('#saveProductMapping').prop('disabled', !$(this).val());
        }
    });

    function resetShopifyLocationToDefault() {
        const defaultLocId = $('#shopifyLocation').data('default-location-id');
        if (defaultLocId !== undefined && defaultLocId !== null && defaultLocId !== '') {
            $('#shopifyLocation').val(defaultLocId);
        }
    }

    $('#mapShopifyProductModal').on('show.bs.modal', function() {
        resetShopifyLocationToDefault();
    });

    $('#mapShopifyProductModal').on('hidden.bs.modal', function() {
        destroyShopifySelect2();
        destroyShopifyVariantSelect2();
        $('#shopifyProductLoadingSpinner').hide();
        $('#shopifyVariantLoadingSpinner').hide();
    });

    $(document).on('click', '#saveProductMapping', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        const selectedProductOpt = $('#shopifyProduct option:selected');
        const selectedVariantOpt = $('#shopifyVariant option:selected');
        const productId = $('#shopifyProduct').val();
        const locationId = $('#shopifyLocation').val();

        let shopifyProductId = currentShopifyProductId || selectedProductOpt.data('shopify-product') || selectedVariantOpt.data('shopify-product-id');
        let shopifyVariantId = null;
        let variantId = null;
        let inventoryItemId = null;

        if (currentProductHasVariants) {
            shopifyVariantId = selectedVariantOpt.val();
            variantId = selectedVariantOpt.val();
            inventoryItemId = selectedVariantOpt.data('inventory-item') || null;
        } else {
            // Standalone product without variants: shopify_variant_id is shopify_product_id
            shopifyVariantId = shopifyProductId;
            variantId = null;
            inventoryItemId = null;
        }

        $.ajax({
            url: "{{ route('inventory.save.mapping') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                shop: shop,
                amazon_sku: $('#amazonSku').val(),
                product_id: productId,
                variant_id: variantId,
                shopify_product_id: shopifyProductId,
                shopify_variant_id: shopifyVariantId,
                shopify_inventory_item_id: inventoryItemId,
                shopify_location_id: locationId
            },
            success: function(response) {
                // alert(response.message);
                Swal.fire({
                    text: response.message,
                    confirmButtonText: 'OK'
                });

                if (response.sync_usage) {
                    updateMappingUsageUI(response.sync_usage);
                } else if (typeof response.used !== 'undefined' && typeof response.limit !== 'undefined') {
                    updateMappingUsageUI(response);
                }

                $('#mapShopifyProductModal').modal('hide');
                refreshMappingUI();
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'warning',
                    text: xhr.responseJSON?.message ?? 'Failed to map product.',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    $(document).on('click', '#existingProductBtn', function() {
        $('#productActionModal').modal('hide');
        $('#amazonSku').val(selectedAmazonSku);
        if (selectedAmazonSku) {
            $('#displayTargetAmazonSku').text(selectedAmazonSku);
            $('#targetAmazonProductCard').show();
        } else {
            $('#targetAmazonProductCard').hide();
        }
        resetShopifyLocationToDefault();
        $('#mapShopifyProductModal').modal('show');
        loadShopifyProducts();
    });

    $(document).on('click', '#newProductBtn', function() {
        $('#productActionModal').modal('hide');
        $('#amazonSku').val(selectedAmazonSku);
    });

    function loadShopifyProducts() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        currentProductHasVariants = false;
        currentShopifyProductId = null;
        $('#saveProductMapping').prop('disabled', true);
        $('#shopifyProductLoadingSpinner').show();
        destroyShopifySelect2();
        destroyShopifyVariantSelect2();

        $.get("{{ route('inventory.shopify.products') }}", {
            shop: shop
        }, function(response) {
            $('#shopifyProductLoadingSpinner').hide();
            let html = '<option value="">Select Shopify Product</option>';
            if (response && Array.isArray(response.products)) {
                response.products.forEach(p => {
                    const title = p.title || '';
                    const shopifyId = p.shopify_id || p.id || '';
                    html += `<option value="${p.id}" data-shopify-product="${shopifyId}">${escapeHtmlText(title)}</option>`;
                });
            }
            $('#shopifyProduct').html(html).prop('disabled', false);
            $('#shopifyVariant').html('<option value="">Select Product First</option>').prop('disabled', true);
            initOrRefreshShopifyProductSelect2();
        }).fail(function() {
            $('#shopifyProductLoadingSpinner').hide();
            $('#shopifyProduct').html('<option value="" disabled selected>Failed to load products</option>').prop('disabled', true);
            $('#shopifyVariant').html('<option value="">Select Product First</option>').prop('disabled', true);
        });
    }

    $(document).on('change', '#shopifyProduct', function() {
        const productId = $(this).val();
        $('#saveProductMapping').prop('disabled', true);
        currentProductHasVariants = false;
        currentShopifyProductId = null;
        destroyShopifyVariantSelect2();

        if (!productId) {
            $('#shopifyVariant').html('<option value="">Select Product First</option>').prop('disabled', true);
            return;
        }

        const selectedOpt = $(this).find('option:selected');
        currentShopifyProductId = selectedOpt.data('shopify-product') || null;

        $('#shopifyVariantLoadingSpinner').show();
        $('#shopifyVariant').html('<option value="" disabled selected>Loading variants...</option>').prop('disabled', true);

        const shop = new URLSearchParams(window.location.search).get('shop');
        $.get("{{ url('inventory/shopify-product-variants') }}/" + productId, {
            shop: shop
        }, function(response) {
            $('#shopifyVariantLoadingSpinner').hide();
            if (response.shopify_product_id) {
                currentShopifyProductId = response.shopify_product_id;
            }

            // Case B: No variants exist
            if (!response.success || !response.has_variants || !response.variants || response.variants.length === 0) {
                currentProductHasVariants = false;
                $('#shopifyVariant').html('<option value="">No variants available</option>').prop('disabled', true);
                $('#saveProductMapping').prop('disabled', false);
                return;
            }

            // Case A: Product has variants
            currentProductHasVariants = true;
            let html = '<option value="">Select Variant</option>';
            response.variants.forEach(v => {
                const title = v.title || 'Default Title';
                const invId = v.inventory_item_id || '';
                html += `<option value="${v.id}" data-inventory-item="${invId}" data-shopify-product-id="${response.shopify_product_id}">${escapeHtmlText(title)}</option>`;
            });
            $('#shopifyVariant').html(html).prop('disabled', false);
            initOrRefreshShopifyVariantSelect2();
            $('#saveProductMapping').prop('disabled', !$('#shopifyVariant').val());
        }).fail(function() {
            $('#shopifyVariantLoadingSpinner').hide();
            currentProductHasVariants = false;
            $('#shopifyVariant').html('<option value="">Failed to load variants</option>').prop('disabled', true);
            $('#saveProductMapping').prop('disabled', true);
        });
    });

    $(document).on('click', '#continueAmazonMapping', function() {
        const type = $('input[name="mapping_type"]:checked').val();
        $('#mapAmazonProductModal').modal('hide');
        if (type === 'existing') $('#existingAmazonProductModal').modal('show');
    });

    $(document).on('click', '#newAmazonProductBtn', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        let url = "{{ route('user.product.syncShopifyToAmazon', ['id' => '__ID__']) }}".replace('__ID__', selectedShopifyProductId) + '?shop=' + encodeURIComponent(shop);
        window.location.href = url;
    });

    $(document).on('click', '.map-amazon-product', function() {
        selectedShopifyProductId = $(this).data('product');
        selectedShopifyVariantId = $(this).data('variant');
        $('#amazonProductActionModal').modal('show');
    });

    $(document).on('click', '#saveAmazonProductMapping', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.ajax({
            url: "{{ route('inventory.save.amazon.mapping') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                shop: shop,
                product_id: selectedShopifyProductId,
                shopify_variant_id: selectedShopifyVariantId,
                amazon_sku: $('#amazonProduct').val()
            },
            success: function(response) {
                // alert(response.message);
                Swal.fire({
                    text: response.message,
                    confirmButtonText: 'OK'
                });

                if (response.sync_usage) {
                    updateMappingUsageUI(response.sync_usage);
                } else if (typeof response.used !== 'undefined' && typeof response.limit !== 'undefined') {
                    updateMappingUsageUI(response);
                }

                $('#mapAmazonProductModal').modal('hide');
                refreshMappingUI();
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'warning',
                    text: xhr.responseJSON?.message ?? 'Failed to map product.',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // ==========================================
    // Existing Amazon Product Mapping Selector State & Polished UI
    // ==========================================
    let existingAmazonProductLoading = false;
    let existingAmazonProductRequestId = 0;
    let amazonMappingPollTimer = null;
    let amazonMappingPollCount = 0;
    const MAX_AMAZON_MAPPING_POLL_ATTEMPTS = 25; // ~50s at 2s interval

    function escapeHtmlText(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function matchAmazonProduct(params, data) {
        if ($.trim(params.term) === '') {
            return data;
        }
        if (typeof data.text === 'undefined') {
            return null;
        }

        const term = params.term.toLowerCase().trim();
        const $el = $(data.element);
        const title = ($el.data('title') || data.text || '').toString().toLowerCase();
        const sku = ($el.data('sku') || data.id || '').toString().toLowerCase();
        const asin = ($el.data('asin') || '').toString().toLowerCase();

        if (title.indexOf(term) > -1 || sku.indexOf(term) > -1 || asin.indexOf(term) > -1) {
            return data;
        }
        return null;
    }

    function formatAmazonOption(item) {
        if (!item.id || !item.element) {
            return item.text;
        }
        const $el = $(item.element);
        const title = $el.data('title') || item.text;
        const sku = $el.data('sku') || item.id;
        const asin = $el.data('asin') || '';

        const asinBadge = asin ? `<span class="badge bg-light text-secondary border me-1" style="font-size: 0.7rem; font-weight: 500;">ASIN: ${escapeHtmlText(asin)}</span>` : '';
        const skuBadge = sku ? `<span class="badge bg-light text-primary border" style="font-size: 0.7rem; font-weight: 500;">SKU: ${escapeHtmlText(sku)}</span>` : '';

        const $container = $(`
            <div class="amazon-option-item py-1">
                <div class="amazon-option-title" title="${escapeHtmlText(title)}">
                    ${escapeHtmlText(title)}
                </div>
                <div class="amazon-option-meta d-flex flex-wrap gap-1 mt-1">
                    ${skuBadge}
                    ${asinBadge}
                </div>
            </div>
        `);
        return $container;
    }

    function formatAmazonSelection(item) {
        if (!item.id || !item.element) {
            return item.text;
        }
        const $el = $(item.element);
        const title = $el.data('title') || item.text;
        const sku = $el.data('sku') || item.id;
        let shortTitle = title;
        if (shortTitle.length > 50) {
            shortTitle = shortTitle.substring(0, 50) + '...';
        }
        return `${shortTitle} (${sku})`;
    }

    function destroyAmazonSelect2() {
        const $select = $('#amazonProduct');
        if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }

    function initOrRefreshAmazonSelect2() {
        const $select = $('#amazonProduct');
        if (typeof $.fn.select2 !== 'undefined') {
            destroyAmazonSelect2();
            $select.select2({
                dropdownParent: $('#mapAmazonProductModal'),
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Search Amazon products by Title, SKU, ASIN...',
                allowClear: true,
                matcher: matchAmazonProduct,
                templateResult: formatAmazonOption,
                templateSelection: formatAmazonSelection
            });
        }
    }

    function updateSelectedAmazonProductSummary(selectedSku) {
        if (selectedSku) {
            const $selectedOpt = $('#amazonProduct').find(`option[value="${selectedSku}"]`);
            const title = $selectedOpt.data('title') || $selectedOpt.text() || selectedSku;
            const sku = $selectedOpt.data('sku') || selectedSku;
            const asin = $selectedOpt.data('asin') || '';

            $('#selectedAmazonTitle').text(title).attr('title', title);
            $('#selectedAmazonSku').text(sku);
            if (asin) {
                $('#selectedAmazonAsin').text(asin);
                $('#selectedAmazonAsinWrap').show();
            } else {
                $('#selectedAmazonAsinWrap').hide();
            }
            $('#selectedAmazonProductSummary').slideDown(150);
        } else {
            $('#selectedAmazonProductSummary').slideUp(150);
        }
    }

    function clearAmazonMappingPolling() {
        if (amazonMappingPollTimer) {
            clearTimeout(amazonMappingPollTimer);
            amazonMappingPollTimer = null;
        }
        amazonMappingPollCount = 0;
    }

    function setAmazonProductSelectorLoading(message = 'Loading Amazon products...') {
        destroyAmazonSelect2();
        $('#selectedAmazonProductSummary').hide();
        $('#amazonProductLoadingSpinner').show();
        $('#amazonMappingStatusContainer').html(`
            <div class="alert alert-light border py-2 px-3 mb-0 d-flex align-items-center text-muted" style="font-size: 0.8rem; border-radius: 8px;">
                <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
                <span>${escapeHtmlText(message)}</span>
            </div>
        `).show();
        $('#amazonProduct').html(`<option value="" disabled selected>${escapeHtmlText(message)}</option>`).prop('disabled', true);
        $('#saveAmazonProductMapping').prop('disabled', true);
    }

    function setAmazonProductSelectorSyncing() {
        destroyAmazonSelect2();
        $('#selectedAmazonProductSummary').hide();
        $('#amazonProductLoadingSpinner').show();
        $('#amazonMappingStatusContainer').html(`
            <div class="alert alert-warning py-2 px-3 mb-0 d-flex align-items-center border-0 bg-warning-subtle text-warning-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                <span class="spinner-border spinner-border-sm text-warning me-2" role="status"></span>
                <div>
                    <strong>Amazon inventory is syncing...</strong>
                    <div class="small text-muted">Please wait while we load your Amazon products.</div>
                </div>
            </div>
        `).show();
        $('#amazonProduct').html('<option value="" disabled selected>Amazon inventory is syncing... please wait</option>').prop('disabled', true);
        $('#saveAmazonProductMapping').prop('disabled', true);
    }

    function setAmazonProductSelectorSuccess(unmappedProducts) {
        $('#amazonProductLoadingSpinner').hide();
        $('#amazonMappingStatusContainer').hide().empty();

        let options = '<option value="">Select Amazon Product</option>';
        unmappedProducts.forEach(item => {
            const rawTitle = item.title || item.sku || '';
            const rawSku = item.sku || '';
            const rawAsin = item.asin || '';
            let displayTitle = rawTitle;
            if (displayTitle.length > 55) {
                displayTitle = displayTitle.substring(0, 55) + '...';
            }
            options += `<option value="${escapeHtmlText(rawSku)}" data-sku="${escapeHtmlText(rawSku)}" data-asin="${escapeHtmlText(rawAsin)}" data-title="${escapeHtmlText(rawTitle)}">
                ${escapeHtmlText(displayTitle)} (${escapeHtmlText(rawSku)})
            </option>`;
        });

        $('#amazonProduct').html(options).prop('disabled', false);
        initOrRefreshAmazonSelect2();

        const currentVal = $('#amazonProduct').val();
        $('#saveAmazonProductMapping').prop('disabled', !currentVal);
        updateSelectedAmazonProductSummary(currentVal);
    }

    function setAmazonProductSelectorAllMapped() {
        destroyAmazonSelect2();
        $('#selectedAmazonProductSummary').hide();
        $('#amazonProductLoadingSpinner').hide();
        $('#amazonMappingStatusContainer').html(`
            <div class="alert alert-info py-2 px-3 mb-0 d-flex align-items-center border-0 bg-info-subtle text-info-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                <i class="fas fa-info-circle text-info me-2 fs-6"></i>
                <span>All Amazon products in your inventory are already mapped to Shopify variants.</span>
            </div>
        `).show();
        $('#amazonProduct').html('<option value="" disabled selected>All Amazon products are already mapped</option>').prop('disabled', true);
        $('#saveAmazonProductMapping').prop('disabled', true);
    }

    function setAmazonProductSelectorEmpty() {
        destroyAmazonSelect2();
        $('#selectedAmazonProductSummary').hide();
        $('#amazonProductLoadingSpinner').hide();
        $('#amazonMappingStatusContainer').html(`
            <div class="alert alert-warning py-2 px-3 mb-0 d-flex align-items-center border-0 bg-warning-subtle text-warning-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                <i class="fas fa-exclamation-triangle text-warning me-2 fs-6"></i>
                <span>No Amazon products found in your Amazon account.</span>
            </div>
        `).show();
        $('#amazonProduct').html('<option value="" disabled selected>No Amazon products found in account</option>').prop('disabled', true);
        $('#saveAmazonProductMapping').prop('disabled', true);
    }

    function setAmazonProductSelectorNotConnected() {
        destroyAmazonSelect2();
        $('#selectedAmazonProductSummary').hide();
        $('#amazonProductLoadingSpinner').hide();
        const shop = new URLSearchParams(window.location.search).get('shop');
        const connectUrl = "{{ route('amazon.connect') }}" + (shop ? ('?shop=' + encodeURIComponent(shop)) : '');
        $('#amazonMappingStatusContainer').html(`
            <div class="alert alert-danger py-2 px-3 mb-0 border-0 bg-danger-subtle text-danger-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="fas fa-plug text-danger me-1 fs-6"></i>
                        <span>Amazon account is not connected.</span>
                    </div>
                    <a href="${connectUrl}" class="btn btn-sm btn-danger py-0 px-2 fw-medium" style="font-size: 0.75rem;">Connect</a>
                </div>
            </div>
        `).show();
        $('#amazonProduct').html('<option value="" disabled selected>Amazon account not connected</option>').prop('disabled', true);
        $('#saveAmazonProductMapping').prop('disabled', true);
    }

    function setAmazonProductSelectorError(errorMessage = 'Failed to load Amazon products.', isTimeout = false) {
        destroyAmazonSelect2();
        $('#selectedAmazonProductSummary').hide();
        $('#amazonProductLoadingSpinner').hide();
        $('#amazonMappingStatusContainer').html(`
            <div class="alert alert-danger py-2 px-3 mb-0 d-flex align-items-center justify-content-between border-0 bg-danger-subtle text-danger-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                <div>
                    <i class="fas fa-exclamation-circle text-danger me-1 fs-6"></i>
                    <span>${escapeHtmlText(errorMessage)}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium retry-amazon-mapping-btn" style="font-size: 0.75rem;">
                    <i class="fas fa-redo-alt me-1"></i> Retry
                </button>
            </div>
        `).show();
        $('#amazonProduct').html(`<option value="" disabled selected>${escapeHtmlText(errorMessage)}</option>`).prop('disabled', true);
        $('#saveAmazonProductMapping').prop('disabled', true);
    }

    function renderAmazonProductSelector(response, isClientCache = false) {
        if (!response || typeof response !== 'object') {
            setAmazonProductSelectorError('Invalid response received from server.');
            return;
        }

        if (response.connected === false || response.status?.error === 'amazon_not_connected') {
            clearAmazonMappingPolling();
            setAmazonProductSelectorNotConnected();
            return;
        }

        const isRefreshing = response.status?.refreshing === true;
        const products = Array.isArray(response.products) ? response.products : [];

        // State B: Background sync running and no products loaded yet
        if (isRefreshing && products.length === 0) {
            setAmazonProductSelectorSyncing();
            scheduleAmazonMappingPoll();
            return;
        }

        // Real products response or completed sync
        clearAmazonMappingPolling();

        const totalCount = products.length;
        const unmapped = products.filter(item => !item.is_mapped);
        const unmappedCount = unmapped.length;

        if (totalCount === 0) {
            // State D: Zero total products
            setAmazonProductSelectorEmpty();
        } else if (unmappedCount === 0) {
            // State C: All products mapped
            setAmazonProductSelectorAllMapped();
        } else {
            // State A: Unmapped products exist
            setAmazonProductSelectorSuccess(unmapped);
        }
    }

    function scheduleAmazonMappingPoll() {
        if (amazonMappingPollTimer) return;

        amazonMappingPollCount++;
        if (amazonMappingPollCount > MAX_AMAZON_MAPPING_POLL_ATTEMPTS) {
            clearAmazonMappingPolling();
            setAmazonProductSelectorError('Amazon inventory is taking longer than expected.', true);
            return;
        }

        amazonMappingPollTimer = setTimeout(function() {
            amazonMappingPollTimer = null;
            loadAmazonProductsForMapping(false, true);
        }, 2000);
    }

    function loadAmazonProductsForMapping(forceFresh = false, isPoll = false) {
        const currentRequestId = ++existingAmazonProductRequestId;
        const shop = new URLSearchParams(window.location.search).get('shop');

        if (!isPoll) {
            setAmazonProductSelectorLoading('Loading Amazon products...');

            // Fast-path: If client cache exists and is non-empty, render it immediately for 0ms delay
            if (!forceFresh && Array.isArray(amazonProductsCache) && amazonProductsCache.length > 0) {
                const unmappedCached = amazonProductsCache.filter(item => !item.is_mapped);
                if (unmappedCached.length > 0) {
                    setAmazonProductSelectorSuccess(unmappedCached);
                }
            }
        }

        $.ajax({
            url: "{{ route('shopify.inventory.amazon') }}",
            type: 'GET',
            data: { shop: shop },
            dataType: 'json',
            success: function(response) {
                if (currentRequestId !== existingAmazonProductRequestId) {
                    return; // Ignore stale responses
                }

                // If response has products, update the global client cache too
                if (Array.isArray(response?.products) && response.products.length > 0) {
                    amazonProductsCache = response.products;
                }

                renderAmazonProductSelector(response, false);
            },
            error: function(xhr) {
                if (currentRequestId !== existingAmazonProductRequestId) {
                    return;
                }
                clearAmazonMappingPolling();
                setAmazonProductSelectorError('Failed to load Amazon products.');
            },
            complete: function() {
                existingAmazonProductLoading = false;
                $('#existingAmazonProductBtn').prop('disabled', false).html('<i class="fas fa-boxes me-1"></i> Existing Amazon Product');
            }
        });
    }

    $(document).on('click', '#existingAmazonProductBtn', function() {
        if (existingAmazonProductLoading) {
            return;
        }
        existingAmazonProductLoading = true;

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Loading...');

        // Transition modals smoothly
        $('#amazonProductActionModal').modal('hide');
        $('#mapAmazonProductModal').modal('show');

        loadAmazonProductsForMapping(false, false);
    });

    $(document).on('change', '#amazonProduct', function() {
        const val = $(this).val();
        $('#saveAmazonProductMapping').prop('disabled', !val);
        updateSelectedAmazonProductSummary(val);
    });

    $(document).on('click', '.retry-amazon-mapping-btn', function() {
        clearAmazonMappingPolling();
        loadAmazonProductsForMapping(true, false);
    });

    $('#mapAmazonProductModal').on('hidden.bs.modal', function() {
        clearAmazonMappingPolling();
        existingAmazonProductLoading = false;
        $('#existingAmazonProductBtn').prop('disabled', false).html('<i class="fas fa-boxes me-1"></i> Existing Amazon Product');
        $('#amazonProductLoadingSpinner').hide();
        $('#amazonMappingStatusContainer').hide().empty();
        destroyAmazonSelect2();
        $('#amazonProduct').val('').trigger('change');
        $('#selectedAmazonProductSummary').hide();
    });

</script>
@endpush