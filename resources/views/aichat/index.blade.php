@extends('layouts.app')

@section('content')
<style>
    .ai-chat-page {
        font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #111827;
    }

    .ai-chat-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        margin-bottom: 22px;
    }

    .ai-chat-header .page-title {
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0;
    }

    .ai-chat-header .page-subtitle {
        margin: 6px 0 0;
        color: #6B7280;
        max-width: 760px;
    }

    .ai-chat-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 20px;
    }

    .ai-chat-card,
    .ai-chat-form {
        background: #ffffff;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
        padding: 22px;
    }

    .ai-chat-card-header,
    .ai-chat-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
    }

    .ai-chat-card-header h2,
    .ai-chat-form-header h2 {
        font-size: 1.05rem;
        margin: 0;
        color: #111827;
    }

    .ai-chat-log {
        display: flex;
        flex-direction: column;
        gap: 16px;
        max-height: 68vh;
        overflow-y: auto;
        padding-right: 4px;
    }

    .ai-chat-message {
        padding: 16px 18px;
        border-radius: 16px;
        border: 1px solid transparent;
        line-height: 1.6;
        white-space: pre-line;
    }

    .ai-chat-message.user {
        align-self: flex-end;
        background: #EFF6FF;
        border-color: #BFDBFE;
        color: #1D4ED8;
    }

    .ai-chat-message.assistant {
        align-self: flex-start;
        background: #F9FAFB;
        border-color: #E5E7EB;
        color: #111827;
    }

    .ai-chat-message .message-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        margin-bottom: 8px;
        text-transform: uppercase;
        color: #6B7280;
    }

    .ai-chat-message a {
        color: #2563EB;
        text-decoration: underline;
    }

    .message-links {
        margin-top: 14px;
        padding: 14px;
        border-radius: 14px;
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
    }

    .message-links strong {
        display: block;
        margin-bottom: 10px;
        font-size: 0.92rem;
        color: #111827;
    }

    .message-links ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 8px;
    }

    .message-links li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #F8FAFC;
        border: 1px solid #E5E7EB;
        font-size: 0.94rem;
        color: #111827;
    }

    .message-links li a {
        color: #1D4ED8;
        text-decoration: none;
        word-break: break-all;
    }

    .message-links li a:hover {
        text-decoration: underline;
    }

    .ai-chat-form textarea {
        min-height: 170px;
        resize: vertical;
        border-radius: 12px;
        border: 1px solid #D1D5DB;
        padding: 14px;
        font-size: 0.95rem;
        color: #111827;
    }

    .ai-chat-form .form-text {
        color: #6B7280;
        font-size: 0.9rem;
        margin-top: 8px;
    }

    .ai-chat-footer {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .ai-chat-footer .btn {
        min-width: 140px;
    }

    .ai-chat-empty {
        color: #6B7280;
        padding: 18px 16px;
        border-radius: 14px;
        background: #F8FAFC;
        border: 1px dashed #D1D5DB;
        text-align: center;
    }

    @media (max-width: 991.98px) {
        .ai-chat-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ai-chat-page">
    <div class="ai-chat-header">
        <div>
            <h1 class="page-title">AI Chat</h1>
            <p class="page-subtitle">Ask questions about your Shopify/Amazon store, product performance, pricing, and best selling items. This view uses the same app layout and sidebar styling as other pages.</p>
        </div>
        <div>
            <a href="{{ route('shopify.ai.chat', ['shop' => $currentShop, 'reset' => 1]) }}"
                class="btn btn-outline-secondary">
                Clear conversation
            </a>
        </div>
    </div>

    <div class="ai-chat-grid">
        <div class="ai-chat-card">
            <div class="ai-chat-card-header">
                <h2>Conversation</h2>
                <span class="text-muted">AI history stored in this browser session</span>
            </div>
            <div class="ai-chat-log">
                @if(count($chatHistory) === 0)
                    <div class="ai-chat-empty">
                        Start by asking how your products are performing, which product is selling best, or for a Shopify/Amazon price comparison.
                    </div>
                @endif

                @foreach($chatHistory as $message)
                    @php
                        $escapedMessage = e($message['message']);
                        $linkedMessage = preg_replace(
                            '/(https?:\/\/[^\s]+)/',
                            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
                            nl2br($escapedMessage)
                        );

                        preg_match_all('/https?:\/\/[^\s]+/', $message['message'], $matches);
                        $detectedLinks = array_unique($matches[0] ?? []);
                    @endphp

                    <div class="ai-chat-message {{ $message['role'] === 'user' ? 'user' : 'assistant' }}">
                        <span class="message-label">{{ ucfirst($message['role']) }}</span>
                        {!! $linkedMessage !!}

                        @if($message['role'] === 'assistant' && count($detectedLinks))
                            <div class="message-links">
                                <strong>Product details</strong>
                                <ul>
                                    @foreach($detectedLinks as $link)
                                        <li>
                                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer">{{ $link }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ai-chat-form">
            <div class="ai-chat-form-header">
                <h2>Ask the AI</h2>
            </div>

            <form method="POST" action="{{ route('shopify.ai.chat.ask', ['shop' => $currentShop]) }}">
                @csrf

                <div class="mb-3">
                    <label for="prompt" class="form-label">Your question</label>
                    <textarea id="prompt"
                        name="prompt"
                        class="form-control @error('prompt') is-invalid @enderror"
                        placeholder="For example: Which product is selling the most? What is the Shopify and Amazon price of my best seller?"
                        rows="6">{{ old('prompt') }}</textarea>
                    @error('prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">You can ask about product performance, price comparisons, or inventory insights.</div>
                </div>

                <div class="ai-chat-footer">
                    <button type="submit" class="btn btn-primary">Send question</button>
                    <a href="{{ route('shopify.ai.chat', ['shop' => $currentShop]) }}" class="btn btn-outline-secondary">Refresh page</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
