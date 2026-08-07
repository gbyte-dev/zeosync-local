@extends('layouts.app')
@section('content')


@push('css')
<!-- DataTables Bootstrap 5 CSS -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
@endpush

<style>
    /* Shopify Admin Inspired UI - Ultra Compact & Premium */
    .sp-page {
        background-color: #F6F6F7;
        padding: 20px;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
    }

    /* Typography */
    .sp-title {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
        letter-spacing: -0.02em;
        margin: 0 0 2px 0;
        line-height: 1.2;
    }

    .sp-subtitle {
        font-size: 13px;
        margin: 0;
    }

    /* Layout Spacing */
    .sp-header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        min-height: 60px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .sp-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    in-iframe .sp-card-grid {
        grid-template-columns: repeat(4, minmax(180px, 1fr));
    }
    /* Buttons */
    .sp-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .sp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 34px;
        padding: 0 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        cursor: pointer;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid transparent;
        box-sizing: border-box;
    }

    .sp-btn-sm {
        height: 28px;
        padding: 0 8px;
        border-radius: 6px;
        font-size: 12px;
    }

    .sp-btn i {
        font-size: 14px;
        display: flex;
        align-items: center;
    }

    .sp-btn-primary {
        background-color: #111827;
        color: #FFFFFF;
        border-color: #111827;
    }

    .sp-btn-primary:hover {
        background-color: #374151;
        color: #FFFFFF;
    }

    .sp-btn-secondary {
        background-color: #FFFFFF;
        color: #111827;
        border-color: #E5E7EB;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-btn-secondary:hover {
        background-color: #F9FAFB;
        border-color: #D1D5DB;
    }

    .sp-btn-danger {
        background-color: transparent;
        color: #DC2626;
        border-color: #DC2626;
    }

    .sp-btn-danger:hover {
        background-color: #FEF2F2;
    }

    .sp-btn-success {
        background-color: #16A34A;
        color: #FFFFFF;
        border-color: #16A34A;
    }

    .sp-btn-success:hover {
        background-color: #15803d;
    }

    .sp-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Minimal Stat Pills */
    .sp-stat-pill {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 8px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 38px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-stat-label {
        font-size: 11px;
        font-weight: 500;
        color: #4B5563;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin: 0;
    }

    .sp-stat-value {
        font-size: 15px;
        font-weight: 700;
        margin: 0;
    }

    .val-default {
        color: #111827;
    }

    .val-success {
        color: #16A34A;
    }

    .val-warning {
        color: #D97706;
    }

    .val-danger {
        color: #DC2626;
    }

    /* Table Container */
    .sp-table-wrapper {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .sp-table th {
        background-color: #F6F6F7;
        padding: 0 12px;
        height: 42px;
        font-size: 13px;
        font-weight: 500;
        border-bottom: 1px solid #E5E7EB;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
        vertical-align: middle;
    }

    .sp-table td {
        padding: 0px 12px;
        font-size: 13px;
        color: #111827;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: middle;
        height: 10px;
    }

    .sp-table tbody tr:hover td {
        background-color: #F9FAFB;
    }

    .sp-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Table Elements */
    .sp-product-img {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
        object-fit: cover;
    }

    .sp-text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
        display: block;
    }

    .sp-fw-600 {
        font-weight: 600;
    }

    .sp-text-muted {
        font-size: 12px;
    }

    /* Badges */
    .sp-badge {
        display: inline-flex;
        align-items: center;
        height: 22px;
        padding: 0 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 500;
        line-height: 1;
    }

    .sp-badge-success {
        background-color: #F0FDF4;
        color: #16A34A;
        border: 1px solid #BBF7D0;
    }

    .sp-badge-secondary {
        background-color: #F6F6F7;
        border: 1px solid #E5E7EB;
    }

    /* DataTables Integration Styling Overrides (Shopify Theme Match) */
    .dataTables_wrapper {
        padding: 16px;
    }

    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 13px;
        outline: none;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        margin-left: 8px;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #111827;
        box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.1);
    }

    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 4px 30px 4px 8px;
        font-size: 13px;
        margin: 0 6px;
    }

    .dataTables_wrapper .dataTables_info {
        font-size: 13px;
        color: #6B7280;
        padding-top: 16px !important;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 16px !important;
    }

    .dataTables_wrapper .page-item.active .page-link {
        background-color: #111827;
        border-color: #111827;
        color: #FFFFFF;
    }

    .dataTables_wrapper .page-item.disabled .page-link {
        background-color: #F9FAFB;
        color: #9CA3AF;
        border-color: #E5E7EB;
    }

    .dataTables_wrapper .page-link {
        font-size: 13px;
        color: #111827;
        border: 1px solid #E5E7EB;
        margin: 0 2px;
        border-radius: 6px;
        padding: 4px 10px;
    }

    .dataTables_wrapper .page-link:hover:not(:disabled) {
        background-color: #F9FAFB;
        border-color: #D1D5DB;
    }

    table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
        background-color: #111827;
    }
</style>

<div class="sp-page">

    <!-- Header Section -->
    <div class="sp-header-section">
        <div>
            <h1 class="sp-title">Shopify Products</h1>
            <p class="sp-subtitle">If Products not available Click refresh button</p>
        </div>
        <div class="sp-actions">
            <button id="refreshBtn" class="sp-btn sp-btn-secondary" data-url="{{ route('shopify.products', ['shop' => $activeShop]) }}">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <a href="{{ route('shopify.product.create', ['shop' => request('shop')]) }}" class="sp-btn sp-btn-secondary">
                <i class="bi bi-plus-lg"></i> Add to Shopify
            </a>
            @if(!$productLimitReached)
            <a href="{{ route('user.addProductCategory', ['shop' => request('shop')]) }}" class="sp-btn sp-btn-primary">
                <i class="bi bi-send"></i> Add To Amazon
            </a>
            @else
            <a href="javascript:void(0)" onclick="showProductLimitAlert()" class="sp-btn sp-btn-primary">
                <i class="bi bi-send"></i> Add To Amazon
            </a>
            @endif
        </div>
    </div>
    

    <!-- Stat Pills -->
    <div class="sp-card-grid">
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Total Products</span>
            <span class="sp-stat-value val-default">{{ $totalProducts }}</span>
        </div>
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Active</span>
            <span class="sp-stat-value val-success">{{ collect($products)->where('status', 'active')->count() }}</span>
        </div>
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Draft</span>
            <span class="sp-stat-value val-warning">{{ collect($products)->where('status', 'draft')->count() }}</span>
        </div>
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Out of Stock</span>
            <span class="sp-stat-value val-danger">
                {{ $outOfStockProducts }}
            </span>
        </div>
    </div>

    <!-- Table Section -->
    <div class="sp-table-wrapper">
        @if(count($products) > 0)
        <div class="table-responsive">
            <table class="sp-table table" id="productsTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 48px;">Image</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Inventory</th>
                        <th>Category</th>
                        <th style="width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    @foreach($products as $product)
                    @php
                    $firstImage = data_get($product, 'image.src')
                    ?? data_get($product, 'images.0.src')
                    ?? asset('b6.png');
                    $status = $product['status'] ?? 'draft';
                    $category = $product['product_type'] ?? 'Uncategorized';
                    $inventory = collect($product['variants'] ?? [])->sum(fn($v) => $v['inventory_quantity'] ?? 0);
                    $price = $product['variants'][0]['price'] ?? 0;
                    @endphp
                    <tr data-product-id="{{ $product['id'] }}">
                        <td>
                            <img src="{{ $firstImage }}" alt="{{ $product['title'] }}" class="sp-product-img">
                        </td>
                        <td>
                            <span class="sp-fw-600 sp-text-truncate" title="{{ $product['title'] }}">{{ $product['title'] }}</span>
                            <span class="sp-text-muted sp-text-truncate" title="{{ $product['vendor'] ?? 'N/A' }}">Vendor: {{ $product['vendor'] ?? 'N/A' }} • ₹{{ $price }}</span>
                        </td>
                        <td>
                            <span class="sp-badge sp-badge-{{ $status === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>
                        <td>
                            <div class="sp-fw-600 {{ $inventory > 0 ? 'text-success' : 'text-danger' }}">{{ $inventory }}</div>
                            <div class="sp-text-muted">units</div>
                        </td>
                        <td style="color: #4B5563;">{{ $category }}</td>
                        <td>
                            @if($product->needs_resync)
                            <div style="margin-bottom: 4px;">
                                <span class="text-warning" style="font-size: 11px; font-weight: 500;">
                                    Needs resync
                                </span>
                            </div>
                            @endif

                            <div class="sp-actions" style="gap: 4px;">

                                @if((int)$product->synced_to_amazon === 1 && (int)$product->needs_resync === 0)

                                <button class="sp-btn sp-btn-sm sp-btn-success" disabled>
                                    <i class="bi bi-check-lg"></i>
                                    <span class="d-none d-md-inline">Synced</span>
                                </button>

                                @elseif((int)$product->needs_resync === 1)

                                <button type="button"
                                    class="sp-btn sp-btn-sm sp-btn-secondary btn-sync"
                                    data-url="{{ route('shopify.sync.amazon', ['id' => $product->id, 'shop' => request('shop')]) }}"
                                    title="Resync">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    <span class="d-none d-md-inline">Resync</span>
                                </button>

                                @else

                                @if(!checkIsProductSynced($product->shopify_id,'shopify'))

                                @php
                                if($product->amazon_product_id){
                                $routeurl = route('admin.product.productEdit', [
                                'product' => $product->amazon_product_id,
                                'shop' => session('active_shop'),
                                ]);
                                }else{
                                $routeurl = route('user.product.syncShopifyToAmazon', [
                                'id' => $product->shopify_id,
                                'shop' => session('active_shop')
                                ]);
                                }
                                @endphp

                                <a href="{{ $routeurl }}"
                                    class="sp-btn sp-btn-sm sp-btn-secondary"
                                    title="Sync">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <span class="d-none d-md-inline">Sync</span>
                                </a>

                                @else

                                @php
                                $pid = checkIsProductSynced($product->shopify_id,'shopify');
                                @endphp

                                <a href="javascript:void(0)"
                                    class="sp-btn sp-btn-sm sp-btn-secondary"
                                    title="Mapped to Amazon by SKU {{ $pid }}">
                                    <i class="bi bi-link-45deg"></i>
                                    <span class="d-none d-md-inline">Mapped</span>
                                </a>

                                @endif

                                @endif

                                {{-- View (Always Visible) --}}
                                <a href="{{ route('shopify.product.view', ['id' => $product->shopify_id, 'shop' => request('shop')]) }}"
                                    class="sp-btn sp-btn-sm sp-btn-secondary"
                                    title="View">
                                    <i class="bi bi-eye"></i>
                                </a>

                                {{-- Edit (Always Visible) --}}
                                <button type="button"
                                    class="sp-btn sp-btn-sm sp-btn-secondary btn-edit"
                                    title="Edit"
                                    data-id="{{ $product->id }}"
                                    data-route="{{ route('shopify.product.edit', ['id' => $product->shopify_id, 'shop' => request('shop')]) }}">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                {{-- Delete (Always Visible) --}}
                                <button type="button"
                                    class="sp-btn sp-btn-sm sp-btn-danger btn-delete"
                                    title="Delete"
                                    data-id="{{ $product->id }}"
                                    data-route="{{ route('shopify.product.delete', ['id' => $product->shopify_id, 'shop' => request('shop')]) }}">
                                    <i class="bi bi-trash"></i>
                                </button>

                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <!-- Rendered safely outside the table tag to prevent DataTables "_DT_CellIndex" errors -->
        <div style="text-align: center; padding: 60px 12px;">
            <div style="display: flex; flex-direction: column; align-items: center; ">
                <div style="background: #F3F4F6; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                    <i class="bi bi-box-seam" style="font-size: 18px; color: #9CA3AF;"></i>
                </div>
                <span style="font-size: 13px; font-weight: 500; color: #111827;">No products found</span>
                <span style="font-size: 12px; margin-top: 2px;">Try refreshing or adding a new product.</span>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<!-- Maintain FontAwesome reference for JS injections that might strictly rely on it -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endpush

@push('scripts')
<!-- DataTables JS & Bootstrap 5 Integration -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        const $table = $('#productsTable');

        // Initialize DataTables safely only if the table is rendered in the DOM
        if ($table.length > 0) {
            // Destroy any existing initialization to prevent multiple initialization errors
            if ($.fn.DataTable.isDataTable('#productsTable')) {
                $table.DataTable().destroy();
            }

            $table.DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                stateSave: true,
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                order: [
                    [1, 'asc']
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search products..."
                }
            });
        }
        // const refreshBtn = document.getElementById('refreshBtn');
        // if (refreshBtn) {
        //     refreshBtn.click();
        // }
    });

    document.addEventListener('click', function(e) {
        // ✅ EDIT BUTTON (Safely delegated, works after DT pagination)
        const editBtn = e.target.closest('.btn-edit');
        if (editBtn) {
            const url = editBtn.getAttribute('data-route');
            if (url) {
                window.location.href = url;
            }
            return;
        }

        // ✅ DELETE BUTTON
        const deleteBtn = e.target.closest('.btn-delete');
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            const url = deleteBtn.getAttribute('data-route');
            deleteProduct(id, url, deleteBtn);
            return;
        }
    });

    function showProductLimitAlert() {
        Swal.fire({
            icon: 'warning',
            title: 'Product Limit Reached',
            html: `
            <p>You have already added <b>{{ $productUsed }}</b> out of <b>{{ $productLimit }}</b> products for your current billing cycle.</p>
            <p>Please upgrade your plan to continue adding more products.</p>
        `,
            confirmButtonText: 'OK'
        });
    }

    function deleteProduct(id, url, deleteBtn) {
        if (!url) {
            alert('Delete route not found');
            return;
        }
        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }
        deleteBtn.disabled = true;
        const oldText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        showLoader('Deleting product...');
        // FORCE UI REPAINT
        requestAnimationFrame(() => {
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    hideLoader();
                    if (data.success) {
                        deleteBtn.innerHTML = '<i class="fas fa-check"></i>';
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = oldText;
                        showToast(data.message, 'danger');;
                    }
                })
                .catch(error => {
                    hideLoader();
                    console.error(error);
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = oldText;
                    showToast('Delete failed', 'danger');
                });
        });
    }

    document.getElementById('refreshBtn').addEventListener('click', function() {
        const btn = this;
        const icon = btn.querySelector('i');
        btn.disabled = true;
        icon.classList.add('fa-spin');
        let url = btn.dataset.url;
        if (url.includes('?')) {
            url += '&refresh=1';
        } else {
            url += '?refresh=1';
        }
        fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                console.log('REFRESH DONE');
                location.reload();
            })
            .catch(err => {
                console.error(err);
                alert('Refresh failed');
            })
            .finally(() => {
                btn.disabled = false;
                icon.classList.remove('fa-spin');
            });
    });

    document.addEventListener('click', function(e) {
        const syncBtn = e.target.closest('.btn-sync');
        if (!syncBtn) return;
        const url = syncBtn.getAttribute('data-url');
        if (!url) {
            console.error('Sync URL missing');
            return;
        }
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="ms-1 d-none d-md-inline">Syncing...</span>';

        fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                if (data.success) {
                    syncBtn.classList.remove('sp-btn-secondary', 'sp-btn-warning');
                    syncBtn.classList.add('sp-btn-success');
                    syncBtn.innerHTML = '<i class="fas fa-check"></i> <span class="ms-1 d-none d-md-inline">Synced</span>';
                    showToast('Amazon sync success', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = '<i class="fas fa-rotate-right"></i> <span class="ms-1 d-none d-md-inline">Resync</span>';
                    showToast(data.message || 'Sync failed', 'danger');
                }
            })
            .catch(error => {
                console.error(error);
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fas fa-rotate-right"></i> <span class="ms-1 d-none d-md-inline">Resync</span>';
                showToast('Sync failed', 'danger');
            });
    });
</script>
@endpush