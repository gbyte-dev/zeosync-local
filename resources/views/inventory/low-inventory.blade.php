@extends('layouts.app')

@section('content')

<style>
    /* Scoped Page Styles */
    .zeo-inventory-page {
        background-color: #f4f6f8;
        min-height: 100vh;
        padding: 32px 40px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* Page Level Header */
    .zeo-page-header {
        margin-bottom: 24px;
    }

    .zeo-page-header h1 {
        margin: 0 0 6px 0;
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        line-height: 1.2;
    }

    .zeo-page-header p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    /* Main Card */
    .zeo-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .zeo-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px 0 24px;
    }

    .zeo-card-title {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }

    .zeo-product-count {
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
    }

    .zeo-card-body {
        padding: 16px 24px 24px 24px;
    }

    /* Table Styles */
    #amazonLowInventoryTable {
        width: 100% !important;
        border-collapse: collapse;
        margin-top: 10px !important;
        margin-bottom: 16px !important;
    }

    #amazonLowInventoryTable thead th {
        background: transparent;
        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 16px 12px 0;
        border-top: none;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    #amazonLowInventoryTable tbody td {
        padding: 14px 16px 14px 0;
        font-size: 14px;
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
        max-width: 260px;
        font-size: 14px;
        color: #374151;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: default;
    }

    /* SKU Text */
    .sku-text {
        color: #4b5563;
        font-size: 13px;
    }

    /* Quantity Badge */
    .quantity-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 8px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 12px;
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
        padding: 60px 20px;
        color: #6b7280;
        font-size: 14px;
    }

    /* DataTables Overrides for a clean UI */
    .dataTables_wrapper .row {
        align-items: center;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 16px;
    }

    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        color: #4b5563;
        font-size: 13px;
        font-weight: 400;
        display: flex;
        align-items: center;
    }

    .dataTables_wrapper .dataTables_length select {
        margin: 0 8px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 4px 24px 4px 8px;
        font-size: 13px;
        color: #374151;
        background-color: #fff;
        outline: none;
    }

    .dataTables_wrapper .dataTables_filter input {
        margin-left: 8px;
        width: 220px;
        height: 32px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 0 12px;
        font-size: 13px;
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
        font-size: 13px;
        padding-top: 16px !important;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 12px !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 6px !important;
        padding: 4px 10px !important;
        margin-left: 4px !important;
        border: 1px solid transparent !important;
        font-size: 13px !important;
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
    @media (max-width: 768px) {
        .zeo-inventory-page {
            padding: 24px 16px;
        }

        .zeo-card-header {
            padding: 16px 16px 0 16px;
        }

        .zeo-card-body {
            padding: 16px;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 160px;
        }
    }
</style>

<div class="zeo-inventory-page">

    <div class="zeo-page-header">
        <h1>Amazon Low Inventory</h1>
        <p>Products with Amazon inventory below 10 units</p>
    </div>

    <div class="zeo-card">
        @if($amazonLowInventoryProducts->isNotEmpty())
        <div class="zeo-card-header">
            <h2 class="zeo-card-title">Low Inventory Products</h2>
            <span class="zeo-product-count">{{ $amazonLowInventoryProducts->count() }} products</span>
        </div>

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
                                $shortTitle = mb_strlen($title) > 20
                                ? mb_substr($title, 0, 20) . '...'
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
        @else
        <div class="empty-state">
            No product inventory is low.
        </div>
        @endif
    </div>

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
                        width: '50%'
                    },
                    {
                        targets: 1,
                        width: '35%'
                    },
                    {
                        targets: 2,
                        width: '15%',
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