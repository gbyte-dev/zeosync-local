@extends('admin.layout.app')

@section('title', 'App Settings')

@section('content')

<style>
    /* FULL WIDTH WRAPPER */
    .container-settings {
        width: 100%;
        padding: 20px;
    }

    /* CENTER CONTENT BUT KEEP BG FULL */
    .form-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #d2d2d2;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .form-wrapper {
        background: #ffffff;
        border: 1px solid #d2d2d2;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .page-title {
        font-size: 22px;
        font-weight: 600;
        margin-bottom: 20px;
        color: #0a0f1c;
    }

    /* card */
    .section {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #d2d2d2;
        margin-bottom: 20px;
    }

    .section-header {
        padding: 12px 18px;
        /* font-weight: 600; */
        font-size: 16px;
        background: #f8f8f8;
        border-bottom: 1px solid #d2d2d2;
    }

    .page-header {
        display: block;
        width: calc(100% + 48px);
        margin-left: -24px;
        margin-top: -24px;

        padding: 12px 18px;
        font-size: 16px;
        background: #f8f8f8;
        border-bottom: 1px solid #d2d2d2;

        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .section-body {
        padding: 18px;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 22px;
    }

    .switch input {
        display: none;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        background: #ccc;
        border-radius: 20px;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        transition: .3s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background: white;
        border-radius: 50%;
        transition: .3s;
    }

    input:checked+.slider {
        background: #2563eb;
    }

    input:checked+.slider:before {
        transform: translateX(20px);
    }

    /* field */
    .field {
        display: flex;
        flex-direction: column;
    }

    .field label {
        font-size: 16px;
        /* font-weight: 600; */
        margin-bottom: 5px;
        color: #1e293b;
    }

    .field input {
        height: 40px;
        border: 1px solid #d2d2d2;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 14px;
    }

    .field input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }

    /* full width */
    .field.full {
        grid-column: span 2;
    }

    .hint {
        font-size: 11px;
        color: #64748b;
        margin-top: 4px;
    }

    /* buttons */
    .actions {
        margin-top: 20px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn {
        padding: 8px 18px;
        border-radius: 20px;
        font-size: 13px;
        border: none;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .btn-secondary {
        background: #ff0000;
        border: 1px solid #cbdcec;
    }
</style>

<div class="container-settings">

    <form method="POST"
        action="{{ route('admin.app.settings.update') }}"
        autocomplete="off">
        @csrf

        <!-- Amazon Test -->
        <div class="section">
            <div class="section-header d-flex justify-content-between align-items-center">
                <span>Amazon Test Credentials</span>
                <div class="toggle-wrapper">
                    <label class="switch">
                        <input type="checkbox" id="testModeToggle"
                            {{ ($settings['is_testmode'] ?? 0) == 1 ? 'checked' : '' }}>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <input type="hidden"
                name="is_testmode"
                id="is_testmode"
                value="{{ $settings['is_testmode'] ?? 0 }}">

            <div class="section-body row g-3">

                <div class="col-md-6">
                    <div class="field">
                        <label>Test Client ID</label>
                        <input type="text"
                            name="test_client_id"
                            autocomplete="off"
                            value="{{ $settings['test_client_id'] ?? '' }}"
                            placeholder="amzn1.application-oa2-client.xxx">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label>Test Client Secret</label>
                        <input type="text"
                            name="test_client_secret"
                            autocomplete="new-password"
                            value="{{ $settings['test_client_secret'] ?? '' }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label>Test Refresh Token</label>
                        <input type="text"
                            name="test_refresh_token"
                            autocomplete="new-password"
                            value="{{ $settings['test_refresh_token'] ?? '' }}">
                    </div>
                </div>

            </div>
        </div>

        <!-- Amazon Production -->
        <div class="section">
            <div class="section-header">Amazon Production Credentials</div>

            <div class="section-body row g-3">

                <div class="col-md-6">
                    <div class="field">
                        <label>Production Client ID</label>
                        <input type="text"
                            name="production_client_id"
                            autocomplete="off"
                            value="{{ $settings['production_client_id'] ?? '' }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label>Production Client Secret</label>
                        <input type="text"
                            name="production_client_secret"
                            autocomplete="new-password"
                            value="{{ $settings['production_client_secret'] ?? '' }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label>Production Refresh Token</label>
                        <input type="text"
                            name="production_refresh_token"
                            autocomplete="new-password"
                            value="{{ $settings['production_refresh_token'] ?? '' }}">
                    </div>
                </div>

            </div>
        </div>

        <!-- Shopify -->
        <div class="section">
            <div class="section-header">Shopify Credentials</div>

            <div class="section-body row g-3">

                <div class="col-md-6">
                    <div class="field">
                        <label>Shopify API Key</label>
                        <input type="text"
                            name="SHOPIFY_API_KEY"
                            autocomplete="off"
                            value="{{ $settings['SHOPIFY_API_KEY'] ?? '' }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label>Shopify API Secret</label>
                        <input type="text"
                            name="SHOPIFY_API_SECRET"
                            autocomplete="new-password"
                            value="{{ $settings['SHOPIFY_API_SECRET'] ?? '' }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field">
                        <label>Shopify Redirect URI</label>
                        <input type="text"
                            name="SHOPIFY_REDIRECT_URI"
                            autocomplete="off"
                            value="{{ $settings['SHOPIFY_REDIRECT_URI'] ?? '' }}">
                    </div>
                </div>

            </div>
        </div>

        <div class="actions">
            <button type="reset" class="btn btn-secondary">Reset</button>
            <button type="submit" class="btn btn-primary">Save Credentials</button>
        </div>
    </form>
</div>

<script>
    const toggle = document.getElementById('testModeToggle');
    const hiddenInput = document.getElementById('is_testmode');

    toggle.addEventListener('change', function() {
        hiddenInput.value = this.checked ? 1 : 0;
    });
</script>

@endsection