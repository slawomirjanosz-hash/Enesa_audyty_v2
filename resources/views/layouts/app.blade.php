<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ENESA') — Panel</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:   #1A4D3A;
            --cream:   #F4F1EA;
            --sidebar: 260px;
            --topbar:  60px;
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

        .nav-link .arrow {
            margin-left: auto;
            font-size: 11px;
            transition: transform .2s;
        }

        .nav-group.open > .nav-link .arrow {
            transform: rotate(90deg);
        }

        .nav-sub {
            list-style: none;
            display: none;
            background: rgba(0,0,0,.15);
        }

        .nav-group.open > .nav-sub {
            display: block;
        }

        .nav-sub .nav-link {
            padding-left: 52px;
            font-size: 13px;
            font-weight: 400;
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
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Manrope', sans-serif;
            letter-spacing: .3px;
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

        .online-wrapper { position: relative; }

        .btn-online {
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

        .btn-online:hover { background: rgba(255,255,255,.22); }

        .btn-online .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4ade80;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .4; }
        }

        .online-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.14);
            min-width: 200px;
            padding: 8px 0;
            z-index: 200;
        }

        .online-wrapper.open .online-dropdown { display: block; }

        .online-dropdown-header {
            padding: 6px 16px 8px;
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: .6px;
            border-bottom: 1px solid #f0f0f0;
        }

        .online-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            font-size: 13px;
            color: #333;
        }

        .online-user .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4ade80;
            flex-shrink: 0;
        }

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
</head>
<body>

{{-- =============== SIDEBAR =============== --}}
<aside id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <img src="{{ asset('Logo2.png') }}" alt="ENESA logo">
            <span class="sidebar-logo-text">ENESA</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li class="nav-item">
                <a href="{{ url('/dashboard') }}"
                   class="nav-link {{ request()->is('dashboard*') ? 'active' : '' }}">
                    <i class="ti ti-layout-dashboard"></i> Dashboard
                </a>
            </li>

            <li class="nav-item nav-group {{ request()->is('audit*') ? 'open' : '' }}">
                <span class="nav-link" onclick="toggleGroup(this)">
                    <i class="ti ti-clipboard-list"></i> System Audytów
                    <span class="arrow">▶</span>
                </span>
                <ul class="nav-sub">
                    <li><a href="{{ url('/audit-types') }}" class="nav-link">Typy audytów</a></li>
                    <li><a href="{{ url('/surveys') }}" class="nav-link">Ankiety HTML</a></li>
                    <li><a href="{{ url('/versioning') }}" class="nav-link">Wersjonowanie</a></li>
                </ul>
            </li>

            <li class="nav-item nav-group {{ request()->is('crm*') ? 'open' : '' }}">
                <span class="nav-link" onclick="toggleGroup(this)">
                    <i class="ti ti-users"></i> CRM
                    <span class="arrow">▶</span>
                </span>
                <ul class="nav-sub">
                    <li><a href="{{ route('crm.index') }}" class="nav-link">Firmy</a></li>
                    <li><a href="{{ route('crm.index', ['tab' => 'pipeline']) }}" class="nav-link">Lejek sprzedaży</a></li>
                    <li><a href="{{ route('crm.index', ['tab' => 'tasks']) }}" class="nav-link">Zadania</a></li>
                </ul>
            </li>

            <li class="nav-item nav-group {{ request()->is('offer*') ? 'open' : '' }}">
                <span class="nav-link" onclick="toggleGroup(this)">
                    <i class="ti ti-file-invoice"></i> Strefa Ofert
                    <span class="arrow">▶</span>
                </span>
                <ul class="nav-sub">
                    <li><a href="{{ url('/offers?template=1') }}" class="nav-link {{ request()->is('offers*') && request('template') ? 'active' : '' }}">
                        <i class="ti ti-bookmark"></i> Szablony ofert
                    </a></li>
                    <li><a href="{{ url('/offers') }}" class="nav-link {{ request()->is('offers*') && !request('template') ? 'active' : '' }}">
    <i class="ti ti-file-invoice"></i> Oferty
</a></li>
                </ul>
            </li>

            <li class="nav-item">
                <a href="{{ url('/client-zone') }}"
                   class="nav-link {{ request()->is('client-zone*') ? 'active' : '' }}">
                    <i class="ti ti-eye"></i> Strefa klienta
                </a>
            </li>

            <li class="nav-item nav-group {{ request()->is('settings*') ? 'open' : '' }}">
                <span class="nav-link" onclick="toggleGroup(this)">
                    <i class="ti ti-settings"></i> Ustawienia
                    <span class="arrow">▶</span>
                </span>
                <ul class="nav-sub">
                    <li><a href="{{ url('/settings/company') }}" class="nav-link">Dane ENESA</a></li>
                    <li><a href="{{ url('/settings/users') }}" class="nav-link">Użytkownicy</a></li>
                    <li><a href="{{ url('/settings/roles') }}" class="nav-link">Role i uprawnienia</a></li>
                </ul>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        @auth
            <div class="avatar">
                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
            </div>
            <div style="flex:1;min-width:0;">
                <span class="sidebar-user-name">{{ Auth::user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.5);font-size:12px;padding:4px 0;display:flex;align-items:center;gap:6px;">
                        <i class="ti ti-logout"></i> Wyloguj się
                    </button>
                </form>
            </div>
        @endauth
    </div>
</aside>

{{-- =============== TOPBAR =============== --}}
<header id="topbar">
    <div class="topbar-title">@yield('page-title', 'Panel')</div>

    <div class="topbar-right">
        <div class="topbar-datetime" id="topbar-clock"></div>

        <div class="online-wrapper" id="onlineWrapper">
            <button class="btn-online" onclick="toggleOnline()" type="button">
                <span class="dot"></span> online
            </button>
            <div class="online-dropdown">
                <div class="online-dropdown-header">Zalogowani teraz</div>
                @auth
                    <div class="online-user">
                        <span class="dot"></span>
                        {{ auth()->user()->name }}
                    </div>
                @endauth
            </div>
        </div>
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

    function toggleGroup(el) {
        el.closest('.nav-group').classList.toggle('open');
    }

    function toggleOnline() {
        document.getElementById('onlineWrapper').classList.toggle('open');
    }

    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('onlineWrapper');
        if (!wrapper.contains(e.target)) wrapper.classList.remove('open');
    });
</script>

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

    // Modal form submit — re-login without page reload
    document.getElementById('sessionModalForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const errBox = document.getElementById('sessionModalError');
        errBox.style.display = 'none';

        const formData = new FormData();
        formData.append('email',    document.getElementById('smEmail').value);
        formData.append('password', document.getElementById('smPassword').value);
        formData.append('_token',   document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        fetch('{{ route("login") }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: formData
        }).then(function (res) {
            if (res.ok || res.redirected) {
                _sessionExpired = false;
                document.getElementById('session-expired-modal').style.display = 'none';
                document.title = document.title.replace('\u26A0 Sesja wygasła — ', '');
                // restart check
                _sessionCheckInterval = setInterval(arguments.callee.caller, 60000);
                location.reload();
            } else {
                return res.json().then(function (data) {
                    errBox.textContent = data.message || 'Nieprawidłowe dane logowania.';
                    errBox.style.display = 'block';
                });
            }
        }).catch(function () {
            errBox.textContent = 'Błąd połączenia. Spróbuj ponownie.';
            errBox.style.display = 'block';
        });
    });
</script>

<div id="pdf-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#2d2d2d;border-radius:12px;width:100%;max-width:900px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#1e1e1e;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class="ti ti-file-type-pdf" style="font-size:18px;color:#ef4444;"></i>
                <span id="pdf-modal-title" style="font-size:13px;font-weight:600;color:#fff;font-family:'Manrope',sans-serif;">Podgląd oferty</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <a id="pdf-modal-download" href="#"
                   style="display:inline-flex;align-items:center;gap:6px;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:7px;padding:6px 12px;font-size:12px;font-family:'Manrope',sans-serif;font-weight:600;text-decoration:none;">
                    <i class="ti ti-download"></i> Pobierz PDF
                </a>
                <button onclick="closePdfModal()" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:7px;color:#fff;cursor:pointer;font-size:18px;">
                    <i class="ti ti-x"></i>
                </button>
            </div>
        </div>
        <div id="pdf-canvas-container" style="background:#525659;flex:1;overflow-y:auto;display:flex;flex-direction:column;align-items:center;padding:20px;gap:12px;min-height:500px;">
            <div id="pdf-modal-loading" style="color:#ccc;font-family:'Manrope',sans-serif;font-size:14px;display:flex;align-items:center;gap:8px;padding:40px;">
                <i class="ti ti-loader-2" style="font-size:22px;"></i> Ładowanie PDF...
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script>
var _pdfDownloadUrl = null;

if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
}

async function openPdfModal(url, title) {
    var modal = document.getElementById('pdf-modal');
    var container = document.getElementById('pdf-canvas-container');
    var titleEl = document.getElementById('pdf-modal-title');
    var downloadEl = document.getElementById('pdf-modal-download');

    _pdfDownloadUrl = url;
    titleEl.textContent = title || 'Podgląd oferty';
    downloadEl.href = url;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    container.innerHTML = '<div style="color:#ccc;font-family:\'Manrope\',sans-serif;font-size:14px;display:flex;align-items:center;gap:8px;padding:40px;"><i class="ti ti-loader-2" style="font-size:22px;"></i> Ładowanie PDF...</div>';

    try {
        var response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var arrayBuffer = await response.arrayBuffer();

        var pdfDoc = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        container.innerHTML = '';

        for (var i = 1; i <= pdfDoc.numPages; i++) {
            var page = await pdfDoc.getPage(i);
            var viewport = page.getViewport({ scale: 1.5 });

            var canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.cssText = 'display:block;max-width:100%;box-shadow:0 4px 20px rgba(0,0,0,0.5);';
            container.appendChild(canvas);

            await page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: viewport
            }).promise;
        }
    } catch(err) {
        container.innerHTML = '<div style="color:#f87171;font-family:\'Manrope\',sans-serif;font-size:13px;text-align:center;padding:40px;">Błąd ładowania podglądu.<br><small>' + err.message + '</small></div>';
    }
}

function closePdfModal() {
    document.getElementById('pdf-modal').style.display = 'none';
    document.getElementById('pdf-canvas-container').innerHTML = '';
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const pdfModalEl = document.getElementById('pdf-modal');
    if (pdfModalEl) {
        pdfModalEl.addEventListener('click', function(e) {
            if (e.target === this) closePdfModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePdfModal();
    });
});
</script>

@stack('scripts')
</body>
</html>