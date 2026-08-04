@php
    $isAdmin = request()->is('admin*');
    $layout = $isAdmin ? 'admin.layout.app' : 'layouts.app';
@endphp

@extends($layout)
@section('title', '503 - Service Unavailable')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .page-503, .page-503 * { margin: 0; padding: 0; box-sizing: border-box; }
    .page-503 { font-family: "Inter", -apple-system, sans-serif; background: #F4F6F8; color: #111827; width: 100%; height: 100%; min-height: 80vh; flex: 1; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased; }
    .page-503 .error-container { text-align: center; padding: 40px 24px; max-width: 480px; width: 100%; }
    .page-503 .error-code { font-size: 8rem; font-weight: 800; line-height: 1; letter-spacing: -0.04em; background: linear-gradient(135deg, #f59e0b, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 8px; }
    .page-503 .error-icon { font-size: 3rem; margin-bottom: 8px; }
    .page-503 .error-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 8px; }
    .page-503 .error-message { font-size: 0.95rem; color: #6B7280; line-height: 1.6; margin-bottom: 32px; max-width: 360px; margin-left: auto; margin-right: auto; }
    .page-503 .btn-home { display: inline-flex; align-items: center; gap: 8px; background: #111827; color: #fff; padding: 12px 28px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: background 0.2s ease, transform 0.15s ease; border: none; cursor: pointer; }
    .page-503 .btn-home:hover { background: #1f2937; transform: translateY(-1px); }
    .page-503 .btn-home svg { width: 18px; height: 18px; }
    .page-503 .error-details { margin-top: 32px; padding-top: 24px; border-top: 1px solid #E5E7EB; font-size: 0.8rem; color: #9CA3AF; }
    @media (max-width: 480px) { .page-503 .error-code { font-size: 5rem; } .page-503 .error-title { font-size: 1.25rem; } .page-503 .error-container { padding: 24px 16px; } }
</style>

<div class="page-503">
    <div class="error-container">
        <div class="error-icon">🚧</div>
        <div class="error-code">503</div>
        <h1 class="error-title">Service Unavailable</h1>
        <p class="error-message">
            We're currently performing some scheduled maintenance to improve your experience. 
            We'll be back online shortly!
        </p>
        <a href="javascript:window.location.reload(true)" class="btn-home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.59-9.21l-5.42-5.42"/>
            </svg>
            Refresh Page
        </a>
        <div class="error-details">{{ config('app.name') }} &mdash; Systems will return to normal functionality soon.</div>
    </div>
</div>
@endsection