<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticating &mdash; {{ config('app.name', 'ZeoSync') }}</title>
    @php
        $apiKey = \App\Models\AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key'));
    @endphp
    <meta name="shopify-api-key" content="{{ $apiKey }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <style>
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
    </style>
</head>
<body>
    <div class="reauth-container">
        <div class="spinner"></div>
        <div class="reauth-title">Connecting to Shopify...</div>
        <div class="reauth-desc">Refreshing your session. Please wait a moment.</div>
    </div>

    <script>
        (function() {
            const rawTarget = @json($targetUrl ?? request()->fullUrl());
            const shopParam = @json($shop ?? request('shop') ?? '');

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

            async function obtainTokenAndRedirect() {
                try {
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
