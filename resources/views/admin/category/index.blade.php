@extends('admin.layout.app')

@section('title', 'Category')

@section('content')
<style>
    #category-table_filter{
        float: inline-end;
    }
    #category-table_paginate{
        float: inline-end;
    }
    #category-table_length{
        float: margin-bottom: 10px;
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

    #category-table_info,#category-table_paginate{
        margin-top:10px;
    }

    th{
        font-weight: 400;
    }
</style>
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0  overflow-hidden">

        <div class="p-3 text-dark shadow header" >
        <div class="row">
            <div class="col-sm-7">
                <h5 class="mb-1">Categories</h5>
                <p class="mb-0 opacity-75">
                    Manage categories and subcategories
                </p>
            </div>
            <div class="col-sm-5">
                <button class="btn btn-primary btn-sm  px-4 btn-add-category" style="float:enline-end">
                    <i class="bi bi-plus-lg me-1"></i> Add Category
                </button>
                <form action="{{route('admin.search.categories')}}" style="display:inline-flex">
                    <input type="search"  class="form-control-sm" name="category" placeholder="Search Category">
                </form>
            </div>
        </div>
    </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="category-table" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3">#</th>
                            <th scope="col">Category</th>
                            <th scope="col"> Status </th>
                            <th scope="col">Subcategories <small>(Active/Total)</small></th>
                            <th scope="col" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $key => $category)
                            <tr>
                                <td class="ps-3 ">
                                    {{ $key + 1 }}
                                </td>
                                <td>
                                    <span class=" text-dark">{{ $category->name }}</span>
                                </td>
                                <td>
                                    @if($category->status == 'Active')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center pe-3">
                                   {{ getSubCategorires($category->id,'Active') }} / {{ getCategorires($category->id)->count() }} <sub>active sub-categories</sub>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex gap-2 justify-content-end">
                                          @if($category->self_added == 1)
                                        <button class="btn btn-outline-danger btn-sm  px-3 btn-delete-category"
                                                data-id="{{ $category->id }}"
                                                data-name="{{ $category->name }}">
                                            <i class="bi bi-trash"></i> 
                                        </button>
                                        @endif
                                        <button class="btn btn-outline-secondary btn-sm  px-3 btn-edit-category"
                                                data-id="{{ $category->id }}"
                                                data-name="{{ $category->name }}"
                                                data-status="{{ $category->status }}"
                                                data-parent-id="{{ $category->parent_id }}"
                                                data-category="{{ $category->category }}"
                                                data-slug="{{ $category->slug }}"
                                                data-marketplace-ids="{{ $category->marketplaceIds }}">
                                            <i class="bi bi-pencil"></i> 
                                        </button>
    
                                        <a href="{{ route('admin.category.children', $category->id) }}"
                                           class="btn btn-primary btn-sm  px-3">
                                            View Subcategories
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    No categories found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection

{{-- Include Category Form Modal --}}
@include('admin.category.form')

@section('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endsection


@section('scripts')

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#category-table').DataTable({
                pagingType: 'simple_numbers', // Previous / page numbers / Next, like the old Bootstrap pager
                pageLength: 10,               // rows per page
                lengthChange: true,           // let user change rows-per-page
                lengthMenu: [10, 25, 50, 100],
                searching: true,              // built-in search box (replaces old custom search JS)
                ordering: true,
                info: true,                   // "Showing X of Y categories" equivalent
                columnDefs: [
                    { orderable: false, targets: 4 } // disable sorting on Action column
                ],
                language: {
                    search: "Search:",
                    searchPlaceholder: "Find a category",
                    emptyTable: "No matching categories found",
                    zeroRecords: "No matching categories found",
                    info: "Showing _START_ to _END_ of _TOTAL_ categories",
                    infoEmpty: "Showing 0 categories",
                    infoFiltered: "(filtered from _MAX_ total categories)",
                    lengthMenu: "Show _MENU_ ",
                    paginate: {
                        previous: "Previous",
                        next: "Next"
                    }
                }
            });
        });

        // ===== Delete Category =====
        $(document).on('click', '.btn-delete-category', function () {
            const id = $(this).data('id');
            const name = $(this).data('name');

            if (!confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: '{{ url("/admin/delete-category") }}/' + id,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    
                    if(response.status){
                        alert(response.message);
                        location.reload();
                    }else{
                        alert(response.message);
                    }
                    
                },
                error: function (xhr) {
                    let msg = 'Failed to delete category.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="bi bi-trash"></i> Delete');
                }
            });
        });
    </script>
@endsection