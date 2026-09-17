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
    let hasCompletedSuccessFlow = false;

    const form = document.getElementById('activateForm');
    const button = document.getElementById('submitBtn');
    const successAlert = document.getElementById('activationSuccessAlert');
    const successMsg = document.getElementById('activationSuccessMessage');
    const successSubtext = document.getElementById('activationSuccessSubtext');
    const errorAlert = document.getElementById('activationErrorAlert');
    const errorMsg = document.getElementById('activationErrorMessage');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (isSubmitting || hasCompletedSuccessFlow) {
            return;
        }

        isSubmitting = true;
        button.disabled = true;
        button.innerText = 'Activating...';
        errorAlert.style.display = 'none';

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

            // SUCCESS FLOW
            hasCompletedSuccessFlow = true;

            // 1. Show the user a clear success message: "Store activated successfully."
            successMsg.textContent = 'Store activated successfully.';
            successAlert.style.display = 'block';

            // Disable form inputs
            Array.from(form.elements).forEach(function(el) { el.disabled = true; });
            button.innerText = 'Activated ✓';
            button.classList.remove('btn-primary');
            button.classList.add('btn-success');

            if (typeof showToast === 'function') {
                showToast('Store activated successfully.', 'success');
            }

            const redirectUrl = data.redirect_url || '{{ route("dashboard", ["shop" => $shopModel?->shop]) }}';
            const shopDomain = '{{ $shopModel?->shop }}';

            // Detect if opened as popup or inside iframe
            const isInsideIframe = (window.self !== window.top);
            const isPopup = Boolean(window.opener && !window.opener.closed && !isInsideIframe);

            // Notify opener/parent via postMessage
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

            if (isPopup) {
                try {
                    window.opener.postMessage(messagePayload, '*');
                    window.opener.postMessage(legacyPayload, '*');
                } catch (e) {
                    console.warn('Could not postMessage to opener:', e);
                }

                // Immediately close the popup window after displaying success message
                try {
                    window.close();
                } catch (e) {
                    console.warn('window.close() threw error:', e);
                }

                // If browser blocked window.close() or window remains open:
                setTimeout(function () {
                    if (!window.closed) {
                        successSubtext.style.display = 'block';
                        successSubtext.innerHTML = 'Activation complete. You can close this window or <a href="' + redirectUrl + '" class="fw-bold text-decoration-underline">click here to open Dashboard</a>.';

                        // Safe fallback navigation if user is still on page
                        setTimeout(function() {
                            if (!window.closed) {
                                window.location.href = redirectUrl;
                            }
                        }, 1200);
                    }
                }, 300);
            } else if (isInsideIframe && window.parent && window.parent !== window) {
                try {
                    window.parent.postMessage(messagePayload, '*');
                    window.parent.postMessage(legacyPayload, '*');
                } catch (e) {
                    console.warn('Could not postMessage to parent:', e);
                }
                // Inside iframe or direct navigation - never call window.close(), navigate safely
                window.location.href = redirectUrl;
            } else {
                // Standalone direct navigation
                window.location.href = redirectUrl;
            }

        } catch (error) {
            console.error('Activation error:', error);
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