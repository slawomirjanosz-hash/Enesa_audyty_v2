@extends('layouts.client')

@section('title', 'Chat')
@section('page-title', 'Chat')

@push('styles')
<style>
.chat-layout {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

/* Left column – archives */
.chat-sidebar {
    width: 300px;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid #E5E1D8;
    border-radius: 12px;
    overflow: hidden;
}
.chat-sidebar-header {
    background: var(--green);
    color: #F5F0E8;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 700;
}
.archive-item {
    padding: 12px 16px;
    border-bottom: 1px solid #F0EDE6;
    cursor: default;
    transition: background .12s;
}
.archive-item:hover { background: #FAFAF6; }
.archive-item-dates {
    font-size: 12px;
    color: #555;
    font-family: 'Lato', sans-serif;
    margin-bottom: 3px;
}
.archive-item-count {
    font-size: 11px;
    color: #999;
    font-family: 'Lato', sans-serif;
}
.archive-empty {
    padding: 24px 16px;
    text-align: center;
    color: #bbb;
    font-size: 13px;
    font-family: 'Manrope', sans-serif;
}

/* Right column – active chat */
.chat-main {
    flex: 1;
    min-width: 0;
    background: #fff;
    border: 1px solid #E5E1D8;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.chat-main-header {
    background: var(--green);
    color: #F5F0E8;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.chat-header-title {
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.status-dot-online  { background: #4ADE80; }
.status-dot-offline { background: #9CA3AF; }
.status-label {
    font-size: 12px;
    font-weight: 600;
    font-family: 'Manrope', sans-serif;
}
.auditors-list {
    font-size: 11px;
    color: #C8DDD4;
    font-family: 'Lato', sans-serif;
    margin-top: 2px;
}

/* Messages window */
#messages-window {
    padding: 18px;
    overflow-y: auto;
    height: 400px;
    min-height: 200px;
    max-height: 80vh;
    resize: vertical;
    background: #FAFAF6;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Bubbles */
.msg-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}
.msg-row.own  { flex-direction: row-reverse; }
.msg-row.other { flex-direction: row; }

.msg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.msg-avatar.own   { background: var(--green); color: #F5F0E8; }
.msg-avatar.other { background: #E5E1D8; color: #555; }

.msg-bubble {
    max-width: 68%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 13px;
    font-family: 'Lato', sans-serif;
    line-height: 1.5;
    word-break: break-word;
    position: relative;
}
.msg-bubble.own   { background: var(--green); color: #fff; border-bottom-right-radius: 4px; }
.msg-bubble.other { background: #F4F1EA; color: #1A1A1A; border-bottom-left-radius: 4px; }

.msg-time {
    font-size: 10px;
    margin-top: 4px;
    text-align: right;
    opacity: .65;
}
.msg-bubble.other .msg-time { text-align: left; }

/* Input area */
.chat-input-area {
    padding: 14px 18px;
    border-top: 1px solid #E5E1D8;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fff;
}
.chat-input-row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
#message-input {
    flex: 1;
    background: #FAFAF6;
    border: 1px solid #D0CCC0;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
    font-family: 'Lato', sans-serif;
    color: #1A1A1A;
    outline: none;
    resize: none;
    min-height: 42px;
    max-height: 120px;
    transition: border-color .15s;
}
#message-input:focus { border-color: var(--green); background: #fff; }
.btn-send {
    background: var(--green);
    color: #F5F0E8;
    border: none;
    border-radius: 8px;
    padding: 10px 16px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: background .15s;
}
.btn-send:hover { background: #143d2d; }
.btn-end {
    background: #FEF2F2;
    color: #B91C1C;
    border: 1px solid #FECACA;
    border-radius: 8px;
    padding: 8px 14px;
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background .15s;
    align-self: flex-start;
}
.btn-end:hover { background: #FEE2E2; }

.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #bbb;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    gap: 8px;
    padding: 32px;
}

@media (max-width: 768px) {
    .chat-layout { flex-direction: column; }
    .chat-sidebar { width: 100%; }
}
</style>
@endpush

@section('content')

<div class="chat-layout">

    {{-- LEFT: Archives --}}
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <i class="ti ti-archive"></i>
            Archiwum rozmów
        </div>

        @if($archives->isEmpty())
            <div class="archive-empty">
                <i class="ti ti-history-off" style="font-size:28px;display:block;margin-bottom:8px;color:#ddd;"></i>
                Brak archiwalnych rozmów
            </div>
        @else
            @foreach($archives as $arc)
            <div class="archive-item">
                <div class="archive-item-dates">
                    <i class="ti ti-calendar-event" style="font-size:11px;"></i>
                    {{ \Carbon\Carbon::parse($arc->started_at)->format('d.m.Y H:i') }}
                    &rarr; {{ \Carbon\Carbon::parse($arc->ended_at)->format('d.m.Y H:i') }}
                </div>
                <div class="archive-item-count">
                    <i class="ti ti-messages" style="font-size:11px;"></i>
                    {{ $arc->message_count }} {{ $arc->message_count == 1 ? 'wiadomość' : ($arc->message_count < 5 ? 'wiadomości' : 'wiadomości') }}
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- RIGHT: Active chat --}}
    <div class="chat-main">

        {{-- Header with online status --}}
        <div class="chat-main-header">
            <div>
                <div class="chat-header-title">
                    <i class="ti ti-message-2"></i>
                    Rozmowa z ENESA
                </div>
                @if($onlineUsers->isNotEmpty())
                    <div class="auditors-list">{{ $onlineUsers->pluck('name')->join(', ') }}</div>
                @endif
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                @if($onlineUsers->count() > 0)
                    <span class="status-dot status-dot-online"></span>
                    <span class="status-label" id="status-label">Dostępny</span>
                @else
                    <span class="status-dot status-dot-offline" id="status-dot"></span>
                    <span class="status-label" id="status-label">Niedostępny</span>
                @endif
            </div>
        </div>

        {{-- Messages window --}}
        <div id="messages-window">
            @if($messages->isEmpty())
                <div class="chat-empty" id="empty-state">
                    <i class="ti ti-message-off" style="font-size:36px;"></i>
                    <p>Brak wiadomości. Napisz coś!</p>
                </div>
            @else
                @foreach($messages as $msg)
                @php
                    $isOwn = auth()->id() == $msg->user_id;
                    $initials = strtoupper(substr($msg->sender->name ?? 'U', 0, 1));
                    if (($pos = strpos($msg->sender->name ?? '', ' ')) !== false) {
                        $initials = strtoupper(substr($msg->sender->name, 0, 1) . substr($msg->sender->name, $pos + 1, 1));
                    }
                @endphp
                <div class="msg-row {{ $isOwn ? 'own' : 'other' }}" data-id="{{ $msg->id }}">
                    <div class="msg-avatar {{ $isOwn ? 'own' : 'other' }}">{{ $initials }}</div>
                    <div class="msg-bubble {{ $isOwn ? 'own' : 'other' }}">
                        {{ $msg->body }}
                        <div class="msg-time">{{ $msg->created_at->format('H:i') }}</div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        {{-- Input area --}}
        <div class="chat-input-area">
            <div class="chat-input-row">
                <textarea id="message-input" placeholder="Napisz wiadomość..." rows="1"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                <button class="btn-send" onclick="sendMessage()">
                    <i class="ti ti-send"></i> Wyślij
                </button>
            </div>
            @if($messages->isNotEmpty())
            <button class="btn-end" onclick="endConversation()">
                <i class="ti ti-circle-x"></i> Zakończ rozmowę
            </button>
            @endif
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
const SEND_URL  = '{{ route('client.chat.send') }}';
const POLL_URL  = '{{ route('client.chat.poll') }}';
const END_URL   = '{{ route('client.chat.end') }}';
const CSRF      = '{{ csrf_token() }}';
const MY_ID     = {{ auth()->id() }};

let lastId = {{ $messages->last()?->id ?? 0 }};

function initials(name) {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
}

function buildBubble(msg) {
    const isOwn = msg.is_own;
    const cls = isOwn ? 'own' : 'other';
    return `
        <div class="msg-row ${cls}" data-id="${msg.id}">
            <div class="msg-avatar ${cls}">${initials(msg.sender_name)}</div>
            <div class="msg-bubble ${cls}">
                ${escHtml(msg.body)}
                <div class="msg-time">${msg.created_at}</div>
            </div>
        </div>`;
}

function escHtml(text) {
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}

function scrollBottom() {
    const win = document.getElementById('messages-window');
    win.scrollTop = win.scrollHeight;
}

function removeEmptyState() {
    const empty = document.getElementById('empty-state');
    if (empty) empty.remove();

    const endBtn = document.querySelector('.btn-end');
    if (!endBtn) {
        const area = document.querySelector('.chat-input-area');
        area.insertAdjacentHTML('beforeend',
            `<button class="btn-end" onclick="endConversation()"><i class="ti ti-circle-x"></i> Zakończ rozmowę</button>`);
    }
}

async function sendMessage() {
    const input = document.getElementById('message-input');
    const body  = input.value.trim();
    if (!body) return;

    input.value = '';
    input.style.height = 'auto';

    try {
        const res = await fetch(SEND_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ body }),
        });

        if (!res.ok) return;
        const msg = await res.json();
        msg.is_own = true;
        lastId = msg.id;

        removeEmptyState();
        document.getElementById('messages-window').insertAdjacentHTML('beforeend', buildBubble(msg));
        scrollBottom();
    } catch (e) {
        console.error('sendMessage error', e);
    }
}

async function pollMessages() {
    try {
        const res = await fetch(`${POLL_URL}?last_id=${lastId}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        if (!res.ok) return;
        const data = await res.json();

        if (data.messages && data.messages.length > 0) {
            removeEmptyState();
            const win = document.getElementById('messages-window');
            data.messages.forEach(msg => {
                if (!document.querySelector(`[data-id="${msg.id}"]`)) {
                    win.insertAdjacentHTML('beforeend', buildBubble(msg));
                    lastId = Math.max(lastId, msg.id);
                }
            });
            scrollBottom();
        }

        if (data.onlineUsers !== undefined) {
            const isOnline = data.onlineUsers.length > 0;
            const dot   = document.getElementById('status-dot');
            const label = document.getElementById('status-label');
            if (dot) {
                dot.className = 'status-dot ' + (isOnline ? 'status-dot-online' : 'status-dot-offline');
            }
            if (label) {
                label.textContent = isOnline ? 'Dostępny' : 'Niedostępny';
            }
        }
    } catch (e) {
        console.error('poll error', e);
    }
}

async function endConversation() {
    if (!confirm('Czy na pewno chcesz zakończyć rozmowę? Zostanie ona przeniesiona do archiwum.')) return;

    try {
        const res = await fetch(END_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
        });
        if (res.ok) {
            window.location.reload();
        }
    } catch (e) {
        console.error('endConversation error', e);
    }
}

// Auto-resize textarea
document.getElementById('message-input').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Scroll to bottom on load
scrollBottom();

// Start polling
setInterval(pollMessages, 5000);
</script>
@endpush

