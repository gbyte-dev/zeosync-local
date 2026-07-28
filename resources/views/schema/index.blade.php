@extends('admin.layout.app')

@section('title', 'Add to Amazon')

@section('content')

<div class="container">

    <!-- Header -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary text-white" style="    background: linear-gradient(135deg, #111827, #2563eb);">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">

                <div>
                    <span class="badge bg-light text-primary rounded-pill px-3 py-2 mb-3">
                        <i class="fas fa-file-code me-2"></i>Admin Panel
                    </span>

                    <h2 class="fw-bold mb-1">
                        Amazon Product Sync Available 
                    </h2>

                    <p class="mb-0 text-white-50">
                        Manage uploaded Amazon product schemas and create products from active schemas.
                    </p>
                </div>

                <div class="text-end">
                    <a href="{{ route('admin.schema.create') }}"
                       class="btn btn-light btn-lg">
                        <i class="fas fa-upload me-2"></i>
                        Upload Schema
                    </a>
                </div>

            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4">

        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">

                <h5 class="mb-0 fw-semibold">
                    <i class="fas fa-list text-primary me-2"></i>
                   Product Sync Amazon Type Available 
                </h5>

                <span class="badge bg-primary fs-6">
                    {{ $schemas->count() }} Schemas
                </span>

            </div>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th width="80">#</th>
                        <th>Product Type</th>
                        <th>Schema Version</th>
                        <th>Status</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($schemas as $schema)

                    <tr>

                        <td>
                            <strong>{{ $schema->id }}</strong>
                        </td>

                        <td>
                            {{ $schema->product_type }}
                        </td>

                        <td>
                            <span class="badge bg-secondary">
                                {{ $schema->schema_version }}
                            </span>
                        </td>

                        <td>

                            @if($schema->is_active)

                                <span class="badge bg-success">
                                    Active
                                </span>

                            @else

                                <span class="badge bg-secondary">
                                    Inactive
                                </span>

                            @endif

                        </td>

                        <td>

                            @if($schema->is_active)

                                <a href="{{ route('admin.product.store', $schema->id) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus-circle me-1"></i>
                                    Add Product
                                </a>

                            @else

                                <span class="text-muted small">
                                    —
                                </span>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="5" class="text-center py-5">

                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>

                            <h5 class="text-muted">
                                No Schemas Found
                            </h5>

                            <p class="text-muted mb-3">
                                Upload your first Amazon schema to get started.
                            </p>

                            <a href="{{ route('admin.schema.create') }}"
                               class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>
                                Upload Schema
                            </a>

                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection