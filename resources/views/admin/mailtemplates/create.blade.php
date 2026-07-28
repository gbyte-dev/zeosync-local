@extends('admin.layout.app')
@section('title', 'Create Mail Template')
@section('content')
<div class="container-fluid">
    
    <!-- Page Header -->
    <div class="card shadow-sm border-0  overflow-hidden">
        <div class="p-3 text-dark shadow header" >
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="mb-1">Create Mail Template</h5>
            </div>
              <a class="btn btn-primary btn-sm" href="{{ route('admin.mailtemplates') }}">
                ← Back
            </a>
        </div>
    </div>
  
    <div class="card-body p-0">
    <form action="{{ route('admin.mailtemplates.store') }}" method="POST">
        @csrf

        <div class="row">

            <!-- LEFT SIDE -->
            <div class="col-md-8">

                <div class="card shadow-sm mb-4">
                    <div class="card-body">

                        <!-- Name -->
                        <div class="mb-3">
                            <label class="form-label">Template Name</label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="e.g. Order Confirmation Email" required>
                        </div>

                        <!-- Subject -->
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control"
                                   placeholder="e.g. Order {order_id} confirmed" required>
                        </div>

                     <!-- Body -->
                        <div class="mb-3">
                            <label class="form-label">Email Body (HTML)</label>
                            <textarea id="bodyEditor" name="body" rows="6" class="form-control"
                                      placeholder="<h1>Hello {name}</h1>" required></textarea>
                        </div>   

                        <!-- Plain Text -->
                        <div class="mb-3">
                            <label class="form-label">Plain Text</label>
                            <textarea name="plain_text" rows="4" class="form-control"
                                      placeholder="Hello {name}"></textarea>
                        </div>

                    </div>
                </div>

            </div>

            <!-- RIGHT SIDE -->
            <div class="col-md-4">

                <!-- Settings Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">

                        <!-- Variables -->
                        <div class="mb-3">
                            <label class="form-label">Insert Variables</label>
                            <small class="text-muted d-block mb-2">Click to insert at cursor position</small>
                            
                            <div class="variable-list" style="overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px;">
                                <div class="variable-category mb-2">
                                    <small class="text-muted fw-bold">Shop & Store</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{shop_name}">{shop_name}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{shop_domain}">{shop_domain}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{shop_email}">{shop_email}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{shop_currency}">{shop_currency}</button>
                                    </div>
                                </div>
                                
                                <div class="variable-category mb-2">
                                    <small class="text-muted fw-bold">Customer</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{customer_first_name}">{customer_first_name}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{customer_last_name}">{customer_last_name}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{customer_email}">{customer_email}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{customer_phone}">{customer_phone}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{customer_address}">{customer_address}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{customer_city}">{customer_city}</button>
                                    </div>
                                </div>
                                
                                <div class="variable-category">
                                    <small class="text-muted fw-bold">Product</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{product_name}">{product_name}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{product_sku}">{product_sku}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{product_price}">{product_price}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{product_quantity}">{product_quantity}</button>
                                        <button type="button" class="btn btn-sm btn-outline-none variable-btn" data-variable="{product_url}">{product_url}</button>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="variables" id="variablesJson" value='["shop_name","shop_domain","shop_email","shop_currency","customer_first_name","customer_last_name","customer_email","customer_phone","customer_address","customer_city","product_name","product_sku","product_price","product_quantity","product_url"]'>
                        </div>


                        <!-- Status -->
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" checked>
                            <label class="form-check-label">Active</label>
                        </div>

                    </div>
                </div>

                <!-- Actions -->
                <div class="card shadow-sm">
                    <div class="card-body text-end">
                        <button type="submit" class="btn btn-primary w-100">
                             Save Template
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </form>
</div>
</div>

@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<!-- Include Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css" rel="stylesheet">
<!-- Include Summernote JS -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Summernote
        $('#bodyEditor').summernote({
            height: 400,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'table']],
                ['view', ['codeview', 'help']]
            ],
            callbacks: {
                onChange: function(contents) {
                    // Sync with textarea
                    $('#bodyEditor').val(contents);
                }
            }
        });

        // Handle variable button clicks
        $('.variable-btn').on('click', function() {
            var variable = $(this).data('variable');
            var $editor = $('#bodyEditor');
            
            // Focus the editor
            $editor.summernote('focus');
            
            // Insert the variable at cursor position
            $editor.summernote('editor.insertText', variable);
            
            // Visual feedback
            $(this).removeClass('btn-outline-none').addClass('btn-success');
            setTimeout(function() {
                $('.variable-btn').removeClass('btn-success').addClass('btn-outline-none');
            }, 500);
        });
    });
</script>
@endsection