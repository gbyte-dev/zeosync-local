@extends('layouts.app')

@section('content')
<style>
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
        grid-template-columns: 1fr;
        gap: 20px;
        align-items: start;
        max-width: 980px;
        margin: 0 auto;
    }

    .ai-chat-card,
    .ai-chat-form {
        background: #ffffff;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 12px 16px;
    }

    /* Make form sticky at viewport bottom for ChatGPT-like UX */
    .ai-chat-form {
        position: sticky;
        bottom: 16px;
        z-index: 40;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 12px 16px;
        border-top: 1px solid #E5E7EB; /* subtle divider above input */
        background: #fff;
    }

    .ai-chat-card {
        display: flex;
        flex-direction: column;
    }

    .ai-chat-log {
        max-height: calc(75vh - 90px);
        padding-bottom: 110px;
    }

    /* Helper label above input */
    .ai-chat-helper {
        font-size: 12px;
        color: #6D7175;
        margin-bottom: 8px;
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
        font-size: 13px;
    }

    p {
        font-size: 13px;
    }

    .ai-chat-message.assistant {
        margin-right: auto;
        background: #FFFFFF;
        border-color: #E6EDF8;
        color: #0F172A;
        font-size: 13px;
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

    /* Compact pill-style input bar */
    .ai-chat-input-bar {
        display: flex;
        gap: 8px;
        align-items: center;
        background: #ffffff;
        border: 1px solid #E5E7EB;
        padding: 0px 10px;
        border-radius: 9999px;
    }

    .ai-chat-input-bar .input-leading {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 9999px;
        background: #F3F4F6;
        color: #111827;
        font-weight: 600;
        margin-right: 8px;
    }

    .ai-chat-input {
        border: none;
        outline: none;
        flex: 1 1 auto;
        padding: 6px 8px;
        font-size: 0.95rem;
        background: transparent;
        resize: none;
        min-height: 20px;
        max-height: 120px;
        overflow: auto;
        line-height: 1.25;
    }

    .ai-send-btn {
        background: #2563EB;
        color: #fff;
        border: none;
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .ai-send-btn:disabled {
        opacity: 0.6;
    }

    /* sending spinner state for icon-only button */
    .ai-send-btn.sending {
        position: relative;
        pointer-events: none;
    }

    .ai-send-btn.sending svg { opacity: 0; }

    .ai-send-btn.sending::after {
        content: '';
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        animation: btn-spin 0.9s linear infinite;
    }

    @keyframes btn-spin { to { transform: translateY(-50%) rotate(360deg); } }

    .ai-chat-input:focus {
        box-shadow: none;
    }

    /* Tooltip for keyboard hint */
    .tooltip-wrapper {
        position: relative;
        display: inline-block;
    }

    .ai-tooltip {
        position: absolute;
        bottom: 52px;
        right: 0;
        background: rgba(17,24,39,0.95);
        color: #fff;
        font-size: 12px;
        padding: 6px 8px;
        border-radius: 6px;
        white-space: nowrap;
        opacity: 0;
        transform: translateY(6px);
        transition: opacity 120ms ease, transform 120ms ease;
        pointer-events: none;
        z-index: 60;
    }

    .ai-tooltip::after {
        content: '';
        position: absolute;
        bottom: -6px;
        right: 10px;
        border-width: 6px 6px 0 6px;
        border-style: solid;
        border-color: rgba(17,24,39,0.95) transparent transparent transparent;
    }

    .tooltip-wrapper:hover .ai-tooltip,
    .tooltip-wrapper:focus-within .ai-tooltip {
        opacity: 1;
        transform: translateY(0);
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
    .in-iframe .content {
        padding: 5px !important;
    }
    .saas-page-title {
        font-size: 16px;
        font-weight: 650;
        letter-spacing: -0.2px;
        color: #1A1A1A;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
    }

    .saas-page-subtitle {
        color: #6D7175;
        font-size: 12px;
        margin: 0;
    }

    #ai-chat-updated{
        font-size: 12px;
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

<div class="content">
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
        <div class="saas-card" id="ai-chat-history-card">
            <div class="saas-card-body ai-chat-card">
                <div class="ai-chat-card-header">
                    <div>
                        <h2>Conversation</h2>
                    </div>
                    <span class="" id="ai-chat-updated">
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

        <div class="saas-card" id="ai-chat-form-card">
            <div class="saas-card-body ai-chat-form">
            
                <form id="ai-chat-form" method="POST" action="{{ route('shopify.ai.chat.ask', ['shop' => $currentShop]) }}">
                @csrf

                <div class="mb-2" style="display:none;">
                    <div class="ai-chat-status" id="ai-chat-status">
                        {{ count($chatHistory) > 0 ? 'Last activity: ' . now()->format('g:i A') : '' }}
                    </div>
                </div>

                <div class="ai-chat-input-bar">
                    <div class="input-leading" aria-hidden="true">
                        <!-- chat bubble SVG -->
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10z" stroke="#374151" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <textarea id="prompt"
                        name="prompt"
                        class="ai-chat-input @error('prompt') is-invalid @enderror"
                        placeholder="Ask anything"
                        aria-label="Message"
                        autocomplete="off"
                        rows="1">{{ old('prompt') }}</textarea>
                    <div class="tooltip-wrapper">
                        <button type="submit" class="ai-send-btn" id="ai-chat-submit" aria-label="Send message" title="Send message">
                            <!-- paper plane SVG -->
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M22 2L11 13" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M22 2L15 22l-4-9-9-4 20-7z" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="#2563EB"/>
                            </svg>
                        </button>
                        <div class="ai-tooltip" role="status">Enter to send • Shift+Enter for newline</div>
                    </div>
                </div>

                <div id="ai-chat-error" class="ai-chat-error"></div>
            </form>
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

        // Auto-resize textarea to fit content
        const autoResize = (el) => {
            if (!el) return;
            el.style.height = 'auto';
            const h = el.scrollHeight;
            el.style.height = Math.min(h, 120) + 'px';
        };

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

        // handle Enter to send (without Shift) and Shift+Enter for newline
        if (promptField) {
            // initial resize
            autoResize(promptField);
            promptField.addEventListener('input', () => autoResize(promptField));
            promptField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (!submitButton.disabled) submitButton.click();
                }
            });
        }

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

            // disable and show spinner state on send button
            submitButton.disabled = true;
            submitButton.classList.add('sending');
            submitButton.setAttribute('aria-busy', 'true');
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
                    if (promptField) {
                        promptField.style.height = 'auto';
                        promptField.focus();
                    }
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
