@extends('layouts.app')

@section('title', 'Notification')

@section('content')

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
    }

    .saas-wrapper {
        /* max-width: 1180px; */
        /* margin: 0 auto; */
        padding: 12px 25px;
    }

    .content {
        padding: 0;
        /* Resetting padding to let wrapper handle it */
    }

    /* Page Header */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 6px 16px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
    }

    .saas-page-title i {
        color: #005BD3;
        /* Polaris Primary Blue */
        font-size: 16px;
    }

    .saas-page-subtitle {
        color: #6D7175;
        font-size: 12px;
        margin: 0;
    }

    /* Cards */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 16px;
        overflow: hidden;
    }

    /* List Items */
    .saas-list-item {
        padding: 12px 16px;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        transition: background-color 0.15s ease;
    }

    .saas-list-item:hover {
        background-color: #F9FAFB;
    }

    .saas-list-item:last-child {
        border-bottom: none;
    }

    .saas-notif-title {
        font-size: 13px;
        font-weight: 650;
        color: #1A1A1A;
        margin: 0 0 2px 0;
    }

    .saas-notif-desc {
        font-size: 13px;
        color: #6D7175;
        margin: 0 0 4px 0;
        line-height: 1.4;
    }

    .saas-notif-time {
        font-size: 11px;
        color: #8C9196;
        font-weight: 500;
    }

    /* Badges Override */
    .saas-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        border: none !important;
        white-space: nowrap;
    }

    .bg-danger {
        background-color: #FED3D1 !important;
        color: #8C1105 !important;
    }

    /* Empty State */
    .saas-empty-state {
        text-align: center;
        padding: 32px 16px;
        color: #6D7175;
    }

    .saas-empty-state i {
        font-size: 24px;
        color: #8C9196;
        margin-bottom: 8px;
        display: inline-block;
    }

    /* Banners & Alerts */
    .saas-banner-info {
        border-radius: 8px;
        padding: 12px 16px;
        background-color: #EBF5FA;
        color: #202223;
        border: 1px solid #B4E1FA;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 13px;
        line-height: 1.4;
    }

    .saas-banner-info i {
        color: #006FBB;
        font-size: 16px;
        line-height: 1;
        margin-top: 2px;
    }

    /* Pagination Override (Targets Laravel Default Bootstrap Links) */
    .saas-pagination-footer {
        padding: 12px 16px;
        background: #FFFFFF;
        border-top: 1px solid #E5E7EB;
        display: flex;
        justify-content: center;
    }

    .saas-pagination-footer nav {
        margin: 0;
    }

    .saas-pagination-footer .pagination {
        margin: 0;
        gap: 4px;
        align-items: center;
    }

    .saas-pagination-footer .page-item .page-link {
        padding: 4px 10px;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        background: #FFFFFF;
        color: #202223;
        font-size: 12px;
        font-weight: 600;
        box-shadow: none;
        margin: 0;
    }

    .saas-pagination-footer .page-item.active .page-link {
        background: #1A1A1A;
        color: #FFFFFF;
        border-color: #1A1A1A;
    }

    .saas-pagination-footer .page-item.disabled .page-link {
        background: #F9FAFB;
        color: #8C9196;
        border-color: #E5E7EB;
    }
</style>

<div class="content">
    <div class="saas-wrapper">

        {{-- Page Header --}}
        <div class="saas-page-header mt-4">
            <div class="col-6 col-md-6">
                <h1 class="saas-page-title">
                    <i class="fa fa-bell me-2"></i> Latest Notifications
                </h1>
                <p class="saas-page-subtitle">
                    All Notifications.
                </p>
            </div>
            <div class="col-6 col-md-6 text-end" style="display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                <form id="deleteAllForm" action="{{ route('user.notification.delete.all') }}?shop={{ $request->shop ?? session('active_shop') }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn text-danger btn-link btn-sm">
                        Delete All
                    </button>
                </form>
            </div>
            
        </div>

        {{-- Notifications List Card --}}
        <div class="saas-card">
            <div class="saas-card-body p-0">

                @forelse($latestNotifications as $notification)

                <div class="saas-list-item">
                    <div>
                        <h6 class="saas-notif-title">
                            {{ $notification->title }}
                        </h6>

                        <p class="saas-notif-desc">
                            {{ $notification->message }}
                        </p>

                        <div class="saas-notif-time">
                            {{ $notification->created_at->diffForHumans() }}
                        </div>
                    </div>

                    @if(!$notification->is_read)
                        <span class="saas-badge bg-danger">New</span>
                    @else
                        <form action="{{ route('user.notification.delete', ['id' => $notification->id, 'shop' => $request->shop ?? session('active_shop')]) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link btn-sm text-danger">
                                Remove
                            </button>
                        </form>
                    @endif
                </div>

                @empty

                <div class="saas-empty-state">
                    <i class="fa fa-bell-slash"></i>
                    <p class="mb-0">No notifications found.</p>
                </div>

                @endforelse

            </div>

            @if($latestNotifications->hasPages())
            <div class="saas-pagination-footer">
                {{ $latestNotifications->links('pagination::bootstrap-5') }}
            </div>
            @endif

        </div>

        {{-- Info Banner --}}
        <div class="saas-banner-info">
            <i class="fa fa-info-circle"></i>
            <div>
                <strong>Note:</strong> In-app notifications will be shown in the notification center for all admin users.
            </div>
        </div>

    </div>
</div>


@endsection