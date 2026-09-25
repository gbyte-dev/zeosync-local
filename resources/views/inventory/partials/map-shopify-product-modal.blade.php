<!-- ========================= -->
<!-- Action Modal (Compact) -->
<!-- ========================= -->
<div class="modal fade" id="productActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-sm">
            <div class="modal-header py-2 bg-light">
                <h6 class="modal-title mb-0 fw-bold fs-6">Choose Mapping Method</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-grid gap-2">
                    <button id="existingProductBtn" class="btn btn-primary btn-sm fw-medium">
                        Existing Shopify Product
                    </button>
                    <a id="newProductBtn" class="btn btn-success btn-sm fw-medium" href="">
                        Add New Product to Shopify
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================= -->
<!-- Map Shopify Product Modal (Compact) -->
<!-- ========================= -->
<div class="modal fade" id="mapShopifyProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <!-- Header -->
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0 fw-bold fs-6">
                    <i class="fas fa-link me-2"></i>Map Shopify Product
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body p-3">
                <p class="text-muted mb-3 lh-sm" style="font-size: 0.85rem;">
                    Select an existing Shopify product and variant to map with this Amazon SKU.
                </p>
                
                <input type="hidden" id="amazonSku">

                <!-- Flex Grid for Dropdowns -->
                <!-- Row 1: Product and Variant side-by-side -->
                <div class="row g-2 mb-2">
                    
                    <!-- Shopify Product Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold mb-1 text-dark" style="font-size: 0.85rem;">
                            <i class="fas fa-box me-1 text-primary"></i> Select Product
                        </label>
                        <select id="shopifyProduct" class="form-select form-select-sm">
                            <option value="">Select Shopify Product</option>
                        </select>
                    </div>

                    <!-- Shopify Variant Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold mb-1 text-dark" style="font-size: 0.85rem;">
                            <i class="fas fa-layer-group me-1 text-success"></i> Select Variant
                        </label>
                        <select id="shopifyVariant" class="form-select form-select-sm" disabled>
                            <option value="">Select Product First</option>
                        </select>
                    </div>
                    
                </div> <!-- End Row 1 -->

                <!-- Row 2: Shop Location -->
                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <label class="form-label fw-semibold mb-1 text-dark" style="font-size: 0.85rem;">
                            <i class="fas fa-map-marker-alt me-1 text-danger"></i> Shop Location
                        </label>
                        @php
                            $modalLocations = is_array($shop->shopify_locations ?? null) ? $shop->shopify_locations : (json_decode($shop->shopify_locations ?? '[]', true) ?? []);
                            $modalSelectedIdx = (isset($shop->selected_location_index) && isset($modalLocations[$shop->selected_location_index]))
                                ? (int) $shop->selected_location_index
                                : 0;
                            $modalDefaultLocationId = $modalLocations[$modalSelectedIdx]['id'] ?? ($modalLocations[0]['id'] ?? '');
                        @endphp
                        <select id="shopifyLocation" class="form-select form-select-sm" data-default-location-id="{{ $modalDefaultLocationId }}">
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
                <div class="alert alert-info py-2 px-3 mb-0 mt-3 d-flex align-items-center" style="font-size: 0.8rem;">
                    <i class="fas fa-info-circle me-2"></i> 
                    <span>Each Shopify variant can map to only one Amazon SKU.</span>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div class="modal-footer py-2 bg-light">
                <button class="btn btn-light btn-sm border fw-medium" data-bs-dismiss="modal">Cancel</button>
                <button id="saveProductMapping" class="btn btn-primary btn-sm px-4 fw-medium" disabled>
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
            
        </div>
    </div>
</div>