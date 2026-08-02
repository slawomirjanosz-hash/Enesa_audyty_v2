@extends('layouts.app')

@section('page-title', 'Ustawienia — Dane firmy')

@push('styles')
<style>
    /* ── Tabs ─────────────────────────────── */
    .settings-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 28px;
        border-bottom: 2px solid #E5E1D8;
    }
    .settings-tab {
        padding: 10px 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #6b7a70;
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        border-radius: 6px 6px 0 0;
        transition: color .15s, border-color .15s;
    }
    .settings-tab:hover { color: var(--green); }
    .settings-tab.active { color: var(--green); border-bottom-color: var(--green); }

    /* ── Card ─────────────────────────────── */
    .card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #E5E1D8;
        overflow: hidden;
    }
    .card-header {
        display: flex;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #F0EDE6;
    }
    .card-header-title {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .card-header-sub {
        font-size: 13px;
        color: #888;
        margin-top: 2px;
    }

    /* ── Alerts ───────────────────────────── */
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

    /* ── Form ─────────────────────────────── */
    .cf-body { padding: 28px 28px 32px; }
    .cf-section-title {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--green);
        margin: 0 0 16px;
        padding-bottom: 8px;
        border-bottom: 1px solid #F0EDE6;
    }
    .cf-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    .cf-row-full {
        margin-bottom: 16px;
    }
    .cf-group { display: flex; flex-direction: column; }
    .cf-label {
        font-size: 12px;
        font-weight: 700;
        color: #3a3a3a;
        margin-bottom: 5px;
        font-family: 'Manrope', sans-serif;
    }
    .cf-label span { color: #b91c1c; margin-left: 2px; }
    .cf-input {
        width: 100%;
        background: #FAFAF6;
        border: 1px solid #D0CCC0;
        border-radius: 7px;
        padding: 10px 13px;
        font-size: 14px;
        font-family: 'Lato', sans-serif;
        color: #1A1A1A;
        outline: none;
        transition: border-color .15s, background .15s;
        box-sizing: border-box;
    }
    .cf-input:focus { border-color: #2E7D32; background: #fff; }
    .cf-input::placeholder { color: #b0aa9e; }
    .brand-preview {
        width: 88px;
        height: 88px;
        border: 1px solid #D0CCC0;
        border-radius: 10px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .brand-preview img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
    .color-input { width: 64px; height: 42px; padding: 3px; cursor: pointer; }
    .cf-hint {
        font-size: 11px;
        color: #aaa;
        margin-top: 3px;
    }
    .cf-divider {
        border: none;
        border-top: 1px solid #F0EDE6;
        margin: 24px 0 20px;
    }
    .cf-footer {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 20px 28px;
        background: #FAFAF6;
        border-top: 1px solid #F0EDE6;
    }
    .btn-save {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        background: var(--green);
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-save:hover { background: #153d2e; }

    @media (max-width: 640px) {
        .cf-row { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')

<h1 style="margin:0 0 24px;">Dane firmy</h1>

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-error">
        <i class="ti ti-alert-circle"></i> {{ session('error') }}
    </div>
@endif

{{-- Karta formularza --}}
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-header-title"><i class="ti ti-building" style="margin-right:8px;color:var(--green);"></i>Dane firmy właściciela aplikacji</div>
            <div class="card-header-sub">Dane marki, która prowadzi system i wystawia oferty.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
        @csrf

        <div class="cf-body">

            {{-- SEKCJA: Marka aplikacji --}}
            <p class="cf-section-title"><i class="ti ti-palette" style="margin-right:6px;"></i>Marka aplikacji</p>

            <div class="cf-row">
                <div class="cf-group">
                    <label class="cf-label" for="logo">Logo aplikacji</label>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <div class="brand-preview">
                            <img src="{{ $appBrand?->logoUrl() ?? asset('Logo2.png') }}" alt="Aktualne logo">
                        </div>
                        <div style="flex:1;">
                            <input id="logo" type="file" name="logo" class="cf-input @error('logo') is-invalid @enderror" accept="image/png,image/jpeg,image/webp">
                            <span class="cf-hint">PNG, JPG lub WebP, maksymalnie 2 MB. Logo pojawi się w panelu i strefie klienta.</span>
                            @error('logo')
                                <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="cf-group">
                    <label class="cf-label" for="primary_color">Kolor wiodący aplikacji<span>*</span></label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input id="primary_color" type="color" name="primary_color" class="cf-input color-input @error('primary_color') is-invalid @enderror"
                               value="{{ old('primary_color', $appBrand?->primaryColor() ?? '#1A4D3A') }}">
                        <output id="primary-color-value" style="font-family:'Lato',sans-serif;font-size:14px;font-weight:700;color:var(--green);">
                            {{ old('primary_color', $appBrand?->primaryColor() ?? '#1A4D3A') }}
                        </output>
                    </div>
                    <span class="cf-hint">Zmienia kolor nawigacji, przycisków i akcentów w aplikacji.</span>
                    @error('primary_color')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="cf-row-full">
                <div class="cf-group">
                    <label class="cf-label" for="welcome_page_mode">Ekran powitalny aplikacji<span>*</span></label>
                    <select id="welcome_page_mode" name="welcome_page_mode" class="cf-input">
                        <option value="audit" {{ old('welcome_page_mode', $company->welcome_page_mode ?? 'audit') === 'audit' ? 'selected' : '' }}>Strona audytowa — obecna strona ENESA</option>
                        <option value="general" {{ old('welcome_page_mode', $company->welcome_page_mode ?? 'audit') === 'general' ? 'selected' : '' }}>Strona uniwersalna — bez treści o audytach</option>
                        <option value="login_only" {{ old('welcome_page_mode', $company->welcome_page_mode ?? 'audit') === 'login_only' ? 'selected' : '' }}>Bez strony powitalnej — od razu ekran logowania</option>
                    </select>
                    <span class="cf-hint">Ta opcja określa, co zobaczy osoba wchodząca na główny adres aplikacji.</span>
                    @error('welcome_page_mode')<span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>@enderror
                </div>
            </div>

            <hr class="cf-divider">

            {{-- SEKCJA: Informacje podstawowe --}}
            <p class="cf-section-title"><i class="ti ti-info-circle" style="margin-right:6px;"></i>Informacje podstawowe</p>

            <div class="cf-row">
                <div class="cf-group">
                    <label class="cf-label" for="name">Nazwa firmy<span>*</span></label>
                    <input id="name" type="text" name="name" class="cf-input @error('name') is-invalid @enderror"
                           value="{{ old('name', $company->name ?? '') }}"
                           placeholder="np. ENESA Sp. z o.o." required>
                    @error('name')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
                <div class="cf-group">
                    <label class="cf-label" for="tagline">Tagline / slogan</label>
                    <input id="tagline" type="text" name="tagline" class="cf-input @error('tagline') is-invalid @enderror"
                           value="{{ old('tagline', $company->tagline ?? '') }}"
                           placeholder="np. Audyty energetyczne dla przemysłu">
                    @error('tagline')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="cf-row">
                <div class="cf-group">
                    <label class="cf-label" for="nip">NIP</label>
                    <input id="nip" type="text" name="nip" class="cf-input @error('nip') is-invalid @enderror"
                           value="{{ old('nip', $company->nip ?? '') }}"
                           placeholder="0000000000" maxlength="10">
                    <span class="cf-hint">Dokładnie 10 cyfr, bez myślników.</span>
                    @error('nip')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
                <div class="cf-group">
                    <label class="cf-label" for="website">Strona WWW</label>
                    <input id="website" type="url" name="website" class="cf-input @error('website') is-invalid @enderror"
                           value="{{ old('website', $company->website ?? '') }}"
                           placeholder="https://enesa.pl">
                    @error('website')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <hr class="cf-divider">

            {{-- SEKCJA: Kontakt --}}
            <p class="cf-section-title"><i class="ti ti-phone" style="margin-right:6px;"></i>Kontakt</p>

            <div class="cf-row">
                <div class="cf-group">
                    <label class="cf-label" for="email">Adres e-mail</label>
                    <input id="email" type="email" name="email" class="cf-input @error('email') is-invalid @enderror"
                           value="{{ old('email', $company->email ?? '') }}"
                           placeholder="biuro@enesa.pl">
                    @error('email')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
                <div class="cf-group">
                    <label class="cf-label" for="phone">Telefon</label>
                    <input id="phone" type="tel" name="phone" class="cf-input @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $company->phone ?? '') }}"
                           placeholder="+48 000 000 000">
                    @error('phone')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <hr class="cf-divider">

            {{-- SEKCJA: Adres --}}
            <p class="cf-section-title"><i class="ti ti-map-pin" style="margin-right:6px;"></i>Adres siedziby</p>

            <div class="cf-row-full">
                <div class="cf-group">
                    <label class="cf-label" for="address">Ulica i numer</label>
                    <input id="address" type="text" name="address" class="cf-input @error('address') is-invalid @enderror"
                           value="{{ old('address', $company->address ?? '') }}"
                           placeholder="ul. Przykładowa 1/2">
                    @error('address')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="cf-row">
                <div class="cf-group">
                    <label class="cf-label" for="city">Miasto</label>
                    <input id="city" type="text" name="city" class="cf-input @error('city') is-invalid @enderror"
                           value="{{ old('city', $company->city ?? '') }}"
                           placeholder="Warszawa">
                    @error('city')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
                <div class="cf-group">
                    <label class="cf-label" for="postcode">Kod pocztowy</label>
                    <input id="postcode" type="text" name="postcode" class="cf-input @error('postcode') is-invalid @enderror"
                           value="{{ old('postcode', $company->postcode ?? '') }}"
                           placeholder="00-000">
                    @error('postcode')
                        <span class="cf-hint" style="color:#b91c1c;">{{ $message }}</span>
                    @enderror
                </div>
            </div>

        </div>{{-- /.cf-body --}}

        <div class="cf-footer">
            <button type="submit" class="btn-save">
                <i class="ti ti-device-floppy"></i>Zapisz zmiany
            </button>
            <span style="font-size:12px;color:#aaa;">Zmiany będą widoczne natychmiast po zapisaniu. Firma właściciela zostanie automatycznie zsynchronizowana.</span>
        </div>

    </form>

    {{-- Owner company sync status --}}
    @php $ownerCompany = \App\Models\Company::owner()->first(); @endphp
    <div style="margin-top:16px;background:#FAFAF6;border:1px solid #E5E1D8;border-radius:10px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;">
        <div style="display:flex;align-items:center;gap:10px;">
            @if($ownerCompany)
                <span style="width:10px;height:10px;background:#16a34a;border-radius:50%;display:inline-block;flex-shrink:0;"></span>
                <div>
                    <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#1A1A1A;">Firma właściciela zsynchronizowana</div>
                    <div style="font-size:12px;color:#888;font-family:'Lato',sans-serif;">{{ $ownerCompany->name }} (ID: {{ $ownerCompany->id }}) — pracownicy są przypisywani do tej firmy automatycznie.</div>
                </div>
            @else
                <span style="width:10px;height:10px;background:#ef4444;border-radius:50%;display:inline-block;flex-shrink:0;"></span>
                <div>
                    <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#b91c1c;">Firma właściciela nie jest skonfigurowana</div>
                    <div style="font-size:12px;color:#888;font-family:'Lato',sans-serif;">Kliknij "Synchronizuj", aby utworzyć firmę właściciela na podstawie danych powyżej.</div>
                </div>
            @endif
        </div>
        <form method="POST" action="{{ route('settings.company.sync-owner') }}" style="flex-shrink:0;">
            @csrf
            <button type="submit" style="background:var(--green);color:#F5F0E8;border:none;border-radius:8px;padding:9px 18px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;">
                <i class="ti ti-refresh"></i> Synchronizuj teraz
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.getElementById('primary_color')?.addEventListener('input', function () {
        document.getElementById('primary-color-value').textContent = this.value.toUpperCase();
    });
</script>
@endpush
