{{-- resources/views/admin/orders/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Orders')

@section('content')

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
                <tr>
                    <td>#1001</td>
                    <td>demo-store</td>
                    <td>$120</td>
                    <td><span class="badge bg-warning">Pending</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection