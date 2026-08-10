<!-- ========================= -->
<!-- Action Modal (Compact) -->
<!-- ========================= -->
<div class="modal fade" id="amazonProductActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-sm">
            <div class="modal-header py-2 bg-light">
                <h6 class="modal-title mb-0 fw-bold fs-6">Choose Mapping Method</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-grid gap-2">
                    <button id="existingAmazonProductBtn" class="btn btn-primary btn-sm fw-medium">
                        Existing Amazon Product
                    </button>
                    <button id="newAmazonProductBtn" class="btn btn-success btn-sm fw-medium">
                        Create New Amazon Product
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================= -->
<!-- Map Amazon Product Modal (Full & Compact) -->
<!-- ========================= -->
<div class="modal fade" id="mapAmazonProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <!-- Header -->
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0 fw-bold fs-6">
                    <i class="fas fa-link me-2"></i>Map Amazon Product
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body p-3">
                <p class="text-muted mb-3 lh-sm" style="font-size: 0.85rem;">
                    Select an existing Amazon product and variant to map with this Shopify variant.
                </p>
                
                <input type="hidden" id="shopifyVariantId">

                <!-- Flex Grid for Dropdowns (Restored Variant Section) -->
                <div class="row g-2 mb-2">
                    
                    <!-- Amazon Product Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold mb-1 text-dark" style="font-size: 0.85rem;">
                            <i class="fas fa-box me-1 text-primary"></i> Select Product
                        </label>
                        <select id="amazonProduct" class="form-select form-select-sm">
                            <option value="">Select Amazon Product</option>
                        </select>
                    </div>

                    <!-- Amazon Variant Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold mb-1 text-dark" style="font-size: 0.85rem;">
                            <i class="fas fa-layer-group me-1 text-success"></i> Select Variant
                        </label>
                        <select id="amazonVariant" class="form-select form-select-sm" disabled>
                            <option value="">Select Product First</option>
                        </select>
                    </div>
                    
                </div> <!-- End Row -->

                <!-- Compact Info Alert -->
                <div class="alert alert-info py-2 px-3 mb-0 mt-3 d-flex align-items-center" style="font-size: 0.8rem;">
                    <i class="fas fa-info-circle me-2"></i> 
                    <span>Each Amazon product can map to only one Shopify variant.</span>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div class="modal-footer py-2 bg-light">
                <button class="btn btn-light btn-sm border fw-medium" data-bs-dismiss="modal">Cancel</button>
                <button id="saveAmazonProductMapping" class="btn btn-primary btn-sm px-4 fw-medium" disabled>
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
            
        </div>
    </div>
</div>