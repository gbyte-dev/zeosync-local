@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h5 class="mb-3">Pricing Plans</h5>
                <p class="text-muted">Simple, transparent pricing to fit your business needs. All plans include core synchronization features.</p>
            </div>

            <div class="row g-4">
                <!-- Starter Plan -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">Starter</h6>
                            <h3 class="text-primary mb-3">$29<span class="text-muted fs-6">/month</span></h3>
                            <p class="card-text text-muted">Perfect for small businesses just getting started with multi-channel selling.</p>
                            <hr>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2">✓ Up to 500 products</li>
                                <li class="mb-2">✓ 1 Amazon store</li>
                                <li class="mb-2">✓ 1 Shopify store</li>
                                <li class="mb-2">✓ Basic inventory sync</li>
                                <li class="mb-2">✓ Email support</li>
                            </ul>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="{{ route('register') }}" class="btn btn-outline-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>

                <!-- Professional Plan -->
                <div class="col-md-4">
                    <div class="card h-100 border-primary">
                        <div class="card-header bg-primary text-white text-center">
                            <strong>Most Popular</strong>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title">Professional</h6>
                            <h3 class="text-primary mb-3">$79<span class="text-muted fs-6">/month</span></h3>
                            <p class="card-text text-muted">Ideal for growing businesses that need more products and advanced features.</p>
                            <hr>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2">✓ Up to 5,000 products</li>
                                <li class="mb-2">✓ 3 Amazon stores</li>
                                <li class="mb-2">✓ 3 Shopify stores</li>
                                <li class="mb-2">✓ Advanced inventory sync</li>
                                <li class="mb-2">✓ AI-powered optimization</li>
                                <li class="mb-2">✓ Priority support</li>
                            </ul>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="{{ route('register') }}" class="btn btn-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>

                <!-- Enterprise Plan -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">Enterprise</h6>
                            <h3 class="text-primary mb-3">Custom</h3>
                            <p class="card-text text-muted">For large-scale operations with unlimited products and dedicated support.</p>
                            <hr>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2">✓ Unlimited products</li>
                                <li class="mb-2">✓ Unlimited stores</li>
                                <li class="mb-2">✓ Full API access</li>
                                <li class="mb-2">✓ Custom integrations</li>
                                <li class="mb-2">✓ Dedicated account manager</li>
                                <li class="mb-2">✓ 24/7 phone support</li>
                            </ul>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="{{ route('contact') }}" class="btn btn-outline-primary w-100">Contact Sales</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-5">
                <div class="card-body">
                    <h6 class="card-title text-center mb-3">All Plans Include</h6>
                    <div class="row text-center">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <p class="text-muted mb-0">Real-time Sync</p>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <p class="text-muted mb-0">Secure API Connections</p>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <p class="text-muted mb-0">99.9% Uptime SLA</p>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-0">Cancel Anytime</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted">
                    Questions about pricing? <a href="{{ route('contact') }}">Contact our sales team</a> for a personalized quote.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection