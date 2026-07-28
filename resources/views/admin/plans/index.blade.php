@extends('admin.layout.app')

@section('title', 'Plans')

@section('content')

<style>

    .plan-name {
        font-weight: 800;
        color: #111827;
    }

    .price-pill {
        display: inline-block;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 700;
        margin: 3px;
        white-space: nowrap;
    }

    .badge-soft {
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
    }

    .highlight-yes {
        background: #fef3c7;
        color: #92400e;
    }

    .highlight-no {
        background: #f1f5f9;
        color: #475569;
    }

    #plansTable_filter{
        float: inline-end;
    }
    #plansTable_paginate{
        float: inline-end;
    }
    #plansTable_length{
        margin-bottom: 10px;
        width: fit-content;
    }

    .dataTables_length>label{
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .dataTables_filter>label{
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #plansTable_info{
        margin-top:10px;
    }

</style>

<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="card shadow-sm border-0  overflow-hidden">
        <div class="p-3 text-dark shadow header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-1">Plans</h5>
                <p class="mb-0 opacity-75">Manage subscription plans, prices and visibility  </p>
            </div>
             <a href="{{ route('admin.plans.create') }}" class="btn btn-light btn-sm btn-add-category">
                + Add Plan
            </a>
          
        </div>
    </div>

    <div class="card-body mt-2">
        <div class="table-responsive">
            <table id="plansTable" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Badge</th>
                        <th>Status</th>
                        <th>Highlight</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td data-order="{{ $plan->name }}">
                                <div class="plan-name">{{ $plan->name }}</div>
                            </td>

                            <td>
                                @forelse($plan->prices ?? [] as $interval => $price)
                                    <span class="price-pill">
                                        {{ $interval == 'EVERY_30_DAYS' ? 'Monthly' : 'Yearly' }}:
                                        ${{ number_format($price, 2) }}
                                    </span>
                                @empty
                                    <span class="text-muted small">No price</span>
                                @endforelse
                            </td>

                            <td>
                                @if($plan->badge)
                                    <span class="badge bg-primary badge-soft">
                                        {{ $plan->badge }}
                                    </span>
                                @else
                                    <span class="text-muted small">N/A</span>
                                @endif
                            </td>

                            <td data-order="{{ $plan->is_active ? 1 : 0 }}">
                                @if($plan->is_active)
                                    <span class="badge bg-success badge-soft">Active</span>
                                @else
                                    <span class="badge bg-secondary badge-soft">Inactive</span>
                                @endif
                            </td>

                            <td data-order="{{ $plan->is_highlighted ? 1 : 0 }}">
                                @if($plan->is_highlighted)
                                    <span class="badge-soft highlight-yes">Yes</span>
                                @else
                                    <span class="badge-soft highlight-no">No</span>
                                @endif
                            </td>

                            <td class="text-end">
                                <a href="{{ route('admin.plans.edit', $plan) }}"
                                   class="btn btn-warning btn-sm edit-btn">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        {{-- Left empty on purpose: DataTables shows its own
                             "No data available" message when the table body
                             has no rows. --}}
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
    $(function () {
        $('#plansTable').DataTable({
            responsive: true,
            order: [], // keep server-supplied order by default
            language: {
                search: 'Search: _INPUT_',
                searchPlaceholder: 'Search plans...',
                emptyTable: 'No plans found',
                lengthMenu: 'Show _MENU_',
                info: 'Showing _START_ to _END_ of _TOTAL_ plans',
                infoEmpty: 'No plans to show',
                infoFiltered: '(filtered from _MAX_ total plans)'
            },
            columnDefs: [
                { orderable: false, targets: [1, 2, -1] }, // price/badge/action columns aren't meaningfully sortable
                { responsivePriority: 1, targets: 0 },      // name always visible
                { responsivePriority: 2, targets: -1 }      // action always visible
            ],
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100]
        });
    });
</script>
@endpush