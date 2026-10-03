@extends('admin.layout.app')

@section('title', 'AI & Support Chat')

@push('css')
<style>
    .chat-layout {
        display: flex;
        gap: 16px;
        height: calc(100vh - 120px);
        min-height: 550px;
    }

    /* Left panel: Shop conversations */
    .chat-sidebar {
        width: 340px;
        flex-shrink: 0;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .chat-sidebar__header {
        padding: 16px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .chat-sidebar__search {
        position: relative;
    }

    .chat-sidebar__search input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font-size: 13px;
    }

    .chat-sidebar__search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
    }

    .chat-sidebar__list {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }

    .chat-shop-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 12px;
        text-decoration: none;
        color: #1e293b;
        transition: all 0.15s ease;
        margin-bottom: 4px;
        border: 1px solid transparent;
    }

    .chat-shop-item:hover {
        background: #f1f5f9;
    }

    .chat-shop-item.active {
        background: #eff6ff;
        border-color: #bfdbfe;
    }

    .chat-shop-item__avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #e2e8f0;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .chat-shop-item.active .chat-shop-item__avatar {
        background: #3b82f6;
        color: #ffffff;
    }

    .chat-shop-item__info {
        flex: 1;
        min-width: 0;
    }

    .chat-shop-item__name {
        font-weight: 600;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-shop-item__domain {
        font-size: 12px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-shop-item__preview {
        font-size: 11px;
        color: #94a3b8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 2px;
    }

    .chat-shop-item__meta {
        text-align: right;
        flex-shrink: 0;
    }

    .chat-shop-item__time {
        font-size: 10px;
        color: #94a3b8;
    }

    .chat-shop-item__badge {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        margin-top: 4px;
    }

    /* Right panel: Active conversation */
    .chat-main {
        flex: 1;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .chat-main__header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8fafc;
    }

    .chat-main__title {
        font-weight: 700;
        font-size: 16px;
        color: #0f172a;
        margin: 0;
    }

    .chat-main__subtitle {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: #f8fafc;
    }

    .chat-msg {
        display: flex;
        gap: 10px;
        max-width: 75%;
    }

    .chat-msg--user {
        align-self: flex-start;
    }

    .chat-msg--assistant {
        align-self: flex-start;
    }

    .chat-msg--admin {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .chat-msg__avatar {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .chat-msg--user .chat-msg__avatar {
        background: #e2e8f0;
        color: #334155;
    }

    .chat-msg--assistant .chat-msg__avatar {
        background: #0f172a;
        color: #38bdf8;
    }

    .chat-msg--admin .chat-msg__avatar {
        background: #10b981;
        color: #ffffff;
    }

    .chat-msg__content {
        display: flex;
        flex-direction: column;
    }

    .chat-msg__header {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: #64748b;
        margin-bottom: 4px;
    }

    .chat-msg--admin .chat-msg__header {
        justify-content: flex-end;
    }

    .chat-msg__bubble {
        padding: 12px 16px;
        border-radius: 14px;
        font-size: 13.5px;
        line-height: 1.45;
        word-break: break-word;
    }

    .chat-msg--user .chat-msg__bubble {
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-top-left-radius: 4px;
    }

    .chat-msg--assistant .chat-msg__bubble {
        background: #1e293b;
        color: #f8fafc;
        border-top-left-radius: 4px;
    }

    .chat-msg--assistant .chat-msg__bubble p {
        margin: 0 0 6px;
    }

    .chat-msg--assistant .chat-msg__bubble p:last-child {
        margin: 0;
    }

    .chat-msg--admin .chat-msg__bubble {
        background: #2563eb;
        color: #ffffff;
        border-top-right-radius: 4px;
    }

    .chat-composer {
        padding: 16px 20px;
        border-top: 1px solid #e2e8f0;
        background: #ffffff;
    }

    .chat-composer__form {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .chat-composer textarea {
        flex: 1;
        resize: none;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        padding: 10px 14px;
        font-size: 13.5px;
        max-height: 120px;
        min-height: 42px;
    }

    .chat-composer textarea:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
    }

    .chat-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
        padding: 40px;
        text-align: center;
    }

    .chat-empty i {
        font-size: 48px;
        margin-bottom: 12px;
        color: #cbd5e1;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">
    <div class="chat-layout">
        
        <!-- 1. LEFT SIDEBAR: Shops & Conversations List -->
        <aside class="chat-sidebar">
            <div class="chat-sidebar__header">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="m-0 fw-bold"><i class="bi bi-chat-dots me-1 text-primary"></i> Support Chats</h6>
                    <span class="badge bg-primary rounded-pill">{{ $shopsWithMessages->count() }}</span>
                </div>
                <div class="chat-sidebar__search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="shopSearchInput" placeholder="Filter by store or domain...">
                </div>
            </div>

            <div class="chat-sidebar__list" id="shopListContainer">
                @forelse($shopsWithMessages as $s)
                    @php
                        $latestMsg = $s->aiChatMessages->first();
                        $isSelected = $selectedShop && $selectedShop->id === $s->id;
                    @endphp
                    <a href="{{ route('admin.aichats.index', ['shop_id' => $s->id]) }}"
                       class="chat-shop-item {{ $isSelected ? 'active' : '' }}"
                       data-shop-name="{{ strtolower($s->shop_name ?? '') }}"
                       data-shop-domain="{{ strtolower($s->shop) }}">
                        <div class="chat-shop-item__avatar">
                            {{ strtoupper(substr($s->shop_name ?: $s->shop, 0, 2)) }}
                        </div>
                        <div class="chat-shop-item__info">
                            <div class="chat-shop-item__name">{{ $s->shop_name ?: $s->shop }}</div>
                            <div class="chat-shop-item__domain">{{ $s->shop }}</div>
                            @if($latestMsg)
                                <div class="chat-shop-item__preview">
                                    <strong class="text-capitalize">{{ $latestMsg->role }}:</strong> {{ \Illuminate\Support\Str::limit($latestMsg->message, 30) }}
                                </div>
                            @endif
                        </div>
                        <div class="chat-shop-item__meta">
                            @if($latestMsg && $latestMsg->created_at)
                                <div class="chat-shop-item__time">{{ $latestMsg->created_at->diffForHumans(null, true, true) }}</div>
                            @endif
                            <span class="badge {{ (int)$s->is_active === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} chat-shop-item__badge">
                                {{ (int)$s->is_active === 1 ? 'Active' : 'Offline' }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-chat-left text-secondary fs-3 d-block mb-2"></i>
                        No conversations yet
                    </div>
                @endforelse

                @if($allShops->whereNotIn('id', $shopsWithMessages->pluck('id'))->isNotEmpty())
                    <div class="px-2 pt-3 pb-1 text-muted text-uppercase fw-bold" style="font-size: 11px;">
                        Other Stores
                    </div>
                    @foreach($allShops->whereNotIn('id', $shopsWithMessages->pluck('id')) as $otherShop)
                        @php
                            $isSelected = $selectedShop && $selectedShop->id === $otherShop->id;
                        @endphp
                        <a href="{{ route('admin.aichats.index', ['shop_id' => $otherShop->id]) }}"
                           class="chat-shop-item {{ $isSelected ? 'active' : '' }}"
                           data-shop-name="{{ strtolower($otherShop->shop_name ?? '') }}"
                           data-shop-domain="{{ strtolower($otherShop->shop) }}">
                            <div class="chat-shop-item__avatar" style="background:#f1f5f9; color:#94a3b8;">
                                {{ strtoupper(substr($otherShop->shop_name ?: $otherShop->shop, 0, 2)) }}
                            </div>
                            <div class="chat-shop-item__info">
                                <div class="chat-shop-item__name">{{ $otherShop->shop_name ?: $otherShop->shop }}</div>
                                <div class="chat-shop-item__domain">{{ $otherShop->shop }}</div>
                            </div>
                        </a>
                    @endforeach
                @endif
            </div>
        </aside>

        <!-- 2. RIGHT MAIN: Conversation Messages & Admin Reply -->
        <main class="chat-main">
            @if($selectedShop)
                <!-- Header -->
                <div class="chat-main__header">
                    <div>
                        <h5 class="chat-main__title d-flex align-items-center gap-2">
                            {{ $selectedShop->shop_name ?: $selectedShop->shop }}
                            <span class="badge {{ (int)$selectedShop->is_active === 1 ? 'bg-success' : 'bg-secondary' }}" style="font-size: 11px;">
                                {{ (int)$selectedShop->is_active === 1 ? 'Connected' : 'Uninstalled' }}
                            </span>
                        </h5>
                        <div class="chat-main__subtitle">
                            <span class="me-2"><i class="bi bi-shop me-1"></i>{{ $selectedShop->shop }}</span>
                            @if($selectedShop->email)
                                <span><i class="bi bi-envelope me-1"></i>{{ $selectedShop->email }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form action="{{ route('admin.aichats.clear', $selectedShop->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to clear this shop\'s entire chat history?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Clear chat history">
                                <i class="bi bi-trash3 me-1"></i> Clear Chat
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="chat-messages" id="chatMessagesContainer">
                    @forelse($messages as $msg)
                        <div class="chat-msg chat-msg--{{ $msg->role }}" data-msg-id="{{ $msg->id }}">
                            <div class="chat-msg__avatar">
                                @if($msg->role === 'admin')
                                    <i class="bi bi-person-check-fill"></i>
                                @elseif($msg->role === 'assistant')
                                    <i class="bi bi-robot"></i>
                                @else
                                    <i class="bi bi-person-fill"></i>
                                @endif
                            </div>
                            <div class="chat-msg__content">
                                <div class="chat-msg__header">
                                    <span class="fw-bold">
                                        @if($msg->role === 'admin')
                                            Admin Support
                                        @elseif($msg->role === 'assistant')
                                            ZeoSync AI
                                        @else
                                            Merchant ({{ $selectedShop->shop_name ?: 'Store' }})
                                        @endif
                                    </span>
                                    <span>&bull;</span>
                                    <span>{{ $msg->created_at?->format('M d, h:i A') }}</span>
                                </div>
                                <div class="chat-msg__bubble">
                                    {!! nl2br(e($msg->message)) !!}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="chat-empty" id="chatEmptyState">
                            <i class="bi bi-chat-heart"></i>
                            <div class="fw-bold mb-1">No messages in this conversation yet</div>
                            <small class="text-muted">Send a message below to reach out to this merchant.</small>
                        </div>
                    @endforelse
                </div>

                <!-- Composer Form -->
                <div class="chat-composer">
                    <form id="adminChatForm" action="{{ route('admin.aichats.send', $selectedShop->id) }}" method="POST" class="chat-composer__form">
                        @csrf
                        <textarea
                            name="message"
                            id="adminMessageInput"
                            placeholder="Type a reply to {{ $selectedShop->shop_name ?: $selectedShop->shop }} as Admin Support..."
                            rows="1"
                            required
                        ></textarea>
                        <button type="submit" class="btn btn-primary px-3 py-2" id="adminSendBtn">
                            <i class="bi bi-send-fill me-1"></i> Send Reply
                        </button>
                    </form>
                </div>
            @else
                <div class="chat-empty">
                    <i class="bi bi-shop"></i>
                    <div class="fw-bold mb-1">Select a store to view chat history</div>
                    <small class="text-muted">Pick a store from the sidebar to inspect conversations or send support replies.</small>
                </div>
            @endif
        </main>

    </div>
</div>
@endsection

@push('js')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    // 1. Search filter for shops sidebar
    const searchInput = document.getElementById('shopSearchInput');
    const shopList = document.getElementById('shopListContainer');

    if (searchInput && shopList) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = shopList.querySelectorAll('.chat-shop-item');

            items.forEach(item => {
                const name = item.dataset.shopName || '';
                const domain = item.dataset.shopDomain || '';
                if (name.includes(query) || domain.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // 2. Active conversation management & auto-scroll
    const messagesContainer = document.getElementById('chatMessagesContainer');
    const scrollToBottom = () => {
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    };
    scrollToBottom();

    // 3. Textarea auto-height & Enter to submit
    const textarea = document.getElementById('adminMessageInput');
    const adminForm = document.getElementById('adminChatForm');

    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() && adminForm) {
                    adminForm.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }
        });
    }

    // 4. AJAX send message
    @if($selectedShop)
    const selectedShopId = {{ $selectedShop->id }};
    const messagesPollUrl = "{{ route('admin.aichats.messages', $selectedShop->id) }}";
    let lastRenderedId = 0;

    // Track highest rendered ID
    document.querySelectorAll('.chat-msg[data-msg-id]').forEach(el => {
        const id = parseInt(el.getAttribute('data-msg-id'), 10);
        if (id > lastRenderedId) lastRenderedId = id;
    });

    const appendAdminMessageToUI = (msg) => {
        if (!messagesContainer) return;
        const emptyState = document.getElementById('chatEmptyState');
        if (emptyState) emptyState.remove();

        if (document.querySelector(`.chat-msg[data-msg-id="${msg.id}"]`)) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-msg chat-msg--${msg.role}`;
        msgDiv.setAttribute('data-msg-id', msg.id);

        let icon = 'bi-person-fill';
        let roleName = 'Merchant';
        if (msg.role === 'admin') {
            icon = 'bi-person-check-fill';
            roleName = 'Admin Support';
        } else if (msg.role === 'assistant') {
            icon = 'bi-robot';
            roleName = 'ZeoSync AI';
        }

        const div = document.createElement('div');
        div.textContent = msg.message;
        const safeText = div.innerHTML.replace(/\n/g, '<br>');

        msgDiv.innerHTML = `
            <div class="chat-msg__avatar">
                <i class="bi ${icon}"></i>
            </div>
            <div class="chat-msg__content">
                <div class="chat-msg__header">
                    <span class="fw-bold">${roleName}</span>
                    <span>&bull;</span>
                    <span>${msg.formatted_time || 'Just now'}</span>
                </div>
                <div class="chat-msg__bubble">
                    ${safeText}
                </div>
            </div>
        `;

        messagesContainer.appendChild(msgDiv);
        if (msg.id > lastRenderedId) lastRenderedId = msg.id;
        scrollToBottom();
    };

    if (adminForm) {
        adminForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const text = textarea ? textarea.value.trim() : '';
            if (!text) return;

            const sendBtn = document.getElementById('adminSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            const csrfToken = document.querySelector('input[name="_token"]')?.value || '{{ csrf_token() }}';

            try {
                const res = await fetch(adminForm.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ message: text })
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.message) {
                        appendAdminMessageToUI(data.message);
                        if (textarea) {
                            textarea.value = '';
                            textarea.style.height = 'auto';
                        }
                    }
                }
            } catch (err) {
                console.error('Admin send message error:', err);
            } finally {
                if (sendBtn) sendBtn.disabled = false;
                if (textarea) textarea.focus();
            }
        });
    }

    // 5. Polling for live updates (merchant replies or AI responses)
    setInterval(async () => {
        try {
            const url = `${messagesPollUrl}?after_id=${lastRenderedId}`;
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
                    data.messages.forEach(m => appendAdminMessageToUI(m));
                }
            }
        } catch (_) {}
    }, 4000);
    @endif
});
</script>
@endpush
