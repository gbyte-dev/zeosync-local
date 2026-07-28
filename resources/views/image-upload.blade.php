@extends('layouts.app')

@section('content')

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
    }

    .saas-wrapper {
        max-width: 1180px;
        margin: 0 auto;
        padding: 12px 16px;
    }

    /* Page Header */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
    }

    .saas-page-subtitle {
        color: #6D7175;
        font-size: 12px;
        margin: 0;
    }

    /* Gallery Grid & Cards */
    .saas-gallery-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .saas-gallery-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.06);
        border-color: #C9CCCF;
    }

    .saas-gallery-img-wrapper {
        aspect-ratio: 1 / 1;
        overflow: hidden;
        background: #F9FAFB;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #E5E7EB;
        cursor: zoom-in;
    }

    .saas-gallery-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .saas-gallery-card:hover .saas-gallery-img-wrapper img {
        transform: scale(1.05);
    }

    .saas-gallery-footer {
        padding: 8px 10px;
        font-size: 11px;
        color: #6D7175;
        background: #fff;
        display: flex;
        align-items: center;
    }

    /* Delete Button */
    .saas-delete-btn {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 26px;
        height: 26px;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
        background: #FFFFFF;
        color: #D82C0D;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 5;
        cursor: pointer;
        opacity: 0;
        /* Hidden by default, shown on hover */
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .saas-gallery-card:hover .saas-delete-btn {
        opacity: 1;
    }

    .saas-delete-btn:hover {
        background: #D82C0D;
        color: #FFFFFF;
        border-color: #D82C0D;
    }

    .saas-copy-btn {
        position: absolute;
        top: 6px;
        right: 38px;
        /* Delete button ke left me */
        width: 26px;
        height: 26px;
        border-radius: 6px;
        border: 1px solid #E5E7EB;
        background: #FFFFFF;
        color: #2C6ECB;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 5;
        cursor: pointer;
        opacity: 0;
        transition: all .2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
    }

    .saas-gallery-card:hover .saas-copy-btn {
        opacity: 1;
    }

    .saas-copy-btn:hover {
        background: #2C6ECB;
        color: #fff;
        border-color: #2C6ECB;
    }

    /* Banners & Alerts */
    .saas-banner {
        border-radius: 6px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        line-height: 1.4;
    }

    .saas-banner-info {
        background-color: #EBF5FA;
        color: #202223;
        border: 1px solid #B4E1FA;
    }

    .saas-banner-info .bi {
        color: #006FBB;
        font-size: 16px;
        line-height: 1;
    }

    /* Forms */
    .saas-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #202223;
    }

    .saas-input {
        display: block;
        width: 100%;
        height: 36px;
        padding: 6px 12px;
        font-size: 13px;
        color: #202223;
        background-color: #FFFFFF;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .saas-input:focus {
        outline: none;
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
    }

    .saas-input-group {
        display: flex;
    }

    .saas-input-group .saas-input {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right: none;
    }

    .saas-input-group .saas-btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    /* Buttons */
    .saas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        text-align: center;
    }

    .saas-btn-primary {
        background-color: #1A1A1A;
        color: #FFFFFF;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .saas-btn-primary:hover {
        background-color: #333333;
        color: #FFFFFF;
    }

    .saas-btn-outline {
        background-color: #FFFFFF;
        border-color: #C9CCCF;
        color: #202223;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .saas-btn-outline:hover {
        background-color: #F4F6F8;
    }

    /* Modals */
    .saas-modal .modal-content {
        border: none;
        border-radius: 10px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .saas-modal .modal-header {
        border-bottom: 1px solid #E5E7EB;
        padding: 16px 20px;
    }

    .saas-modal .modal-title {
        font-size: 16px;
        font-weight: 650;
        color: #202223;
        margin: 0;
    }

    .saas-modal .modal-body {
        padding: 16px 20px;
    }

    .saas-modal .modal-footer {
        border-top: 1px solid #E5E7EB;
        padding: 12px 20px;
        background: #F9FAFB;
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
    }
</style>

<div class="saas-wrapper">

    {{-- Page Header --}}
    <div class="saas-page-header">
        <div>
            <h1 class="saas-page-title">Image Upload</h1>
            <p class="saas-page-subtitle">Upload images and manage your gallery</p>
        </div>

        <button class="saas-btn saas-btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#imageUploadModal">
            <i class="bi bi-upload me-2"></i>
            Upload Image
        </button>
    </div>

    {{-- Image Grid --}}
    <div class="row g-3">
        @forelse($images as $img)
        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6">
            <div class="saas-gallery-card">
                <button
                    type="button"
                    class="saas-copy-btn"
                    title="Copy Image URL"
                    onclick="copyGalleryImage('{{ asset($img->image) }}')">

                    <i class="bi bi-copy"></i>
                </button>

                <form action="{{ route('shopify.imgupload.delete', [
    'id' => $img->id,
    'shop' => request('shop')
]) }}"
                    method="POST"
                    onsubmit="return confirm('Are you sure you want to delete this image?')">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="saas-delete-btn" title="Delete Image">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>

                <div class="saas-gallery-img-wrapper">
                    <img src="{{ asset($img->image) }}"
                        class="preview-image"
                        alt="Uploaded Image"
                        data-image="{{ asset($img->image) }}">
                </div>

                <div class="saas-gallery-footer">
                    <span>Uploaded: {{ $img->created_at->format('d M Y') }}</span>
                </div>

            </div>
        </div>

        @empty
        <div class="col-12">
            <div class="saas-banner saas-banner-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>No images uploaded yet.</div>
            </div>
        </div>
        @endforelse
    </div>

</div>

{{-- Upload Modal --}}
<div class="modal fade saas-modal" id="imageUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">

            <form action="{{ route('shopify.imgupload.store') }}?shop={{ $activeShop }}"
                method="POST"
                enctype="multipart/form-data">
                @csrf

                <div class="modal-header pb-3">
                    <h5 class="modal-title">Upload Image</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>

                <div class="px-3 pt-2">
                    <small id="imageError" class="d-block text-danger fw-semibold" style="min-height: 18px;"></small>
                </div>

                <div class="modal-body pt-1">
                    <label class="saas-label">Select Image</label>

                    <input type="file"
                        id="image"
                        name="image"
                        class="saas-input"
                        style="padding-top: 5px;"
                        accept="image/*"
                        required>

                    @error('image')
                    <small class="text-danger mt-1 d-block fw-semibold">{{ $message }}</small>
                    @enderror
                </div>

                <div class="modal-footer">
                    <button type="button"
                        class="saas-btn saas-btn-outline"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="saas-btn saas-btn-primary">
                        Upload
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

{{-- Preview Modal --}}
<div class="modal fade saas-modal" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header pb-3">
                <h5 class="modal-title">Image Preview</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center bg-light" style="border-radius: 0;">
                <img id="previewImage"
                    src=""
                    class="img-fluid rounded shadow-sm"
                    style="max-height: 400px; object-fit: contain; background: #fff; border: 1px solid #E5E7EB; padding: 4px;">
            </div>

            <div class="modal-body pt-3">
                <label class="saas-label text-start">Image URL</label>

                <div class="saas-input-group">
                    <input type="text"
                        id="previewImageUrl"
                        class="saas-input"
                        readonly>

                    <button class="saas-btn saas-btn-primary"
                        type="button"
                        onclick="copyImageUrl()">
                       <i class="fa fa-copy"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toastContainer"
    class="toast-container position-fixed bottom-0 end-0 p-3"
    style="z-index: 9999;">
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {

        document.querySelectorAll('.preview-image').forEach(function(img) {
            img.addEventListener('click', function() {
                let imageUrl = this.getAttribute('data-image');

                document.getElementById('previewImage').src = imageUrl;
                document.getElementById('previewImageUrl').value = imageUrl;

                let modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
                modal.show();
            });
        });

    });

    function copyImageUrl() {
        let input = document.getElementById('previewImageUrl');

        navigator.clipboard.writeText(input.value).then(function() {
            showCopyToast();
        });
    }

    function copyGalleryImage(url) {
        navigator.clipboard.writeText(url).then(function() {
            showCopyToast();
        });
    }

    function showCopyToast() {
        let toastContainer = document.getElementById('toastContainer');

        let toastHtml = `
            <div class="toast align-items-center text-bg-success border-0"
                 role="alert"
                 aria-live="assertive"
                 aria-atomic="true"
                 data-bs-autohide="true"
                 data-bs-delay="2000">

                <div class="d-flex">
                    <div class="toast-body" style="font-size: 13px; font-weight: 500;">
                        Image URL copied successfully!
                    </div>

                    <button type="button"
                            class="btn-close btn-close-white me-2 m-auto shadow-none"
                            style="font-size: 10px;"
                            data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        toastContainer.innerHTML = toastHtml;

        let toastEl = toastContainer.querySelector('.toast');
        let toast = new bootstrap.Toast(toastEl);

        toast.show();

        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }

    document.getElementById('image').addEventListener('change', function(e) {

        const file = e.target.files[0];
        const error = document.getElementById('imageError');

        error.innerHTML = '';
        error.classList.remove('text-danger', 'text-warning');

        if (!file) {
            return;
        }

        const img = new Image();

        img.onload = function() {

            if (this.width < 1000 || this.height < 1000) {
                error.classList.add('text-danger');
                error.innerHTML = 'Minimum image size is 1000 × 1000 pixels.';
                e.target.value = '';
                return;
            }

            if (this.width > 10000 || this.height > 10000) {
                error.classList.add('text-danger');
                error.innerHTML = 'Maximum image size is 10000 × 10000 pixels.';
                e.target.value = '';
                return;
            }

            if (this.width < 2000 || this.height < 2000) {
                error.classList.add('text-warning');
                error.innerHTML = 'Recommended image size is at least 2000 × 2000 pixels for better zoom.';
                return;
            }
        };

        img.src = URL.createObjectURL(file);
    });
</script>
@endpush