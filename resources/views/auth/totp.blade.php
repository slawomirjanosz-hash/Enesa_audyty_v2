@extends('auth.totp-layout')
@section('content')
@if(auth()->user()->two_factor_confirmed_at)
    <h1>Potwierdź logowanie</h1>
    <p>Otwórz Google Authenticator lub Microsoft Authenticator i wpisz aktualny kod dla tej aplikacji.</p>
    <form method="POST" action="{{route('auth.totp.verify')}}">@csrf
        <label for="code">Kod z aplikacji lub kod ratunkowy</label>
        <input id="code" name="code" type="text" autocomplete="one-time-code" maxlength="64" required autofocus spellcheck="false" autocapitalize="none">
        <button type="submit">Potwierdź i wejdź</button>
    </form>
    <p class="help">Kod z aplikacji ma 6 cyfr i zmienia się co 30 sekund. Każdy kod ratunkowy działa tylko raz. Przy utracie telefonu użyj zapisanego kodu ratunkowego lub skontaktuj się z operatorem hostingu.</p>
@else
    <h1>Włącz logowanie dwuetapowe</h1>
    <p>Ten krok dotyczy tylko superadministratora. Wystarczy bezpłatna aplikacja Google Authenticator lub Microsoft Authenticator na telefonie. Nie potrzebujesz SMS-ów.</p>
    @if(!$secret)
        <h2>1. Potwierdź swoją tożsamość</h2>
        <form method="POST" action="{{route('auth.totp.begin')}}">@csrf
            <label for="password">Aktualne hasło do naszego systemu</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
            <button type="submit">Pokaż kod QR do konfiguracji</button>
        </form>
    @else
        <h2>2. Zeskanuj kod telefonem</h2>
        <p>W aplikacji Authenticator wybierz dodanie konta i skanowanie kodu QR.</p>
        <img class="qr" src="{{$qr}}" alt="Kod QR do konfiguracji Authenticator">
        <details><summary>Nie mogę zeskanować kodu</summary><p>Wybierz ręczne wpisanie klucza i typ „na podstawie czasu”.</p><code class="secret">{{$secret}}</code></details>
        <p class="help">Nie udostępniaj tego QR ani klucza. Konfiguracja jest ważna 10 minut.</p>
        <h2>3. Wpisz kod i zapisz kody ratunkowe</h2>
        <form method="POST" action="{{route('auth.totp.confirm')}}">@csrf
            <label for="code">Sześciocyfrowy kod z telefonu</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
            <button type="submit">Włącz Authenticator</button>
        </form>
    @endif
@endif
@endsection
