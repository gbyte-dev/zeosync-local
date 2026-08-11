@extends('layouts.app')
@section('content')
<style>
    .shopify-dashboard {
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #111827;
        background: transparent;
    }

    /* Page Header */
    .page-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
        letter-spacing: -.01em;
    }

    .page-subtitle {
        font-size: .8rem;
        color: #6B7280;
    }

    /* Inventory Style Stats */
    .saas-stats-grid {
        display: flex;
        flex-wrap: nowrap;
        gap: 12px;
        margin-bottom: 18px;
        overflow-x: auto;
    }

    .saas-stat-card {
        flex: 1;
        min-width: 180px;
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        transition: .2s;
    }

    .saas-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
    }

    .saas-stat-label {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        color: #6B7280;
        letter-spacing: .05em;
    }

    .in-iframe .saas-stat-label {
        font-size: 10px;
    }

    .saas-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        line-height: 1;
    }

    .in-iframe .saas-stat-value {
        font-size: 20px;
    }

    .in-iframe .saas-stat-card {
        padding: 8px 9px;
    }

    .in-iframe .card-title-custom {
        font-size: 14px;
    }

    .in-iframe .low-inventory-table {
        font-size: 10px;
    }

    /* Graph / Recent Activity Cards */
    .premium-card {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        transition: .2s;
    }

    .premium-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
    }

    .card-header-custom {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .card-title-custom {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: #111827;
    }

    .card-subtitle-custom {
        margin: 0;
        color: #6B7280;
        font-size: 12px;
    }

    .card-divider {
        height: 1px;
        background: #E5E7EB;
        margin: 8px 0;
    }

    .card-footer-custom {
        margin-top: auto;
        padding-top: 12px;
        font-size: 11px;
        color: #9CA3AF;
    }

    /* Badges */
    .saas-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
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

    /* Table */
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
        padding: 8px 12px;
        border-bottom: 1px solid #E5E7EB;
    }

    .saas-table td {
        font-size: 13px;
        color: #374151;
        padding: 10px 12px;
        border-bottom: 1px solid #F3F4F6;
        vertical-align: middle;
    }

    .low-inventory-table th {
        padding: 6px 10px;
        font-size: 10px;
        line-height: 1;
    }

    .low-inventory-table td {
        padding: 7px 10px;
        line-height: 1.2;
        vertical-align: middle;
    }

    .low-inventory-table .saas-badge {
        padding: 2px 8px;
        font-size: 10px;
    }

    .low-inventory-table .product-name {
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dashboard-header-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    /* --- Responsive Improvements --- */
    /* Tablet (577px - 991px) */
    @media (min-width: 577px) and (max-width: 991.98px) {
        .saas-stats-grid {
            flex-wrap: wrap;
            overflow-x: visible;
        }

        .saas-stat-card {
            flex: 1 1 calc(50% - 12px);
            min-width: calc(50% - 12px);
        }
    }

    /* Mobile (320px - 576px) */
    @media (max-width: 576.98px) {
        .saas-stats-grid {
            flex-direction: column;
            flex-wrap: nowrap;
            overflow-x: visible;
        }

        .saas-stat-card {
            width: 100%;
            flex: none;
            min-width: 100%;
        }

        .page-title {
            font-size: 1.1rem;
        }

        .saas-stat-value {
            font-size: 20px;
        }

        /* Table Responsive Adjustments */
        .saas-table th,
        .saas-table td {
            white-space: nowrap;
        }

        /* Long messages wrap instead of overflowing */
        .saas-table td.text-truncate {
            white-space: normal !important;
            max-width: none !important;
            min-width: 250px;
            word-wrap: break-word;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        /* Touch-friendly adjustments and wrapping */
        .saas-badge {
            padding: 8px 14px;
            white-space: normal;
            text-align: center;
            line-height: 1.4;
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
            <div class="saas-stat-value text-success" style="font-size:14px;">
                ● Connected
            </div>
            @else
            <div class="saas-stat-value text-danger" style="font-size:14px;">
                ● Disconnected
            </div>
            @endif
        </div>
    </div>
    <!-- 2. Graphs Section (Exactly 50% Width Cards, Fixed height 350px) -->
    <div class="row g-3 mb-3">
        <!-- Orders Trend Graph -->
        <div class="col-md-6 d-flex">
            <div class="premium-card" style="height: 350px;">
                <div class="card-header-custom">
                    <h3 class="card-title-custom">
                        📈 Top Selling Products
                    </h3>
                    <p class="card-subtitle-custom">
                        Last 24 Hours
                    </p>
                </div>
                <div class="card-divider"></div>
                <!-- Top Selling Products -->
                <div class="flex-grow-1 overflow-auto" style="height:200px; min-height:200px;">
                    <canvas id="topSellingProductsChart"></canvas>
                </div>
                <div class="card-divider"></div>
                <div class="card-footer-custom">
                    Updated dynamically from Shopify orders
                </div>
            </div>
        </div>
        <!-- shopify low inventory card  -->
        <div class="col-md-6 d-flex">
            <div class="premium-card" style="height:350px;">

                <div class="card-header-custom">
                    <h3 class="card-title-custom">
                        Low Inventory Products
                    </h3>

                    <p class="card-subtitle-custom">
                        Products with inventory below 10 units
                    </p>
                </div>

                <div class="card-divider"></div>

                <div class="flex-grow-1 overflow-auto" style="margin-top: -8px;">

                    <table class="saas-table low-inventory-table">

                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th class="text-end">Available</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($lowInventoryProducts as $product)
                            <tr>
                                <td class="product-name" title="{{ $product['product'] }}">
                                    {{ $product['product'] }}
                                </td>

                                <td>
                                    {{ $product['sku'] ?? '-' }}
                                </td>

                                <td class="text-end">
                                    @php $qty = $product['available'] ?? 0; @endphp

                                    <span class="saas-badge
            {{ $qty <= 3 ? 'saas-badge-danger' : ($qty <= 7 ? 'saas-badge-warning' : 'saas-badge-neutral') }}">
                                        {{ $qty }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-3">
                                    No low inventory products found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>

                    </table>

                </div>

                <div class="card-divider"></div>

                <div class="card-footer-custom">
                    Updated from Shopify inventory
                </div>

            </div>
        </div>

        <div class="row g-3 mb-3">

            <!-- Amazon Top Selling -->
            <div class="col-md-6 d-flex">

                <div class="premium-card" style="height:350px;">

                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            Amazon Top Selling Products
                        </h3>

                        <p class="card-subtitle-custom">
                            Last 24 Hours
                        </p>
                    </div>

                    <div class="card-divider"></div>

                    <div class="flex-grow-1 d-flex justify-content-center align-items-center text-muted">
                        Coming Soon...
                    </div>

                    <div class="card-divider"></div>

                    <div class="card-footer-custom">
                        Updated from Amazon Orders
                    </div>

                </div>

            </div>

            <!-- Amazon Low Inventory -->

            <div class="col-md-6 d-flex">

                <div class="premium-card" style="height:350px;">

                    <div class="card-header-custom">
                        <h3 class="card-title-custom">
                            Amazon Low Inventory
                        </h3>

                        <p class="card-subtitle-custom">
                            Products with inventory below 10 units
                        </p>
                    </div>

                    <div class="card-divider"></div>

                    <div class="flex-grow-1 overflow-auto" style="margin-top: -8px;">

                        <table class="saas-table low-inventory-table">

                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>SKU</th>
                                    <th class="text-end">Available</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse($amazonLowInventoryProducts as $product)

                                <tr>

                                    <td class="product-name" title="{{ $product['title'] }}">
                                        {{ $product['title'] }}
                                    </td>

                                    <td>
                                        {{ $product['sku'] ?? '-' }}
                                    </td>

                                    <td class="text-end">

                                        @php
                                        $qty = $product['quantity'] ?? 0;
                                        @endphp

                                        <span class="saas-badge
                                {{ $qty <= 3
                                    ? 'saas-badge-danger'
                                    : ($qty <= 7
                                        ? 'saas-badge-warning'
                                        : 'saas-badge-neutral') }}">
                                            {{ $qty }}
                                        </span>

                                    </td>

                                </tr>

                                @empty

                                <tr>
                                    <td colspan="3" class="text-center py-3">
                                        No low inventory products found.
                                    </td>
                                </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                    <div class="card-divider"></div>

                    <div class="card-footer-custom">
                        Updated from Amazon Inventory Cache
                    </div>

                </div>

            </div>

        </div>
        <!-- 3. Recent Activity Section -->
        <div class="row">
            <div class="col-12">
                <div class="premium-card">
                    <div class="card-header-custom">
                        <h3 class="card-title-custom">Recent Activity</h3>
                        <p class="card-subtitle-custom">Latest synchronization logs and events</p>
                    </div>
                    <div class="card-divider"></div>
                    <div class="table-responsive">
                        <table class="saas-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th style="width: 100px;">Type</th>
                                    <th style="width: 100px;">Status</th>
                                    <th>Message</th>
                                    <th class="text-end" style="width: 120px;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLogs ?? [] as $log)
                                <tr>
                                    <td class="fw-medium text-muted">#{{ $log->id }}</td>
                                    <td>
                                        <span class="saas-badge saas-badge-neutral text-capitalize">
                                            {{ ucfirst($log->type ?? 'System') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->status == 'success')
                                        <span class="saas-badge saas-badge-success">
                                            <span class="badge-dot dot-success"></span> Success
                                        </span>
                                        @elseif($log->status == 'failed')
                                        <span class="saas-badge saas-badge-warning">
                                            <span class="badge-dot dot-warning"></span> Failed
                                        </span>
                                        @else
                                        <span class="saas-badge saas-badge-danger">
                                            <span class="badge-dot dot-danger"></span> Error
                                        </span>
                                        @endif
                                    </td>
                                    <td class="text-truncate" style="max-width: 350px; font-weight: 500;">
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
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // --- 1. Dynamic Data Preparation ---
            // Orders Timeline Data
            const rawOrderData = @json($ordersTimeline ?? []);
            const orderLabels = rawOrderData.map(item => item.date);
            const orderTotals = rawOrderData.map(item => item.total);
            // Product Creation Trend Data
            const rawProductData = @json($productTrend ?? []);
            const productLabels = rawProductData.map(item => item.date);
            const productTotals = rawProductData.map(item => item.total);
            // Reusable Tooltip Configuration
            const commonTooltipConfig = {
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
            };
            // Reusable Scale Configuration
            const commonScaleConfig = {
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
                        },
                        maxTicksLimit: 7
                    }
                },
                y: {
                    grid: {
                        color: '#F3F4F6',
                        drawBorder: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11
                        },
                        maxTicksLimit: 5,
                        precision: 0
                    }
                }
            };

            // --- 2. Top Selling Products (Bar Chart) ---

            const topSellingCtx = document
                .getElementById('topSellingProductsChart')
                .getContext('2d');

            new Chart(topSellingCtx, {

                type: 'bar',

                data: {

                    labels: @json($topSellingChartLabels),

                    datasets: [{

                        label: 'Units Sold',

                        data: @json($topSellingChartData),

                        backgroundColor: '#2563EB',

                        borderRadius: 8,

                        borderSkipped: false,

                        maxBarThickness: 38

                    }]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: commonTooltipConfig

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
                                    size: 11
                                }
                            }

                        },

                        y: {

                            beginAtZero: true,

                            border: {
                                display: false
                            },

                            ticks: {

                                precision: 0,

                                color: '#9CA3AF'

                            },

                            title: {

                                display: true,

                                text: 'Units Sold'

                            }

                        }

                    }

                }

            });
            // --- 3. Product Creation Trend (Line Graph) ---
            const ctxLineProducts = document.getElementById('productTrendChart').getContext('2d');
            const gradientProducts = ctxLineProducts.createLinearGradient(0, 0, 0, 200);
            gradientProducts.addColorStop(0, 'rgba(16, 185, 129, 0.25)'); // Emerald Green Fade
            gradientProducts.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
            new Chart(ctxLineProducts, {
                type: 'line',
                data: {
                    labels: productLabels.length > 0 ? productLabels : ['No Data'],
                    datasets: [{
                        label: 'Products Added',
                        data: productTotals.length > 0 ? productTotals : [0],
                        borderColor: '#10B981', // Emerald Green
                        backgroundColor: gradientProducts,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10B981',
                        pointBorderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: commonTooltipConfig
                    },
                    scales: commonScaleConfig,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                }
            });
        });
    </script>
    <script>
        if (window.opener) {
            window.close();
        }
    </script>
    @endsection