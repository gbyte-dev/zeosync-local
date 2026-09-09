@extends('admin.layout.app')

@section('title', 'Products')

@section('content')

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1 fw-bold">Products</h5>
                    <p class="mb-0 text-muted small">Manage product catalog and images</p>
                </div>
                <a href="{{ route('admin.products.create') ?? '#' }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-2"></i> Add Product
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="row mt-4">
    <!-- <div class="col-md-3">
        <div class="card shadow-sm">
            <img src="https://via.placeholder.com/300" class="card-img-top">
            <div class="card-body">
                <h6>Product Name</h6>
                <p class="text-muted">$50</p>
            </div>
        </div>
    </div> -->

    @foreach($products as $product)
        @php $productimg = ($product->images);    @endphp
        <div class="col-md-3">
            <div class="card shadow-sm">
                <img src="{{$productimg[0]['src'] ?? 'https://via.placeholder.com/300' }} " class="card-img-top">
                <div class="card-body">
                    <h6>{{ $product->title }}</h6>
                </div>
            </div>
        </div>
    @endforeach

</div>
</div>

@endsection