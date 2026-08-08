<div class="modal fade" id="amazonProductActionModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Choose Mapping Method
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="d-grid gap-3">

                    <button
                        id="existingAmazonProductBtn"
                        class="btn btn-primary btn-lg">

                        Existing Amazon Product

                    </button>

                    <button
                        id="newAmazonProductBtn"
                        class="btn btn-success btn-lg">

                        Create New Amazon Product

                    </button>

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
                        Select an existing Amazon product and variant to map with this Shopify variant.
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

                <div class="row">

                    <!-- Amazon Product -->

                    <div class="col-md-12">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-header bg-light">

                                <strong>

                                    <i class="fas fa-box me-2 text-primary"></i>

                                    Amazon Product

                                </strong>

                            </div>

                            <div class="card-body">

                                <label class="form-label fw-semibold">
                                    Select Product
                                </label>

                                <select
                                    id="amazonProduct"
                                    class="form-select form-select">

                                    <option value="">
                                        Select Amazon Product
                                    </option>

                                </select>

                                <small class="text-muted mt-2 d-block">
                                    Choose the Amazon product to map.
                                </small>

                            </div>

                        </div>

                    </div>

                    <!-- Variant -->

                    <!-- <div class="col-md-6">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-header bg-light">

                                <strong>

                                    <i class="fas fa-layer-group me-2 text-success"></i>

                                    Product Variant

                                </strong>

                            </div>

                            <div class="card-body">

                                <label class="form-label fw-semibold">
                                    Select Variant
                                </label>

                                <select
                                    id="amazonVariant"
                                    class="form-select form-select-lg"
                                    disabled>

                                    <option value="">
                                        Select Product First
                                    </option>

                                </select>

                                <small class="text-muted mt-2 d-block">
                                    Only variants of the selected Amazon product are shown.
                                </small>

                            </div>

                        </div>

                    </div> -->

                </div>

                <div class="alert alert-info mt-4 mb-0">

                    <i class="fas fa-info-circle me-2"></i>

                    Each Amazon product can be mapped with only one Shopify variant.

                </div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-light border"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
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