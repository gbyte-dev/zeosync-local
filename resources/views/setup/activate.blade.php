@extends('layouts.app')

@section('content')

<style>
.setup-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.setup-card {
    width: 100%;
    max-width: 700px;
    border: 1px solid #e5e5e5;
    border-radius: 16px;
    padding: 30px;
    background: #fff;
    text-align: center;
}

.setup-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 10px;
}

.setup-subtitle {
    color: #666;
    margin-bottom: 20px;
}

.form-control {
    border-radius: 10px;
    padding: 12px;
}

.btn-activate {
    width: 100%;
    background: black;
    color: white;
    padding: 14px;
    border-radius: 12px;
    font-weight: 500;
}
</style>

<div class="container setup-container">

    <div class="setup-card">

        <div class="setup-title">Connect Your Shopify Store</div>
        <div class="setup-subtitle">
            Enter your store details to activate the app
        </div>

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf

            <!-- Shopify URL -->
            <div class="mb-3 text-start">
                <label class="mb-1">Shopify Store URL</label>
                <input type="text" name="shop_url"
                       placeholder="your-store.myshopify.com"
                       class="form-control" value="{{$shopModel?->shop}}" required readonly>
            </div>

            <!-- Shop Name -->
            <div class="mb-3 text-start">
                <label class="mb-1">Shop Name</label>
                <input type="text" name="shop_name"
                       placeholder="My Store"
                       class="form-control" value="{{$shopModel?->shop_name}}" required >
            </div>

            <!-- Email -->
            <div class="mb-3 text-start">
                <label class="mb-1">Email</label>
                <input type="email" name="email"
                       placeholder="owner@email.com"
                       class="form-control" value="{{$shopModel?->email}}" required>
            </div>

            <input type="hidden" name="access_token" value="{{$shopModel?->access_token}}">

            <!-- Button -->
            <button type="submit" class="btn-activate">
                Activate App
            </button>

        </form>

    </div>

</div>

@endsection