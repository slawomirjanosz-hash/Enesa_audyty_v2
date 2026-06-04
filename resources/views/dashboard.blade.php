@extends('layouts.app')

@section('page-title', 'Dashboard')

@push('styles')
<style>
    /* ── Page header ──────────────────────── */
    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 22px;
    }
    .page-badge {
        display: inline-block;
        background: #E8F5E9;
        color: #2E7D32;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        padding: 3px 10px;
        border-radius: 4px;
        margin-bottom: 8px;
    }
    .page-title {
        font-family: 'Lato', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A1A1A;
        margin: 0;
    }
    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        border-radius: 7px;
        padding: 9px 18px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .btn-add:hover { background: #153d2e; color: #F5F0E8; }

    /* ── Stats grid ───────────────────────── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 20px;
    }
    .stat-icon-green { background: #E8F5E9; color: #2E7D32; }
    .stat-icon-blue  { background: #E3F2FD; color: #1565C0; }
    .stat-icon-red   { background: #FFEBEE; color: #C62828; }

    .stat-value {
        font-family: 'Lato', sans-serif;
        font-size: 26px;
        font-weight: 900;
        color: #1A1A1A;
        line-height: 1;
        margin-bottom: 2px;
    }
    .stat-label {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #555;
        margin-bottom: 2px;
    }
    .stat-sub {
        font-size: 11px;
        color: #999;
    }

    /* ── Clients grid ─────────────────────── */
    .clients-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .client-tile {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 12px;
        padding: 16px;
        cursor: pointer;
        transition: transform .15s, box-shadow .15s;
        position: relative;
        border-left-width: 3px;
        border-left-style: solid;
    }
    .client-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.09);
    }
    .tile-status-bar {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
    }
    .status-dot-tile {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .dot-green  { background: #43A047; }
    .dot-gray   { background: #CBD5E1; }
    .status-label-tile {
        font-size: 10.5px;
        font-family: 'Manrope', sans-serif;
        font-weight: 600;
        color: #888;
    }
    .tile-main {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    .tile-name {
        font-family: 'Lato', sans-serif;
        font-size: 13.5px;
        font-weight: 700;
        color: #1A1A1A;
        margin-bottom: 2px;
    }
    .tile-nip {
        font-size: 11px;
        color: #888;
    }
    .tile-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 99px;
        font-family: 'Manrope', sans-serif;
        font-size: 10.5px;
        font-weight: 700;
        white-space: nowrap;
        flex-shrink: 0;
        margin-left: 8px;
    }
    .tile-badge-pending { background: #FFF3E0; color: #E65100; }
    .tile-badge-active  { background: #E8F5E9; color: #2E7D32; }
    .tile-badge-other   { background: #F0EDE6; color: #888; }

    .tile-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
        margin-bottom: 12px;
    }
    .tile-info-row {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        color: #555;
    }
    .tile-info-row i {
        font-size: 14px;
        color: #aaa;
        width: 16px;
        text-align: center;
    }

    .tile-footer {
        border-top: 1px solid #F0EDE6;
        padding-top: 12px;
        display: flex;
        gap: 8px;
    }
    .tile-btn-primary {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 7px 0;
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        border-radius: 6px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s;
    }
    .tile-btn-primary:hover { background: #153d2e; color: #F5F0E8; }
    .tile-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 7px 14px;
        background: transparent;
        color: #1A4D3A;
        border: 1px solid #C8DDD4;
        border-radius: 6px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s, border-color .15s;
    }
    .tile-btn-secondary:hover { background: #F0F8F4; border-color: #1A4D3A; }

    /* ── Empty state ──────────────────────── */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 24px;
        background: #fff;
        border: 1px dashed #D0CCC0;
        border-radius: 12px;
    }
    .empty-state i {
        font-size: 40px;
        color: #C8DDD4;
        display: block;
        margin-bottom: 12px;
    }
    .empty-state p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #888;
        margin: 0;
    }

    @media (max-width: 1100px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .clients-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .stats-grid { grid-template-columns: 1fr; }
        .clients-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')

{{-- ══════ NAGŁÓWEK ══════ --}}
<div class="page-header">
    <div>
        <span class="page-badge">Widok audytora</span>
        <h1 class="page-title">Klienci wymagający uwagi</h1>
    </div>
    <a href="#" class="btn-add" onclick="openModal()">
        <i class="ti ti-plus"></i>Dodaj klienta
    </a>
</div>

{{-- ══════ STATYSTYKI ══════ --}}
<div class="stats-grid">

    {{-- Aktywne audyty --}}
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="ti ti-clipboard-list"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active_audits'] }}</div>
            <div class="stat-label">Aktywne audyty</div>
            <div class="stat-sub" style="color:#2E7D32;">↑ w tym tygodniu</div>
        </div>
    </div>

    {{-- Oferty do wysłania --}}
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <i class="ti ti-file-invoice"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['pending_offers'] }}</div>
            <div class="stat-label">Oferty do wysłania</div>
            <div class="stat-sub" @if($stats['pending_offers'] > 0) style="color:#EF6C00;" @endif>
                {{ $stats['pending_offers'] > 0 ? 'Wymagają akcji' : 'Brak oczekujących' }}
            </div>
        </div>
    </div>

    {{-- Nowe rejestracje --}}
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="ti ti-user-plus"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['new_registrations'] }}</div>
            <div class="stat-label">Nowe rejestracje</div>
            <div class="stat-sub">Do akceptacji</div>
        </div>
    </div>

    {{-- Zadania po terminie --}}
    <div class="stat-card">
        <div class="stat-icon stat-icon-red">
            <i class="ti ti-clock"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['overdue_tasks'] }}</div>
            <div class="stat-label">Zadania po terminie</div>
            <div class="stat-sub" @if($stats['overdue_tasks'] > 0) style="color:#C62828;" @endif>
                {{ $stats['overdue_tasks'] > 0 ? 'Pilne — sprawdź teraz' : 'Wszystko na czas' }}
            </div>
        </div>
    </div>

</div>

{{-- ══════ SIATKA KLIENTÓW ══════ --}}
<div class="clients-grid">
    @forelse($companies as $company)
        @php
            $isOnline = $company->users->contains(
                fn($u) => $u->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5))
            );
            $borderColor = match($company->status) {
                'pending' => '#EF6C00',
                'active'  => '#2E7D32',
                default   => '#E5E1D8',
            };
        @endphp
        <div class="client-tile" style="border-left-color: {{ $borderColor }};">

            {{-- Status online --}}
            <div class="tile-status-bar">
                <span class="status-dot-tile {{ $isOnline ? 'dot-green' : 'dot-gray' }}"></span>
                <span class="status-label-tile">{{ $isOnline ? 'Online' : 'Offline' }}</span>
            </div>

            {{-- Nazwa + NIP + badge statusu --}}
            <div class="tile-main">
                <div>
                    <div class="tile-name">{{ $company->name }}</div>
                    @if($company->nip)
                        <div class="tile-nip">NIP: {{ $company->nip }}</div>
                    @endif
                </div>
                <span class="tile-badge {{ match($company->status) {
                    'pending' => 'tile-badge-pending',
                    'active'  => 'tile-badge-active',
                    default   => 'tile-badge-other',
                } }}">
                    {{ match($company->status) {
                        'pending' => 'Oczekuje',
                        'active'  => 'Aktywny',
                        default   => ucfirst($company->status),
                    } }}
                </span>
            </div>

            {{-- Info wiersze --}}
            <div class="tile-info">
                <div class="tile-info-row">
                    <i class="ti ti-clipboard"></i>
                    <span>{{ $company->audits->count() }} {{ $company->audits->count() === 1 ? 'audyt' : ($company->audits->count() < 5 ? 'audyty' : 'audytów') }}</span>
                </div>
                <div class="tile-info-row">
                    <i class="ti ti-file-invoice"></i>
                    <span>{{ $company->offers->count() }} {{ $company->offers->count() === 1 ? 'oferta' : ($company->offers->count() < 5 ? 'oferty' : 'ofert') }}</span>
                </div>
                <div class="tile-info-row">
                    <i class="ti ti-message"></i>
                    <span>0 wiadomości</span>
                </div>
            </div>

            {{-- Przyciski --}}
            <div class="tile-footer">
                <a href="#" class="tile-btn-primary">
                    <i class="ti ti-layout-2"></i>Otwórz kartę
                </a>
                <a href="#" class="tile-btn-secondary">
                    <i class="ti ti-message-circle"></i>Chat
                </a>
            </div>

        </div>
    @empty
        <div class="empty-state">
            <i class="ti ti-building-community"></i>
            <p>Brak klientów — dodaj pierwszego klienta, by zobaczyć go tutaj.</p>
        </div>
    @endforelse
</div>

{{-- ══════ MODAL DODAJ KLIENTA ══════ --}}
<div id="addClientModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:36px;max-width:500px;width:95%;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:22px;color:#aaa;cursor:pointer;line-height:1;">&times;</button>
        <div style="font-family:'Manrope',sans-serif;font-size:18px;font-weight:700;color:#1A4D3A;margin-bottom:6px;">
            <i class="ti ti-building" style="margin-right:8px;"></i>Dodaj klienta
        </div>
        <div style="font-size:13px;color:#888;margin-bottom:24px;">Wypełnij dane firmy lub pobierz automatycznie z GUS.</div>

        <form method="POST" action="{{ route('companies.store') }}">
            @csrf

            {{-- NIP + GUS --}}
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">NIP firmy</label>
                <div style="display:flex;gap:8px;">
                    <input id="nip-input" type="text" name="nip" placeholder="np. 527-000-11-22"
                           style="flex:1;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;"
                           oninput="this.value=this.value.replace(/[^0-9\-]/g,'')"
                           maxlength="13">
                    <button type="button" onclick="fetchFromGus()"
                            style="padding:10px 14px;background:rgba(26,77,58,0.08);border:1px solid rgba(26,77,58,0.25);border-radius:6px;color:#1A4D3A;font-family:'Lato',sans-serif;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
                        Pobierz z GUS
                    </button>
                </div>
            </div>

            {{-- Nazwa firmy --}}
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Nazwa firmy<span style="color:#b91c1c;">*</span></label>
                <input id="company-name" type="text" name="name" required readonly
                       style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                       placeholder="Pobrana z GUS lub wpisz ręcznie">
            </div>

            {{-- Adres + Miasto --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Adres</label>
                    <input id="company-address" type="text" name="address" readonly
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="ul. Przykładowa 1">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Miasto</label>
                    <input id="company-city" type="text" name="city" readonly
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="Warszawa">
                </div>
            </div>

            {{-- Email + Telefon --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Email</label>
                    <input type="email" name="email"
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="biuro@firma.pl">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Telefon</label>
                    <input type="tel" name="phone"
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="+48 000 000 000">
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit"
                        style="flex:1;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:8px;padding:12px;font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:background .15s;">
                    <i class="ti ti-plus" style="margin-right:6px;"></i>Dodaj klienta
                </button>
                <button type="button" onclick="closeModal()"
                        style="padding:12px 20px;background:transparent;color:#888;border:1px solid #E5E1D8;border-radius:8px;font-family:'Manrope',sans-serif;font-size:14px;font-weight:600;cursor:pointer;">
                    Anuluj
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openModal() {
        document.getElementById('addClientModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('addClientModal').style.display = 'none';
    }
    function fetchFromGus() {
        const nip = document.getElementById('nip-input').value.replace(/[^0-9]/g, '');
        if (nip.length !== 10) {
            alert('NIP musi mieć 10 cyfr');
            return;
        }
        fetch('{{ route("companies.fetchGus") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ nip: nip }),
        })
        .then(r => r.json())
        .then(d => {
            const name = document.getElementById('company-name');
            const addr = document.getElementById('company-address');
            const city = document.getElementById('company-city');
            name.value = d.name    || '';
            addr.value = d.address || '';
            city.value = d.city    || '';
            if (d.name)    { name.style.borderColor = '#2E7D32'; name.readOnly = true; }
            if (d.address) { addr.style.borderColor = '#2E7D32'; }
            if (d.city)    { city.style.borderColor = '#2E7D32'; }
        })
        .catch(() => alert('Błąd pobierania danych z GUS'));
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    document.getElementById('addClientModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>
@endpush

@endsection
