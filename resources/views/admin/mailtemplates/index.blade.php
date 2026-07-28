@extends('admin.layout.app')

@section('title', 'Mail Templates')

@section('content')

<style>
    #mailtemplates-table_filter{
        float: inline-end;
        padding: 10px;
    }
    #mailtemplates-table_paginate{
        float: inline-end;
        margin-top: 10px;
    }
    #mailtemplates-table{
         margin-bottom: 10px;
    }
    #mailtemplates-table_info{
        float: inline-start;
        margin-top: 10px;
    }
    #mailtemplates-table_length{
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
<div class="container-fluid px-0" style="max-width: 1400px; margin: 0 auto;">

    {{-- Header --}}
    <div class="p-3 text-dark shadow header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h6 class="fw-bold mb-1">Mail Templates</h6>
            </div>

            <a class="btn btn-primary btn-sm rounded-3"
               href="{{ route('admin.mailtemplates.create') }}">
                + Create Template
            </a>
        </div>
    </div>

    <div class="card-body border-0 rounded-0 rounded-bottom-4 shadow-sm overflow-hidden">

        <!-- <div class="card-body p-2"> -->

            {{-- Desktop Table --}}
            <div class="table-responsive d-none d-md-block">
                <table id="mailtemplates-table" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-uppercase small text-muted">Name</th>
                            <th class="text-uppercase small text-muted">Subject</th>
                            <th class="text-uppercase small text-muted">Body</th>
                            <th class="text-uppercase small text-muted">Status</th>
                            <th class="text-end pe-4 text-uppercase small text-muted">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($mailtemplates as $mailtemplate)
                            <tr>
                                <td class="ps-4">
                                    <div class=" text-dark text-nowrap">
                                        {{ $mailtemplate->name }}
                                    </div>
                                </td>

                                <td>
                                    <span class=" text-dark">
                                        {{ $mailtemplate->subject }}
                                    </span>
                                </td>

                                <td>
                                    <div class="text-muted text-truncate" style="max-width: 380px;"
                                         title="{{ strip_tags($mailtemplate->body) }}">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($mailtemplate->body), 70) }}
                                    </div>
                                </td>

                                <td>
                                    @if($mailtemplate->is_active)
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                            ● Active
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                                            ● Inactive
                                        </span>
                                    @endif
                                </td>

                                <td class="pe-4">
                                    <div class="d-flex gap-2 justify-content-end align-items-center">
                                        <a href="{{ route('admin.mailtemplates.edit', $mailtemplate->id) }}"
                                           class="btn btn-primary btn-sm rounded-3 fw-bold">
                                            Edit
                                        </a>

                                        <form action="{{ route('admin.mailtemplates.delete', $mailtemplate->id) }}"
                                              method="post"
                                              onsubmit="return confirm('Are you sure you want to delete this template?')">
                                            @csrf
                                            @method('POST')

                                            <button type="submit" class="btn btn-danger btn-sm rounded-3 fw-bold">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    No mail templates found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="d-block d-md-none p-3">

                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">Search</span>
                    <input type="text" id="mobile-template-search" class="form-control" placeholder="Find a template">
                </div>

                @forelse($mailtemplates as $mailtemplate)
                    <div class="border rounded-4 p-3 mb-3 bg-white shadow-sm" data-template-card>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold text-dark">
                                    {{ $mailtemplate->name }}
                                </div>
                                <div class="small text-muted">
                                    {{ $mailtemplate->subject }}
                                </div>
                            </div>

                            @if($mailtemplate->is_active)
                                <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                    Active
                                </span>
                            @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                    Inactive
                                </span>
                            @endif
                        </div>

                        <p class="text-muted small mb-3">
                            {{ \Illuminate\Support\Str::limit(strip_tags($mailtemplate->body), 110) }}
                        </p>

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.mailtemplates.edit', $mailtemplate->id) }}"
                               class="btn btn-primary btn-sm rounded-3 fw-bold">
                                Edit
                            </a>

                            <form action="{{ route('admin.mailtemplates.delete', $mailtemplate->id) }}"
                                  method="post"
                                  onsubmit="return confirm('Are you sure you want to delete this template?')">
                                @csrf
                                @method('POST')

                                <button type="submit" class="btn btn-danger btn-sm rounded-3 fw-bold">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        No mail templates found
                    </div>
                @endforelse

                <div id="mobile-template-no-results" class="text-center text-muted py-5 d-none">
                    No matching templates found
                </div>
            </div>

        <!-- </div> -->
    </div>
</div>

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
        $('#mailtemplates-table').DataTable({
            pagingType: 'simple_numbers',
            pageLength: 10,
            lengthChange: true,
            lengthMenu: [10, 25, 50, 100],
            searching: true,
            ordering: true,
            info: true,
            columnDefs: [
                { orderable: false, targets: [2, 4] } // Body preview & Actions not sortable
            ],
            language: {
                search: "Search:",
                searchPlaceholder: "Find a template",
                emptyTable: "No mail templates found",
                zeroRecords: "No matching templates found",
                info: "Showing _START_ to _END_ of _TOTAL_ templates",
                infoEmpty: "Showing 0 templates",
                infoFiltered: "(filtered from _MAX_ total templates)",
                lengthMenu: "Show _MENU_ templates",
                paginate: {
                    previous: "Previous",
                    next: "Next"
                }
            }
        });

        // Mobile card search (simple client-side filter, mirrors desktop search behavior)
        const mobileSearch = document.getElementById('mobile-template-search');
        const mobileCards = Array.from(document.querySelectorAll('[data-template-card]'));
        const mobileNoResults = document.getElementById('mobile-template-no-results');

        if (mobileSearch) {
            mobileSearch.addEventListener('input', function () {
                const query = mobileSearch.value.trim().toLowerCase();
                let visibleCount = 0;

                mobileCards.forEach(function (card) {
                    const isVisible = card.textContent.toLowerCase().includes(query);
                    card.style.display = isVisible ? '' : 'none';
                    if (isVisible) visibleCount++;
                });

                if (mobileNoResults) {
                    mobileNoResults.classList.toggle('d-none', visibleCount !== 0 || mobileCards.length === 0);
                }
            });
        }
    });
</script>
@endsection