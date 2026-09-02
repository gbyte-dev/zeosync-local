@extends('layouts.app')

@section('content')

<style>
    .low-inventory-page {
        background: #f6f7f9;
        min-height: 100vh;
        padding: 28px 48px;
    }

    .inventory-header {
        background: #fff;
        border: 1px solid #e1e4e8;
        border-radius: 14px;
        padding: 28px 22px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .inventory-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #111827;
    }

    .inventory-header p {
        margin: 6px 0 0;
        color: #6b7280;
        font-size: 14px;
    }

    .inventory-card {
        background: #fff;
        border: 1px solid #e1e4e8;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .inventory-card-header {
        padding: 20px 22px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .inventory-card-header h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }

    .inventory-card-header span {
        font-size: 13px;
        color: #6b7280;
    }

    .inventory-table-wrapper {
        padding: 20px 22px;
    }

    #amazonLowInventoryTable {
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0;
    }

    #amazonLowInventoryTable thead th {
        background: #f8fafc;
        color: #4b5563;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 13px 14px;
        border-top: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }

    #amazonLowInventoryTable tbody td {
        padding: 15px 14px;
        font-size: 14px;
        color: #111827;
        border-bottom: 1px solid #eef0f2;
        vertical-align: middle;
    }

    #amazonLowInventoryTable tbody tr:hover {
        background: #fafafa;
    }

    .product-title {
        font-weight: 500;
        color: #111827;
    }

    .sku-text {
        color: #4b5563;
        font-family: monospace;
        font-size: 13px;
    }

    .quantity-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        padding: 5px 10px;
        border-radius: 7px;
        background: #fff3cd;
        color: #9a6700;
        font-weight: 600;
        font-size: 13px;
    }

    .empty-inventory {
        text-align: center;
        padding: 50px 20px;
        color: #6b7280;
        font-size: 15px;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 18px;
    }

    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        color: #4b5563;
        font-size: 13px;
    }

    .dataTables_wrapper .dataTables_length select {
        margin: 0 5px;
        min-width: 75px;
        border: 1px solid #d1d5db;
        border-radius: 7px;
        padding: 6px 28px 6px 9px;
    }

    .dataTables_wrapper .dataTables_filter input {
        margin-left: 8px;
        width: 240px;
        height: 36px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0 12px;
        outline: none;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #9ca3af;
        box-shadow: none;
    }

    .dataTables_wrapper .dataTables_info {
        color: #6b7280;
        font-size: 13px;
        padding-top: 18px;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 12px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 7px !important;
        margin-left: 3px;
        border: 1px solid transparent !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #2563eb !important;
        color: #fff !important;
        border: 1px solid #2563eb !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f3f4f6 !important;
        color: #111827 !important;
        border: 1px solid #d1d5db !important;
    }

    @media (max-width: 768px) {
        .low-inventory-page {
            padding: 20px 15px;
        }

        .inventory-table-wrapper {
            padding: 15px;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 180px;
        }
    }
</style>

<div class="low-inventory-page">

    <div class="inventory-header">
        <h2>Amazon Low Inventory</h2>
        <p>Products with Amazon inventory below 10 units</p>
    </div>

    <div class="inventory-card">

        <div class="inventory-card-header">
            <h4>Low Inventory Products</h4>

            @if($amazonLowInventoryProducts->isNotEmpty())
            <span>
                {{ $amazonLowInventoryProducts->count() }} products
            </span>
            @endif
        </div>

        <div class="inventory-table-wrapper">

            @if($amazonLowInventoryProducts->isNotEmpty())

            <div class="table-responsive">

                <table id="amazonLowInventoryTable"
                    class="table"
                    style="width:100%">

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
                                <div class="product-title">
                                    {{ $product['title'] ?? '-' }}
                                </div>
                            </td>

                            <td>
                                <span class="sku-text">
                                    {{ $product['sku'] ?? '-' }}
                                </span>
                            </td>

                            <td>
                                <span class="quantity-badge">
                                    {{ $product['quantity'] ?? 0 }}
                                </span>
                            </td>
                        </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            @else

            <div class="empty-inventory">
                No product inventory is low.
            </div>

            @endif

        </div>

    </div>

</div>

@endsection

@push('css')

<link rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
    href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

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
                    targets: 2,
                    type: 'num'
                }],
                language: {
                    search: "",
                    searchPlaceholder: "Search Product / SKU..."
                }
            });

        }

    });
</script>

@endpush