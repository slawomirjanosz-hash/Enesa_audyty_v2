@extends('layouts.app')

@section('title', 'Chat — ' . $company->name)
@section('page-title', 'Chat: ' . $company->name)

@push('styles')
<style>
.chat-layout { display: flex; gap: 20px; align-items: flex-start; }

/* ─── Left: archive sidebar ─── */
.cs-sidebar {
    width: 280px; flex-shrink: 0; background: #fff;
    border: 1px solid #E5E1D8; border-radius: 12px; overflow: hidden;
}
.cs-sidebar-header {
    background: var(--green); color: #F5F0E8; padding: 14px 16px;
    font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 700;
    display: flex; align-items: center; gap: 8px;
}
.cs-arc-item {
    padding: 12px 16px; border-bottom: 1px solid #F0EDE6;
    transition: background .12s;
}
.cs-arc-item:last-child { border-bottom: none; }
.cs-arc-dates { font-size: 12px; color: #555; font-family: 'Lato', sans-serif; margin-bottom: 3px; }
.cs-arc-count { font-size: 11px; color: #999; font-family: 'Lato', sans-serif; }
.cs-arc-empty { padding: 28px 16px; text-align: center; color: #bbb; font-size: 12px; font-family: 'Manrope', sans-serif; }

/* ─── Right: active chat ─── */
.cs-main {
    flex: 1; min-width: 0; background: #fff;
    border: 1px solid #E5E1D8; border-radius: 12px; overflow: hidden;
    display: flex; flex-direction: column;
}
.cs-header {
    background: var(--green); color: #F5F0E8; padding: 14px 18px;
    display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
}
.cs-header-left h2 {
    font-family: 'Manrope', sans-serif; font-size: 15px; font-weight: 700;
    color: #fff; margin: 0 0 3px;
}
.cs-header-left p { font-size: 12px; color: #C8DDD4; font-family: 'Lato', sans-serif; margin: 0; }
.cs-header-back {
    display: inline-flex; align-items: center; gap: 6px; color: #C8DDD4;
    font-size: 12px; font-family: 'Manrope', sans-serif; text-decoration: none;
    margin-right: 12px; transition: color .12s;
}
.cs-header-back:hover { color: #fff; }

#cs-messages {
    flex: 1; overflow-y: auto; padding: 16px; display: flex;
    flex-direction: column; gap: 10px; background: #FAFAF6;
    height: 480px; resize: vertical; min-height: 200px; max-height: 80vh;
}
.cs-msg-row { display: flex; align-items: flex-end; gap: 8px; }
.cs-msg-row.own   { flex-direction: row-reverse; }
.cs-msg-row.other { flex-direction: row; }
.cs-avatar {
    width: 32px; height: 32px; border-radius: 50%; font-size: 11px;
    font-weight: 700; font-family: 'Manrope', sans-serif;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cs-avatar.own   { background: var(--green); color: #fff; }
.cs-avatar.other { background: #E5E1D8; color: #555; }
.cs-bubble {
    max-width: 65%; padding: 10px 14px; border-radius: 14px;
    font-size: 13px; font-family: 'Lato', sans-serif; line-height: 1.5; word-break: break-word;
}
.cs-bubble.own   { background: var(--green); color: #fff; border-bottom-right-radius: 4px; }
.cs-bubble.other { background: #F4F1EA; color: #1A1A1A; border-bottom-left-radius: 4px; }
.cs-time { font-size: 10px; margin-top: 4px; opacity: .6; text-align: right; }
.cs-bubble.other .cs-time { text-align: left; }
.cs-empty { text-align: center; color: #bbb; font-size: 13px; font-family: 'Manrope', sans-serif; padding: 40px 16px; }

.cs-input-area {
    padding: 14px 18px; border-top: 1px solid #E5E1D8;
    display: flex; flex-direction: column; gap: 10px; background: #fff; flex-shrink: 0;
}
.cs-input-row { display: flex; gap: 8px; align-items: flex-end; }
#cs-input {
    flex: 1; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 8px;
    padding: 10px 12px; font-size: 14px; font-family: 'Lato', sans-serif; color: #1A1A1A;
    outline: none; resize: none; min-height: 42px; max-height: 120px; transition: border-color .15s;
    box-sizing: border-box;
}
#cs-input:focus { border-color: var(--green); background: #fff; }
.cs-btn-send {
    background: var(--green); color: #F5F0E8; border: none; border-radius: 8px;
    padding: 10px 18px; font-family: 'Manrope', sans-serif; font-size: 13px;
    font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;
    white-space: nowrap; transition: background .15s; flex-shrink: 0;
}
.cs-btn-send:hover { background: #143d2d; }
.cs-btn-end {
    background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 8px;
    padding: 8px 14px; font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 700;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s; align-self: flex-start;
}
.cs-btn-end:hover { background: #FEE2E2; }
.cs-no-conversation {
    flex: 1; display: flex; flex-direction: column; align-items: center;
    justify-content: center; color: #bbb; font-family: 'Manrope', sans-serif;
    font-size: 14px; gap: 10px; padding: 48px;
}

@media (max-width: 768px) {
    .chat-layout { flex-direction: column; }
    .cs-sidebar { width: 100%; }
}
</style>
@endpush

@section('content')

<div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
    <div>
        <a href="{{ route('companies.show', $company) }}" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--green);font-weight:600;text-decoration:none;margin-bottom:8px;">
            <i class="ti ti-arrow-left"></i> Wróć do karty firmy
        </a>
        <h1 style="font-family:'Manrope',sans-serif;font-size:20px;font-weight:700;color:#1A1A1A;margin:0;">
            Chat: {{ $company->name }}
        </h1>
    </div>
    @if($messages->isNotEmpty())
    <button class="cs-btn-end" onclick="csEnd()">
        <i class="ti ti-circle-x"></i> Zakończ rozmowę
    </button>
    @endif
</div>

<div class="chat-layout">

    {{-- LEFT: Archives --}}
    <div class="cs-sidebar">
        <div class="cs-sidebar-header">
            <i class="ti ti-archive"></i> Archiwum rozmów
        </div>
        @if($archives->isEmpty())
            <div class="cs-arc-empty">
                <i class="ti ti-history-off" style="font-size:26px;display:block;margin-bottom:8px;color:#ddd;"></i>
                Brak archiwalnych rozmów
            </div>
        @else
            @foreach($archives as $arc)
            <div class="cs-arc-item">
                <div class="cs-arc-dates">
                    <i class="ti ti-calendar-event" style="font-size:11px;"></i>
                    {{ \Carbon\Carbon::parse($arc->started_at)->format('d.m.Y H:i') }}
                    &rarr; {{ \Carbon\Carbon::parse($arc->ended_at)->format('d.m.Y H:i') }}
                </div>
                <div class="cs-arc-count">
                    <i class="ti ti-messages" style="font-size:11px;"></i>
                    {{ $arc->message_count }} {{ $arc->message_count == 1 ? 'wiadomość' : 'wiadomości' }}
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- RIGHT: Active conversation --}}
    <div class="cs-main">

        <div class="cs-header">
            <div class="cs-header-left">
                <h2><i class="ti ti-message-2" style="margin-right:6px;"></i>Aktywna rozmowa</h2>
                <p>
                    @if($messages->isNotEmpty())
                        Ostatnia wiadomość: {{ $messages->last()->created_at->format('d.m.Y H:i') }}
                    @else
                        Brak aktywnych wiadomości
                    @endif
                </p>
            </div>
        </div>

        <div id="cs-messages">
            @if($messages->isEmpty())
                <div class="cs-empty" id="cs-empty-state">
                    <i class="ti ti-message-off" style="font-size:40px;color:#ddd;display:block;margin-bottom:12px;"></i>
                    Brak wiadomości w tej rozmowie.<br>
                    <span style="font-size:12px;">Napisz pierwszą wiadomość!</span>
                </div>
            @else
                @foreach($messages as $msg)
                @php
                    $isOwn = auth()->id() == $msg->user_id;
                    $name  = $msg->sender?->name ?? 'Nieznany';
                    $parts = explode(' ', trim($name));
                    $ini   = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                @endphp
                <div class="cs-msg-row {{ $isOwn ? 'own' : 'other' }}" data-id="{{ $msg->id }}">
                    <div class="cs-avatar {{ $isOwn ? 'own' : 'other' }}">{{ $ini }}</div>
                    <div class="cs-bubble {{ $isOwn ? 'own' : 'other' }}">
                        {{ $msg->body }}
                        <div class="cs-time">{{ $msg->sender?->name ?? '' }} · {{ $msg->created_at->format('H:i') }}</div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        <div class="cs-input-area">
            <div class="cs-input-row">
                <textarea id="cs-input" placeholder="Napisz wiadomość…" rows="1"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();csSend();}"></textarea>
                <button class="cs-btn-send" onclick="csSend()">
                    <i class="ti ti-send"></i> Wyślij
                </button>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
var CS_SEND = '{{ route('chat.send', $company) }}';
var CS_POLL = '{{ route('chat.poll', $company) }}';
var CS_END  = '{{ route('chat.end', $company) }}';
var CSRF    = '{{ csrf_token() }}';
var MY_ID   = {{ auth()->id() }};
var csLastId = {{ $messages->last()?->id ?? 0 }};

function csInitials(n) {
    if (!n) return '?';
    var p = n.trim().split(' ');
    return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
}
function csEsc(t) {
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}
function csBubble(msg) {
    var cls = msg.is_own ? 'own' : 'other';
    return '<div class="cs-msg-row ' + cls + '" data-id="' + msg.id + '">' +
        '<div class="cs-avatar ' + cls + '">' + csInitials(msg.sender_name) + '</div>' +
        '<div class="cs-bubble ' + cls + '">' + csEsc(msg.body) +
        '<div class="cs-time">' + csEsc(msg.sender_name) + ' · ' + msg.created_at + '</div>' +
        '</div></div>';
}
function csScroll() {
    var el = document.getElementById('cs-messages');
    if (el) el.scrollTop = el.scrollHeight;
}

async function csSend() {
    var input = document.getElementById('cs-input');
    var body  = input.value.trim();
    if (!body) return;
    input.value = '';
    input.style.height = 'auto';
    try {
        var res = await fetch(CS_SEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ body: body }),
        });
        if (!res.ok) return;
        var msg = await res.json();
        msg.is_own = true;
        csLastId = Math.max(csLastId, msg.id);
        var win   = document.getElementById('cs-messages');
        var empty = document.getElementById('cs-empty-state');
        if (empty) empty.remove();
        win.insertAdjacentHTML('beforeend', csBubble(msg));
        csScroll();
    } catch(e) {}
}

async function csPoll() {
    try {
        var res = await fetch(CS_POLL + '?json=1&last_id=' + csLastId, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
        });
        if (!res.ok) return;
        var data = await res.json();
        var msgs = data.messages || [];
        var win  = document.getElementById('cs-messages');
        msgs.forEach(function (msg) {
            if (!win.querySelector('[data-id="' + msg.id + '"]')) {
                var empty = document.getElementById('cs-empty-state');
                if (empty) empty.remove();
                win.insertAdjacentHTML('beforeend', csBubble(msg));
                csLastId = Math.max(csLastId, msg.id);
            }
        });
        if (msgs.length) csScroll();
    } catch(e) {}
}

async function csEnd() {
    if (!confirm('Zakończyć tę rozmowę? Zostanie ona przeniesiona do archiwum.')) return;
    try {
        var res = await fetch(CS_END, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        if (res.ok) window.location.reload();
    } catch(e) {}
}

// Auto-resize textarea
document.getElementById('cs-input').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

csScroll();
setInterval(csPoll, 5000);
</script>
@endpush
