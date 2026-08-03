@extends('layouts.app')

@section('content')

@include('inventory.partials.map-shopify-product-modal')
@include('inventory.partials.map-amazon-product-modal')

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

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
    }

    .saas-table td {
        padding: 6px 16px;
        /* Added small vertical padding for tighter fit */
        vertical-align: middle;
        border-bottom: 1px solid #E5E7EB;
        color: #202223;
    }

    /* Dynamic Table Layout Fixes */
    .saas-table .product-title-clamp {
        display: block;
        max-width: 180px;
        /* Constrains column growth preventing table breakout */
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

    /* Overriding JS-Rendered Elements without modifying JS code */
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

    /* Buttons Override (Targets both native HTML and JS rendered buttons) */
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

    /* Map Product Icon Button Layout Fix */
    .saas-table .btn-icon-only {
        width: 28px !important;
        /* Forces perfect square matching regular button height */
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

    /* Pagination Override (Targets JS rendered) */
    .saas-pagination-footer {
        padding: 12px 16px;
        background: #FFFFFF;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        border-top: 1px solid #E5E7EB;
    }

    .custom-pagination {
        display: flex;
        gap: 4px;
        background: transparent;
        border: none;
    }

    .page-btn {
        padding: 4px 10px;
        border: 1px solid #C9CCCF;
        border-radius: 6px !important;
        background: #FFFFFF;
        color: #202223;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        min-width: 32px;
        text-align: center;
    }

    .page-btn:hover:not(.disabled) {
        background: #F4F6F8;
    }

    .page-btn.active {
        background: #1A1A1A !important;
        color: #FFFFFF !important;
        border-color: #1A1A1A !important;
    }

    .page-btn.disabled {
        opacity: 0.5;
        background: #F9FAFB;
        cursor: not-allowed;
        border-color: #E5E7EB;
        color: #8C9196;
    }

    /* Loader */
    .amazon-loader {
        position: fixed;
        inset: 0;
        background: rgba(32, 34, 35, 0.7);
        backdrop-filter: blur(3px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 999999;
    }

    .amazon-loader.d-none {
        display: none;
    }

    .progress-percent {
        font-size: 28px;
        font-weight: 700;
        color: #FFFFFF;
        margin-top: 16px;
    }

    .progress-message {
        font-size: 14px;
        color: #E3E5E7;
        font-weight: 500;
    }

    /* Responsiveness */
    @media(max-width:576px) {
        .saas-page-header {
            flex-direction: column;
            align-items: stretch;
        }

        .saas-usage-box {
            min-width: auto;
        }

        .saas-pagination-footer {
            flex-direction: column;
            text-align: center;
            justify-content: center;
        }
    }
</style>

<div class="saas-wrapper">

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
                <div class="saas-usage-progress-bar {{ $progressClass }}"
                    role="progressbar"
                    style="width: {{ min($percentage, 100) }}%">
                </div>
            </div>

            @if($syncUsage['remaining'] == 0)
            <div class="text-danger fw-bold" style="font-size: 11px;">Sync limit reached</div>
            @elseif($syncUsage['remaining'] <= 10)
                <div class="text-warning fw-bold" style="font-size: 11px;">Running low
        </div>
        @else
        <div class="text-muted" style="font-size: 11px;">
            Remaining: <span class="fw-bold text-dark">{{ $syncUsage['remaining'] }}</span>
        </div>
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
                    id="shopify-tab-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#shopifyTab"
                    onclick="activeTab='shopify'; loadShopify();">
                    Shopify
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link"
                    id="amazon-tab-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#amazonTab"
                    onclick="activeTab='amazon'; loadAmazon();">
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
                    <div class="col-md-2 d-none">
                        <button class="btn btn-primary w-100" onclick="loadShopify()">Load Shopify</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Search Product</label>
                        <input type="text" id="searchInputs" class="saas-input" placeholder="Search SKU / Product...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Status Filter</label>
                        <select id="statusFilters" class="saas-select">
                            <option value="">All Status</option>
                            <option value="synced">Synced</option>
                            <option value="pending">Pending</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-6">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button class="btn btn-dark w-100" onclick="applyFilter('s')">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button onclick="refreshCache(event)" class="btn btn-light w-100">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="saas-table" id="shopifyTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-nowrap">SKU</th>
                            <th class="text-nowrap">Mapped To</th>
                            <th class="text-nowrap">Available</th>
                            <th class="text-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="saas-pagination-footer">
                <div class="text-muted" style="font-size: 12px;" id="paginationInfoShopify">Showing 0 to 0 of 0 results</div>
                <div class="custom-pagination" id="paginationButtonsShopify"></div>
            </div>
        </div>

        {{-- Amazon Tab --}}
        <div class="tab-pane fade" id="amazonTab">
            @if($shop->amazon_refresh_token)
            <div class="saas-toolbar">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2 d-none">
                        <button class="btn btn-warning w-100" onclick="loadAmazon()">Load Amazon</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Search Product</label>
                        <input type="text" id="searchInputa" class="saas-input" placeholder="Search SKU / Product...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold mb-1" style="font-size: 11px;">Status Filter</label>
                        <select id="statusFiltera" class="saas-select">
                            <option value="">All Status</option>
                            <option value="synced">Synced</option>
                            <option value="pending">Pending</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-6">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button class="btn btn-dark w-100" onclick="applyFilter('a')">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label d-none d-md-block mb-1">&nbsp;</label>
                        <button onclick="refreshCache(event)" class="btn btn-light w-100">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="saas-table" id="amazonTable">
                    <thead>
                        <tr>
                            <th class="text-nowrap">SKU</th>
                            <th>Product</th>
                            <th class="text-nowrap">Mapped To</th>
                            <th class="text-nowrap">Qty</th>
                            <th class="text-nowrap">Status</th>
                            <th class="text-nowrap">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="saas-pagination-footer">
                <div class="text-muted" style="font-size: 12px;" id="paginationInfoAmazon">Showing 0 to 0 of 0 results</div>
                <div class="custom-pagination" id="paginationButtonsAmazon"></div>
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

{{-- Global Full Screen Loader --}}
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
</div>

@endsection

@push('scripts')
<script>
    const amazonConnected = @json(!empty($shop -> amazon_refresh_token));
    let selectedAmazonSku = null;
    let selectedVariant = null;
    let selectedShopifyVariantId = null;
    let selectedShopifyProductId = null;
    let selectedInventoryItemId = null;
    let shopifyData = [];
    let amazonData = [];
    let filteredData = [];
    let activeTab = 'shopify';
    let currentPage = 1;
    let perPage = 10;

    function loadShopify() {
        activeTab = 'shopify';
        currentPage = 1;

        fetch(`{{ route('shopify.inventory.shopify') }}?shop={{ $shop->shop }}`)
            .then(res => res.json())
            .then(data => {
                shopifyData = Array.isArray(data) ? data : [];
                filteredData = [...shopifyData];
                renderTable();
            })
            .catch(() => {
                shopifyData = [];
                filteredData = [];
                renderTable();
            });
    }

    let progressTimer = null;

    function showAmazonLoader() {
        $('#amazonLoader').removeClass('d-none');
        $('#amazonProgressPercent').text('0%');
        $('#amazonProgressMessage').text('Preparing...');
    }

    function hideAmazonLoader() {
        $('#amazonLoader').addClass('d-none');
    }

    function startProgress() {
        if (progressTimer) {
            clearInterval(progressTimer);
        }

        progressTimer = setInterval(function() {
            $.ajax({
                url: "{{ route('inventory.amazon.progress') }}",
                type: "GET",
                data: {
                    shop: new URLSearchParams(window.location.search).get("shop"),
                },
                success: function(res) {
                    console.log("Progress:", res.percent, res.message);

                    const percent = res.percent ?? 0;

                    $("#amazonProgressPercent").text(percent + "%");
                    $("#amazonProgressMessage").text(res.message ?? "Preparing...");

                    if (percent >= 100) {

                        clearInterval(progressTimer);

                        hideAmazonLoader();

                        loadAmazon();
                    }
                },
            });
        }, 1000);
    }

    function loadAmazon() {

        if (!amazonConnected) {
            return;
        }

        console.log('Load Amazon Clicked');

        const shop = new URLSearchParams(window.location.search).get('shop');
        console.log('Shop:', shop);

        $.ajax({
            url: "{{ route('shopify.inventory.amazon') }}",
            type: 'GET',
            data: {
                shop: shop
            },
            beforeSend: function() {
                console.log('Amazon request started');
            },
            success: function(response) {

                console.log('Amazon request completed', response);

                amazonData = response.products ?? [];
                filteredData = [...amazonData];

                activeTab = 'amazon';
                currentPage = 1;

                console.log('Active Tab:', activeTab);
                console.log('Amazon Data Count:', amazonData.length);
                console.log('Filtered Data Count:', filteredData.length);
                console.log('First Amazon Item:', filteredData[0]);

                renderTable();

                if (response.status?.refreshing) {

                    console.log("Background refresh running");

                    showAmazonLoader();
                    startProgress();

                } else {

                    console.log("Serving cached inventory");

                    hideAmazonLoader();

                    if (progressTimer) {
                        clearInterval(progressTimer);
                    }
                }
            },
            error: function(xhr) {
                console.error('Amazon request failed', xhr);
                alert('Failed to load Amazon Inventory.');
            },
            complete: function() {
                console.log('Amazon AJAX complete');
            }
        });
    }

    function renderTable() {
        let data = filteredData;
        let total = data.length;
        let totalPages = Math.ceil(total / perPage) || 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        let start = (currentPage - 1) * perPage;
        let paginated = data.slice(start, start + perPage);

        let rows = '';
        let synced = 0;
        let pending = 0;
        let error = 0;

        data.forEach(item => {
            let status = (item.status || '').toLowerCase();

            // Amazon status mapping
            if (activeTab === 'amazon') {
                status = {
                    active: 'synced',
                    inactive: 'pending',
                    incomplete: 'pending'
                } [status] || 'error';
            }

            switch (status) {
                case 'synced':
                    synced++;
                    break;
                case 'pending':
                    pending++;
                    break;
                default:
                    error++;
                    break;
            }
        });

        if (paginated.length === 0) {
            rows = `
                <tr>
                    <td colspan="${activeTab === 'shopify' ? 5 : 6}" class="text-center text-muted py-5">
                        No inventory data found
                    </td>
                </tr>
            `;
        }

        paginated.forEach(item => {
            if (activeTab === 'shopify') {
                rows += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                <img src="${item.image || 'https://via.placeholder.com/46'}" class="product-img flex-shrink-0">
                                <div style="min-width: 0;">
                                    <div class="fw-bold text-dark product-title-clamp" title="${item.product || 'Product'}">
    ${(item.product || 'Product').length > 20 ? (item.product || 'Product').substring(0, 20) + '...' : (item.product || 'Product')}
</div>
                                    <small class="text-muted product-title-clamp">${item.variant || ''}</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap">
    <span
        title="${item.sku || 'No SKU'}"
        data-bs-toggle="tooltip"
        data-bs-container="body"
        data-bs-placement="top"
        style="cursor:pointer;">
        ${(item.sku || 'No SKU').length > 20
            ? (item.sku || 'No SKU').substring(0, 20) + '...'
            : (item.sku || 'No SKU')}
    </span>
</td>
                        <td class="text-nowrap">
    ${
        item.mapped_sku
            ? `<a href="{{ route('user.product.amazonView', ['sku' => '__SKU__']) }}"
                    class="text-decoration-none">
                    ${item.mapped_sku}
               </a>`.replace('__SKU__', encodeURIComponent(item.mapped_sku))
            : `<span class="text-muted">N/A</span>`
    }
</td>
                        <td>
                            <input type="number"
                                   value="${item.available || 0}"
                                   class="form-control form-control-sm qty-input">
                        </td>
                        <td class="text-nowrap">
                                    <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-success btn-sm update-shopify-inventory"
                                    data-product="${item.pid}"  data-variant="${item.vid}" data-inventory-item="${item.inventory_item_id}"
                                    title="Update Stock"  data-bs-toggle="tooltip" 
                                    data-bs-placement="top">  Update </button>
                                                            ${item.is_mapped
    ? `
        <button class="btn btn-danger btn-sm unmap-product"
                data-mapping-id="${item.mapping_id}"
                title="Unmap Product"
                data-bs-toggle="tooltip"
                data-bs-placement="top">
            <i class="bi bi-link"></i>
        </button>
    `
    : `
        <button class="btn btn-primary btn-sm map-amazon-product btn-icon-only"
                data-product="${item.pid}"
                data-variant="${item.vid}"
                data-inventory-item="${item.inventory_item_id}"
                title="Map Product"
                data-bs-toggle="tooltip"
                data-bs-placement="top">
            <i class="bi bi-link-45deg"></i>
        </button>
    `
}
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                const qty = item.quantity ?? item.qty ?? 0;
                const synced = qty > 0;
                const amazonViewUrl = "{{ route('user.product.amazonView', ['sku' => '__SKU__']) }}".replace('__SKU__', encodeURIComponent(item.sku));

                rows += `
                    <tr>
                        <td class="text-nowrap"><a class="text-dark" href="${amazonViewUrl}"> ${item.sku ?? '-'}</a></td>
                        <td>
                            <span class="product-title-clamp" title="${item.title ?? '-'}"
      data-bs-toggle="tooltip"  data-bs-container="body"  data-bs-placement="top"  style="cursor:pointer;">
    ${(item.title ?? '-').length > 20 ? (item.title ?? '-').substring(0, 20) + '...' : (item.title ?? '-')}
</span>
                        </td>
                        <td class="text-nowrap">
    ${
        item.mapped_shopify_variant_id
            ? item.mapped_shopify_variant_id
            : '<span class="text-muted">NA</span>'
    }
</td>
                        <td>
                            <input type="number"
                                   class="form-control form-control-sm qty-input amazon-qty"
                                   value="${qty}"
                                   data-sku="${item.sku}">
                        </td>
                        <td class="text-nowrap">${badge(item.status)}</td>
                        <td class="text-nowrap">
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-primary btn-sm update-amazon-qty" data-sku="${item.sku}" title="Update Stock"  data-bs-toggle="tooltip"  data-bs-placement="top">
                                Update </button>
                                ${item.is_mapped
    ? `
        <button class="btn btn-danger btn-sm unmap-product"
                data-mapping-id="${item.mapping_id}"
                title="Unmap Product"
                data-bs-toggle="tooltip"
                data-bs-placement="top">
            <i class="bi bi-link"></i>
        </button>
      `
    : `
        <button class="btn btn-primary btn-sm map-shopify-product btn-icon-only"
                data-sku="${item.sku}"
                title="Map Product"
                data-bs-toggle="tooltip"
                data-bs-placement="top">
            <i class="bi bi-link-45deg"></i>
        </button>
      `
}
                            </div>
                        </td>
                    </tr>
                `;
            }
        });

        if (activeTab === 'shopify') {
            document.querySelector('#shopifyTable tbody').innerHTML = rows;
            updatePagination('Shopify', start, total, totalPages);
        } else {
            document.querySelector('#amazonTable tbody').innerHTML = rows;
            updatePagination('Amazon', start, total, totalPages);
        }

        document.getElementById('totalCount').innerText = total;
        document.getElementById('syncedCount').innerText = synced;
        document.getElementById('pendingCount').innerText = pending;
        document.getElementById('errorCount').innerText = error;

        // Re-initialize tooltips for perfectly rendering UI in newly injected HTML blocks
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                const instance = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (instance) {
                    instance.dispose();
                }
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    container: 'body',
                    boundary: document.body
                });
            });
        }
    }

    $(document).on('click', '.unmap-product', function() {

        if (!confirm('Are you sure you want to unmap this product?')) {
            return;
        }

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

                console.log(xhr);

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

        // Disable while updating
        button.prop('disabled', true)
            .text('Updating...');

        qtyInput.prop('disabled', true);

        updateAmazonQuantity(sku, quantity, button, qtyInput);

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
                alert(response.message);
                loadShopify();
                if (activeTab === 'amazon') {
                    loadAmazon();
                }
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message ?? 'Inventory update failed.');
            },
            complete: function() {
                button.prop('disabled', false).text('Update');
                qtyInput.prop('disabled', false);
            }
        });
    });

    function updateAmazonQuantity(sku, quantity, button, qtyInput) {

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

                alert(response.message ?? 'Inventory updated successfully.');

                loadAmazon();

            },

            error: function(xhr) {

                alert(xhr.responseJSON?.message ?? 'Inventory update failed.');

            },

            complete: function() {

                button.prop('disabled', false)
                    .text('Update');

                qtyInput.prop('disabled', false);

            }
        });

    }

    function updatePagination(type, start, total, totalPages) {
        let startItem = total === 0 ? 0 : start + 1;
        let endItem = Math.min(start + perPage, total);

        document.getElementById('paginationInfo' + type).innerText =
            `Showing ${startItem} to ${endItem} of ${total} results`;

        let buttons = '';
        buttons += `<button class="page-btn ${currentPage === 1 ? 'disabled' : ''}" onclick="prevPage()">‹</button>`;

        let startPage = Math.max(1, currentPage - 1);
        let endPage = Math.min(totalPages, currentPage + 1);

        if (currentPage === 1) {
            endPage = Math.min(3, totalPages);
        }

        if (currentPage === totalPages) {
            startPage = Math.max(1, totalPages - 2);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        }

        buttons += `<button class="page-btn ${currentPage === totalPages ? 'disabled' : ''}" onclick="nextPage()">›</button>`;
        document.getElementById('paginationButtons' + type).innerHTML = buttons;
    }

    function goToPage(page) {
        currentPage = page;
        renderTable();
    }

    // ===========================
    // Map Product Button
    // ===========================
    $(document).on('click', '.map-shopify-product', function() {
        selectedVariant = null;
        $('#saveProductMapping').prop('disabled', true);
        selectedAmazonSku = $(this).data('sku');

        var routetoadd = "{{route('user.product.syncAmazonToShopify',['sku' => 'SKU_PLACEHOLDER'])}}";
        routetoadd = routetoadd.replace('SKU_PLACEHOLDER', encodeURIComponent(selectedAmazonSku));
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
        const product = $('#shopifyProduct option:selected');
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
                location.reload();
            },
            error: function(xhr) {
                console.log(xhr.responseJSON);
                alert(xhr.responseJSON.message);
            }
        });
    });

    // ===========================
    // Existing Shopify Product
    // ===========================
    $(document).on('click', '#existingProductBtn', function() {
        $('#productActionModal').modal('hide');
        $('#amazonSku').val(selectedAmazonSku);
        $('#mapShopifyProductModal').modal('show');
        loadShopifyProducts();
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, {
            container: 'body',
            boundary: document.body
        });
    });

    // ===========================
    // Add New Shopify Product
    // ===========================
    $(document).on('click', '#newProductBtn', function() {
        $('#productActionModal').modal('hide');
        $('#amazonSku').val(selectedAmazonSku);
    });

    // ===========================
    // Load Shopify Products
    // ===========================
    function loadShopifyProducts() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.get("{{ route('inventory.shopify.products') }}", {
            shop: shop
        }, function(response) {
            let html = '<option value="">Select Product</option>';
            response.products.forEach(function(product) {
                html += `
                <option
                    value="${product.id}"
                    data-shopify-product="${product.shopify_id}">
                    ${product.title}
                </option>
            `;
            });
            $('#shopifyProduct').html(html);
            $('#shopifyVariant')
                .html('<option value="">Select Product First</option>')
                .prop('disabled', true);
        });
    }

    // ===========================
    // Load Variants
    // ===========================
    function loadVariants(productId) {
        const shop = new URLSearchParams(window.location.search).get('shop');
        const url = "{{ url('inventory/shopify-product-variants') }}/" + productId;

        $.get(url, {
            shop: shop
        }, function(response) {
            let html = '<option value="">Select Variant</option>';
            if (!response.success || response.variants.length === 0) {
                $('#shopifyVariant')
                    .html('<option value="">No variants available</option>')
                    .prop('disabled', true);
                return;
            }

            response.variants.forEach(function(variant) {
                html += `
                <option
                    value="${variant.id}"
                    data-inventory-item="${variant.inventory_item_id}"
                    data-shopify-product-id="${response.shopify_product_id}">
                    ${variant.title}
                </option>
            `;
            });

            $('#shopifyVariant')
                .html(html)
                .prop('disabled', false);

        }).fail(function(xhr) {
            console.error(xhr.responseText);
            $('#shopifyVariant')
                .html('<option value="">Failed to load variants</option>')
                .prop('disabled', true);
        });
    }

    // ===========================
    // Product Changed
    // ===========================
    $(document).on('change', '#shopifyProduct', function() {
        const productId = $(this).val();
        if (!productId) {
            $('#shopifyVariant')
                .html('<option value="">Select Product First</option>')
                .prop('disabled', true);
            return;
        }
        loadVariants(productId);
    });

    function nextPage() {
        let totalPages = Math.ceil(filteredData.length / perPage) || 1;
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    }

    function applyFilter(type) {
        currentPage = 1;

        let search = document.getElementById('searchInput' + type).value.toLowerCase();
        let status = document.getElementById('statusFilter' + type).value;

        let data = activeTab === 'shopify' ? shopifyData : amazonData;

        filteredData = data.filter(item => {
            let sku = String(item.sku || '').toLowerCase();
            let product = String(item.product || item.title || '').toLowerCase();
            let asin = String(item.asin || item.ASIN || '').toLowerCase();

            let matchSearch = !search ||
                sku.includes(search) ||
                product.includes(search) ||
                asin.includes(search);

            let matchStatus = status ? item.status === status : true;

            return matchSearch && matchStatus;
        });

        renderTable();
    }

    function resetFilter() {
        currentPage = 1;
        filteredData = activeTab === 'shopify' ? [...shopifyData] : [...amazonData];

        if (activeTab === 'shopify') {
            document.getElementById('searchInputs').value = '';
            document.getElementById('statusFilters').value = '';
        } else {
            document.getElementById('searchInputa').value = '';
            document.getElementById('statusFiltera').value = '';
        }

        renderTable();
    }

    function badge(status) {
        const original = (status || '').toLowerCase();
        let mapped = original;

        if (activeTab === 'amazon') {
            if (original === 'active') mapped = 'synced';
            else if (original === 'inactive') mapped = 'pending';
            else if (original === 'incomplete') mapped = 'pending';
        }

        switch (mapped) {
            case 'synced':
                return `<span class="soft-badge bg-success-subtle text-success">Synced</span>`;
            case 'pending':
                return `<span class="soft-badge bg-warning-subtle text-warning">Pending</span>`;
            default:
                return `<span class="soft-badge bg-danger-subtle text-danger">Error</span>`;
        }
    }

    function refreshCache(event) {

        const btn = event.currentTarget;
        const oldHtml = btn.innerHTML;

        btn.innerHTML = 'Refreshing...';
        btn.disabled = true;

        const shop = new URLSearchParams(window.location.search).get('shop');
        const type = activeTab; // <-- Add this

        fetch(`{{ route('shopify.inventory.refresh') }}?shop=${encodeURIComponent(shop)}&type=${type}`)
            .then(() => {
                if (activeTab === 'shopify') {
                    loadShopify();
                } else {
                    loadAmazon();
                }
            })
            .finally(() => {
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadShopify();
    });

    $(document).on('click', '#continueAmazonMapping', function() {
        const type = $('input[name="mapping_type"]:checked').val();
        $('#mapAmazonProductModal').modal('hide');

        if (type === 'existing') {
            $('#existingAmazonProductModal').modal('show');
        } else {
            alert('Redirect to Create Amazon Product');
        }
    });

    $(document).on('click', '#newAmazonProductBtn', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        let url = "{{ route('user.product.syncShopifyToAmazon', ['id' => '__ID__']) }}";
        url = url.replace('__ID__', selectedShopifyProductId);
        url += '?shop=' + encodeURIComponent(shop);
        window.location.href = url;
    });

    $(document).on('click', '.map-amazon-product', function() {
        selectedShopifyProductId = $(this).data('product');
        selectedShopifyVariantId = $(this).data('variant');
        selectedInventoryItemId = $(this).data('inventory-item');
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

    function populateAmazonProducts(products) {
        let options = '<option value="">Select Amazon Product</option>';
        products
            .filter(item => !item.is_mapped)
            .forEach(item => {
                let title = item.title || '';
                if (title.length > 40) {
                    title = title.substring(0, 40) + '...';
                }
                options += `
                <option value="${item.sku}">
                    ${title} (${item.sku})
                </option>
            `;
            });
        $('#amazonProduct').html(options);
    }

    $(document).on('click', '#existingAmazonProductBtn', function() {
        const shop = new URLSearchParams(window.location.search).get('shop');
        $.get('/zeosync/inventory/amazon', {
            shop: shop
        }, function(response) {
            populateAmazonProducts(response);
            $('#amazonProductActionModal').modal('hide');
            $('#mapAmazonProductModal').modal('show');
        });
    });
</script>
@endpush