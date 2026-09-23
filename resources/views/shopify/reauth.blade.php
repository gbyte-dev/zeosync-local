<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticating &mdash; {{ config('app.name', 'Zeosync') }}</title>
    @php
        $apiKey = \App\Models\AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key'));
    @endphp
    <meta name="shopify-api-key" content="{{ $apiKey }}">
    <script  nonce="{{ $cspNonce }}" src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <style nonce="{{ $cspNonce }}">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Segoe UI", Roboto, sans-serif;
            background: #F4F6F8;
            color: #202223;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }
        .reauth-container {
            text-align: center;
            padding: 32px 24px;
            max-width: 400px;
            width: 90%;
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
        }
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #E5E7EB;
            border-top: 3px solid #2563EB;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .reauth-title {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 6px;
        }
        .reauth-desc {
            font-size: 13px;
            color: #6B7280;
        }
        .reauth-btn {
            display: inline-block;
            margin-top: 14px;
            padding: 8px 16px;
            background-color: #2563EB;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .reauth-btn:hover {
            background-color: #1D4ED8;
        }
        .reauth-btn-secondary {
            background-color: #F3F4F6;
            color: #374151;
            margin-left: 8px;
        }
        .reauth-btn-secondary:hover {
            background-color: #E5E7EB;
        }
    </style>
</head>
<body>
    <div class="reauth-container">
        <div class="spinner" id="reauth-spinner"></div>
        <div class="reauth-title" id="reauth-title">Connecting to Shopify...</div>
        <div class="reauth-desc" id="reauth-desc">Refreshing your session. Please wait a moment.</div>
        <div id="reauth-actions" style="display: none; margin-top: 16px;">
            <button type="button" class="reauth-btn" id="reauth-retry-btn">Retry Connection</button>
            <a href="{{ route('crm.entry') }}" class="reauth-btn reauth-btn-secondary">Go Home</a>
        </div>
    </div>

    <script nonce="{{ $cspNonce }}">
        (function() {
            const rawTarget = @json($targetUrl ?? request()->fullUrl());
            const shopParam = @json($shop ?? request('shop') ?? '');
            const GUARD_KEY = 'zeosync_reauth_guard_' + (shopParam || 'global');
            const MAX_ATTEMPTS = 3;
            const WINDOW_MS = 15000;

            function sanitizeDestination(urlStr) {
                try {
                    const parsed = new URL(urlStr, window.location.origin);
                    if (parsed.origin !== window.location.origin) {
                        return window.location.origin + '/dashboard' + (shopParam ? '?shop=' + encodeURIComponent(shopParam) : '');
                    }
                    return parsed.pathname + parsed.search + parsed.hash;
                } catch (e) {
                    return '/dashboard' + (shopParam ? '?shop=' + encodeURIComponent(shopParam) : '');
                }
            }

            const targetPath = sanitizeDestination(rawTarget);

            function showFallbackUI() {
                const spinner = document.getElementById('reauth-spinner');
                const title = document.getElementById('reauth-title');
                const desc = document.getElementById('reauth-desc');
                const actions = document.getElementById('reauth-actions');

                if (spinner) spinner.style.display = 'none';
                if (title) title.textContent = 'Connection paused';
                if (desc) desc.textContent = 'Automatic authentication took longer than expected. Click below to reconnect.';
                if (actions) actions.style.display = 'block';

                const retryBtn = document.getElementById('reauth-retry-btn');
                if (retryBtn) {
                    retryBtn.onclick = function() {
                        sessionStorage.removeItem(GUARD_KEY);
                        if (spinner) spinner.style.display = 'block';
                        if (title) title.textContent = 'Connecting to Shopify...';
                        if (desc) desc.textContent = 'Refreshing your session. Please wait a moment.';
                        if (actions) actions.style.display = 'none';
                        obtainTokenAndRedirect();
                    };
                }
            }

            async function obtainTokenAndRedirect() {
                try {
                    // Check loop protection guard
                    let guardData = null;
                    try {
                        const raw = sessionStorage.getItem(GUARD_KEY);
                        if (raw) guardData = JSON.parse(raw);
                    } catch (e) {
                        guardData = null;
                    }

                    const now = Date.now();
                    if (guardData && (now - guardData.timestamp) < WINDOW_MS) {
                        if (guardData.attempts >= MAX_ATTEMPTS) {
                            showFallbackUI();
                            return;
                        }
                        sessionStorage.setItem(GUARD_KEY, JSON.stringify({
                            attempts: guardData.attempts + 1,
                            timestamp: now
                        }));
                    } else {
                        sessionStorage.setItem(GUARD_KEY, JSON.stringify({
                            attempts: 1,
                            timestamp: now
                        }));
                    }

                    if (typeof shopify === 'undefined' || !shopify.idToken) {
                        if (shopParam) {
                            window.location.href = "{{ route('shopify.install') }}?shop=" + encodeURIComponent(shopParam);
                        } else {
                            window.location.href = "{{ route('crm.entry') }}";
                        }
                        return;
                    }

                    const token = await shopify.idToken();
                    if (!token) {
                        throw new Error('Empty token received from Shopify App Bridge');
                    }

                    const separator = targetPath.includes('?') ? '&' : '?';
                    const authTarget = targetPath + separator + 'id_token=' + encodeURIComponent(token);

                    window.location.replace(authTarget);
                } catch (err) {
                    console.warn('Re-auth token retrieval error:', err);
                    if (shopParam) {
                        window.location.href = "{{ route('shopify.install') }}?shop=" + encodeURIComponent(shopParam);
                    } else {
                        window.location.href = "{{ route('crm.entry') }}";
                    }
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', obtainTokenAndRedirect);
            } else {
                obtainTokenAndRedirect();
            }
        })();
    </script>
</body>
</html>
