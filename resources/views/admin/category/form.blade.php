{{-- Category Add/Edit Modal Form --}}
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold" id="categoryModalLabel">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="categoryForm" method="POST">
                    @csrf
                    <input type="hidden" id="category_id" name="category_id" value="">
                    <input type="hidden" id="category" name="category" value="">
                    <input type="hidden" id="slug" name="slug" value="">
                    <input type="hidden" id="marketplaceIds" name="marketplaceIds" value="">

                    {{-- Name --}}
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold small text-uppercase text-muted">Name</label>
                        <input type="text" class="form-control rounded-3" id="name" name="name"
                               placeholder="Enter category name" required>
                    </div>

                    {{-- Parent Category --}}
                    <div class="mb-3">
                        <label for="parent_id" class="form-label fw-semibold small text-uppercase text-muted">Parent Category</label>
                        <select class="form-select rounded-3" id="parent_id" name="parent_id">
                            <option value="">None (Top Level)</option>
                            @isset($parentCategories)
                                @foreach($parentCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            @endisset
                        </select>
                    </div>

                    {{-- Status --}}
                    <div class="mb-4">
                        <label for="status" class="form-label fw-semibold small text-uppercase text-muted">Status</label>
                        <select class="form-select rounded-3" id="status" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Draft</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold"
                                data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold"
                                id="categorySubmitBtn">
                            <span id="categoryBtnText">Create</span>
                            <span id="categoryBtnLoader" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {

    // ===== Auto-generate category & slug from name =====
    function generateCategoryFields(name) {
        const trimmed = name.trim();
        const category = trimmed
            ? trimmed.toUpperCase().replace(/[^A-Z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '')
            : '';
        const slug = trimmed
            ? trimmed.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '')
            : '';
        $('#category').val(category);
        $('#slug').val(slug);
    }

    $(document).on('input', '#name', function () {
        const isAdd = $('#category_id').val() === '';
        if (isAdd) {
            generateCategoryFields($(this).val());
        }
    });

    // ===== Open Add Modal =====
    $(document).on('click', '.btn-add-category', function () {
        $('#categoryForm').trigger('reset');
        $('#category_id').val('');
        $('#categoryModalLabel').text('Add Category');
        $('#categoryBtnText').text('Create');
        $('#categoryForm').attr('action', '{{ route("admin.category.create") }}');
        $('#parent_id').prop('required', false);
        generateCategoryFields('');
        $('#categoryModal').modal('show');
    });

    // ===== Open Edit Modal =====
    $(document).on('click', '.btn-edit-category', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const status = $(this).data('status');
        const parentId = $(this).data('parent-id') || '';

        $('#categoryForm').trigger('reset');
        $('#category_id').val(id);
        $('#name').val(name);
        $('#status').val(status);
        $('#parent_id').val(parentId);
        $('#categoryModalLabel').text('Edit Category');
        $('#categoryBtnText').text('Update');
        $('#categoryForm').attr('action', '{{ url("/admin/update-category") }}/' + id);
        // Hidden fields stay empty for edit (backend doesn't validate them for edits)

        $('#parent_id').prop('required', parentId ? true : false);
        $('#categoryModal').modal('show');
    });

    // ===== Submit Form via AJAX =====
    $('#categoryForm').on('submit', function (e) {
        e.preventDefault();

        const btn = $('#categorySubmitBtn');
        const text = $('#categoryBtnText');
        const loader = $('#categoryBtnLoader');

        // Disable button & show loader
        btn.prop('disabled', true);
        text.text('Saving...');
        loader.removeClass('d-none');

        const formData = $(this).serialize();
        const actionUrl = $(this).attr('action');

        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            success: function (response) {
                $('#categoryModal').modal('hide');
                // Show success toast
                if (typeof toast !== 'undefined') {
                    // Using Bootstrap toast if available
                }
                // Reload page to reflect changes
                location.reload();
            },
            error: function (xhr) {
                let errorMsg = 'Something went wrong.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    errorMsg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function () {
                btn.prop('disabled', false);
                text.text($('#category_id').val() ? 'Update' : 'Create');
                loader.addClass('d-none');
            }
        });
    });

});
</script>
@endpush