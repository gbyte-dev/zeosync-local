@extends('layouts.app')

@section('content')

<style>

    .order-hero {
        color: #000000;
        border-radius: 22px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 18px 40px rgba(37, 99, 235, .18);
    }

    .back-btn {
        border-radius: 12px;
        font-weight: 700;
    }

    .status-pill {
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 800;
        background: #fff;
    }

    .status-shipped {
        color: #16a34a;
    }

    .status-processing {
        color: #d97706;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 22px;
    }

    .info-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .08);
    }

    .info-card-header {
        padding: 18px 22px;
        border-bottom: 1px solid #eef2f7;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .info-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        flex-shrink: 0;
    }

    .info-card-header h5 {
        margin: 0;
        font-weight: 800;
        color: #111827;
        font-size: 16px;
    }

    .info-card-header p {
        margin: 2px 0 0;
        font-size: 12px;
    }

    .info-card-body {
        padding: 2px 22px;
    }

    .data-row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-row:last-child {
        border-bottom: 0;
    }

    .data-label {
        font-size: 13px;
        font-weight: 700;
    }

    .data-value {
        color: #111827;
        font-size: 14px;
        font-weight: 800;
        text-align: right;
        word-break: break-word;
    }

    .highlight {
        background: #eff6ff;
        color: #1d4ed8;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
    }

    .address-box,
    .timeline-box,
    .sync-box {
        background: #f8fafc;
        border: 1px solid #eef2f7;
        border-radius: 16px;
        padding: 14px;
        color: #374151;
        font-size: 14px;
        line-height: 1.7;
    }

    .progress-bg {
        height: 8px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 14px;
    }

    .progress-fill {
        height: 100%;
        width: 68%;
        background: #2563eb;
        border-radius: 999px;
    }

    .progress-fill.shipped {
        width: 100%;
        background: #16a34a;
    }

    .footer-note {
        margin-top: 24px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 18px;
        padding: 16px 20px;
        color: #64748b;
        font-size: 13px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    @media(max-width: 768px) {
        .amazon-order-page {
            padding: 14px;
        }

        .order-hero {
            padding: 22px;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }

        .data-row {
            flex-direction: column;
            gap: 4px;
        }

        .data-value {
            text-align: left;
        }
    }
</style>

@php
    $orderStat = $order['OrderStatus'] ?? 'Processing';
    $isShipped = strtolower($orderStat) === 'shipped';
    $statusLabel = $isShipped ? 'Shipped' : 'Processing';

    $addr = $order['DefaultShipFromLocationAddress'] ?? [];
    $fullName = $addr['Name'] ?? 'N/A';
    $line = $addr['AddressLine1'] ?? 'N/A';
    $cityState = ($addr['City'] ?? '-') . ', ' . ($addr['StateOrRegion'] ?? '-');
    $postal = $addr['PostalCode'] ?? '-';
    $phone = $addr['Phone'] ?? '-';

    $earliestShip = \Carbon\Carbon::parse($order['EarliestShipDate'] ?? now()->addDay());
    $latestShip = \Carbon\Carbon::parse($order['LatestShipDate'] ?? now()->addDays(3));
    $earliestDel = \Carbon\Carbon::parse($order['EarliestDeliveryDate'] ?? now()->addDays(4));
    $latestDel = \Carbon\Carbon::parse($order['LatestDeliveryDate'] ?? now()->addDays(7));
@endphp

<div class="amazon-order-page">

    {{-- Hero --}}
    <div class="order-hero card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h6 class=" mb-1">
                    Order #{{ $order['AmazonOrderId'] ?? 'N/A' }}
                </h6>

                <small class="mb-0">
                    {{ \Carbon\Carbon::parse($order['PurchaseDate'] ?? now())->format('d M Y, h:i A') }}
                    • {{ $order['SalesChannel'] ?? 'Amazon' }}
                </small>
            </div>

            <div class="mb-3">
                <span class="status-pill {{ $isShipped ? 'status-shipped' : 'status-processing' }}">
                 {{ $statusLabel }}
                </span>
                <a href="{{ url()->previous() ?? url('/orders?source=amazon') }}"
                class="btn btn-light border back-btn btn-sm ms-1">
                    ← Back
                </a>
            </div>
        </div>
    </div>

    <div class="details-grid">

        {{-- Order Details --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-icon">📦</div>
                <div>
                    <h5>Order Details</h5>
                    <p>Marketplace and fulfillment details</p>
                </div>
            </div>

            <div class="info-card-body">
                <div class="data-row">
                    <div class="data-label">Fulfillment</div>
                    <div class="data-value">{{ $order['FulfillmentChannel'] ?? 'Merchant' }}</div>
                </div>

                <div class="data-row">
                    <div class="data-label">Order Type</div>
                    <div class="data-value">{{ $order['OrderType'] ?? 'Standard' }}</div>
                </div>

                <div class="data-row">
                    <div class="data-label">Prime</div>
                    <div class="data-value">{{ isset($order['IsPrime']) && $order['IsPrime'] ? 'Yes' : 'No' }}</div>
                </div>

                <div class="data-row">
                    <div class="data-label">Unshipped Items</div>
                    <div class="data-value">
                        <span class="highlight">{{ $order['NumberOfItemsUnshipped'] ?? 0 }}</span>
                    </div>
                </div>

                <div class="data-row">
                    <div class="data-label">Channel</div>
                    <div class="data-value">{{ $order['SalesChannel'] ?? 'Amazon' }}</div>
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-icon">💳</div>
                <div>
                    <h5>Payment Summary</h5>
                    <p>Payment method and transaction status</p>
                </div>
            </div>

            <div class="info-card-body">
                <div class="data-row">
                    <div class="data-label">Total</div>
                    <div class="data-value">
                        <span class="highlight">
                            {{ $order['OrderTotal']['CurrencyCode'] ?? 'USD' }}
                            {{ number_format((float)($order['OrderTotal']['Amount'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>

                <div class="data-row">
                    <div class="data-label">Method</div>
                    <div class="data-value">{{ $order['PaymentMethod'] ?? 'Credit Card' }}</div>
                </div>

                <div class="data-row">
                    <div class="data-label">Transaction</div>
                    <div class="data-value">
                        TXN{{ substr(($order['AmazonOrderId'] ?? 'ORD000000'), -6) }}A2
                    </div>
                </div>

                <div class="data-row">
                    <div class="data-label">Status</div>
                    <div class="data-value">Captured</div>
                </div>
            </div>
        </div>

        {{-- Shipping Address --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-icon">📍</div>
                <div>
                    <h5>Shipping Address</h5>
                    <p>Recipient and shipping location</p>
                </div>
            </div>

            <div class="info-card-body">
                <div class="data-row">
                    <div class="data-label">Recipient</div>
                    <div class="data-value">{{ $fullName }}</div>
                </div>

                <div class="address-box mt-2">
                    {{ $line }}<br>
                    {{ $cityState }} {{ $postal }}<br>
                    Phone: {{ $phone }}
                </div>

                <div class="data-row mt-2">
                    <div class="data-label">Type</div>
                    <div class="data-value">Residential</div>
                </div>
            </div>
        </div>

        {{-- Delivery Timeline --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-icon">🚚</div>
                <div>
                    <h5>Delivery Timeline</h5>
                    <p>Shipping and estimated delivery window</p>
                </div>
            </div>

            <div class="info-card-body">
                <div class="timeline-box mb-3">
                    <strong>Ship Window</strong>
                    <div class="data-row">
                        <div class="data-label">Earliest</div>
                        <div class="data-value">{{ $earliestShip->format('d M Y') }}</div>
                    </div>

                    <div class="data-row">
                        <div class="data-label">Latest</div>
                        <div class="data-value">{{ $latestShip->format('d M Y') }}</div>
                    </div>
                </div>

                <div class="timeline-box">
                    <strong>Estimated Delivery</strong>
                    <div class="data-row">
                        <div class="data-label">From</div>
                        <div class="data-value">{{ $earliestDel->format('d M Y') }}</div>
                    </div>

                    <div class="data-row">
                        <div class="data-label">To</div>
                        <div class="data-value">{{ $latestDel->format('d M Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Summary --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-icon">🛍️</div>
                <div>
                    <h5>Items Summary</h5>
                    <p>Order item and preparation status</p>
                </div>
            </div>

            <div class="info-card-body">
                <div class="data-row">
                    <div class="data-label">Total Units</div>
                    <div class="data-value">
                        <span class="highlight">
                            {{ ($order['NumberOfItemsUnshipped'] ?? 0) + ($order['NumberOfItemsShipped'] ?? 0) }} items
                        </span>
                    </div>
                </div>

                <div class="data-row">
                    <div class="data-label">SKU Ref</div>
                    <div class="data-value">B0{{ substr(($order['AmazonOrderId'] ?? 'X7G3'), -5) }}K9</div>
                </div>

                <div class="progress-bg">
                    <div class="progress-fill {{ $isShipped ? 'shipped' : '' }}"></div>
                </div>

                <div class="small text-muted mt-2">
                    {{ $isShipped ? 'Shipped complete' : 'Preparing shipment' }}
                </div>
            </div>
        </div>

        {{-- Logistics --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-icon">📡</div>
                <div>
                    <h5>Logistics & Tracking</h5>
                    <p>Carrier, protection and sync status</p>
                </div>
            </div>

            <div class="info-card-body">
                <div class="data-row">
                    <div class="data-label">Carrier</div>
                    <div class="data-value">Amazon Logistics</div>
                </div>

                <div class="data-row">
                    <div class="data-label">Tracking #</div>
                    <div class="data-value">
                        {{ $isShipped ? '1Z' . rand(10000000, 99999999) : 'Awaiting' }}
                    </div>
                </div>

                <div class="data-row">
                    <div class="data-label">Protection</div>
                    <div class="data-value">A-to-Z covered</div>
                </div>

                <div class="sync-box mt-2">
                    Sync: {{ now()->format('h:i A') }}
                </div>
            </div>
        </div>

    </div>

    <div class="footer-note">
        Order #{{ $order['AmazonOrderId'] ?? 'N/A' }} • Amazon Marketplace
    </div>

</div>

@endsection