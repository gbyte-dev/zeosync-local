@extends('admin.layout.app')

@section('title', 'Shops')

@section('content')

<style>
    #shops-table_filter { float: inline-end; }
    #shops-table_paginate { float: inline-end; margin-top: 10px; }
    #shops-table { margin-bottom: 10px; }
    #shops-table_info { float: inline-start; margin-top: 10px; }
    #shops-table_length { width: fit-content; }
    .dataTables_length>label,
    .dataTables_filter>label { display: flex; align-items: center; gap: 10px; }
</style>

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold">Shops</h4>
                    <p class="mb-0 text-muted small">Manage all connected stores and their subscription status</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2">{{ $shops->count() }} total</span>
                    <span class="badge bg-success-subtle text-success px-3 py-2">{{ $shops->where('is_active', true)->count() }} active</span>
                </div>
            </div>
        </div>

        <div class="card-body border-0">
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
                        @php $i=0; @endphp

                        @foreach($shops as $shop)
                        <tr>
                            <td class="ps-4 text-muted fw-semibold">#{{ ++$i }}</td>
                            <td>
                                <a href="{{ route('admin.shops.show', $shop->id) }}" class="text-dark text-decoration-none fw-bold d-flex align-items-center">
                                    <!-- <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary fw-bold me-2" style="width: 38px; height: 38px;">
                                        {{ strtoupper(substr($shop->shop, 0, 1)) }}
                                    </span> -->
                                    <span>{{ $shop->shop }}</span>
                                </a>
                            </td>
                            <td>
                                @if($shop->subscription && $shop->subscription->plan)
                                <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">{{ $shop->subscription->plan->name }}</span>
                                @else
                                <span class="text-muted small">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($shop->subscription)
                                <span class="badge bg-{{ $shop->subscription->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($shop->subscription->status) }}</span>
                                @else
                                <span class="badge bg-secondary">No Subscription</span>
                                @endif
                            </td>
                            <td>
                                @if($shop->is_active)
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">● Active</span>
                                @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">● Inactive</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $shop->created_at?->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="d-none d-md-block">
                <div class="alert alert-info text-center mb-0">No shops found.</div>
            </div>
            @endif

            <div class="d-block d-md-none">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">Search</span>
                    <input type="text" id="mobile-shop-search" class="form-control" placeholder="Find a shop">
                </div>

                <div id="mobile-shop-list">
                @forelse($shops as $shop)
                <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm" data-shop-card>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <a href="{{ route('admin.shops.show', $shop->id) }}" class="text-dark text-decoration-none fw-bold d-flex align-items-center">
                            <!-- <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary fw-bold me-2" style="width: 38px; height: 38px;">
                                {{ strtoupper(substr($shop->shop, 0, 1)) }}
                            </span> -->
                            <span>{{ $shop->shop }}</span>
                        </a>
                        @if($shop->is_active)
                        <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Active</span>
                        @else
                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">Inactive</span>
                        @endif
                    </div>
                    <div class="small text-muted mb-1">ID: #{{ $shop->id }}</div>
                    <div class="small text-muted mb-1">Subscription: {{ $shop->subscription ? ucfirst($shop->subscription->billing_interval) : 'N/A' }}</div>
                    <div class="small text-muted">Connected: {{ $shop->created_at?->format('d M Y, h:i A') }}</div>
                </div>
                @empty
                <div class="text-center text-muted py-5">No shops found.</div>
                @endforelse
                </div>

                <div id="mobile-shop-no-results" class="text-center text-muted py-5 d-none">No matching shops found</div>

                <div class="d-flex justify-content-between align-items-center mt-2" id="mobile-pagination" style="display:none">
                    <button class="btn btn-sm btn-outline-secondary" id="mobile-prev">Previous</button>
                    <div class="small text-muted" id="mobile-page-info"></div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobile-next">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

    @section('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    @endsection

    @section('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Buttons extension -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

    <script>
        $(document).ready(function() {
            // Desktop DataTable — pagination + search + sorting, styled for Bootstrap 5
            if ($('#shops-table tbody tr').length > 0 && $('#shops-table tbody tr td[colspan]').length === 0) {
                if ($('#shops-table').length) {
                    $('#shops-table').DataTable({
                        dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                            "<'row'<'col-sm-12'tr>>" +
                            "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                        buttons: [
                            { extend: 'copy', className: 'btn btn-sm btn-outline-primary text-white' },
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

                // Mobile card search + pagination (client-side)
                const mobileSearch = document.getElementById('mobile-shop-search');
                const mobileCards = Array.from(document.querySelectorAll('[data-shop-card]'));
                const mobileNoResults = document.getElementById('mobile-shop-no-results');
                const mobilePagination = document.getElementById('mobile-pagination');
                const mobilePrev = document.getElementById('mobile-prev');
                const mobileNext = document.getElementById('mobile-next');
                const mobilePageInfo = document.getElementById('mobile-page-info');

                const perPage = 5;
                let currentPage = 1;

                function renderMobilePage() {
                    const matched = mobileCards.filter(c => c.dataset.match === '1');
                    const total = matched.length;
                    const totalPages = Math.max(1, Math.ceil(total / perPage));

                    if (total === 0) {
                        mobileNoResults.classList.remove('d-none');
                        mobilePagination.style.display = 'none';
                        return;
                    }

                    mobileNoResults.classList.add('d-none');

                    // show only current page items
                    matched.forEach((card, i) => {
                        const start = (currentPage - 1) * perPage;
                        const end = currentPage * perPage;
                        card.style.display = (i >= start && i < end) ? '' : 'none';
                    });

                    // hide non-matched
                    mobileCards.forEach(c => {
                        if (c.dataset.match !== '1') c.style.display = 'none';
                    });

                    mobilePagination.style.display = (totalPages > 1) ? 'flex' : 'none';
                    const showingStart = Math.min((currentPage - 1) * perPage + 1, total);
                    const showingEnd = Math.min(currentPage * perPage, total);
                    mobilePageInfo.textContent = `${showingStart}-${showingEnd} of ${total}`;

                    mobilePrev.disabled = currentPage <= 1;
                    mobileNext.disabled = currentPage >= totalPages;
                }

                function applySearchAndPaginate() {
                    const query = mobileSearch.value.trim().toLowerCase();
                    mobileCards.forEach(function(card) {
                        const isVisible = card.textContent.toLowerCase().includes(query);
                        card.dataset.match = isVisible ? '1' : '0';
                    });
                    currentPage = 1;
                    renderMobilePage();
                }

                if (mobileSearch) {
                    // initialize matches
                    mobileCards.forEach(c => c.dataset.match = '1');
                    renderMobilePage();

                    mobileSearch.addEventListener('input', function() {
                        applySearchAndPaginate();
                    });

                    mobilePrev.addEventListener('click', function() {
                        if (currentPage > 1) {
                            currentPage--;
                            renderMobilePage();
                        }
                    });

                    mobileNext.addEventListener('click', function() {
                        const matched = mobileCards.filter(c => c.dataset.match === '1');
                        const totalPages = Math.max(1, Math.ceil(matched.length / perPage));
                        if (currentPage < totalPages) {
                            currentPage++;
                            renderMobilePage();
                        }
                    });
                }
            }
        });
    </script>
    @endsection