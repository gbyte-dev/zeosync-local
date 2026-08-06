@extends('admin.layout.app')

@section('title', 'Contact Request Detail')

@section('content')

<style>
    .detail-card {
        border: 0;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, .06);
    }

    .detail-card .card-header {
        background: #fff;
        padding: .85rem 1rem;
        border-bottom: 1px solid #edf2f7;
        font-weight: 600;
    }

    .detail-card .card-body {
        padding: 1rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: .65rem 0;
        border-bottom: 1px solid #f1f3f5;
        gap: 15px;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 600;
        min-width: 120px;
    }

    .info-value {
        text-align: right;
        font-weight: 500;
        color: #212529;
        word-break: break-word;
    }

    .message-box {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fafafa;
        padding: 14px 16px;
        font-size: 14px;
        line-height: 1.7;
        white-space: pre-wrap;
        word-break: break-word;
        margin: 0;
    }

    .page-title {
        font-weight: 700;
        margin-bottom: 2px;
    }

    .page-subtitle {
        color: #6c757d;
        font-size: 13px;
    }

    .badge {
        font-size: 11px;
    }
</style>

<div class="container-fluid px-0">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h4 class="page-title">
                Contact Request Details
            </h4>

            <div class="page-subtitle">
                Submitted on {{ $contact->created_at->format('d M Y, h:i A') }}
            </div>
        </div>

        <a href="{{ route('admin.contact-requests') }}"
            class="btn btn-outline-secondary btn-sm">

            <i class="bi bi-arrow-left"></i>
            Back

        </a>

    </div>

    <div class="row g-3">

        <div class="col-lg-4">

            <div class="card detail-card">

                <div class="card-header">
                    Contact Information
                </div>

                <div class="card-body">

                    <div class="info-row">
                        <div class="info-label">Name</div>
                        <div class="info-value">{{ $contact->name }}</div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value">{{ $contact->email }}</div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Subject</div>
                        <div class="info-value">{{ $contact->subject }}</div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Enquiry Type</div>
                        <div class="info-value">

                            @if($contact->enquiry_type == 'enterprise_plan_enquiry')

                            <span class="badge bg-primary">
                                Enterprise Plan
                            </span>

                            @else

                            <span class="badge bg-secondary">
                                General Enquiry
                            </span>

                            @endif

                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">

                            @if($contact->is_read)

                            <span class="badge bg-success">
                                Read
                            </span>

                            @else

                            <span class="badge bg-warning text-dark">
                                Unread
                            </span>

                            @endif

                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Request ID</div>
                        <div class="info-value">
                            #{{ $contact->id }}
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">Submitted</div>
                        <div class="info-value">
                            {{ $contact->created_at->format('d M Y') }}
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-lg-8">

            <div class="card detail-card">

                <div class="card-header">
                    Message
                </div>

                <div class="card-body">

                    <div class="message-box">

                        {!! nl2br(e($contact->message)) !!}

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection