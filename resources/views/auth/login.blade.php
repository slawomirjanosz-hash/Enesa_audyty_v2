@php
    $brandName = $appBrand?->name ?: 'ENESA';
    $brandColor = $appBrand?->primaryColor() ?: '#1A4D3A';
    $brandLogo = $appBrand?->logoUrl() ?: asset('Logo2.png');
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie — {{ $brandName }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&family=Manrope:wght@400;500;600;700&display=swap">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --green: {{ $brandColor }}; }
        body { font-family: 'Lato', sans-serif; background: #F4F1EA; }

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 500; background: var(--green); height: 64px; padding: 0 48px; display: flex; align-items: center; gap: 40px; }
        .navbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
        .navbar-brand img { width: 38px; height: 38px; object-fit: contain; }
        .navbar-brand span { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 18px; color: #F5F0E8; letter-spacing: 0.04em; }
        .navbar-spacer { flex: 1; }
        .navbar-actions { display: flex; align-items: center; gap: 10px; }
        .btn-outline-nav { padding: 8px 18px; border: 1px solid rgba(255,255,255,0.5); border-radius: 7px; background: transparent; color: #F5F0E8; font-family: 'Lato', sans-serif; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; transition: background .2s, border-color .2s; }
        .btn-outline-nav:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.8); }
        .btn-filled-nav { padding: 8px 18px; border: 1px solid #F5F0E8; border-radius: 7px; background: #F5F0E8; color: var(--green); font-family: 'Lato', sans-serif; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .2s, transform .15s; }
        .btn-filled-nav:hover { background: #fff; transform: translateY(-1px); }

        /* MAIN */
        .main-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 64px); background: #F4F1EA; padding: 40px 24px; }

        /* LOGIN BOX */
        .login-box { background: #fff; max-width: 420px; width: 100%; border-radius: 12px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.08); text-align: center; }
        .login-logo { width: 52px; height: 52px; object-fit: contain; margin-bottom: 18px; }
        .login-box h2 { font-family: 'Lato', sans-serif; font-size: 22px; font-weight: 700; color: var(--green); margin-bottom: 8px; }
        .login-desc { font-size: 13px; color: #5a6a60; margin-bottom: 26px; line-height: 1.55; }

        .form-group { text-align: left; margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #3a3a3a; margin-bottom: 5px; }
        .form-group .login-input { width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 6px; padding: 10px 13px; font-size: 14px; font-family: 'Lato', sans-serif; color: #1e1e1e; outline: none; transition: border-color .15s; }
        .form-group input:focus { border-color: #2E7D32; background: #fff; }
        .password-field { position: relative; }
        .password-field .login-input { padding-right: 46px; }
        .password-toggle { position: absolute; top: 50%; right: 5px; transform: translateY(-50%); width: 36px; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: transparent; border: none; border-radius: 6px; color: #65756c; font-size: 19px; cursor: pointer; }
        .password-toggle:hover { background: rgba(26,77,58,.08); color: var(--green); }
        .password-toggle:focus-visible { outline: 2px solid var(--green); outline-offset: 1px; }

        .remember-row { display: flex; align-items: center; gap: 8px; margin-bottom: 18px; }
        .remember-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--green); cursor: pointer; }
        .remember-row label { font-size: 13px; color: #5a6a60; cursor: pointer; }

        .error-msg { color: #b91c1c; font-size: 12px; margin-top: 4px; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; color: #b91c1c; font-size: 13px; padding: 10px 14px; margin-bottom: 16px; text-align: left; }
        .session-status { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; color: #166534; font-size: 13px; padding: 10px 14px; margin-bottom: 16px; text-align: left; }

        .btn-submit { width: 100%; background: var(--green); color: #F5F0E8; border: none; border-radius: 8px; padding: 13px; font-size: 15px; font-family: 'Manrope', sans-serif; font-weight: 700; cursor: pointer; transition: background .15s; }
        .btn-submit:hover { background: #153d2e; }

        .forgot-link { display: block; margin-top: 16px; font-size: 13px; color: #2E7D32; text-decoration: none; text-align: center; }
        .forgot-link:hover { text-decoration: underline; }

        /* MODAL */
        #modalOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9000; align-items: center; justify-content: center; }
        #modalOverlay.open { display: flex; }
        .modal-box { background: #fff; border-radius: 14px; padding: 40px; max-width: 480px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; }
        .modal-close { position: absolute; top: 16px; right: 20px; background: none; border: none; font-size: 22px; color: #888; cursor: pointer; line-height: 1; }
        .modal-close:hover { color: #333; }
        .modal-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 22px; }
        .modal-brand img { width: 40px; height: 40px; object-fit: contain; }
        .modal-brand span { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 16px; color: var(--green); }
        .modal-box h2 { font-family: 'Manrope', sans-serif; font-size: 22px; font-weight: 700; color: var(--green); margin-bottom: 8px; }
        .modal-box > p { font-size: 13px; color: #5a6a60; margin-bottom: 24px; line-height: 1.6; }
        .mform-group { margin-bottom: 14px; }
        .mform-group label { display: block; font-size: 12px; font-weight: 700; color: #3a3a3a; margin-bottom: 5px; }
        .mform-row { display: flex; gap: 8px; }
        .mform-row input { flex: 1; }
        .mform-input { width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 6px; padding: 10px 12px; font-size: 14px; font-family: 'Lato', sans-serif; outline: none; transition: border-color .2s; }
        .mform-input:focus { border-color: #2E7D32; }
        .btn-gus { padding: 10px 14px; background: rgba(26,77,58,0.08); border: 1px solid rgba(26,77,58,0.25); border-radius: 6px; color: var(--green); font-family: 'Lato', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background .2s; }
        .btn-gus:hover { background: rgba(26,77,58,0.15); }
        .modal-divider { border: none; border-top: 1px solid #E5E1D8; margin: 20px 0; }
        .btn-modal-submit { width: 100%; background: var(--green); color: #F5F0E8; border: none; border-radius: 8px; padding: 13px; font-family: 'Manrope', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s; margin-top: 4px; }
        .btn-modal-submit:hover { background: #2E7D32; }
        .modal-login-link { display: block; text-align: center; font-size: 13px; color: #888; margin-top: 16px; }
        .modal-login-link a { color: var(--green); font-weight: 700; text-decoration: none; }
        .modal-login-link a:hover { text-decoration: underline; }
    </style>
    <link rel="icon" type="image/png" href="{{ $brandLogo }}">
    <link rel="apple-touch-icon" href="{{ $brandLogo }}">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="{{ url('/') }}" class="navbar-brand">
        <img src="{{ $brandLogo }}" alt="{{ $brandName }} logo">
        <span>{{ $brandName }}</span>
    </a>
    <div class="navbar-spacer"></div>
    <div class="navbar-actions">
        <a href="{{ route('home') }}" class="btn-outline-nav">Strona g&#322;&#243;wna</a>
        <button class="btn-filled-nav" onclick="openModal()">Zarejestruj firm&#281;</button>
    </div>
</nav>

<!-- MAIN -->
<div class="main-wrap">
    <div class="login-box">
        <a href="{{ url('/') }}">
            <img src="{{ $brandLogo }}" alt="{{ $brandName }} logo" class="login-logo">
        </a>
        <h2>Zaloguj si&#281;</h2>
        <p class="login-desc">Witaj z powrotem. Zaloguj si&#281; do swojego konta.</p>

        @if (session('status'))
            <div class="session-status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Adres e-mail</label>
                <input id="email" class="login-input" type="email" name="email"
                       value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       placeholder="adres@firma.pl">
            </div>

            <div class="form-group">
                <label for="password">Has&#322;o</label>
                <div class="password-field">
                    <input id="password" class="login-input" type="password" name="password"
                           required autocomplete="current-password"
                           placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;">
                    <button id="passwordToggle" type="button" class="password-toggle" aria-label="Pokaż hasło" aria-pressed="false" title="Pokaż hasło">
                        <i class="ti ti-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="remember-row">
                <input type="checkbox" id="remember_me" name="remember">
                <label for="remember_me">Zapami&#281;taj mnie</label>
            </div>

            <button type="submit" class="btn-submit">Zaloguj si&#281;</button>
        </form>

        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" class="forgot-link">Zapomnia&#322;em has&#322;a</a>
        @endif
    </div>
</div>

<!-- MODAL REJESTRACJI -->
<div id="modalOverlay" onclick="closeModalOutside(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()" title="Zamknij">&times;</button>
        <div class="modal-brand">
            <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
            <span>{{ $brandName }}</span>
        </div>
        <h2>Zarejestruj firm&#281;</h2>
        <p>Wype&#322;nij formularz, a nasz zesp&#243;&#322; skontaktuje si&#281; z Tob&#261; w ci&#261;gu jednego dnia roboczego z ofert&#261; dostosowan&#261; do Twoich potrzeb.</p>
        <form id="registerForm" onsubmit="return false;">
            <div class="mform-group">
                <label for="regNip">NIP firmy</label>
                <div class="mform-row">
                    <input id="regNip" type="text" class="mform-input" placeholder="000-000-00-00" maxlength="13">
                    <button type="button" class="btn-gus" onclick="fetchGUS()">Pobierz z GUS</button>
                </div>
            </div>
            <div class="mform-group">
                <label for="regNazwa">Nazwa firmy</label>
                <input id="regNazwa" type="text" class="mform-input" placeholder="Pobrana automatycznie z GUS" readonly>
            </div>
            <div class="mform-group">
                <label for="regAdres">Adres</label>
                <input id="regAdres" type="text" class="mform-input" placeholder="Pobrana automatycznie z GUS" readonly>
            </div>
            <hr class="modal-divider">
            <div class="mform-group">
                <label for="regImie">Imi&#281; i nazwisko osoby kontaktowej</label>
                <input id="regImie" type="text" class="mform-input" placeholder="Jan Kowalski">
            </div>
            <div class="mform-group">
                <label for="regEmail">Adres e-mail</label>
                <input id="regEmail" type="email" class="mform-input" placeholder="jan.kowalski@firma.pl">
            </div>
            <div class="mform-group">
                <label for="regTelefon">Telefon</label>
                <input id="regTelefon" type="tel" class="mform-input" placeholder="+48 000 000 000">
            </div>
            <button type="submit" class="btn-modal-submit">Wy&#347;lij zg&#322;oszenie do {{ $brandName }}</button>
        </form>
        <p class="modal-login-link">Masz ju&#380; konto? <a href="{{ route('login') }}">Zaloguj si&#281;</a></p>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');

    passwordToggle.addEventListener('click', function () {
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
        passwordToggle.setAttribute('aria-label', showPassword ? 'Ukryj hasło' : 'Pokaż hasło');
        passwordToggle.title = showPassword ? 'Ukryj hasło' : 'Pokaż hasło';
        passwordToggle.querySelector('i').className = showPassword ? 'ti ti-eye-off' : 'ti ti-eye';
        passwordInput.focus({ preventScroll: true });
    });

    function openModal() {
        document.getElementById('modalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    function closeModalOutside(event) {
        if (event.target === document.getElementById('modalOverlay')) closeModal();
    }
    function fetchGUS() {
        alert('Funkcja pobierania danych z GUS zostanie uruchomiona wkr&#243;tce.');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
</script>
</body>
</html>
