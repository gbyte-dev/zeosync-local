@extends('layouts.app')

@section('content')

<style>
    body {
        background: #f5f7fb;
    }

    .returns-page {
        
        padding: 24px;
    }

    .returns-hero {
        background: linear-gradient(135deg, #111827, #2563eb);
        color: #fff;
        border-radius: 22px;
        padding: 30px;
        margin-bottom: 24px;
        box-shadow: 0 18px 40px rgba(37, 99, 235, .18);
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .summary-card {
        background: #fff;
        border-radius: 20px;
        padding: 22px;
        border: 1px solid #eef2f7;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .08);
    }

    .summary-label {
        color: #6b7280;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .summary-value {
        font-size: 30px;
        font-weight: 800;
        color: #111827;
    }

    .filter-card {
        background: #fff;
        border: 0;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        margin-bottom: 20px;
    }

    .form-control,
    .form-select {
        border-radius: 12px;
        border: 1px solid #dbe3ef;
        padding: 12px 14px;
    }

    .returns-tabs {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 16px;
        padding: 8px;
        display: inline-flex;
        gap: 8px;
        margin-bottom: 24px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    .returns-tab {
        border: 0;
        background: transparent;
        color: #64748b;
        padding: 10px 22px;
        border-radius: 12px;
        font-weight: 800;
    }

    .returns-tab.active {
        background: #2563eb;
        color: #fff;
    }

    .return-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 22px;
        padding: 20px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        transition: .2s;
        height: 100%;
    }

    .return-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 38px rgba(15, 23, 42, .10);
    }

    .return-header {
        display: flex;
        gap: 14px;
        align-items: center;
        margin-bottom: 18px;
    }

    .return-img {
        width: 68px;
        height: 68px;
        border-radius: 16px;
        object-fit: cover;
        background: #f1f5f9;
    }

    .return-title {
        font-weight: 800;
        color: #111827;
        line-height: 1.35;
    }

    .return-meta {
        font-size: 13px;
        color: #6b7280;
        margin-top: 2px;
    }

    .return-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 12px;
    }

    .return-info-box {
        background: #f8fafc;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        padding: 12px;
        text-align: center;
    }

    .return-info-box small {
        display: block;
        color: #6b7280;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .return-info-box div {
        font-size: 13px;
        font-weight: 800;
        color: #111827;
    }

    .badge-status {
        border-radius: 999px;
        padding: 7px 10px;
        font-size: 11px;
        font-weight: 800;
        text-transform: capitalize;
    }

    .requested {
        background: #fef3c7;
        color: #92400e;
    }

    .approved {
        background: #dbeafe;
        color: #1e40af;
    }

    .refunded {
        background: #d1fae5;
        color: #065f46;
    }

    .pagination-box {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 18px;
        padding: 16px 20px;
        margin-top: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    .page-btn {
        border-radius: 12px;
        padding: 9px 18px;
        font-weight: 800;
    }

    @media(max-width: 992px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width: 576px) {
        .returns-page {
            padding: 14px;
        }

        .returns-hero {
            padding: 22px;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }

        .return-grid {
            grid-template-columns: 1fr;
        }

        .pagination-box {
            flex-direction: column;
            gap: 12px;
        }

        .returns-tabs {
            width: 100%;
        }

        .returns-tab {
            flex: 1;
        }
    }
</style>

<div class="returns-page">

    <div class="returns-hero">
        <!-- <span class="badge bg-light text-primary mb-3 px-3 py-2">
            Returns Center
        </span> -->

        <h3 class="fw-bold mb-1">Returns & Refunds</h3>

        <p class="mb-0 opacity-75">
            Track Shopify and Amazon returns, approvals and refund status
        </p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Returns</div>
            <div class="summary-value" id="totalCount">0</div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Requested</div>
            <div class="summary-value text-warning" id="requestedCount">0</div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Approved</div>
            <div class="summary-value text-info" id="approvedCount">0</div>
        </div>

        <div class="summary-card">
            <div class="summary-label">Refunded</div>
            <div class="summary-value text-success" id="refundedCount">0</div>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">Search</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search Order / SKU">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="requested">Requested</option>
                        <option value="approved">Approved</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary w-100 fw-bold" style="border-radius:12px;padding:12px;" onclick="applyFilter()">
                        Filter
                    </button>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-light border w-100 fw-bold" style="border-radius:12px;padding:12px;" onclick="resetFilter()">
                        Reset
                    </button>
                </div>

                <div class="col-md-1">
                    <button class="btn btn-danger w-100 fw-bold" style="border-radius:12px;padding:12px;" onclick="refreshReturns()">
                        ⟳
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="returns-tabs">
        <button class="returns-tab active" id="shopifyTabBtn" onclick="switchTab('shopify')">
            Shopify
        </button>

        <button class="returns-tab" id="amazonTabBtn" onclick="switchTab('amazon')">
            Amazon
        </button>
    </div>

    <div class="row g-4" id="returnGrid"></div>

    <div class="pagination-box">
        <button class="btn btn-light border page-btn" onclick="prevPage()">
            ← Prev
        </button>

        <span class="fw-bold text-muted">
            Page <span id="currentPage">1</span>
        </span>

        <button class="btn btn-primary page-btn" onclick="nextPage()">
            Next →
        </button>
    </div>

</div>

@endsection

@push('scripts')
<script>
    let returnsData = [];
    let filteredData = [];
    let activeTab = 'shopify';
    let currentPage = 1;
    let perPage = 9;

    function getViewUrl(item) {
        let shopifyUrl = '{{ route("shopify.returns.view.shopify", ":id") }}';
        let amazonUrl = '{{ route("shopify.returns.view.amazon", ":id") }}';

        return activeTab === 'amazon'
            ? amazonUrl.replace(':id', item.oid)
            : shopifyUrl.replace(':id', item.oid);
    }

    function loadReturns(type = 'shopify') {
        activeTab = type;
        currentPage = 1;

        let url = type === 'amazon'
            ? '{{ route("shopify.returns.amazon") }}'
            : '{{ route("shopify.returns.shopify") }}';

        fetch(url)
            .then(res => res.json())
            .then(data => {
                returnsData = Array.isArray(data) ? data : [];
                filteredData = [...returnsData];
                renderCards();
            })
            .catch(() => {
                returnsData = [];
                filteredData = [];
                renderCards();
            });
    }

    function renderCards() {
        let start = (currentPage - 1) * perPage;
        let paginated = filteredData.slice(start, start + perPage);
        let token = '{{ $shop->amazon_access_token ?? "" }}';

        let html = '';
        let requested = 0, approved = 0, refunded = 0;

        filteredData.forEach(i => {
            if (i.status === 'requested') requested++;
            if (i.status === 'approved') approved++;
            if (i.status === 'refunded') refunded++;
        });

        if ((token === null || token === '') && activeTab === 'amazon') {
            html = `
                <div class="col-12">
                    <div class="alert alert-warning rounded-4 p-4">
                        Please connect your Amazon account to view returns.
                    </div>
                </div>
            `;
        } else if (paginated.length === 0) {
            html = `
                <div class="col-12">
                    <div class="text-center text-muted py-5 bg-white rounded-4 border">
                        No returns found
                    </div>
                </div>
            `;
        }

        paginated.forEach(item => {
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="return-card">

                        <div class="return-header">
                            <img src="${item.image || 'https://via.placeholder.com/80'}" class="return-img">
                            <div>
                                <div class="return-title">${item.product_name || 'Product'}</div>
                                <div class="return-meta">Order: ${item.order_id || '-'}</div>
                                <div class="return-meta">SKU: ${item.sku || '-'}</div>
                            </div>
                        </div>

                        <div class="return-grid">
                            <div class="return-info-box">
                                <small>Refund</small>
                                <div>$${item.refund_amount || 0}</div>
                            </div>

                            <div class="return-info-box">
                                <small>Date</small>
                                <div>${formatDate(item.created_at)}</div>
                            </div>

                            <div class="return-info-box">
                                <small>Status</small>
                                <div>
                                    <span class="badge-status ${item.status || 'requested'}">
                                        ${item.status || 'requested'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button class="btn btn-dark btn-sm fw-bold"
                                    style="border-radius:10px;"
                                    onclick="window.location.href='${getViewUrl(item)}'">
                                View
                            </button>
                        </div>

                    </div>
                </div>
            `;
        });

        document.getElementById('returnGrid').innerHTML = html;
        document.getElementById('totalCount').innerText = filteredData.length;
        document.getElementById('requestedCount').innerText = requested;
        document.getElementById('approvedCount').innerText = approved;
        document.getElementById('refundedCount').innerText = refunded;
        document.getElementById('currentPage').innerText = currentPage;
    }

    function applyFilter() {
        let search = document.getElementById('searchInput').value.toLowerCase();
        let status = document.getElementById('statusFilter').value;

        filteredData = returnsData.filter(item => {
            let orderId = String(item.order_id || '').toLowerCase();
            let sku = String(item.sku || '').toLowerCase();

            return (
                (!search || orderId.includes(search) || sku.includes(search)) &&
                (!status || item.status === status)
            );
        });

        currentPage = 1;
        renderCards();
    }

    function resetFilter() {
        filteredData = [...returnsData];
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        currentPage = 1;
        renderCards();
    }

    function nextPage() {
        if (currentPage * perPage < filteredData.length) {
            currentPage++;
            renderCards();
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            renderCards();
        }
    }

    function formatDate(date) {
        if (!date) return '-';
        return new Date(date).toLocaleDateString();
    }

    function refreshReturns() {
        loadReturns(activeTab);
    }

    function switchTab(tab) {
        document.getElementById('shopifyTabBtn').classList.remove('active');
        document.getElementById('amazonTabBtn').classList.remove('active');
        document.getElementById(tab + 'TabBtn').classList.add('active');

        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';

        loadReturns(tab);
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadReturns('shopify');
    });
</script>
@endpush