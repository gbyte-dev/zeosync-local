
@extends('admin.layout.app')

@section('title', 'Orders')

@section('content')
<div class="title-head"> <h4> Orders </h4></div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5>Orders</h5>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Shop</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($orders as $order)
                <tr>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->shop->shop ?? '-' }}</td>
                    <td>{{ $order->total }}</td>
                    <td>{{ $order->status }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection