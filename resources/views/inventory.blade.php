@extends('layouts.app')

@section('content')

<style>
    table td {
        vertical-align: middle;
    }

    table input {
        text-align: center;
    }

    img {
        object-fit: cover;
    }

    tbody tr:hover {
        background-color: #f6f6f7;
    }

    input.form-control {
        border-radius: 8px;
    }

    thead th {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
    }

    .card-bodydata {
        padding: 0px 0px 0px 5px !important;
        display: flex;
        flex-direction: row;
    }

    .titem {
        /* margin: 7px; */
        margin: auto;
    }



    @media (max-width: 768px) {
        .titem {
            margin: auto;
        }
    }
</style>



<div class="container-fluid py-4">
    <h6 class="fw-bold mb-4">Inventory</h6>
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body card-bodydata"><small style="margin: 9px;">Total Items</small>
                    <h6 class="titem" id="totalCount">0</h6>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body card-bodydata"><small style="margin: 9px;">Synced</small>
                    <h6 class="text-success titem" id="syncedCount">0</h6>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body card-bodydata"><small style="margin: 9px;">Pending</small>
                    <h6 class="text-warning titem" id="pendingCount">0</h6>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body card-bodydata"><strong style="margin: 9px;">Errors</strong>
                    <h4 class="text-danger titem" id="errorCount">0</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#shopifyTab">Shopify</button>
        </li>

        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#amazonTab">Amazon</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Shopify -->
        <div class="tab-pane fade show active" id="shopifyTab">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <button class="btn btn-primary mb-3" onclick="loadShopify()">Load Shopify</button>
                        </div>

                        <div class="col-sm-9">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <input type="text" id="searchInputs" class="form-control"
                                        placeholder="Search SKU / Product...">
                                </div>

                                <div class="col-md-3">
                                    <select id="statusFilters" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="synced">Synced</option>
                                        <option value="pending">Pending</option>
                                        <option value="error">Error</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-dark w-100" onclick="applyFilter('s')" title="Search"><i
                                            class="fa fa-search"></i></button>
                                </div>

                                <!-- <div class="col-md-2">
                                <button class="btn btn-secondary w-100" onclick="resetFilter()">Reset</button>
                            </div> -->

                                <div class="col-md-2">
                                    <button onclick="refreshCache(event)" class="btn btn-danger w-100" title="Refresh">
                                        <i class="fa fa-refresh"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle" id="shopifyTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;">
                                        <input type="checkbox">
                                    </th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Unavailable</th>
                                    <th>Committed</th>
                                    <th>Available</th>
                                    <th>On hand</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">

                        <!-- LEFT -->
                        <div class="text-muted small" id="paginationInfo">
                            Showing 0 to 0 of 0 results
                        </div>
                        <!-- RIGHT -->
                        <div class="custom-pagination" id="paginationButtons"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Amazon -->

        <div class="tab-pane fade" id="amazonTab">
            <div class="card">
                <div class="card-body">
                    @if($shop->amazon_refresh_token)
                    <p><strong>Note : </string> Please click Load Amazon button to get the amazon inventory data</p>
                    <div class="row">
                        <div class="col-sm-3">
                            <button class="btn btn-warning mb-3" onclick="loadAmazon()">Load Amazon</button>
                        </div>

                        <div class="col-sm-9">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <input type="text" id="searchInputa" class="form-control"
                                        placeholder="Search SKU / Product...">
                                </div>

                                <div class="col-md-3">
                                    <select id="statusFiltera" class="form-select">
                                        <option value="">All Status</option>
                                        <option value="synced">Synced</option>
                                        <option value="pending">Pending</option>
                                        <option value="error">Error</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-dark w-100" onclick="applyFilter('a')" title="Search"><i
                                            class="fa fa-search"></i></button>
                                </div>

                                <!-- <div class="col-md-2">
                                <button class="btn btn-secondary w-100" onclick="resetFilter()">Reset</button>
                            </div> -->

                                <div class="col-md-2">
                                    <button onclick="refreshCache(event)" class="btn btn-danger w-100" title="Refresh">
                                        <i class="fa fa-refresh"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="amazonTable">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>ASIN</th>
                                        <th>Qty</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">

                            <!-- LEFT -->
                            <div class="text-muted small" id="paginationInfo">
                                Showing 0 to 0 of 0 results
                            </div>

                            <!-- RIGHT -->
                            <div class="custom-pagination" id="paginationButtons"></div>
                        </div>

                        @else

                        <div class="alert alert-warning">
                            Please connect your Amazon account first.
                            <a href="{{ route('amazon.connect') }}">Connect Amazon</a>
                        </div>

                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>

    @endsection


    @push('scripts')

<script>
    let shopifyData = [];
    let amazonData = [];
    let filteredData = [];
    let activeTab = 'shopify';
    let currentPage = 1;
    let perPage = 10;

    // Load Shopify
    function loadShopify() {
        activeTab = 'shopify';
        currentPage = 1;

        fetch('{{ route("shopify.inventory.shopify") }}')
            .then(res => res.json())
            .then(data => {
                shopifyData = data;
                filteredData = [...data];
                renderTable();
            });
    }

    // Load Amazon
    function loadAmazon() {
        activeTab = 'amazon';
        currentPage = 1;

        fetch('{{ route("shopify.inventory.amazon") }}')
            .then(res => res.json())
            .then(data => {
                amazonData = data;
                filteredData = [...data];
                renderTable();
            });

    }


    function renderTable() {
        let data = filteredData;
        let total = data.length;
        let totalPages = Math.ceil(total / perPage);

        if (currentPage > totalPages) {
            currentPage = totalPages || 1;
        }

        let start = (currentPage - 1) * perPage;
        let paginated = data.slice(start, start + perPage);
        let rows = '';
        let synced = 0, pending = 0, error = 0;

        data.forEach(item => {
            if (item.status === 'synced') synced++;
            if (item.status === 'pending') pending++;
            if (item.status === 'error') error++;
        });

        paginated.forEach(item => {
            if (activeTab === 'shopify') {
                rows += `
        <tr>
            <td><input type="checkbox"></td>
            <td class="d-flex align-items-center gap-2">
                <img src="${item.image || 'https://via.placeholder.com/40'}"
                    width="40" height="40"
                    class="rounded border">
                <div>
                    <div class="fw-semibold">${item.product}</div>
                    <small class="text-muted">${item.variant || ''}</small>
                </div>
            </td>
            <td>${item.sku || 'No SKU'}</td>
            <td>${item.unavailable || 0}</td>
            <td>${item.committed || 0}</td>
            <td>
                <input type="number" value="${item.available || 0}"
                    class="form-control form-control-sm" style="width:80px">
            </td>
            <td>
                <input type="number" value="${item.on_hand || 0}"
                    class="form-control form-control-sm" style="width:80px">
            </td>
        </tr>`;
            }
        });

        document.querySelector(`#${activeTab}Table tbody`).innerHTML = rows;

        document.getElementById('totalCount').innerText = total;
        document.getElementById('syncedCount').innerText = synced;
        document.getElementById('pendingCount').innerText = pending;
        document.getElementById('errorCount').innerText = error;

        let startItem = total === 0 ? 0 : start + 1;
        let endItem = Math.min(start + perPage, total);

        document.getElementById('paginationInfo').innerText =
            `Showing ${startItem} to ${endItem} of ${total} results`;

        let buttons = '';

        // PREV
        buttons += `<button class="page-btn ${currentPage === 1 ? 'disabled' : ''}" onclick="prevPage()">‹</button>`;

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        // FIRST PAGE + DOTS
        if (startPage > 1) {
            buttons += `<button class="page-btn" onclick="goToPage(1)">1</button>`;
            if (startPage > 2) {
                buttons += `<span class="page-btn disabled">...</span>`;
            }
        }

        // MIDDLE PAGES
        for (let i = startPage; i <= endPage; i++) {
            buttons += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        }

        // LAST PAGE + DOTS
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                buttons += `<span class="page-btn disabled">...</span>`;
            }
            buttons += `<button class="page-btn" onclick="goToPage(${totalPages})">${totalPages}</button>`;
        }

        // NEXT
        buttons += `<button class="page-btn ${currentPage === totalPages ? 'disabled' : ''}" onclick="nextPage()">›</button>`;

        document.getElementById('paginationButtons').innerHTML = buttons;
    }

    function goToPage(page) {
        currentPage = page;
        renderTable();
    }

    function nextPage() {
        let totalPages = Math.ceil(filteredData.length / perPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    }

    // Filter
    function applyFilter(types) {

        currentPage = 1;
        let search = document.getElementById('searchInput' + types).value.toLowerCase();
        let status = document.getElementById('statusFilter' + types).value;
        let data = activeTab === 'shopify' ? shopifyData : amazonData;

        filteredData = data.filter(item => {

            let matchSearch =
                (item.sku && item.sku.toLowerCase().includes(search)) ||
                (item.product && item.product.toLowerCase().includes(search));

            let matchStatus = status ? item.status === status : true;
            return matchSearch && matchStatus;
        });

        renderTable();
    }



    // Reset Filter

    function resetFilter() {

        currentPage = 1;
        filteredData = activeTab === 'shopify'
            ? [...shopifyData]  :  [...amazonData];

        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';

        renderTable();
    }

    // Badge
    function badge(status) {

        if (status === 'synced') return `<span class="badge bg-success">Synced</span>`;
        if (status === 'pending') return `<span class="badge bg-warning">Pending</span>`;
        if (status === 'error') return `<span class="badge bg-danger">Error</span>`;
        return `<span class="badge bg-secondary">Unknown</span>`;

    }

    // Refresh Cache
    function refreshCache(event) {

        const btn = event.target;
        btn.innerHTML = 'Refreshing...';
        btn.disabled = true;
        fetch('{{ route("shopify.inventory.refresh") }}')
            .then(() => {
                if (activeTab === 'shopify') loadShopify();
                else loadAmazon();

            })
            .finally(() => {
                btn.innerHTML = 'Force Refresh';
                btn.disabled = false;

            });
    }

    loadShopify();
    </script>

    @endpush