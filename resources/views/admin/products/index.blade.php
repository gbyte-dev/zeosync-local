@extends('admin.layout.app')

@section('title', 'Products')

@section('content')


<div class="card shadow-sm">
    <div class="card-header">
        <h5>Products</h5>
    </div>
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