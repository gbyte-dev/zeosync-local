@extends('layouts.guest')

@section('content')

<div class="bg-white">

    <section class="py-5 text-center border-bottom">
        <div class="container" style="max-width: 900px;">

            <h1 class="fw-bold mb-3">
                Sync Amazon & Shopify Effortlessly with Zeosync
            </h1>

            <p class="text-muted mb-4">
                Connect your store, automate product sync, and manage orders & returns — all in one place.
            </p>

            <form method="GET" action="{{ route('crm.entry') }}" class="d-flex justify-content-center">
                <div class="input-group" style="max-width: 450px;">

                    <input type="text" name="shop" class="form-control" placeholder="your-store-name" value="{{session('active_shop')}}" required>

                    <span class="input-group-text d-none d-md-inline-flex">.myshopify.com</span>

                    <button class="btn btn-primary px-4">
                        Connect Store
                    </button>

                </div>
            </form>

            <p class="text-muted small mt-2">
                Example: demo-store.myshopify.com
            </p>

        </div>
    </section>

    {{-- FEATURES --}}
    <section class="py-5">
        <div class="container">

            <div class="text-center mb-5">
                <h3 class="fw-bold">Everything You Need</h3>
                <p class="text-muted">Powerful tools to manage your Amazon business</p>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <div class="p-4 border rounded h-100">
                        <h5 class="fw-semibold">🔄 Auto Sync</h5>
                        <p class="text-muted small">
                            Automatically sync products, inventory, and orders between Amazon and Shopify.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-4 border rounded h-100">
                        <h5 class="fw-semibold">📦 Order Management</h5>
                        <p class="text-muted small">
                            View and manage all your Amazon orders directly from your dashboard.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-4 border rounded h-100">
                        <h5 class="fw-semibold">↩️ Returns & Refunds</h5>
                        <p class="text-muted small">
                            Handle returns and track refunds easily in one place.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section class="py-5 bg-light">
        <div class="container text-center">

            <h3 class="fw-bold mb-4">How It Works</h3>

            <div class="row g-4">

                <div class="col-md-4">
                    <div>
                        <div class="fw-bold mb-2">1. Connect</div>
                        <p class="text-muted small">
                            Enter your Shopify store and connect securely.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div>
                        <div class="fw-bold mb-2">2. Sync</div>
                        <p class="text-muted small">
                            Automatically sync products, inventory, and orders.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div>
                        <div class="fw-bold mb-2">3. Manage</div>
                        <p class="text-muted small">
                            Track orders, returns, and performance in one dashboard.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </section>

        {{-- VIDEO TUTORIAL --}}
    <section class="py-5">
        <div class="container text-center" style="max-width: 960px;">
            <h3 class="fw-bold mb-3">Quick Tutorial</h3>
            <p class="text-muted mb-4">Watch this short video to get started in minutes.</p>
            <div class="ratio ratio-16x9 mb-3">
                <iframe src="https://www.youtube.com/embed/-QYseKCaQyc?si=hAVLDgmSd62uo6p6" title="Zeosync tutorial" allowfullscreen></iframe>
            </div>

            <p class="small text-muted">Prefer a guided walkthrough? Visit our <a href="{{route('contact')}}">Help Center</a>.</p>
        </div>
    </section>

<!-- 
    {{-- CTA --}}
    <section class="py-5 text-center">
        <div class="container">

            <h4 class="fw-bold mb-3">Get Started Now</h4>
            <p class="text-muted mb-4">
                Connect your store and start syncing in minutes.
            </p>

            <form method="GET" action="{{ route('shopify.install') }}" class="d-flex justify-content-center">
                <div class="input-group" style="max-width: 450px;">
                    <input type="text"
                        name="shop"
                        class="form-control"
                        placeholder="your-store-name"
                        required>

                    <span class="input-group-text">.myshopify.com</span>

                    <button class="btn btn-dark px-4">
                        Connect Store
                    </button>
                </div>
            </form>

        </div>
    </section> -->

</div>

@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Handle form submission
     try {
            console.log(window.top.location.href);
        } catch (e) {
            console.log("Cannot access top URL:", e.message);
        }
    });
</script>
@endpush