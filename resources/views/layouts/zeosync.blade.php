   @php
    $favicon = \App\Models\AdminSetting::where('option_key', 'app_favicon')->value('option_value');
    $fallback = asset('logo/favamzsync.png');
    $faviconUrl = $fallback;

    if ( !empty($favicon) &&  \Illuminate\Support\Facades\Storage::disk('public')->exists($favicon)
    ) {
        $faviconUrl = asset('storage/' . $favicon);
    }

     $faviconUrl = getFavicon();
     $shopifyclient_id = \App\Models\AdminSetting::get('SHOPIFY_API_KEY', config('services.shopify.api_key'));
    @endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Zeosync'))</title>
    <meta name="description" content="@yield('meta_description', 'Connect Amazon and Shopify with clearer product, inventory, order and returns workflows.')">
    <meta name="theme-color" content="#111c25">
    <meta name="shopify-api-key" content="{{ $shopifyclient_id }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconUrl }}">
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}?v={{ time() }}" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/guest.css') }}">
    @stack('styles')
    <script src="{{ asset('js/zeosync-site.js') }}" defer></script>
    @stack('head-scripts')
</head>
<body>

    <a class="skip" href="#main">Skip to content</a>

    @hasSection('preview-banner')
        <div class="preview">@yield('preview-banner', 'Website preview')</div>
    @endif

    @include('partials.header')

    <main id="main">
        @yield('content')
        <section class="closing wrap">
            <div>
                <p class="eyebrow">YOUR NEXT CHAPTER</p>
                <h2>More time selling.<br>Less time checking.</h2></div>
            <div>
                <p>Let’s see how Amazon and Shopify can work better together for your store.</p><a class="btn " href="{{ route('contact') }}">Find your fit<span aria-hidden="true">↗</span></a></div>
        </section>
    </main>

    @include('partials.footer')

    @stack('scripts')
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}" defer></script>

    <script>
        (function() {
            function isInIframe() {
                try {
                    return window.self !== window.top;
                } catch (e) {
                    return true;
                }
            }

            // 1. Normal standalone browser window: do nothing
            if (!isInIframe()) {
                return;
            }

            // 2. Loop prevention & explicit logout checks
            const REAUTH_GUARD_KEY = 'zeosync_iframe_reauth_ts';
            const REAUTH_COOLDOWN_MS = 60000;
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.get('logged_out') === '1' || sessionStorage.getItem('zeosync_explicit_logout') === '1') {
                return;
            }

            const lastAttempt = sessionStorage.getItem(REAUTH_GUARD_KEY);
            const now = Date.now();
            if (lastAttempt && (now - parseInt(lastAttempt, 10)) < REAUTH_COOLDOWN_MS) {
                return;
            }

            // 3. Embedded Shopify Recovery via App Bridge
            async function recoverEmbeddedShopifySession() {
                if (typeof shopify === 'undefined' || typeof shopify.idToken !== 'function') {
                    // Not in Shopify App Bridge environment (e.g. generic iframe)
                    return;
                }

                try {
                    sessionStorage.setItem(REAUTH_GUARD_KEY, String(Date.now()));
                    const token = await shopify.idToken();
                    if (!token) {
                        return;
                    }

                    let targetPath = window.location.pathname;
                    if (targetPath === '' || targetPath === '/') {
                        targetPath = '/dashboard';
                    }

                    if (targetPath.startsWith('http://') || targetPath.startsWith('https://')) {
                        try {
                            const parsed = new URL(targetPath, window.location.origin);
                            if (parsed.origin !== window.location.origin) {
                                targetPath = '/dashboard';
                            } else {
                                targetPath = parsed.pathname;
                            }
                        } catch (e) {
                            targetPath = '/dashboard';
                        }
                    }

                    const shop = urlParams.get('shop');
                    const host = urlParams.get('host');

                    const outParams = new URLSearchParams();
                    if (shop) outParams.set('shop', shop);
                    if (host) outParams.set('host', host);
                    outParams.set('embedded', '1');
                    outParams.set('id_token', token);

                    sessionStorage.removeItem(REAUTH_GUARD_KEY);
                    window.location.replace(targetPath + '?' + outParams.toString());
                } catch (err) {
                    console.warn('Shopify iframe re-auth skipped or unavailable:', err);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', recoverEmbeddedShopifySession);
            } else {
                recoverEmbeddedShopifySession();
            }
        })();
    </script>
</body>
</html>
