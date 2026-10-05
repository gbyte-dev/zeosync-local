@extends('layouts.app')

@section('content')
<div class="saas-wrapper" style="max-width: 540px; margin: 40px auto; padding: 16px;">
    <div class="saas-card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 32px 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: #EFF6FF; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="bi bi-envelope" style="font-size: 22px; color: #2563EB;"></i>
            </div>
            <h1 style="font-size: 18px; font-weight: 650; color: #111827; margin: 0 0 6px 0;">Complete Store Setup</h1>
            <p style="font-size: 13px; color: #6B7280; line-height: 1.5; margin: 0;">
                Please provide your contact email address for <strong>{{ $shopName }}</strong> to complete setup and access your dashboard.
            </p>
        </div>

        @if(session('error'))
            <div class="alert alert-danger" role="alert" style="font-size: 13px; margin-bottom: 16px;">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert" style="font-size: 13px; margin-bottom: 16px;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf
            @if(!empty($host))
                <input type="hidden" name="host" value="{{ $host }}">
            @endif
            @if(!empty($embedded))
                <input type="hidden" name="embedded" value="{{ $embedded }}">
            @endif

            <div style="margin-bottom: 20px;">
                <label for="email" style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block;">
                    Email Address <span style="color: #DC2626;">*</span>
                </label>
                <input type="email" 
                       class="form-control @error('email') is-invalid @enderror" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       placeholder="merchant@example.com" 
                       required 
                       autofocus
                       style="width: 100%; padding: 10px 14px; font-size: 14px; border: 1px solid #D1D5DB; border-radius: 8px;">
                @error('email')
                    <div class="invalid-feedback" style="font-size: 12px; color: #DC2626; margin-top: 4px;">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; background: #2563EB; border: none; padding: 11px 16px; font-size: 14px; font-weight: 600; border-radius: 8px; color: #FFFFFF; cursor: pointer;">
                Save &amp; Continue
            </button>
        </form>

    </div>
</div>
@endsection
