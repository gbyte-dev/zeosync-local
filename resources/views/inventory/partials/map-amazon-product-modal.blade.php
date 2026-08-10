<div class="modal fade" id="amazonProductActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header border-bottom-0">
                <div>

                    <h5 class="modal-title mb-1">Choose Mapping Method</h5>

                    <p class="mb-0 text-muted small">Select whether you want to map to an existing Amazon product or create a new one.</p>

                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <button type="button" id="existingAmazonProductBtn" class="btn btn-outline-primary w-100 text-start p-3 shadow-sm rounded-3">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <span class="d-flex align-items-center mb-2">
                                        <i class="fas fa-box-open fa-lg me-3"></i>
                                        <strong>Existing Amazon Product</strong>
                                    </span>
                                    <div class="text-muted small">Link this Shopify variant to an already listed Amazon product.</div>
                                </div>
                                <i class="fas fa-arrow-right fa-lg text-primary mt-1"></i>
                            </div>
                        </button>
                    </div>

                    <div class="col-12">
                        <button type="button" id="newAmazonProductBtn" class="btn btn-outline-success w-100 text-start p-3 shadow-sm rounded-3">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <span class="d-flex align-items-center mb-2">
                                        <i class="fas fa-plus-circle fa-lg me-3"></i>
                                        <strong>Create New Amazon Product</strong>
                                    </span>
                                    <div class="text-muted small">Create a new Amazon product record for this Shopify variant.</div>
                                </div>
                                <i class="fas fa-arrow-right fa-lg text-success mt-1"></i>
                            </div>
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </div>

</div>


<!-- ========================= -->
<!-- Existing Amazon Product Modal -->
<!-- ========================= -->

<div class="modal fade" id="mapAmazonProductModal" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header bg-primary text-white">

                <div>

                    <h5 class="modal-title mb-1">
                        <i class="fas fa-link me-2"></i>
                        Map Amazon Product
                    </h5>

                    <small class="opacity-75">
                        Link an existing Amazon product to the current Shopify variant.
                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <input
                    type="hidden"
                    id="shopifyVariantId">

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light">
                                <strong>
                                    <i class="fas fa-box me-2 text-primary"></i>
                                    Amazon Product
                                </strong>
                            </div>
                            <div class="card-body">
                                <label for="amazonProduct" class="form-label fw-semibold">Select Product</label>
                                <select
                                    id="amazonProduct"
                                    class="form-select">
                                    <option value="">
                                        Select Amazon Product
                                    </option>
                                </select>
                                <small class="text-muted mt-2 d-block">
                                    Choose the Amazon product to map.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light">
                                <strong>
                                    <i class="fas fa-info-circle me-2 text-success"></i>
                                    Mapping Details
                                </strong>
                            </div>
                            <div class="card-body">
                                <p class="mb-2 text-muted small">Current Shopify variant</p>
                                <div class="bg-white border rounded-3 p-3 mb-3" id="shopifyVariantSummary">
                                    <div class="fw-semibold">No variant selected yet</div>
                                    <div class="text-muted small">The selected Shopify variant details will appear here once you choose a product.</div>
                                </div>
                                <p class="mb-2 text-muted small">How mapping works</p>
                                <ul class="list-unstyled small mb-0">
                                    <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Each Amazon product can be linked to only one Shopify variant.</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Choose a product from your existing Amazon inventory.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0 d-flex align-items-start">
                    <i class="fas fa-info-circle me-2 mt-1"></i>
                    <div>Each Amazon product can be mapped with only one Shopify variant. Choose carefully before saving.</div>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light border"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    type="button"
                    id="saveAmazonProductMapping"
                    class="btn btn-primary px-4"
                    disabled>

                    <i class="fas fa-save me-2"></i>

                    Save Mapping

                </button>

            </div>

        </div>

    </div>

</div>