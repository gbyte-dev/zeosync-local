@extends('admin.layout.app')
@section('title', 'Settings')

@section('content')

@php
$groups = [
'App Basic Details' => ['app_name','currency','timezone', 'app_logo','app_favicon'],

'Email / SMTP Settings' => [
'admin_email','SMTP_host','SMTP_port',
'SMTP_username','SMTP_password',
'SMTP_encryption','from_email','from_name'
],
'Stripe Settings' => [
'stripe_secret_key','stripe_publishable_key','stripe_webhook_secret'
],
'Amazon Credentials' => [
'production_client_id',
'production_client_secret',
'amazon_refresh_token',
'amazon_seller_id',
'amazon_app_id'
],
'Shopify Credentials' => [
'SHOPIFY_API_KEY','SHOPIFY_API_SECRET','SHOPIFY_REDIRECT_URI'
],
'Gemini AI Info' => [
'openai_api_key',
'ai_provider',
'openai_model',
'openai_temperature',
'openai_endpoint',
],
];
@endphp

<!-- 'Amazon Test Credentials' => [
        'test_client_id','test_client_secret','test_refresh_token','is_testmode'
    ], -->
<div class="container-fluid">

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Settings</h5>
        </div>

        <div class="card-body">

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                @csrf

                {{-- LOOP GROUPS --}}
                @foreach($groups as $groupTitle => $keys)

                <div class="card mb-4">
                    <div class="card-header py-2">
                        <strong>{{ $groupTitle }}</strong>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            @foreach($keys as $key)

                            @php
                            $value = $settings[$key] ?? '';
                            if($key == 'app_logo' || $key == 'app_favicon'){
                            $type = 'file';
                            }else{
                            $type = 'text';
                            }
                            @endphp

                            <div class="col-md-6 col-12">
                                <div class="mb-2">

                                    <label class="form-label small mb-1">
                                        {{ ucfirst(str_replace('_',' ', $key)) }}
                                    </label>

                                    @if($key === 'admin_email')

                                    <input type="email" name="{{ $key }}" class="form-control"
                                        value="{{ old($key, $value) }}">

                                    @elseif($key === 'SMTP_password')

                                    <input type="password" name="{{ $key }}" class="form-control"
                                        value="{{ old($key, $value) }}">

                                    @elseif($key === 'SMTP_encryption')

                                    <select name="{{ $key }}" class="form-select">
                                        <option value="">None</option>
                                        <option value="tls" {{ old($key, $value ?: 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ old($key, $value) == 'ssl' ? 'selected' : '' }}>SSL</option>
                                    </select>

                                    @elseif(in_array($key, ['app_maintenance','install_info']))

                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="{{ $key }}" value="1" class="form-check-input"
                                            {{ old($key, $value) == '1' ? 'checked' : '' }}>
                                    </div>

                                    @elseif($key === 'is_testmode')

                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" name="{{ $key }}" value="1"
                                            class="form-check-input"
                                            {{ old($key, $value ?: '1') == '1' ? 'checked' : '' }}>
                                    </div>

                                    @else

                                    <input type="{{ $type }}" name="{{ $key }}" class="form-control"
                                        value="{{ old($key, $value) }}">

                                    @if($key === 'app_logo' && $value)
                                    <div class="mt-2">
                                        <img src="{{ asset($value) }}"
                                            alt="App Logo"
                                            style="max-height:50px;">
                                    </div>

                                    @elseif($key === 'app_favicon' && $value)
                                    <div class="mt-2">
                                        <img src="{{ asset($value) }}"
                                            alt="App Favicon"
                                            style="max-height:50px;">
                                    </div>
                                    @endif

                                    @endif

                                </div>
                            </div>

                            @endforeach

                        </div>
                    </div>
                </div>

                @endforeach

                {{-- NOTIFICATION PERMISSIONS --}}
                <div class="card mb-4">
                    <div class="card-header py-2">
                        <strong>Notification Permissions</strong>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Notification</th>
                                        <th>Description</th>
                                        <th class="text-center">Email</th>
                                        <th class="text-center">In-App</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($notifications as $n)
                                    <tr>
                                        <td>
                                            <strong>{{ $n->title }}</strong>
                                        </td>

                                        <td class="text-muted">
                                            {{ $n->description }}
                                        </td>

                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input"
                                                    type="checkbox"
                                                    name="notifications[{{ $n->notification_key }}][email]"
                                                    {{ $n->email_enabled ? 'checked' : '' }}>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input"
                                                    type="checkbox"
                                                    name="notifications[{{ $n->notification_key }}][in_app]"
                                                    {{ $n->in_app_enabled ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        <td class="text-center">

                                            @if($n->notification_key == 'trial_ending')

                                            <button type="submit"
                                                form="trialNotifyForm"
                                                class="btn btn-sm btn-warning">
                                                <i class="fa fa-paper-plane me-1"></i>
                                                Send Notification
                                            </button>

                                            @else

                                            <span class="text-muted">-</span>

                                            @endif

                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>



                {{-- ALERTS --}}
                @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                    @endforeach
                </div>
                @endif

                @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
                @endif

                @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fa fa-exclamation-triangle me-2"></i>
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                {{-- BUTTON --}}
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        Save Settings
                    </button>
                </div>
            </form>
            <form id="trialNotifyForm"
                action="{{ route('admin.trial.ending.notify') }}"
                method="POST">
                @csrf
            </form>


        </div>
    </div>

</div>

@endsection