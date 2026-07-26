<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ENESA') — Strefa Klienta</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:   #2E6B52;
            --cream:   #F4F1EA;
            --sidebar: 260px;
            --topbar:  64px;
        }

        body {
            font-family: 'Manrope', 'Lato', sans-serif;
            background: var(--cream);
            color: #1e1e1e;
            min-height: 100vh;
        }

        /* SIDEBAR */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar);
            height: 100vh;
            background: var(--green);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-logo {
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .sidebar-logo-text {
            color: #fff;
            font-family: 'Manrope', sans-serif;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
        }

        .nav-item {
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            color: rgba(255,255,255,.82);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background .15s, color .15s;
            cursor: pointer;
            user-select: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,.12);
            color: #fff;
        }

        .nav-link i {
            font-size: 18px;
            flex-shrink: 0;
        }

        /* SIDEBAR FOOTER */
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,.25);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-user-name {
            color: rgba(255,255,255,.9);
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-meta {
            color: rgba(255,255,255,.5);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* TOPBAR */
        #topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar);
            right: 0;
            height: var(--topbar);
            background: var(--green);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 90;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }

        .topbar-title {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .topbar-company {
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Manrope', sans-serif;
            letter-spacing: .3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-section {
            color: rgba(255,255,255,.6);
            font-size: 11px;
            font-family: 'Lato', sans-serif;
            margin-top: 1px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .topbar-datetime {
            color: rgba(255,255,255,.75);
            font-size: 13px;
            font-family: 'Lato', sans-serif;
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-logout:hover { background: rgba(255,255,255,.22); }

        /* MAIN CONTENT */
        #main {
            margin-left: var(--sidebar);
            padding-top: var(--topbar);
            min-height: 100vh;
        }

        .content-area {
            padding: 32px;
        }
    </style>

    @stack('styles')
    <link rel="icon" type="image/png" sizes="114x114" href="{{ asset('logo1.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo1.png') }}">
</head>
<body>

{{-- =============== SIDEBAR =============== --}}
<aside id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route('client.dashboard') }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <img src="{{ asset('Logo2.png') }}" alt="ENESA logo">
            <span class="sidebar-logo-text">ENESA</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li class="nav-item">
                <a href="{{ route('client.dashboard') }}"
                   class="nav-link {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('client.audits') }}"
                   class="nav-link {{ request()->routeIs('client.audits') ? 'active' : '' }}">
                    <i class="ti ti-clipboard-check"></i> Moje audyty
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('client.request-offer') }}"
                   class="nav-link {{ request()->routeIs('client.request-offer') ? 'active' : '' }}">
                    <i class="ti ti-send"></i> Zapytaj o ofertę
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('client.offers') }}"
                   class="nav-link {{ request()->routeIs('client.offers') ? 'active' : '' }}">
                    <i class="ti ti-file-invoice"></i> Oferty
                </a>
            </li>

            @if(auth()->user()->hasRole('client_admin'))
            <li class="nav-item">
                <a href="{{ route('client.users') }}"
                   class="nav-link {{ request()->routeIs('client.users') ? 'active' : '' }}">
                    <i class="ti ti-users"></i> Użytkownicy
                </a>
            </li>
            @endif

            <li class="nav-item">
                <a href="{{ route('client.documents') }}"
                   class="nav-link {{ request()->routeIs('client.documents') ? 'active' : '' }}">
                    <i class="ti ti-files"></i> Dokumenty
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('client.chat') }}"
                   class="nav-link {{ request()->routeIs('client.chat') ? 'active' : '' }}">
                    <i class="ti ti-message-2"></i> Chat
                </a>
            </li>
        </ul>
    </nav>

    @auth
        @php
            $clientUser     = auth()->user();
            $clientCompany  = $clientUser->companies->first();
            $clientRoleLabel = $clientUser->hasRole('client_admin') ? 'Administrator firmy' : 'Pracownik';
        @endphp
        <div class="sidebar-footer">
            <div class="avatar">
                {{ strtoupper(substr($clientUser->name, 0, 2)) }}
            </div>
            <div style="flex:1;min-width:0;">
                <div class="sidebar-user-name">{{ $clientUser->name }}</div>
                @if($clientCompany)
                    <div class="sidebar-user-meta">{{ $clientCompany->name }}</div>
                @endif
                <div class="sidebar-user-meta">{{ $clientRoleLabel }}</div>
            </div>
        </div>
    @endauth
</aside>

{{-- =============== TOPBAR =============== --}}
<header id="topbar">
    <div class="topbar-title">
        @auth
        @php $__topCompany = auth()->user()->companies->first(); @endphp
        <div class="topbar-company">Jesteś w strefie klienta: {{ $__topCompany?->name ?? 'Twoja firma' }}</div>
        <div class="topbar-section">@yield('page-title')</div>
        @endauth
    </div>

    <div class="topbar-right">
        <div class="topbar-datetime" id="topbar-clock"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">
                <i class="ti ti-logout"></i> Wyloguj
            </button>
        </form>
    </div>
</header>

{{-- =============== MAIN =============== --}}
<main id="main">
    <div class="content-area">
        @yield('content')
    </div>
</main>

<script>
    function updateClock() {
        const now = new Date();
        const date = now.toLocaleDateString('pl-PL', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const time = now.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.getElementById('topbar-clock').textContent = date + ', ' + time;
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>

@stack('scripts')

{{-- =============== SESSION EXPIRED MODAL =============== --}}
<div id="session-expired-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:36px;max-width:420px;width:90%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.22);">
        <i class="ti ti-lock-off" style="font-size:48px;color:#EF6C00;display:block;margin-bottom:16px;"></i>
        <h2 style="font-family:'Lato',sans-serif;font-size:20px;font-weight:700;color:#1A4D3A;margin-bottom:10px;">Sesja wygasła</h2>
        <p style="font-size:13px;color:#5a6a60;margin-bottom:24px;line-height:1.6;">Twoja sesja wygasła z powodu braku aktywności.</p>
        <button type="button" onclick="window.location.href = '/login';"
            style="width:100%;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:8px;padding:12px;font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;cursor:pointer;">
            Przejdź do logowania
        </button>
    </div>
</div>

<script>
    // Session expiry check — every 60 seconds
    let _sessionExpired = false;
    let _sessionCheckInterval = setInterval(function () {
        fetch('/session-check', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (!data.authenticated && !_sessionExpired) {
                _sessionExpired = true;
                clearInterval(_sessionCheckInterval);
                document.getElementById('session-expired-modal').style.display = 'flex';
                document.title = '\u26A0 Sesja wygasła — ENESA';
            }
        }).catch(function () { /* network error – ignore */ });
    }, 60000);
</script>

{{-- ===================== CHAT WIDGET ===================== --}}
@php $isOnChatPage = request()->routeIs('client.chat'); @endphp
@unless($isOnChatPage)
<div id="chat-widget">

    {{-- Toggle button --}}
    <button id="chat-toggle" onclick="widgetToggle()" title="Chat" aria-label="Otwórz chat">
        <i class="ti ti-message-circle"></i>
        <span id="widget-badge" style="display:none;"></span>
    </button>

    {{-- Bubble --}}
    <div id="chat-bubble">

        {{-- Bubble header --}}
        <div id="bubble-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <span id="bubble-status-dot" class="w-status-dot w-status-offline"></span>
                <div>
                    <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#fff;">Chat ENESA</div>
                    <div style="font-size:11px;color:#C8DDD4;font-family:'Lato',sans-serif;" id="bubble-status-label">Niedostępny</div>
                </div>
            </div>
            <div style="display:flex;gap:6px;">
                <button class="bubble-ctrl-btn" id="expand-btn" onclick="widgetExpand()" title="Rozszerz"><i class="ti ti-arrows-maximize"></i></button>
                <button class="bubble-ctrl-btn" onclick="widgetClose()" title="Zamknij"><i class="ti ti-x"></i></button>
            </div>
        </div>

        {{-- Messages --}}
        <div id="bubble-messages"></div>

        {{-- Input --}}
        <div id="bubble-footer">
            <textarea id="bubble-input" placeholder="Napisz wiadomość…" rows="2"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();widgetSend();}"></textarea>
            <button id="bubble-send-btn" onclick="widgetSend()">
                <i class="ti ti-send"></i> Wyślij
            </button>
        </div>

    </div>
</div>

<style>
#chat-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}
#chat-toggle {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #1A4D3A;
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(26,77,58,.35);
    position: relative;
    transition: background .15s, transform .15s;
    flex-shrink: 0;
}
#chat-toggle:hover { background: #143d2d; transform: scale(1.07); }
#widget-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}
.w-status-online  { background: #4ADE80; }
.w-status-offline { background: #9CA3AF; }
.w-status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}
#chat-bubble {
    display: none;
    flex-direction: column;
    width: 340px;
    max-height: 480px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.10);
    overflow: hidden;
    transition: width .2s, max-height .2s;
}
#chat-bubble.open { display: flex; }
#chat-bubble.expanded { width: 600px; max-height: 640px; }
#bubble-header {
    background: #1A4D3A;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.bubble-ctrl-btn {
    background: rgba(255,255,255,.12);
    border: none;
    color: #fff;
    border-radius: 6px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
    transition: background .12s;
}
.bubble-ctrl-btn:hover { background: rgba(255,255,255,.22); }
#bubble-messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 0;
    background: #FAFAF6;
}
.w-msg-row {
    display: flex;
    align-items: flex-end;
    gap: 6px;
}
.w-msg-row.own   { flex-direction: row-reverse; }
.w-msg-row.other { flex-direction: row; }
.w-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 700;
    font-family: 'Manrope', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.w-avatar.own   { background: #1A4D3A; color: #fff; }
.w-avatar.other { background: #E5E1D8; color: #555; }
.w-bubble {
    max-width: 75%;
    padding: 8px 11px;
    border-radius: 12px;
    font-size: 13px;
    font-family: 'Lato', sans-serif;
    line-height: 1.45;
    word-break: break-word;
}
.w-bubble.own   { background: #1A4D3A; color: #fff; border-bottom-right-radius: 3px; }
.w-bubble.other { background: #F0EDE6; color: #1A1A1A; border-bottom-left-radius: 3px; }
.w-time {
    font-size: 10px;
    margin-top: 3px;
    opacity: .6;
    text-align: right;
}
.w-bubble.other .w-time { text-align: left; }
.w-empty {
    text-align: center;
    color: #ccc;
    font-size: 12px;
    font-family: 'Manrope', sans-serif;
    padding: 24px 8px;
}
#bubble-footer {
    border-top: 1px solid #E5E1D8;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-shrink: 0;
    background: #fff;
}
#bubble-input {
    width: 100%;
    background: #FAFAF6;
    border: 1px solid #D0CCC0;
    border-radius: 7px;
    padding: 8px 10px;
    font-size: 13px;
    font-family: 'Lato', sans-serif;
    color: #1A1A1A;
    outline: none;
    resize: none;
    transition: border-color .15s;
    box-sizing: border-box;
}
#bubble-input:focus { border-color: #1A4D3A; background: #fff; }
#bubble-send-btn {
    align-self: flex-end;
    background: #1A4D3A;
    color: #F5F0E8;
    border: none;
    border-radius: 7px;
    padding: 7px 14px;
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: background .15s;
}
#bubble-send-btn:hover { background: #143d2d; }
</style>

<script>
(function () {
    var SEND = '{{ route('client.chat.send') }}';
    var POLL = '{{ route('client.chat.poll') }}';
    var CSRF = '{{ csrf_token() }}';
    var MY_ID = {{ auth()->id() }};
    var widgetOpen = false;
    var widgetExpanded = false;
    var wLastId = 0;
    var wPollTimer = null;

    function wInitials(name) {
        if (!name) return '?';
        var parts = name.trim().split(' ');
        return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
    }

    function wEsc(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');
    }

    function wBuildBubble(msg) {
        var cls = msg.is_own ? 'own' : 'other';
        return '<div class="w-msg-row ' + cls + '" data-id="' + msg.id + '">' +
            '<div class="w-avatar ' + cls + '">' + wInitials(msg.sender_name) + '</div>' +
            '<div class="w-bubble ' + cls + '">' + wEsc(msg.body) +
            '<div class="w-time">' + msg.created_at + '</div>' +
            '</div></div>';
    }

    function wScrollBottom() {
        var el = document.getElementById('bubble-messages');
        if (el) el.scrollTop = el.scrollHeight;
    }

    function wSetStatus(isOnline) {
        var dot   = document.getElementById('bubble-status-dot');
        var label = document.getElementById('bubble-status-label');
        var badge = document.getElementById('widget-badge');
        if (dot) {
            dot.className = 'w-status-dot ' + (isOnline ? 'w-status-online' : 'w-status-offline');
        }
        if (label) label.textContent = isOnline ? 'Dostępny' : 'Niedostępny';
        if (badge) {
            badge.className = isOnline ? 'w-status-online' : 'w-status-offline';
        }
    }

    async function wPoll() {
        if (!widgetOpen) return;
        try {
            var res = await fetch(POLL + '?last_id=' + wLastId, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            if (!res.ok) return;
            var data = await res.json();

            if (data.onlineUsers !== undefined) {
                wSetStatus(data.onlineUsers.length > 0);
            }

            if (data.messages && data.messages.length > 0) {
                var win = document.getElementById('bubble-messages');
                var empty = win.querySelector('.w-empty');
                if (empty) empty.remove();
                data.messages.forEach(function (msg) {
                    if (!win.querySelector('[data-id="' + msg.id + '"]')) {
                        win.insertAdjacentHTML('beforeend', wBuildBubble(msg));
                        wLastId = Math.max(wLastId, msg.id);
                    }
                });
                wScrollBottom();
            }
        } catch (e) {}
    }

    window.widgetToggle = function () {
        var bubble = document.getElementById('chat-bubble');
        if (widgetOpen) {
            widgetClose();
        } else {
            bubble.classList.add('open');
            widgetOpen = true;
            var badge = document.getElementById('widget-badge');
            if (badge) badge.style.display = 'none';
            wPoll();
            wPollTimer = setInterval(wPoll, 5000);
            wScrollBottom();
        }
    };

    window.widgetClose = function () {
        var bubble = document.getElementById('chat-bubble');
        bubble.classList.remove('open');
        widgetOpen = false;
        clearInterval(wPollTimer);
        wPollTimer = null;
    };

    window.widgetExpand = function () {
        var bubble = document.getElementById('chat-bubble');
        var btn    = document.getElementById('expand-btn');
        widgetExpanded = !widgetExpanded;
        bubble.classList.toggle('expanded', widgetExpanded);
        btn.innerHTML = widgetExpanded
            ? '<i class="ti ti-arrows-minimize"></i>'
            : '<i class="ti ti-arrows-maximize"></i>';
    };

    window.widgetSend = async function () {
        var input = document.getElementById('bubble-input');
        var body  = input.value.trim();
        if (!body) return;
        input.value = '';
        try {
            var res = await fetch(SEND, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ body: body }),
            });
            if (!res.ok) return;
            var msg = await res.json();
            msg.is_own = true;
            wLastId = Math.max(wLastId, msg.id);

            var win   = document.getElementById('bubble-messages');
            var empty = win.querySelector('.w-empty');
            if (empty) empty.remove();
            win.insertAdjacentHTML('beforeend', wBuildBubble(msg));
            wScrollBottom();
        } catch (e) {}
    };

    // Check online status immediately (lightweight poll with last_id=0)
    (async function () {
        try {
            var res = await fetch(POLL + '?last_id=0', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            if (!res.ok) return;
            var data = await res.json();

            // Pre-load messages into widget
            var win = document.getElementById('bubble-messages');
            if (data.messages && data.messages.length > 0) {
                win.innerHTML = '';
                data.messages.forEach(function (msg) {
                    win.insertAdjacentHTML('beforeend', wBuildBubble(msg));
                    wLastId = Math.max(wLastId, msg.id);
                });
            } else {
                win.innerHTML = '<div class="w-empty"><i class="ti ti-message-off" style="font-size:24px;display:block;margin-bottom:6px;"></i>Brak wiadomości</div>';
            }

            if (data.onlineUsers !== undefined) {
                wSetStatus(data.onlineUsers.length > 0);
                var badge = document.getElementById('widget-badge');
                if (badge) badge.style.display = data.onlineUsers.length > 0 ? 'block' : 'none';
            }
        } catch (e) {}
    })();
})();
</script>
@endunless

</body>
</html>
