<!-- ========================= -->
<!-- Action Modal (Compact) -->
<!-- ========================= -->
<div class="modal fade" id="productActionModal" tabindex="-1" aria-labelledby="productActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header py-2 px-3 bg-light border-bottom">
                <h6 class="modal-title mb-0 fw-bold fs-6 text-dark" id="productActionModalLabel">Choose Mapping Method</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-grid gap-2">
                    <button id="existingProductBtn" class="btn btn-primary btn-sm fw-medium py-2 shadow-sm">
                        <i class="fab fa-shopify me-1"></i> Existing Shopify Product
                    </button>
                    <a id="newProductBtn" class="btn btn-success btn-sm fw-medium py-2 shadow-sm" href="">
                        <i class="fas fa-plus-circle me-1"></i> Add New Product to Shopify
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================= -->
<!-- Map Shopify Product Modal (Compact) -->
<!-- ========================= -->
<div class="modal fade" id="mapShopifyProductModal" tabindex="-1" aria-labelledby="mapShopifyProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            
            <!-- Header -->
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title mb-0 fw-bold fs-6 d-flex align-items-center" id="mapShopifyProductModalLabel">
                    <i class="fas fa-link me-2"></i>Map Amazon Product to Shopify
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body p-3">
                <p class="text-muted mb-3 lh-sm" style="font-size: 0.85rem;">
                    Select the Shopify product and variant you want to map to this Amazon product.
                </p>
                
                <input type="hidden" id="amazonSku">

                <!-- Amazon Product Context Badge -->
                <div id="targetAmazonProductCard" class="card bg-light border p-2 mb-3" style="display: none; border-radius: 8px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Amazon Target</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0" style="font-size: 0.7rem;">Active SKU</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <i class="fab fa-amazon text-warning fs-6"></i>
                        <span class="fw-semibold text-dark font-monospace" id="displayTargetAmazonSku" style="font-size: 0.85rem;"></span>
                    </div>
                </div>

                <!-- Flex Grid for Dropdowns -->
                <!-- Row 1: Product and Variant side-by-side -->
                <div class="row g-2 mb-2">
                    
                    <!-- Shopify Product Dropdown -->
                    <div class="col-md-6">
                        <label for="shopifyProduct" class="form-label fw-semibold mb-1 text-dark d-flex align-items-center" style="font-size: 0.85rem;">
                            <i class="fas fa-box me-1 text-primary"></i> Shopify Product
                            <span id="shopifyProductLoadingSpinner" class="spinner-border spinner-border-sm text-primary ms-1 align-middle" role="status" style="display: none; width: 0.8rem; height: 0.8rem;"></span>
                        </label>
                        <select id="shopifyProduct" class="form-select form-select-sm" style="width: 100%;">
                            <option value="">Select Shopify Product</option>
                        </select>
                    </div>

                    <!-- Shopify Variant Dropdown -->
                    <div class="col-md-6">
                        <label for="shopifyVariant" class="form-label fw-semibold mb-1 text-dark d-flex align-items-center" style="font-size: 0.85rem;">
                            <i class="fas fa-layer-group me-1 text-success"></i> Shopify Variant
                            <span id="shopifyVariantLoadingSpinner" class="spinner-border spinner-border-sm text-success ms-1 align-middle" role="status" style="display: none; width: 0.8rem; height: 0.8rem;"></span>
                        </label>
                        <select id="shopifyVariant" class="form-select form-select-sm" style="width: 100%;" disabled>
                            <option value="">Select Product First</option>
                        </select>
                    </div>
                    
                </div> <!-- End Row 1 -->

                <!-- Row 2: Shop Location -->
                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <label for="shopifyLocation" class="form-label fw-semibold mb-1 text-dark d-flex align-items-center" style="font-size: 0.85rem;">
                            <i class="fas fa-map-marker-alt me-1 text-danger"></i> Shop Location
                        </label>
                        @php
                            $modalLocations = is_array($shop->shopify_locations ?? null) ? $shop->shopify_locations : (json_decode($shop->shopify_locations ?? '[]', true) ?? []);
                            $modalSelectedIdx = (isset($shop->selected_location_index) && isset($modalLocations[$shop->selected_location_index]))
                                ? (int) $shop->selected_location_index
                                : 0;
                            $modalDefaultLocationId = $modalLocations[$modalSelectedIdx]['id'] ?? ($modalLocations[0]['id'] ?? '');
                        @endphp
                        <select id="shopifyLocation" class="form-select form-select-sm" data-default-location-id="{{ $modalDefaultLocationId }}" style="width: 100%;">
                            @if(!empty($modalLocations))
                                @foreach($modalLocations as $idx => $loc)
                                    <option value="{{ $loc['id'] }}" {{ (int)$modalSelectedIdx === (int)$idx ? 'selected' : '' }}>
                                        {{ $loc['name'] ?? 'Location ' . ($idx + 1) }}
                                    </option>
                                @endforeach
                            @else
                                <option value="">Default Location</option>
                            @endif
                        </select>
                    </div>
                </div> <!-- End Row 2 -->

                <!-- Compact Info Alert -->
                <div class="alert alert-info py-2 px-3 mb-0 mt-3 d-flex align-items-center border-0 shadow-none bg-info-subtle text-info-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                    <i class="fas fa-info-circle me-2 fs-6 text-info"></i> 
                    <span>Each Shopify variant can map to only one Amazon SKU.</span>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div class="modal-footer py-2 px-3 bg-light border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm fw-medium px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveProductMapping" class="btn btn-primary btn-sm px-4 fw-medium" disabled>
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
            
        </div>
    </div>
</div>