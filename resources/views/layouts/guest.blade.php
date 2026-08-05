<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amazon Sync</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}?v={{ time() }}" rel="stylesheet">

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

    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconUrl }}">

    <style>
        /* Enterprise SaaS App Layout - Tight Density */
        body {
            background-color: #F4F6F8;
            /* Shopify admin background */
            font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #202223;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body.sidebar-open {
            overflow: hidden;
            /* Prevent body scroll when mobile menu is open */
        }

        .app-layout {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Minimal SaaS Topbar */
        .topbar {
            background-color: #FFFFFF;
            height: 48px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 30;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        #menuToggle {
            background: transparent;
            border: none;
            color: #5C5F62;
            font-size: 16px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease, color 0.15s ease;
            padding: 0;
        }

        #menuToggle:hover,
        #menuToggle:focus {
            background-color: #F4F6F8;
            color: #202223;
            outline: none;
        }

        .topbar span {
            font-weight: 650;
            font-size: 14px;
            letter-spacing: -0.2px;
            color: #1A1A1A;
        }

        /* Apple-inspired Blur Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(32, 34, 35, 0.4);
            /* Soft dark overlay */
            backdrop-filter: blur(2px);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Main Content Area */
        .content {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
    </style>
</head>

<body>
    <!-- Topbar (mobile) -->
    <div class="topbar">
        <span><img src="{{ getLogo() }}" alt="Logo" class="topbar__logo">amazonSync</span>
         <button id="menuToggle" class="float-right">☰</button>
    </div>

    <div class="app-layout">

        <!-- Overlay -->
        <div class="overlay" id="overlay"></div>

        <!-- Content -->
        <div class="content">
            @yield('content')
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')

    <script>
        // Set up CSRF token for all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Global sidebar toggle for mobile
        document.addEventListener("DOMContentLoaded", function() {
            const menuBtn = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            if (menuBtn && sidebar) {
                menuBtn.addEventListener('click', () => {
                    sidebar.classList.add('active');
                    if (overlay) overlay.classList.add('active');
                    document.body.classList.add('sidebar-open');
                });
            }

            if (overlay) {
                overlay.addEventListener('click', () => {
                    if (sidebar) sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                });
            }

            // Close sidebar when clicking ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    </script>

</body>

</html>