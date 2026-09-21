<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopify Authentication</title>
    <style nonce="{{ $cspNonce }}">
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f7f8fb; color: #202124; margin: 0; }
        .page { max-width: 640px; margin: 0 auto; padding: 48px 24px; text-align: center; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 16px 40px rgba(16,24,40,.08); padding: 32px; }
        .button { appearance: none; border: none; background: #1d72f3; color: #fff; padding: 12px 24px; border-radius: 10px; font-size: 1rem; cursor: pointer; margin-top: 20px; }
        .button:hover { background: #1558d0; }
        .muted { color: #556074; }
        .status { margin-top: 16px; font-size: 0.95rem; }
        a { color: #1d72f3; text-decoration: none; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>Shopify authorization</h1>
            <p class="muted">A new window is being opened for Shopify authentication. If the popup is blocked, allow popups for this site and click the button below.</p>
            <button id="openPopup" class="button">Open Shopify authorization</button>
            <p class="status" id="status">Opening Shopify auth window...</p>
            <p class="muted">When authorization completes, this page will redirect automatically.</p>
        </div>
    </div>

    <script nonce="{{ $cspNonce }}">
        const shop = @json($shop ?? null);
        let setupCheckInterval;
        let hasRedirected = false;

        // Poll shop setup status
        function checkShopSetup() {
            if (!shop) return;

            fetch('/api/shop-status?shop=' + encodeURIComponent(shop), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Non-JSON response received');
                }
                return response.json();
            })
            .then(data => {
                if (data.is_active && data.shop_name && data.email && !hasRedirected) {
                    hasRedirected = true;
                    const statusEl = document.getElementById('status');
                    if (statusEl) statusEl.textContent = 'Setup complete. Redirecting to dashboard...';
                    setTimeout(function() {
                        window.location.href = '{{ route("dashboard", ["shop" => "SHOP_PLACEHOLDER"]) }}'.replace('SHOP_PLACEHOLDER', shop);
                    }, 500);
                    if (setupCheckInterval) clearInterval(setupCheckInterval);
                }
            })
            .catch(err => console.error('Status check failed:', err));
        }

        (function() {
            const redirectUrl = @json($redirectUrl);
            const statusEl = document.getElementById('status');
            const openButton = document.getElementById('openPopup');
            const features = [
                'toolbar=no',
                'location=no',
                'status=no',
                'menubar=no',
                'scrollbars=yes',
                'resizable=yes',
                'width=1000',
                'height=700',
                'top=' + Math.round((screen.height - 700) / 2),
                'left=' + Math.round((screen.width - 1000) / 2)
            ].join(',');

            let popup = window.open(redirectUrl, 'shopifyAuth', features);
            window.activeActivationPopup = popup;

            if (popup) {
                popup.focus();
                statusEl.textContent = 'Shopify auth window opened. Complete the authorization there.';
            } else {
                statusEl.textContent = 'Popup blocked. Please allow popups and click the button below.';
            }

            openButton.addEventListener('click', function() {
                popup = window.open(redirectUrl, 'shopifyAuth', features);
                window.activeActivationPopup = popup;
                if (popup) {
                    popup.focus();
                    statusEl.textContent = 'Shopify auth window opened. Complete the authorization there.';
                } else {
                    statusEl.textContent = 'Popup blocked again. Please allow popups for this site.';
                }
            });

            const popupChecker = setInterval(function() {
                if (popup && popup.closed) {
                    clearInterval(popupChecker);
                    statusEl.textContent = 'Authorization window closed. Waiting for setup completion...';
                    // Start polling for shop setup when popup closes
                    if (!setupCheckInterval) {
                        setupCheckInterval = setInterval(checkShopSetup, 1000);
                    }
                }
            }, 500);

            function isSafeRedirectUrl(url) {
                if (typeof url !== 'string' || !url.trim()) return false;
                const trimmed = url.trim();
                if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
                    return true;
                }
                try {
                    const parsed = new URL(trimmed, window.location.origin);
                    return parsed.origin === window.location.origin && (parsed.protocol === 'http:' || parsed.protocol === 'https:');
                } catch (e) {
                    return false;
                }
            }

            window.addEventListener('message', function(event) {
                if (event.origin !== window.location.origin) {
                    return;
                }

                if (popup && event.source !== popup) {
                    return;
                }

                const data = event.data || {};
                if (data.type === 'shopify_authenticated' || data.type === 'shopify_activated') {
                    if (data.redirect_url && isSafeRedirectUrl(data.redirect_url) && (data.redirect_url.includes('/dashboard') || data.type === 'shopify_activated') && !hasRedirected) {
                        hasRedirected = true;
                        if (setupCheckInterval) clearInterval(setupCheckInterval);
                        statusEl.textContent = 'Store activated successfully. Redirecting...';
                        if (window.activeActivationPopup && !window.activeActivationPopup.closed) {
                            try { window.activeActivationPopup.close(); } catch(e) {}
                        }
                        if (popup && !popup.closed) {
                            try { popup.close(); } catch(e) {}
                        }
                        window.location.href = data.redirect_url;
                        return;
                    }
                    statusEl.textContent = 'Authorization successful. Waiting for setup completion...';
                    // Start polling for shop setup
                    if (!setupCheckInterval) {
                        setupCheckInterval = setInterval(checkShopSetup, 1000);
                    }
                }
            }, false);
        })();
    </script>
</body>
</html>
