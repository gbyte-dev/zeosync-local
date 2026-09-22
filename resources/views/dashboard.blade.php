@extends('layouts.app')
@php
$currentShop = $activeShop ?? request('shop') ?? session('active_shop');
$shopifyOrdersUrl = url('/orders?') . http_build_query(array_filter([
    'shop' => $currentShop,
    'source' => 'shopify',
]));
$amazonOrdersUrl = url('/orders?') . http_build_query(array_filter([
    'shop' => $currentShop,
    'source' => 'amazon',
]));
$shopifyProductsUrl = route('shopify.products', array_filter(['shop' => $currentShop]));
$amazonProductsUrl = route('user.product.showProducts', array_filter(['shop' => $currentShop]));
$amazonConnectUrl = route('amazon.connect', array_filter(['shop' => $currentShop]));

$isAmazonConnected = !empty($shop->amazon_seller_id);
$hasShopifyTopSelling = !empty($topSellingProducts) && $topSellingProducts->isNotEmpty() && $topSellingChartData->sum() > 0;
$hasShopifyLowInventory = !empty($lowInventoryProducts) && $lowInventoryProducts->isNotEmpty();
$hasAmazonLowInventory = !empty($amazonLowInventoryProducts) && (is_countable($amazonLowInventoryProducts) ? count($amazonLowInventoryProducts) > 0 : $amazonLowInventoryProducts->isNotEmpty());
@endphp
@section('content')
<style nonce="{{ $cspNonce }}">
    .shopify-dashboard {
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #111827;
        background: transparent;
    }

    /* Page Header */
    .dashboard-header-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .page-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #111827;
        letter-spacing: -.01em;
    }

    .page-subtitle {
        font-size: .8rem;
        color: #6B7280;
    }

    .dashboard-ai-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background-color: #111827;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 500;
        padding: 7px 14px;
        border-radius: 6px;
        text-decoration: none;
        transition: background-color 0.2s ease, transform 0.1s ease;
        border: 1px solid #111827;
        white-space: nowrap;
    }

    .dashboard-ai-btn:hover {
        background-color: #374151;
        color: #FFFFFF;
        text-decoration: none;
    }

    /* Stats Grid */
    .saas-stats-grid {
        display: flex;
        flex-wrap: nowrap;
        gap: 14px;
        margin-bottom: 20px;
        overflow-x: auto;
    }

    .saas-stat-card {
        flex: 1;
        min-width: 180px;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        transition: all 0.2s ease;
    }

    .saas-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .saas-stat-label {
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        color: #6B7280;
        letter-spacing: .05em;
    }

    .saas-stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #111827;
        line-height: 1;
    }

    /* 2x2 Grid Layout */
    .dashboard-grid-2x2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    /* SaaS Dashboard Card */
    .saas-dashboard-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 14px;
        padding: 20px 22px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 380px;
        height: 100%;
        transition: box-shadow 0.2s ease;
    }

    .saas-dashboard-card:hover {
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    }

    /* Card Header */
    .card-header-clean {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .card-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-icon-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .card-icon-blue {
        background-color: #EFF6FF;
        color: #2563EB;
    }

    .amazon-header-icon-box {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .card-title-clean {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        line-height: 1.25;
        letter-spacing: -0.01em;
    }

    .card-subtitle-clean {
        font-size: 12px;
        color: #6B7280;
        margin: 2px 0 0 0;
        line-height: 1.25;
    }

    /* Timeframe Dropdown Pill */
    .header-time-pill {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        color: #374151;
        font-size: 12px;
        font-weight: 500;
        padding: 5px 12px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .header-time-pill:hover {
        background-color: #F9FAFB;
        border-color: #D1D5DB;
        color: #111827;
    }

    .header-location-select {
        font-size: 12px;
        max-width: 180px;
        border-radius: 6px;
        border-color: #E5E7EB;
    }

    /* Card Body */
    .card-body-clean {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
        width: 100%;
        min-height: 220px;
    }

    .card-body-content {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: stretch;
    }

    /* Empty States */
    .empty-state-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 16px 10px;
        max-width: 320px;
        margin: auto;
    }

    .empty-state-icon-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
        flex-shrink: 0;
    }

    .empty-icon-blue {
        background-color: #EFF6FF;
        color: #2563EB;
    }

    .empty-icon-red {
        background-color: #FEE2E2;
        color: #DC2626;
    }

    .empty-icon-amber {
        background-color: #FEF3C7;
        color: #D97706;
    }

    .empty-state-title {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 5px;
        line-height: 1.3;
    }

    .empty-state-desc {
        font-size: 12.5px;
        color: #6B7280;
        line-height: 1.45;
        margin-bottom: 16px;
    }

    /* SaaS Buttons */
    .btn-saas-primary {
        background-color: #2563EB;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 500;
        padding: 7px 18px;
        border-radius: 8px;
        border: 1px solid #2563EB;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(37, 99, 235, 0.15);
    }

    .btn-saas-primary:hover {
        background-color: #1D4ED8;
        border-color: #1D4ED8;
        color: #FFFFFF;
        text-decoration: none;
    }

    .btn-saas-primary:active {
        transform: scale(0.98);
    }

    .btn-saas-secondary {
        background-color: #F9FAFB;
        color: #374151;
        font-size: 13px;
        font-weight: 500;
        padding: 7px 18px;
        border-radius: 8px;
        border: 1px solid #E5E7EB;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .btn-saas-secondary:hover {
        background-color: #F3F4F6;
        border-color: #D1D5DB;
        color: #111827;
        text-decoration: none;
    }

    .btn-saas-secondary:active {
        transform: scale(0.98);
    }

    /* Card Footer */
    .card-footer-clean {
        font-size: 11.5px;
        color: #9CA3AF;
        display: flex;
        align-items: center;
        gap: 6px;
        padding-top: 14px;
        margin-top: auto;
    }

    .card-footer-clean i {
        font-size: 12px;
        color: #9CA3AF;
    }

    /* Table Styles */
    .saas-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .saas-table th {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #6B7280;
        letter-spacing: .05em;
        padding: 8px 10px;
        border-bottom: 1px solid #E5E7EB;
    }

    .saas-table td {
        font-size: 12.5px;
        color: #374151;
        padding: 8px 10px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: middle;
    }

    .low-inventory-table {
        table-layout: fixed;
        width: 100%;
    }

    .low-inventory-table .product-name {
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        font-weight: 500;
        color: #111827;
    }

    /* Badges */
    .saas-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    .saas-badge-success {
        background: #D1FAE5;
        color: #065F46;
    }

    .saas-badge-danger {
        background: #FEE2E2;
        color: #991B1B;
    }

    .saas-badge-warning {
        background: #FEF3C7;
        color: #92400E;
    }

    .saas-badge-neutral {
        background: #F3F4F6;
        color: #374151;
    }

    /* Activity Card */
    .activity-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 14px;
        padding: 20px 22px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }

    .activity-card:hover {
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    }

    .card-divider {
        height: 1px;
        background: #E5E7EB;
        margin: 12px 0 16px 0;
    }

    /* --- Responsive Queries --- */
    @media (max-width: 991.98px) {
        .dashboard-grid-2x2 {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .saas-stats-grid {
            flex-wrap: wrap;
        }

        .saas-stat-card {
            flex: 1 1 calc(50% - 8px);
            min-width: calc(50% - 8px);
        }
    }

    @media (max-width: 576.98px) {
        .saas-stats-grid {
            flex-direction: column;
        }

        .saas-stat-card {
            width: 100%;
            min-width: 100%;
        }

        .saas-dashboard-card {
            padding: 16px;
            min-height: 350px;
        }

        .page-title {
            font-size: 1rem;
        }
    }
</style>

<div class="container-fluid py-3 px-3 shopify-dashboard">
    <!-- Page Header -->
    <div class="dashboard-header-card">
        <div>
            <h1 class="page-title mb-0">Dashboard</h1>
            <p class="page-subtitle mb-0">Amazon ↔ Shopify sync overview</p>
        </div>
        <div>
            <a href="{{ route('shopify.ai.chat', ['shop' => $currentShop]) }}" class="dashboard-ai-btn">
                <i class="bi bi-chat-left-text"></i>
                <span>Chat to AI</span>
            </a>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="saas-stats-grid">
        <div class="saas-stat-card">
            <div>
                <div class="saas-stat-label">Shopify Products</div>
            </div>
            <div class="saas-stat-value">
                {{ number_format($totalProducts ?? 0) }}
            </div>
        </div>
        <div class="saas-stat-card">
            <div>
                <div class="saas-stat-label">Mapped Products</div>
            </div>
            <div class="saas-stat-value">
                {{ number_format($totalMapped ?? 0) }}
            </div>
        </div>
        <div class="saas-stat-card">
            <div>
                <div class="saas-stat-label">Orders</div>
            </div>
            <div class="saas-stat-value">
                {{ number_format($totalOrders ?? 0) }}
            </div>
        </div>
        <div class="saas-stat-card">
            <div>
                <div class="saas-stat-label">Sync Status</div>
            </div>
            @if(isset($isShopConnected) && $isShopConnected)
            <div class="saas-stat-value text-success" style="font-size:14px; font-weight: 600;">
                ● Connected
            </div>
            @else
            <div class="saas-stat-value text-danger" style="font-size:14px; font-weight: 600;">
                ● Disconnected
            </div>
            @endif
        </div>
    </div>

    <!-- 2x2 Cards Grid -->
    <div class="dashboard-grid-2x2">

        <!-- ========================================== -->
        <!-- ROW 1, CARD 1: Shopify Top Selling Products -->
        <!-- ========================================== -->
        <div class="saas-dashboard-card" id="shopifyTopSellingCard">
            <!-- Header -->
            <div class="card-header-clean">
                <div class="card-header-left">
                    <div class="card-icon-box card-icon-blue">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div>
                        <h3 class="card-title-clean">Top Selling Products</h3>
                        <p class="card-subtitle-clean">Last 24 Hours</p>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="header-time-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span>Last 24 Hours</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item active" href="javascript:void(0)">Last 24 Hours</a></li>
                    </ul>
                </div>
            </div>

            <!-- Body -->
            <div class="card-body-clean">
                @if($hasShopifyTopSelling)
                <div class="card-body-content" style="min-height: 220px; height: 220px;">
                    <canvas id="topSellingProductsChart"></canvas>
                </div>
                @else
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <div class="empty-state-title">No orders yet</div>
                    <div class="empty-state-desc">
                        Your top-selling products will appear here<br>once you receive orders.
                    </div>
                    <a href="{{ $shopifyOrdersUrl }}" class="btn-saas-primary">
                        View Orders
                    </a>
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="card-footer-clean">
                <i class="bi bi-info-circle-fill"></i>
                <span>Updated dynamically from Shopify orders</span>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- ROW 1, CARD 2: Shopify Low Inventory Products -->
        <!-- ========================================== -->
        <div class="saas-dashboard-card" id="shopifyLowInventoryCard">
            <!-- Header -->
            <div class="card-header-clean">
                <div class="card-header-left">
                    <div class="card-icon-box card-icon-blue">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div>
                        <h3 class="card-title-clean">Low Inventory Products</h3>
                        <p class="card-subtitle-clean">Products with inventory below 10 units</p>
                    </div>
                </div>
                @php
                    $locations = $shop->shopify_locations ?? [];
                    $selectedIndex = (isset($shop->selected_location_index) && isset($locations[$shop->selected_location_index]))
                        ? (int) $shop->selected_location_index : 0;
                @endphp
                @if(!empty($locations) && count($locations) > 1)
                <div>
                    <select name="selected_location_index" class="form-select form-select-sm header-location-select" id="locationSelect" disabled="true" title="Update location from settings">
                        @foreach($locations as $index => $location)
                        <option value="{{ $index }}" {{ (string) old('selected_location_index', $selectedIndex) === (string) $index ? 'selected' : '' }}>
                            {{ $location['name'] ?? 'Unnamed Location' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>

            <!-- Body -->
            <div class="card-body-clean">
                @if($hasShopifyLowInventory)
                <div class="card-body-content overflow-auto" style="max-height: 240px;">
                    <table class="saas-table low-inventory-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th class="text-end">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowInventoryProducts as $product)
                            <tr>
                                <td class="product-name" title="{{ $product['product'] }}">
                                    {{ $product['product'] }}
                                </td>
                                <td>{{ $product['sku'] ?? '-' }}</td>
                                <td class="text-end">
                                    @php $qty = $product['available'] ?? null; @endphp
                                    @if($qty !== null)
                                    <span class="saas-badge {{ $qty <= 3 ? 'saas-badge-danger' : ($qty <= 7 ? 'saas-badge-warning' : 'saas-badge-neutral') }}">
                                        {{ $qty }}
                                    </span>
                                    @else
                                    <span class="saas-badge saas-badge-neutral">Unknown</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-red">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">No low inventory products</div>
                    <div class="empty-state-desc">
                        All your products have sufficient inventory.<br>We'll show low stock items here.
                    </div>
                    <a href="{{ $shopifyProductsUrl }}" class="btn-saas-secondary">
                        View Products
                    </a>
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="card-footer-clean">
                <i class="bi bi-info-circle-fill"></i>
                <span>Updated from Shopify inventory</span>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- ROW 2, CARD 3: Amazon Top Selling Products -->
        <!-- ========================================== -->
        <div class="saas-dashboard-card" id="amazonTopSellingCard">
            <!-- Header -->
            <div class="card-header-clean">
                <div class="card-header-left">
                    <div class="amazon-header-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.9 14.3c-.1-.7-.2-1.6-.2-2.6 0-1.9.5-3.3 1.5-4.2 1-.9 2.4-1.4 4.1-1.4.7 0 1.3.1 1.8.2v-.8c0-1.2-.3-2.1-.9-2.7-.6-.6-1.6-.8-3-.8-1 0-1.9.2-2.8.5-.9.3-1.6.8-2.2 1.4l-1.7-2c.8-.8 1.8-1.4 3.1-1.9C14.8.3 16.2.1 17.7.1c2.3 0 4 .6 5.2 1.6 1.2 1.1 1.8 2.7 1.8 4.7v8.8h-3.4v-1.8c-.6.7-1.3 1.2-2.2 1.6-.8.4-1.8.6-2.8.6-1.3 0-2.4-.3-3.2-1-.8-.6-1.3-1.5-1.4-2.7l.2.4zm6.8-4c-.5-.1-1-.2-1.6-.2-1 0-1.8.2-2.4.7-.5.5-.8 1.2-.8 2.2 0 .9.2 1.5.7 2 .5.4 1.1.6 1.9.6.7 0 1.4-.2 1.9-.5.5-.4.9-.8 1.2-1.5.1-.2.1-.5.1-.9v-2.4z" fill="#111827"/>
                            <path d="M22.9 19.4C19.8 21.7 15.4 22.9 10.7 22.9c-6.2 0-11.8-2.3-16-6.1-.3-.3 0-.8.4-.5 4.6 2.8 10.3 4.5 16.3 4.5 4.1 0 8.2-.9 11.8-2.7.6-.3 1 .4.5.8z" fill="#FF9900"/>
                            <path d="M24.1 21.3c-.4.6-1.4 1.1-2.1 1.3-.2.1-.4-.1-.3-.3.6-1.3 1.7-2.3 1.7-2.3s.9.4 1.9.8c.2.1.2.4 0 .5h-1.2z" fill="#FF9900"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="card-title-clean">Amazon Top Selling Products</h3>
                        <p class="card-subtitle-clean">Last 24 Hours</p>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="header-time-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span>Last 24 Hours</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item active" href="javascript:void(0)">Last 24 Hours</a></li>
                    </ul>
                </div>
            </div>

            <!-- Body -->
            <div class="card-body-clean">
                @if(!$isAmazonConnected)
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-amber">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">Please connect your Amazon account first.</div>
                    <div class="empty-state-desc">
                        Connect your Amazon account to sync sales and top selling product data.
                    </div>
                    <a href="{{ $amazonConnectUrl }}" class="btn-saas-primary">
                        Connect Amazon
                    </a>
                </div>
                @else
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">No Amazon orders yet</div>
                    <div class="empty-state-desc">
                        Amazon sales data will appear here<br>once orders are synced.
                    </div>
                    <a href="{{ $amazonOrdersUrl }}" class="btn-saas-primary">
                        View Amazon Orders
                    </a>
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="card-footer-clean">
                <i class="bi bi-info-circle-fill"></i>
                <span>Updated dynamically from Amazon orders</span>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- ROW 2, CARD 4: Amazon Low Inventory -->
        <!-- ========================================== -->
        <div class="saas-dashboard-card" id="amazonLowInventoryCard">
            <!-- Header -->
            <div class="card-header-clean">
                <div class="card-header-left">
                    <div class="amazon-header-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.9 14.3c-.1-.7-.2-1.6-.2-2.6 0-1.9.5-3.3 1.5-4.2 1-.9 2.4-1.4 4.1-1.4.7 0 1.3.1 1.8.2v-.8c0-1.2-.3-2.1-.9-2.7-.6-.6-1.6-.8-3-.8-1 0-1.9.2-2.8.5-.9.3-1.6.8-2.2 1.4l-1.7-2c.8-.8 1.8-1.4 3.1-1.9C14.8.3 16.2.1 17.7.1c2.3 0 4 .6 5.2 1.6 1.2 1.1 1.8 2.7 1.8 4.7v8.8h-3.4v-1.8c-.6.7-1.3 1.2-2.2 1.6-.8.4-1.8.6-2.8.6-1.3 0-2.4-.3-3.2-1-.8-.6-1.3-1.5-1.4-2.7l.2.4zm6.8-4c-.5-.1-1-.2-1.6-.2-1 0-1.8.2-2.4.7-.5.5-.8 1.2-.8 2.2 0 .9.2 1.5.7 2 .5.4 1.1.6 1.9.6.7 0 1.4-.2 1.9-.5.5-.4.9-.8 1.2-1.5.1-.2.1-.5.1-.9v-2.4z" fill="#111827"/>
                            <path d="M22.9 19.4C19.8 21.7 15.4 22.9 10.7 22.9c-6.2 0-11.8-2.3-16-6.1-.3-.3 0-.8.4-.5 4.6 2.8 10.3 4.5 16.3 4.5 4.1 0 8.2-.9 11.8-2.7.6-.3 1 .4.5.8z" fill="#FF9900"/>
                            <path d="M24.1 21.3c-.4.6-1.4 1.1-2.1 1.3-.2.1-.4-.1-.3-.3.6-1.3 1.7-2.3 1.7-2.3s.9.4 1.9.8c.2.1.2.4 0 .5h-1.2z" fill="#FF9900"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="card-title-clean">Amazon Low Inventory</h3>
                        <p class="card-subtitle-clean">Products with inventory below 10 units</p>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="card-body-clean" id="amazonLowInventoryContainer">
                @if(!$isAmazonConnected)
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-amber">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">Please connect your Amazon account first.</div>
                    <div class="empty-state-desc">
                        Connect your Amazon account to view low inventory alerts and stock levels.
                    </div>
                    <a href="{{ $amazonConnectUrl }}" class="btn-saas-primary">
                        Connect Amazon
                    </a>
                </div>
                @elseif(!$amazonInventoryCacheExists)
                {{-- Async loading state --}}
                <div class="empty-state-container" id="amazonInventoryLoadingState">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 2.2rem; height: 2.2rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="empty-state-title" style="font-size: 14px;">Loading Amazon inventory...</div>
                    <div class="empty-state-desc mb-0">Fetching latest stock counts from Amazon</div>
                </div>
                <div id="amazonInventoryLoadedContent" class="card-body-content" style="display: none;"></div>
                @elseif($hasAmazonLowInventory)
                <div class="card-body-content overflow-auto" style="max-height: 240px;">
                    <table class="saas-table low-inventory-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th class="text-end">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($amazonLowInventoryProducts as $product)
                            <tr>
                                <td class="product-name" title="{{ $product['title'] ?? '' }}">
                                    {{ $product['title'] ?? '-' }}
                                </td>
                                <td>{{ $product['sku'] ?? '-' }}</td>
                                <td class="text-end">
                                    @php $qty = $product['quantity'] ?? 0; @endphp
                                    <span class="saas-badge {{ $qty <= 3 ? 'saas-badge-danger' : ($qty <= 7 ? 'saas-badge-warning' : 'saas-badge-neutral') }}">
                                        {{ $qty }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-red">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">No low inventory products</div>
                    <div class="empty-state-desc">
                        All your Amazon products have sufficient inventory.<br>We'll show low stock items here.
                    </div>
                    <a href="{{ $amazonProductsUrl }}" class="btn-saas-secondary">
                        View Amazon Products
                    </a>
                </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="card-footer-clean">
                <i class="bi bi-info-circle-fill"></i>
                <span>Updated from Amazon inventory</span>
            </div>
        </div>

    </div>

    <!-- 3. Recent Activity Section -->
    <div class="row">
        <div class="col-12">
            <div class="activity-card">
                <div class="card-header-clean mb-0">
                    <div class="card-header-left">
                        <div class="card-icon-box card-icon-blue">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                        <div>
                            <h3 class="card-title-clean">Recent Activity</h3>
                            <p class="card-subtitle-clean">Latest synchronization logs and events</p>
                        </div>
                    </div>
                </div>
                <div class="card-divider"></div>
                <div class="table-responsive">
                    <table class="saas-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th style="width: 110px;">Type</th>
                                <th style="width: 110px;">Status</th>
                                <th>Message</th>
                                <th class="text-end" style="width: 130px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs ?? [] as $log)
                            <tr>
                                <td class="fw-medium text-muted">#{{ $log->id }}</td>
                                <td>
                                    <span class="saas-badge saas-badge-neutral text-capitalize">
                                        {{ str_replace('_', ' ', ucfirst($log->type ?? 'System')) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->status == 'success')
                                    <span class="saas-badge saas-badge-success">
                                        <span class="badge-dot dot-success me-1">●</span> Success
                                    </span>
                                    @elseif($log->status == 'failed')
                                    <span class="saas-badge saas-badge-warning">
                                        <span class="badge-dot dot-warning me-1">●</span> Failed
                                    </span>
                                    @else
                                    <span class="saas-badge saas-badge-danger">
                                        <span class="badge-dot dot-danger me-1">●</span> Error
                                    </span>
                                    @endif
                                </td>
                                <td class="text-truncate" style="max-width: 380px; font-weight: 500;">
                                    {{ $log->message ?? 'No message provided' }}
                                </td>
                                <td class="text-muted text-end" style="font-size: 0.75rem;">
                                    {{ optional($log->created_at)->diffForHumans() }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="text-muted">
                                        <h6 class="fw-semibold text-dark mb-1" style="font-size: 0.85rem;">No recent activity</h6>
                                        <p class="small mb-0">Synchronization logs will automatically populate here.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
document.addEventListener("DOMContentLoaded", function() {
    const isAmazonConnected = @json($isAmazonConnected);
    const amazonInventoryCacheExists = @json($amazonInventoryCacheExists ?? false);
    const amazonConnectUrl = @json($amazonConnectUrl);
    const amazonProductsUrl = @json($amazonProductsUrl);

    // --- 1. Async Amazon Low Inventory Loader ---
    if (isAmazonConnected && !amazonInventoryCacheExists) {
        const loadingStateEl = document.getElementById('amazonInventoryLoadingState');
        const containerEl = document.getElementById('amazonLowInventoryContainer');

        const renderAmazonEmptyState = () => {
            if (!containerEl) return;
            containerEl.innerHTML = `
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-red">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">No low inventory products</div>
                    <div class="empty-state-desc">
                        All your Amazon products have sufficient inventory.<br>We'll show low stock items here.
                    </div>
                    <a href="${amazonProductsUrl}" class="btn-saas-secondary">
                        View Amazon Products
                    </a>
                </div>
            `;
        };

        const renderAmazonDisconnectedState = () => {
            if (!containerEl) return;
            containerEl.innerHTML = `
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-amber">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title">Please connect your Amazon account first.</div>
                    <div class="empty-state-desc">
                        Connect your Amazon account to view low inventory alerts and stock levels.
                    </div>
                    <a href="${amazonConnectUrl}" class="btn-saas-primary">
                        Connect Amazon
                    </a>
                </div>
            `;
        };

        const renderAmazonErrorState = () => {
            if (!containerEl) return;
            containerEl.innerHTML = `
                <div class="empty-state-container">
                    <div class="empty-state-icon-circle empty-icon-amber">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="empty-state-title text-danger">Unable to load Amazon inventory</div>
                    <div class="empty-state-desc">
                        Could not retrieve latest inventory. Please try again.
                    </div>
                    <button type="button" class="btn-saas-secondary" id="retryAmazonInventoryBtn">
                        Retry
                    </button>
                </div>
            `;
            const retryBtn = document.getElementById('retryAmazonInventoryBtn');
            if (retryBtn) {
                retryBtn.addEventListener('click', function() {
                    if (loadingStateEl) {
                        containerEl.innerHTML = `
                            <div class="empty-state-container">
                                <div class="spinner-border text-primary mb-3" role="status" style="width: 2.2rem; height: 2.2rem;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="empty-state-title" style="font-size: 14px;">Loading Amazon inventory...</div>
                                <div class="empty-state-desc mb-0">Fetching latest stock counts from Amazon</div>
                            </div>
                        `;
                    }
                    loadAmazonInventory();
                });
            }
        };

        const loadAmazonInventory = () => {
            fetch("{{ route('shopify.inventory.amazon') }}", {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Non-JSON response received');
                }
                return response.json();
            })
            .then(data => {
                if (data.requires_activation && data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }
                if (data.requires_reauth && data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }
                if (data.requires_subscription && data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }

                const isNotConnected = data.connected === false || data.status?.error === 'amazon_not_connected';
                if (isNotConnected) {
                    renderAmazonDisconnectedState();
                    return;
                }

                const products = data.products || [];
                const refreshing = data.status?.refreshing === true;
                const syncCompleted = data.status?.sync_completed === true;

                if (refreshing) {
                    setTimeout(loadAmazonInventory, 2000);
                    return;
                }

                if (!syncCompleted && products.length === 0) {
                    renderAmazonErrorState();
                    return;
                }

                const lowInventoryProducts = products
                    .filter(product => Number(product.quantity) < 10)
                    .sort((a, b) => Number(a.quantity) - Number(b.quantity))
                    .slice(0, 7);

                if (lowInventoryProducts.length === 0) {
                    renderAmazonEmptyState();
                    return;
                }

                const rowsHtml = lowInventoryProducts.map(product => {
                    const title = product.title ?? '-';
                    const sku = product.sku ?? '-';
                    const qty = Number(product.quantity ?? 0);
                    const badgeClass = qty <= 3 ? 'saas-badge-danger' : (qty <= 7 ? 'saas-badge-warning' : 'saas-badge-neutral');

                    return `
                        <tr>
                            <td class="product-name" title="${title}">
                                ${title}
                            </td>
                            <td>${sku}</td>
                            <td class="text-end">
                                <span class="saas-badge ${badgeClass}">
                                    ${qty}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('');

                containerEl.innerHTML = `
                    <div class="card-body-content overflow-auto" style="max-height: 240px;">
                        <table class="saas-table low-inventory-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>SKU</th>
                                    <th class="text-end">Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Amazon inventory fetch failed:', error);
                renderAmazonErrorState();
            });
        };

        loadAmazonInventory();
    }

    // --- 2. Top Selling Products (Chart.js) ---
    const topSellingCanvas = document.getElementById('topSellingProductsChart');
    if (topSellingCanvas && typeof Chart !== 'undefined') {
        const topSellingLabels = @json($topSellingChartLabels ?? []);
        const topSellingData = @json($topSellingChartData ?? []);

        if (topSellingData.length > 0 && topSellingData.some(val => val > 0)) {
            const ctx = topSellingCanvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topSellingLabels,
                    datasets: [{
                        label: 'Units Sold',
                        data: topSellingData,
                        backgroundColor: '#2563EB',
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 32
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#111827',
                            padding: 10,
                            cornerRadius: 6,
                            displayColors: false,
                            titleFont: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            },
                            bodyFont: {
                                size: 13,
                                family: "'Inter', sans-serif",
                                weight: 'bold'
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            border: {
                                display: false
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            border: {
                                display: false
                            },
                            grid: {
                                color: '#F3F4F6'
                            },
                            ticks: {
                                precision: 0,
                                color: '#9CA3AF',
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
@endsection