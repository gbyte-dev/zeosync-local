@extends('admin.layout.app')

@section('title', 'Shops')

@section('content')

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold">Shops</h4>
                    <p class="mb-0 text-muted small">Manage all connected stores and their subscription status</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary px-3 py-2">{{ $shops->count() }} total</span>
                    <span class="badge bg-success-subtle text-success px-3 py-2">{{ $shops->where('is_active', true)->count() }} active</span>
                </div>
            </div>
        </div>

        <div class="card-body border-0">
            @if($shops->count() > 0)
            <div class="table-responsive">
                <table id="datatable-table" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-uppercase small text-muted">#</th>
                            <th class="text-uppercase small text-muted">Shop</th>
                            <th class="text-uppercase small text-muted">Subscription</th>
                            <th class="text-uppercase small text-muted">Subscri. Status</th>
                            <th class="text-uppercase small text-muted">Status</th>
                            <th class="text-uppercase small text-muted">Connected At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $i=0; @endphp

                        @foreach($shops as $shop)
                        <tr>
                            <td class="ps-4 text-muted fw-semibold">#{{ ++$i }}</td>
                            <td>
                                <a href="{{ route('admin.shops.show', $shop->id) }}" class="text-dark text-decoration-none fw-bold d-flex align-items-center">
                                
                                    <span>{{ $shop->shop }}</span>
                                </a>
                            </td>
                            <td>
                                @if($shop->subscription && $shop->subscription->plan)
                                <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">{{ $shop->subscription->plan->name }}</span>
                                @else
                                <span class="text-muted small">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($shop->subscription)
                                <span class="badge bg-{{ $shop->subscription->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($shop->subscription->status) }}</span>
                                @else
                                <span class="badge bg-secondary">No Subscription</span>
                                @endif
                            </td>
                            <td>
                                @if($shop->is_active)
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">● Active</span>
                                @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">● Inactive</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $shop->created_at?->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="d-none d-md-block">
                <div class="alert alert-info text-center mb-0">No shops found.</div>
            </div>
            @endif

            {{-- table is responsive and used for all screen sizes; mobile cards removed --}}
        </div>
    </div>
</div>
@endsection

    @section('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    @endsection

    @section('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Buttons extension -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

    <script>
        $(document).ready(function() {
            initDatatable('#datatable-table');
        });

        
    </script>
    @endsection