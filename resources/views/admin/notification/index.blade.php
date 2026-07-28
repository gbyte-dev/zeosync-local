@extends('admin.layout.app')

@section('title', 'Notification')

@section('content')

<style>
    /* Only the brand gradient can't be done with stock Bootstrap utilities */
    .bg-brand-gradient {
        background: linear-gradient(135deg, #111827, #2563eb);
    }
</style>

<div class="" style="max-width: 1400px;">

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold mb-1">Total Notifications</div>
                    <div class="fs-3 fw-bold">{{ $totalNotifications }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold mb-1">Email Enabled</div>
                    <div class="fs-3 fw-bold text-success">{{ $emailEnabled }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold mb-1">In-App Enabled</div>
                    <div class="fs-3 fw-bold text-primary">{{ $inAppEnabled }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-bold mb-1">Last Updated</div>
                    <div class="fs-5 fw-bold">
                        {{ $lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->diffForHumans() : 'Never' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Latest notifications --}}
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 rounded-top-4">
            <div class="row">
            <div class=" col-sm-6">
                <h5 class="mb-0 fw-bold">
                    <i class="fa fa-bell text-primary me-2"></i>
                    Latest Notifications
                </h5>
            </div>
             <div class=" col-md-6 text-end" style="display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                <form id="notificationForm" action="{{ route('admin.notification.marked') }}" method="POST">
                    @csrf
                    <button type="submit" id="saveChangesBtn" class="btn btn-link btn-sm">
                        Mark All as Read
                    </button>
                </form>
                <form id="deleteAllForm" action="{{ route('admin.notification.delete.all') }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn text-danger btn-link btn-sm">
                        Delete All
                    </button>
                </form>
            </div>
            </div>
        </div>

        <div class="card-body p-0">

            @forelse($latestNotifications as $notification)

            <div class="d-flex justify-content-between align-items-start px-4 py-3 border-bottom">

                <div class="d-flex">

                    <div class="me-3">
                        @if($notification->status == 'success')
                        <span class="badge bg-success p-2">
                            <i class="fa fa-check"></i>
                        </span>
                        @elseif($notification->status == 'error')
                        <span class="badge bg-danger p-2">
                            <i class="fa fa-times"></i>
                        </span>
                        @else
                        <span class="badge bg-primary p-2">
                            <i class="fa fa-bell"></i>
                        </span>
                        @endif
                    </div>
                    <div> 
                         <h6 class="mb-0">  {{ $notification->title }}  </h6>
                         <p class="mb-0 text-muted"> <small> {{ $notification->message }} </small> </p>
                        <small class="text-secondary"> {{ $notification->created_at->format('d M Y h:i A') }} </small>
                    </div>

                </div>

                @if(!$notification->is_read)
                    <span class="">New</span>
                @else
                    <form action="{{ route('admin.notification.delete', ['id' => $notification->id]) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-link btn-sm text-danger">
                            Remove
                        </button>
                    </form>
                @endif

            </div>

            @empty

            <div class="text-center py-5 text-muted">
                <i class="fa fa-bell-slash fa-2x mb-3"></i>
                <p class="mb-0">No notifications found.</p>
            </div>

            @endforelse

        </div>

        @if($latestNotifications->hasPages())
        <div class="card-footer bg-white rounded-bottom-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing <strong class="text-dark">{{ $latestNotifications->firstItem() }}</strong>
                    to <strong class="text-dark">{{ $latestNotifications->lastItem() }}</strong>
                    of <strong class="text-dark">{{ $latestNotifications->total() }}</strong> notifications
                </div>

                {{ $latestNotifications->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
        @endif

    </div>

    {{-- Note --}}
    <div class="alert alert-primary d-flex gap-2 rounded-4 mt-4 mb-0">
        <i class="fa fa-info-circle mt-1"></i>
        <div>
            <strong>Note:</strong> In-app notifications will be shown in the notification center for all admin users.
        </div>
    </div>

</div>

@endsection