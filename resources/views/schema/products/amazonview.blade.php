@extends('layouts.app')
@section('content')

@push('css')
<style>
    /* ── Page Base ── */
    .amz-page {
        background-color: #eaeded;
        font-family: "Amazon Ember", Arial, sans-serif;
        min-height: 100vh;
    }

    /* Breadcrumb */
    .amz-breadcrumb { font-size: 12px; color: #565959; }
    .amz-breadcrumb a { color: #007185; text-decoration: none; }
    .amz-breadcrumb a:hover { color: #C7511F; text-decoration: underline; }

    /* SKU badge */
    .amz-sku-badge {
        font-size: 12px; color: #565959; background: #F0F2F2;
        padding: 2px 8px; border-radius: 4px;
    }

    /* ── Status Badges ── */
    .amz-badge-status {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 14px; border-radius: 20px;
        font-size: 13px; font-weight: 500;
    }
    .amz-badge-active { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .amz-badge-draft { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .amz-badge-submitted { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .amz-badge-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .amz-badge-other { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

    /* ── Alerts ── */
    .amz-alert-error {
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
        padding: 12px 16px; font-size: 13px; color: #991b1b;
    }
    .amz-alert-error i { color: #dc2626; }
    .amz-alert-warning {
        background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
        padding: 12px 16px; font-size: 13px; color: #92400e;
    }
    .amz-alert-warning i { color: #d97706; }

    /* ── Schema Chips ── */
    .amz-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; background: #f3f4f6; border-radius: 20px;
        font-size: 13px; color: #111827;
    }
    .amz-chip i { color: #6b7280; }

    /* ── Image Gallery ── */
    .amz-image-section { position: sticky; top: 20px; }
    .amz-thumbnail-list {
        display: flex; flex-direction: column; gap: 8px; min-width: 50px;
    }
    .amz-thumbnail {
        width: 50px; height: 50px; border: 1px solid #d1d5db; border-radius: 6px;
        overflow: hidden; cursor: pointer; display: flex; align-items: center;
        justify-content: center; background: #fff; padding: 2px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .amz-thumbnail:hover { border-color: #9ca3af; }
    .amz-thumbnail.active { border-color: #007185; box-shadow: 0 0 0 2px #c8f3fa; }
    .amz-thumbnail img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .amz-main-image-wrap {
        flex: 1; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
        background: #fff; min-height: 350px; max-height: 500px; padding: 20px;
    }
    .amz-main-image-wrap img { max-width: 100%; max-height: 100%; object-fit: contain; }
    @media (max-width: 991px) {
        .amz-thumbnail-list { flex-direction: row; overflow-x: auto; }
        .amz-image-section { position: static; }
    }

    /* ── Right Column Info ── */
    .amz-title {
        font-size: 22px; line-height: 1.3; color: #0F1111;
    }
    .amz-brand-link { color: #007185; font-size: 13px; }
    .amz-brand-link:hover { color: #C7511F; }
    .amz-price-large { font-size: 20px; line-height: 1.1; color: #0F1111; }
    .amz-price-currency { font-size: 12px; vertical-align: super; color: #0F1111; }
    .amz-price-offer { font-size: 18px; line-height: 1.1; color: #B12704; }
    .amz-price-label { font-size: 13px; color: #565959; }
    .amz-divider { border-top: 1px solid #e5e7eb; margin: 14px 0; }

    /* ── Bullet Points ── */
    .amz-bullet-list { list-style: none; padding: 0; margin: 0; }
    .amz-bullet-list li {
        font-size: 14px; line-height: 1.6; color: #0F1111;
        padding: 2px 0 2px 14px; position: relative;
    }
    .amz-bullet-list li::before {
        content: '•'; position: absolute; left: 0; color: #565959; font-weight: bold;
    }

    /* ── Tabs ── */
    .amz-tabs-wrap {
        border-bottom: 1px solid #d1d5db; overflow-x: auto;
        display: flex; gap: 0; background: #fff;
    }
    .amz-tab-btn {
        padding: 10px 24px; font-size: 14px; font-weight: 500; color: #6b7280;
        cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap;
        transition: all 0.15s ease; background: none;
        border-top: none; border-left: none; border-right: none;
    }
    .amz-tab-btn:hover { color: #111827; }
    .amz-tab-btn.active { color: #111827; border-bottom-color: #007185; }
    .amz-tab-pane { display: none; }
    .amz-tab-pane.active { display: block; }

    /* ── Buttons ── */
    .btn-amz-primary {
        background: #ffd814; border-color: #fcd200; color: #111827;
        border-radius: 20px; font-weight: 500; font-size: 14px;
        transition: all 0.15s;
    }
    .btn-amz-primary:hover { background: #f7ca00; border-color: #f2c200; color: #111827; }
    .btn-amz-secondary {
        background: #fff; border-color: #d1d5db; color: #111827;
        border-radius: 20px; font-weight: 500; font-size: 14px;
    }
    .btn-amz-secondary:hover { background: #f9fafb; border-color: #9ca3af; color: #111827; }
    .btn-amz-danger {
        background: #fff; border-color: #d1d5db; color: #dc2626;
        border-radius: 20px; font-weight: 500; font-size: 14px;
    }
    .btn-amz-danger:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }

    /* ── Detail Table ── */
    .amz-detail-table { width: 100%; border-collapse: collapse; }
    .amz-detail-table td {
        padding: 10px 14px; font-size: 14px; vertical-align: top;
        border-bottom: 1px solid #e5e7eb;
    }
    .amz-detail-table tr:last-child td { border-bottom: none; }
    .amz-detail-table .amz-label {
        color: #6b7280; font-weight: 500; width: 200px; background: #f9fafb;
    }
    .amz-detail-table .amz-value { color: #111827; }
    @media (max-width: 768px) {
        .amz-detail-table td { display: block; width: 100%; }
        .amz-detail-table .amz-label { width: 100%; }
        .content{
            padding:0px!important;
        }
    }

    /* ── Attribute Grid ── */
    .amz-attr-item {
        display: flex; padding: 10px 14px; background: #f9fafb;
        border-radius: 8px; border: 1px solid #e5e7eb;
        transition: border-color 0.15s;
    }
    .amz-attr-item:hover { border-color: #d1d5db; }

    /* ── Issues List ── */
    .amz-issue-item {
        padding: 8px 12px; border-radius: 6px; font-size: 13px;
        border-left: 3px solid #fecaca; background: #fef2f2; margin-bottom: 6px;
    }
    .amz-issue-item:last-child { margin-bottom: 0; }
    .amz-issue-item.warning { border-left-color: #fde68a; background: #fffbeb; }
    .amz-issue-item.info { border-left-color: #bfdbfe; background: #eff6ff; }

    /* ── Card ── */
    .amz-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    }

    /* ── Section Headers ── */
    .amz-section-title {
        font-size: 15px; font-weight: 600; color: #111827;
        padding-bottom: 8px; margin-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    /* ── Empty State ── */
    .amz-empty-state {
        text-align: center; padding: 40px 20px; color: #9ca3af;
    }
    .amz-empty-state i { font-size: 36px; display: block; margin-bottom: 8px; }
</style>
@endpush

<div class="amz-page px-3 px-md-4 py-3 py-md-4">

    {{-- Breadcrumb --}}
    <div class="row">
        <div class="col-sm-8">
            <nav class="amz-breadcrumb mb-3 d-flex flex-wrap align-items-center gap-1" aria-label="breadcrumb">
                <a href="{{ route('user.product.showProducts', ['shop' => request('shop')]) }}">Amazon Products</a>
                <span class="text-secondary mx-1">›</span>
                <span class="text-muted">Product Details</span>
                <span class="text-secondary mx-1">›</span>
                <span class="amz-sku-badge">{{ $product->sku ?? 'N/A' }}</span>
            </nav>
        </div>
        <div class="col-sm-4">
            <a class="float-end btn btn-sm btn-primary" href="{{route('user.product.showProducts',['shop'=> request()->shop??session('active_shop')])}}">Back</a>
        </div>
    </div>


    @if(isset($error))
    {{-- Error State --}}
    <div class="amz-card p-4">
        <div class="text-center py-5">
            <div class="text-danger mb-3" style="font-size: 48px;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h2 class="h5 fw-semibold mb-2">Product Not Found</h2>
            <p class="text-muted small mb-4">{{ $error }}</p>
            <a href="{{ route('user.product.showProducts', ['shop' => request('shop')]) }}" class="btn btn-amz-primary btn-sm px-4">
                <i class="bi bi-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    @else
    @php
    // Amazon API returns statuses like: ACTIVE, BUYABLE, DRAFT, INCOMPLETE, CLOSED, etc.
    $rawStatus = $product->status ?? 'DRAFT';
    $status = strtolower(trim($rawStatus));

    // Map Amazon statuses to display categories
    $hasStatusError = in_array($status, ['invalid', 'error', 'failed', 'unknown', 'incomplete']);
    $hasSchemaError = isset($schemaError) && !empty($schemaError);
    $hasIssues = isset($issues) && count($issues) > 0;

    $statusBadgeClass = match($status) {
        'active', 'buyable' => 'amz-badge-active',
        'draft' => 'amz-badge-draft',
        'submitted' => 'amz-badge-submitted',
        'invalid', 'error', 'failed', 'incomplete' => 'amz-badge-error',
        default => 'amz-badge-other'
    };
    $statusIcon = match($status) {
        'active', 'buyable' => 'check-circle-fill',
        'draft' => 'pencil-fill',
        'submitted' => 'cloud-arrow-up-fill',
        'invalid', 'error', 'failed', 'incomplete' => 'exclamation-circle-fill',
        default => 'info-circle-fill'
    };

    // Human-readable status label
    $statusLabel = match($status) {
        'active' => 'Active',
        'buyable' => 'Buyable',
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'incomplete' => 'Incomplete',
        'closed' => 'Closed',
        default => ucfirst($status)
    };
    @endphp

    {{-- Status & Schema Error Alerts (full width) --}}
    @if($hasStatusError)
    <div class="amz-alert-error mb-3 d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-circle-fill mt-1 flex-shrink-0"></i>
        <div>
            <strong class="d-block">Status Error</strong>
            <span>The product status is "<strong>{{ ucfirst($status) }}</strong>". This may indicate the listing has issues on Amazon.</span>
        </div>
    </div>
    @endif

    @if($hasSchemaError)
    <div class="amz-alert-warning mb-3 d-flex align-items-start gap-2">
        <i class="bi bi-database-exclamation mt-1 flex-shrink-0"></i>
        <div>
            <strong class="d-block">Schema Information Unavailable</strong>
            <span>{{ $schemaError }}</span>
        </div>
    </div>
    @endif

    @if($hasIssues)
    <div class="amz-alert-warning mb-3 d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle mt-1 flex-shrink-0"></i>
        <div>
            <strong class="d-block">Listing Issues ({{ count($issues) }})</strong>
            <span>The following issues were found with this listing on Amazon.</span>
        </div>
    </div>
    @endif

    {{-- MAIN LAYOUT: Image Left + Info Right --}}
    <div class="row g-4">

        {{-- LEFT COLUMN: Image Gallery --}}
        <div class="col-12 col-lg-6">
            <div class="amz-card p-3 amz-image-section">
                @if(count($images) > 0)
                <div class="d-flex gap-3">
                    <div class="amz-thumbnail-list" id="thumbnailList">
                        @foreach($images as $index => $image)
                        <div class="amz-thumbnail {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}" onclick="switchImage(this)">
                            <img src="{{ $image }}" alt="Product image {{ $index + 1 }}" onerror="this.parentElement.style.display='none'">
                        </div>
                        @endforeach
                    </div>
                    <div class="amz-main-image-wrap" id="mainImageContainer">
                        <img id="mainProductImage" src="{{ $images[0] ?? '' }}" alt="{{ $item_name ?? 'Product Image' }}" onerror="this.parentElement.innerHTML='<i class=\'bi bi-image\' style=\'font-size:64px;color:#DDD;\'></i>'">
                    </div>
                </div>
                @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-image" style="font-size:64px;color:#DDD;"></i>
                    <p class="mt-2 small">No images available</p>
                </div>
                @endif
            </div>
        </div>

        {{-- RIGHT COLUMN: Product Info --}}
        <div class="col-12 col-lg-6">
            <div class="amz-card p-4">

                {{-- Product Title --}}
                <h1 class="amz-title fw-normal mb-2">
                    {{ $item_name ?? 'Untitled Product' }}
                </h1>

                {{-- Brand Link --}}
                @if(!empty($brand))
                <div class="mb-2">
                    <a href="#" class="amz-brand-link">Visit the {{ $brand }} Store</a>
                </div>
                @endif

                {{-- SKU & Status Row --}}
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <span class="amz-sku-badge">SKU: {{ $product->sku }}</span>
                    @if(!empty($product->parent_id))
                    <span class="amz-sku-badge">Parent SKU: {{ $parentSku ?? 'N/A' }}</span>
                    @endif
                    <span class="amz-badge-status {{ $statusBadgeClass }}" style="font-size:11px;padding:2px 10px;">
                        <i class="bi bi-{{ $statusIcon }}"></i> {{ $statusLabel }}
                    </span>
                </div>

                {{-- Schema Chips --}}
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <span class="amz-chip" style="font-size:12px;padding:4px 10px;"><i class="bi bi-tag"></i> {{ $productType ?? 'Uncategorized' }}</span>
                    @if(!empty($product->schema))
                    <span class="amz-chip" style="font-size:12px;padding:4px 10px;"><i class="bi bi-layers"></i> Schema v{{ $product->schema->schema_version ?? '1.0' }}</span>
                    @endif
                    @if(!empty($product->created_at))
                    <span class="amz-chip" style="font-size:12px;padding:4px 10px;"><i class="bi bi-clock"></i> {{ $product->created_at->format('M d, Y') }}</span>
                    @endif
                </div>

                <hr class="amz-divider">

                {{-- Price --}}
                @if($price)
                <div class="mb-3">
                    <div class="d-flex align-items-baseline gap-2 flex-wrap">
                        <span class="amz-price-label">List Price:</span>
                        <span class="amz-price-currency">$</span>
                        <span class="amz-price-large">{{ number_format((float)$price, 2, '.', '') }}</span>
                    </div>
                    @if($purchasable_price)
                    <div class="d-flex align-items-baseline gap-2 flex-wrap mt-1">
                        <span class="amz-price-label">Offer Price:</span>
                        <span class="amz-price-currency">$</span>
                        <span class="amz-price-offer">{{ number_format((float)$purchasable_price, 2, '.', '') }}</span>
                    </div>
                    @endif
                </div>
                <hr class="amz-divider">
                @endif

                {{-- Highlights / Bullet Points --}}
                @if(count($bullet_points) > 0)
                <div class="mb-3">
                    <h3 class="amz-section-title" style="font-size:14px;">Product Highlights</h3>
                    <ul class="amz-bullet-list">
                        @foreach($bullet_points as $point)
                        <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
                <hr class="amz-divider">
                @endif

                {{-- Description (short preview) --}}
                @if($description)
                <div class="mb-3">
                    <h3 class="amz-section-title" style="font-size:14px;">Description</h3>
                    <div style="font-size:14px;line-height:1.6;color:#0F1111;max-height:80px;overflow:hidden;">
                        {{ nl2br(e($description)) }}
                    </div>
                    @if(strlen($description) > 200)
                    <a href="#" onclick="event.preventDefault();document.querySelector('[data-tab=\'description\']').click();" style="color:#007185;font-size:13px;">See full description ›</a>
                    @endif
                </div>
                <hr class="amz-divider">
                @endif

                {{-- Quick Info --}}
                <div class="d-flex flex-wrap gap-3 mb-3">
                    @if(!empty($manufacturer))
                    <div class="small"><span class="text-muted">Manufacturer:</span> {{ $manufacturer }}</div>
                    @endif
                    @if($quantity !== null)
                    <div class="small"><span class="text-muted">Qty:</span> {{ $quantity }}</div>
                    @endif
                    @if(!empty($condition))
                    <div class="small"><span class="text-muted">Condition:</span> {{ ucfirst(str_replace('_', ' ', $condition)) }}</div>
                    @endif
                </div>

                {{-- Submission Status --}}
                @if(!empty($product->submission_status) || !empty($product->submitted_on))
                <div class="d-flex flex-wrap gap-3 mb-3 p-2 bg-light rounded-3">
                    @if(!empty($product->submission_status))
                    <div class="small d-flex align-items-center gap-1">
                        <i class="bi bi-hash text-muted"></i>
                        <span class="text-muted">Submission:</span>
                        <code style="font-size:11px;">{{ $product->submission_status }}</code>
                    </div>
                    @endif
                    @if(!empty($product->submitted_on))
                    <div class="small d-flex align-items-center gap-1">
                        <i class="bi bi-calendar3 text-muted"></i>
                        <span class="text-muted">Submitted:</span>
                        {{ \Carbon\Carbon::parse($product->submitted_on)->format('M d, Y h:i A') }}
                    </div>
                    @endif
                </div>
                @endif

                {{-- Issues Summary --}}
                @if($hasIssues)
                <div class="mb-3 p-2 bg-warning bg-opacity-10 rounded-3 border border-warning border-opacity-25">
                    <div class="d-flex align-items-center gap-2 small">
                        <i class="bi bi-exclamation-triangle text-warning"></i>
                        <span class="fw-medium">{{ count($issues) }} listing issue(s) found</span>
                        <a href="#" onclick="event.preventDefault();document.querySelector('[data-tab=\'issues\']').click();" style="color:#007185;font-size:12px;">View details ›</a>
                    </div>
                </div>
                @endif

                {{-- Action Buttons --}}
                <div class="d-flex gap-2 flex-wrap pt-2">
                    <!-- <a href="{{ route('admin.product.productEdit', ['product' => $product->id, 'shop' => request('shop')]) }}" class="btn btn-amz-primary btn-sm">
                        <i class="bi bi-pencil"></i> Edit Product
                    </a> -->
                    @if(empty($product->parent_id))
                    <!-- <a href="{{ route('user.product.showProducts.child', ['parent_id' => $product->id, 'shop' => request('shop')]) }}" class="btn btn-amz-secondary btn-sm">
                        <i class="bi bi-eye"></i> View Variations
                    </a> -->
                    @endif
                    @if(!checkIsProductSynced($product->sku,'amazon'))
                    <a href="{{ route('user.product.syncAmazonToShopify', ['sku' => $product->sku, 'shop' => request('shop')]) }}" class="btn btn-amz-secondary btn-sm">
                        <i class="bi bi-cloud-arrow-up"></i> Sync to Shopify
                    </a>
                    @else
                    @php $pid = checkIsProductSynced($product->sku,'amazon'); @endphp
                    <a href="https://admin.shopify.com/store/{{ str_replace('.myshopify.com','',session('active_shop')) }}/products/{{ $pid }}" class="btn btn-amz-secondary btn-sm" target="_blank">
                        <i class="bi bi-link-45deg"></i> View on Shopify
                    </a>
                    @endif
                    @if(strtolower($product->status ?? '') === 'draft')
                    <form method="POST" action="{{ route('user.product.removeDraft', ['product' => $product->id, 'shop' => request('shop')]) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-amz-danger btn-sm" onclick="return confirm('Are you sure you want to delete this draft?')">
                            <i class="bi bi-trash"></i> Remove Draft
                        </button>
                    </form>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- BOTTOM TABS SECTION (full width below the image+info row) --}}
    <div class="amz-card mt-4">
        <div class="amz-tabs-wrap" role="tablist">
            <button class="amz-tab-btn active" data-tab="description" onclick="switchTab(this)">Description</button>
            <button class="amz-tab-btn" data-tab="attributes" onclick="switchTab(this)">All Attributes</button>
            <button class="amz-tab-btn" data-tab="details" onclick="switchTab(this)">Product Details</button>
            @if(isset($isParent) && $isParent && count($variations) > 0)
            <button class="amz-tab-btn" data-tab="variations" onclick="switchTab(this)">
                Variations <span class="badge bg-primary ms-1" style="font-size:10px;">{{ count($variations) }}</span>
            </button>
            @endif
            @if($hasIssues)
            <button class="amz-tab-btn" data-tab="issues" onclick="switchTab(this)">
                Issues <span class="badge bg-danger ms-1" style="font-size:10px;">{{ count($issues) }}</span>
            </button>
            @endif
        </div>

        <div class="p-4">

            {{-- Tab: Description (full) --}}
            <div class="amz-tab-pane active" id="tab-description">
                @if($description)
                <h3 class="amz-section-title">Product Description</h3>
                <div class="mb-3" style="font-size:14px;line-height:1.7;color:#0F1111;">{{ nl2br(e($description)) }}</div>
                @endif

                @if(count($bullet_points) > 0)
                <h3 class="amz-section-title">Product Highlights</h3>
                <ul class="amz-bullet-list mb-3">
                    @foreach($bullet_points as $point)
                    <li>{{ $point }}</li>
                    @endforeach
                </ul>
                @endif

                @if(!$description && count($bullet_points) === 0)
                <div class="amz-empty-state">
                    <i class="bi bi-file-text"></i>
                    <span>No description or highlights available for this product.</span>
                </div>
                @endif
            </div>

            {{-- Tab: All Attributes --}}
            <div class="amz-tab-pane" id="tab-attributes">
                <h3 class="amz-section-title">All Product Attributes</h3>
                @if(count($allAttributes) > 0)
                <div class="row g-2 mb-3">
                    @foreach($allAttributes as $attr)
                    @php
                    // Format attribute values for better readability
                    $displayValue = $attr['value'];
                    // Decode JSON string to array if needed
                    if (is_string($displayValue) && str_starts_with(trim($displayValue), '{')) {
                        $decoded = json_decode($displayValue, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $displayValue = $decoded;
                        }
                    }
                    if (is_array($displayValue)) {
                        $formatted = [];
                        foreach ($displayValue as $dk => $dv) {
                            if (is_array($dv) && isset($dv['value']) && isset($dv['unit'])) {
                                $formatted[] = $dv['value'] . ' ' . $dv['unit'];
                            } elseif (is_array($dv) && isset($dv['value'])) {
                                $formatted[] = $dv['value'];
                            } elseif (!is_array($dv) && !is_numeric($dk)) {
                                // Named key like 'marketplace_id' - skip internal IDs
                                if (!str_contains($dk, 'marketplace') && !str_contains($dk, 'seller')) {
                                    $formatted[] = $dk . ': ' . $dv;
                                }
                            } elseif (is_array($dv)) {
                                // Further nested - try to extract meaningful info
                                $subParts = [];
                                foreach ($dv as $sk => $sv) {
                                    if (is_array($sv) && isset($sv['value']) && isset($sv['unit'])) {
                                        $subParts[] = $sk . ': ' . $sv['value'] . ' ' . $sv['unit'];
                                    } elseif (is_array($sv) && isset($sv['value'])) {
                                        $subParts[] = $sk . ': ' . $sv['value'];
                                    } elseif (!is_array($sv) && !is_numeric($sk)) {
                                        $subParts[] = $sv;
                                    }
                                }
                                if (!empty($subParts)) {
                                    $formatted[] = implode(', ', $subParts);
                                }
                            }
                        }
                        $displayValue = !empty($formatted) ? implode(' | ', $formatted) : (is_string($attr['value']) ? $attr['value'] : json_encode($displayValue));
                    }
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="amz-attr-item">
                            <span class="small fw-medium text-muted text-capitalize" style="min-width:120px;">
                                {{ Str::title(str_replace('_', ' ', $attr['name'])) }} : 
                            </span>
                            <span class="small" style="color:#111827;word-break:break-word;">
                                {{ $displayValue }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="amz-empty-state">
                    <i class="bi bi-list-ul"></i>
                    <span>No attributes found for this product.</span>
                </div>
                @endif
            </div>

            {{-- Tab: Product Details --}}
            <div class="amz-tab-pane" id="tab-details">
                <h3 class="amz-section-title">Product Information</h3>
                <table class="amz-detail-table mb-3">
                    <tr><td class="amz-label">SKU</td><td class="amz-value">{{ $product->sku }}</td></tr>
                    @if(!empty($product->parent_id))
                    <tr>
                        <td class="amz-label">Parent Product</td>
                        <td class="amz-value">
                            <a href="{{ route('admin.product.productEdit', ['product' => $product->parent_id, 'shop' => request('shop')]) }}" style="color:#007185;">
                                {{ $parentSku ?? 'View Parent' }} (ID: {{ $product->parent_id }})
                            </a>
                        </td>
                    </tr>
                    @endif
                    <tr><td class="amz-label">Product Type</td><td class="amz-value">{{ $productType ?? 'N/A' }}</td></tr>
                    <tr>
                        <td class="amz-label">Status</td>
                        <td class="amz-value">
                            <span class="amz-badge-status {{ $statusBadgeClass }}" style="font-size:12px;padding:2px 10px;">
                                <i class="bi bi-{{ $statusIcon }}"></i> {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>
                    @if(!empty($product->submission_status))
                    <tr><td class="amz-label">Submission ID</td><td class="amz-value" style="font-family:monospace;font-size:12px;">{{ $product->submission_status }}</td></tr>
                    @endif
                    @if(!empty($product->submitted_on))
                    <tr><td class="amz-label">Submitted On</td><td class="amz-value">{{ \Carbon\Carbon::parse($product->submitted_on)->format('F d, Y h:i A') }}</td></tr>
                    @endif
                    <tr><td class="amz-label">Created</td><td class="amz-value">{{ $product->created_at ? $product->created_at->format('F d, Y h:i A') : 'N/A' }}</td></tr>
                    <tr><td class="amz-label">Last Updated</td><td class="amz-value">{{ $product->updated_at ? $product->updated_at->format('F d, Y h:i A') : 'N/A' }}</td></tr>
                    @if(!empty($product->schema))
                    <tr><td class="amz-label">Schema</td><td class="amz-value">{{ $product->schema->product_type ?? 'N/A' }} (v{{ $product->schema->schema_version ?? '1.0' }})</td></tr>
                    @else
                    <tr>
                        <td class="amz-label">Schema</td>
                        <td class="amz-value">
                            <span class="text-warning d-flex align-items-center gap-1">
                                <i class="bi bi-exclamation-circle"></i> Schema not available
                            </span>
                        </td>
                    </tr>
                    @endif
                    @if(!empty($brand))
                    <tr><td class="amz-label">Brand</td><td class="amz-value">{{ $brand }}</td></tr>
                    @endif
                    @if(!empty($manufacturer))
                    <tr><td class="amz-label">Manufacturer</td><td class="amz-value">{{ $manufacturer }}</td></tr>
                    @endif
                    @if($quantity !== null)
                    <tr><td class="amz-label">Quantity</td><td class="amz-value">{{ $quantity }}</td></tr>
                    @endif
                    @if(!empty($condition))
                    <tr><td class="amz-label">Condition</td><td class="amz-value">{{ ucfirst(str_replace('_', ' ', $condition)) }}</td></tr>
                    @endif
                </table>
            </div>

            {{-- Tab: Issues Detail --}}
            @if($hasIssues)
            <div class="amz-tab-pane" id="tab-issues">
                <h3 class="amz-section-title d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Listing Issues ({{ count($issues) }})
                </h3>
                @foreach($issues as $issue)
                @php
                $severity = strtolower($issue['severity'] ?? 'error');
                $issueClass = match($severity) {
                    'warning' => 'warning',
                    'info' => 'info',
                    default => ''
                };
                @endphp
                <div class="amz-issue-item {{ $issueClass }}">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-{{ $severity === 'warning' ? 'exclamation-circle' : ($severity === 'info' ? 'info-circle' : 'x-circle') }} mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>{{ $issue['message'] ?? 'Unknown Issue' }}</strong>
                            @if(!empty($issue['attributeName']))
                            <div class="text-muted small mt-1">Attribute: <code>{{ $issue['attributeName'] }}</code></div>
                            @endif
                            @if(!empty($issue['severity']))
                            <div class="mt-1">
                                <span class="badge bg-{{ $severity === 'warning' ? 'warning' : ($severity === 'info' ? 'info' : 'danger') }} text-white" style="font-size:10px;">
                                    {{ ucfirst($severity) }}
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Tab: Variations (child products) --}}
            @if(isset($isParent) && $isParent && count($variations) > 0)
            <div class="amz-tab-pane" id="tab-variations">
            <h3 class="amz-section-title d-flex align-items-center gap-2">
                <i class="bi bi-collection"></i> Product Variations ({{ count($variations) }})
            </h3>

            {{-- Variation Theme Info --}}
            @php
                $variationTheme = '';
                foreach ($variations as $v) {
                    if (!empty($v['attributes'])) {
                        $keys = array_keys($v['attributes']);
                        $variationTheme = implode(' / ', $keys);
                        break;
                    }
                }
            @endphp
            @if($variationTheme)
            <div class="mb-3 d-flex align-items-center gap-2">
                <span class="amz-chip" style="font-size:12px;padding:4px 10px;">
                    <i class="bi bi-layers"></i> Variation Theme: <strong>{{ $variationTheme }}</strong>
                </span>
            </div>
            @endif

            {{-- Variations Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" style="font-size:13px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:60px;">Image</th>
                            <th>SKU</th>
                            <th>Name</th>
                            @if($variationTheme)
                                @php
                                    $themeKeys = [];
                                    foreach ($variations as $v) {
                                        if (!empty($v['attributes'])) {
                                            $themeKeys = array_keys($v['attributes']);
                                            break;
                                        }
                                    }
                                @endphp
                                @foreach($themeKeys as $tk)
                                <th>{{ Str::title(str_replace('_', ' ', $tk)) }}</th>
                                @endforeach
                            @endif
                            <th>Status</th>
                            <th>Price</th>
                            <th>Offer Price</th>
                            <th>Qty</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($variations as $idx => $variation)
                        @php
                            $vStatus = strtolower(trim($variation['status'] ?? 'UNKNOWN'));
                            $vBadgeClass = match($vStatus) {
                                'active', 'buyable' => 'amz-badge-active',
                                'draft' => 'amz-badge-draft',
                                'submitted' => 'amz-badge-submitted',
                                'incomplete' => 'amz-badge-error',
                                default => 'amz-badge-other'
                            };
                            $vStatusLabel = match($vStatus) {
                                'active' => 'Active', 'buyable' => 'Buyable',
                                'draft' => 'Draft', 'submitted' => 'Submitted',
                                'incomplete' => 'Incomplete', 'closed' => 'Closed',
                                default => ucfirst($vStatus)
                            };
                            $vPrice = $variation['price'] ?? null;
                            $vOfferPrice = $variation['offer_price'] ?? null;
                            $vQty = $variation['quantity'] ?? null;
                            $vImage = $variation['image'] ?? null;
                        @endphp
                        <tr>
                            <td class="text-muted text-center">{{ $idx + 1 }}</td>
                            <td class="text-center">
                                @if($vImage)
                                <img src="{{ $vImage }}" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:4px;border:1px solid #e5e7eb;" onerror="this.style.display='none'">
                                @else
                                <i class="bi bi-box text-muted" style="font-size:20px;"></i>
                                @endif
                            </td>
                            <td><code style="font-size:11px;">{{ $variation['sku'] }}</code></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $variation['item_name'] ?? '' }}">
                                {{ $variation['item_name'] ?? 'N/A' }}
                            </td>
                            @if(isset($themeKeys))
                                @foreach($themeKeys as $tk)
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $variation['attributes'][$tk] ?? '-' }}</span>
                                </td>
                                @endforeach
                            @endif
                            <td>
                                <span class="amz-badge-status {{ $vBadgeClass }}" style="font-size:10px;padding:2px 8px;white-space:nowrap;">
                                    <i class="bi bi-circle-fill" style="font-size:6px;"></i> {{ $vStatusLabel }}
                                </span>
                            </td>
                            <td>
                                @if($vPrice)
                                <span class="fw-medium">${{ number_format((float)$vPrice, 2) }}</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($vOfferPrice)
                                <span class="text-danger fw-medium">${{ number_format((float)$vOfferPrice, 2) }}</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($vQty !== null)
                                <span class="fw-medium">{{ $vQty }}</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                   
                                    @if(!checkIsProductSynced($variation['sku'],'amazon'))
                                    <a href="{{ route('user.product.syncAmazonToShopify', ['sku' => $variation['sku'], 'shop' => request('shop')]) }}" class="btn btn-sm btn-outline-secondary" title="Sync to Shopify" style="padding:2px 8px;font-size:11px;">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Variation Summary Cards (mobile-friendly view) --}}
            <div class="d-md-none mt-3">
                <h4 class="text-muted small fw-semibold mb-2">Variation Summary</h4>
                <div class="row g-2">
                    @foreach($variations as $idx => $variation)
                    @php
                        $vStatus = strtolower(trim($variation['status'] ?? 'UNKNOWN'));
                        $vBadgeClass = match($vStatus) {
                            'active', 'buyable' => 'amz-badge-active',
                            'draft' => 'amz-badge-draft',
                            'submitted' => 'amz-badge-submitted',
                            'incomplete' => 'amz-badge-error',
                            default => 'amz-badge-other'
                        };
                        $vStatusLabel = match($vStatus) {
                            'active' => 'Active', 'buyable' => 'Buyable',
                            'draft' => 'Draft', 'submitted' => 'Submitted',
                            'incomplete' => 'Incomplete', 'closed' => 'Closed',
                            default => ucfirst($vStatus)
                        };
                    @endphp
                    <div class="col-6">
                        <div class="amz-card p-2">
                            <div class="d-flex flex-column gap-1">
                                @if(!empty($variation['image']))
                                <div class="text-center mb-1">
                                    <img src="{{ $variation['image'] }}" alt="" style="width:60px;height:60px;object-fit:contain;border-radius:4px;" onerror="this.style.display='none'">
                                </div>
                                @endif
                                <div class="small">
                                    <code style="font-size:10px;">{{ $variation['sku'] }}</code>
                                </div>
                                @if(!empty($variation['attributes']))
                                <div class="d-flex gap-1 flex-wrap">
                                    @foreach($variation['attributes'] as $ak => $av)
                                    <span class="badge bg-light text-dark border" style="font-size:10px;">{{ $ak }}: {{ $av }}</span>
                                    @endforeach
                                </div>
                                @endif
                                <div>
                                    <span class="amz-badge-status {{ $vBadgeClass }}" style="font-size:9px;padding:1px 6px;">
                                        {{ $vStatusLabel }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">

                                    <a href="{{ route('user.product.syncAmazonToShopify', ['sku' => $variation['sku'], 'shop' => request('shop')]) }}" class="btn btn-sm btn-outline-secondary" style="padding:1px 6px;font-size:10px;">
                                        <i class="bi bi-cloud-arrow-up"></i> Sync
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endif
</div>
@endsection

@push('scripts')
<script>
    function switchImage(el) {
        document.querySelectorAll('.amz-thumbnail').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        const img = el.querySelector('img');
        if (img) {
            const mainImg = document.getElementById('mainProductImage');
            if (mainImg) mainImg.src = img.src;
        }
    }

    function switchTab(el) {
        document.querySelectorAll('.amz-tab-btn').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        const tabId = el.getAttribute('data-tab');
        document.querySelectorAll('.amz-tab-pane').forEach(c => c.classList.remove('active'));
        const target = document.getElementById('tab-' + tabId);
        if (target) target.classList.add('active');
    }
</script>
@endpush