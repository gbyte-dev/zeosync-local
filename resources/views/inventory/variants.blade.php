@extends('layouts.app')

@section('content')

<style>
    body {
        background: #f5f7fb;
    }

    .inventory-page {
        padding: 24px;
    }

    .inventory-hero {
        background: linear-gradient(135deg, #111827, #2563eb);
        color: #fff;
        border-radius: 22px;
        padding: 30px;
        margin-bottom: 24px;
        box-shadow: 0 18px 40px rgba(37, 99, 235, .18);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #fff;
        border-radius: 20px;
        padding: 22px;
        border: 1px solid #eef2f7;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .08);
    }

    .stat-label {
        color: #6b7280;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .stat-value {
        font-size: 30px;
        font-weight: 800;
        color: #111827;
        word-break: break-all;
    }

    .inventory-card {
        background: #fff;
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .08);
    }

    .toolbar {
        padding: 20px;
        border-bottom: 1px solid #eef2f7;
        background: #fff;
    }

    .parent-info-box {
        background: #f8fafc;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        padding: 16px;
        margin-top: 16px;
    }

    .table th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding: 16px;
        white-space: nowrap;
    }

    .table td {
        padding: 16px;
        vertical-align: middle;
        white-space: nowrap;
    }

    .qty-input {
        width: 90px;
        text-align: center;
        border-radius: 10px;
        border: 1px solid #dbe3ef;
    }

    .soft-badge {
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
    }

    .btn-action {
        border-radius: 10px;
        font-weight: 700;
        padding: 6px 16px;
    }

    @media(max-width:992px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width:576px) {
        .inventory-page {
            padding: 14px;
        }

        .inventory-hero {
            padding: 22px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="inventory-page">

    <div class="inventory-hero">
        <h6 class="fw-bold mb-1">Amazon Variant Inventory</h6>
        <small class="mb-0 opacity-75">Manage individual variant inventory for the selected Amazon parent product.</small>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Parent SKU</div>
            <div class="stat-value" style="font-size: 22px;">{{ $parentSku }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Variants</div>
            <div class="stat-value text-primary">{{ count($variants) }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Quantity</div>
            <div class="stat-value text-info">{{ array_sum(array_column($variants, 'quantity')) ?? 0 }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Inventory Status</div>
            <div class="stat-value text-success" style="font-size: 24px;">Active</div>
        </div>
    </div>

    <div class="inventory-card">

        <div class="toolbar">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <a href="{{ url()->previous() }}" class="btn btn-light border fw-bold" style="border-radius:12px;padding:11px 20px;">
                    <i class="fa fa-arrow-left me-1"></i> Back to Inventory
                </a>

                <button class="btn btn-primary fw-bold" style="border-radius:12px;padding:11px 24px;">
                    Sync All Variants
                </button>
            </div>

            <div class="parent-info-box">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <div class="small fw-bold text-muted mb-1">Parent SKU</div>
                        <div class="fw-bold text-dark">{{ $parentSku }}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small fw-bold text-muted mb-1">Parent ASIN</div>
                        <div class="fw-bold text-dark">{{ $parentAsin ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small fw-bold text-muted mb-1">Variation Theme</div>
                        <div class="fw-bold text-dark">{{ $variationTheme ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small fw-bold text-muted mb-1">Parent Product</div>
                        <div style="font-size: 12px;" class="fw-bold text-dark">
                            {{ $parentName }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>ASIN</th>
                        <th>Quantity</th>
                        <!-- <th>Status</th>
                        <th>Issues</th> -->
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($variants as $variant)
                    <tr>
                        <td class="fw-bold text-dark">{{ $variant['sku'] }}</td>
                        <td>{{ $variant['color'] ?? '-' }}</td>
                        <td>{{ $variant['size'] ?? '-' }}</td>
                        <td>{{ $variant['asin'] ?? '-' }}</td>

                        <td>
                            <input type="number"
                                class="form-control form-control-sm qty-input"
                                value="{{ $variant['quantity'] ?? 0 }}"
                                data-sku="{{ $variant['sku'] }}">
                        </td>

                        <!-- <td>
                            @if(strtolower($variant['status'] ?? '') === 'active')
                            <span class="soft-badge bg-success-subtle text-success">Active</span>
                            @elseif(strtolower($variant['status'] ?? '') === 'pending')
                            <span class="soft-badge bg-warning-subtle text-warning">Pending</span>
                            @else
                            <span class="soft-badge bg-danger-subtle text-danger">Error</span>
                            @endif
                        </td>

                        <td>
                            @if(!empty($variant['issues']))
                            <span class="badge bg-danger rounded-pill">{{ $variant['issues'] }}</span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td> -->

                        <td>
                            <button class="btn btn-primary btn-sm btn-action">
                                Update
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <div class="mb-2 fs-5">📦</div>
                            No variants found for this parent product.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
    // Include any variant-specific scripts here if needed.
    // Ensure the shop parameter is preserved in API calls if applicable.
    const shop = new URLSearchParams(window.location.search).get('shop') || '{{ $shop ?? '
    ' }}';
</script>
@endpush