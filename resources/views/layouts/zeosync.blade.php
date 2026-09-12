   @php
    $favicon = \App\Models\AdminSetting::where('option_key', 'app_favicon')->value('option_value');
    $fallback = asset('logo/favamzsync.png');
    $faviconUrl = $fallback;

    if ( !empty($favicon) &&  \Illuminate\Support\Facades\Storage::disk('public')->exists($favicon)
    ) {
        $faviconUrl = asset('storage/' . $favicon);
    }

     $faviconUrl = getFavicon();
    @endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Zeosync'))</title>
    <meta name="description" content="@yield('meta_description', 'Connect Amazon and Shopify with clearer product, inventory, order and returns workflows.')">
    <meta name="theme-color" content="#111c25">
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

            function isInIframe() {
                try {
                    return window.self !== window.top;
                } catch (e) {
                    // If accessing window.top throws, you're definitely cross-origin framed
                    return true;
                }
            }

            function getIframeSrc(iframeElement) {
                return iframeElement.getAttribute('src'); // the src you set
            }

            // or, if you need the *actual* URL the iframe navigated to (same-origin only)
            function getIframeCurrentUrl(iframeElement) {
                try {
                    return iframeElement.contentWindow.location.href;
                } catch (e) {
                    // Cross-origin — browser blocks this, only the src attribute is visible
                    return iframeElement.getAttribute('src');
                }
            }

            function getTopPageUrl() {
                try {
                    // Works only if the parent page is same-origin
                    return window.top.location.href;
                } catch (e) {
                    // Cross-origin iframe: can't read parent's URL directly.
                    // Fall back to document.referrer (often set to the parent page URL)
                    return document.referrer || null;
                }
            }

        document.addEventListener('DOMContentLoaded', function() {
            if (isInIframe()) {
            console.log("Running inside an iframe");
            console.log("Top page URL:", getTopPageUrl());
            console.log("Iframe current URL:", getIframeCurrentUrl());
            console.log("Iframe src URL:", getIframeSrc());

            } else {
            console.log("Not in an iframe");
            console.log("Current page URL:", window.location.href);
            }
});
    </script>
</body>
</html>
