@extends('layouts.app')

@section('content')

<style>
    /* Shopify Admin Inspired UI - Ultra Compact & Tight */
    .sp-page {
        background-color: #F6F6F7;
        padding: 16px 20px;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, sans-serif;
    }

    /* Header Section */
    .sp-header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        min-height: 48px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .sp-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        letter-spacing: -0.01em;
        margin: 0 0 2px 0;
        line-height: 1.2;
    }

    .sp-subtitle {
        font-size: 13px;
        color: #6B7280;
        margin: 0;
    }

    /* Step Badge */
    .sp-step-badge {
        display: inline-flex;
        align-items: center;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 500;
        color: #4B5563;
        gap: 6px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    /* Card */
    .sp-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        /* max-width: 800px; */
        /* Constrain width for better reading */
    }

    /* Form Elements */
    .sp-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
    }

    .sp-help-text {
        font-size: 12px;
        color: #6B7280;
        margin-top: 6px;
    }

    /* Actions */
    .sp-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #E5E7EB;
    }

    .sp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        cursor: pointer;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid transparent;
        box-sizing: border-box;
    }

    .sp-btn i {
        font-size: 13px;
        display: flex;
        align-items: center;
    }

    .sp-btn-primary {
        background-color: #111827;
        color: #FFFFFF;
        border-color: #111827;
    }

    .sp-btn-primary:hover {
        background-color: #374151;
        color: #FFFFFF;
    }

    .sp-btn-secondary {
        background-color: #FFFFFF;
        color: #111827;
        border-color: #E5E7EB;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .sp-btn-secondary:hover {
        background-color: #F9FAFB;
        border-color: #D1D5DB;
    }

    /* Select2 UI Overrides for Shopify Style */
    .select2-container--bootstrap-5 .select2-selection {
        font-size: 13px !important;
        min-height: 32px !important;
        border: 1px solid #D1D5DB !important;
        border-radius: 6px !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01) inset !important;
    }

    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #2563EB !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #111827 !important;
        line-height: 22px !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 30px !important;
    }

    .select2-results__option {
        font-size: 13px !important;
        padding: 6px 10px !important;
    }

    .select2-search__field {
        font-size: 13px !important;
    }

    .select2-dropdown {
        border-color: #D1D5DB !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
    }

    .dashboard-header-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 20px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
</style>

<div class="sp-page">

    <!-- Header -->
    <div class="sp-header-section dashboard-header-card">
        <div>
            <h1 class="sp-title" style="font-size:medium">Select Product Category</h1>
            <p class="sp-subtitle">
                Choose the Amazon product category before creating your product listing.
            </p>
        </div>

        <div>
            <div class="sp-step-badge">
                <i class="bi bi-layers"></i>
                <span>Step 1 of 2: Category Selection</span>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="sp-card">
        <form action="{{ route('user.addProductCategory', [
    'shop' => $activeShop
]) }}" method="POST">
            @csrf
            <input type="hidden" name="shop" value="{{ $activeShop }}">

            <div style="margin-bottom: 8px;">
                <label for="category_id" class="sp-label">
                    Amazon Category
                </label>

                <select id="category_id" name="category_id" class="form-select" required>
                    <option value="">Search and select a category...</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ ucfirst(str_replace('_', ' ', strtolower($category->product_type))) }}
                    </option>
                    @endforeach
                </select>

                <div class="sp-help-text">
                    Start typing to search for a category.
                </div>
            </div>

            <!-- Actions -->
            <div class="sp-actions">
                <a href="{{ url()->previous() }}" class="sp-btn sp-btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>

                <button type="submit" class="sp-btn sp-btn-primary">
                    Continue <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>

</div>

@endsection

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('#category_id').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search and select a category...',
            allowClear: true
        });
    });
</script>
@endpush