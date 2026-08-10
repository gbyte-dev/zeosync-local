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
                    
                </div> <!-- End Row -->

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