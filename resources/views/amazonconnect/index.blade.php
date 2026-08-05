@extends('layouts.app')

@section('content')

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
        /* Smaller base font for tighter UI */
    }

    .saas-wrapper {
        max-width: 1180px;
        margin: 0 auto;
        /* padding: 12px 16px; */
    }

    /* Page Header */
    .saas-page-header {
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .saas-page-title {
        font-size: 20px;
        font-weight: 650;
        letter-spacing: -0.5px;
        color: #1A1A1A;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .in-iframe .saas-page-title{
        font-size: 18px;
    }
    .saas-page-subtitle {
        color: #6D7175;
        font-size: 13px;
        margin-top: 4px;
        margin-bottom: 0;
    }

    /* Status Badges */
    .saas-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
    }

    .saas-badge-success {
        background-color: #AEE9D1;
        color: #005C3B;
    }

    .saas-badge-warning {
        background-color: #FFEA8A;
        color: #8A6116;
    }

    .saas-badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 4px;
    }

    .saas-badge-success .saas-badge-dot {
        background-color: #005C3B;
    }

    .saas-badge-warning .saas-badge-dot {
        background-color: #8A6116;
    }

    /* Cards */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .saas-card-header {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
    }

    .saas-card-title {
        font-size: 14px;
        font-weight: 600;
        margin: 0;
        color: #202223;
    }

    .saas-card-body {
        padding: 16px;
    }

    /* Banners & Alerts */
    .saas-banner {
        border-radius: 6px;
        padding: 10px 12px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 16px;
        font-size: 13px;
        line-height: 1.4;
    }

    .saas-banner-info {
        background-color: #EBF5FA;
        color: #202223;
        border: 1px solid #B4E1FA;
    }

    .saas-banner-info .bi {
        color: #006FBB;
        font-size: 16px;
        line-height: 1;
    }

    .saas-banner-success {
        background-color: #F1F8F5;
        color: #202223;
        border: 1px solid #AEE9D1;
    }

    .saas-banner-success .bi {
        color: #007F5F;
        font-size: 16px;
        line-height: 1;
    }

    /* Forms */
    .saas-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 4px;
        color: #202223;
    }

    .saas-select {
        display: block;
        width: 100%;
        height: 36px;
        padding: 6px 12px;
        font-size: 13px;
        color: #202223;
        background-color: #FFFFFF;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%228%22%20viewBox%3D%220%200%2012%208%22%20fill%3D%22none%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M1.41%200.589966L6%205.16997L10.59%200.589966L12%201.99997L6%207.99997L0%201.99997L1.41%200.589966Z%22%20fill%3D%22%235C5F62%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 10px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .saas-select:focus {
        outline: none;
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
    }

    /* Buttons */
    .saas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        text-align: center;
    }

    .saas-btn-primary {
        background-color: #1A1A1A;
        color: #FFFFFF;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .saas-btn-primary:hover {
        background-color: #333333;
        color: #FFFFFF;
    }

    .saas-btn-danger {
        background-color: #D82C0D;
        color: #FFFFFF;
    }

    .saas-btn-danger:hover {
        background-color: #B8250B;
        color: #FFFFFF;
    }

    .saas-btn-outline {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .saas-btn-outline:hover {
        background-color: #F4F6F8;
    }

    /* List Layouts */
    .saas-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .saas-list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #E5E7EB;
        font-size: 13px;
    }

    .saas-list-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .saas-list-label {
        color: #6D7175;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .saas-list-label .bi {
        font-size: 14px;
        color: #8C9196;
    }

    .saas-list-value {
        color: #202223;
        font-weight: 600;
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }

    .amazon-loader-small {
        width: 18px;
        height: 18px;
        border: 2px solid #dbe4f0;
        border-top: 2px solid #0d6efd;
        border-radius: 50%;
        animation: amazonSpin .8s linear infinite;
        flex-shrink: 0;
    }

    .amazon-email-modal {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 12px 35px rgba(0, 0, 0, .12);
    }

    .amazon-email-modal .modal-body {
        padding: 22px;
    }

    .amazon-email-icon {
        width: 50px;
        height: 50px;
        margin: 0 auto 12px;
        border-radius: 50%;
        background: #eef5ff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .amazon-email-icon i {
        font-size: 22px;
        color: #0d6efd;
    }

    .amazon-modal-title {
        text-align: center;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .amazon-modal-desc {
        text-align: center;
        color: #6c757d;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .amazon-checklist {
        margin: 0 0 16px;
        padding-left: 18px;
    }

    .amazon-checklist li {
        margin-bottom: 8px;
        color: #495057;
        font-size: 14px;
        line-height: 1.45;
    }

    .amazon-checklist li:last-child {
        margin-bottom: 0;
    }

    .amazon-checklist span {
        color: #6c757d;
    }

    .amazon-modal-note {
        text-align: center;
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 16px;
    }

    #amazonEmailModalClose {
        border-radius: 10px;
        font-weight: 600;
        height: 42px;
    }

    @keyframes amazonSpin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div class="container-fluid py-3">
    <div class="saas-wrapper">

        {{-- Page Header --}}
        <div class="saas-page-header">
            <div>
                <h1 class="saas-page-title">
                    Amazon Connect
                    @if($shop->amazon_seller_id && $shop->amazon_refresh_token)
                    <span class="saas-badge saas-badge-success">
                        <span class="saas-badge-dot"></span> Connected
                    </span>
                    @else
                    <span class="saas-badge saas-badge-warning">
                        <span class="saas-badge-dot"></span> Not Connected
                    </span>
                    @endif
                </h1>
                <p class="saas-page-subtitle">
                    Connect your Amazon Seller account and manage Shopify synchronization securely.
                </p>
            </div>
        </div>

        <div class="row g-3">

            {{-- Connect Form / Status Card --}}
            <div class="col-lg-7">
                <div class="saas-card h-100 mb-0">

                    @if($shop->amazon_seller_id && $shop->amazon_refresh_token)
                    <div class="saas-card-header">
                        <h2 class="saas-card-title">Connection Status</h2>
                    </div>
                    <div class="saas-card-body">
                        <div class="saas-banner saas-banner-success">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <strong class="d-block mb-1 text-dark">Amazon Account Connected</strong>
                                Great! You have connected your Amazon Seller account. To connect another account, please disconnect the current one first.
                            </div>
                        </div>

                        <input type="hidden" name="is_iframe" id="is_iframe" value="0">

                        <div class="mt-3 pt-3 border-top border-light">
                            <button type="button" onclick="confirmDisconnect()" class="saas-btn saas-btn-danger">
                                Disconnect Amazon
                            </button>
                        </div>
                    </div>

                    @else
                    <div class="saas-card-header">
                        <h2 class="saas-card-title">Setup Configuration</h2>
                    </div>
                    <div class="saas-card-body">
                        <div class="saas-banner saas-banner-info">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                <strong class="d-block mb-1 text-dark">Requirements</strong>
                                Please ensure you have an active professional Amazon Seller account. Private seller accounts are not supported.
                            </div>
                        </div>

                        <form action="{{ route('amazon.authorize') }}" id="amazonConnectForm" method="GET">
                            <input type="hidden" name="shop" value="{{request()->shop}}" id="shop_name">
                            <input type="hidden" name="is_iframe" id="is_iframe" value="0">

                            <div class="mb-3">
                                <label class="saas-label" for="marketplace_id">Primary Marketplace</label>
                                <select name="amazon_config" id="marketplace_id" class="saas-select" required>
                                    <option value="" selected disabled>Choose a marketplace...</option>

                                    <optgroup label="North America">
                                        <option value='{"id":"ATVPDKIKX0DER","region":"na","endpoint":"https://sellingpartnerapi-na.amazon.com"}'>United States (Amazon.com)</option>
                                        <option value='{"id":"A2EUQ1WTGCTBG2","region":"na","endpoint":"https://sellingpartnerapi-na.amazon.com"}'>Canada (Amazon.ca)</option>
                                        <option value='{"id":"A1AM78C64UM0Y8","region":"na","endpoint":"https://sellingpartnerapi-na.amazon.com"}'>Mexico (Amazon.com.mx)</option>
                                        <option value='{"id":"A2Q3Y263D00KWC","region":"na","endpoint":"https://sellingpartnerapi-na.amazon.com"}'>Brazil (Amazon.com.br)</option>
                                    </optgroup>

                                    <optgroup label="Europe, India & Middle East">
                                        <option value='{"id":"A1F83G8C2ARO7P","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>United Kingdom (Amazon.co.uk)</option>
                                        <option value='{"id":"A1PA6795UKMFR9","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>Germany (Amazon.de)</option>
                                        <option value='{"id":"A13V1IB3VIYZZH","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>France (Amazon.fr)</option>
                                        <option value='{"id":"APJ6JRA9NG5V4","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>Italy (Amazon.it)</option>
                                        <option value='{"id":"A1RKKUPIHCS9HS","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>Spain (Amazon.es)</option>
                                        <option value='{"id":"A21TJ7DG3LB67B","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>India (Amazon.in)</option>
                                        <option value='{"id":"A17E79C6D8W67H","region":"eu","endpoint":"https://sellingpartnerapi-eu.amazon.com"}'>United Arab Emirates (Amazon.ae)</option>
                                    </optgroup>

                                    <optgroup label="Far East & Oceania">
                                        <option value='{"id":"A1VC38T7YXB528","region":"fe","endpoint":"https://sellingpartnerapi-fe.amazon.com"}'>Japan (Amazon.co.jp)</option>
                                        <option value='{"id":"A39IBJ37TRP1C6","region":"fe","endpoint":"https://sellingpartnerapi-fe.amazon.com"}'>Australia (Amazon.com.au)</option>
                                        <option value='{"id":"A19S7P0821G9B","region":"fe","endpoint":"https://sellingpartnerapi-fe.amazon.com"}'>Singapore (Amazon.sg)</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2">
                                <span class="text-muted" style="font-size: 12px;">
                                    A popup window will open for Amazon authorization.
                                </span>

                                <button type="button" onclick="showAmazonAcknowledgeModal()" class="saas-btn saas-btn-primary">
                                    Authorize on Amazon <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif

                    <div id="amazonStatusCard" class="mt-4 d-none">

                        <div class="border rounded-3 p-3 bg-light">

                            <div class="d-flex align-items-center">

                                <div id="amazonStatusLoader" class="amazon-loader-small me-3"></div>

                                <div class="flex-grow-1">

                                    <h6 class="mb-1 fw-semibold text-dark">
                                        Connection Status
                                    </h6>

                                    <div id="amazonCurrentStatus" class="text-muted small">
                                        Preparing authorization...
                                    </div>

                                    <div
                                        id="amazonStatusInfo"
                                        class="small text-muted mt-2 d-none">

                                        We've sent an Amazon Connect link to
                                        <strong>{{ $shop->email }}</strong>.

                                        <br>

                                        Please check your inbox (or Spam/Junk folder) and click
                                        <strong>"Authorize with Amazon"</strong>
                                        to complete the setup.

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>
                </div>
            </div>

            {{-- Shop Details Card --}}
            <div class="col-lg-5">
                <div class="saas-card h-100 mb-0">
                    <div class="saas-card-header">
                        <h2 class="saas-card-title">Shop Details</h2>
                    </div>
                    <div class="saas-card-body">
                        <ul class="saas-list">
                            <li class="saas-list-item">
                                <span class="saas-list-label">
                                    <i class="bi bi-envelope"></i> Email
                                </span>
                                <span class="saas-list-value">{{ $shop->email }}</span>
                            </li>
                            <li class="saas-list-item">
                                <span class="saas-list-label">
                                    <i class="bi bi-shop"></i> Shop Name
                                </span>
                                <span class="saas-list-value">{{ $shop->shop_name }}</span>
                            </li>
                            <li class="saas-list-item">
                                <span class="saas-list-label">
                                    <i class="bi bi-amazon"></i> Amazon Seller ID
                                </span>
                                <span class="saas-list-value text-muted">{{ $shop->amazon_seller_id ?: 'Not connected' }}</span>
                            </li>
                            <li class="saas-list-item">
                                <span class="saas-list-label">
                                    <i class="bi bi-globe"></i> Marketplace ID
                                </span>
                                <span class="saas-list-value text-muted">{{ $shop->amazon_marketplace_id ?: 'Not connected' }}</span>
                            </li>
                            <li class="saas-list-item">
                                <span class="saas-list-label">
                                    <i class="bi bi-bag"></i> Shopify Domain
                                </span>
                                <span class="saas-list-value">{{ $shop->shop }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Confirmation Modal --}}
<div class="modal fade saas-modal" id="amazonAcknowledgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Add new Amazon connection</h5>
                    <p class="text-muted mb-0" style="font-size: 13px;">Confirm these points before continuing.</p>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <ol class="saas-ol">
                    <li>
                        You require an Amazon seller account in the region where you want to sell.
                        If you do not have an account, <a href="https://sell.amazon.com/" target="_blank">sign up here</a>.
                    </li>
                    <li>
                        You need a <strong>professional seller account</strong>. Private seller accounts do not support Amazon SP-API integration.
                    </li>
                    <li>
                        Amazon marketplaces are grouped by region. Other regions may require additional registrations.
                    </li>
                </ol>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="saas-btn saas-btn-outline" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" id="confirmConnectBtn" class="saas-btn saas-btn-primary">
                    Acknowledge and continue
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade"
    id="amazonEmailSentModal"
    tabindex="-1"
    aria-hidden="true"
    data-bs-backdrop="static">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content amazon-email-modal">

            <div class="modal-body">

                <div class="amazon-email-icon">
                    <i class="bi bi-envelope-check-fill"></i>
                </div>

                <h5 class="amazon-modal-title">
                    Check Your Email to Continue
                </h5>

                <p class="amazon-modal-desc">
                    We've sent an <strong>Amazon Connect</strong> link to
                    <strong>{{ $shop->email }}</strong>.
                </p>

                <ul class="amazon-checklist">
                    <li>Open your inbox <span>(check Spam/Junk if needed)</span>.</li>
                    <li>Click <strong>Authorize with Amazon</strong>.</li>
                    <li>Sign in and approve the connection.</li>
                    <li>You'll return here automatically after authorization.</li>
                </ul>

                <p class="amazon-modal-note">
                    This page updates automatically after authorization.
                </p>

                <button
                    type="button"
                    class="btn btn-primary w-100"
                    id="amazonEmailModalClose">
                    Close (30s)
                </button>

            </div>

        </div>

    </div>

</div>



<script>
    let emailModalTimer = null;
    let countdown = 30;

    function showAmazonEmailSentModal() {

        countdown = 30;

        const modal = new bootstrap.Modal(
            document.getElementById("amazonEmailSentModal")
        );

        modal.show();

        const btn = document.getElementById("amazonEmailModalClose");

        btn.innerHTML = `Close (${countdown}s)`;

        emailModalTimer = setInterval(function() {

            countdown--;

            btn.innerHTML = `Close (${countdown}s)`;

            if (countdown <= 0) {

                clearInterval(emailModalTimer);

                modal.hide();

            }

        }, 1000);

        btn.onclick = function() {

            clearInterval(emailModalTimer);

            modal.hide();

        };

    }

    function showAmazonAcknowledgeModal() {
        const marketplace = document.getElementById('marketplace_id').value;

        if (!marketplace) {
            alert('Please select a marketplace first.');
            return;
        }

        const myModal = new bootstrap.Modal(document.getElementById('amazonAcknowledgeModal'));
        myModal.show();
    }

    document.getElementById('confirmConnectBtn')?.addEventListener('click', function() {

        if (document.getElementById('is_iframe').value == '1') {
            startIframeAuthorization();
        } else {
            openPopupAuthorization();
        }

    });

    // Listen for messages from popup (success signal)
    window.addEventListener('message', function(event) {
        try {
            // Ensure message is from same origin
            if (event.origin !== window.location.origin) return;
        } catch (e) {
            // ignore
        }

        const data = event.data || {};
        if (data && data.type === 'amazon_connected') {
            // Stop any polling and refresh status
            authorizationPending = false;
            if (amazonProgressInterval) clearInterval(amazonProgressInterval);
            // Briefly show success then reload
            document.getElementById("amazonCurrentStatus").innerHTML = 'Amazon account connected successfully.';
            setTimeout(function() { location.reload(); }, 900);
        }
    }, false);

    // Open a popup window for non-iframe authorization
    function openPopupAuthorization() {
        const form = document.getElementById("amazonConnectForm");
        const url = form.action + "?" + new URLSearchParams(new FormData(form));

        const width = 1000;
        const height = 700;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        const features = `toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=${width},height=${height},top=${top},left=${left}`;

        const popup = window.open(url, 'amazonAuth', features);

        if (!popup) {
            alert('Popup blocked. Please allow popups for this site and try again.');
            return;
        }

        // Focus popup
        popup.focus();

        // Show status card and start polling
        document.getElementById("amazonStatusCard").classList.remove('d-none');
        document.getElementById("amazonCurrentStatus").innerHTML = 'Waiting for authorization...';
        authorizationPending = true;
        startAmazonProgressPolling();

        // Detect popup closed without completing
        const popupChecker = setInterval(function() {
            if (popup.closed) {
                clearInterval(popupChecker);
                if (authorizationPending) {
                    // If still pending, switch to email flow message
                    document.getElementById("amazonCurrentStatus").innerHTML = 'Popup closed. If you completed authorization, please wait a moment. Otherwise try again.';
                    // continue polling for a short period
                }
            }
        }, 800);
    }

    function confirmDisconnect() {
        if (confirm('Are you sure you want to disconnect Amazon?')) {
            window.location.href = "{{ route('amazon.disconnect', ['shop' => $activeShop]) }}";
        }
    }

    function updateiframcheck() {
        document.getElementById('is_iframe').value = (window.self !== window.top) ? '1' : '0';
    }
    updateiframcheck();

    let amazonProgressInterval = null;
    let authorizationPending = false;

    // Start Authorization
    async function startIframeAuthorization() {

        bootstrap.Modal.getInstance(
            document.getElementById('amazonAcknowledgeModal')
        ).hide();

        document.getElementById("amazonStatusCard")
            .classList.remove("d-none");

        document.getElementById("amazonCurrentStatus").innerHTML =
            "Preparing authorization...";

        authorizationPending = true;

        const form = document.getElementById("amazonConnectForm");
        const url = form.action + "?" + new URLSearchParams(new FormData(form));

        try {

            const response = await fetch(url, {
                method: "GET",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                }
            });

            if (!response.ok) {
                throw new Error("Unable to start authorization.");
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message ?? "Authorization failed.");
            }

            document.getElementById("amazonCurrentStatus").innerHTML =
                "Authorization email sent.";
            document.getElementById("amazonStatusInfo")
                .classList.remove("d-none");
            showAmazonEmailSentModal();

            startAmazonProgressPolling();

        } catch (e) {

            authorizationPending = false;

            document.getElementById("amazonCurrentStatus").innerHTML =
                e.message;
        }
    }


    // Poll Progress
    function startAmazonProgressPolling() {

        if (amazonProgressInterval) {
            clearInterval(amazonProgressInterval);
        }

        const shop = document.getElementById("shop_name").value;

        amazonProgressInterval = setInterval(async function() {

            try {

                const response = await fetch(
                    "{{ route('amazon.connect.progress') }}?shop=" + shop
                );

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                document.getElementById("amazonCurrentStatus").innerHTML =
                    data.message;

                if (data.completed) {
                    document.getElementById("amazonStatusInfo")
                        .classList.add("d-none");

                    authorizationPending = false;

                    clearInterval(amazonProgressInterval);

                    document.getElementById("amazonStatusLoader").innerHTML =
                        '<i class="bi bi-check-circle-fill text-success fs-5"></i>';

                    document.getElementById("amazonStatusLoader")
                        .classList.remove("amazon-loader-small");

                    document.getElementById("amazonCurrentStatus").innerHTML =
                        "Amazon account connected successfully.";

                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }

            } catch (e) {
                console.error(e);
            }

        }, 2000);
    }


    // Stop polling if user leaves tab
    document.addEventListener("visibilitychange", function() {

        if (!authorizationPending) {
            return;
        }

        if (document.hidden) {

            clearInterval(amazonProgressInterval);

            document.getElementById("amazonCurrentStatus").innerHTML =
                "Authorization email sent. Please check your email.";

        } else {

            startAmazonProgressPolling();

        }

    });


    // Auto stop after 15 minutes
    setTimeout(function() {

        if (!authorizationPending) {
            return;
        }

        clearInterval(amazonProgressInterval);

        authorizationPending = false;

        document.getElementById("amazonStatusLoader").style.display = "none";

        document.getElementById("amazonCurrentStatus").innerHTML =
            "Authorization email sent. Please check your email.";

    }, 900000);
</script>

@endsection