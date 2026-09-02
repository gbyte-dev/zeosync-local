@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Amazon Low Inventory</h4>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="amazonLowInventoryTable" class="table table-bordered table-striped">
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
                                <td>{{ $product['title'] ?? '-' }}</td>
                                <td>{{ $product['sku'] ?? '-' }}</td>
                                <td>{{ $product['quantity'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#amazonLowInventoryTable').DataTable({
            pageLength: 10,
            order: [[2, 'asc']]
        });
    });
</script>
@endpush