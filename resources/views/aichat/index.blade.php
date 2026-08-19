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
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.01em;
    }

    .ai-chat-header .page-subtitle {
        margin: 6px 0 0;
        color: #6B7280;
        max-width: 760px;
        font-size: 0.95rem;
        line-height: 1.35;
    }

    .ai-chat-grid {
        display: grid;
        grid-template-columns: 1fr 1.6fr;
        gap: 20px;
        align-items: start;
    }

    .ai-chat-card,
    .ai-chat-form {
        background: #ffffff;
        border: 1px solid #E6EDF8;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
        padding: 22px;
    }

    .ai-chat-card-header,
    .ai-chat-form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        gap: 12px;
    }

    .ai-chat-card-header h2,
    .ai-chat-form-header h2 {
        font-size: 1rem;
        margin: 0;
        color: #111827;
        font-weight: 700;
    }

    .ai-chat-log {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 72vh;
        overflow-y: auto;
        padding-right: 6px;
        min-height: 420px;
    }

    .ai-chat-message {
        display: flex;
        gap: 12px;
        padding: 12px;
        border-radius: 14px;
        border: 1px solid transparent;
        align-items: flex-start;
        max-width: 82%;
    }

    .ai-chat-message.user {
        margin-left: auto;
        background: linear-gradient(180deg,#ECFDF5,#DBFCEC);
        border-color: #BBF7D0;
        color: #065F46;
    }

    .ai-chat-message.assistant {
        margin-right: auto;
        background: #FFFFFF;
        border-color: #E6EDF8;
        color: #0F172A;
    }

    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }

    .message-avatar.user { background: #0ea5a4; }
    .message-avatar.assistant { background: #2563EB; }

    .message-content { flex: 1; }

    .message-time {
        font-size: 0.78rem;
        color: #6B7280;
        margin-top: 8px;
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
        min-height: 140px;
        resize: vertical;
        border-radius: 12px;
        border: 1px solid #E6EDF8;
        padding: 14px;
        font-size: 0.95rem;
        color: #0F172A;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .ai-chat-form textarea:focus {
        border-color: #2563EB;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
    }

    .ai-chat-form .form-text {
        color: #6B7280;
        font-size: 0.9rem;
        margin-top: 10px;
    }

    .ai-chat-footer {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 14px;
        margin-top: 12px;
    }

    .ai-chat-footer .btn {
        min-width: 140px;
    }

    .ai-chat-status {
        color: #6B7280;
        font-size: 0.92rem;
        text-align: left;
    }

    .ai-chat-error {
        margin-top: 12px;
        display: none;
        color: #b91c1c;
        font-size: 0.9rem;
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

        .ai-chat-footer {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ai-chat-page">
    <div class="saas-page-header">
        <div>
            <h1 class="saas-page-title">AI Chat</h1>
            <p class="saas-page-subtitle">Ask about your store, products, pricing and inventory.</p>
        </div>
        <div>
            <a href="{{ route('shopify.ai.chat', ['shop' => $currentShop, 'reset' => 1]) }}"
                class="btn btn-outline-secondary">
                Clear conversation
            </a>
        </div>
    </div>

    <div class="ai-chat-grid">
        <div class="saas-card" id="ai-chat-form-card">
            <div class="saas-card-body ai-chat-form">
                <div class="ai-chat-form-header">
                    <h2>Ask the AI</h2>
                </div>

                <form id="ai-chat-form" method="POST" action="{{ route('shopify.ai.chat.ask', ['shop' => $currentShop]) }}">
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
                    <div class="ai-chat-status" id="ai-chat-status">
                        {{ count($chatHistory) > 0 ? 'Last activity: ' . now()->format('g:i A') : 'No messages yet' }}
                    </div>
                    <button type="submit" class="btn btn-primary" id="ai-chat-submit">Send question</button>
                </div>

                <div id="ai-chat-error" class="ai-chat-error"></div>
            </form>
                </form>
            </div>
        </div>

        <div class="saas-card" id="ai-chat-history-card">
            <div class="saas-card-body ai-chat-card">
                <div class="ai-chat-card-header">
                    <div>
                        <h2>Conversation</h2>
                        <p class="text-muted mb-0">Recent questions &amp; answers</p>
                    </div>
                    <span class="text-muted" id="ai-chat-updated">
                        {{ count($chatHistory) > 0 ? 'Last updated: ' . now()->format('g:i A') : '' }}
                    </span>
                </div>
                <div class="ai-chat-log" id="ai-chat-log">
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
                    @endphp

                    <div class="ai-chat-message {{ $message['role'] === 'user' ? 'user' : 'assistant' }}">
                        {!! $linkedMessage !!}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('ai-chat-form');
        const log = document.getElementById('ai-chat-log');
        const status = document.getElementById('ai-chat-status');
        const updated = document.getElementById('ai-chat-updated');
        const errorBox = document.getElementById('ai-chat-error');
        const submitButton = document.getElementById('ai-chat-submit');
        const promptField = document.getElementById('prompt');

        const escapeHtml = (text) => {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const linkify = (text) => {
            const escaped = escapeHtml(text);
            return escaped
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
                .replace(/\n/g, '<br>');
        };

        const createMessageElement = (role, message) => {
            const item = document.createElement('div');
            item.className = 'ai-chat-message ' + (role === 'user' ? 'user' : 'assistant');
            item.innerHTML = linkify(message);
            return item;
        };

        const scrollToBottom = () => {
            if (!log) return;
            log.scrollTop = log.scrollHeight;
        };

        const formatTime = (date) => {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        };

        if (log) {
            scrollToBottom();
        }

        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const prompt = promptField.value.trim();
            if (!prompt) {
                errorBox.textContent = 'Please enter your question.';
                errorBox.style.display = 'block';
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Sending...';
            errorBox.style.display = 'none';

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(async (response) => {
                    const data = await response.json();

                    if (!response.ok || data.success === false) {
                        const message = data.error || (data.errors && data.errors.prompt && data.errors.prompt[0]) || 'Unable to send your question.';
                        throw new Error(message);
                    }

                    const userMessage = createMessageElement('user', prompt);
                    const assistantMessage = createMessageElement('assistant', data.message);

                    if (log.querySelector('.ai-chat-empty')) {
                        log.innerHTML = '';
                    }

                    log.appendChild(userMessage);
                    log.appendChild(assistantMessage);
                    scrollToBottom();
                    promptField.value = '';
                    const now = new Date();
                    status.textContent = 'Last activity: ' + formatTime(now);
                    updated.textContent = 'Last updated: ' + formatTime(now);
                })
                .catch((error) => {
                    errorBox.textContent = error.message || 'Unable to send your question.';
                    errorBox.style.display = 'block';
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Send question';
                });
        });
    });
</script>
@endsection
