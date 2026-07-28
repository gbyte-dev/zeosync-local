@extends('layouts.app')

@section('content')

<div class="container">

    <h5 class="mb-4">Return / Refund Details</h5>

    <!-- ORDER INFO -->
    <div class="card mb-3">
        <div class="card-body">
            <p><strong>Order ID:</strong> {{ $order['name'] }}</p>
            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($order['createdAt'])->format('d M Y') }}</p>

            @if(!empty($order['customer']))
                <p><strong>Customer:</strong>
                    {{ $order['customer']['firstName'] ?? '' }}
                    {{ $order['customer']['lastName'] ?? '' }}
                    ({{ $order['customer']['email'] ?? '' }})
                </p>
            @endif
        </div>
    </div>

    <!-- REFUND ITEMS -->
    <div class="card mb-3">
        <div class="card-body">

            <h5 class="mb-3">Refund Items</h5>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Quantity</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($refunds as $item)
                    <tr>
                        <td>{{ $item['product_name'] }}</td>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>
                            {{ $item['type'] === 'manual' ? 'Manual' : 'Product' }}
                        </td>
                        <td>
                            {{ $item['refund_amount'] }} {{ $item['currency'] }}
                        </td>
                        <td>
                            {{ \Carbon\Carbon::parse($item['created_at'])->format('d M Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>

    <!-- TOTAL -->
    <div class="card mb-3">
        <div class="card-body">
            <h5>Total Refund</h5>
            <p>
                <strong>
                    {{ collect($refunds)->sum('refund_amount') }}
                    {{ $refunds[0]['currency'] ?? '' }}
                </strong>
            </p>
        </div>
    </div>

    <!-- BACK BUTTON -->
    <a href="{{ url()->previous() }}" class="btn btn-secondary">
        ← Back
    </a>

</div>

@endsection