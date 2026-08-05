@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="text-center mb-5">
                <h5 class="mb-3">Contact Us</h5>
                <p class="text-muted">Have questions? We're here to help. Reach out to our team and we'll get back to you as soon as possible.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="bi bi-envelope fs-1 text-primary"></i>
                            </div>
                            <h6 class="card-title">Email Support</h6>
                            <p class="card-text text-muted small">For general inquiries and support</p>
                            <a href="mailto:support@zeosync.example" class="text-decoration-none">support@zeosync.example</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="bi bi-telephone fs-1 text-primary"></i>
                            </div>
                            <h6 class="card-title">Phone Support</h6>
                            <p class="card-text text-muted small">Mon-Fri, 9am-6pm EST</p>
                            <a href="tel:+1-555-123-4567" class="text-decoration-none">+1 (555) 123-4567</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="bi bi-chat-dots fs-1 text-primary"></i>
                            </div>
                            <h6 class="card-title">Live Chat</h6>
                            <p class="card-text text-muted small">Instant support for quick questions</p>
                            <a href="#" class="text-decoration-none">Start Chat</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-5">
                <div class="card-body">
                    <h6 class="card-title mb-4">Send Us a Message</h6>

                    @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                    @endif

                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('contact.store') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" id="name" placeholder="John Doe" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" id="email" placeholder="john@example.com" value="{{ old('email') }}" required>
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" id="subject" placeholder="How can we help?" value="{{ old('subject') }}" required>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Message</label>
                                <textarea name="message" class="form-control" id="message" rows="5" placeholder="Tell us more about your inquiry..." required>{{ old('message') }}</textarea>
                            </div>
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary px-5">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title mb-3">Frequently Asked Questions</h6>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How do I integrate my Amazon and Shopify stores?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Getting started is easy! Sign up for an account, connect your Amazon and Shopify stores 
                                    using our secure integration wizard, and start syncing your products, inventory, and orders 
                                    within minutes.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    What platforms do you support?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Currently, we support Amazon Seller Central and Shopify. We're constantly working on 
                                    adding more platforms to provide you with a comprehensive multi-channel selling solution.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Is my data secure?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Absolutely. We use industry-standard encryption and security measures to protect your 
                                    data. All API connections are secure, and we never share your information with third parties.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection