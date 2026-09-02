@extends('layouts.app')

@section('content')

<style>
    /* Scoped Page Styles */
    .zeo-inventory-page {
        background-color: #f4f6f8;
        min-height: 100vh;
        padding: 24px 32px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* Page Header Card */
    .zeo-page-header {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px 24px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .zeo-page-header h1 {
        margin: 0 0 4px 0;
        font-size: 20px;
        font-weight: 600;
        color: #111827;
    }

    .zeo-page-header p {
        margin: 0;
        color: #6b7280;
        font-size: 13px;
    }

    /* Stats Grid */
    .zeo-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 16px;
    }

    .zeo-stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .zeo-stat-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .zeo-stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #111827;
        line-height: 1;
    }

    .zeo-stat-status {
        font-size: 14px;
        font-weight: 600;
        color: #059669;
        display: flex;
        align-items: center;
    }

    /* Main Table Card */
    .zeo-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        overflow: hidden;
    }

    .zeo-card-body {
        padding: 12px 16px 16px 16px;
    }

    /* Table Styles - Vertically Tight */
    #amazonLowInventoryTable {
        width: 100% !important;
        border-collapse: collapse;
        margin-top: 4px !important;
        margin-bottom: 12px !important;
    }

    #amazonLowInventoryTable thead th {
        background: transparent;
        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 8px 12px 8px 0;
        border-top: none;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    #amazonLowInventoryTable tbody td {
        padding: 8px 12px 8px 0;
        font-size: 13px;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    #amazonLowInventoryTable tbody tr:last-child td {
        border-bottom: none;
    }

    #amazonLowInventoryTable tbody tr:hover {
        background-color: #f9fafb;
    }

    /* Truncated Product Title */
    .product-title {
        display: block;
        max-width: 240px;
        font-weight: 500;
        font-size: 12px !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: default;
    }

    /* SKU Text */
    .sku-text {
        color: #4b5563;
        font-size: 12px;
    }

    /* Quantity Badge */
    .quantity-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 11px;
        font-weight: 600;
        font-size: 11px;
    }

    .quantity-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .quantity-warning {
        background: #fef3c7;
        color: #92400e;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
        font-size: 13px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }

    /* DataTables Overrides - Compact Controls & Full Width Search */
    .dataTables_wrapper .row {
        align-items: center;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 12px;
    }

    .dataTables_wrapper .dataTables_length label {
        color: #4b5563;
        font-size: 12px;
        font-weight: 400;
        display: flex;
        align-items: center;
        margin: 0;
    }

    .dataTables_wrapper .dataTables_length select {
        margin: 0 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 2px 20px 2px 8px;
        font-size: 12px;
        height: 28px;
        color: #374151;
        background-color: #fff;
        outline: none;
    }

    .dataTables_wrapper .dataTables_filter {
        width: 100%;
    }

    .dataTables_wrapper .dataTables_filter label {
        width: 100%;
        display: flex;
        margin: 0;
    }

    .dataTables_wrapper .dataTables_filter input {
        width: 100%;
        margin-left: 0;
        height: 34px;
        box-sizing: border-box;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 0 12px;
        font-size: 12px;
        color: #374151;
        outline: none;
        transition: border-color 0.15s ease-in-out;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #9ca3af;
        box-shadow: none;
    }

    .dataTables_wrapper .dataTables_info {
        color: #6b7280;
        font-size: 12px;
        padding-top: 8px !important;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 4px !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 4px !important;
        margin-left: 2px !important;
        border: 1px solid transparent !important;
        font-size: 12px !important;
        color: #4b5563 !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #f3f4f6 !important;
        color: #111827 !important;
        border: 1px solid #e5e7eb !important;
        font-weight: 500;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
        background: #f9fafb !important;
        color: #111827 !important;
        border: 1px solid #d1d5db !important;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .zeo-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .zeo-inventory-page {
            padding: 16px;
        }

        .zeo-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="zeo-inventory-page">

    <div class="zeo-page-header">
        <h1>Amazon Low Inventory</h1>
        <p>Products with Amazon inventory below 10 units</p>
    </div>

    @if($amazonLowInventoryProducts->isNotEmpty())

    <div class="zeo-stats-grid">
        <div class="zeo-stat-card">
            <span class="zeo-stat-label">Low Inventory</span>
            <span class="zeo-stat-value">{{ $amazonLowInventoryProducts->count() }}</span>
        </div>
        <div class="zeo-stat-card">
            <span class="zeo-stat-label">Mapped Products</span>
            <span class="zeo-stat-value">3</span>
        </div>
        <div class="zeo-stat-card">
            <span class="zeo-stat-label">Orders</span>
            <span class="zeo-stat-value">0</span>
        </div>
        <div class="zeo-stat-card">
            <span class="zeo-stat-label">Sync Status</span>
            <span class="zeo-stat-status">● Connected</span>
        </div>
    </div>

    <div class="zeo-card">
        <div class="zeo-card-body">
            <div class="table-responsive">
                <table id="amazonLowInventoryTable" class="table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($amazonLowInventoryProducts as $product)
                        <tr>
                            <td>
                                @php
                                $title = $product['title'] ?? '-';
                                $shortTitle = mb_strlen($title) > 25
                                ? mb_substr($title, 0, 25) . '...'
                                : $title;
                                @endphp
                                <div class="product-title" title="{{ $title }}">
                                    {{ $shortTitle }}
                                </div>
                            </td>
                            <td>
                                <span class="sku-text">
                                    {{ $product['sku'] ?? '-' }}
                                </span>
                            </td>
                            <td>
                                @php
                                $qty = $product['quantity'] ?? 0;
                                $badgeClass = $qty == 0 ? 'quantity-danger' : 'quantity-warning';
                                @endphp
                                <span class="quantity-badge {{ $badgeClass }}">
                                    {{ $qty }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @else
    <div class="empty-state">
        No product inventory is low.
    </div>
    @endif

</div>

@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        if ($('#amazonLowInventoryTable').length) {
            $('#amazonLowInventoryTable').DataTable({
                responsive: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                order: [
                    [2, 'asc']
                ],
                columnDefs: [{
                        targets: 0,
                        width: '55%'
                    },
                    {
                        targets: 1,
                        width: '35%'
                    },
                    {
                        targets: 2,
                        width: '10%',
                        type: 'num'
                    }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search Product / SKU...",
                    lengthMenu: "_MENU_"
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
            });
        }
    });
</script>
@endpush