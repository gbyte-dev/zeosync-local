@extends('layouts.activate')

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
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.setup-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #202223;
}

.setup-subtitle {
    color: #666;
    margin-bottom: 20px;
}

.form-control {
    border-radius: 10px;
    padding: 12px;
}

.activation-success-banner {
    display: none;
    background-color: #ecfdf5;
    border: 1px solid #10b981;
    color: #065f46;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    font-size: 16px;
    font-weight: 600;
    animation: fadeIn 0.3s ease-in-out;
}

.activation-error-banner {
    display: none;
    background-color: #fef2f2;
    border: 1px solid #ef4444;
    color: #991b1b;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
    font-size: 14px;
    text-align: left;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="container setup-container">

    <div class="setup-card">

        <div class="setup-title">Connect Your Shopify Store</div>
        <div class="setup-subtitle">
            Enter your store details to activate the app
        </div>

        <!-- Success Banner -->
        <div id="activationSuccessAlert" class="activation-success-banner text-center" role="alert">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                <span id="activationSuccessMessage">Store activated successfully.</span>
            </div>
            <div id="activationSuccessSubtext" class="small text-muted mt-1 fw-normal" style="display: none;"></div>
        </div>

        <!-- Error Banner -->
        <div id="activationErrorAlert" class="activation-error-banner" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span id="activationErrorMessage"></span>
        </div>

        <form method="POST" action="{{ route('setup.store', ['shop' => $shopModel?->shop]) }}" id="activateForm">
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
                <input type="text" name="shop_name" placeholder="My Store"
                       class="form-control" value="{{$shopModel?->shop_name}}" required >
            </div>

            <!-- Email -->
            <div class="mb-3 text-start">
                <label class="mb-1">Email</label>
                <input type="email" name="email" placeholder="owner@email.com"
                       class="form-control" value="{{$shopModel?->email}}" required>
            </div>

            <input type="hidden" name="access_token" value="{{$shopModel?->access_token}}">

            <!-- Button -->
            <button type="submit" class="btn-activate btn btn-primary float-end" id="submitBtn">
                Activate App
            </button>

        </form>

    </div>

</div>

@endsection
@push('scripts')
<script>
(function() {
    let isSubmitting = false;
    let isPolling = false;
    let pollInterval = null;
    let pollTimeout = null;
    let hasCompletedSuccessFlow = false;

    const form = document.getElementById('activateForm');
    const button = document.getElementById('submitBtn');
    const successAlert = document.getElementById('activationSuccessAlert');
    const successMsg = document.getElementById('activationSuccessMessage');
    const successSubtext = document.getElementById('activationSuccessSubtext');
    const errorAlert = document.getElementById('activationErrorAlert');
    const errorMsg = document.getElementById('activationErrorMessage');

    // Detect execution context
    const isInsideIframe = (window.self !== window.top);
    const urlParams = new URLSearchParams(window.location.search);
    const hasPopupQuery = urlParams.get('popup') === '1' || urlParams.has('popup');
    const isNamedPopup = (window.name === 'shopifyAuth' || window.name === 'shopifyActivationPopup');
    const hasOpener = Boolean(window.opener && !window.opener.closed);
    const isPopup = !isInsideIframe && (hasOpener || isNamedPopup || hasPopupQuery);

    // Initial structured debug logging
    console.log('[Activation Context]', {
        isTop: window.self === window.top,
        hasOpener: Boolean(window.opener),
        openerNotClosed: Boolean(window.opener && !window.opener.closed),
        currentUrl: window.location.href,
        isInsideIframe: isInsideIframe,
        isPopup: isPopup,
        hasPopupQuery: hasPopupQuery,
        isNamedPopup: isNamedPopup,
        windowName: window.name || ''
    });

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        if (pollTimeout) {
            clearTimeout(pollTimeout);
            pollTimeout = null;
        }
        isPolling = false;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (isSubmitting || isPolling || hasCompletedSuccessFlow) {
            return;
        }

        isSubmitting = true;
        button.disabled = true;
        button.innerText = 'Activating...';
        errorAlert.style.display = 'none';
        successAlert.style.display = 'none';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });

            const contentType = response.headers.get('content-type') || '';
            let data = null;

            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                throw new Error('Server returned an unexpected response format. Please try again.');
            }

            if (!response.ok || !data.success) {
                let msg = data.message || 'Activation failed.';
                if (data.errors && typeof data.errors === 'object') {
                    const firstErr = Object.values(data.errors)[0];
                    if (Array.isArray(firstErr) && firstErr.length > 0) {
                        msg = firstErr[0];
                    }
                }
                throw new Error(msg);
            }

            // Form submitted successfully: now start DB-confirmed polling
            const shopDomain = data.shop || '{{ $shopModel?->shop }}';
            const pollUrl = data.poll_url || '{{ route("setup.activation.status") }}?shop=' + encodeURIComponent(shopDomain);
            const redirectUrl = data.redirect_url || '{{ route("dashboard", ["shop" => $shopModel?->shop]) }}';

            // Disable form inputs during polling
            Array.from(form.elements).forEach(function(el) { el.disabled = true; });
            button.innerText = 'Verifying activation...';

            isSubmitting = false;
            isPolling = true;

            const maxPollDuration = 30000; // 30 seconds timeout

            // Start 30s timeout guard
            pollTimeout = setTimeout(function() {
                stopPolling();
                if (!hasCompletedSuccessFlow) {
                    errorMsg.textContent = 'Activation is still processing. Please refresh and try again.';
                    errorAlert.style.display = 'block';
                    button.disabled = false;
                    button.innerText = 'Activate App';
                    Array.from(form.elements).forEach(function(el) {
                        if (el.name !== 'shop_url') el.disabled = false;
                    });
                }
            }, maxPollDuration);

            // Polling function
            async function checkActivationStatus() {
                if (hasCompletedSuccessFlow || !isPolling) {
                    return;
                }

                try {
                    const statusRes = await fetch(pollUrl, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!statusRes.ok) {
                        return;
                    }

                    const statusData = await statusRes.json();

                    // Structured logging of polling status response
                    console.log('[Activation Polling Status]', {
                        activated: Boolean(statusData && statusData.activated),
                        shop: statusData?.shop || null,
                        message: statusData?.message || null
                    });

                    if (statusData && statusData.activated === true) {
                        // DB CONFIRMED ACTIVATION
                        stopPolling();
                        hasCompletedSuccessFlow = true;

                        // 1. Display "Store activated successfully."
                        successMsg.textContent = 'Store activated successfully.';
                        successAlert.style.display = 'block';
                        button.innerText = 'Activated ✓';
                        button.classList.remove('btn-primary');
                        button.classList.add('btn-success');

                        if (typeof showToast === 'function') {
                            showToast('Store activated successfully.', 'success');
                        }

                        // Secure message target origin
                        const targetOrigin = window.location.origin && window.location.origin !== 'null'
                            ? window.location.origin
                            : '*';

                        const messagePayload = {
                            type: 'shopify_activated',
                            shop: shopDomain,
                            redirect_url: redirectUrl,
                            status: 'activated'
                        };

                        const legacyPayload = {
                            type: 'shopify_authenticated',
                            shop: shopDomain,
                            redirect_url: redirectUrl,
                            status: 'activated'
                        };

                        // 2. Send activation event to opener/parent
                        if (window.opener && !window.opener.closed) {
                            try {
                                window.opener.postMessage(messagePayload, targetOrigin);
                                window.opener.postMessage(legacyPayload, targetOrigin);
                            } catch (e) {
                                try {
                                    window.opener.postMessage(messagePayload, '*');
                                    window.opener.postMessage(legacyPayload, '*');
                                } catch (err) {}
                            }
                        }

                        if (isPopup) {
                            // 3. Structured logging before window.close() attempt
                            console.log('[Activation Action]', {
                                action: 'window.close()',
                                attempted: true,
                                isPopup: true,
                                hasOpener: Boolean(window.opener && !window.opener.closed)
                            });

                            // Immediately call window.close()
                            try {
                                window.close();
                            } catch (e) {
                                console.warn('window.close() error:', e);
                            }

                            // If browser blocks window.close(): show fallback message without auto-redirecting
                            setTimeout(function () {
                                if (!window.closed) {
                                    console.log('[Activation Action]', {
                                        action: 'popup_fallback_shown',
                                        fallback_branch_executed: true,
                                        windowClosed: window.closed
                                    });

                                    successSubtext.style.display = 'block';
                                    successSubtext.innerHTML = 'Store activated successfully. You can close this window or <a href="' + redirectUrl + '" class="fw-bold text-decoration-underline">click here to open Dashboard</a>.';
                                }
                            }, 400);

                        } else if (isInsideIframe && window.parent && window.parent !== window) {
                            console.log('[Activation Action]', {
                                action: 'iframe_redirect',
                                fallback_branch_executed: false,
                                redirectUrl: redirectUrl
                            });

                            try {
                                window.parent.postMessage(messagePayload, targetOrigin);
                                window.parent.postMessage(legacyPayload, targetOrigin);
                            } catch (e) {
                                try {
                                    window.parent.postMessage(messagePayload, '*');
                                    window.parent.postMessage(legacyPayload, '*');
                                } catch (err) {}
                            }
                            // Inside iframe: never call window.close(), redirect safely
                            window.location.href = redirectUrl;
                        } else {
                            // Standalone direct navigation
                            console.log('[Activation Action]', {
                                action: 'standalone_redirect',
                                fallback_branch_executed: false,
                                redirectUrl: redirectUrl
                            });
                            window.location.href = redirectUrl;
                        }
                    }
                } catch (err) {
                    console.warn('Polling check error:', err);
                }
            }

            // Poll every 750ms
            pollInterval = setInterval(checkActivationStatus, 750);
            // Also run one immediate poll
            checkActivationStatus();

        } catch (error) {
            console.error('Activation error:', error);
            stopPolling();
            errorMsg.textContent = error.message || 'An error occurred during activation.';
            errorAlert.style.display = 'block';
            button.disabled = false;
            button.innerText = 'Activate App';
            isSubmitting = false;
        }
    });
})();
</script>
@endpush