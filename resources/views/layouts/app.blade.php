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

        .mobile-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            color: #fff;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 8px;
            cursor: pointer;
            font-size: 22px;
        }

        .sidebar-backdrop { display: none; }

        @media (max-width: 767px) {
            html, body { overflow-x: hidden; }

            #sidebar {
                width: min(280px, 86vw);
                transform: translateX(-100%);
                transition: transform .2s ease;
                z-index: 1001;
                box-shadow: 8px 0 24px rgba(0,0,0,.24);
            }

            body.mobile-menu-open #sidebar { transform: translateX(0); }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                display: block;
                visibility: hidden;
                opacity: 0;
                background: rgba(0,0,0,.42);
                transition: opacity .2s ease, visibility .2s ease;
                z-index: 1000;
            }

            body.mobile-menu-open .sidebar-backdrop {
                visibility: visible;
                opacity: 1;
            }

            #topbar {
                left: 0;
                height: 56px;
                padding: 0 16px;
                gap: 12px;
                z-index: 900;
            }

            .mobile-menu-toggle { display: inline-flex; }
            .topbar-title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .topbar-right { display: none; }

            #main { margin-left: 0; padding-top: 56px; }
            .content-area { padding: 16px; }
        }
    </style>

    <style>
    /* Globalne dymki: dodaj data-tooltip="..." do dowolnego elementu */
    [data-tooltip] { position: relative; }
    [data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: #1A4D3A;
    color: #F5F0E8;
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    padding: 6px 10px;
    border-radius: 7px;
    box-shadow: 0 4px 14px rgba(0,0,0,.18);
    opacity: 0;
    pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
    z-index: 9999;
    }
    [data-tooltip]::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 3px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    border: 5px solid transparent;
    border-top-color: #1A4D3A;
    opacity: 0;
    pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
    z-index: 9999;
    }
    [data-tooltip]:hover::after,
    [data-tooltip]:hover::before {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
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
                    <li><a href="{{ url('/offer-forms') }}" class="nav-link {{ request()->is('offer-forms*') ? 'active' : '' }}"><i class="ti ti-clipboard-list"></i> Formularze zapytań</a></li>
                </ul>
            </li>

            <li class="nav-item">
                <a href="{{ url('/client-zone') }}"
                   class="nav-link {{ request()->is('client-zone*') ? 'active' : '' }}">
                    <i class="ti ti-eye"></i> Strefa klienta
                </a>
            </li>

            @if(auth()->user()->hasAnyRole(['superadmin', 'admin', 'auditor_senior']))
            <li class="nav-item">
                <a href="{{ route('documents.index') }}"
                   class="nav-link {{ request()->routeIs('documents.index') ? 'active' : '' }}">
                    <i class="ti ti-folder"></i> Wszystkie dokumenty
                </a>
            </li>
            @endif

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

<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

{{-- =============== TOPBAR =============== --}}
<header id="topbar">
    <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Otwórz menu" aria-controls="sidebar" aria-expanded="false">
        <i class="ti ti-menu-2"></i>
    </button>
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

    function setMobileMenu(open) {
        document.body.classList.toggle('mobile-menu-open', open);
        document.getElementById('mobileMenuToggle').setAttribute('aria-expanded', String(open));
    }

    document.getElementById('mobileMenuToggle').addEventListener('click', function () {
        setMobileMenu(!document.body.classList.contains('mobile-menu-open'));
    });
    document.getElementById('sidebarBackdrop').addEventListener('click', function () { setMobileMenu(false); });
    document.querySelectorAll('#sidebar a').forEach(function (link) {
        link.addEventListener('click', function () { setMobileMenu(false); });
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 767) setMobileMenu(false);
    });

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
    const sessionModalForm = document.getElementById('sessionModalForm');
    if (sessionModalForm) sessionModalForm.addEventListener('submit', function (e) {
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

{{-- ===================== AUDITOR CHAT WIDGET ===================== --}}
{{-- Only on company detail page --}}
@if(auth()->check() && request()->routeIs('companies.show'))
@php $widgetCompany = request()->route('company'); @endphp
@if($widgetCompany)
<div id="aud-chat-widget">

    <button id="aud-chat-toggle" onclick="audWidgetToggle()" title="Chat z {{ $widgetCompany->name }}" aria-label="Chat">
        <i class="ti ti-message-circle"></i>
        <span id="aud-badge" style="display:none;">!</span>
    </button>

    <div id="aud-chat-bubble">

        <div id="aud-bubble-header">
            <div style="min-width:0;">
                <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $widgetCompany->name }}
                </div>
                <div style="font-size:11px;color:#C8DDD4;font-family:'Lato',sans-serif;" id="aud-header-sub">Chat</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <a href="{{ route('chat.show', $widgetCompany) }}" class="aud-ctrl-btn" title="Otwórz pełny widok" style="text-decoration:none;"><i class="ti ti-external-link"></i></a>
                <button class="aud-ctrl-btn" id="aud-expand-btn" onclick="audWidgetExpand()" title="Rozszerz"><i class="ti ti-arrows-maximize"></i></button>
                <button class="aud-ctrl-btn" onclick="audWidgetClose()" title="Zamknij"><i class="ti ti-x"></i></button>
            </div>
        </div>

        <div id="aud-messages-window"></div>

        <div id="aud-msg-footer">
            <textarea id="aud-msg-input" placeholder="Napisz wiadomość…" rows="2"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();audSend();}"></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <button id="aud-end-btn" onclick="audEndConversation()">
                    <i class="ti ti-circle-x"></i> Zakończ
                </button>
                <button id="aud-send-btn" onclick="audSend()">
                    <i class="ti ti-send"></i> Wyślij
                </button>
            </div>
        </div>

    </div>
</div>

<style>
#aud-chat-widget {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    display: flex; flex-direction: column; align-items: flex-end; gap: 12px;
}
#aud-chat-toggle {
    width: 56px; height: 56px; border-radius: 50%; background: #1A4D3A; color: #fff;
    border: none; cursor: pointer; font-size: 24px; display: flex; align-items: center;
    justify-content: center; box-shadow: 0 4px 20px rgba(26,77,58,.35); position: relative;
    transition: background .15s, transform .15s; flex-shrink: 0;
}
#aud-chat-toggle:hover { background: #143d2d; transform: scale(1.07); }
#aud-badge {
    position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px;
    border-radius: 8px; background: #EF4444; color: #fff; font-size: 10px; font-weight: 700;
    font-family: 'Manrope', sans-serif; display: flex; align-items: center;
    justify-content: center; padding: 0 3px; border: 2px solid #fff;
}
#aud-chat-bubble {
    display: none; flex-direction: column; width: 340px; height: 460px; background: #fff;
    border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.10);
    overflow: hidden; transition: width .2s, height .2s;
}
#aud-chat-bubble.open     { display: flex; }
#aud-chat-bubble.expanded { width: 560px; height: 620px; }
#aud-bubble-header {
    background: #1A4D3A; padding: 12px 14px; display: flex; align-items: center;
    justify-content: space-between; flex-shrink: 0; gap: 8px;
}
.aud-ctrl-btn {
    background: rgba(255,255,255,.12); border: none; color: #fff; border-radius: 6px;
    width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px; transition: background .12s;
}
.aud-ctrl-btn:hover { background: rgba(255,255,255,.22); }
#aud-messages-window {
    flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column;
    gap: 8px; background: #FAFAF6; min-height: 0;
}
.aud-msg-row { display:flex; align-items:flex-end; gap:6px; }
.aud-msg-row.own   { flex-direction:row-reverse; }
.aud-msg-row.other { flex-direction:row; }
.aud-avatar {
    width: 26px; height: 26px; border-radius: 50%; font-size: 10px; font-weight: 700;
    font-family: 'Manrope', sans-serif; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0;
}
.aud-avatar.own   { background: #1A4D3A; color: #fff; }
.aud-avatar.other { background: #E5E1D8; color: #555; }
.aud-bubble {
    max-width: 75%; padding: 8px 11px; border-radius: 12px; font-size: 13px;
    font-family: 'Lato', sans-serif; line-height: 1.45; word-break: break-word;
}
.aud-bubble.own   { background: #1A4D3A; color: #fff; border-bottom-right-radius: 3px; }
.aud-bubble.other { background: #F0EDE6; color: #1A1A1A; border-bottom-left-radius: 3px; }
.aud-time { font-size: 10px; margin-top: 3px; opacity: .6; text-align: right; }
.aud-bubble.other .aud-time { text-align: left; }
.aud-msg-empty { text-align: center; color: #ccc; font-size: 12px; font-family: 'Manrope', sans-serif; padding: 24px 8px; }
#aud-msg-footer {
    border-top: 1px solid #E5E1D8; padding: 10px 12px; display: flex;
    flex-direction: column; gap: 8px; flex-shrink: 0; background: #fff;
}
#aud-msg-input {
    width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 7px;
    padding: 8px 10px; font-size: 13px; font-family: 'Lato', sans-serif; color: #1A1A1A;
    outline: none; resize: none; transition: border-color .15s; box-sizing: border-box;
}
#aud-msg-input:focus { border-color: #1A4D3A; background: #fff; }
#aud-send-btn {
    background: #1A4D3A; color: #F5F0E8; border: none; border-radius: 7px; padding: 7px 14px;
    font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 5px; transition: background .15s;
}
#aud-send-btn:hover { background: #143d2d; }
#aud-end-btn {
    background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; border-radius: 7px;
    padding: 6px 12px; font-family: 'Manrope', sans-serif; font-size: 11px; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background .15s;
}
#aud-end-btn:hover { background: #FEE2E2; }
</style>

<script>
(function () {
    var BASE       = '{{ url('/chat') }}';
    var COMPANY_ID = {{ $widgetCompany->id }};
    var CSRF       = '{{ csrf_token() }}';
    var MY_ID      = {{ auth()->id() }};
    var isOpen     = false;
    var expanded   = false;
    var audLastId  = 0;
    var pollTimer  = null;

    function esc(t) {
        return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }
    function initials(n) {
        if (!n) return '?';
        var p = n.trim().split(' ');
        return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
    }
    function scrollBottom() {
        var el = document.getElementById('aud-messages-window');
        if (el) el.scrollTop = el.scrollHeight;
    }
    function buildBubble(msg) {
        var cls = msg.is_own ? 'own' : 'other';
        return '<div class="aud-msg-row ' + cls + '" data-id="' + msg.id + '">' +
            '<div class="aud-avatar ' + cls + '">' + initials(msg.sender_name) + '</div>' +
            '<div class="aud-bubble ' + cls + '">' + esc(msg.body) +
            '<div class="aud-time">' + msg.created_at + '</div></div></div>';
    }

    async function loadMessages(since) {
        try {
            var res = await fetch(BASE + '/' + COMPANY_ID + '?json=1&last_id=' + since, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            if (!res.ok) return;
            var data = await res.json();
            var msgs = data.messages || [];
            var win  = document.getElementById('aud-messages-window');
            var badge = document.getElementById('aud-badge');

            if (since === 0) {
                win.innerHTML = msgs.length === 0
                    ? '<div class="aud-msg-empty"><i class="ti ti-message-off" style="font-size:24px;display:block;margin-bottom:6px;"></i>Brak wiadomości.<br>Napisz pierwszą!</div>'
                    : msgs.map(buildBubble).join('');
                if (msgs.length > 0) audLastId = msgs[msgs.length - 1].id;
            } else {
                var hasNew = false;
                msgs.forEach(function (msg) {
                    if (!win.querySelector('[data-id="' + msg.id + '"]')) {
                        var empty = win.querySelector('.aud-msg-empty');
                        if (empty) empty.remove();
                        win.insertAdjacentHTML('beforeend', buildBubble(msg));
                        audLastId = Math.max(audLastId, msg.id);
                        hasNew = true;
                    }
                });
                if (hasNew && badge) badge.style.display = 'none';
            }
            scrollBottom();
        } catch (e) {}
    }

    window.audWidgetToggle = function () {
        if (isOpen) { audWidgetClose(); return; }
        var bubble = document.getElementById('aud-chat-bubble');
        bubble.classList.add('open');
        isOpen = true;
        document.getElementById('aud-badge').style.display = 'none';
        loadMessages(0);
        pollTimer = setInterval(function () {
            if (isOpen) loadMessages(audLastId);
        }, 5000);
    };

    window.audWidgetClose = function () {
        document.getElementById('aud-chat-bubble').classList.remove('open');
        isOpen = false;
        clearInterval(pollTimer);
        pollTimer = null;
    };

    window.audWidgetExpand = function () {
        expanded = !expanded;
        var bubble = document.getElementById('aud-chat-bubble');
        var btn    = document.getElementById('aud-expand-btn');
        bubble.classList.toggle('expanded', expanded);
        btn.innerHTML = expanded ? '<i class="ti ti-arrows-minimize"></i>' : '<i class="ti ti-arrows-maximize"></i>';
    };

    window.audSend = async function () {
        var input = document.getElementById('aud-msg-input');
        var body  = input.value.trim();
        if (!body) return;
        input.value = '';
        try {
            var res = await fetch(BASE + '/' + COMPANY_ID + '/send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ body: body }),
            });
            if (!res.ok) return;
            var msg = await res.json();
            msg.is_own = true;
            var win = document.getElementById('aud-messages-window');
            var empty = win.querySelector('.aud-msg-empty');
            if (empty) empty.remove();
            win.insertAdjacentHTML('beforeend', buildBubble(msg));
            audLastId = Math.max(audLastId, msg.id);
            scrollBottom();
        } catch (e) {}
    };

    window.audEndConversation = async function () {
        if (!confirm('Zakończyć rozmowę z tą firmą?')) return;
        try {
            var res = await fetch(BASE + '/' + COMPANY_ID + '/end', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            if (res.ok) {
                document.getElementById('aud-messages-window').innerHTML =
                    '<div class="aud-msg-empty"><i class="ti ti-check" style="font-size:24px;display:block;margin-bottom:6px;color:#1A4D3A;"></i>Rozmowa zakończona.</div>';
                audLastId = 0;
            }
        } catch (e) {}
    };

    // Check for unread messages on page load (badge only)
    (async function () {
        try {
            var res = await fetch(BASE + '?json=1', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            if (!res.ok) return;
            var data = await res.json();
            var unread = (data.companies || []).reduce(function (sum, item) {
                return item.company && item.company.id === COMPANY_ID ? sum + (item.unread_count || 0) : sum;
            }, 0);
            var badge = document.getElementById('aud-badge');
            if (badge && unread > 0) { badge.textContent = unread; badge.style.display = 'flex'; }
        } catch (e) {}
    })();
})();
</script>
@endif
@endauth

</body>
</html>
