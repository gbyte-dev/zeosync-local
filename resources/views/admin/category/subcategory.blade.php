@extends('admin.layout.app')

@section('title', 'Subcategories')

@section('content')

<style>
    #subcategory-table_filter{
        float: inline-end;
        padding: 10px;
    }
    #subcategory-table_paginate{
        float: inline-end;
        margin-top: 10px;
    }
    #subcategory-table{
         margin-bottom: 10px;
    }
    #subcategory-table_info{
        float: inline-start;
        margin-top: 10px;
    }

    #subcategory-table_length{
        width: fit-content;
        padding: 10px;
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
 
</style>
    {{-- Header --}}
  
<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h6 class="fw-bold mb-1">Sub-categories</h6>
                    <p class="mb-0 text-muted small">Manage subcategories ({{$children[0]->parent->name??''}})</p>
                </div>
                <a href="{{ route('admin.category') }}" class="btn btn-light btn-sm">← Back</a>
            </div>
        </div>

        <div class="card-body p-0">

            {{-- Desktop Table --}}
            <div class="p-3">
                {{-- Separate Filter Form --}}
                <form id="filter-subcategories-form" class="mb-3" onsubmit="return false;">
                    <div class="d-flex gap-2 align-items-center">
                        <label class="mb-0 small text-muted">Filter by status</label>
                        <select id="status-filter" name="status_filter" class="form-select form-select-sm w-auto">
                            <option value="all" {{ request()->get('status', 'all') === 'all' ? 'selected' : '' }}>All</option>
                            <option value="Active" {{ request()->get('status') === 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ request()->get('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        <button type="button" id="clear-status-filter" class="btn btn-sm btn-outline-secondary">Clear</button>
                    </div>
                </form>

                <form id="move-subcategories-form" method="post" action="{{ route('admin.subcategories.move') }}">
                    @csrf
                    <input type="hidden" name="status" id="move-form-status" value="{{ request()->get('status','all') }}">
                    <div class="d-flex gap-2 align-items-center mb-3">
                        <label class="mb-0 small text-muted">Move selected to</label>
                        <select class="form-select form-select-sm w-auto" name="target_parent_id">
                            <option value="">Top Level</option>
                            @foreach($parentCategories as $pc)
                                <option value="{{ $pc->id }}">{{ $pc->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Move Selected</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table id="subcategory-table" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-uppercase small text-muted"><input id="select-all-subcats" type="checkbox"></th>
                            <th class="ps-4 text-uppercase small text-muted">Sr No.</th>
                            <th class="text-uppercase small text-muted">Subcategory</th>
                            <th class="text-end pe-4 text-uppercase small text-muted">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($children as $key => $child)
                            <tr>
                                <td class="ps-4">
                                    <input form="move-subcategories-form" type="checkbox" name="subcategory_ids[]" value="{{ $child->id }}">
                                </td>
                                <td class="ps-4 text-muted fw-semibold">
                                    #{{ $key + 1 }}
                                </td>

                                <td>
                                    <span class=" text-dark">
                                        {{ ucfirst(strtolower(str_replace('_', ' ', $child->name))) }} 
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-flex gap-2 justify-content-end align-items-center">
                                        @if(strtolower($child->status) == 'active')
                                            <a href="{{route('admin.schema.deactivate',['category'=> $child->slug ])}}" class="badge rounded-pill btn btn-sm btn-danger">Deactivate</a>
                                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                                ● Active
                                            </span>
                                        @else
                                            <a href="{{route('admin.importSchema',['category'=> $child->slug ])}}" class="badge rounded-pill btn btn-sm btn-primary">Activate</a>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                                                ● Inactive
                                            </span>
                                        @endif
                                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 btn-edit-category"
                                                data-id="{{ $child->id }}"
                                                data-name="{{ $child->name }}"
                                                data-status="{{ $child->status }}"
                                                data-parent-id="{{ $child->parent_id }}"
                                                data-category="{{ $child->category }}"
                                                data-slug="{{ $child->slug }}"
                                                data-marketplace-ids="{{ $child->marketplaceIds }}">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-5">
                                    No subcategories found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="d-block d-none p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">Search</span>
                    <input type="text" id="mobile-subcategory-search" class="form-control" placeholder="Find a subcategory">
                </div>

                @forelse($children as $key => $child)
                    <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm" data-subcategory-card>
                        <div class="d-flex justify-content-between align-items-start row gap-2">
                            <div class="col">
                                <div class="small text-muted mb-1">
                                    #{{ $key + 1 }}
                                </div>
                                <div class="fw-bold text-dark">
                                    {{ (ucfirst(strtolower(str_replace('_', ' ', $child->name)))) }}
                                </div>
                            </div>

                            <div class="col-auto">
                                @if(strtolower($child->status) == 'active')
                                    <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                        Active
                                    </span>
                                @else
                                    <a href="{{route('admin.downloadScema',['category'=> $child->slug ])}}" class="badge rounded-pill btn btn-sm btn-danger">Download Schema</a>
                                    <a href="{{route('admin.schema.create')}}" class="badge rounded-pill btn btn-sm btn-success">Upload Schema</a>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                        Inactive
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        No subcategories found
                    </div>
                @endforelse

                <div id="mobile-subcategory-no-results" class="text-center text-muted py-5 d-none">
                    No matching subcategories found
                </div>
            </div>

        </div>

    </div>
</div>

{{-- Include Category Form Modal --}}
@include('admin.category.form')

@endsection

@section('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endsection

@section('scripts')

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        // Desktop DataTable — pagination + search + sorting, styled for Bootstrap 5
        var initialStatus = '{{ request()->get('status','all') }}';
        var subcatTable = $('#subcategory-table').DataTable({
            pagingType: 'simple_numbers',
            pageLength: 10,
            lengthChange: true,
            lengthMenu: [10, 25, 50, 100],
            searching: true,
            ordering: true,
            info: true,
            columnDefs: [
                { orderable: false, targets: 2 } // Status column not sortable
            ],
            language: {
                search: "Search:",
                searchPlaceholder: "Find a subcategory",
                emptyTable: "No subcategories found",
                zeroRecords: "No matching subcategories found",
                info: "Showing _START_ to _END_ of _TOTAL_ subcategories",
                infoEmpty: "Showing 0 subcategories",
                infoFiltered: "(filtered from _MAX_ total subcategories)",
                lengthMenu: "Show _MENU_ ",
                paginate: {
                    previous: "Previous",
                    next: "Next"
                }
            }
        });
        // apply initial status filter (if any)
        if (initialStatus && initialStatus !== 'all') {
            $('#status-filter').val(initialStatus);
            subcatTable.column(3).search(initialStatus).draw();
        } else {
            $('#status-filter').val('all');
        }

        // wire the separate status filter — also update URL so selection persists on refresh
        $('#status-filter').on('change', function () {
            var val = $(this).val();
            if (!subcatTable) return;
            if (val === 'all') {
                subcatTable.column(3).search('').draw();
            } else {
                subcatTable.column(3).search(val).draw();
            }

            // update URL query param 'status' (so refresh keeps it)
            try {
                var url = new URL(window.location.href);
                var params = url.searchParams;
                if (val === 'all') {
                    params.delete('status');
                } else {
                    params.set('status', val);
                }
                var newUrl = url.pathname + (params.toString() ? ('?' + params.toString()) : '');
                history.replaceState(null, '', newUrl);
            } catch (e) {
                // older browsers fallback: do nothing
            }

            // keep move form hidden field in sync
            var moveStatus = document.getElementById('move-form-status');
            if (moveStatus) moveStatus.value = val;
        });

        // clear button: remove filter and URL param
        $('#clear-status-filter').on('click', function () {
            $('#status-filter').val('all').trigger('change');
            try {
                var url = new URL(window.location.href);
                var params = url.searchParams;
                params.delete('status');
                var newUrl = url.pathname + (params.toString() ? ('?' + params.toString()) : '');
                history.replaceState(null, '', newUrl);
            } catch (e) {}
        });

        // // Mobile card search (simple client-side filter, mirrors desktop search behavior)
        // const mobileSearch = document.getElementById('mobile-subcategory-search');
        // const mobileCards = Array.from(document.querySelectorAll('[data-subcategory-card]'));
        // const mobileNoResults = document.getElementById('mobile-subcategory-no-results');

        // if (mobileSearch) {
        //     mobileSearch.addEventListener('input', function () {
        //         const query = mobileSearch.value.trim().toLowerCase();
        //         let visibleCount = 0;

        //         mobileCards.forEach(function (card) {
        //             const isVisible = card.textContent.toLowerCase().includes(query);
        //             card.style.display = isVisible ? '' : 'none';
        //             if (isVisible) visibleCount++;
        //         });

        //         if (mobileNoResults) {
        //             mobileNoResults.classList.toggle('d-none', visibleCount !== 0 || mobileCards.length === 0);
        //         }
        //     });
        // }
    });
</script>

<script>
    // Select all checkbox handler
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('select-all-subcats');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                const checks = document.querySelectorAll('input[name="subcategory_ids[]"]');
                checks.forEach(cb => cb.checked = selectAll.checked);
            });
        }

        const form = document.getElementById('move-subcategories-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                const selected = Array.from(document.querySelectorAll('input[name="subcategory_ids[]"]:checked'));
                if (selected.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one subcategory to move.');
                    return;
                }
                if (!confirm('Move ' + selected.length + ' subcategory(ies) to the selected parent?')) {
                    e.preventDefault();
                    return;
                }
            });
        }
    });
</script>
@endsection