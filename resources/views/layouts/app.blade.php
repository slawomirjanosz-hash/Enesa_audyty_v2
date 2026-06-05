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
                    <li><a href="{{ url('/companies') }}" class="nav-link">Firmy</a></li>
                    <li><a href="{{ url('/contacts') }}" class="nav-link">Kontakty</a></li>
                    <li><a href="{{ url('/pipeline') }}" class="nav-link">Lejek sprzedaży</a></li>
                    <li><a href="{{ url('/tasks') }}" class="nav-link">Zadania</a></li>
                </ul>
            </li>

            <li class="nav-item nav-group {{ request()->is('offer*') ? 'open' : '' }}">
                <span class="nav-link" onclick="toggleGroup(this)">
                    <i class="ti ti-file-invoice"></i> Strefa Ofert
                    <span class="arrow">▶</span>
                </span>
                <ul class="nav-sub">
                    <li><a href="{{ url('/offer-templates') }}" class="nav-link">Szablony</a></li>
                    <li><a href="{{ url('/offers') }}" class="nav-link">Wysłane oferty</a></li>
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

@stack('scripts')

{{-- =============== SESSION EXPIRED MODAL =============== --}}
<div id="session-expired-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:36px;max-width:420px;width:90%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.22);">
        <i class="ti ti-lock-off" style="font-size:48px;color:#EF6C00;display:block;margin-bottom:16px;"></i>
        <h2 style="font-family:'Lato',sans-serif;font-size:20px;font-weight:700;color:#1A4D3A;margin-bottom:10px;">Sesja wygasła</h2>
        <p style="font-size:13px;color:#5a6a60;margin-bottom:24px;line-height:1.6;">Twoja sesja wygasła z powodu braku aktywności. Zaloguj się ponownie aby kontynuować.</p>

        <div id="sessionModalError" style="display:none;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#b91c1c;font-size:13px;padding:8px 12px;margin-bottom:14px;text-align:left;"></div>

        <form id="sessionModalForm" action="{{ route('login') }}" method="POST">
            @csrf
            <div style="margin-bottom:12px;text-align:left;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:4px;">E-mail</label>
                <input id="smEmail" type="text" name="email" required
                    style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;outline:none;"
                    placeholder="Twój email">
            </div>
            <div style="margin-bottom:20px;text-align:left;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:4px;">Hasło</label>
                <input id="smPassword" type="password" name="password" required
                    style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;outline:none;"
                    placeholder="••••••••">
            </div>
            <button type="submit"
                style="width:100%;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:8px;padding:12px;font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;cursor:pointer;">
                Zaloguj się ponownie
            </button>
        </form>
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
</body>
</html>