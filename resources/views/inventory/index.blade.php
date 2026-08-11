@extends('layouts.app')

@section('content')

@include('inventory.partials.map-shopify-product-modal')
@include('inventory.partials.map-amazon-product-modal')

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
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
</style>

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

        <div class="saas-usage-box">
            <div class="saas-usage-header">
                <div>
                    <span class="fw-semibold text-dark">Mapping Usage</span>
                    @if(!empty($syncUsage['plan_name']))
                    <span class="text-muted ms-1">({{ $syncUsage['plan_name'] }})</span>
                    @endif
                </div>
                <span class="fw-bold text-dark">{{ $syncUsage['used'] }} / {{ $syncUsage['limit'] }}</span>
            </div>
            <div class="saas-usage-progress">
                <div class="saas-usage-progress-bar {{ $progressClass }}" role="progressbar" style="width: {{ min($percentage, 100) }}%"></div>
            </div>
            @if($syncUsage['remaining'] == 0)
            <div class="text-danger fw-bold" style="font-size: 11px;">Sync limit reached</div>
            @elseif($syncUsage['remaining'] <= 10)
                <div class="text-warning fw-bold" style="font-size: 11px;">Running low
        </div>
        @else
        <div class="text-muted" style="font-size: 11px;">Remaining: <span class="fw-bold text-dark">{{ $syncUsage['remaining'] }}</span></div>
        @endif
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
        <ul class="nav nav-tabs saas-tabs">
            <li class="nav-item">
                <button class="nav-link active"
                    data-bs-toggle="tab"
                    data-bs-target="#shopifyTab"
                    onclick="switchToShopifyTab();">
                    Shopify
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#amazonTab"
                    onclick="switchToAmazonTab();">
                    Amazon
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content">

        {{-- Shopify Tab --}}
        <div class="tab-pane fade show active" id="shopifyTab">
            <div class="saas-toolbar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Search Product</label>
                        <input type="text" id="dtSearchShopify" class="saas-input" placeholder="Search SKU / Product...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Status Filter</label>
                        <select id="dtStatusShopify" class="saas-select">
                            <option value="">All Status</option>
                            <option value="synced">Synced</option>
                            <option value="pending">Pending</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Rows Per Page</label>
                        <select id="dtLengthShopify" class="saas-select">
                            <option value="10">10 Rows</option>
                            <option value="25">25 Rows</option>
                            <option value="50">50 Rows</option>
                            <option value="100">100 Rows</option>
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
        <div class="tab-pane fade" id="amazonTab">
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
                            <option value="10">10 Rows</option>
                            <option value="25">25 Rows</option>
                            <option value="50">50 Rows</option>
                            <option value="100">100 Rows</option>
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
<!-- DataTables JS Files -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    const amazonConnected = @json(!empty($shop -> amazon_refresh_token));
    let selectedAmazonSku = null;
    let selectedShopifyVariantId = null;
    let selectedShopifyProductId = null;
    let activeTab = 'shopify';

    let dtShopify = null;
    let dtAmazon = null;
    let progressTimer = null;

    // Read saved page length from browser memory (default to 10)
    let savedShopifyLength = localStorage.getItem('zeosync_shopify_length') || 10;
    let savedAmazonLength = localStorage.getItem('zeosync_amazon_length') || 10;

    // On load, update the dropdown UI to match the saved memory
    document.addEventListener('DOMContentLoaded', function() {
        $('#dtLengthShopify').val(savedShopifyLength);
        $('#dtLengthAmazon').val(savedAmazonLength);
        loadShopify(); // Your existing load call
    });

    function getMappedStatus(rawStatus, tab) {
        let original = (rawStatus || '').toLowerCase();
        if (tab === 'amazon') {
            if (original === 'active') return 'synced';
            if (original === 'inactive' || original === 'incomplete') return 'pending';
        }
        if (original === 'synced') return 'synced';
        if (original === 'pending') return 'pending';
        return 'error';
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
            case 'pending':
                return `<span class="soft-badge bg-warning-subtle text-warning">Pending</span>`;
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

    function loadAmazon() {
        if (!amazonConnected) return;

        activeTab = 'amazon';

        // Show loader only when no Amazon products are currently visible
        const hasProducts = $('#amazonTable tbody tr').length > 0;

        if (!hasProducts) {
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

                console.log('AMAZON RESPONSE:', response);
                console.log('REFRESHING:', response.status?.refreshing);

                let items = response.products ?? [];
                let isRefreshing = response.status?.refreshing === true;

                // While refresh is running, don't show "No Inventory Found"
                renderAmazonTable(items, isRefreshing);

                if (isRefreshing) {

                    // No products yet → keep loader visible
                    if (items.length === 0) {
                        showAmazonLoader();
                    } else {
                        // Existing products are already visible
                        hideAmazonLoader();
                    }

                    startProgress();

                } else {

                    // Final response received
                    hideAmazonLoader();

                    if (progressTimer) {
                        clearInterval(progressTimer);
                        progressTimer = null;
                    }
                }
            },

            error: function(xhr) {
                if ($('#amazonTable tbody tr').length === 0) {
                    renderAmazonTable([], false);
                }

                hideAmazonLoader();

                if (progressTimer) {
                    clearInterval(progressTimer);
                    progressTimer = null;
                }
            }
        });
    }

    function switchToAmazonTab() {
        activeTab = 'amazon';
        loadAmazon();
    }

    function switchToShopifyTab() {
        activeTab = 'shopify';

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
                            if (type === 'sort' || type === 'filter') return row.available || 0;
                            return `<input type="number" value="${row.available || 0}" class="form-control form-control-sm qty-input">`;
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
                            let mapBtn = row.is_mapped ?
                                `<button class="btn btn-danger btn-sm unmap-product" data-mapping-id="${row.mapping_id}" title="Unmap Product" data-bs-toggle="tooltip" data-bs-placement="top"><i class="bi bi-link"></i></button>` :
                                `<button class="btn btn-primary btn-sm map-amazon-product btn-icon-only" data-product="${row.pid}" data-variant="${row.vid}" data-inventory-item="${row.inventory_item_id}" title="Map Product" data-bs-toggle="tooltip" data-bs-placement="top"><i class="bi bi-link-45deg"></i></button>`;

                            return `
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <button class="btn btn-success btn-sm update-shopify-inventory" data-product="${row.pid}" data-variant="${row.vid}" data-inventory-item="${row.inventory_item_id}" title="Update Stock" data-bs-toggle="tooltip" data-bs-placement="top">Update</button>
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

        if ($.fn.DataTable.isDataTable('#amazonTable')) {
            // Update elegantly via AJAX
            dtAmazon.clear().rows.add(data).draw();
        } else {
            // Initialize DataTables
            dtAmazon = $('#amazonTable').DataTable({
                data: data,
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
                            let qty = row.quantity ?? row.qty ?? 0;
                            if (type === 'sort' || type === 'filter') return qty;
                            return `<input type="number" class="form-control form-control-sm qty-input amazon-qty" value="${qty}" data-sku="${row.sku}">`;
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
        let val = parseInt(this.value);
        localStorage.setItem('zeosync_shopify_length', val); // Lock in local override
        savedShopifyLength = val; // Sync active variable
        if (dtShopify) dtShopify.page.len(val).draw();
    });

    // Amazon Inputs
    $('#dtSearchAmazon').on('keyup', function() {
        if (dtAmazon) dtAmazon.search(this.value).draw();
    });
    $('#dtStatusAmazon').on('change', function() {
        if (dtAmazon) dtAmazon.column(4).search(this.value).draw();
    });
    $('#dtLengthAmazon').on('change', function() {
        let val = parseInt(this.value);
        localStorage.setItem('zeosync_amazon_length', val); // Lock in local override
        savedAmazonLength = val; // Sync active variable
        if (dtAmazon) dtAmazon.page.len(val).draw();
    });
    // ==========================================
    // Core Functions & Actions
    // ==========================================

    function initTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                const instance = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (instance) instance.dispose();
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    container: 'body',
                    boundary: document.body
                });
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
                if (activeTab === 'shopify') loadShopify();
                else loadAmazon();
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

                        hideAmazonLoader();

                        // Final cached data load - only once
                        loadAmazon();
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
                loadShopify();
                loadAmazon();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message ?? 'Failed to unmap product.');
            }
        });
    });

    $(document).on('click', '.update-amazon-qty', function() {

        const button = $(this);
        const row = button.closest('tr');
        const qtyInput = row.find('.amazon-qty');
        const sku = button.data('sku');
        const quantity = qtyInput.val();

        button.prop('disabled', true).text('Updating...');
        qtyInput.prop('disabled', true);

        const shop = new URLSearchParams(window.location.search).get('shop');

        $.ajax({
            url: `${window.location.origin}/inventory/amazon/${sku}/update-quantity?shop=${encodeURIComponent(shop)}`,
            type: 'POST',

            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },

            data: {
                quantity: quantity
            },

            success: function(response) {

                Swal.fire({
                    icon: 'success',
                    title: 'Inventory Updated',
                    text: 'Inventory updated successfully. Latest inventory will reflect in the app in approximately 15 minutes.',
                    confirmButtonText: 'OK'
                });

                // Do NOT call loadAmazon() here.
                // Amazon latest inventory comes through the report after ~15 minutes.
            },

            error: function(xhr) {

                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: xhr.responseJSON?.message ??
                        'Inventory update failed.',
                    confirmButtonText: 'OK'
                });
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

        button.prop('disabled', true).text('Updating...');
        qtyInput.prop('disabled', true);

        // Step 1: Update Shopify inventory
        $.ajax({
            url: "{{ route('inventory.shopify.update') }}",
            type: 'POST',
            data: {
                shop: shop,
                inventory_item_id: inventoryItemId,
                quantity: quantity,
                _token: $('meta[name="csrf-token"]').attr('content')
            },

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

                            // Step 3: Render latest Shopify data
                            renderShopifyTable(items);
                        },

                        error: function(xhr) {

                            console.error(
                                'Failed to refresh Shopify products:',
                                xhr.responseText
                            );

                            showToast(
                                'Inventory updated, but latest Shopify data could not be loaded.',
                                'danger'
                            );
                        }
                    });

                }, 2000);

                // Refresh Amazon data if Amazon tab is active
                if (activeTab === 'amazon') {
                    loadAmazon();
                }
            },

            error: function(xhr) {

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

    // ==========================================
    // Modals & Mappings Logic
    // ==========================================

    $(document).on('click', '.map-shopify-product', function() {
        $('#saveProductMapping').prop('disabled', true);
        selectedAmazonSku = $(this).data('sku');
        var routetoadd = "{{route('user.product.syncAmazonToShopify',['sku' => 'SKU_PLACEHOLDER'])}}".replace('SKU_PLACEHOLDER', encodeURIComponent(selectedAmazonSku));
        document.getElementById('newProductBtn').setAttribute('href', routetoadd);
        $('#productActionModal').modal('show');
    });

    $(document).on('change', '#shopifyVariant', function() {
        $('#saveProductMapping').prop('disabled', !$(this).val());
    });
    $(document).on('change', '#amazonProduct', function() {
        $('#saveAmazonProductMapping').prop('disabled', !$(this).val());
    });

    $(document).on('click', '#saveProductMapping', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        const variant = $('#shopifyVariant option:selected');
        $.ajax({
            url: "{{ route('inventory.save.mapping') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                shop: shop,
                amazon_sku: $('#amazonSku').val(),
                product_id: $('#shopifyProduct').val(),
                variant_id: variant.val(),
                shopify_product_id: variant.data('shopify-product-id'),
                shopify_variant_id: variant.val(),
                shopify_inventory_item_id: variant.data('inventory-item')
            },
            success: function(response) {
                alert(response.message);
                $('#mapShopifyProductModal').modal('hide');
                loadShopify();
            },
            error: function(xhr) {
                alert(xhr.responseJSON.message);
            }
        });
    });

    $(document).on('click', '#existingProductBtn', function() {
        $('#productActionModal').modal('hide');
        $('#amazonSku').val(selectedAmazonSku);
        $('#mapShopifyProductModal').modal('show');
        loadShopifyProducts();
    });

    $(document).on('click', '#newProductBtn', function() {
        $('#productActionModal').modal('hide');
        $('#amazonSku').val(selectedAmazonSku);
    });

    function loadShopifyProducts() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.get("{{ route('inventory.shopify.products') }}", {
            shop: shop
        }, function(response) {
            let html = '<option value="">Select Product</option>';
            response.products.forEach(p => html += `<option value="${p.id}" data-shopify-product="${p.shopify_id}">${p.title}</option>`);
            $('#shopifyProduct').html(html);
            $('#shopifyVariant').html('<option value="">Select Product First</option>').prop('disabled', true);
        });
    }

    $(document).on('change', '#shopifyProduct', function() {
        const productId = $(this).val();
        if (!productId) {
            $('#shopifyVariant').html('<option value="">Select Product First</option>').prop('disabled', true);
            return;
        }
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.get("{{ url('inventory/shopify-product-variants') }}/" + productId, {
            shop: shop
        }, function(response) {
            let html = '<option value="">Select Variant</option>';
            if (!response.success || response.variants.length === 0) {
                $('#shopifyVariant').html('<option value="">No variants available</option>').prop('disabled', true);
                return;
            }
            response.variants.forEach(v => {
                html += `<option value="${v.id}" data-inventory-item="${v.inventory_item_id}" data-shopify-product-id="${response.shopify_product_id}">${v.title}</option>`;
            });
            $('#shopifyVariant').html(html).prop('disabled', false);
        }).fail(() => $('#shopifyVariant').html('<option value="">Failed to load variants</option>').prop('disabled', true));
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
                alert(response.message);
                $('#mapAmazonProductModal').modal('hide');
                loadShopify();
                loadAmazon();
            },
            error: function(xhr) {
                alert(xhr.responseJSON.message);
            }
        });
    });

    $(document).on('click', '#existingAmazonProductBtn', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.get("{{ route('shopify.inventory.amazon') }}", {
            shop: shop
        }, function(response) {

            let items = response.products ?? [];

            let options = '<option value="">Select Amazon Product</option>';

            items
                .filter(item => !item.is_mapped)
                .forEach(item => {
                    let title = item.title || '';

                    if (title.length > 40) {
                        title = title.substring(0, 40) + '...';
                    }

                    options += `<option value="${item.sku}">
                    ${title} (${item.sku})
                </option>`;
                });

            $('#amazonProduct').html(options);

            $('#amazonProductActionModal').modal('hide');
            $('#mapAmazonProductModal').modal('show');
        }).fail(function(xhr) {
            console.error('Failed to load Amazon products:', xhr.responseText);
        });
    });
    // Boot execution
    // document.addEventListener('DOMContentLoaded', function() {
    //     loadShopify();
    // });
</script>
@endpush