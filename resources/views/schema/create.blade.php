@extends('admin.layout.app')

@section('title', 'Schema Upload')
@section('content')

<div class="container">

    <!-- Header -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white" style="    background: linear-gradient(135deg, #111827, #2563eb);">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">

                <div>

                    <h2 class="fw-bold mb-1">
                        Upload Amazon Schema
                    </h2>

                    <p class="mb-0 text-white-50">
                        Upload Amazon product schema JSON files for validation and product creation.
                    </p>
                </div>

                <div class="bg-white rounded-4 shadow-sm text-center p-4">
                    <i class="fas fa-upload fa-2x text-primary mb-2"></i>

                    <small class="text-muted">
                        use Supported Format
                    </small>
                </div>

            </div>
        </div>
    </div>

    <!-- Upload Card -->
    <div class="card border-0 shadow-sm rounded-4">

        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-semibold">
                <i class="fas fa-cloud-upload-alt text-primary me-2"></i>
                Upload Schema File
            </h5>
        </div>

        <div class="card-body">

            <form
                action="{{ route('product-schemas.store') }}"
                method="POST"
                enctype="multipart/form-data">

                @csrf

                <div class="mb-4">

                    <label class="form-label fw-semibold">
                        Amazon Schema JSON
                    </label>

                    <input
                        type="file"
                        name="schema_file"
                        accept=".json"
                        class="form-control form-control-lg"
                        required>

                    <div class="form-text">
                        Only <strong>.json</strong> schema files are supported.
                    </div>

                </div>

                <div class="d-flex justify-content-end">

                    <button
                        type="submit"
                        class="btn btn-primary px-4">

                        <i class="fas fa-upload me-2"></i>
                        Upload Schema

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection