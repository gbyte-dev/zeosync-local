@extends('admin.layout.app')

@section('title', 'Contact Requests')

@section('content')
<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold">Contact Requests</h4>
                    <p class="mb-0 text-muted small">Manage all customer enquiries and support requests</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form method="GET" action="{{ route('admin.contact-requests') }}" class="d-flex align-items-center gap-2">
                        <label for="enquiry_type" class="fw-semibold mb-0 text-muted">Filter:</label>
                        <select id="enquiry_type" name="enquiry_type" class="form-select" onchange="this.form.submit()" style="width: 240px;">
                            <option value="">All Enquiries</option>
                            <option value="general_enquiry" {{ request('enquiry_type') == 'general_enquiry' ? 'selected' : '' }}>General Enquiries</option>
                            <option value="enterprise_plan_enquiry" {{ request('enquiry_type') == 'enterprise_plan_enquiry' ? 'selected' : '' }}>Enterprise Plan Enquiries</option>
                        </select>
                    </form>
                    <form action="{{ route('admin.contact-requests.markall') }}" method="POST" class="d-inline-block">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-sm">Mark All Read</button>
                    </form>
                    <form action="{{ route('admin.contact-requests.deleteall') }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to delete ALL contact requests? This cannot be undone.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete All</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contacts as $contact)
                        <tr>
                            <td class="fw-semibold text-muted">#{{ $contact->id }}</td>
                            <td>{{ $contact->name }}</td>
                            <td>{{ $contact->email }}</td>
                            <td>{{ $contact->subject }}</td>
                            <td>{{ $contact->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $contact->is_read ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $contact->is_read ? 'Read' : 'Unread' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.contact-requests.show', $contact) }}" class="btn btn-sm btn-outline-primary">View</a>

                                    @if(!$contact->is_read)
                                    <form action="{{ route('admin.contact-requests.markread', $contact) }}" method="POST" class="d-inline-block">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Mark Read</button>
                                    </form>
                                    @endif

                                    <form action="{{ route('admin.contact-requests.destroy', $contact) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to delete this contact request?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                @if(request('enquiry_type') == 'enterprise_plan_enquiry')
                                No enterprise plan enquiries found.
                                @elseif(request('enquiry_type') == 'general_enquiry')
                                No general enquiries found.
                                @else
                                No contact requests yet.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        {{ $contacts->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

@push('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush

@push('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush