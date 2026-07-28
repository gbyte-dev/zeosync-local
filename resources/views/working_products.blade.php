@extends('layouts.app')



@section('content')

<style>
    .custom-pagination {
        display: flex;
        background: #f6f6f7;
        border: 1px solid #ddd;
        border-radius: 10px;
        overflow: hidden;
    }

    .page-btn {
        border: none;
        padding: 6px 12px;
        background: transparent;
        border-right: 1px solid #ddd;
        font-size: 14px;
        color: #2563eb;
        text-decoration: none;
        min-width: 36px;
        text-align: center;
    }

    .page-btn:last-child {
        border-right: none;
    }

    .page-btn:hover {
        background: #eee;
    }

    .page-btn.active {
        background: #2563eb;
        color: #fff;
        font-weight: 500;
    }

    .page-btn.disabled {
        pointer-events: none;
        opacity: 0.5;
    }
</style>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2 class="mb-0">Products</h2>

        <div>

            <button
                id="refreshBtn"
                class="btn btn-outline-secondary mr-2"
                title="Refresh"
                data-url="{{ request()->fullUrl() }}">

                <i class="fas fa-sync-alt"></i> Refresh
            </button>

            <a href="{{ route('shopify.product.create', ['shop' => request('shop')]) }}" class="btn btn-primary">

                <i class="fas fa-plus"></i> ADD PRODUCT

            </a>

        </div>

    </div>



    <div class="card shadow">

        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-hover mb-0">

                    <thead class="thead-light">
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Inventory</th>
                            <th>Category</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="productsTableBody">

                        @forelse($products as $product)

                        @php
                        // ✅ Safe Image Handling (Shopify API compatible)
                        $firstImage = $product['image']['src']
                        ?? ($product['images'][0]['src'] ?? 'https://via.placeholder.com/80');

                        $status = $product['status'] ?? 'draft';
                        $category = $product['product_type'] ?? 'Uncategorized';

                        // ✅ Clean Inventory Calculation
                        $inventory = collect($product['variants'] ?? [])
                        ->sum(fn($v) => $v['inventory_quantity'] ?? 0);

                        // ✅ Optional Price
                        $price = $product['variants'][0]['price'] ?? 0;
                        @endphp

                        <tr data-product-id="{{ $product['id'] }}">

                            <!-- Image -->
                            <td>
                                <img src="{{ $firstImage }}"
                                    alt="{{ $product['title'] }}"
                                    class="img-thumbnail"
                                    style="width: 60px; height: 60px; object-fit: cover;">
                            </td>

                            <!-- Title -->
                            <td>
                                <strong>{{ $product['title'] }}</strong><br>
                                <small class="text-muted">
                                    Vendor: {{ $product['vendor'] ?? 'N/A' }}
                                </small><br>
                                <small class="text-muted">
                                    ₹{{ $price }}
                                </small>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge bg-{{ $status === 'active' ? 'success' : 'secondary' }} text-white">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <!-- Inventory -->
                            <td>
                                <span class="font-weight-bold {{ $inventory > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $inventory }}
                                </span>
                                <small class="text-muted d-block">units</small>
                            </td>

                            <!-- Category -->
                            <td>
                                {{ $category }}
                            </td>

                            <!-- Actions -->
                            <td>
                                @if($product->needs_resync)
                                <div class="mb-1">
                                    <span class="resync-warning text-warning small">
                                        Product updated — needs resync
                                    </span>
                                </div>
                                @endif
                                <div class="btn-group btn-group-sm" role="group">
                                    @if($product->synced_to_amazon && !$product->needs_resync)
                                    <button class="btn btn-success btn-sm" disabled>
                                        Synced
                                    </button>

                                    @elseif($product->needs_resync)
                                    <button type="button"
                                        class="btn btn-warning btn-sync"
                                        data-url="{{ route('shopify.sync.amazon', ['id' => $product->id, 'shop' => request('shop')]) }}">
                                        Resync
                                    </button>

                                    @else
                                    <button type="button"
                                        class="btn btn-outline-dark btn-sync"
                                        data-url="{{ route('shopify.sync.amazon', ['id' => $product->id, 'shop' => request('shop')]) }}">
                                        Sync
                                    </button>
                                    @endif

                                    <a href="{{ route('shopify.product.view', ['id' => $product->shopify_id, 'shop' => request('shop')]) }}"
                                        class="btn btn-outline-info" title="View">
                                        View
                                    </a>

                                    <button type="button"
                                        class="btn btn-outline-warning btn-edit"
                                        title="Edit"
                                        data-id="{{ $product['id'] }}"
                                        data-route="{{ route('shopify.product.edit', ['id' => $product->shopify_id, 'shop' => request('shop')]) }}">
                                        Edit
                                    </button>

                                    <button type="button"
                                        class="btn btn-outline-danger btn-delete"
                                        title="Delete"
                                        data-id="{{ $product['id'] }}"
                                        data-route="{{ route('shopify.product.delete', ['id' => $product->shopify_id, 'shop' => request('shop')]) }}">
                                        Delete
                                    </button>

                                </div>
                            </td>

                        </tr>

                        @empty

                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-box-open fa-3x mb-3"></i><br>
                                    No products found.
                                </div>
                            </td>
                        </tr>

                        @endforelse

                    </tbody>

                </table>
                <div class="d-flex justify-content-between align-items-center p-3 mt-2">

                    <!-- LEFT -->
                    <div class="text-muted small">
                        Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $products->total() }} results
                    </div>

                    <!-- RIGHT -->
                    <div class="custom-pagination">

                        <!-- PREV -->
                        <a href="{{ $products->previousPageUrl() }}"
                            class="page-btn {{ $products->onFirstPage() ? 'disabled' : '' }}">‹</a>

                        @php
                        $current = $products->currentPage();
                        $last = $products->lastPage();

                        $start = max(1, $current - 1);
                        $end = min($last, $current + 1);

                        if ($current == 1) {
                        $end = min(3, $last);
                        }

                        if ($current == $last) {
                        $start = max(1, $last - 2);
                        }
                        @endphp

                        <!-- PAGE NUMBERS -->
                        @for($i = $start; $i <= $end; $i++)
                            <a href="{{ $products->url($i) }}"
                            class="page-btn {{ $i == $current ? 'active' : '' }}">
                            {{ $i }}
                            </a>
                            @endfor

                            <!-- NEXT -->
                            <a href="{{ $products->nextPageUrl() }}"
                                class="page-btn {{ !$products->hasMorePages() ? 'disabled' : '' }}">›</a>

                    </div>
                </div>
            </div>

        </div>

    </div>

</div>





@endsection



@push('styles')

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .table th,
    .table td {

        vertical-align: middle;

    }

    .badge-success {

        background-color: #28a745;

    }

    .badge-secondary {

        background-color: #6c757d;

    }

    .img-thumbnail {

        border-radius: 8px;

    }
</style>

@endpush



@push('scripts')

<script>
    document.addEventListener('click', function(e) {

        // ✅ EDIT BUTTON
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

        deleteBtn.innerHTML = 'Deleting...';

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

                        deleteBtn.innerHTML = 'Deleted';

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

                // 👉 reload table only (simple approach)
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

        console.log("Click detected");

        // Prevent double click request
        if (syncBtn.dataset.loading === '1') {
            return;
        }

        const url = syncBtn.getAttribute('data-url');

        if (!url) {

            console.error("URL missing");

            return;
        }

        console.log("Sync URL:", url);

        // Loading state
        syncBtn.dataset.loading = '1';

        syncBtn.innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> Syncing...';

        fetch(url, {

                method: 'POST',

                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }

            })

            .then(res => {

                console.log("HTTP STATUS:", res.status);

                if (!res.ok) {
                    throw new Error("HTTP " + res.status);
                }

                return res.json();
            })

            .then(data => {

                console.log("API RESPONSE:", data);

                // SUCCESS
                if (data.success) {

                    const row = syncBtn.closest('tr');

                    // Button UI
                    syncBtn.innerHTML = 'Synced';

                    syncBtn.classList.remove(
                        'btn-outline-dark',
                        'btn-warning',
                        'btn-danger'
                    );

                    syncBtn.classList.add('btn-success');

                    syncBtn.disabled = true;

                    // Remove warning
                    if (row) {

                        const warning =
                            row.querySelector('.resync-warning');

                        if (warning) {
                            warning.remove();
                        }

                        // Replace button with final synced button
                        const group =
                            row.querySelector('.btn-group');

                        if (group) {

                            const oldSyncBtn =
                                group.querySelector('.btn-sync');

                            if (oldSyncBtn) {

                                oldSyncBtn.outerHTML = `
                    <button class="btn btn-success btn-sm" disabled>
                        Synced
                    </button>
                `;
                            }
                        }
                    }

                    console.log("Product synced successfully");
                }

                // FAILED
                syncBtn.innerHTML = 'Retry';

                syncBtn.classList.remove(
                    'btn-success',
                    'btn-outline-dark',
                    'btn-warning'
                );

                syncBtn.classList.add('btn-danger');

                syncBtn.dataset.loading = '0';

                console.warn(
                    "Amazon validation failed:",
                    data.message
                );
            })

            .catch(err => {

                console.error("SYNC ERROR:", err);

                syncBtn.innerHTML = 'Retry';

                syncBtn.classList.remove(
                    'btn-success',
                    'btn-outline-dark',
                    'btn-warning'
                );

                syncBtn.classList.add('btn-danger');

                syncBtn.dataset.loading = '0';
            });
    });

    function showLoader(text = 'Processing...') {

        document.getElementById('globalLoaderOverlay').style.display = 'flex';

        document.getElementById('loaderText').innerText = text;
    }

    function hideLoader() {

        document.getElementById('globalLoaderOverlay').style.display = 'none';
    }
</script>

@endpush