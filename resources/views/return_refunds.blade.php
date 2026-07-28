@extends('layouts.app')

@section('content')

<style>
/* Card */
.return-card {
    border:1px solid #e5e5e5;
    border-radius:12px;
    padding:15px;
    background:#fff;
    transition:0.2s;
}
.return-card:hover {
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}

/* Header */
.return-header {
    display:flex;
    gap:12px;
    align-items:center;
}
.return-img {
    width:60px;
    height:60px;
    border-radius:10px;
    object-fit:cover;
}

/* Info */
.return-title { font-weight:600; }
.return-meta { font-size:13px; color:#666; }

/* Grid */
.return-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
    margin-top:10px;
    text-align:center;
}

/* Status */
.badge-status {
    padding:4px 8px;
    border-radius:6px;
    font-size:12px;
}
.requested { background:#fef3c7; color:#92400e; }
.approved { background:#dbeafe; color:#1e40af; }
.refunded { background:#d1fae5; color:#065f46; }

/* Summary */
.summary-card {
    border-radius:12px;
    padding:15px;
    background:#fff;
    box-shadow:0 2px 6px rgba(0,0,0,0.05);
}
</style>

<div class="container-fluid py-4">

<h2 class="fw-bold mb-4">Returns & Refunds</h2>

<!-- SUMMARY -->
<div class="row mb-4">
    <div class="col-md-3"><div class="summary-card">Total <h4 id="totalCount">0</h4></div></div>
    <div class="col-md-3"><div class="summary-card text-warning">Requested <h4 id="requestedCount">0</h4></div></div>
    <div class="col-md-3"><div class="summary-card text-info">Approved <h4 id="approvedCount">0</h4></div></div>
    <div class="col-md-3"><div class="summary-card text-success">Refunded <h4 id="refundedCount">0</h4></div></div>
</div>

<!-- FILTER -->
<div class="card mb-3">
<div class="card-body row g-3">
    <div class="col-md-4">
        <input type="text" id="searchInput" class="form-control" placeholder="Search Order / SKU">
    </div>
    <div class="col-md-3">
        <select id="statusFilter" class="form-select">
            <option value="">All</option>
            <option value="requested">Requested</option>
            <option value="approved">Approved</option>
            <option value="refunded">Refunded</option>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-dark w-100" onclick="applyFilter()">Filter</button>
    </div>
    <div class="col-md-3">
        <button class="btn btn-danger w-100" onclick="refreshReturns()">Refresh</button>
    </div>
</div>
</div>

<!-- GRID -->
<div class="row g-3" id="returnGrid"></div>

<!-- PAGINATION -->
<div class="d-flex justify-content-between mt-3">
    <button class="btn btn-sm btn-secondary" onclick="prevPage()">Prev</button>
    <span>Page: <span id="currentPage">1</span></span>
    <button class="btn btn-sm btn-secondary" onclick="nextPage()">Next</button>
</div>

</div>
@endsection

@push('scripts')
<script>

let returnsData = [];
let filteredData = [];
let currentPage = 1;
let perPage = 9;

// LOAD
function loadReturns() {
    fetch('{{ route("shopify.returns.shopify") }}')
        .then(res => res.json())
        .then(data => {
            returnsData = data;
            filteredData = [...data];
            renderCards();
        });
}

// RENDER CARDS
function renderCards() {

    let start = (currentPage - 1) * perPage;
    let paginated = filteredData.slice(start, start + perPage);

    let html = '';
    let requested = 0, approved = 0, refunded = 0;

    filteredData.forEach(i => {
        if(i.status==='requested') requested++;
        if(i.status==='approved') approved++;
        if(i.status==='refunded') refunded++;
    });

    paginated.forEach(item => {
        html += `
        <div class="col-md-6 col-lg-4">
            <div class="return-card">

                <div class="return-header">
                    <img src="${item.image || 'https://via.placeholder.com/60'}" class="return-img">
                    <div>
                        <div class="return-title">${item.product_name || 'Product'}</div>
                        <div class="return-meta">Order: ${item.order_id}</div>
                        <div class="return-meta">SKU: ${item.sku || '-'}</div>
                    </div>
                </div>

                <div class="return-grid">
                    <div>
                        <small>Refund</small>
                        <div>$${item.refund_amount || 0}</div>
                    </div>
                    <div>
                        <small>Date</small>
                        <div>${formatDate(item.created_at)}</div>
                    </div>
                    <div>
                        <small>Status</small>
                        <div>
                            <span class="badge-status ${item.status}">
                                ${item.status}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button class="btn btn-sm btn-dark">View</button>
                </div>

            </div>
        </div>`;
    });

    document.getElementById('returnGrid').innerHTML = html;

    document.getElementById('totalCount').innerText = filteredData.length;
    document.getElementById('requestedCount').innerText = requested;
    document.getElementById('approvedCount').innerText = approved;
    document.getElementById('refundedCount').innerText = refunded;

    document.getElementById('currentPage').innerText = currentPage;
}

// FILTER
function applyFilter() {
    let search = document.getElementById('searchInput').value.toLowerCase();
    let status = document.getElementById('statusFilter').value;

    filteredData = returnsData.filter(item => {
        return (
            (!search || item.order_id.toLowerCase().includes(search)) &&
            (!status || item.status === status)
        );
    });

    currentPage = 1;
    renderCards();
}

// PAGINATION
function nextPage(){
    if(currentPage * perPage < filteredData.length){
        currentPage++;
        renderCards();
    }
}
function prevPage(){
    if(currentPage>1){
        currentPage--;
        renderCards();
    }
}

// DATE
function formatDate(date){
    return new Date(date).toLocaleDateString();
}

// REFRESH
function refreshReturns(){
    loadReturns();
}

// INIT
loadReturns();

</script>
@endpush