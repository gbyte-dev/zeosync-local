<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin Dashboard') | Amazon Sync</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        :root {
            --admin-bg: #f4f7fb;
            --admin-surface: #ffffff;
            --admin-border: #e2e8f0;
            --admin-text: #0f172a;
            --admin-muted: #64748b;
            --admin-primary: #2563eb;
        }

        body {
            background: var(--admin-bg);
            color: var(--admin-text);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }

        .admin-layout {
            min-height: 100vh;
            background: var(--admin-bg);
        }

        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
            position: sticky;
            top: 0;
            box-shadow: 12px 0 28px rgba(15, 23, 42, 0.12);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: 0.2s ease;
            margin-bottom: 4px;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(96, 165, 250, 0.15);
            color: #fff;
        }

        .top-navbar {
            height: 76px;
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--admin-border);
            padding: 0 26px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .page-title {
            font-size: clamp(1.1rem, 1.2rem + 0.35vw, 1.8rem);
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .notification-btn {
            width: 42px;
            height: 42px;
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            background: #f8fafc;
            color: var(--admin-text);
        }

        .notification-menu {
            width: 340px;
            border-radius: 18px;
            overflow: hidden;
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            color: var(--admin-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .content-area {
            padding: 22px;
        }

        .admin-page-shell {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            border: 1px solid var(--admin-border);
            border-radius: 18px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .form-control,
        .form-select,
        .input-group-text {
            border-radius: 12px;
            border: 1px solid var(--admin-border);
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
        }

        @media (max-width: 991px) {
            .desktop-sidebar {
                display: none;
            }

            .top-navbar {
                padding: 0 16px;
            }

            .content-area {
                padding: 14px;
            }
        }

        @media (max-width: 576px) {
            .admin-name {
                display: none;
            }

            .notification-menu {
                width: 300px;
            }
        }
    </style>
    @stack('css')
</head>

<body>
    {{-- Toast Container --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
        @if(session('success'))
        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">{{ session('success') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
            </div>
        </div>
        @endif
        @if(session('error'))
        <div class="toast align-items-center text-bg-danger border-0 show mt-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">{{ session('error') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
            </div>
        </div>
        @endif
        @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fa fa-exclamation-triangle me-2"></i>
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @if ($errors->any())
        <div class="toast align-items-center text-bg-danger border-0 show mt-2">
            <div class="d-flex">
                <div class="toast-body">{{ $errors->first() }}</div>
            </div>
        </div>
        @endif
    </div>
    <div class="d-flex admin-layout">
        {{-- Desktop Sidebar --}}
        <aside class="sidebar desktop-sidebar">
            <div class="sidebar-brand">
                <h5>Amazon Sync</h5>
                <small>Admin Panel</small>
            </div>
            <div class="sidebar-menu">
                <a href="{{ route('admin.dashboard') }}"
                    class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="{{ route('admin.shops') }}"
                    class="sidebar-link {{ request()->routeIs('admin.shops*') ? 'active' : '' }}">
                    <i class="bi bi-shop"></i> Shops
                </a>
                <a href="{{ route('admin.category') }}"
                    class="sidebar-link {{ request()->routeIs('admin.category*') ? 'active' : '' }}">
                    <i class="bi bi-grid"></i> Category
                </a>
                <a href="{{ route('admin.mailtemplates') }}"
                    class="sidebar-link {{ request()->routeIs('admin.mailtemplates*') ? 'active' : '' }}">
                    <i class="bi bi-envelope"></i> Mail Templates
                </a>
                <a href="{{ route('admin.contact-requests') }}"
                    class="sidebar-link {{ request()->routeIs('admin.contact-requests*') ? 'active' : '' }}">
                    <i class="bi bi-chat-left-text"></i> Contact Requests
                </a>
                <a href="{{ route('admin.plans') }}"
                    class="sidebar-link {{ request()->routeIs('admin.plans*') ? 'active' : '' }}">
                    <i class="bi bi-credit-card"></i> Plans
                </a>
                <a href="{{ route('admin.notification') }}"
                    class="sidebar-link {{ request()->routeIs('admin.notification*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i> Notification
                </a>
                <a href="{{ route('admin.settings') }}"
                    class="sidebar-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </div>
        </aside>
        {{-- Main Area --}}
        <div class="main-area">
            {{-- Top Navbar --}}
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary d-lg-none mobile-menu-btn"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#sidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <div class="page-title">@yield('title', 'Dashboard')</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    {{-- Notification --}}
                    <div class="dropdown">
                        <button class="notification-btn position-relative"
                            type="button"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5"></i>
                            @if($unreadCount > 0)
                            <span id="adminUnreadBadge"
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-menu">
                            <li class="px-3 py-3 border-bottom">
                                <strong>Notifications</strong>
                                <div class="small text-muted">
                                    {{ $unreadCount }} unread notifications
                                </div>
                            </li>
                            @forelse($adminNotifications as $notification)
                            <li>
                                <a class="dropdown-item notification-item mark-admin-read {{ $notification->is_read ? '' : 'fw-bold' }}"
                                    href="javascript:void(0)"
                                    data-url="{{ route('admin.notification.read', $notification->id) }}">
                                    <div class="fw-semibold">
                                        {{ $notification->title }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $notification->message }}
                                    </small>
                                    <div class="small text-muted mt-1">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </div>
                                </a>
                            </li>
                            @empty
                            <li class="px-3 py-3 text-center text-muted">
                                No notifications found
                            </li>
                            @endforelse
                            <li class="p-2 border-top">
                                <a href="{{ route('admin.notification') }}"
                                    class="btn btn-light w-100 fw-bold"
                                    style="border-radius:12px;">
                                    View All ({{ $unreadCount }})
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-avatar">
                            {{ strtoupper(substr(auth('admin')->user()->name ?? 'A', 0, 1)) }}
                        </div>
                        <span class="text-muted admin-name">
                            {{ auth('admin')->user()->name ?? 'Admin' }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="btn btn-danger btn-sm logout-btn">
                            Logout
                        </button>
                    </form>
                </div>
            </nav>
            {{-- Main Content --}}
            <main class="content-area">
                <div class="admin-page-shell">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    {{-- Mobile Sidebar --}}
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar">
        <div class="offcanvas-header border-bottom border-secondary">
            <div>
                <h5 class="mb-0">Amazon Sync</h5>
            </div>
            <button type="button" class="btn-close btn-close-white d-none" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="{{ route('admin.shops') }}" class="sidebar-link">
                <i class="bi bi-shop"></i> Shops
            </a>
            <a href="{{ route('admin.category') }}" class="sidebar-link">
                <i class="bi bi-grid"></i> Category
            </a>
            <a href="{{ route('admin.mailtemplates') }}" class="sidebar-link">
                <i class="bi bi-envelope"></i> Mail Templates
            </a>
            <a href="{{ route('admin.contact-requests') }}"
                class="sidebar-link {{ request()->routeIs('admin.contact-requests*') ? 'active' : '' }}">
                <i class="bi bi-chat-left-text"></i> Contact Requests
            </a>
            <a href="{{ route('admin.plans') }}" class="sidebar-link">
                <i class="bi bi-credit-card"></i> Plans
            </a>
            <a href="{{ route('admin.notification') }}" class="sidebar-link">
                <i class="bi bi-bell"></i> Notification
            </a>
            <a href="{{ route('admin.settings') }}" class="sidebar-link">
                <i class="bi bi-gear"></i> Settings
            </a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.toast').forEach(function(toastEl) {
                let toast = new bootstrap.Toast(toastEl, {
                    delay: 20000
                });
                toast.show();
            });
        });
    </script>
    <script>
        document.addEventListener('click', function(e) {
            let item = e.target.closest('.mark-admin-read');
            if (!item) return;
            e.preventDefault();
            e.stopPropagation();
            fetch(item.dataset.url, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "application/json"
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.log(error);
                });
        });
    </script>
    @yield('scripts')
    @stack('scripts')
</body>

</html>