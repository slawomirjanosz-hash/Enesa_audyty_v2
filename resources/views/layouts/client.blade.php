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
</body>
</html>
