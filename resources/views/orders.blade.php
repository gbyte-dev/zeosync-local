@extends('layouts.app')

@section('content')

@php
$currentShop = $activeShop ?? request('shop') ?? session('active_shop');
$source = $source ?? request('source', 'shopify');
$shopLabel = $currentShop ?: 'your connected store';
@endphp

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
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

    /* Stats Grid - Single Line */
    .saas-stats-grid {
        display: flex;
        flex-wrap: nowrap;
        /* Forces all 4 cards into a single line */
        gap: 12px;
        margin-bottom: 16px;
        overflow-x: auto;
        /* Allows horizontal swipe on very small mobile screens */
        padding-bottom: 4px;
        /* Prevents scrollbar clipping */
    }

    .saas-stat-card {
        flex: 1;
        min-width: 160px;
        /* Prevents cards from squishing too much */
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 10px 14px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        display: flex;
        justify-content: space-between;
        /* Puts label and value on a single line */
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

    /* Card & Toolbar */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .saas-toolbar {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
        background: #FFFFFF;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-form {
        display: flex;
        gap: 8px;
        flex: 1;
        max-width: 760px;
    }

    /* Inputs */
    .saas-input,
    .saas-select {
        display: block;
        height: 34px;
        padding: 4px 10px;
        font-size: 13px;
        color: #202223;
        background-color: #FFFFFF;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        transition: border-color 0.15s, box-shadow 0.15s;
        outline: none;
    }

    .saas-input {
        flex: 1;
    }

    .saas-input:focus,
    .saas-select:focus {
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
    }

    /* Buttons */
    .saas-btn {
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
        height: 34px;
        text-decoration: none;
        white-space: nowrap;
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

    .saas-btn-light {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
    }

    .saas-btn-light:hover {
        background-color: #F4F6F8;
        color: #202223;
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
        padding: 0.4rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #E5E7EB;
        color: #202223;
        white-space: nowrap;
    }

    .saas-table tr:last-child td {
        border-bottom: none;
    }

    .action-link {
        color: #005BD3;
        text-decoration: none;
        font-weight: 600;
    }

    .action-link:hover {
        text-decoration: underline;
        color: #004299;
    }

    /* Badges Override (Targets PHP generated bootstrap string classes safely) */
    .saas-table .soft-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        border: none !important;
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

    .saas-table .bg-info-subtle {
        background-color: #EBF5FA !important;
        color: #006FBB !important;
    }

    /* Pagination Override (Targets Laravel Default Bootstrap Links) */
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

    .saas-pagination-footer nav {
        margin: 0;
    }

    .saas-pagination-footer .pagination {
        margin: 0;
        gap: 4px;
        align-items: center;
    }

    .saas-pagination-footer .page-item .page-link {
        padding: 4px 10px;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        background: #FFFFFF;
        color: #202223;
        font-size: 12px;
        font-weight: 600;
        box-shadow: none;
        margin: 0;
    }

    .saas-pagination-footer .page-item.active .page-link {
        background: #1A1A1A;
        color: #FFFFFF;
        border-color: #1A1A1A;
    }

    .saas-pagination-footer .page-item.disabled .page-link {
        background: #F9FAFB;
        color: #8C9196;
        border-color: #E5E7EB;
    }

    /* Responsiveness */
    @media(max-width: 768px) {
        .saas-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .search-form {
            max-width: 100%;
            flex-direction: column;
        }

        .desktop-orders-table {
            display: none;
        }

        .saas-pagination-footer {
            justify-content: center;
            text-align: center;
        }
    }
</style>

<div class="saas-wrapper">

    {{-- Page Header --}}
    <div class="saas-page-header">
        <div>
            <h1 class="saas-page-title">
                {{ $source === 'amazon' ? 'Amazon Orders' : 'Shopify Orders' }}
            </h1>
            <p class="saas-page-subtitle">
                Manage and track orders for {{ $shopLabel }}
            </p>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="saas-stats-grid">
        <div class="saas-stat-card">
            <div class="saas-stat-label">Total Items</div>
            <div class="saas-stat-value">{{ $totalOrders ?? $shopifyOrders->total() }}</div>
        </div>
        <div class="saas-stat-card">
            <div class="saas-stat-label">Synced</div>
            <div class="saas-stat-value text-success">{{ $paidOrders ?? 0 }}</div>
        </div>
        <div class="saas-stat-card">
            <div class="saas-stat-label">Pending</div>
            <div class="saas-stat-value text-warning">{{ $pendingOrders ?? 0 }}</div>
        </div>
        <div class="saas-stat-card">
            <div class="saas-stat-label">Errors</div>
            <div class="saas-stat-value text-danger">{{ $cancelledOrders ?? 0 }}</div>
        </div>
    </div>

    {{-- Orders Card --}}
    <div class="saas-card">

        <div class="saas-toolbar">
            <form method="GET" action="{{ route('orders.index') }}" class="search-form">
                <input type="hidden" name="shop" value="{{ $activeShop }}">
                <input type="hidden" name="source" value="shopify">

                <input type="text"
                    name="search"
                    class="saas-input"
                    placeholder="Search by Order ID, Customer, Email..."
                    value="{{ request('search') }}">

                <select name="status" class="saas-select">
                    <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Status</option>
                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>

                <button type="submit" class="saas-btn saas-btn-primary">
                    Search
                </button>
            </form>

            <a href="{{ route('orders.index', ['shop' => $activeShop, 'source' => $source, 'refresh' => 1]) }}"
                class="saas-btn saas-btn-light">
                <i class="bi bi-arrow-repeat me-1"></i> Refresh
            </a>
        </div>

        @php
        $ordersData = $source === 'amazon' ? ($orders ?? []) : ($shopifyOrders ?? []);
        @endphp

        {{-- Desktop Table --}}
        <div class="table-responsive desktop-orders-table">
            @if($source === 'amazon')
            @if(!empty($ordersData) && count($ordersData) > 0)
            <table class="saas-table">
                <thead>
                    <tr>
                        <th class="ps-4">Order ID</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Items</th>
                        <th>Channel</th>
                        <th>Fulfillment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ordersData as $order)
                    @php
                    $status = strtolower($order['OrderStatus'] ?? '');
                    $badge = 'bg-info-subtle text-info border border-info-subtle';
                    if ($status === 'shipped') $badge = 'bg-success-subtle text-success border border-success-subtle';
                    elseif ($status === 'unshipped') $badge = 'bg-warning-subtle text-warning border border-warning-subtle';
                    elseif ($status === 'cancelled') $badge = 'bg-danger-subtle text-danger border border-danger-subtle';
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <a href="{{ route('amazon.order.detail', ['id' => $order['AmazonOrderId']]) }}"
                                class="action-link">
                                {{ $order['AmazonOrderId'] ?? '-' }}
                            </a>
                        </td>
                        <td>
                            <span class="badge {{ $badge }} soft-badge">
                                {{ $order['OrderStatus'] ?? '-' }}
                            </span>
                        </td>
                        <td>{{ $order['OrderTotal']['CurrencyCode'] ?? '' }} {{ $order['OrderTotal']['Amount'] ?? '0' }}</td>
                        <td>{{ ($order['NumberOfItemsShipped'] ?? 0) + ($order['NumberOfItemsUnshipped'] ?? 0) }}</td>
                        <td>{{ $order['SalesChannel'] ?? '-' }}</td>
                        <td>{{ $order['FulfillmentChannel'] ?? '-' }}</td>
                        <td>{{ isset($order['PurchaseDate']) ? \Carbon\Carbon::parse($order['PurchaseDate'])->format('d M Y') : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="text-center text-muted py-4" style="font-size: 13px;">No Amazon Orders Found</div>
            @endif
            @else
            @if($shopifyOrders->count() > 0)
            <table class="saas-table">
                <thead>
                    <tr>
                        <th class="ps-4">Order</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shopifyOrders as $order)
                    @php
                    $customerName = trim(($order->customer_first_name ?? '') . ' ' . ($order->customer_last_name ?? '')) ?: 'Guest';
                    $financialStatus = strtolower($order->financial_status ?? 'pending');
                    $badgeClass = 'bg-info-subtle text-info border border-info-subtle';
                    if ($financialStatus === 'paid') $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    elseif ($financialStatus === 'pending') $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                    elseif ($financialStatus === 'cancelled') $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                    @endphp
                    <tr>
                        <td class="ps-4 fw-bold text-dark">{{ $order->name ?? '#' . $order->order_number }}</td>
                        <td>{{ $customerName }}</td>
                        <td class="text-muted">{{ $order->email ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $badgeClass }} soft-badge">
                                {{ ucfirst($financialStatus) }}
                            </span>
                        </td>
                        <td>{{ $order->line_items_count ?? 0 }}</td>
                        <td class="fw-semibold">{{ $order->currency }} {{ number_format((float) $order->total_price, 2) }}</td>
                        <td class="text-muted">{{ $order->order_created_at ? $order->order_created_at->format('d M Y') : '-' }}</td>
                        <td class="text-end pe-4">
                            <a href="{{ route('orders.show', ['order' => $order->id, 'shop' => $activeShop]) }}"
                                class="action-link">
                                View →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="text-center text-muted py-4" style="font-size: 13px;">No Shopify Orders Found</div>
            @endif
            @endif
        </div>

        @if($source === 'shopify' && isset($shopifyOrders) && $shopifyOrders->hasPages())
        <div class="saas-pagination-footer">
            <div class="text-muted" style="font-size: 12px;">
                Showing {{ $shopifyOrders->firstItem() }} to {{ $shopifyOrders->lastItem() }} of {{ $shopifyOrders->total() }} results
            </div>
            {{ $shopifyOrders->links() }}
        </div>
        @endif

    </div>
</div>
@endsection