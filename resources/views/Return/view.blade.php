@extends('layouts.app')

@section('content')

<div class="container-fluid py-3">

    <div class="saas-page-header mb-3">
        <div class="saas-page-title-wrap">
            <h5 class="saas-page-title">Return / Refund Details</h5>
            <p class="saas-page-subtitle">Order refunds summary and details</p>
        </div>
        <div>
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">← Back</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <p class="mb-1"><strong>Order ID:</strong> {{ $order['name'] }}</p>
                    <p class="mb-1"><strong>Date:</strong> {{ \Carbon\Carbon::parse($order['createdAt'])->format('d M Y') }}</p>
                    @if(!empty($order['customer']))
                        <p class="mb-0"><strong>Customer:</strong>
                            {{ $order['customer']['firstName'] ?? '' }} {{ $order['customer']['lastName'] ?? '' }}
                            <span class="text-muted">({{ $order['customer']['email'] ?? '' }})</span>
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-end">
                    <div class="text-muted small">Total Refund</div>
                    <div class="h5 mb-0">
                        <strong>{{ number_format(collect($refunds)->sum('refund_amount'), 2) }} {{ $refunds[0]['currency'] ?? '' }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3">Refunds</h6>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Detail</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refunds as $item)
                        <tr>
                            <td>
                                @if(!empty($item['product_name']))
                                    <div class="fw-semibold">{{ $item['product_name'] }}</div>
                                    <div class="text-muted small">SKU: {{ $item['sku'] ?? '-' }}</div>
                                @else
                                    <div class="fw-semibold">Order-level refund</div>
                                    <div class="text-muted small">Order: {{ $order['name'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if(isset($item['type']) && $item['type'] === 'manual')
                                    Manual
                                @elseif(isset($item['type']) && $item['type'] === 'product')
                                    Product
                                @else
                                    Order
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($item['refund_amount'] ?? 0, 2) }} {{ $item['currency'] ?? '' }}</td>
                            <td class="text-end">{{ \Carbon\Carbon::parse($item['created_at'])->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No refund records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection