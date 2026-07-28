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

  <div class="p-3 text-dark shadow header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h6 class="fw-bold mb-1">Sub-categories</h6>
                <p class="mb-0 opacity-75">
                    Manage subcategories ({{$children[0]->parent->name??''}})
                </p>
            </div>

             <a href="{{ route('admin.category') }}" class="btn btn-primary btn-sm rounded-3" style="">
                ← Back
            </a>
        </div>
    </div>

    <div class="card border-0 mt-2 shadow-sm overflow-hidden">

        <div class="card-body p-0">

            {{-- Desktop Table --}}
            <div class="table-responsive">
                <table id="subcategory-table" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-uppercase small text-muted">Sr No.</th>
                            <th class="text-uppercase small text-muted">Subcategory</th>
                            <th class="text-end pe-4 text-uppercase small text-muted">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($children as $key => $child)
                            <tr>
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
        $('#subcategory-table').DataTable({
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
@endsection