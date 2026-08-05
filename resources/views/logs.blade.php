@extends('layouts.app')

@push('css')
<!-- Bootstrap 5 DataTables 1.13.8 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
    }

    .saas-wrapper {
        max-width: 1180px;
        padding: 12px 16px;
    }

    /* Page Header */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
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

    /* Tables */
    .saas-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        margin-bottom: 0 !important;
    }

    .saas-table th {
        background: #F9FAFB;
        color: #6D7175;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 16px;
        border-bottom: 1px solid #E5E7EB;
        white-space: nowrap;
    }

    .saas-table td {
        padding: 0.6rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #E5E7EB;
        color: #202223;
    }

    .saas-table tr:last-child td {
        border-bottom: none;
    }

    /* Badges Override */
    .saas-table .soft-badge,
    .mobile-log-card .soft-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        border: none !important;
    }

    .bg-success-subtle {
        background-color: #AEE9D1 !important;
        color: #005C3B !important;
    }

    .bg-warning-subtle {
        background-color: #FFEA8A !important;
        color: #8A6116 !important;
    }

    .bg-danger-subtle {
        background-color: #FED3D1 !important;
        color: #8C1105 !important;
    }

    .saas-log-message {
        max-width: 460px;
        color: #6D7175;
        font-size: 13px;
        line-height: 1.4;
        white-space: normal;
    }

    /* Mobile view */
    .mobile-log-list {
        display: none;
    }

    .mobile-log-card {
        border-bottom: 1px solid #E5E7EB;
        padding: 12px 16px;
        background: #FFFFFF;
    }

    .mobile-log-card:last-child {
        border-bottom: none;
    }

    @media(max-width: 768px) {
        .desktop-log-table {
            display: none;
        }

        .mobile-log-list {
            display: block;
        }
    }

    /* ---------------------------------------------------
       DataTables 1.13.8 Integration Fixes (Theme specific)
    --------------------------------------------------- */
    div.dataTables_wrapper {
        padding: 0 !important;
        display: block !important;
        width: 100% !important;
    }

    /* Override Bootstrap rows inside DataTables to prevent collapsing
       We use :not(:nth-child(2)) to exclude the main table row from being flex-squished */
    div.dataTables_wrapper>div.row:not(:nth-child(2)) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        margin: 0 !important;
        padding: 12px 16px !important;
        background: #FFFFFF;
        width: 100% !important;
    }

    div.dataTables_wrapper>div.row:nth-child(2) {
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Add border to the header row */
    div.dataTables_wrapper>div.row:first-of-type {
        border-bottom: 1px solid #E5E7EB !important;
    }

    /* Add border to the footer row */
    div.dataTables_wrapper>div.row:last-of-type {
        border-top: 1px solid #E5E7EB !important;
    }

    /* Force columns to auto-size based on content */
    div.dataTables_wrapper>div.row:not(:nth-child(2))>div[class*="col-"] {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: 100% !important;
        padding: 0 !important;
    }

    /* Guarantee visibility of specific elements */
    .dataTables_filter,
    .dataTables_length,
    .dataTables_info,
    .dataTables_paginate {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Layout the inner labels using flexbox */
    .dataTables_filter label,
    .dataTables_length label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin-bottom: 0 !important;
        font-weight: 500;
        color: #6D7175;
        font-size: 13px;
        white-space: nowrap;
    }

    /* Match SaaS Input Styling for DataTables inputs */
    .dataTables_filter input {
        border: 1px solid #C9CCCF !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        font-size: 13px !important;
        min-height: 32px !important;
        display: inline-block !important;
        width: 220px !important;
        box-shadow: none !important;
        outline: none !important;
        background-color: #FFFFFF !important;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .dataTables_filter input:focus {
        border-color: #2C6ECB !important;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2) !important;
    }

    .dataTables_length select {
        border: 1px solid #C9CCCF !important;
        border-radius: 6px !important;
        padding: 4px 28px 4px 10px !important;
        font-size: 13px !important;
        min-height: 32px !important;
        display: inline-block !important;
        width: auto !important;
        box-shadow: none !important;
        outline: none !important;
        background-color: #FFFFFF !important;
    }

    /* Pagination Buttons Styling */
    div.dataTables_paginate .pagination {
        margin: 0 !important;
        gap: 4px !important;
    }

    div.dataTables_paginate .pagination .page-item .page-link {
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #202223 !important;
        border: 1px solid #C9CCCF !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        background: #FFFFFF !important;
    }

    div.dataTables_paginate .pagination .page-item:not(.active):not(.disabled) .page-link:hover {
        background-color: #F4F6F8 !important;
    }

    div.dataTables_paginate .pagination .page-item.active .page-link {
        background-color: #1A1A1A !important;
        border-color: #1A1A1A !important;
        color: #FFFFFF !important;
    }

    div.dataTables_info {
        font-size: 12px !important;
        color: #6D7175 !important;
        padding-top: 0 !important;
    }
</style>
@endpush

@section('content')
<div class="saas-wrapper">

    {{-- Header --}}
    <div class="saas-page-header row">
        <div class="col-6 col-md-6">
            <h1 class="saas-page-title">Sync Logs</h1>
            <p class="saas-page-subtitle">Track all synchronization activities between Amazon and Shopify</p>
        </div>
        <div class="col-6 col-md-6 text-end" style="display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
            <form action="{{ route('shopify.logs.remove.all') }}" method="POST" onsubmit="return confirm('Are you sure you want to remove all logs?');">
                @csrf
                <button type="submit" class="btn btn-link btn-sm text-danger">
                    Remove All Logs
                </button>
            </form>
        </div>
    </div>

    {{-- Logs Card --}}
    <div class="saas-card mb-0">

        {{-- Desktop Table --}}
        <div class="table-responsive desktop-log-table">
            <table id="sync-logs-table" class="saas-table">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th class="text-end pe-4">Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @if($logs->count())
                    @foreach($logs as $log)
                    <tr>
                        <td class="ps-4 text-muted" style="font-size: 12px;">
                            #{{ $log->id }}
                        </td>

                        <td>
                            <span class="fw-semibold text-dark text-capitalize">
                                {{ str_replace('_', ' ', ucfirst($log->type ?? 'order')) }}
                            </span>
                        </td>

                        <td>
                            @if($log->status === 'success')
                            <span class="badge bg-success-subtle text-success soft-badge">
                                ● Success
                            </span>
                            @elseif($log->status === 'failed')
                            <span class="badge bg-warning-subtle text-warning soft-badge">
                                ● Failed
                            </span>
                            @else
                            <span class="badge bg-danger-subtle text-danger soft-badge">
                                ● Error
                            </span>
                            @endif
                        </td>

                        <td>
                            <div class="saas-log-message">
                                {{ $log->message ?? 'No message available' }}
                            </div>
                        </td>

                        <td class="text-end pe-4 text-muted" style="font-size: 12px;">
                            {{ optional($log->created_at)->format('d M Y, h:i A') }}
                        </td>
                        <td class="text-center">
                            <form action="{{ route('shopify.logs.remove', $log->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this log?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link btn-sm text-danger">
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="mobile-log-list">
            @forelse($logs as $log)
            <div class="mobile-log-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold text-dark text-capitalize" style="font-size: 13px;">
                            {{ $log->type ?? 'order' }}
                        </div>
                        <div class="text-muted" style="font-size: 11px;">
                            #{{ $log->id }}
                        </div>
                    </div>
                    @if($log->status == 'success')
                    <span class="badge bg-success-subtle text-success soft-badge">Success</span>
                    @elseif($log->status == 'failed')
                    <span class="badge bg-warning-subtle text-warning soft-badge">Failed</span>
                    @else
                    <span class="badge bg-danger-subtle text-danger soft-badge">Error</span>
                    @endif
                </div>
                <p class="saas-log-message mb-2">
                    {{ $log->message ?? 'No message available' }}
                </p>
                <div class="text-muted" style="font-size: 11px;">
                    {{ optional($log->created_at)->format('d M Y, h:i A') }}
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-4" style="font-size: 13px;">
                No sync logs found
            </div>
            @endforelse
        </div>

    </div>
<!-- </div> -->
@endsection

@push('scripts')
<!-- DataTables 1.13.8 Client-Side Initialisation pushed to layout stack -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        $('#sync-logs-table').DataTable({
            "paging": true,
            "searching": true,
            "lengthChange": true,
            "pageLength": 10,
            "lengthMenu": [10, 25, 50, 100],
            "order": [], // Preserves backend sorting by default
            "language": {
                "search": "Search:",
                "searchPlaceholder": "Search logs...",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "Showing 0 to 0 of 0 entries",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "emptyTable": "No sync logs found"
            }
        });
    });
</script>
@endpush