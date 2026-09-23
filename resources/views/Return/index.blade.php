@extends('layouts.app')

@section('content')

<style nonce="{{ $cspNonce??'' }}">

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .summary-card {
        background: #FFFFFF;
        border-radius: 10px;
        padding: 10px 14px;
        border: 1px solid #E5E7EB;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    }

    .summary-label {
        color: #6D7175;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .summary-value {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }

    .filter-card {
        background: #fff;
        border: 0;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        margin-bottom: 20px;
    }


    .returns-tabs {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 16px;
        display: inline-flex;
        gap: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        padding: 6px;
    }

    .returns-tab {
        border: 0;
        background: transparent;
        color: #64748b;
        padding: 10px 12px;
    }

    .returns-tab.active {
        background: #2563eb;
        color: #fff;
    }

    .return-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
        transition: transform .18s ease, box-shadow .18s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .return-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 38px rgba(15, 23, 42, .10);
    }

    .return-header {
        display: flex;
        gap: 14px;
        align-items: center;
        margin-bottom: 14px;
    }

    .return-img {
        width: 72px;
        height: 72px;
        border-radius: 12px;
        object-fit: cover;
        background: #f1f5f9;
        border: 1px solid #eef2f7;
    }

    .return-title {
        font-weight: 700;
        color: #111827;
        line-height: 1.25;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .return-meta {
        font-size: 12px;
        color: #6D7175;
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
        border-radius: 12px;
        padding: 10px 12px;
        text-align: center;
        min-height: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .return-info-box small {
        display: block;
        color: #6b7280;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .return-info-box div {
        font-size: 14px;
        font-weight: 700;
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
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        margin-top: 18px;
    }

    .page-btn {
        border-radius: 10px;
        padding: 8px 16px;
        font-weight: 700;
    }

    /* Card hover subtle lift */
    .return-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(15,23,42,0.10);
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .return-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 576px) {
        .return-grid { grid-template-columns: 1fr; }
        .return-img { width: 64px; height: 64px; }
        .return-title { font-size: 14px; }
        .summary-grid { grid-template-columns: 1fr; }
    }

    @media(max-width: 992px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width: 576px) {

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

        .returns-tab {
            flex: 1;
        }
    }
</style>

<div class="container-fluid py-3 px-3 saas-wrapper">

    <div class="saas-page-header">
        <h5 class="fw-bold mb-1">Returns & Refunds</h5>
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

    <div class="returns-tabs">
        <button class="returns-tab active" id="shopifyTabBtn" onclick="switchTab('shopify')">
            Shopify
        </button>

        <button class="returns-tab" id="amazonTabBtn" onclick="switchTab('amazon')">
            Amazon
        </button>
    </div>
    <div class="card filter-card">
        <div class="card-body">
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
                    <button class="btn btn-primary w-100 fw-bold" onclick="applyFilter()">
                        Filter
                    </button>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-light border w-100 fw-bold"  onclick="resetFilter()">
                        Reset
                    </button>
                </div>

                <div class="col-md-1">
                    <button class="btn btn-danger w-100 fw-bold" onclick="refreshReturns()">
                        ⟳
                    </button>
                </div>
            </div>
        </div>
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
<script nonce="{{ $cspNonce??'' }}">
    let returnsData = [];
    let filteredData = [];
    let activeTab = 'shopify';
    let currentPage = 1;
    let perPage = 9;

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function sanitizeImageUrl(url, fallback = 'https://via.placeholder.com/80') {
        if (!url || typeof url !== 'string') return fallback;
        const trimmed = url.trim();
        if (/^(https?:\/\/|\/|\.\/)/i.test(trimmed) && !/^(javascript|vbscript|data):/i.test(trimmed)) {
            return escapeHtml(trimmed);
        }
        return fallback;
    }

    function getViewUrl(item) {
        let shopifyUrl = '{{ route("shopify.returns.view.shopify", ":id") }}';
        let amazonUrl = '{{ route("shopify.returns.view.amazon", ":id") }}';
        let rawId = String(item?.oid ?? item?.id ?? '');
        let encodedId = encodeURIComponent(rawId);

        return activeTab === 'amazon'
            ? amazonUrl.replace(':id', encodedId)
            : shopifyUrl.replace(':id', encodedId);
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
        let isAmazonConnected = {{ !empty($shop->amazon_refresh_token) ? 'true' : 'false' }};

        let html = '';
        let requested = 0, approved = 0, refunded = 0;

        filteredData.forEach(i => {
            if (i.status === 'requested') requested++;
            if (i.status === 'approved') approved++;
            if (i.status === 'refunded') refunded++;
        });

        if (!isAmazonConnected && activeTab === 'amazon') {
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
            const safeImage = sanitizeImageUrl(item.image, 'https://via.placeholder.com/80');
            const safeName = escapeHtml(item.product_name || 'Product');
            const safeOrderId = escapeHtml(item.order_id || '-');
            const safeSku = escapeHtml(item.sku || '-');
            const safeRefund = escapeHtml(item.refund_amount || 0);
            const safeDate = escapeHtml(formatDate(item.created_at));
            const safeStatus = escapeHtml(item.status || 'requested');
            const viewUrl = escapeHtml(getViewUrl(item));

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="return-card">

                        <div class="return-header">
                            <img src="${safeImage}" class="return-img" alt="${safeName}">
                            <div>
                                <div class="return-title">${safeName}</div>
                                <div class="return-meta">Order: ${safeOrderId}</div>
                                <div class="return-meta">SKU: ${safeSku}</div>
                            </div>
                        </div>

                        <div class="return-grid">
                            <div class="return-info-box">
                                <small>Refund</small>
                                <div>$${safeRefund}</div>
                            </div>

                            <div class="return-info-box">
                                <small>Date</small>
                                <div>${safeDate}</div>
                            </div>

                            <div class="return-info-box">
                                <small>Status</small>
                                <div>
                                    <span class="badge-status ${safeStatus}">
                                        ${safeStatus}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button class="btn btn-dark btn-sm fw-bold"
                                    style="border-radius:10px;"
                                    onclick="window.location.href='${viewUrl}'">
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