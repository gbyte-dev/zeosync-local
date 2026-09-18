<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin Dashboard') | Amazon Sync</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Bootstrap 5 --}}
    <link nonce="{{ $cspNonce }}" href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <link nonce="{{ $cspNonce }}" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link nonce="{{ $cspNonce }}" rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link nonce="{{ $cspNonce }}" rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link nonce="{{ $cspNonce }}" rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    @php
    $favicon = \App\Models\AdminSetting::where('option_key', 'app_favicon')->value('option_value');

    $fallback = asset('logo/favamzsync.png');

    $faviconUrl = $fallback;

    if ( !empty($favicon) && \Illuminate\Support\Facades\Storage::disk('public')->exists($favicon)) {
        $faviconUrl = asset('storage/' . $favicon);
    }   
    @endphp

    <link nonce="{{ $cspNonce }}" rel="icon" type="image/png" sizes="32x32" href="{{ $faviconUrl }}">
    <link nonce="{{ $cspNonce }}" rel="icon" type="image/png" sizes="16x16" href="{{ $faviconUrl }}">
    <link nonce="{{ $cspNonce }}" rel="apple-touch-icon" sizes="180x180" href="{{ $faviconUrl }}">

    <style nonce="{{ $cspNonce }}">
        body {
            background: #f5f7fb;
        }

        .admin-layout {
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: linear-gradient(180deg, #0f172a, #111827);
            color: #fff;
            position: sticky;
            top: 0;
        }

        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .sidebar-brand h5 {
            font-weight: 800;
            margin: 0;
        }

        .sidebar-brand small {
            color: #94a3b8;
        }

        .sidebar-menu {
            padding: 16px 12px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 8px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 400;
            transition: .2s;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(37, 99, 235, .18);
            color: #fff;
        }

        .sidebar-link i {
            font-size: 18px;
        }

        .main-area {
            flex: 1;
            min-width: 0;
        }

        .top-navbar {
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #eef2f7;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .page-title {
            font-weight: 800;
            color: #111827;
        }

        .notification-btn {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid #eef2f7;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-menu {
            width: 340px;
            border: 0;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .15);
            overflow: auto;
        }

        .notification-item {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .notification-item:last-child {
            border-bottom: 0;
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .content-area {
            padding: 15px;
        }

        .offcanvas-start {
            width: 270px !important;
            background: linear-gradient(180deg, #0f172a, #111827);
            color: #fff;
        }

        .mobile-menu-btn {
            border-radius: 12px;
        }

        .logout-btn {
            border-radius: 10px;
            font-weight: 700;
        }

        .toast {
            border-radius: 14px;
        }

        @media(max-width: 991px) {
            .desktop-sidebar {
                display: none;
            }

            .top-navbar {
                padding: 0 16px;
            }

            .content-area {
                padding: 11px;
            }
        }

        @media(max-width: 576px) {
            .admin-name {
                display: none;
            }

            .notification-menu {
                width: 300px;
            }
        }

        .offcanvas-start {
            width: 260px !important;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow: hidden;
        }

        .offcanvas-body {
            overflow-y: auto;
            height: calc(100vh - 70px);
            /* header height ke hisab se */
        }

        body {
            overflow-x: hidden;
        }

        @media (min-width: 992px) {
            .offcanvas-start {
                visibility: visible !important;
                transform: none !important;
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 260px !important;
                z-index: 1040;
            }
        }

        tbody,
        td {
            font-size: small;
        }

        .header {
            background: rgba(var(--bs-body-color-rgb), 0.11);
            border-radius: 22px 22px 0 0;
            border-top: 1px solid gray;
        }

    #datatable-table_filter { float: inline-end; }
    #datatable-table_paginate { float: inline-end; margin-top: 10px; }
    #datatable-table { margin-bottom: 10px; }
    #datatable-table_info { float: inline-start; margin-top: 10px; }
    #datatable-table_length { width: fit-content; }
    .dataTables_length>label,
    .dataTables_filter>label { display: flex; align-items: center; gap: 10px; }

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
                    <i class="bi bi-chat-left-text"></i> Contact Requests <span class="badge bg-danger rounded-pill ms-auto">{{ getContactInquiryUnread()->count() }}</span>
                </a>
                <a href="{{ route('admin.plans') }}"
                    class="sidebar-link {{ request()->routeIs('admin.plans*') ? 'active' : '' }}">
                    <i class="bi bi-credit-card"></i> Plans
                </a>
                <a href="{{ route('admin.notification') }}"
                    class="sidebar-link {{ request()->routeIs('admin.notification*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i> Notification <span class="badge bg-danger rounded-pill ms-auto">{{ getAdminNotificationUnread()->count() }}</span>
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
                                    class="btn btn-light w-100"
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
                @yield('content')
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
                <i class="bi bi-chat-left-text"></i> Contact Requests @if(getContactInquiryUnread()->count() > 0) <span class="badge bg-danger rounded-pill ms-auto">{{ getContactInquiryUnread()->count() }}</span> @endif
            </a>
            <a href="{{ route('admin.plans') }}" class="sidebar-link">
                <i class="bi bi-credit-card"></i> Plans
            </a>
            <a href="{{ route('admin.notification') }}" class="sidebar-link">
                <i class="bi bi-bell"></i> Notification @if(getAdminNotificationUnread()->count() > 0) <span class="badge bg-danger rounded-pill ms-auto">{{ getAdminNotificationUnread()->count() }}</span> @endif
            </a>
            <a href="{{ route('admin.settings') }}" class="sidebar-link">
                <i class="bi bi-gear"></i> Settings
            </a>
        </div>
    </div>
    <script nonce="{{ $cspNonce }}" src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    <script nonce="{{ $cspNonce }}" src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Buttons extension -->
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script nonce="{{ $cspNonce }}" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

    <script nonce="{{ $cspNonce }}">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.toast').forEach(function(toastEl) {
                let toast = new bootstrap.Toast(toastEl, {
                    delay: 20000
                });
                toast.show();
            });
        });
    </script>
    <script nonce="{{ $cspNonce }}">
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
                    console.error(error);
                });
        });

    // Initialize reusable datatable for this page
    function initDatatable(selector, opts = {}) {
        const $el = $(selector);
        if (!$el.length) return null;

        const defaults = {
            dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'csv', className: 'btn btn-sm btn-outline-primary text-white' },
                { extend: 'excel', className: 'btn btn-sm btn-outline-primary text-white' },
                { extend: 'pdf', className: 'btn btn-sm btn-outline-primary text-white' },
                { extend: 'print', className: 'btn btn-sm btn-outline-primary text-white' },
                { extend: 'colvis', className: 'btn btn-sm btn-outline-primary text-white' }
            ],
            responsive: true,
            pagingType: 'simple_numbers',
            pageLength: 10,
            lengthChange: true,
            lengthMenu: [10, 25, 50, 100],
            searching: true,
            ordering: true,
            info: true,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [1,2,3,4] }],
            language: {
                search: "Search:",
                searchPlaceholder: "Find a shop",
                emptyTable: "No shops found",
                zeroRecords: "No matching shops found",
                info: "Showing _START_ to _END_ of _TOTAL_ shops",
                infoEmpty: "Showing 0 shops",
                infoFiltered: "(filtered from _MAX_ total shops)",
                lengthMenu: "Show _MENU_ shops",
                paginate: { previous: "Previous", next: "Next" }
            }
        };

        const config = $.extend(true, {}, defaults, opts);
        return $el.DataTable(config);
    }
    </script>
    @yield('scripts')
    @stack('scripts')
</body>

</html>