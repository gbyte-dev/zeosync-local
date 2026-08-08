<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopify Authentication</title>
    <style>
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

    <script>
        const shop = {!! json_encode($shop ?? null) !!};
        let setupCheckInterval;
        let hasRedirected = false;

        // Poll shop setup status
        function checkShopSetup() {
            if (!shop) return;

            fetch('/api/shop-status?shop=' + encodeURIComponent(shop), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.shop_name && data.email && !hasRedirected) {
                    hasRedirected = true;
                    const statusEl = document.getElementById('status');
                    if (statusEl) statusEl.textContent = 'Setup complete. Closing auth window...';

                    if (setupCheckInterval) clearInterval(setupCheckInterval);

                    const dashboardUrl = '{{ route("dashboard", ["shop" => "SHOP_PLACEHOLDER"]) }}'.replace('SHOP_PLACEHOLDER', shop);

                    if (window.opener && !window.opener.closed) {
                        try {
                            window.opener.location.href = dashboardUrl;
                            window.opener.focus();
                        } catch (e) {
                            console.warn('Unable to redirect opener:', e);
                            window.opener.postMessage({ type: 'shopify_installed', shop, redirect_url: dashboardUrl }, '*');
                        }
                        setTimeout(() => {
                            window.close();
                        }, 200);
                    } else {
                        window.location.href = dashboardUrl;
                    }
                }
            })
            .catch(err => console.log('Status check failed:', err));
        }

        (function() {
            const redirectUrl = {!! json_encode($redirectUrl) !!};
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

            if (popup) {
                popup.focus();
                statusEl.textContent = 'Shopify auth window opened. Complete the authorization there.';
            } else {
                statusEl.textContent = 'Popup blocked. Please allow popups and click the button below.';
            }

            openButton.addEventListener('click', function() {
                popup = window.open(redirectUrl, 'shopifyAuth', features);
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

            window.addEventListener('message', function(event) {
                const data = event.data || {};
                if (data.type === 'shopify_authenticated') {
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
