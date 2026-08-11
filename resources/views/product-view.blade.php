@extends('layouts.app')

@section('content')

@php
$variants = $product['variants'] ?? [];
$colors = !empty($variants) ? array_unique(array_column($variants, 'option1')) : [];
$allSizes = [];
$colorSizeMap = [];
$sizeColorsMap = [];

foreach ($variants as $variant) {
$color = $variant['option1'];
$size = $variant['option2'];
$qty = $variant['inventory_quantity'];
if (!in_array($size, $allSizes)) {
$allSizes[] = $size;
}

if ($qty > 0) {
if (!isset($colorSizeMap[$color])) {
$colorSizeMap[$color] = [];
}

if (!in_array($size, $colorSizeMap[$color])) {
$colorSizeMap[$color][] = $size;
}

if (!isset($sizeColorsMap[$size])) {
$sizeColorsMap[$size] = [];
}

if (!in_array($color, $sizeColorsMap[$size])) {
$sizeColorsMap[$size][] = $color;
}
}

}

// Ensure each color has at least an empty array
foreach ($colors as $color) {
if (!isset($colorSizeMap[$color])) {
$colorSizeMap[$color] = [];
}
}
@endphp

<style>
    /* Shopify Admin Inspired UI - Ultra Tight & Compact */
    .sp-page {
        background-color: #F6F6F7;
        padding: 16px 20px;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
    }

    /* Typography */
    .sp-title {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 12px 0;
        letter-spacing: -0.01em;
    }

    .sp-label {
        font-size: 13px;
        font-weight: 500;
        color: #111827;
        margin-bottom: 6px;
        display: block;
    }

    .sp-text-muted {
        color: #6B7280;
        font-size: 12px;
    }

    .sp-text-primary {
        color: #111827;
        font-size: 13px;
    }

    /* Layout Grid */
    .sp-layout-grid {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        gap: 16px;
        margin-bottom: 16px;
    }

    @media (max-width: 768px) {
        .sp-layout-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Cards */
    .sp-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 12px 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        margin-bottom: 16px;
    }

    .sp-card-header {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 12px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid #E5E7EB;
    }

    /* Divider */
    .sp-divider {
        margin: 12px 0;
        border: 0;
        border-top: 1px solid #E5E7EB;
    }

    /* Product Header Info */
    .sp-product-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 4px 0;
        line-height: 1.2;
    }

    .sp-price-wrap {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-top: 8px;
    }

    .sp-price-amount {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }

    /* Badges */
    .sp-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1;
        white-space: nowrap;
    }

    .sp-badge-success {
        background: #F0FDF4;
        color: #16A34A;
        border: 1px solid #BBF7D0;
    }

    .sp-badge-danger {
        background: #FEF2F2;
        color: #DC2626;
        border: 1px solid #FECACA;
    }

    .sp-badge-neutral {
        background: #F3F4F6;
        color: #4B5563;
        border: 1px solid #E5E7EB;
    }

    .sp-badge-blue {
        background: #EFF6FF;
        color: #2563EB;
        border: 1px solid #BFDBFE;
    }

    /* Image Gallery */
    .main-image-container {
        width: 100% !important;
        height: 320px !important;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #F9FAFB;
        position: relative;
    }

    .main-img {
        width: 100% !important;
        height: 100% !important;
        object-fit: contain !important;
        /* contain is better for mixed aspect ratios */
    }

    .image-badge {
        position: absolute;
        top: 8px;
        left: 8px;
    }

    .thumbnail-gallery {
        display: flex;
        gap: 8px;
        margin-top: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .thumbnail-item {
        width: 48px !important;
        height: 48px !important;
        flex-shrink: 0;
        border: 1px solid #E5E7EB;
        border-radius: 4px;
        overflow: hidden !important;
        cursor: pointer;
        opacity: 0.6;
        transition: all 0.15s ease;
    }

    .thumbnail-item:hover {
        opacity: 0.8;
    }

    .thumbnail-item.active {
        opacity: 1;
        border-color: #111827;
        box-shadow: 0 0 0 1px #111827;
    }

    .thumbnail-item img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }

    /* Variant Options (JS targets these classes) */
    .sp-flex-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* Color Swatches */
    .color-option {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        color: #4B5563;
        background: #FFFFFF;
        transition: all 0.15s ease;
    }

    .color-option:hover {
        border-color: #D1D5DB;
        background: #F9FAFB;
    }

    .color-option.selected {
        border-color: #111827;
        background: #F9FAFB;
        color: #111827;
        font-weight: 500;
        box-shadow: inset 0 0 0 1px #111827;
    }

    .color-swatch {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 1px solid #D1D5DB;
    }

    /* Size Chips */
    .size-option {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        padding: 4px 8px;
        border: 1px solid #E5E7EB;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        color: #111827;
        background: #FFFFFF;
        transition: all 0.15s ease;
        text-align: center;
    }

    .size-option:hover:not(.out-of-stock) {
        border-color: #9CA3AF;
    }

    .size-option.selected {
        border-color: #111827;
        background: #111827;
        color: #FFFFFF;
        font-weight: 500;
    }

    .size-option.out-of-stock {
        opacity: 0.5;
        background: #F3F4F6;
        color: #9CA3AF;
        text-decoration: line-through;
        cursor: not-allowed;
    }

    /* Specs Grid */
    .sp-specs-grid {
        display: grid;
        grid-template-columns: auto 1fr;
        column-gap: 16px;
        row-gap: 8px;
        font-size: 12px;
    }

    .sp-spec-label {
        color: #6B7280;
    }

    .sp-spec-value {
        color: #111827;
        font-weight: 500;
    }

    /* Description */
    .description-content {
        font-size: 13px;
        color: #374151;
        line-height: 1.5;
    }

    .btn-show-more {
        background: transparent;
        border: none;
        color: #2563EB;
        font-size: 12px;
        font-weight: 500;
        padding: 0;
        margin-top: 8px;
        cursor: pointer;
    }

    .btn-show-more:hover {
        text-decoration: underline;
    }

    /* Table Container */
    .sp-table-wrapper {
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        overflow-x: auto;
    }

    .sp-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .sp-table th {
        background-color: #F9FAFB;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 500;
        color: #6B7280;
        border-bottom: 1px solid #E5E7EB;
        text-align: left;
        white-space: nowrap;
    }

    .sp-table td {
        padding: 8px 12px;
        font-size: 12px;
        color: #111827;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: middle;
    }

    .sp-table tbody tr:last-child td {
        border-bottom: none;
    }

    .sp-header {
        margin-bottom: 16px;
    }

    .dashboard-header-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 20px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .sp-table tbody tr:hover td {
        background-color: #F9FAFB;
    }

    .out-of-stock-row td {
        opacity: 0.6;
        background-color: #FAFAFA;
    }
</style>

<div class="sp-page">

    <div class="sp-header dashboard-header-card">
        <h1 class="sp-title">Product Details</h1>
    </div>

    <div class="sp-layout-grid">

        <!-- LEFT: Gallery Card -->
        <div class="product-gallery">
            <div class="sp-card" style="padding: 8px;">
                <div class="main-image-container">
                    <img id="mainImage" src="{{ $product['image']['src'] ?? ($product['images'][0]['src'] ?? '') }}" class="main-img" alt="{{ $product['title'] }}">
                    <div class="image-badge">
                        <span class="sp-badge sp-badge-blue">New</span>
                    </div>
                </div>

                <div class="thumbnail-gallery">
                    @foreach($product['images'] ?? [] as $index => $img)
                    <div class="thumbnail-item {{ $index === 0 ? 'active' : '' }}">
                        <img src="{{ $img['src'] }}" onclick="changeImage(this)" alt="Thumbnail {{ $index + 1 }}">
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Product Specifications Card -->
            <div class="sp-card">
                <h3 class="sp-card-header">Product Details</h3>
                <div class="sp-specs-grid">
                    <span class="sp-spec-label">Vendor</span>
                    <span class="sp-spec-value">{{ $product['vendor'] }}</span>

                    <span class="sp-spec-label">Type</span>
                    <span class="sp-spec-value">{{ $product['product_type'] }}</span>

                    <span class="sp-spec-label">Handle</span>
                    <span class="sp-spec-value">{{ $product['handle'] }}</span>
                </div>
            </div>
        </div>

        <!-- RIGHT: Product Details & Variants -->
        <div class="product-details">
            <div class="sp-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="sp-product-title">{{ $product['title'] }}</h2>
                        <span class="sp-text-muted">By {{ $product['vendor'] }} • {{ $product['product_type'] }}</span>
                    </div>
                    <span class="sp-badge {{ $product['status'] === 'active' ? 'sp-badge-success' : 'sp-badge-neutral' }}">
                        {{ ucfirst($product['status']) }}
                    </span>
                </div>

                <!-- Price Section -->
                <div class="sp-price-wrap">
                    <span class="sp-price-amount" id="dynamicPrice">₹{{ $variants[0]['price'] ?? '0' }}</span>

                    @php
                    $prices = collect($variants)->pluck('price');
                    $minPrice = $prices->min() ?? 0;
                    $maxPrice = $prices->max() ?? 0;
                    @endphp

                    <span class="sp-text-muted" style="margin-left: 4px;">
                        From ₹{{ $minPrice }} to ₹{{ $maxPrice }} (Inc. taxes)
                    </span>
                </div>

                <hr class="sp-divider">

                <!-- Color Selection -->
                <div class="variant-section" style="margin-bottom: 12px;">
                    <span class="sp-label">Color</span>
                    <div class="color-options sp-flex-wrap">
                        @foreach($colors as $color)
                        <div class="color-option {{ $loop->first ? 'selected' : '' }}"
                            data-color="{{ $color }}"
                            onclick="selectColor(this, '{{ $color }}')">
                            <div class="color-swatch"
                                style="background-color: {{ in_array(strtolower($color), ['red','green','blue','black','white','yellow','pink','orange','purple','gray']) ? strtolower($color) : '#ccc' }}">
                            </div>
                            <span class="color-name">{{ $color }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Size Selection -->
                <div class="variant-section" style="margin-bottom: 12px;">
                    <div class="d-flex justify-content-between align-items-end" style="margin-bottom: 6px;">
                        <span class="sp-label" style="margin: 0;">Size</span>
                        <a href="#" class="sp-text-muted" style="text-decoration: underline;">Size Guide</a>
                    </div>

                    <div class="size-options sp-flex-wrap">
                        @php
                        $defaultColor = $colors[0] ?? ($variants[0]['option1'] ?? '');
                        $sizes = $allSizes;
                        @endphp

                        @foreach($sizes as $size)
                        @php
                        $availableForColor = in_array($size, $colorSizeMap[$defaultColor] ?? []);
                        $price = '';
                        foreach($variants as $variant) {
                        if($variant['option1'] === $defaultColor && $variant['option2'] === $size && $variant['inventory_quantity'] > 0) {
                        $price = $variant['price'];
                        break;
                        }
                        }
                        $availableAny = isset($sizeColorsMap[$size]) && count($sizeColorsMap[$size]) > 0;
                        $availableColors = isset($sizeColorsMap[$size]) ? implode(',', $sizeColorsMap[$size]) : '';
                        @endphp

                        <div class="size-option {{ $availableForColor ? '' : 'out-of-stock' }} {{ $loop->first && $availableForColor ? 'selected' : '' }}"
                            data-size="{{ $size }}"
                            data-available-colors="{{ $availableColors }}"
                            onclick="selectSize(this, '<?php echo $size; ?>', <?php echo $availableForColor ? 1 : 0; ?>)">
                            <span class="size-label">{{ $size }}</span>
                            @if(!$availableForColor)
                            <span class="stock-label d-none">Out of Stock</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                <hr class="sp-divider">

                <!-- Inventory Info -->
                <div class="inventory-info d-flex justify-content-between align-items-center">
                    <div class="stock-status">
                        @php
                        $totalStock = collect($variants)->sum(fn($v) => $v['inventory_quantity'] ?? 0);
                        @endphp

                        @if($totalStock > 10)
                        <span class="sp-badge sp-badge-success">In Stock ({{ $totalStock }} available)</span>
                        @elseif($totalStock > 0)
                        <span class="sp-badge sp-badge-warning" style="background:#FFFBEB;color:#B45309;border:1px solid #FEF3C7;">Only {{ $totalStock }} left</span>
                        @else
                        <span class="sp-badge sp-badge-danger">Out of Stock</span>
                        @endif
                    </div>
                    <div class="sp-text-muted">
                        <i class="bi bi-truck me-1"></i> Free delivery over ₹500
                    </div>
                </div>

                <!-- Action Buttons (Commented out per original file) -->
                <!-- <div class="action-buttons mt-3 d-flex gap-2"> ... </div> -->
            </div>

            <!-- Description Card -->
            <div class="sp-card">
                <h3 class="sp-card-header">Description</h3>
                <div class="description-content">
                    <div class="description-text" id="descriptionText">
                        {!! $product['body_html'] ?? '' !!}
                    </div>
                    <div class="description-toggle">
                        <button class="btn-show-more" onclick="toggleDescription()">
                            Show More ↓
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Variants Table Section -->
    <div class="sp-card">
        <h3 class="sp-card-header" style="border-bottom: none; padding-bottom: 0;">Available Variants & Pricing</h3>
        <div class="sp-table-wrapper mt-2">
            <table class="sp-table variants-table">
                <thead>
                    <tr>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>SKU</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($variants as $variant)
                    <tr class="{{ $variant['inventory_quantity'] === 0 ? 'out-of-stock-row' : '' }}">
                        <td>{{ $variant['option1'] }}</td>
                        <td>{{ $variant['option2'] }}</td>
                        <td>₹{{ $variant['price'] }}</td>
                        <td>
                            <span class="sp-badge {{ $variant['inventory_quantity'] > 0 ? 'sp-badge-success' : 'sp-badge-danger' }}">
                                {{ $variant['inventory_quantity'] > 0 ? $variant['inventory_quantity'] . ' in stock' : 'Out of stock' }}
                            </span>
                        </td>
                        <td>{{ $variant['sku'] ?: 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let selectedColor = '<?php echo isset($colors[0]) ? $colors[0] : (isset($variants[0]["option1"]) ? $variants[0]["option1"] : ""); ?>';
    let selectedSize = '<?php echo $allSizes[0] ?? ""; ?>';
    let isDescriptionExpanded = false;

    // Product variants data from PHP
    const productVariants = <?php echo json_encode($variants ?? []); ?>;
    // Color-size availability mapping
    const colorSizeMap = <?php echo json_encode($colorSizeMap ?? []); ?>;

    function changeImage(element) {
        document.getElementById('mainImage').src = element.src;

        // Update active thumbnail
        document.querySelectorAll('.thumbnail-item').forEach(item => {
            item.classList.remove('active');
        });
        element.parentElement.classList.add('active');
    }

    function selectColor(element, color) {
        selectedColor = color;

        // Update UI
        document.querySelectorAll('.color-option').forEach(option => {
            option.classList.remove('selected');
        });
        element.classList.add('selected');

        // Update size availability based on color
        updateSizeAvailability(color);

        // Update price
        updatePrice();
    }

    function selectSize(element, size, available) {
        if (available === 0) return;

        selectedSize = size;

        // Update UI
        document.querySelectorAll('.size-option').forEach(option => {
            option.classList.remove('selected');
        });
        element.classList.add('selected');

        // Update price
        updatePrice();
    }

    function updateSizeAvailability(color) {
        // Get sizes available for this color
        const availableSizes = colorSizeMap[color] || [];

        // Loop through all size options
        document.querySelectorAll('.size-option').forEach(option => {
            const size = option.getAttribute('data-size');
            const isAvailable = availableSizes.includes(size);

            // Show/hide based on availability
            if (isAvailable) {
                option.style.display = 'inline-flex';
                option.classList.remove('out-of-stock');
                // Update click handler availability
                option.onclick = function() {
                    selectSize(this, size, 1);
                };
            } else {
                // Hide size option entirely (not available for this color)
                option.style.display = 'none';
                option.classList.add('out-of-stock');
                // Remove selected class if it was selected
                option.classList.remove('selected');
            }
        });

        // If currently selected size is not available, select the first available size
        const selectedOption = document.querySelector('.size-option.selected');
        if (!selectedOption || selectedOption.style.display === 'none') {
            // Find first visible size option
            const firstVisible = document.querySelector('.size-option[style*="display: inline-flex"], .size-option:not([style*="display: none"])');
            if (firstVisible) {
                firstVisible.click();
            } else {
                // No sizes available, reset selectedSize
                selectedSize = '';
            }
        }
    }

    function updatePrice() {
        // Find variant matching selected color and size
        const variant = productVariants.find(v =>
            v.option1 === selectedColor && v.option2 === selectedSize
        );

        let price = '0';
        if (variant) {
            price = variant.price;
        } else {
            // Fallback: find any variant with selected color
            const fallbackVariant = productVariants.find(v => v.option1 === selectedColor);
            if (fallbackVariant) {
                price = fallbackVariant.price;
            } else if (productVariants.length > 0) {
                price = productVariants[0].price;
            }
        }

        // Update price display
        const priceElement = document.getElementById('dynamicPrice');
        if (priceElement) {
            priceElement.textContent = '₹' + price;
        }
    }

    function toggleDescription() {
        const descriptionText = document.getElementById('descriptionText');
        const toggleButton = document.querySelector('.btn-show-more');

        if (isDescriptionExpanded) {
            descriptionText.style.maxHeight = '120px';
            descriptionText.style.overflow = 'hidden';
            toggleButton.innerHTML = 'Show More ↓';
        } else {
            descriptionText.style.maxHeight = 'none';
            descriptionText.style.overflow = 'visible';
            toggleButton.innerHTML = 'Show Less ↑';
        }

        isDescriptionExpanded = !isDescriptionExpanded;
    }

    function addToCart() {
        alert(`Added ${selectedColor} ${selectedSize} to cart!`);
    }

    function buyNow() {
        alert(`Proceeding to checkout with ${selectedColor} ${selectedSize}`);
    }

    function addToWishlist() {
        alert('Added to wishlist!');
    }

    // Initialize description height
    document.addEventListener('DOMContentLoaded', function() {
        const descriptionText = document.getElementById('descriptionText');
        if (descriptionText.scrollHeight > 120) {
            descriptionText.style.maxHeight = '120px';
            descriptionText.style.overflow = 'hidden';
        } else {
            document.querySelector('.description-toggle').style.display = 'none';
        }

        // Initialize size availability based on default color
        updateSizeAvailability(selectedColor);

        // Initialize price based on default selections
        updatePrice();
    });
</script>
@endpush