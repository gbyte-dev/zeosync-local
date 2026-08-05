@extends('admin.layout.app')

@section('title', 'Contact Request Detail')

@section('content')
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Request from {{ $contact->name }}</h2>
            <p class="text-muted mb-0">Submitted on {{ $contact->created_at->format('M d, Y H:i') }}</p>
        </div>
        <a href="{{ route('admin.contact-requests') }}" class="btn btn-outline-secondary">Back to list</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Contact Info</h5>
                    <p><strong>Name:</strong> {{ $contact->name }}</p>
                    <p><strong>Email:</strong> {{ $contact->email }}</p>
                    <p><strong>Subject:</strong> {{ $contact->subject }}</p>
                    <p><strong>Status:</strong>
                        <span class="badge rounded-pill {{ $contact->is_read ? 'bg-success' : 'bg-secondary' }}">
                            {{ $contact->is_read ? 'Read' : 'Unread' }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3">Message</h5>
                    <p class="text-muted">{{ $contact->message }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
