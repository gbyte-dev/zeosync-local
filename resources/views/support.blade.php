@extends('layouts.app')

@section('content')

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style nonce="{{ $cspNonce }}">
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
    }

    .saas-wrapper {
        /* max-width: 1180px; */
        /* margin: 0 auto;   */
        padding: 12px 16px;
    }

    /* Page Header */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
    }

    .saas-page-subtitle {
        color: #6D7175;
        font-size: 12px;
        margin: 0;
    }

    /* Cards */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .saas-card-body {
        padding: 16px;
    }

    .saas-card-title {
        font-size: 14px;
        font-weight: 650;
        color: #1A1A1A;
        margin: 0 0 8px 0;
    }

    .saas-card-desc {
        font-size: 12px;
        color: #6D7175;
        margin: 0 0 12px 0;
        line-height: 1.5;
    }

    /* Buttons */
    .saas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        height: 28px;
        text-decoration: none;
    }

    .saas-btn-outline {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .saas-btn-outline:hover {
        background-color: #F9FAFB;
        border-color: #1A1A1A;
        color: #1A1A1A;
    }

    /* Typography & Lists */
    .saas-list {
        padding-left: 24px;
        margin: 0 0 12px 0;
        color: #4A4A4A;
        font-size: 13px;
        line-height: 1.6;
    }

    .saas-list li {
        margin-bottom: 6px;
    }

    .saas-list li:last-child {
        margin-bottom: 0;
    }

    .saas-list strong {
        color: #1A1A1A;
    }

    /* Banners & Alerts */
    .saas-banner-success {
        border-radius: 8px;
        padding: 10px 12px;
        background-color: #F1F8F5;
        color: #005C3B;
        border: 1px solid #AEE9D1;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 12px;
        line-height: 1.4;
        font-weight: 500;
    }

    /* Video Placeholder */
    .saas-video-placeholder {
        background-color: #F9FAFB;
        border: 1px dashed #C9CCCF;
        border-radius: 8px;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8C9196;
        font-size: 12px;
        font-weight: 500;
    }

    /* Accordion Overrides (Targets Bootstrap) */
    .accordion-item {
        border: 1px solid #E5E7EB;
        border-radius: 8px !important;
        margin-bottom: 8px;
        overflow: hidden;
        background: #FFFFFF;
    }

    .accordion-item:last-child {
        margin-bottom: 0;
    }

    .accordion-button {
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 600;
        color: #1A1A1A;
        background-color: #F9FAFB;
        box-shadow: none !important;
    }

    .accordion-button:not(.collapsed) {
        color: #1A1A1A;
        background-color: #F4F6F8;
        border-bottom: 1px solid #E5E7EB;
    }

    .accordion-body {
        padding: 12px 16px;
        font-size: 13px;
        color: #6D7175;
        line-height: 1.5;
        background: #FFFFFF;
    }
</style>

<div class="saas-wrapper mt-3">

    {{-- Page Header --}}
    <div class="saas-page-header">
        <h1 class="saas-page-title">Help & Docs</h1>
        <p class="saas-page-subtitle">Guides, FAQs and resources to help you get started.</p>
    </div>

    {{-- Quick Help Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="saas-card h-100 mb-0">
                <div class="saas-card-body d-flex flex-column h-100">
                    <h5 class="saas-card-title">Connect Amazon</h5>
                    <p class="saas-card-desc flex-grow-1">Learn how to connect your Amazon seller account to the app.</p>
                    <div class="mt-auto pt-2">
                        <a href="#connect-amazon" class="saas-btn saas-btn-outline">View Guide</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="saas-card h-100 mb-0">
                <div class="saas-card-body d-flex flex-column h-100">
                    <h5 class="saas-card-title">Product Sync</h5>
                    <p class="saas-card-desc flex-grow-1">Understand how products sync between Amazon & Shopify.</p>
                    <div class="mt-auto pt-2">
                        <a href="#product-sync" class="saas-btn saas-btn-outline">View Guide</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="saas-card h-100 mb-0">
                <div class="saas-card-body d-flex flex-column h-100">
                    <h5 class="saas-card-title">FAQs</h5>
                    <p class="saas-card-desc flex-grow-1">Answers to common questions and troubleshooting steps.</p>
                    <div class="mt-auto pt-2">
                        <a href="#faq" class="saas-btn saas-btn-outline">Browse FAQs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- How to Connect Amazon --}}
    <div class="saas-card mb-3" id="connect-amazon">
        <div class="saas-card-body">
            <h5 class="saas-card-title">How to Connect Amazon</h5>
            <ol class="saas-list">
                <li>Go to the <strong>Amazon Connect</strong> menu in the app.</li>
                <li>Select your <strong>Primary Marketplace</strong> from the dropdown.</li>
                <li>Click the <strong>Authenticate</strong> button.</li>
                <li>You will be redirected to your <strong>Amazon Seller Central</strong> account.</li>
                <li>Log in (if required) and <strong>grant permission</strong> to the app.</li>
                <li>After approval, you will be <strong>automatically redirected back</strong> to the app.</li>
            </ol>

            <div class="saas-banner-success mt-3 mb-0">
                <i class="bi bi-check-circle-fill"></i>
                <div>Your Amazon account is now connected and ready to sync.</div>
            </div>
        </div>
    </div>

    {{-- How Product Sync Works --}}
    <div class="saas-card mb-3" id="product-sync">
        <div class="saas-card-body">
            <h5 class="saas-card-title">How Product Sync Works</h5>
            <ul class="saas-list mb-0">
                <li>Products from Amazon are matched to Shopify using SKU.</li>
                <li>If SKU doesn't match, manual mapping is required.</li>
                <li>Stock & price sync happens every hour (if auto-sync is enabled).</li>
                <li>Errors and mismatches are logged in Sync Logs.</li>
            </ul>
        </div>
    </div>

    {{-- Video Tutorials (Optional) --}}
    <div class="saas-card mb-3">
        <div class="saas-card-body">
            <h5 class="saas-card-title">Video Tutorials</h5>
            <p class="saas-card-desc mb-3">Coming soon — step-by-step video walkthroughs.</p>
            <div class="saas-video-placeholder">
                <iframe src="https://www.youtube.com/embed/-QYseKCaQyc?si=hAVLDgmSd62uo6p6" title="Zeosync tutorial" style="width: -webkit-fill-available;width: -moz-available; height: 400px;" allowfullscreen></iframe>
            </div>
        </div>
    </div>

    {{-- FAQs --}}
    <div class="saas-card mb-0" id="faq">
        <div class="saas-card-body">
            <h5 class="saas-card-title mb-2">Frequently Asked Questions</h5>

            <div class="d-flex align-items-center mb-3">
                <input id="faqSearch" class="form-control me-2" type="search" placeholder="Search FAQs..." aria-label="Search FAQs" style="max-width:420px; height:36px; font-size:13px;" />
                <a href="#product-sync" class="saas-btn saas-btn-outline">Product Sync Guide</a>
            </div>

            <div class="accordion" id="faqAccordion">

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
                            Why are my products not syncing?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Check SKU mapping between Amazon and Shopify, ensure the Amazon store is connected, and verify auto-sync is enabled. If SKUs differ, set up manual mapping in the Inventory page.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                            How often does syncing run?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            By default, automatic sync runs every hour when auto-sync is enabled. You can run a manual sync from the Inventory page for immediate updates.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                            Can I manually trigger sync?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes — open the Inventory page and use the "Manual Sync" action. Manual sync will process selected SKUs immediately, subject to rate limits.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                            What if my inventory shows negative or incorrect values?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Negative inventory usually indicates mismatched SKUs or duplicate listings. Check the Sync Logs for errors and reconcile mismatched SKUs using the Inventory mapping tools.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                            How do I disconnect or reconnect my Amazon account?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Go to Amazon Connect settings in the app and choose "Disconnect" to remove the link. To reconnect, follow the "Connect Amazon" guide and re-authenticate via Seller Central.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                            Do you support variants, bundles or multi-location stock?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The app matches by SKU and supports variant sync if SKUs are mapped per variant. Bundles require manual handling; multi-location stock depends on your Shopify plan and settings — check the Inventory & Locations docs.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7" aria-expanded="false" aria-controls="faq7">
                            Where can I find logs and troubleshooting information?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Check the Sync Logs page for detailed error messages. Copy error IDs and include them when contacting support to speed up troubleshooting.
                        </div>
                    </div>
                </div>

            </div>

            <div class="mt-3 d-flex gap-2">
                <a href="mailto:support@zeosync.io" class="saas-btn saas-btn-outline">Contact Support</a>
                <a href="/docs" class="saas-btn saas-btn-outline">View Documentation</a>
            </div>

            <div class="saas-banner-success mt-3" id="contact-support" style="font-size:13px;">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Need personalized help? Email <strong>support@zeosync.io</strong> with your store URL and any Sync Log IDs — our team typically responds within one business day.
                </div>
            </div>

        </div>
    </div>

</div>
<form method="GET" action="{{ route('shopify.install') }}" style="display:none;">
    <input type="text" name="shop" class="form-control" placeholder="your-store-name" value="{{session('active_shop')}}" required>
    <button type="submit" class="btn btn-primary px-4" id="connect_to_store"> Connect Store </button>
</form>
@endsection