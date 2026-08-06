@extends('admin.layout.app')

@section('title', 'Contact Requests')

@section('content')
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

        <div>
            <h2 class="mb-1">Contact Requests</h2>
            <p class="text-muted mb-0">
                View and manage user inquiries from the contact form.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.contact-requests') }}" class="d-flex align-items-center gap-2">

            <label for="enquiry_type" class="fw-semibold mb-0">
                Filter:
            </label>

            <select
                id="enquiry_type"
                name="enquiry_type"
                class="form-select"
                onchange="this.form.submit()"
                style="width:250px;">

                <option value="">All Enquiries</option>

                <option value="general_enquiry"
                    {{ request('enquiry_type') == 'general_enquiry' ? 'selected' : '' }}>
                    General Enquiries
                </option>

                <option value="enterprise_plan_enquiry"
                    {{ request('enquiry_type') == 'enterprise_plan_enquiry' ? 'selected' : '' }}>
                    Enterprise Plan Enquiries
                </option>

            </select>

        </form>

    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
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
                            <td>{{ $contact->id }}</td>
                            <td>{{ $contact->name }}</td>
                            <td>{{ $contact->email }}</td>
                            <td>{{ $contact->subject }}</td>
                            <td>{{ $contact->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $contact->is_read ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $contact->is_read ? 'Read' : 'Unread' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.contact-requests.show', $contact) }}" class="btn btn-sm btn-outline-primary">View</a>
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
        {{ $contacts->links() }}
    </div>
</div>
@endsection