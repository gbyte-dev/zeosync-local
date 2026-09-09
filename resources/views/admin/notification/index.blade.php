@extends('admin.layout.app')

@section('title', 'Notification')

@section('content')

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold">Notifications</h4>
                    <p class="mb-0 text-muted small">Monitor app events, alerts, and recent admin activity</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form id="notificationForm" action="{{ route('admin.notification.marked') }}" method="POST">
                        @csrf
                        <button type="submit" id="saveChangesBtn" class="btn btn-outline-secondary btn-sm">Mark All as Read</button>
                    </form>
                    <form id="deleteAllForm" action="{{ route('admin.notification.delete.all') }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete All</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold mb-1">Total Notifications</div>
                            <div class="fs-3 fw-bold">{{ $totalNotifications }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold mb-1">Email Enabled</div>
                            <div class="fs-3 fw-bold text-success">{{ $emailEnabled }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold mb-1">In-App Enabled</div>
                            <div class="fs-3 fw-bold text-primary">{{ $inAppEnabled }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold mb-1">Last Updated</div>
                            <div class="fs-5 fw-bold">{{ $lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->diffForHumans() : 'Never' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-bell-fill text-primary me-2"></i>Latest Notifications</h5>
                    </div>
                </div>

                <div class="card-body p-0">
                    @forelse($latestNotifications as $notification)
                    <div class="d-flex justify-content-between align-items-start px-4 py-3 border-bottom">
                        <div class="d-flex">
                            <div class="me-3">
                                @if($notification->status == 'success')
                                    <span class="badge bg-success p-2"><i class="bi bi-check-lg"></i></span>
                                @elseif($notification->status == 'error')
                                    <span class="badge bg-danger p-2"><i class="bi bi-x-lg"></i></span>
                                @else
                                    <span class="badge bg-primary p-2"><i class="bi bi-bell"></i></span>
                                @endif
                            </div>
                            <div>
                                <h6 class="mb-1">{{ $notification->title }}</h6>
                                <p class="mb-1 text-muted small">{{ $notification->message }}</p>
                                <small class="text-secondary">{{ $notification->created_at->format('d M Y h:i A') }}</small>
                            </div>
                        </div>

                        @if(!$notification->is_read)
                            <span class="badge bg-primary-subtle text-primary px-3 py-2">New</span>
                        @else
                            <form action="{{ route('admin.notification.delete', ['id' => $notification->id]) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link btn-sm text-danger">Remove</button>
                            </form>
                        @endif
                    </div>
                    @empty
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bell-slash fs-2 d-block mb-3"></i>
                        <p class="mb-0">No notifications found.</p>
                    </div>
                    @endforelse
                </div>

                @if($latestNotifications->hasPages())
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted small">
                            Showing <strong class="text-dark">{{ $latestNotifications->firstItem() }}</strong> to <strong class="text-dark">{{ $latestNotifications->lastItem() }}</strong> of <strong class="text-dark">{{ $latestNotifications->total() }}</strong> notifications
                        </div>
                        {{ $latestNotifications->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
                @endif
            </div>

            <div class="alert alert-primary d-flex gap-2 rounded-4 mt-4 mb-0">
                <i class="bi bi-info-circle mt-1"></i>
                <div><strong>Note:</strong> In-app notifications will be shown in the notification center for all admin users.</div>
            </div>
        </div>
    </div>
</div>
@endsection