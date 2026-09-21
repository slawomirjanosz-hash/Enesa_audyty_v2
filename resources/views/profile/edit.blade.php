@extends($user->hasAnyRole(['client_admin', 'client_user']) ? 'layouts.client' : 'layouts.app')

@section('title', 'Mój profil')
@section('page-title', 'Mój profil')

@push('styles')
<style>
.profile-page{max-width:1000px;margin:0 auto;color:#243c36}.profile-page h1{font-size:26px;margin:0 0 8px}.profile-page h2{font-size:18px;margin:0 0 12px}.profile-help{font-size:14px;color:#66756f;line-height:1.65;margin:8px 0 18px}.profile-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:24px;margin:22px 0}.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.profile-field label{display:block;font-size:14px;font-weight:600;margin-bottom:8px}.profile-field input:not([type=checkbox]){width:100%;box-sizing:border-box;border:1px solid #cccfc8;border-radius:7px;padding:11px 12px;font:inherit;background:#fff;color:#243c36}.profile-field input[readonly]{background:#f4f5f2;color:#68766f}.profile-field input:focus-visible,.profile-button:focus-visible{outline:3px solid var(--green);outline-offset:3px}.profile-media{display:flex;align-items:center;gap:20px;flex-wrap:wrap}.profile-media .profile-field{flex:1;min-width:220px}.profile-avatar{width:72px;height:72px;border-radius:50%;overflow:hidden;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center}.profile-signature{width:220px;height:90px;border:1px solid #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fff;padding:8px}.profile-signature img{max-width:100%;max-height:100%;object-fit:contain}.profile-check{display:flex;align-items:center;gap:8px;font-size:14px;margin-top:12px}.profile-button{background:var(--green);color:#fff;padding:11px 20px;border:0;border-radius:8px;font:inherit;font-weight:600;cursor:pointer}.profile-success{background:#edf8f1;padding:14px;border:1px solid #b7d9c4;border-radius:8px;margin:16px 0}.profile-error{color:#b42318;font-size:14px;margin:8px 0}#signature{scroll-margin-top:90px}@media(max-width:640px){.profile-grid{grid-template-columns:1fr}.profile-card{padding:18px}.profile-media .profile-field{min-width:0;width:100%;flex-basis:100%}}
</style>
@endpush

@section('content')
<div class="profile-page">
    <h1>Mój profil</h1>
    <p class="profile-help">Twoje dane, podpis do dokumentów i hasło. Zmiana roli, uprawnień, adresu logowania, przypisania do firmy oraz blokowanie lub usuwanie konta należą do uprawnionego administratora.</p>
    @if(session('status') === 'profile-updated')<div class="profile-success" role="status">Zapisano profil i podpis.</div>@endif
    @if(session('status') === 'password-updated')<div class="profile-success" role="status">Hasło zostało zmienione. Pozostałe sesje zostały unieważnione.</div>@endif
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('patch')
        <section class="profile-card">
            <h2>Dane użytkownika</h2>
            <div class="profile-grid">
                <div class="profile-field"><label for="name">Imię i nazwisko</label><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required autocomplete="name">@error('name')<p class="profile-error">{{ $message }}</p>@enderror</div>
                <div class="profile-field"><label for="email">E-mail — adres logowania</label><input id="email" name="email" type="email" value="{{ $user->email }}" readonly autocomplete="username">@error('email')<p class="profile-error">Adres logowania może zmienić uprawniony administrator.</p>@enderror</div>
            </div>
        </section>
        <section class="profile-card">
            <h2>Zdjęcie profilowe</h2>
            <div class="profile-media">
                <div class="profile-avatar"><x-user-avatar :user="$user" /></div>
                <div class="profile-field"><label for="avatar">Wgraj zdjęcie</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"><p class="profile-help">JPG, PNG lub WEBP, maksymalnie 2 MB.</p></div>
            </div>
            @if($user->avatar_data)<label class="profile-check"><input type="checkbox" name="remove_avatar" value="1"> Usuń zdjęcie profilowe</label>@endif
            @error('avatar')<p class="profile-error">{{ $message }}</p>@enderror
        </section>
        <section class="profile-card" id="signature">
            <h2>Podpis do protokołów i dokumentów HR</h2>
            <p class="profile-help">Podpisz się na białej kartce, zrób zdjęcie lub skan i przytnij obraz do samego podpisu. Najlepiej użyć PNG z białym lub przezroczystym tłem. To obraz podpisu, a nie kwalifikowany podpis elektroniczny.</p>
            <div class="profile-media">
                <div class="profile-signature">@if($user->signatureDataUri())<img src="{{ $user->signatureDataUri() }}" alt="Twój zapisany podpis">@else<span>Brak podpisu</span>@endif</div>
                <div class="profile-field"><label for="signature_file">Wgraj swój podpis</label><input id="signature_file" name="signature" type="file" accept="image/jpeg,image/png,image/webp"><p class="profile-help">JPG, PNG lub WEBP, maksymalnie 2 MB. Aby go zachować, kliknij „Zapisz profil”.</p></div>
            </div>
            @if($user->signature_data)<label class="profile-check"><input type="checkbox" name="remove_signature" value="1"> Usuń zapisany podpis</label>@endif
            @error('signature')<p class="profile-error">{{ $message }}</p>@enderror
            <p class="profile-help">W protokole zaznacz „Dołącz mój zapisany podpis” i wpisz siebie jako odbierającego. Zmiana podpisu w profilu nie zmienia podpisów zapisanych wcześniej w protokołach.</p>
        </section>
        <button class="profile-button" type="submit">Zapisz profil</button>
    </form>
    <section class="profile-card">
        <h2>Zmiana hasła</h2>
        <p class="profile-help">Podaj aktualne hasło i ustaw nowe, unikalne hasło. Po zmianie pozostałe sesje zostaną unieważnione.</p>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('put')
            <div class="profile-grid">
                <div class="profile-field"><label for="current_password">Aktualne hasło</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
                <div class="profile-field"><label for="password">Nowe hasło</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
                <div class="profile-field"><label for="password_confirmation">Powtórz nowe hasło</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
            </div>
            @foreach($errors->updatePassword->all() as $error)<p class="profile-error" role="alert">{{ $error }}</p>@endforeach
            <p><button type="submit" class="profile-button">Zmień hasło</button></p>
        </form>
    </section>
</div>
@endsection
