<!-- ========================= -->
<!-- Action Modal (Compact) -->
<!-- ========================= -->
<div class="modal fade" id="amazonProductActionModal" tabindex="-1" aria-labelledby="amazonProductActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header py-2 px-3 bg-light border-bottom">
                <h6 class="modal-title mb-0 fw-bold fs-6 text-dark" id="amazonProductActionModalLabel">Choose Mapping Method</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-grid gap-2">
                    <button id="existingAmazonProductBtn" class="btn btn-primary btn-sm fw-medium py-2 shadow-sm">
                        <i class="fas fa-boxes me-1"></i> Existing Amazon Product
                    </button>
                    <button id="newAmazonProductBtn" class="btn btn-success btn-sm fw-medium py-2 shadow-sm">
                        <i class="fas fa-plus-circle me-1"></i> Create New Amazon Product
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================= -->
<!-- Map Amazon Product Modal (Full & Compact) -->
<!-- ========================= -->
<div class="modal fade" id="mapAmazonProductModal" tabindex="-1" aria-labelledby="mapAmazonProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            
            <!-- Header -->
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title mb-0 fw-bold fs-6 d-flex align-items-center" id="mapAmazonProductModalLabel">
                    <i class="fas fa-link me-2"></i>Map Amazon Product
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body p-3">
                <p class="text-muted mb-3 lh-sm" style="font-size: 0.85rem;">
                    Select the Amazon product you want to map to this Shopify variant.
                </p>
                
                <input type="hidden" id="shopifyVariantId">

                <!-- Status / Alert Container -->
                <div id="amazonMappingStatusContainer" class="mb-3" style="display: none;"></div>

                <!-- Single Amazon Product Selector Section -->
                <div class="mb-2">
                    <label for="amazonProduct" class="form-label fw-semibold mb-1 text-dark d-flex align-items-center" style="font-size: 0.85rem;">
                        <i class="fas fa-box me-1 text-primary"></i> Select Amazon Product
                        <span id="amazonProductLoadingSpinner" class="spinner-border spinner-border-sm text-primary ms-2 align-middle" role="status" style="display: none; width: 0.85rem; height: 0.85rem;"></span>
                    </label>
                    <select id="amazonProduct" class="form-select form-select-sm" style="width: 100%;">
                        <option value="">Select Amazon Product</option>
                    </select>

                    <!-- Selected Amazon Product Summary (Compact) -->
                    <div id="selectedAmazonProductSummary" class="card bg-light border p-2 mt-2" style="display: none; border-radius: 8px;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Selected Amazon Product</span>
                            <span class="badge bg-success text-white px-2 py-0" style="font-size: 0.7rem;">Ready to map</span>
                        </div>
                        <div id="selectedAmazonTitle" class="fw-semibold text-dark text-truncate mb-1" style="font-size: 0.82rem;"></div>
                        <div class="d-flex flex-wrap gap-2 text-muted" style="font-size: 0.75rem;">
                            <div><strong>SKU:</strong> <span id="selectedAmazonSku" class="text-primary fw-medium font-monospace"></span></div>
                            <div id="selectedAmazonAsinWrap" style="display: none;"><strong>ASIN:</strong> <span id="selectedAmazonAsin" class="text-secondary font-monospace"></span></div>
                        </div>
                    </div>
                </div>

                <!-- Compact Info Alert -->
                <div class="alert alert-info py-2 px-3 mb-0 mt-3 d-flex align-items-center border-0 shadow-none bg-info-subtle text-info-emphasis" style="font-size: 0.8rem; border-radius: 8px;">
                    <i class="fas fa-info-circle me-2 fs-6 text-info"></i> 
                    <span>Each Amazon product can map to only one Shopify variant.</span>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div class="modal-footer py-2 px-3 bg-light border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm fw-medium px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveAmazonProductMapping" class="btn btn-primary btn-sm px-4 fw-medium" disabled>
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
            
        </div>
    </div>
</div>