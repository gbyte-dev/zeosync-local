@extends('admin.layout.app')

@section('title', 'Shops')

@section('content')

<style>
    #shops-table_filter {
        float: inline-end;
    }

    #shops-table_paginate {
        float: inline-end;
        margin-top: 10px;
    }

    #shops-table {
        margin-bottom: 10px;
    }

    #shops-table_info {
        float: inline-start;
        margin-top: 10px;
    }

    #shops-table_length {
        width: fit-content;
    }

    .dataTables_length>label {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dataTables_filter>label {
        display: flex;
        align-items: center;
        gap: 10px;
    }
</style>

<div class="container-fluid px-0">

    {{-- Header --}}

    <div class="card shadow-sm border-0  overflow-hidden">
<?php /*   <div class="p-3 text-dark shadow header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold mb-1">Shops</h5>
                    <p class="mb-0 opacity-75">
                        Manage your shops and their details
                    </p>
                </div>
                <!-- <button class="btn btn-light btn-sm fw-bold rounded-pill px-4 btn-add-category">
                <i class="bi bi-plus-lg me-1"></i> Add Category
            </button> -->
            </div>
        </div>
*/ ?>

        <div class="card-body border-0 shadow-sm overflow-hidden">
            <!-- <div class="card-body p-2"> -->

            {{-- Desktop Table --}}
            @if($shops->count() > 0)

            <div class="table-responsive d-none d-md-block">
                <table id="shops-table" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-uppercase small text-muted">#</th>
                            <th class="text-uppercase small text-muted">Shop</th>
                            <th class="text-uppercase small text-muted">Subscription</th>
                            <th class="text-uppercase small text-muted">Subscri. Status</th>
                            <th class="text-uppercase small text-muted">Status</th>
                            <th class="text-uppercase small text-muted">Connected At</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($shops as $shop)
                        <tr>
                            <td class="ps-4 text-muted fw-semibold">
                                #{{ $shop->id }}
                            </td>

                            <td>
                                <a href="{{ route('admin.shops.show', $shop->id) }}"
                                    class="text-dark text-decoration-none fw-bold d-flex align-items-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary fw-bold me-2"
                                        style="width: 38px; height: 38px;">
                                        {{ strtoupper(substr($shop->shop, 0, 1)) }}
                                    </span>
                                    <span>{{ $shop->shop }}</span>
                                </a>
                            </td>

                            <td>
                                @if($shop->subscription && $shop->subscription->plan)
                                <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                    {{ $shop->subscription->plan->name }}
                                </span>
                                @else
                                <span class="text-muted small">N/A</span>
                                @endif
                            </td>

                            <td>
                                @if($shop->subscription)
                                <span class="badge bg-{{ $shop->subscription->status === 'active' ? 'success' : 'danger' }}">
                                    {{ ucfirst($shop->subscription->status) }}
                                </span>
                                @else
                                <span class="badge bg-secondary">No Subscription</span>
                                @endif
                            </td>

                            <td>
                                @if($shop->is_active)
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                    ● Active
                                </span>
                                @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                                    ● Inactive
                                </span>
                                @endif
                            </td>

                            <td class="text-muted small">
                                {{ $shop->created_at?->format('d M Y') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @else

            <div class="d-none d-md-block">
                <div class="alert alert-info text-center mb-0">
                    No shops found.
                </div>
            </div>

            @endif

            {{-- Mobile Cards --}}
            <div class="d-block d-md-none p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">Search</span>
                    <input type="text" id="mobile-shop-search" class="form-control" placeholder="Find a shop">
                </div>

                @forelse($shops as $shop)
                <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm" data-shop-card>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <a href="{{ route('admin.shops.show', $shop->id) }}"
                            class="text-dark text-decoration-none fw-bold d-flex align-items-center">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary fw-bold me-2"
                                style="width: 38px; height: 38px;">
                                {{ strtoupper(substr($shop->shop, 0, 1)) }}
                            </span>
                            <span>{{ $shop->shop }}</span>
                        </a>

                        @if($shop->is_active)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Active</span>
                        @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">Inactive</span>
                        @endif
                    </div>

                    <div class="small text-muted mb-1">
                        ID: #{{ $shop->id }}
                    </div>

                    <div class="small text-muted mb-1">
                        Subscription:
                        {{ $shop->subscription ? ucfirst($shop->subscription->billing_interval) : 'N/A' }}
                    </div>

                    <div class="small text-muted">
                        Connected:
                        {{ $shop->created_at?->format('d M Y, h:i A') }}
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    No shops found.
                </div>
                @endforelse

                <div id="mobile-shop-no-results" class="text-center text-muted py-5 d-none">
                    No matching shops found
                </div>
            </div>

            <!-- </div> -->

        </div>
    </div>

    @endsection

    @section('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    @endsection

    @section('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
                    // Desktop DataTable — pagination + search + sorting, styled for Bootstrap 5
                    if ($('#shops-table tbody tr').length > 0 &&
                        $('#shops-table tbody tr td[colspan]').length === 0) {

                        if ($('#shops-table').length) {

                            $('#shops-table').DataTable({
                                pagingType: 'simple_numbers',
                                pageLength: 10,
                                lengthChange: true,
                                lengthMenu: [10, 25, 50, 100],
                                searching: true,
                                ordering: true,
                                info: true,
                                columnDefs: [{
                                    orderable: false,
                                    targets: [1, 2, 3, 4]
                                }],
                                language: {
                                    search: "Search:",
                                    searchPlaceholder: "Find a shop",
                                    emptyTable: "No shops found",
                                    zeroRecords: "No matching shops found",
                                    info: "Showing _START_ to _END_ of _TOTAL_ shops",
                                    infoEmpty: "Showing 0 shops",
                                    infoFiltered: "(filtered from _MAX_ total shops)",
                                    lengthMenu: "Show _MENU_ shops",
                                    paginate: {
                                        previous: "Previous",
                                        next: "Next"
                                    }
                                }
                            });
                        }

                        // Mobile card search (simple client-side filter, mirrors desktop search behavior)
                        const mobileSearch = document.getElementById('mobile-shop-search');
                        const mobileCards = Array.from(document.querySelectorAll('[data-shop-card]'));
                        const mobileNoResults = document.getElementById('mobile-shop-no-results');

                        if (mobileSearch) {
                            mobileSearch.addEventListener('input', function() {
                                const query = mobileSearch.value.trim().toLowerCase();
                                let visibleCount = 0;

                                mobileCards.forEach(function(card) {
                                    const isVisible = card.textContent.toLowerCase().includes(query);
                                    card.style.display = isVisible ? '' : 'none';
                                    if (isVisible) visibleCount++;
                                });

                                if (mobileNoResults) {
                                    mobileNoResults.classList.toggle('d-none', visibleCount !== 0 || mobileCards.length === 0);
                                }
                            });
                        }
                    });
    </script>
    @endsection