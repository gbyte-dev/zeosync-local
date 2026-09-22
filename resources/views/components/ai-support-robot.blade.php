@php
$currentShop = $activeShop ?? session('active_shop') ?? request('shop') ?? '';
$contactUrl = route('contact', array_filter(['shop' => $currentShop]));
$askUrl = route('shopify.ai.chat.ask', array_filter(['shop' => $currentShop]));
@endphp

<!-- ======================================================== -->
<!-- ZEOSYNC FLOATING AI SUPPORT ROBOT & CHAT WIDGET           -->
<!-- ======================================================== -->
<div id="zeosync-ai-support" class="zeosync-ai-support" aria-live="polite">

    <!-- 1. FLOATING ROBOT LAUNCHER & SPEECH BUBBLE -->
    <div class="zeosync-ai-support__launcher-wrapper" id="zeosyncAiLauncherWrapper">
        
        <!-- Speech Bubble with rotating messages -->
        <div class="zeosync-ai-support__speech-bubble" id="zeosyncAiSpeechBubble" role="status">
            <span class="zeosync-ai-support__speech-text" id="zeosyncAiSpeechText">Have any query? Let's chat!</span>
            <button type="button" class="zeosync-ai-support__speech-close" id="zeosyncAiSpeechClose" aria-label="Dismiss message">✕</button>
            <div class="zeosync-ai-support__speech-tail"></div>
        </div>

        <!-- Robot Button -->
        <button type="button" class="zeosync-ai-support__robot-btn" id="zeosyncAiRobotBtn" aria-label="Open ZeoSync AI Support" aria-expanded="false" aria-controls="zeosyncAiChatPanel">
            <!-- Custom Vector SVG AI Robot Avatar -->
            <svg class="zeosync-ai-support__robot-svg" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <!-- Antenna -->
                <circle cx="36" cy="7" r="4" fill="#3B82F6" class="zeosync-ai-support__antenna-glow"/>
                <rect x="34.5" y="10" width="3" height="7" rx="1.5" fill="#94A3B8"/>
                
                <!-- Head Outer -->
                <rect x="14" y="16" width="44" height="34" rx="12" fill="#1E293B" stroke="#334155" stroke-width="2"/>
                
                <!-- Ears / Connectors -->
                <rect x="10" y="27" width="4" height="12" rx="2" fill="#3B82F6"/>
                <rect x="58" y="27" width="4" height="12" rx="2" fill="#3B82F6"/>
                
                <!-- Screen / Visor -->
                <rect x="19" y="21" width="34" height="23" rx="7" fill="#0F172A"/>
                
                <!-- Cute Robot Eyes -->
                <circle cx="28" cy="31" r="4.5" fill="#38BDF8" class="zeosync-ai-support__eye-left"/>
                <circle cx="29.5" cy="29.5" r="1.5" fill="#FFFFFF"/>
                <circle cx="44" cy="31" r="4.5" fill="#38BDF8" class="zeosync-ai-support__eye-right"/>
                <circle cx="45.5" cy="29.5" r="1.5" fill="#FFFFFF"/>
                
                <!-- Friendly Smiling Mouth (curved track) -->
                <path d="M30 38 Q36 42 42 38" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" fill="none" class="zeosync-ai-support__mouth"/>
                
                <!-- Body Base -->
                <path d="M22 52 L50 52 C53 52 55 54 54 57 L52 65 C51.5 67 49 68 47 68 L25 68 C23 68 20.5 67 20 65 L18 57 C17 54 19 52 22 52 Z" fill="#334155"/>
                
                <!-- Chest Core Light -->
                <circle cx="36" cy="60" r="3" fill="#38BDF8" class="zeosync-ai-support__chest-glow"/>
            </svg>
            
            <!-- Online status badge -->
            <span class="zeosync-ai-support__online-badge" aria-hidden="true"></span>
        </button>
    </div>

    <!-- 2. FLOATING CHAT PANEL -->
    <div class="zeosync-ai-support__panel" id="zeosyncAiChatPanel" role="dialog" aria-modal="false" aria-labelledby="zeosyncAiChatTitle" style="display: none;">
        
        <!-- Header -->
        <div class="zeosync-ai-support__header">
            <div class="zeosync-ai-support__header-info">
                <div class="zeosync-ai-support__header-avatar">
                    <svg viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="28" height="28">
                        <circle cx="36" cy="7" r="4" fill="#38BDF8"/>
                        <rect x="34.5" y="10" width="3" height="7" rx="1.5" fill="#94A3B8"/>
                        <rect x="14" y="16" width="44" height="34" rx="12" fill="#1E293B"/>
                        <rect x="19" y="21" width="34" height="23" rx="7" fill="#0F172A"/>
                        <circle cx="28" cy="31" r="4" fill="#38BDF8"/>
                        <circle cx="44" cy="31" r="4" fill="#38BDF8"/>
                        <path d="M30 38 Q36 42 42 38" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" fill="none"/>
                    </svg>
                    <span class="zeosync-ai-support__avatar-dot" aria-hidden="true"></span>
                </div>
                <div>
                    <h3 class="zeosync-ai-support__header-title" id="zeosyncAiChatTitle">ZeoSync Support</h3>
                    <div class="zeosync-ai-support__header-subtitle">
                        <span class="zeosync-ai-support__status-pill">AI Assistant</span>
                        <span class="zeosync-ai-support__status-text">Online</span>
                    </div>
                </div>
            </div>

            <!-- Header Action Buttons -->
            <div class="zeosync-ai-support__header-actions">
                <!-- 3-Dots Menu -->
                <div class="zeosync-ai-support__menu-wrapper">
                    <button type="button" class="zeosync-ai-support__icon-btn" id="zeosyncAiMenuBtn" aria-label="Support options" aria-haspopup="true" aria-expanded="false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="2"/>
                            <circle cx="12" cy="12" r="2"/>
                            <circle cx="12" cy="19" r="2"/>
                        </svg>
                    </button>
                    <div class="zeosync-ai-support__dropdown-menu" id="zeosyncAiDropdownMenu" style="display: none;">
                        <button type="button" class="zeosync-ai-support__dropdown-item" id="zeosyncAiActionNewChat">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                                <path d="M21 3v5h-5"/>
                                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                                <path d="M8 16H3v5"/>
                            </svg>
                            <span>New Conversation</span>
                        </button>
                        <a href="{{ $contactUrl }}" class="zeosync-ai-support__dropdown-item" id="zeosyncAiActionContact">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <span>Contact Support</span>
                        </a>
                    </div>
                </div>

                <!-- Close Panel Button -->
                <button type="button" class="zeosync-ai-support__icon-btn zeosync-ai-support__close-btn" id="zeosyncAiCloseBtn" aria-label="Close AI Support">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Body / Messages Log -->
        <div class="zeosync-ai-support__messages" id="zeosyncAiMessages" tabindex="0">
            <!-- Initial Greeting -->
            <div class="zeosync-ai-support__msg zeosync-ai-support__msg--assistant">
                <div class="zeosync-ai-support__msg-avatar">
                    <svg viewBox="0 0 72 72" fill="none" width="20" height="20">
                        <circle cx="36" cy="7" r="4" fill="#38BDF8"/>
                        <rect x="14" y="16" width="44" height="34" rx="12" fill="#1E293B"/>
                        <rect x="19" y="21" width="34" height="23" rx="7" fill="#0F172A"/>
                        <circle cx="28" cy="31" r="3.5" fill="#38BDF8"/>
                        <circle cx="44" cy="31" r="3.5" fill="#38BDF8"/>
                    </svg>
                </div>
                <div class="zeosync-ai-support__msg-bubble">
                    <p class="zeosync-ai-support__msg-text">
                        Hi! I'm ZeoSync's AI assistant. I can help you with Shopify, Amazon, products, inventory, orders, billing, and other ZeoSync questions.
                    </p>
                    <p class="zeosync-ai-support__msg-text zeosync-ai-support__msg-text--sub">
                        How can I help you today?
                    </p>
                </div>
            </div>

            <!-- Quick Action Chips -->
            <div class="zeosync-ai-support__quick-actions" id="zeosyncAiQuickActions">
                <button type="button" class="zeosync-ai-support__chip" data-prompt="How do I manage my Shopify products and sync them?">
                    📦 Shopify Help
                </button>
                <button type="button" class="zeosync-ai-support__chip" data-prompt="How do I connect and manage Amazon in ZeoSync?">
                    🛒 Amazon Help
                </button>
                <button type="button" class="zeosync-ai-support__chip" data-prompt="How does inventory synchronization work between Shopify and Amazon?">
                    📊 Inventory Sync
                </button>
                <button type="button" class="zeosync-ai-support__chip" data-prompt="How are orders tracked and synced?">
                    🚚 Orders
                </button>
                <button type="button" class="zeosync-ai-support__chip" data-prompt="How do ZeoSync billing and plans work?">
                    💳 Plans & Billing
                </button>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="zeosync-ai-support__typing-wrapper" id="zeosyncAiTyping" style="display: none;" aria-live="polite">
            <div class="zeosync-ai-support__typing-avatar">
                <svg viewBox="0 0 72 72" fill="none" width="16" height="16">
                    <circle cx="36" cy="7" r="4" fill="#38BDF8"/>
                    <rect x="14" y="16" width="44" height="34" rx="12" fill="#1E293B"/>
                </svg>
            </div>
            <div class="zeosync-ai-support__typing-bubble">
                <span class="zeosync-ai-support__typing-text">AI is typing</span>
                <span class="zeosync-ai-support__typing-dots">
                    <span>.</span><span>.</span><span>.</span>
                </span>
            </div>
        </div>

        <!-- Composer / Input Area -->
        <div class="zeosync-ai-support__composer">
            <form id="zeosyncAiForm" class="zeosync-ai-support__form">
                <textarea
                    id="zeosyncAiInput"
                    class="zeosync-ai-support__textarea"
                    placeholder="Ask ZeoSync anything..."
                    rows="1"
                    maxlength="1200"
                    aria-label="Ask ZeoSync AI assistant"
                ></textarea>
                <button
                    type="submit"
                    id="zeosyncAiSendBtn"
                    class="zeosync-ai-support__send-btn"
                    aria-label="Send message"
                    disabled
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </form>
            <div class="zeosync-ai-support__footer-note">
                Press <strong>Enter</strong> to send &bull; <strong>Shift + Enter</strong> for new line
            </div>
        </div>

    </div>
</div>

<!-- ======================================================== -->
<!-- STYLES (Strictly Scoped to .zeosync-ai-support)          -->
<!-- ======================================================== -->
<style nonce="{{ $cspNonce ?? '' }}">
    /* Root Container */
    .zeosync-ai-support {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99990;
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.4;
        box-sizing: border-box;
    }

    .zeosync-ai-support *,
    .zeosync-ai-support *::before,
    .zeosync-ai-support *::after {
        box-sizing: border-box;
    }

    /* 1. Launcher Wrapper */
    .zeosync-ai-support__launcher-wrapper {
        position: relative;
        display: flex;
        align-items: flex-end;
        justify-content: flex-end;
        gap: 12px;
    }

    /* Robot Floating Button */
    .zeosync-ai-support__robot-btn {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
        border: 2px solid #38BDF8;
        box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.35), 0 0 16px rgba(56, 189, 248, 0.35);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        padding: 6px;
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease, border-color 0.2s ease;
        animation: zeosyncRobotIdleFloat 4s ease-in-out infinite;
    }

    .zeosync-ai-support__robot-btn:hover {
        transform: scale(1.08) translateY(-2px);
        box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.45), 0 0 22px rgba(56, 189, 248, 0.55);
        border-color: #60A5FA;
    }

    .zeosync-ai-support__robot-btn:focus-visible {
        outline: 3px solid #2563EB;
        outline-offset: 3px;
    }

    .zeosync-ai-support__robot-svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    /* Robot SVG Animations */
    .zeosync-ai-support__antenna-glow {
        animation: zeosyncAntennaPulse 2.5s ease-in-out infinite;
    }

    .zeosync-ai-support__eye-left,
    .zeosync-ai-support__eye-right {
        transform-origin: center;
        animation: zeosyncEyeBlink 5s infinite;
    }

    .zeosync-ai-support__chest-glow {
        animation: zeosyncChestPulse 3s ease-in-out infinite;
    }

    @keyframes zeosyncRobotIdleFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    @keyframes zeosyncAntennaPulse {
        0%, 100% { fill: #38BDF8; filter: drop-shadow(0 0 2px #38BDF8); }
        50% { fill: #60A5FA; filter: drop-shadow(0 0 6px #60A5FA); }
    }

    @keyframes zeosyncEyeBlink {
        0%, 94%, 100% { transform: scaleY(1); }
        96% { transform: scaleY(0.1); }
    }

    @keyframes zeosyncChestPulse {
        0%, 100% { opacity: 0.7; }
        50% { opacity: 1; filter: drop-shadow(0 0 4px #38BDF8); }
    }

    /* Online Badge */
    .zeosync-ai-support__online-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 13px;
        height: 13px;
        border-radius: 50%;
        background-color: #10B981;
        border: 2px solid #FFFFFF;
        box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
    }

    /* Speech Bubble */
    .zeosync-ai-support__speech-bubble {
        position: absolute;
        right: 76px;
        bottom: 12px;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 9px 28px 9px 14px;
        box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04);
        white-space: nowrap;
        cursor: pointer;
        display: flex;
        align-items: center;
        transition: transform 0.2s ease, opacity 0.2s ease;
        animation: zeosyncBubbleFade 0.3s ease-out;
    }

    .zeosync-ai-support__speech-bubble:hover {
        transform: translateX(-4px);
        box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.16);
    }

    .zeosync-ai-support__speech-text {
        font-size: 13px;
        font-weight: 600;
        color: #1E293B;
        transition: opacity 0.25s ease;
    }

    .zeosync-ai-support__speech-close {
        position: absolute;
        right: 7px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #94A3B8;
        font-size: 11px;
        cursor: pointer;
        padding: 4px;
        line-height: 1;
        border-radius: 4px;
    }

    .zeosync-ai-support__speech-close:hover {
        color: #475569;
        background: #F1F5F9;
    }

    .zeosync-ai-support__speech-tail {
        position: absolute;
        right: -6px;
        bottom: 16px;
        width: 10px;
        height: 10px;
        background: #FFFFFF;
        border-right: 1px solid #E2E8F0;
        border-bottom: 1px solid #E2E8F0;
        transform: rotate(-45deg);
    }

    @keyframes zeosyncBubbleFade {
        from { opacity: 0; transform: translateX(8px); }
        to { opacity: 1; transform: translateX(0); }
    }

    /* 2. Chat Panel */
    .zeosync-ai-support__panel {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 390px;
        height: 580px;
        max-height: calc(100vh - 48px);
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 16px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 99990;
        animation: zeosyncPanelSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes zeosyncPanelSlideUp {
        from { opacity: 0; transform: translateY(16px) scale(0.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Header */
    .zeosync-ai-support__header {
        background: #0F172A;
        color: #FFFFFF;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #1E293B;
    }

    .zeosync-ai-support__header-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .zeosync-ai-support__header-avatar {
        position: relative;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #1E293B;
        border: 1.5px solid #38BDF8;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .zeosync-ai-support__avatar-dot {
        position: absolute;
        bottom: -1px;
        right: -1px;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background-color: #10B981;
        border: 1.5px solid #0F172A;
    }

    .zeosync-ai-support__header-title {
        font-size: 14.5px;
        font-weight: 700;
        color: #FFFFFF;
        margin: 0;
        line-height: 1.2;
    }

    .zeosync-ai-support__header-subtitle {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
    }

    .zeosync-ai-support__status-pill {
        font-size: 10.5px;
        font-weight: 600;
        background: rgba(56, 189, 248, 0.15);
        color: #38BDF8;
        padding: 1px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .zeosync-ai-support__status-text {
        font-size: 11px;
        color: #94A3B8;
    }

    .zeosync-ai-support__header-actions {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .zeosync-ai-support__icon-btn {
        background: transparent;
        border: none;
        color: #94A3B8;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .zeosync-ai-support__icon-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #FFFFFF;
    }

    /* 3-Dots Dropdown */
    .zeosync-ai-support__menu-wrapper {
        position: relative;
    }

    .zeosync-ai-support__dropdown-menu {
        position: absolute;
        top: 36px;
        right: 0;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        padding: 5px;
        min-width: 175px;
        z-index: 1000;
    }

    .zeosync-ai-support__dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        font-size: 12.5px;
        font-weight: 500;
        color: #334155;
        background: transparent;
        border: none;
        border-radius: 6px;
        text-align: left;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .zeosync-ai-support__dropdown-item:hover {
        background: #F1F5F9;
        color: #0F172A;
        text-decoration: none;
    }

    /* Messages Area */
    .zeosync-ai-support__messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: #F8FAFC;
    }

    .zeosync-ai-support__messages:focus {
        outline: none;
    }

    /* Message Rows */
    .zeosync-ai-support__msg {
        display: flex;
        gap: 8px;
        max-width: 88%;
        animation: zeosyncMsgAppear 0.2s ease-out;
    }

    @keyframes zeosyncMsgAppear {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .zeosync-ai-support__msg--assistant {
        align-self: flex-start;
    }

    .zeosync-ai-support__msg--user {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .zeosync-ai-support__msg-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #1E293B;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .zeosync-ai-support__msg-bubble {
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.45;
        word-break: break-word;
    }

    .zeosync-ai-support__msg--assistant .zeosync-ai-support__msg-bubble {
        background: #FFFFFF;
        color: #1E293B;
        border: 1px solid #E2E8F0;
        border-top-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .zeosync-ai-support__msg--user .zeosync-ai-support__msg-bubble {
        background: #2563EB;
        color: #FFFFFF;
        border-top-right-radius: 4px;
        box-shadow: 0 1px 3px rgba(37, 99, 235, 0.25);
    }

    .zeosync-ai-support__msg-text {
        margin: 0 0 6px 0;
    }

    .zeosync-ai-support__msg-text:last-child {
        margin-bottom: 0;
    }

    .zeosync-ai-support__msg-text--sub {
        color: #64748B;
        font-size: 12px;
    }

    .zeosync-ai-support__msg-bubble a {
        color: #2563EB;
        text-decoration: underline;
        word-break: break-all;
    }

    .zeosync-ai-support__msg--user .zeosync-ai-support__msg-bubble a {
        color: #93C5FD;
    }

    .zeosync-ai-support__msg-bubble ul,
    .zeosync-ai-support__msg-bubble ol {
        margin: 6px 0;
        padding-left: 18px;
    }

    .zeosync-ai-support__msg-bubble li {
        margin-bottom: 3px;
    }

    .zeosync-ai-support__msg-bubble code {
        background: rgba(0, 0, 0, 0.05);
        padding: 2px 4px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 12px;
    }

    .zeosync-ai-support__msg--user .zeosync-ai-support__msg-bubble code {
        background: rgba(255, 255, 255, 0.2);
        color: #FFFFFF;
    }

    /* Error Bubble & Retry */
    .zeosync-ai-support__msg--error .zeosync-ai-support__msg-bubble {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #991B1B;
    }

    .zeosync-ai-support__retry-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;
        padding: 4px 10px;
        font-size: 11.5px;
        font-weight: 600;
        color: #991B1B;
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .zeosync-ai-support__retry-btn:hover {
        background: #FECACA;
    }

    /* Quick Action Chips */
    .zeosync-ai-support__quick-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
        padding-left: 36px;
    }

    .zeosync-ai-support__chip {
        font-size: 11.5px;
        font-weight: 500;
        color: #2563EB;
        background: #EFF6FF;
        border: 1px solid #BFDBFE;
        border-radius: 16px;
        padding: 4px 10px;
        cursor: pointer;
        transition: all 0.15s ease;
        text-align: left;
    }

    .zeosync-ai-support__chip:hover {
        background: #DBEAFE;
        border-color: #93C5FD;
        transform: translateY(-1px);
    }

    /* Typing Indicator */
    .zeosync-ai-support__typing-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 16px 10px 16px;
        background: #F8FAFC;
    }

    .zeosync-ai-support__typing-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #1E293B;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .zeosync-ai-support__typing-bubble {
        display: flex;
        align-items: center;
        gap: 2px;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        padding: 6px 12px;
        border-radius: 12px;
        border-top-left-radius: 4px;
        font-size: 12px;
        color: #64748B;
        font-style: italic;
    }

    .zeosync-ai-support__typing-dots span {
        display: inline-block;
        font-weight: bold;
        animation: zeosyncDotBounce 1.4s infinite both;
    }

    .zeosync-ai-support__typing-dots span:nth-child(1) { animation-delay: 0.0s; }
    .zeosync-ai-support__typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .zeosync-ai-support__typing-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes zeosyncDotBounce {
        0%, 80%, 100% { opacity: 0.3; transform: scale(0.9); }
        40% { opacity: 1; transform: scale(1.3); }
    }

    /* Composer Area */
    .zeosync-ai-support__composer {
        background: #FFFFFF;
        border-top: 1px solid #E2E8F0;
        padding: 10px 14px;
    }

    .zeosync-ai-support__form {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        background: #F8FAFC;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        padding: 6px 8px 6px 12px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .zeosync-ai-support__form:focus-within {
        border-color: #2563EB;
        background: #FFFFFF;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .zeosync-ai-support__textarea {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        resize: none;
        font-size: 13px;
        color: #1E293B;
        font-family: inherit;
        line-height: 1.4;
        max-height: 100px;
        min-height: 22px;
        padding: 2px 0;
    }

    .zeosync-ai-support__textarea::placeholder {
        color: #94A3B8;
    }

    .zeosync-ai-support__send-btn {
        background: #2563EB;
        border: none;
        color: #FFFFFF;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: background-color 0.15s ease, transform 0.1s ease, opacity 0.15s ease;
    }

    .zeosync-ai-support__send-btn:hover:not(:disabled) {
        background: #1D4ED8;
        transform: translateY(-1px);
    }

    .zeosync-ai-support__send-btn:disabled {
        background: #CBD5E1;
        color: #94A3B8;
        cursor: not-allowed;
    }

    .zeosync-ai-support__footer-note {
        font-size: 10.5px;
        color: #94A3B8;
        text-align: center;
        margin-top: 6px;
    }

    /* Accessibility: Reduced Motion */
    @media (prefers-reduced-motion: reduce) {
        .zeosync-ai-support__robot-btn,
        .zeosync-ai-support__antenna-glow,
        .zeosync-ai-support__eye-left,
        .zeosync-ai-support__eye-right,
        .zeosync-ai-support__chest-glow,
        .zeosync-ai-support__speech-bubble,
        .zeosync-ai-support__panel,
        .zeosync-ai-support__msg,
        .zeosync-ai-support__typing-dots span {
            animation: none !important;
            transition: none !important;
        }
    }

    /* Mobile Responsiveness */
    @media (max-width: 480px) {
        .zeosync-ai-support {
            bottom: 16px;
            right: 16px;
        }

        .zeosync-ai-support__panel {
            bottom: 8px;
            right: 8px;
            left: 8px;
            width: auto;
            height: calc(100vh - 16px);
            max-height: none;
            border-radius: 12px;
        }

        .zeosync-ai-support__speech-bubble {
            right: 70px;
            max-width: 220px;
            white-space: normal;
        }
    }
</style>

<!-- ======================================================== -->
<!-- SCRIPT (Strictly Scoped IIFE & CSP Compliant)            -->
<!-- ======================================================== -->
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('zeosync-ai-support');
        if (!root) return;

        // Elements
        const launcherWrapper = document.getElementById('zeosyncAiLauncherWrapper');
        const robotBtn = document.getElementById('zeosyncAiRobotBtn');
        const speechBubble = document.getElementById('zeosyncAiSpeechBubble');
        const speechText = document.getElementById('zeosyncAiSpeechText');
        const speechClose = document.getElementById('zeosyncAiSpeechClose');
        const chatPanel = document.getElementById('zeosyncAiChatPanel');
        const closeBtn = document.getElementById('zeosyncAiCloseBtn');
        const menuBtn = document.getElementById('zeosyncAiMenuBtn');
        const dropdownMenu = document.getElementById('zeosyncAiDropdownMenu');
        const newChatBtn = document.getElementById('zeosyncAiActionNewChat');
        const messagesContainer = document.getElementById('zeosyncAiMessages');
        const quickActions = document.getElementById('zeosyncAiQuickActions');
        const typingIndicator = document.getElementById('zeosyncAiTyping');
        const form = document.getElementById('zeosyncAiForm');
        const textarea = document.getElementById('zeosyncAiInput');
        const sendBtn = document.getElementById('zeosyncAiSendBtn');

        // State
        const askUrl = @json($askUrl);
        let isOpen = false;
        let isSubmitting = false;
        let lastFailedPrompt = null;

        // Rotating Speech Messages
        const speechMessages = [
            "Have any query? Let's chat!",
            "Need help with ZeoSync?",
            "Need help with Shopify?",
            "Need help with Amazon?",
            "Got a question? Ask me!",
            "Need help? I'm here."
        ];
        let speechIndex = 0;
        let speechInterval = null;
        let isSpeechDismissed = false;

        const startSpeechRotation = () => {
            if (speechInterval || isSpeechDismissed) return;
            speechInterval = setInterval(() => {
                if (isOpen || isSpeechDismissed || !speechText) return;
                speechIndex = (speechIndex + 1) % speechMessages.length;
                speechText.style.opacity = '0';
                setTimeout(() => {
                    if (speechText) {
                        speechText.textContent = speechMessages[speechIndex];
                        speechText.style.opacity = '1';
                    }
                }, 200);
            }, 6500);
        };

        const stopSpeechRotation = () => {
            if (speechInterval) {
                clearInterval(speechInterval);
                speechInterval = null;
            }
        };

        startSpeechRotation();

        // Speech Close
        if (speechClose) {
            speechClose.addEventListener('click', (e) => {
                e.stopPropagation();
                isSpeechDismissed = true;
                stopSpeechRotation();
                if (speechBubble) speechBubble.style.display = 'none';
            });
        }

        // Open / Close Panel Handlers
        const openChat = () => {
            isOpen = true;
            stopSpeechRotation();
            if (speechBubble) speechBubble.style.display = 'none';
            if (launcherWrapper) launcherWrapper.style.display = 'none';
            if (chatPanel) {
                chatPanel.style.display = 'flex';
                if (robotBtn) robotBtn.setAttribute('aria-expanded', 'true');
            }
            if (textarea) {
                setTimeout(() => textarea.focus(), 150);
            }
            scrollToBottom();
        };

        const closeChat = () => {
            isOpen = false;
            if (chatPanel) {
                chatPanel.style.display = 'none';
                if (robotBtn) robotBtn.setAttribute('aria-expanded', 'false');
            }
            if (launcherWrapper) launcherWrapper.style.display = 'flex';
            if (!isSpeechDismissed && speechBubble) {
                speechBubble.style.display = 'flex';
                startSpeechRotation();
            }
            if (dropdownMenu) dropdownMenu.style.display = 'none';
        };

        if (robotBtn) robotBtn.addEventListener('click', openChat);
        if (speechBubble) speechBubble.addEventListener('click', openChat);
        if (closeBtn) closeBtn.addEventListener('click', closeChat);

        // Escape Key closes panel
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen) {
                closeChat();
            }
        });

        // 3-Dots Menu Toggle
        if (menuBtn && dropdownMenu) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isMenuOpen = dropdownMenu.style.display === 'block';
                dropdownMenu.style.display = isMenuOpen ? 'none' : 'block';
                menuBtn.setAttribute('aria-expanded', isMenuOpen ? 'false' : 'true');
            });

            document.addEventListener('click', (e) => {
                if (!menuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                    menuBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // New Conversation (Reset)
        if (newChatBtn) {
            newChatBtn.addEventListener('click', () => {
                if (dropdownMenu) dropdownMenu.style.display = 'none';
                if (messagesContainer) {
                    // Reset to initial greeting
                    messagesContainer.innerHTML = `
                        <div class="zeosync-ai-support__msg zeosync-ai-support__msg--assistant">
                            <div class="zeosync-ai-support__msg-avatar">
                                <svg viewBox="0 0 72 72" fill="none" width="20" height="20">
                                    <circle cx="36" cy="7" r="4" fill="#38BDF8"/>
                                    <rect x="14" y="16" width="44" height="34" rx="12" fill="#1E293B"/>
                                    <rect x="19" y="21" width="34" height="23" rx="7" fill="#0F172A"/>
                                    <circle cx="28" cy="31" r="3.5" fill="#38BDF8"/>
                                    <circle cx="44" cy="31" r="3.5" fill="#38BDF8"/>
                                </svg>
                            </div>
                            <div class="zeosync-ai-support__msg-bubble">
                                <p class="zeosync-ai-support__msg-text">
                                    Hi! I'm ZeoSync's AI assistant. I can help you with Shopify, Amazon, products, inventory, orders, billing, and other ZeoSync questions.
                                </p>
                                <p class="zeosync-ai-support__msg-text zeosync-ai-support__msg-text--sub">
                                    How can I help you today?
                                </p>
                            </div>
                        </div>
                        <div class="zeosync-ai-support__quick-actions" id="zeosyncAiQuickActions">
                            <button type="button" class="zeosync-ai-support__chip" data-prompt="How do I manage my Shopify products and sync them?">
                                📦 Shopify Help
                            </button>
                            <button type="button" class="zeosync-ai-support__chip" data-prompt="How do I connect and manage Amazon in ZeoSync?">
                                🛒 Amazon Help
                            </button>
                            <button type="button" class="zeosync-ai-support__chip" data-prompt="How does inventory synchronization work between Shopify and Amazon?">
                                📊 Inventory Sync
                            </button>
                            <button type="button" class="zeosync-ai-support__chip" data-prompt="How are orders tracked and synced?">
                                🚚 Orders
                            </button>
                            <button type="button" class="zeosync-ai-support__chip" data-prompt="How do ZeoSync billing and plans work?">
                                💳 Plans & Billing
                            </button>
                        </div>
                    `;
                    bindChipListeners();
                    scrollToBottom();
                }
            });
        }

        // Textarea Auto-Resize & Send button toggle
        if (textarea) {
            textarea.addEventListener('input', () => {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
                if (sendBtn) {
                    sendBtn.disabled = !textarea.value.trim() || isSubmitting;
                }
            });

            textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (!sendBtn.disabled) {
                        sendMessage(textarea.value);
                    }
                }
            });
        }

        // Quick action chips listener binder
        const bindChipListeners = () => {
            const chips = document.querySelectorAll('#zeosync-ai-support .zeosync-ai-support__chip');
            chips.forEach(chip => {
                chip.addEventListener('click', function() {
                    const prompt = this.getAttribute('data-prompt');
                    if (prompt) {
                        sendMessage(prompt);
                    }
                });
            });
        };
        bindChipListeners();

        // Form Submit
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (textarea && textarea.value.trim() && !isSubmitting) {
                    sendMessage(textarea.value);
                }
            });
        }

        // Scroll to bottom helper
        const scrollToBottom = () => {
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        };

        // Escape HTML for XSS safety
        const escapeHtml = (str) => {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        };

        // Light Markdown Parser (safe output)
        const formatMarkdown = (text) => {
            let html = escapeHtml(text);

            // Bold **text** or __text__
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');

            // Inline code `code`
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

            // Links [text](url) -> safe target blank
            html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

            // Convert bullet lines
            const lines = html.split('\n');
            let inList = false;
            const processed = [];

            lines.forEach(line => {
                const trimmed = line.trim();
                if (trimmed.startsWith('&bull; ') || trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
                    if (!inList) {
                        inList = true;
                        processed.push('<ul>');
                    }
                    processed.push(`<li>${trimmed.replace(/^(&bull;|-|\*)\s+/, '')}</li>`);
                } else {
                    if (inList) {
                        inList = false;
                        processed.push('</ul>');
                    }
                    if (trimmed.length > 0) {
                        processed.push(`<p class="zeosync-ai-support__msg-text">${line}</p>`);
                    }
                }
            });

            if (inList) processed.push('</ul>');

            return processed.join('');
        };

        // Append Message Helper
        const appendMessage = (role, content, isError = false) => {
            if (!messagesContainer) return;

            const msgDiv = document.createElement('div');
            msgDiv.className = `zeosync-ai-support__msg zeosync-ai-support__msg--${role} ${isError ? 'zeosync-ai-support__msg--error' : ''}`;

            if (role === 'assistant') {
                const avatar = document.createElement('div');
                avatar.className = 'zeosync-ai-support__msg-avatar';
                avatar.innerHTML = `
                    <svg viewBox="0 0 72 72" fill="none" width="20" height="20">
                        <circle cx="36" cy="7" r="4" fill="#38BDF8"/>
                        <rect x="14" y="16" width="44" height="34" rx="12" fill="#1E293B"/>
                        <rect x="19" y="21" width="34" height="23" rx="7" fill="#0F172A"/>
                        <circle cx="28" cy="31" r="3.5" fill="#38BDF8"/>
                        <circle cx="44" cy="31" r="3.5" fill="#38BDF8"/>
                    </svg>
                `;
                msgDiv.appendChild(avatar);
            }

            const bubble = document.createElement('div');
            bubble.className = 'zeosync-ai-support__msg-bubble';

            if (role === 'user') {
                bubble.textContent = content;
            } else if (isError) {
                bubble.innerHTML = `
                    <p class="zeosync-ai-support__msg-text">${escapeHtml(content)}</p>
                    <button type="button" class="zeosync-ai-support__retry-btn" id="zeosyncAiRetryBtn">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                            <path d="M21 3v5h-5"/>
                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                            <path d="M8 16H3v5"/>
                        </svg>
                        <span>Try Again</span>
                    </button>
                `;
            } else {
                bubble.innerHTML = formatMarkdown(content);
            }

            msgDiv.appendChild(bubble);
            messagesContainer.appendChild(msgDiv);

            // Hide quick actions if any
            const qa = document.getElementById('zeosyncAiQuickActions');
            if (qa) qa.style.display = 'none';

            // Bind retry button if present
            if (isError) {
                const retryBtn = bubble.querySelector('#zeosyncAiRetryBtn');
                if (retryBtn) {
                    retryBtn.addEventListener('click', () => {
                        if (lastFailedPrompt) {
                            sendMessage(lastFailedPrompt);
                        }
                    });
                }
            }

            scrollToBottom();
        };

        // Main Send Message Function
        const sendMessage = async (rawPrompt) => {
            const prompt = (rawPrompt || '').trim();
            if (!prompt || isSubmitting) return;

            isSubmitting = true;
            lastFailedPrompt = prompt;

            // Clear input & reset height
            if (textarea) {
                textarea.value = '';
                textarea.style.height = 'auto';
            }
            if (sendBtn) sendBtn.disabled = true;

            // Display user message in chat
            appendMessage('user', prompt);

            // Show typing indicator
            if (typingIndicator) typingIndicator.style.display = 'flex';
            scrollToBottom();

            // Prepare CSRF Token
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '{{ csrf_token() }}';

            try {
                const response = await fetch(askUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ prompt: prompt })
                });

                if (typingIndicator) typingIndicator.style.display = 'none';

                if (!response.ok) {
                    let errData = {};
                    try {
                        errData = await response.json();
                    } catch (e) {}

                    const errorMsg = errData.error || errData.message || 'Sorry, I couldn\'t process that request right now.';
                    appendMessage('assistant', errorMsg, true);
                    return;
                }

                const data = await response.json();

                if (data.success && data.message) {
                    lastFailedPrompt = null;
                    appendMessage('assistant', data.message);
                } else {
                    const fallbackMsg = data.error || 'Sorry, I couldn\'t process that request right now.';
                    appendMessage('assistant', fallbackMsg, true);
                }
            } catch (err) {
                console.error('ZeoSync AI support fetch error:', err);
                if (typingIndicator) typingIndicator.style.display = 'none';
                appendMessage('assistant', 'Sorry, I couldn\'t process that request right now. Please check your network connection and try again.', true);
            } finally {
                isSubmitting = false;
                if (textarea) {
                    textarea.focus();
                    if (sendBtn) sendBtn.disabled = !textarea.value.trim();
                }
            }
        };

    });
})();
</script>
