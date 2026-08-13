@extends('layouts.app')

@section('content')

<!-- Optionally include Bootstrap Icons CDN if not already in your app.blade.php layout -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Global Clean SaaS Environment - Tighter Density */
    body {
        background-color: #F4F6F8;
        font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #202223;
        font-size: 13px;
    }

    .saas-wrapper {
        /* max-width: 1180px; */
        /* margin: 0 auto; */
        padding: 12px 16px;
    }

    /* Page Header */
    .saas-page-header {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
    }

    .saas-page-subtitle {
        color: #6D7175;
        font-size: 12px;
        margin: 0;
    }

    /* Cards */
    .saas-card {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 16px;
        overflow: hidden;
    }

    .saas-card-body {
        padding: 6px 16px;
    }

    .saas-section-title {
        font-size: 14px;
        font-weight: 650;
        color: #1A1A1A;
        margin-bottom: 2px;
    }

    .saas-section-desc {
        font-size: 12px;
        color: #6D7175;
        margin-bottom: 16px;
    }

    /* Setting Rows */
    .saas-setting-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        border-bottom: 1px solid #E5E7EB;
        gap: 16px;
    }

    .saas-setting-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .saas-setting-label {
        font-size: 13px;
        font-weight: 600;
        color: #202223;
        margin-bottom: 2px;
    }

    .saas-setting-help {
        font-size: 12px;
        color: #6D7175;
    }

    /* Toggles (Overrides Bootstrap .form-check-input) */
    .saas-switch-wrapper .form-check-input {
        width: 36px;
        height: 20px;
        margin-top: 0;
        cursor: pointer;
        border: 1px solid #C9CCCF;
        background-color: #E5E7EB;
    }

    .saas-switch-wrapper .form-check-input:checked {
        background-color: #1A1A1A;
        border-color: #1A1A1A;
    }

    .saas-switch-wrapper .form-check-input:focus {
        box-shadow: 0 0 0 2px rgba(26, 26, 26, 0.2);
    }

    /* Inputs / Selects */
    .saas-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 4px;
        color: #202223;
    }

    .saas-select {
        display: block;
        width: 100%;
        height: 34px;
        padding: 4px 10px;
        font-size: 13px;
        color: #202223;
        background-color: #FFFFFF;
        border: 1px solid #C9CCCF;
        border-radius: 6px;
        transition: border-color 0.15s, box-shadow 0.15s;
        outline: none;
    }

    .saas-select:focus {
        border-color: #2C6ECB;
        box-shadow: 0 0 0 2px rgba(44, 110, 203, 0.2);
    }

    /* Tables */
    .saas-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .saas-table th {
        background: #F9FAFB;
        color: #6D7175;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 16px;
        border-bottom: 1px solid #E5E7EB;
        white-space: nowrap;
    }

    .saas-table td {
        padding: 5px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #E5E7EB;
        color: #202223;
    }

    .saas-table tr:last-child td {
        border-bottom: none;
    }

    /* Custom Checkbox for Tables */
    .saas-checkbox {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        border: 1px solid #C9CCCF;
        cursor: pointer;
        margin: 0;
        vertical-align: middle;
    }

    .saas-checkbox:checked {
        background-color: #1A1A1A;
        border-color: #1A1A1A;
    }

    /* Danger Card */
    .saas-danger-card {
        border: 1px solid #FED3D1;
        background: #FFFFFF;
    }

    .saas-danger-header {
        background: #FFF5F5;
        padding: 12px 16px;
        border-bottom: 1px solid #FED3D1;
    }

    .saas-danger-title {
        font-size: 14px;
        font-weight: 650;
        color: #8C1105;
        margin-bottom: 2px;
    }

    .saas-danger-desc {
        font-size: 12px;
        color: #D82C0D;
    }

    .saas-amazon-status {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    /* Buttons */
    .saas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        height: 34px;
        text-decoration: none;
    }

    .saas-btn-primary {
        background-color: #1A1A1A;
        color: #FFFFFF;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .saas-btn-primary:hover {
        background-color: #333333;
        color: #FFFFFF;
    }

    .saas-btn-success {
        background-color: #008060;
        color: #FFFFFF;
    }

    .saas-btn-success:hover {
        background-color: #006e52;
        color: #FFFFFF;
    }

    .saas-btn-outline-danger {
        background-color: #FFFFFF;
        border-color: #FED3D1;
        color: #D82C0D;
    }

    .saas-btn-outline-danger:hover {
        background-color: #FFF5F5;
        border-color: #D82C0D;
    }

    .saas-btn-outline-success {
        background-color: #FFFFFF;
        border-color: #AEE9D1;
        color: #008060;
    }

    .saas-btn-outline-success:hover {
        background-color: #F1F8F5;
        border-color: #008060;
    }

    @media(max-width: 576px) {
        .saas-setting-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 8px;
        }

        .saas-amazon-status {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
    }
</style>

<div class="saas-wrapper mt-3">

    {{-- Header --}}
    <div class="saas-page-header">
        <div>
            <h1 class="saas-page-title">Settings</h1>
            <p class="saas-page-subtitle">Manage sync, automation, regional preferences and Amazon connection</p>
        </div>
    </div>

    {{-- Form 1: General Settings --}}
    <form action="{{ route('settings.update', ['shop' => request('shop')]) }}" method="POST">
        @csrf

        {{-- Sync & Automation Settings --}}
        <div class="saas-card">
            <div class="saas-card-body">
                <div class="saas-section-title">Sync & Automation</div>
                <div class="saas-section-desc">Control how Amazon and Shopify data stays in sync.</div>

                <div class="saas-setting-row">
                    <div>
                        <div class="saas-setting-label">Auto-sync Orders</div>
                        <div class="saas-setting-help">Fetch Amazon orders automatically every hour.</div>
                    </div>
                    <div class="form-check form-switch m-0 saas-switch-wrapper">
                        <input class="form-check-input shadow-none"
                            type="checkbox"
                            name="auto_sync"
                            value="1"
                            {{ old('auto_sync', $settings->auto_sync ?? '0') == '1' ? 'checked' : '' }}>
                    </div>
                </div>

                <div class="saas-setting-row">
                    <div>
                        <div class="saas-setting-label">AI Assistance</div>
                        <div class="saas-setting-help">Automatically map SKUs using AI.</div>
                    </div>
                    <div class="form-check form-switch m-0 saas-switch-wrapper">
                        <input class="form-check-input shadow-none"
                            type="checkbox"
                            name="ai_assist"
                            value="1"
                            {{ old('ai_assist', $settings->ai_assist ?? '0') == '1' ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="saas-setting-row">
                    <div>
                        <div class="saas-setting-label">Automatic SKU Mapping</div>
                        <div class="saas-setting-help"> Automatically map Shopify and Amazon products with matching SKUs. Existing mappings are never modified.</div>
                    </div>
                    <div class="form-check form-switch m-0 saas-switch-wrapper">
                        <input class="form-check-input shadow-none"
                            type="checkbox"
                            id="auto_sku_mapping"
                            name="auto_sku_mapping"
                            {{ old('auto_sku_mapping', $settings?->auto_sku_mapping) ? 'checked' : '' }}>

                    </div>
                </div>
            </div>
        </div>

        {{-- Regional Settings --}}
        <div class="saas-card">
            <div class="saas-card-body">
                <div class="saas-section-title">Regional Settings</div>
                <div class="saas-section-desc">Configure currency and tax handling for imported orders.</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="saas-label">Default Currency</label>
                        <select name="currency" class="saas-select">
                            <option value="USD" {{ old('currency', $settings->currency ?? 'USD') == 'USD' ? 'selected' : '' }}>
                                USD - US Dollar
                            </option>
                            <option value="CAD" {{ old('currency', $settings->currency ?? 'USD') == 'CAD' ? 'selected' : '' }}>
                                CAD - Canadian Dollar
                            </option>
                            <option value="GBP" {{ old('currency', $settings->currency ?? 'USD') == 'GBP' ? 'selected' : '' }}>
                                GBP - British Pound
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="saas-label">Tax Behavior</label>
                        <select name="tax_behavior" class="saas-select">
                            <option value="include" {{ old('tax_behavior', $settings->tax_behavior ?? 'include') == 'include' ? 'selected' : '' }}>
                                Prices include tax
                            </option>
                            <option value="exclude" {{ old('tax_behavior', $settings->tax_behavior ?? 'include') == 'exclude' ? 'selected' : '' }}>
                                Prices exclude tax
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        {{-- Shopify Inventory Location --}}
        <div class="saas-card">
            <div class="saas-card-body">
                <div class="saas-section-title">Shopify Inventory</div>

                <div class="saas-section-desc">
                    Select the Shopify location that will be used for inventory sync and updates.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="saas-label">Select Your Shopify Location</label>

                        <select
                            name="selected_location_index"
                            class="saas-select">
                            <option value="">Select a location</option>

                            @foreach(($shop->shopify_locations ?? []) as $index => $location)
                            <option
                                value="{{ $index }}"
                                {{ old(
                                'selected_location_index',
                                $shop->selected_location_index
                            ) == $index ? 'selected' : '' }}>
                                {{ $location['name'] ?? 'Unnamed Location' }}
                            </option>
                            @endforeach
                        </select>

                        @if(empty($shop->shopify_locations))
                        <div class="text-muted mt-2" style="font-size: 12px;">
                            No Shopify locations found.
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        {{-- Save Button for General Settings --}}
        <div class="d-flex justify-content-end mb-3">
            <button class="saas-btn saas-btn-primary">
                Save Changes
            </button>
        </div>
    </form>

    {{-- Form 2: Notification Settings --}}
    <form action="{{ route('notification.settings.update') }}" method="POST">
        @csrf
        <div class="saas-card">
            <div class="saas-card-body p-0">
                <div class="p-3 border-bottom border-light">
                    <div class="saas-section-title">Notification Permissions</div>
                    <div class="saas-section-desc mb-0">Control how you receive notifications.</div>
                </div>

                <div class="table-responsive">
                    <table class="saas-table">
                        <thead>
                            <tr>
                                <th>Notification</th>
                                <th>Description</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">In-App</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notifications as $n)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $n->title }}</td>
                                <td class="text-muted" style="font-size: 12px;">{{ $n->description }}</td>
                                <td class="text-center">
                                    <input
                                        class="saas-checkbox shadow-none"
                                        type="checkbox"
                                        name="notifications[{{ $n->notification_key }}][email]"
                                        {{ $n->mail_enabled ? 'checked' : '' }}>
                                </td>
                                <td class="text-center">
                                    <input
                                        class="saas-checkbox shadow-none"
                                        type="checkbox"
                                        name="notifications[{{ $n->notification_key }}][in_app]"
                                        {{ $n->app_enabled ? 'checked' : '' }}>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-top border-light d-flex justify-content-end">
                    <button type="submit" class="saas-btn saas-btn-primary">
                        Save Notification Settings
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Danger / Connection Zone --}}
    <div class="saas-card saas-danger-card">
        <div class="saas-danger-header">
            <h5 class="saas-danger-title">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $shop->amazon_refresh_token ? 'Danger Zone' : 'Amazon Connection' }}
            </h5>
            <p class="saas-danger-desc m-0">
                @if($shop->amazon_refresh_token)
                Disconnecting will immediately stop all syncing.
                @else
                Connect Amazon and manage all orders in one place.
                @endif
            </p>
        </div>
        <div class="saas-card-body">
            <div class="saas-amazon-status">
                <div>
                    <div class="fw-semibold text-dark mb-1" style="font-size: 13px;">Amazon Account</div>
                    <div class="text-muted" style="font-size: 12px;">
                        Connected as:
                        <strong class="text-dark">{{ $shop->amazon_seller_id ?? 'Not Connected' }}</strong>
                    </div>
                </div>

                @if($shop->amazon_refresh_token)
                <button class="saas-btn saas-btn-outline-danger"
                    onclick="confirmDisconnect()"
                    type="button">
                    Disconnect
                </button>
                @else
                <a class="saas-btn saas-btn-outline-success"
                    href="{{ route('amazon.connect') }}?shop={{ $activeShop }}">
                    <i class="bi bi-link-45deg me-1"></i> Connect Amazon
                </a>
                @endif
            </div>
        </div>
    </div>

</div>

<script>
    function confirmDisconnect() {
        if (confirm('Are you sure you want to disconnect Amazon?')) {
            window.location.href = "{{ route('amazon.disconnect') }}";
        }
    }
</script>

@endsection