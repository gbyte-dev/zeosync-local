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
    .table-responsive { position: relative; }

    .table-loader {
        position: absolute;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.85);
        z-index: 3;
        border-radius: 6px;
    }

    .table-loader .spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 4px solid rgba(0,0,0,0.08);
        border-top-color: #2563eb;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 12px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }
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

    <div class="card mt-3">
        <div class="card-body">
            <div class="table-responsive">
                <div id="returnsLoader" class="table-loader">
                    <div class="spinner" aria-hidden="true"></div>
                    <div class="fw-semibold">Loading returns…</div>
                </div>
                <table id="returnsTable" class="table table-hover table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:56px"></th>
                            <th>Detail</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="returnsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script nonce="{{ $cspNonce??'' }}">
    let returnsData = [];
    let filteredData = [];
    let activeTab = 'shopify';
    let perPage = 9;
    let returnsTable = null;

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

        let url = type === 'amazon'
            ? '{{ route("shopify.returns.amazon") }}'
            : '{{ route("shopify.returns.shopify") }}';

        // show loader
        try { document.getElementById('returnsLoader').style.display = 'flex'; } catch (e) {}

        fetch(url)
            .then(res => res.json())
            .then(data => {
                returnsData = Array.isArray(data) ? data : [];
                filteredData = [...returnsData];
                try { document.getElementById('returnsLoader').style.display = 'none'; } catch (e) {}
                renderTable();
            })
            .catch(() => {
                returnsData = [];
                filteredData = [];
                try { document.getElementById('returnsLoader').style.display = 'none'; } catch (e) {}
                renderTable();
            });
    }

    function renderTable() {
        let isAmazonConnected = {{ !empty($shop->amazon_refresh_token) ? 'true' : 'false' }};

        let requested = 0, approved = 0, refunded = 0;
        filteredData.forEach(i => {
            if (i.status === 'requested') requested++;
            if (i.status === 'approved') approved++;
            if (i.status === 'refunded') refunded++;
        });

        // Update summaries
        document.getElementById('totalCount').innerText = filteredData.length;
        document.getElementById('requestedCount').innerText = requested;
        document.getElementById('approvedCount').innerText = approved;
        document.getElementById('refundedCount').innerText = refunded;

        // Handle no data / not connected message
        if (!isAmazonConnected && activeTab === 'amazon') {
            const body = document.getElementById('returnsTableBody');
            body.innerHTML = `<tr><td colspan="7"><div class="alert alert-warning mb-0">Please connect your Amazon account to view returns.</div></td></tr>`;
            if (returnsTable) { returnsTable.clear().draw(); }
            return;
        }

        if (!Array.isArray(filteredData) || filteredData.length === 0) {
            const body = document.getElementById('returnsTableBody');
            body.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No returns found</td></tr>`;
            if (returnsTable) { returnsTable.clear().draw(); }
            return;
        }

        // Build rows
        let rows = '';
        filteredData.forEach(item => {
            const safeImage = sanitizeImageUrl(item.image, 'https://via.placeholder.com/80');
            const detailHtml = item.product_name ?
                `<div class="fw-semibold">${escapeHtml(item.product_name)}</div><div class="text-muted small">SKU: ${escapeHtml(item.sku || '-')}</div>` :
                `<div class="fw-semibold">Order-level refund</div><div class="text-muted small">Order: ${escapeHtml(item.order_id || '-')}</div>`;
            const type = (item.type === 'manual') ? 'Manual' : (item.type === 'product' ? 'Product' : 'Order');
            const amount = Number(item.refund_amount || 0).toFixed(2);
            const date = escapeHtml(formatDate(item.created_at));
            const status = escapeHtml(item.status || 'requested');
            const viewUrl = escapeHtml(getViewUrl(item));

            rows += `
                <tr>
                    <td><img src="${safeImage}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eef2f7"></td>
                    <td>${detailHtml}</td>
                    <td>${type}</td>
                    <td class="text-end">${amount} ${escapeHtml(item.currency || '')}</td>
                    <td class="text-end">${date}</td>
                    <td><span class="badge-status ${status}">${status}</td>
                    <td class="text-end"><a href="${viewUrl}" class="btn btn-sm btn-primary">View</a></td>
                </tr>`;
        });

        document.getElementById('returnsTableBody').innerHTML = rows;

        // Initialize or refresh DataTable
        if (returnsTable) {
            try { returnsTable.destroy(); } catch (e) {}
            document.getElementById('returnsTable').querySelector('tbody').style.display = '';
        }

        returnsTable = $('#returnsTable').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: perPage,
            lengthMenu: [[9, 25, 50], [9, 25, 50]],
            order: [[4, 'desc']],
            columnDefs: [
                { orderable: false, targets: [0,6] },
                { className: 'text-end', targets: [3,4,6] }
            ],
        });
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

        renderTable();
    }

    function resetFilter() {
        filteredData = [...returnsData];
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        renderTable();
    }

    // DataTable provides pagination controls; next/prev not used.

    function formatDate(date) {
        if (!date) return '-';
        return new Date(date).toLocaleDateString();
    }

    function refreshReturns() { loadReturns(activeTab); }

    function switchTab(tab) {
        document.getElementById('shopifyTabBtn').classList.remove('active');
        document.getElementById('amazonTabBtn').classList.remove('active');
        document.getElementById(tab + 'TabBtn').classList.add('active');

        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';

        loadReturns(tab);
    }

    document.addEventListener('DOMContentLoaded', () => { loadReturns('shopify'); });
</script>
@endpush