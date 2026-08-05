<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeosync — Amazon & Shopify Sync</title>
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

        .topbar__logo{
                height: 56px;
                width: auto;
                max-width: 220px;
            }

        #menuToggle {
            background: transparent;
            border: none;
            color: #111827;
            font-size: 26px;
            cursor: pointer;
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.12s ease, color 0.12s ease, transform 0.12s ease;
            padding: 0;
        }

        #menuToggle:hover,
        #menuToggle:focus {
            background-color: #F4F6F8;
            color: #202223;
            outline: none;
        }

        .topbar span {
            display: flex;
            align-items: center;
            font-weight: 650;
            font-size: 14px;
            letter-spacing: -0.2px;
            color: #1A1A1A;
        }

        /* Sidebar (mobile) */
        #sidebar {
            position: fixed;
            right: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            max-width: 90vw;
            background: #fff;
            box-shadow: -8px 0 24px rgba(16,24,40,0.08);
            transform: translateX(110%);
            transition: transform 0.18s ease;
            z-index: 50;
            padding: 20px;
            overflow-y: auto;
        }

        #sidebar.active {
            transform: translateX(0%);
        }

        #sidebar .nav-link {
            display: block;
            padding: 10px 8px;
            color: #111827;
            text-decoration: none;
            border-radius: 6px;
        }

        #siteFooter {
            background: #0f172a;
            color: #94a3b8;
            padding: 28px 18px;
        }

        #siteFooter a { color: #cbd5e1; text-decoration: none }

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
    <!-- Topbar -->
    <div class="topbar container-fluid">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center">
                <a href="/" class="d-inline-block me-3"><img src="{{ getLogo() }}" alt="Zeosync" class="topbar__logo"></a>
            </div>

            <div class="d-flex align-items-center">
                <button id="menuToggle" aria-label="Open menu">☰</button>
            </div>
        </div>
    </div>

    <div class="app-layout">

        <!-- Overlay -->
        <div class="overlay" id="overlay"></div>

        <!-- Sidebar / Mobile Menu -->
        <aside id="sidebar" aria-hidden="true">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Menu</strong>
                <button id="closeSidebar" aria-label="Close menu">✕</button>
            </div>

            <nav>
                <a class="nav-link" href="/">Home</a>
                <a class="nav-link" href="/about">About</a>
                <a class="nav-link" href="/pricing">Pricing</a>
                <a class="nav-link" href="/contact">Contact Us</a>
                <a class="nav-link" href="/terms">Terms</a>
                <a class="nav-link" href="/privacy">Privacy Policy</a>
            </nav>
        </aside>

        <!-- Content -->
        <div class="content">
            @yield('content')
        </div>

        <!-- Footer -->
        <footer id="siteFooter">
            <div class="container d-md-flex justify-content-between">
                <div class="mb-3 mb-md-0">
                    <h5 class="text-white">Zeosync</h5>
                    <div class="small">Sync Amazon & Shopify effortlessly</div>
                </div>

                <div class="d-flex gap-4 small">
                    <div>
                        <div class="fw-bold text-white">Company</div>
                        <div><a href="/about">About</a></div>
                        <div><a href="/pricing">Pricing</a></div>
                        <div><a href="/contact">Contact Us</a></div>
                    </div>

                    <div>
                        <div class="fw-bold text-white">Legal</div>
                        <div><a href="/terms">Terms</a></div>
                        <div><a href="/privacy">Privacy</a></div>
                    </div>
                </div>

                <div class="text-md-end small text-muted">
                    &copy; {{ date('Y') }} Zeosync. All rights reserved.
                </div>
            </div>
        </footer>

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
                    sidebar.setAttribute('aria-hidden', 'false');
                    if (overlay) overlay.classList.add('active');
                    document.body.classList.add('sidebar-open');
                    // move focus into sidebar
                    const firstLink = sidebar.querySelector('.nav-link');
                    if(firstLink) firstLink.focus();
                });
            }

            if (overlay) {
                overlay.addEventListener('click', () => {
                    if (sidebar) {
                        sidebar.classList.remove('active');
                        sidebar.setAttribute('aria-hidden', 'true');
                    }
                    overlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                });
            }

            const closeSidebarBtn = document.getElementById('closeSidebar');
            if (closeSidebarBtn && sidebar) {
                closeSidebarBtn.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    sidebar.setAttribute('aria-hidden', 'true');
                    if (overlay) overlay.classList.remove('active');
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