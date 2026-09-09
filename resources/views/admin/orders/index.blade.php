
@extends('admin.layout.app')

@section('title', 'Orders')

@section('content')
<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1 fw-bold">Orders</h5>
                    <p class="mb-0 text-muted small">View and manage customer orders</p>
                </div>
                <div>
                    <a class="btn btn-light btn-sm" href="#">Export</a>
                </div>
            </div>
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