<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amazon Sync</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/sidebar.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}?v={{ time() }}" rel="stylesheet">
    @php
    $favicon = \App\Models\AdminSetting::where('option_key', 'app_favicon')->value('option_value');

    $fallback = asset('logo/favamzsync.png');

    $faviconUrl = $fallback;

    if (
    !empty($favicon) &&
    \Illuminate\Support\Facades\Storage::disk('public')->exists($favicon)
    ) {
    $faviconUrl = asset('storage/' . $favicon);
    }
    @endphp

    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconUrl }}">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    @stack('css')

    <style>
        /* 
     * 1. Design Tokens (Shopify/Apple HIG Inspired)
     */
        :root {
            /* Colors */
            --sp-bg: #F4F6F8;
            --sp-card: #FFFFFF;
            --sp-border: #E5E7EB;
            --sp-primary: #2563EB;
            --sp-text: #202223;
            --sp-text-muted: #6B7280;

            /* Sidebar Specific Tokens */
            --sidebar-bg: #111827;
            --sidebar-text: #9CA3AF;
            --sidebar-text-hover: #F3F4F6;
            --sidebar-text-active: #FFFFFF;
            --sidebar-hover-bg: rgba(255, 255, 255, 0.06);
            --sidebar-active-bg: rgba(255, 255, 255, 0.12);
            --sidebar-border: rgba(255, 255, 255, 0.08);

            /* Layout Metrics */
            --topbar-height: 48px;
            --sidebar-width: 240px;
        }

        /* Desktop pe Topbar nahi hota, isliye height 0px karein */
        @media (min-width: 768px) {
            :root {
                --topbar-height: 0px !important;
            }
        }

        /* 
     * 2. Global Reset & Typography
     */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
            background-color: var(--sp-bg);
            color: var(--sp-text);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body.sidebar-open {
            overflow: hidden;
            /* Lock scroll on mobile */
        }

        /* 
     * 3. Macro Layout Architecture 
     */
        .app-layout {
            display: flex;
            height: calc(100vh - var(--topbar-height));
            width: 100%;
            overflow: hidden;
        }

        .content {
            flex: 1;
            min-width: 0 !important;
            /* Critical: Stops wide tables from breaking horizontal flex layout */
            min-height: 0 !important;
            /* Critical: Stops tall tables from squishing vertical flex height */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px;
            background-color: var(--sp-bg);
            position: relative;
        }

        .in-iframe .content {
            padding: 1px;
        }

        /* Mobile View */
        @media (max-width: 767.98px) {
            .content {
                padding: 0px;
            }
        }

        /* Overlay for Mobile */
        .overlay {
            position: fixed;
            inset: var(--topbar-height) 0 0 0;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(2px);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 1035;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>

<body>

    {{-- Toast Container --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999; margin-top: 56px;">

        {{-- Success --}}
        @if(session('success'))
        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    {{ session('success') }}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        @endif

        {{-- Error --}}
        @if(session('error'))
        <div class="toast align-items-center text-bg-danger border-0 show mt-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    {{ session('error') }}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        @endif

        @if ($errors->any())
        <div class="toast align-items-center text-bg-danger border-0 show mt-2">
            <div class="d-flex">
                <div class="toast-body">
                    {{ $errors->first() }}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        @endif

    </div>

    <!-- Topbar -->
    <div class="topbar justify-content-between align-items-center">

        <div class="d-flex align-items-center gap-3">
            <button id="menuToggle" class="sp-icon-btn d-md-none">
                <i class="bi bi-list fs-5"></i>
            </button>

            <span class="sp-topbar-brand">AmazonSync</span>
        </div>

        <div class="dropdown">
            <button class="sp-icon-btn position-relative"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">

                <i class="bi bi-bell"></i>

                @if($userUnreadCount > 0)
                <span id="userUnreadBadge" style="font-size: 12px;"
                    class="position-absolute top-0 start-100 translate-middle sp-badge-count">
                    {{ $userUnreadCount > 9 ? '9+' : $userUnreadCount }}
                </span>
                @endif
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow" style="width: 320px;">

                <li class="border-bottom">
                    <strong>Notifications</strong>
                    <div class="small text-muted mt-1">
                        {{ $userUnreadCount }} unread notifications
                    </div>
                </li>

                @forelse($userNotifications as $notification)
                <li>
                    <a class="dropdown-item mark-user-read {{ $notification->is_read ? 'bg-light' : 'fw-bold' }}"
                        href="javascript:void(0)"
                        data-id="{{ $notification->id }}">
                        <div class="fw-semibold">
                            {{ $notification->title }}
                        </div>

                        <small class="text-muted d-block">
                            {{ $notification->message }}
                        </small>

                        <small class="text-secondary">
                            {{ $notification->created_at->diffForHumans() }}
                        </small>
                    </a>
                </li>
                @empty
                <li class="px-3 py-4 text-center text-muted" style="font-size: 12px;">
                    No recent notifications
                </li>
                @endforelse

                <li class="border-top text-center">
                    <a href="{{ route('user.notification')}}"
                        class="btn btn-light w-100 py-1 fw-medium">
                        View All
                    </a>
                </li>

            </ul>
        </div>

    </div>

    <div class="app-layout">

        <!-- Sidebar -->
        @include('partials.sidebar')

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

        document.addEventListener("DOMContentLoaded", function() {

            const menuBtn = document.getElementById("menuToggle");
            const sidebar = document.getElementById("sidebar");
            const overlay = document.getElementById("overlay");

            if (!menuBtn || !sidebar) return;

            function openSidebar() {
                sidebar.classList.add("active");
                overlay?.classList.add("active");
                document.body.classList.add("sidebar-open");
            }

            function closeSidebar() {
                sidebar.classList.remove("active");
                overlay?.classList.remove("active");
                document.body.classList.remove("sidebar-open");
            }

            // Burger Toggle
            menuBtn.addEventListener("click", function(e) {
                e.stopPropagation();

                if (sidebar.classList.contains("active")) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            // Overlay Click
            overlay?.addEventListener("click", function() {
                closeSidebar();
            });

            // Click Outside Sidebar
            document.addEventListener("click", function(e) {

                if (
                    window.innerWidth < 768 &&
                    sidebar.classList.contains("active") &&
                    !sidebar.contains(e.target) &&
                    !menuBtn.contains(e.target)
                ) {
                    closeSidebar();
                }

            });

            // ESC Key
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") {
                    closeSidebar();
                }
            });

            // Close after clicking sidebar menu (Mobile)
            sidebar.querySelectorAll("a").forEach(link => {
                link.addEventListener("click", function() {
                    if (window.innerWidth < 768) {
                        closeSidebar();
                    }
                });
            });

        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let toasts = document.querySelectorAll('.toast');
            toasts.forEach(function(toastEl) {
                let toast = new bootstrap.Toast(toastEl, {
                    delay: 20000 // 20 seconds
                });
                toast.show();
            });
        });
    </script>

    <!-- GLOBAL LOADER -->
    <div id="globalLoaderOverlay" class="global-loader-overlay" style="display:none;">

        <div class="loader">
            <div class="loader-square"></div>
            <div class="loader-square"></div>
            <div class="loader-square"></div>
            <div class="loader-square"></div>
            <div class="loader-square"></div>
            <div class="loader-square"></div>
            <div class="loader-square"></div>
        </div>

        <p id="loaderText">Processing...</p>

    </div>

    <style>
        .global-loader-overlay {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            bottom: 0;
            width: auto;
            height: auto;
            background: rgba(255, 255, 255, 0.85);
            /* Shopify light blur overlay */
            backdrop-filter: blur(4px);
            z-index: 99999999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }

        #loaderText {
            color: var(--sp-sidebar);
            font-size: 14px;
            font-weight: 500;
            margin-top: 16px;
        }

        /* LOADER */
        @keyframes square-animation {
            0% {
                left: 0;
                top: 0;
            }

            10.5% {
                left: 0;
                top: 0;
            }

            12.5% {
                left: 32px;
                top: 0;
            }

            23% {
                left: 32px;
                top: 0;
            }

            25% {
                left: 64px;
                top: 0;
            }

            35.5% {
                left: 64px;
                top: 0;
            }

            37.5% {
                left: 64px;
                top: 32px;
            }

            48% {
                left: 64px;
                top: 32px;
            }

            50% {
                left: 32px;
                top: 32px;
            }

            60.5% {
                left: 32px;
                top: 32px;
            }

            62.5% {
                left: 32px;
                top: 64px;
            }

            73% {
                left: 32px;
                top: 64px;
            }

            75% {
                left: 0;
                top: 64px;
            }

            85.5% {
                left: 0;
                top: 64px;
            }

            87.5% {
                left: 0;
                top: 32px;
            }

            98% {
                left: 0;
                top: 32px;
            }

            100% {
                left: 0;
                top: 0;
            }
        }

        .loader {
            position: relative;
            width: 96px;
            height: 96px;
            transform: rotate(45deg);
        }

        .loader-square {
            position: absolute;
            top: 0;
            left: 0;
            width: 28px;
            height: 28px;
            margin: 2px;
            background: var(--sp-primary);
            /* Use primary color instead of white */
            border-radius: 4px;
            animation: square-animation 10s ease-in-out infinite both;
        }

        .loader-square:nth-of-type(1) {
            animation-delay: -1.4285714286s;
        }

        .loader-square:nth-of-type(2) {
            animation-delay: -2.8571428571s;
        }

        .loader-square:nth-of-type(3) {
            animation-delay: -4.2857142857s;
        }

        .loader-square:nth-of-type(4) {
            animation-delay: -5.7142857143s;
        }

        .loader-square:nth-of-type(5) {
            animation-delay: -7.1428571429s;
        }

        .loader-square:nth-of-type(6) {
            animation-delay: -8.5714285714s;
        }

        .loader-square:nth-of-type(7) {
            animation-delay: -10s;
        }

        @media (max-width: 767.98px) {
            .global-loader-overlay {
                left: 0;
            }
        }
    </style>

    <script>
        function showLoader(text = 'Processing...') {
            const loader = document.getElementById('globalLoaderOverlay');
            if (!loader) return;
            loader.style.display = 'flex';
            document.getElementById('loaderText').innerText = text;
        }

        function hideLoader() {
            const loader = document.getElementById('globalLoaderOverlay');
            if (!loader) return;
            loader.style.display = 'none';
        }
    </script>

    <script>
        document.addEventListener('click', function(e) {

            const link = e.target.closest('a');

            if (!link) {
                return;
            }

            // Ignore Bootstrap collapse/dropdown links
            const href = link.getAttribute('href');

            if (!href || href.startsWith('#')) {
                return;
            }

            const url = new URL(link.href, window.location.origin);

            if (url.pathname !== '/products') {
                return;
            }

            showLoader('Loading Shopify products...');
        });
    </script>

    <div id="dynamicToastContainer"
        class="position-fixed top-0 end-0 p-3"
        style="z-index:999999; margin-top: 56px;">
    </div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('dynamicToastContainer');
            if (!container) return;

            const toastId = 'toast_' + Date.now();
            const html = `
        <div id="${toastId}"
             class="toast align-items-center text-bg-${type} border-0 mb-2"
             role="alert">

            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button"
                        class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast">
                </button>
            </div>
        </div>
    `;

            container.insertAdjacentHTML('beforeend', html);
            const toastEl = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastEl, {
                delay: 4000
            });

            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => {
                toastEl.remove();
            });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.mark-user-read').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    let id = this.dataset.id;
                    let element = this;

                    fetch("{{ url('/user/notification') }}/" + id + "/read", {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                                "Accept": "application/json"
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {

                                element.classList.remove('fw-bold');
                                element.classList.add('bg-light');

                                let badge = document.getElementById('userUnreadBadge');

                                if (badge) {
                                    let count = badge.innerText.trim();

                                    if (count === '9+') {
                                        location.reload();
                                        return;
                                    }

                                    count = parseInt(count);

                                    if (count > 1) {
                                        badge.innerText = count - 1;
                                    } else {
                                        badge.remove();
                                    }
                                }
                            }
                        });
                });
            });
        });
    </script>
    <script>
        if (window.self !== window.top) {
            document.documentElement.classList.add('in-iframe');
        } else {
            document.documentElement.classList.add('normal-page');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>

</html>