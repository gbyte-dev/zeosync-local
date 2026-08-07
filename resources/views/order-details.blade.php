@extends('layouts.app')

@section('content')

@php
$currentShop = $activeShop ?? request('shop') ?? session('active_shop');
$shipping = $order->shipping_address ?? [];
$billing = $order->billing_address ?? [];
$customer = $order->customer ?? [];
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
        height: 32px;
        text-decoration: none;
    }

    .saas-btn-outline {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .saas-btn-outline:hover {
        background-color: #F4F6F8;
        color: #202223;
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

    /* Panels / Cards */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .saas-card-header {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        align-items: center;
        gap: 12px;
        background: #FAFBFC;
    }

    .saas-icon-box {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        background: #EBF5FA;
        color: #006FBB;
        flex-shrink: 0;
    }

    .saas-card-title {
        font-size: 14px;
        font-weight: 650;
        color: #1A1A1A;
        margin: 0;
    }

    .saas-card-desc {
        font-size: 11px;
        color: #6D7175;
        margin: 2px 0 0;
    }

    .saas-card-body {
        padding: 16px;
    }

    /* Info Grids */
    .saas-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .saas-address-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .saas-info-box {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 10px 14px;
    }

    .saas-info-label {
        font-size: 11px;
        font-weight: 600;
        color: #6D7175;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .saas-info-value {
        font-size: 13px;
        font-weight: 650;
        color: #202223;
        word-break: break-word;
        line-height: 1.4;
    }

    .saas-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 12px;
    }

    /* Badges */
    .saas-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        text-transform: capitalize;
        border: none !important;
    }

    .bg-success-subtle {
        background-color: #AEE9D1 !important;
        color: #005C3B !important;
    }

    .bg-warning-subtle {
        background-color: #FFEA8A !important;
        color: #8A6116 !important;
    }

    .bg-secondary-subtle {
        background-color: #E3E5E7 !important;
        color: #4A4A4A !important;
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
        padding: 10px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #E5E7EB;
        color: #202223;
    }

    .saas-table tr:last-child td {
        border-bottom: none;
    }

    .saas-table-wrap {
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        overflow: hidden;
    }

    /* Totals Box */
    .saas-totals-wrapper {
        display: flex;
        justify-content: flex-end;
        margin-top: 16px;
    }

    .saas-totals-box {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 12px 16px;
        width: 100%;
        max-width: 320px;
    }

    .saas-total-line {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 13px;
        color: #6D7175;
    }

    .saas-total-line strong {
        color: #202223;
    }

    .saas-grand-total {
        border-top: 1px solid #E5E7EB;
        margin-top: 8px;
        padding-top: 8px;
        font-size: 14px;
        font-weight: 700;
        color: #1A1A1A;
    }

    /* Responsiveness */
    @media(max-width: 768px) {
        .saas-address-grid {
            grid-template-columns: 1fr;
        }

        .saas-totals-box {
            max-width: 100%;
        }
    }
</style>

<div class="saas-wrapper">

    {{-- Back Button --}}
    <div class="mb-3">
        <a href="{{ route('orders.index', ['shop' => $activeShop, 'source' => 'shopify']) }}"
            class="saas-btn saas-btn-outline">
            <i class="bi bi-arrow-left me-2"></i> Back to Orders
        </a>
    </div>

    {{-- Hero Header --}}
    <div class="saas-page-header">
        <h1 class="saas-page-title">Order #{{ $order->name ?: $order->order_number }}</h1>
        <p class="saas-page-subtitle">Complete order details from Shopify webhook</p>
    </div>

    {{-- Order Status --}}
    <div class="saas-card">
        <div class="saas-card-header">
            <div class="saas-icon-box"><i class="bi bi-card-checklist"></i></div>
            <div>
                <h2 class="saas-card-title">Order Status</h2>
                <p class="saas-card-desc">Current order state and identifiers</p>
            </div>
        </div>

        <div class="saas-card-body">
            <div class="saas-info-grid">
                <div class="saas-info-box">
                    <div class="saas-info-label">Order ID</div>
                    <div class="saas-info-value saas-mono">{{ $order->shopify_order_id }}</div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Order Number</div>
                    <div class="saas-info-value">{{ $order->order_number ?: 'N/A' }}</div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Financial Status</div>
                    <div>
                        @if($order->financial_status === 'paid')
                        <span class="saas-badge bg-success-subtle text-success">Paid</span>
                        @elseif($order->financial_status === 'pending')
                        <span class="saas-badge bg-warning-subtle text-warning">Pending</span>
                        @else
                        <span class="saas-badge bg-secondary-subtle text-secondary">
                            {{ $order->financial_status ?: 'Pending' }}
                        </span>
                        @endif
                    </div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Fulfillment Status</div>
                    <div>
                        @if($order->fulfillment_status)
                        <span class="saas-badge bg-success-subtle text-success">
                            {{ $order->fulfillment_status }}
                        </span>
                        @else
                        <span class="saas-badge bg-secondary-subtle text-secondary">
                            Unfulfilled
                        </span>
                        @endif
                    </div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Source</div>
                    <div class="saas-info-value">{{ $order->source_name ?: 'web' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="saas-card">
        <div class="saas-card-header">
            <div class="saas-icon-box"><i class="bi bi-clock-history"></i></div>
            <div>
                <h2 class="saas-card-title">Timeline</h2>
                <p class="saas-card-desc">Order creation and processing dates</p>
            </div>
        </div>

        <div class="saas-card-body">
            <div class="saas-info-grid">
                <div class="saas-info-box">
                    <div class="saas-info-label">Created At</div>
                    <div class="saas-info-value">{{ optional($order->order_created_at)->format('d M Y, h:i A') ?: 'N/A' }}</div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Processed At</div>
                    <div class="saas-info-value">{{ optional($order->processed_at)->format('d M Y, h:i A') ?: 'N/A' }}</div>
                </div>

                @if($order->cancelled_at)
                <div class="saas-info-box">
                    <div class="saas-info-label">Cancelled At</div>
                    <div class="saas-info-value">{{ optional($order->cancelled_at)->format('d M Y, h:i A') }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Customer --}}
    <div class="saas-card">
        <div class="saas-card-header">
            <div class="saas-icon-box"><i class="bi bi-person"></i></div>
            <div>
                <h2 class="saas-card-title">Customer Information</h2>
                <p class="saas-card-desc">Buyer details and contact information</p>
            </div>
        </div>

        <div class="saas-card-body">
            <div class="saas-info-grid">
                <div class="saas-info-box">
                    <div class="saas-info-label">Customer Name</div>
                    <div class="saas-info-value">{{ $order->customer_name ?: ($customer['display_name'] ?? 'Guest') }}</div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Email</div>
                    <div class="saas-info-value">{{ $order->email ?: 'N/A' }}</div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Phone</div>
                    <div class="saas-info-value">{{ $order->customer_phone ?: ($order->phone ?: 'N/A') }}</div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Tags</div>
                    <div class="saas-info-value">{{ $order->tags ?: '—' }}</div>
                </div>
            </div>

            @if($order->note)
            <div class="saas-info-box mt-3">
                <div class="saas-info-label">Note</div>
                <div class="saas-info-value fw-normal">{{ $order->note }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Addresses --}}
    <div class="saas-card">
        <div class="saas-card-header">
            <div class="saas-icon-box"><i class="bi bi-geo-alt"></i></div>
            <div>
                <h2 class="saas-card-title">Addresses</h2>
                <p class="saas-card-desc">Billing and shipping locations</p>
            </div>
        </div>

        <div class="saas-card-body">
            <div class="saas-address-grid">
                <div class="saas-info-box">
                    <div class="saas-info-label">Billing Address</div>
                    <div class="saas-info-value fw-normal">
                        {{ collect([
                            $billing['name'] ?? null,
                            $billing['address1'] ?? null,
                            $billing['address2'] ?? null,
                            $billing['city'] ?? null,
                            $billing['province'] ?? null,
                            $billing['zip'] ?? null,
                            $billing['country'] ?? null
                        ])->filter()->implode(', ') ?: 'No billing address saved' }}
                    </div>
                </div>

                <div class="saas-info-box">
                    <div class="saas-info-label">Shipping Address</div>
                    <div class="saas-info-value fw-normal">
                        {{ collect([
                            $shipping['name'] ?? null,
                            $shipping['address1'] ?? null,
                            $shipping['address2'] ?? null,
                            $shipping['city'] ?? null,
                            $shipping['province'] ?? null,
                            $shipping['zip'] ?? null,
                            $shipping['country'] ?? null
                        ])->filter()->implode(', ') ?: 'No shipping address saved' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items --}}
    <div class="saas-card">
        <div class="saas-card-header">
            <div class="saas-icon-box"><i class="bi bi-bag"></i></div>
            <div>
                <h2 class="saas-card-title">Line Items</h2>
                <p class="saas-card-desc">Products purchased in this order</p>
            </div>
        </div>

        <div class="saas-card-body">
            <div class="saas-table-wrap table-responsive">
                <table class="saas-table">
                    <thead>
                        <tr>
                            <th class="ps-3">Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>SKU</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order->line_items ?? [] as $item)
                        <tr>
                            <td class="ps-3 fw-semibold text-dark">{{ $item['name'] ?? 'Item' }}</td>
                            <td>{{ $item['quantity'] ?? 'N/A' }}</td>
                            <td>{{ $order->currency ?: 'USD' }} {{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
                            <td class="saas-mono">{{ $item['sku'] ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4" style="font-size: 13px;">
                                No line items found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="saas-totals-wrapper">
                <div class="saas-totals-box">
                    <div class="saas-total-line">
                        <span>Subtotal</span>
                        <strong>{{ $order->currency ?: 'USD' }} {{ number_format((float) $order->subtotal_price, 2) }}</strong>
                    </div>

                    <div class="saas-total-line">
                        <span>Tax</span>
                        <strong>{{ $order->currency ?: 'USD' }} {{ number_format((float) $order->total_tax, 2) }}</strong>
                    </div>

                    @if((float) $order->total_discounts > 0)
                    <div class="saas-total-line">
                        <span>Discounts</span>
                        <strong>-{{ $order->currency ?: 'USD' }} {{ number_format((float) $order->total_discounts, 2) }}</strong>
                    </div>
                    @endif

                    <div class="saas-total-line saas-grand-total">
                        <span>Total</span>
                        <strong>{{ $order->currency ?: 'USD' }} {{ number_format((float) $order->total_price, 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection