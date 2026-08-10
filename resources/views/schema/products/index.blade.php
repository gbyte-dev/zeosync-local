@extends('layouts.app')
@section('content')

@push('css')
<!-- DataTables Bootstrap 5 CSS -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
@endpush

<style>
    /* Shopify Admin Inspired UI - Ultra Compact & Tight */
    .sp-page {
        background-color: #F6F6F7;
        padding: 16px 20px;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
    }

    /* Typography */
    .sp-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        letter-spacing: -0.01em;
        margin: 0;
        line-height: 1.2;
    }

    /* Layout Spacing */
    .sp-header-section {
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        min-height: 48px;
        flex-wrap: wrap;
        gap: 12px;
    }

    /* Image-Reference Style Stat Pills */
    .sp-stat-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .sp-stat-pill {
        flex: 1;
        min-width: 160px;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 6px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 38px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-stat-label {
        font-size: 11px;
        font-weight: 500;
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

    .sp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 12px;
        border-radius: 6px;
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
        height: 26px;
        padding: 0 8px;
        border-radius: 6px;
        font-size: 12px;
        gap: 4px;
    }

    .sp-btn i {
        font-size: 13px;
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

    .sp-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Table Container */
    .sp-table-wrapper {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
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
        height: 36px;
        font-size: 12px;
        font-weight: 500;
        border-bottom: 1px solid #E5E7EB;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
        vertical-align: middle;
    }

    .sp-table td {
        padding: 6px 12px;
        font-size: 13px;
        color: #111827;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: middle;
        height: 42px;
    }

    .sp-table tbody tr:hover td {
        background-color: #F9FAFB;
    }

    .sp-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Table Elements */
    .sp-product-img {
        width: 28px;
        height: 28px;
        border-radius: 4px;
        border: 1px solid #E5E7EB;
        object-fit: cover;
    }

    .sp-text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
        display: block;
    }

    .sp-fw-600 {
        font-weight: 600;
    }

    .sp-text-muted {
        font-size: 11px;
    }

    /* Badges */
    .sp-badge {
        display: inline-flex;
        align-items: center;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        font-size: 11px;
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
        display: inline-block;
        width: auto;
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
        display: inline-block;
        width: auto;
    }

    .dataTables_wrapper .dataTables_info {
        font-size: 13px;
        color: #6B7280;
        padding-top: 16px !important;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 16px !important;
    }

    .dataTables_paginate .pagination {
        margin: 0;
        gap: 4px;
        justify-content: flex-end;
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
        box-shadow: none !important;
    }

    .dataTables_wrapper .page-link:hover:not(:disabled) {
        background-color: #F9FAFB;
        border-color: #D1D5DB;
    }

    table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
        background-color: #111827;
    }

    table.dataTable.sp-table {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        border-collapse: collapse !important;
    }

      @media (max-width: 768px) {
        .content{
            padding:0px!important;
        }
    }

table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before, table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before{
    background:white;
}
</style>

@if(!checkAmazonConnected())
    <div class="alert alert-warning">
        Please connect your Amazon account first.
        <a href="{{ route('amazon.connect') }}">Connect Amazon</a>
    </div>
@else
<div class="sp-page">

    <!-- Header Section -->
    <div class="sp-header-section">
        <div class="saas-page-header row">
        <div class="col-md-7 col-sm-12">
            <h1 class="sp-title">Amazon Products Under Progress</h1>
        </div>
        <div class="sp-actions col-md-5 col-sm-12">
            <button id="refreshBtn" style="float: right;" class="sp-btn sp-btn-secondary" data-url="{{ route('shopify.products', ['shop' => $activeShop]) }}">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>

            @if(!$productLimitReached)
            <a style="float: right;" href="{{ route('user.addProductCategory', ['shop' => session('active_shop')]) }}" class="sp-btn sp-btn-primary">
                <i class="bi bi-send"></i> Add To Amazon
            </a>
            @else
            <a style="float: right;" href="javascript:void(0)" onclick="showProductLimitAlert()" class="sp-btn sp-btn-primary">
                <i class="bi bi-send"></i> Add To Amazon
            </a>
            @endif
        </div>
        </div>
    </div>

    <!-- Stat Pills (Reference Image Style) -->
    <div class="sp-stat-grid">
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Total Products</span>
            <span class="sp-stat-value val-default">{{ $products->total() }}</span>
        </div>
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Active</span>
            <span class="sp-stat-value val-success">{{ collect($products)->where('status', 'active')->count() }}</span>
        </div>
        <div class="sp-stat-pill">
            <span class="sp-stat-label">Draft</span>
            <span class="sp-stat-value val-warning">{{ collect($products)->where('status', 'draft')->count() }}</span>
        </div>
        <div class="sp-stat-pill" style="display: flex; align-items: center;">
            @if(!$parent_productid)
            <span class="sp-stat-label">Out of Stock</span>
            <span class="sp-stat-value val-danger"></span>
            @else
            <a href="{{route('user.product.showProducts')}}" class="sp-btn sp-btn-sm sp-btn-secondary" style="margin-left: auto; width: 100%;">Back</a>
            @endif
        </div>
    </div>

    <!-- Table Section -->
    <div class="sp-table-wrapper">
        <div class="table-responsive">
            <table class="sp-table table" id="productsTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 40px;">Image</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Inventory</th>
                        <th>Category</th>
                        <th style="width: 280px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <!-- CHANGED: Swapped forelse to foreach to prevent colspan error in DataTables -->
                    @foreach($products as $product)
                    @php
                    if(isset($product->filled_json)){
                        $proddata = json_decode($product->filled_json, true);
                        $item_name = $proddata['item_name'] ?? '';
                        $main_product_image = $proddata['main_product_image_locator'] ?? '';
                        $schema_id = $product->schema_id ?? '';
                        $quantity = $proddata['number_of_items'] ?? 0;
                        $category = $product->schema->product_type ?? 'Uncategorized';
                        $status = $product['status'] ?? 'draft';
                        $manufacturer = $proddata['manufacturer'] ?? '';
                        $price = $proddata['price'] ?? 0;
                        $parentage_level = $proddata['parentage_level']??'';
                    }
                    elseif(isset($product->attributes)){
                        $item_name = optional($product->attributes->firstWhere('attribute_name', 'item_name'))->attribute_value;
                        $main_product_image = optional($product->attributes->firstWhere('attribute_name', 'main_product_image_locator'))->attribute_value;
                        $schema_id = $product->schema_id ?? '';
                        $quantity = optional($product->attributes->firstWhere('attribute_name', 'number_of_items'))->attribute_value ?? 0;
                        $category = $product->schema->product_type ?? 'Uncategorized';
                        $status = $product['status'] ?? 'draft';
                        $manufacturer = optional($product->attributes->firstWhere('attribute_name', 'manufacturer'))->attribute_value ?? '';
                        $price = optional($product->attributes->firstWhere('attribute_name', 'price'))->attribute_value ?? 0;
                        $parentage_level = optional($product->attributes->firstWhere('attribute_name', 'parentage_level'))->attribute_value ?? '';

                    }
                    @endphp
                    <tr data-product-id="{{ $product['id'] }}">
                        <td>
                            <img src="{{ $main_product_image??'' }}" alt="{{ $item_name??'' }}" class="sp-product-img">
                        </td>
                        <td>
                            <span class="sp-fw-600 sp-text-truncate" title="{{ $item_name??'N/A' }}">{{ $item_name??'N/A' }}</span>
                            <span class="sp-text-muted sp-text-truncate" title="{{ $product->sku??'N/A' }}">SKU: {{ $product->sku??'N/A' }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <span class="sp-badge sp-badge-{{ $status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($status) }}
                                </span>
                                @if(!in_array(strtolower($status), ['draft', 'active']))
                                <span class="sp-badge sp-badge-secondary" title="Please check this product on Amazon Seller Dashboard, may be some issue on this product" style="cursor:help; padding:0 4px;">
                                    <i class="bi bi-info-circle" style="font-size:10px;"></i>
                                </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="sp-fw-600 {{ $quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $quantity }}</div>
                            <div class="sp-text-muted">units</div>
                        </td>
                        <td style="color: #4B5563;">{{ $category }}</td>
                        <td>
                            <div class="sp-actions" style="gap: 4px;">
                                @if( strtolower($status) != 'draft')
                                @if($product->parent_id == null)
                                
                                @if(!checkIsProductSynced($product->sku,'amazon'))
                                    @if($parentage_level)
                                    <a href="{{ route('admin.product.product.child', ['product' => $product->id, 'shop' => request('shop')]) }}" class="sp-btn sp-btn-sm sp-btn-secondary" title="Add Variation">
                                        <i class="bi bi-plus-lg"></i>
                                        <span class="d-none d-md-inline">Add Variation</span>
                                    </a>
                                    @endif
                                @endif

                                <!-- <a href="{{ route('user.product.showProducts.child', ['parent_id' => $product->id, 'shop' => request('shop')]) }}" class="sp-btn sp-btn-sm sp-btn-secondary" title="Show Variation">
                                    <i class="bi bi-eye"></i>
                                    <span class="d-none d-md-inline">Show Variation</span>
                                </a> -->


                                <a href="{{ route('user.product.amazonView', ['sku' => $product->sku, 'shop' => request('shop')]) }}" class="sp-btn sp-btn-sm sp-btn-secondary" title="Show Variation">
                                    <i class="bi bi-eye"></i>
                                    <span class="d-none d-md-inline">View</span>
                                </a>

                                

                                @if(!checkIsProductSynced($product->sku,'amazon'))
                                <a href="{{ route('user.product.syncAmazonToShopify', ['sku' => $product->sku, 'shop' => request('shop')]) }}" class="sp-btn sp-btn-sm sp-btn-secondary" title="Add to Shopify">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <span class="d-none d-md-inline">Add to Shopify</span>
                                </a>
                                @else
                                @php $pid = checkIsProductSynced($product->sku,'amazon'); @endphp
                                <a href="https://admin.shopify.com/store/{{str_replace('.myshopify.com','',session('active_shop'))}}/products/{{$pid}}" class="sp-btn sp-btn-sm sp-btn-secondary" target="_blank" title="Product mapped">
                                    <i class="bi bi-link-45deg"></i>
                                    <span class="d-none d-md-inline">Product mapped</span>
                                </a>
                                @endif
                                @else
                                <button type="button" class="sp-btn sp-btn-sm sp-btn-secondary btn-edit" title="View"
                                    data-id="{{ $product->id }}"
                                    data-route="{{ route('admin.product.productEdit', ['product' => $product->id, 'shop' => request('shop')]) }}">
                                    <i class="bi bi-pencil"></i>
                                    <span class="d-none d-md-inline">View</span>
                                </button>
                                @endif
                                @else
                                <button type="button" class="sp-btn sp-btn-sm sp-btn-secondary btn-edit" title="View"
                                    data-id="{{ $product->id }}"
                                    data-route="{{ route('admin.product.productEdit', ['product' => $product->id, 'shop' => request('shop')]) }}">
                                    <i class="bi bi-pencil"></i>
                                    <span class="d-none d-md-inline">View</span>
                                </button>
                                <form method="POST" action="{{ route('user.product.removeDraft', ['product' => $product->id,'shop' => request('shop')]) }}">
                                    @csrf
                                    <button type="submit" class="sp-btn sp-btn-sm sp-btn-danger " title="Remove Draft"
                                        data-id="{{ $product->id }}"
                                        data-route="{{ route('user.product.removeDraft', ['product' => $product->id,'shop' => request('shop')]) }}">
                                        <i class="bi bi-trash"></i>
                                        <span class="d-none d-md-inline">Remove Draft</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <!-- CHANGED: Completely removed custom blade manual pagination div, DataTables handles it -->
    </div>
</div>
@endif
@endsection

@push('styles')
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
        // Initialize DataTables safely once
        if (!$.fn.DataTable.isDataTable('#productsTable')) {
            $('#productsTable').DataTable({
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
                    searchPlaceholder: "Search products...",
                    // CHANGED: Injected your custom empty layout design cleanly into DataTables
                    emptyTable: "<div style='display: flex; flex-direction: column; align-items: center; color: #6B7280; padding: 40px 12px;'><div style='background: #F3F4F6; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;'><i class='bi bi-box-seam' style='font-size: 18px; color: #9CA3AF;'></i></div><span style='font-size: 13px; font-weight: 500; color: #111827;'>No products found</span></div>"
                }
            });
        }
    });

    document.addEventListener('click', function(e) {
        //  EDIT BUTTON (Safely delegated, works after DT pagination)
        const editBtn = e.target.closest('.btn-edit');
        if (editBtn) {
            const url = editBtn.getAttribute('data-route');
            if (url) {
                window.location.href = url;
            }
            return;
        }
        //  DELETE BUTTON
        const deleteBtn = e.target.closest('.btn-delete');
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            const url = deleteBtn.getAttribute('data-route');
            deleteProduct(id, url, deleteBtn);
            return;
        }
    });

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
        deleteBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
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
                        deleteBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = oldText;
                        showToast(data.message, 'danger');
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
            });
    });

    document.addEventListener('click', function(e) {
        const syncBtn =
            e.target.closest('.btn-sync');
        if (!syncBtn) return;
        const url =
            syncBtn.getAttribute('data-url');
        if (!url) {
            console.error('Sync URL missing');
            return;
        }
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

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
                    syncBtn.classList.remove('sp-btn-secondary');
                    syncBtn.classList.add('sp-btn-success');
                    syncBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
                    showToast('Amazon sync success', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                    showToast(data.message || 'Sync failed', 'danger');
                }
            })
            .catch(error => {
                console.error(error);
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                showToast('Sync failed', 'danger');
            });
    });
</script>
@endpush