<div class="modal fade" id="productActionModal" tabindex="-1">

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
                        id="existingProductBtn"
                        class="btn btn-primary btn-lg">

                        Existing Shopify Product

                    </button>

                    <a
                        id="newProductBtn"
                        class="btn btn-success btn-lg"  href="">
                       
                        Add New Product to Shopify

</a>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="modal fade" id="mapShopifyProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-1">
                        <i class="fas fa-link me-2"></i>
                        Map Shopify Product
                    </h5>
                    <small class="opacity-75">
                        Select an existing Shopify product and variant to map with this Amazon SKU.
                    </small>
                </div>

                <button type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="amazonSku">

                <div class="row">

                    <!-- Product -->
                    <div class="col-md-6">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-header bg-light">
                                <strong>
                                    <i class="fas fa-box me-2 text-primary"></i>
                                    Shopify Product
                                </strong>
                            </div>

                            <div class="card-body">

                                <label class="form-label fw-semibold">
                                    Select Product
                                </label>

                                <select
                                    id="shopifyProduct"
                                    class="form-select form-select">

                                    <option value="">
                                        Select Shopify Product
                                    </option>

                                </select>

                                <small class="text-muted mt-2 d-block">
                                    Choose the Shopify product to map.
                                </small>

                            </div>

                        </div>

                    </div>

                    <!-- Variant -->
                    <div class="col-md-6">

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
                                    id="shopifyVariant"
                                    class="form-select form-select"
                                    disabled>

                                    <option value="">
                                        Select Product First
                                    </option>

                                </select>

                                <small class="text-muted mt-2 d-block">
                                    Only unmapped variants are shown.
                                </small>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="alert alert-info mt-4 mb-0">

                    <i class="fas fa-info-circle me-2"></i>

                    Each Shopify variant can be mapped only once with one Amazon SKU.

                </div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-light border"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    id="saveProductMapping"
                    class="btn btn-primary px-4"
                    disabled>

                    <i class="fas fa-save me-2"></i>

                    Save Mapping

                </button>

            </div>

        </div>
    </div>
</div>