@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <h5 class="mb-4">Terms & Conditions</h5>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Acceptance of Terms</h6>
                    <p class="card-text text-muted">
                        By accessing or using Zeosync's services, you agree to be bound by these Terms & Conditions. 
                        If you do not agree to these terms, please do not use our services. We reserve the right to 
                        modify these terms at any time, and your continued use of the service constitutes acceptance of any changes.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Service Description</h6>
                    <p class="card-text text-muted">
                        Zeosync provides a synchronization platform that integrates Amazon and Shopify stores. Our services 
                        include product listing synchronization, inventory management, order processing, and related features. 
                        We strive to maintain high service availability but do not guarantee uninterrupted access to our platform.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">User Responsibilities</h6>
                    <p class="card-text text-muted">
                        Users are responsible for maintaining the confidentiality of their account credentials and API keys. 
                        You agree to:
                    </p>
                    <ul class="text-muted">
                        <li>Provide accurate and complete information when using our services</li>
                        <li>Maintain the security of your account and promptly notify us of any unauthorized access</li>
                        <li>Comply with all applicable laws and regulations</li>
                        <li>Not use our services for any unlawful or prohibited activities</li>
                        <li>Respect intellectual property rights</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Limitation of Liability</h6>
                    <p class="card-text text-muted">
                        Zeosync shall not be liable for any indirect, incidental, special, consequential, or punitive damages 
                        resulting from your use or inability to use the service. We are not responsible for any data loss, 
                        business interruption, or lost profits arising from the use of our platform.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Contact Information</h6>
                    <p class="card-text text-muted">
                        For questions or concerns regarding these Terms & Conditions, please contact us at 
                        legal@zeosync.example. We will respond to your inquiry within a reasonable timeframe.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection