@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <h5 class="mb-4">Privacy Policy</h5>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Information We Collect</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} collects information necessary to provide our synchronization services, including:
                    </p>
                    <ul class="text-muted">
                        <li>Store credentials and API keys for Amazon and Shopify integrations</li>
                        <li>Product information, inventory levels, and pricing data</li>
                        <li>Order details and customer information as needed for fulfillment</li>
                        <li>Account information and usage analytics</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">How We Use Your Information</h6>
                    <p class="card-text text-muted">
                        We use your data solely to provide and improve our synchronization services. Your information is used to:
                    </p>
                    <ul class="text-muted">
                        <li>Synchronize product listings, inventory, and orders between platforms</li>
                        <li>Provide customer support and technical assistance</li>
                        <li>Improve our services and develop new features</li>
                        <li>Send important service updates and notifications</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Data Security</h6>
                    <p class="card-text text-muted">
                        We implement industry-standard security measures to protect your data, including encryption of sensitive 
                        information, secure API connections, and regular security audits. We do not sell or share your personal 
                        data with third parties except as necessary to provide our services.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Your Rights</h6>
                    <p class="card-text text-muted">
                        You have the right to access, update, or delete your data at any time. You can also request a copy of 
                        your data or revoke API access through your account settings. For privacy-related inquiries, please 
                        contact us at privacy@zeosync.app.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection