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
        html, body {
            min-height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
            background-color: #F4F6F8;
            /* Shopify admin background */
            font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #202223;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
        }

        body.sidebar-open {
            overflow: hidden;
            /* Prevent body scroll when mobile menu is open */
        }

        .app-layout {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
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
                height: 70px;
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

        #sidebar .nav-link:hover { background: #f8fafc; }
        #sidebar .nav-link.active { background: #eef2ff; font-weight: 600; }
        #sidebar .nav-icon { width: 28px; display: inline-block; text-align: center }

        #siteFooter {
            background: linear-gradient(180deg, #07141f 0%, #0f172a 100%);
            color: #cbd5e1;
            padding: 42px 18px 32px;
        }

        #siteFooter a {
            color: #dbe4ff;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        #siteFooter a:hover {
            color: #ffffff;
        }

        #siteFooter .footer-brand {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        #siteFooter .footer-title {
            color: #f8fafc;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.75rem;
        }

        #siteFooter .footer-note {
            color: #94a3b8;
            line-height: 1.8;
        }

        #siteFooter .footer-section {
            margin-bottom: 1.5rem;
        }

        @media (min-width: 768px) {
            #siteFooter .footer-section {
                margin-bottom: 0;
            }
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
                <div class="mb-2">
                    <a class="nav-link d-flex align-items-center justify-content-start mb-1 {{ request()->is('/') ? 'active' : '' }}" href="{{ route('crm.entry') }}">
                        <span class="nav-icon me-2">🏠</span>
                        <span>Home</span>
                    </a>

                    <a class="nav-link d-flex align-items-center justify-content-start mb-1 {{ request()->is('about') ? 'active' : '' }}" href="{{ route('about') }}">
                        <span class="nav-icon me-2">ℹ️</span>
                        <span>About</span>
                    </a>

                    <a class="nav-link d-flex align-items-center justify-content-start mb-1 {{ request()->is('pricing') ? 'active' : '' }}" href="{{ route('pricing') }}">
                        <span class="nav-icon me-2">💳</span>
                        <span>Pricing</span>
                    </a>

                    <a class="nav-link d-flex align-items-center justify-content-start mb-1 {{ request()->is('contact') ? 'active' : '' }}" href="{{ route('contact') }}">
                        <span class="nav-icon me-2">✉️</span>
                        <span>Contact</span>
                    </a>
                </div>

                <div class="mt-3">
                    <a class="nav-link d-flex align-items-center justify-content-start mb-1 {{ request()->is('terms') ? 'active' : '' }}" href="{{ route('terms') }}">
                        <span class="nav-icon me-2">📄</span>
                        <span>Terms</span>
                    </a>

                    <a class="nav-link d-flex align-items-center justify-content-start mb-1 {{ request()->is('privacy') ? 'active' : '' }}" href="{{ route('privacy') }}">
                        <span class="nav-icon me-2">🔒</span>
                        <span>Privacy</span>
                    </a>
                </div>

                <hr>
            </nav>
        </aside>

        <!-- Content -->
        <div class="content">
            @yield('content')
        </div>

        <!-- Footer -->
        <footer id="siteFooter">
            <div class="container">
                <div class="row gy-4">
                    <div class="col-md-5 footer-section">
                        <h5 class="footer-brand">{{ getAppName() }}</h5>
                        <p class="small footer-note mt-2">
                            Sync Amazon and Shopify effortlessly with a fast, reliable integration designed for modern merchants.
                        </p>
                    </div>

                    <div class="col-6 col-md-2 footer-section">
                        <div class="footer-title">Company</div>
                        <div><a href="/about">About</a></div>
                        <div><a href="/pricing">Pricing</a></div>
                        <div><a href="/contact">Contact</a></div>
                    </div>

                    <div class="col-6 col-md-2 footer-section">
                        <div class="footer-title">Legal</div>
                        <div><a href="/terms">Terms</a></div>
                        <div><a href="/privacy">Privacy</a></div>
                    </div>

                    <div class="col-md-3 footer-section">
                        <div class="footer-title">Need help?</div>
                        <p class="small footer-note mb-3">
                            Reach out anytime and our team will help you get the most from your store sync.
                        </p>
                        <a href="/contact" class="btn btn-sm btn-outline-light rounded-pill">Contact support</a>
                    </div>
                </div>

                <div class="row mt-4 pt-3 border-top border-white-10">
                    <div class="col-md-6 small footer-note">
                        &copy; {{ date('Y') }} {{ getAppName() }}. All rights reserved.
                    </div>
                    <div class="col-md-6 text-md-end small footer-note">
                        Built for seamless Amazon-Shopify synchronization.
                    </div>
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