@php
    $isAdmin = request()->is('admin*');
    $layout = $isAdmin ? 'admin.layout.app' : 'layouts.app';
    
    try {
        $dashboardUrl = $isAdmin ? route('admin.dashboard') : route('dashboard', ['shop' => request('shop') ?? session('active_shop')]);
    } catch (\Exception $e) {
        $dashboardUrl = $isAdmin ? url('/admin') : url('/');
    }
@endphp

@extends($layout)
@section('title', '500 - Server Error')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .page-500, .page-500 * { margin: 0; padding: 0; box-sizing: border-box; }
    .page-500 { font-family: "Inter", -apple-system, sans-serif; background: #F4F6F8; color: #111827; width: 100%; height: 100%; min-height: 80vh; flex: 1; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased; }
    .page-500 .error-container { text-align: center; padding: 40px 24px; max-width: 480px; width: 100%; }
    .page-500 .error-code { font-size: 8rem; font-weight: 800; line-height: 1; letter-spacing: -0.04em; background: linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 8px; }
    .page-500 .error-icon { font-size: 3rem; margin-bottom: 8px; }
    .page-500 .error-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 8px; }
    .page-500 .error-message { font-size: 0.95rem; color: #6B7280; line-height: 1.6; margin-bottom: 32px; max-width: 360px; margin-left: auto; margin-right: auto; }
    .page-500 .btn-home { display: inline-flex; align-items: center; gap: 8px; background: #111827; color: #fff; padding: 12px 28px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: background 0.2s ease, transform 0.15s ease; border: none; cursor: pointer; }
    .page-500 .btn-home:hover { background: #1f2937; transform: translateY(-1px); }
    .page-500 .btn-home svg { width: 18px; height: 18px; }
    .page-500 .error-details { margin-top: 32px; padding-top: 24px; border-top: 1px solid #E5E7EB; font-size: 0.8rem; color: #9CA3AF; }
    @media (max-width: 480px) { .page-500 .error-code { font-size: 5rem; } .page-500 .error-title { font-size: 1.25rem; } .page-500 .error-container { padding: 24px 16px; } }
</style>

<div class="page-500">
    <div class="error-container">
        <div class="error-icon">⚙️</div>
        <div class="error-code">500</div>
        <h1 class="error-title">Server Error</h1>
        <p class="error-message">
            Whoops, something unexpectedly broke on our end. 
            Our engineering team has been notified and is looking into the issue.
        </p>
        <a href="{{ $dashboardUrl }}" class="btn-home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>
            Back to Dashboard
        </a>
        <div class="error-details">{{ config('app.name') }} &mdash; Thank you for your patience while we fix this.</div>
    </div>
</div>
@endsection