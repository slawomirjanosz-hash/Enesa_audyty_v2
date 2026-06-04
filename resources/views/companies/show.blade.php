@extends('layouts.app')

@section('title', $company->name)
@section('page-title', 'Karta klienta')

@push('styles')
<style>
    /* ── HEADER KARTY ── */
    .company-header {
        background: #fff;
        border-radius: 12px;
        padding: 28px 32px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }

    .company-avatar {
        width: 64px;
        height: 64px;
        border-radius: 14px;
        background: #1A4D3A;
        color: #fff;
        font-family: 'Lato', sans-serif;
        font-size: 22px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        letter-spacing: .5px;
    }

    .company-header-info { flex: 1; min-width: 0; }

    .company-header-top {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }

    .company-name {
        font-family: 'Lato', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A4D3A;
        margin: 0;
    }

    .company-nip {
        font-size: 13px;
        color: #7a8a80;
        font-family: 'Manrope', sans-serif;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        font-family: 'Manrope', sans-serif;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .status-badge.pending  { background: #FFF3E0; color: #E65100; }
    .status-badge.active   { background: #E8F5E9; color: #2E7D32; }
    .status-badge.inactive { background: #F5F5F5; color: #757575; }

    .company-header-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 13px;
        color: #5a6a60;
        font-family: 'Manrope', sans-serif;
    }

    .company-header-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .company-header-meta i { font-size: 15px; color: #8a9a90; }

    .company-header-actions { display: flex; gap: 10px; flex-shrink: 0; }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 700;
        font-family: 'Manrope', sans-serif;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s, transform .1s;
        border: none;
    }

    .btn-action:active { transform: translateY(1px); }

    .btn-primary-action { background: #1A4D3A; color: #F5F0E8; }
    .btn-primary-action:hover { background: #153d2e; }

        .btn-accept-action { background: #EF6C00; color: #fff; }
        .btn-accept-action:hover { background: #d95f00; }

    .btn-secondary-action { background: #F4F1EA; color: #1A4D3A; border: 1px solid #D0CCC0; }
    .btn-secondary-action:hover { background: #EAE6DC; }

    /* ── STATS BAR ── */
    .stats-bar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }

    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .stat-icon.green  { background: #E8F5E9; color: #2E7D32; }
    .stat-icon.orange { background: #FFF3E0; color: #E65100; }
    .stat-icon.blue   { background: #E3F2FD; color: #1565C0; }

    .stat-value {
        font-family: 'Lato', sans-serif;
        font-size: 26px;
        font-weight: 700;
        color: #1A4D3A;
        line-height: 1;
        margin-bottom: 3px;
    }

    .stat-label {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        color: #7a8a80;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    /* ── TABS ── */
    .tabs-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        overflow: hidden;
    }

    .tabs-nav {
        display: flex;
        border-bottom: 1px solid #E5E1D8;
        padding: 0 4px;
        overflow-x: auto;
    }

    .tab-btn {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 14px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #7a8a80;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        white-space: nowrap;
        transition: color .15s, border-color .15s;
    }

    .tab-btn:hover { color: #1A4D3A; }
    .tab-btn.active { color: #1A4D3A; border-bottom-color: #1A4D3A; }
    .tab-btn i { font-size: 16px; }

    .tab-badge {
        background: #E8F5E9;
        color: #2E7D32;
        border-radius: 10px;
        padding: 1px 6px;
        font-size: 11px;
        font-weight: 700;
    }

    .tab-panel { display: none; padding: 28px 32px; }
    .tab-panel.active { display: block; }

    /* ── OVERVIEW ── */
    .overview-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .card-section {
        background: #FAFAF8;
        border-radius: 10px;
        padding: 20px 24px;
        border: 1px solid #E5E1D8;
    }

    .card-section h3 {
        font-family: 'Lato', sans-serif;
        font-size: 13px;
        font-weight: 700;
        color: #7a8a80;
        text-transform: uppercase;
        letter-spacing: .6px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #EEE9E0;
        font-size: 14px;
        font-family: 'Manrope', sans-serif;
    }

    .info-row:last-child { border-bottom: none; }

    .info-row i { font-size: 16px; color: #1A4D3A; margin-top: 1px; flex-shrink: 0; }

    .info-label { color: #7a8a80; font-size: 12px; font-weight: 600; min-width: 80px; }
    .info-value { color: #1e1e1e; font-weight: 500; }
    .info-value.empty { color: #bbb; font-style: italic; }

    /* ── ACTIVITY ── */
    .activity-list { display: flex; flex-direction: column; gap: 10px; }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #E5E1D8;
        font-size: 13px;
        font-family: 'Manrope', sans-serif;
    }

    .activity-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #E8F5E9;
        color: #2E7D32;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .activity-text { flex: 1; color: #3a3a3a; line-height: 1.4; }
    .activity-time { font-size: 11px; color: #aaa; white-space: nowrap; }

    .empty-tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 64px 24px;
        color: #b0b8b4;
        text-align: center;
    }

    .empty-tab i { font-size: 40px; margin-bottom: 12px; }
    .empty-tab p { font-family: 'Manrope', sans-serif; font-size: 14px; }

    /* ── AUDITS TABLE ── */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
    }

    .data-table th {
        text-align: left;
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 700;
        color: #7a8a80;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: 2px solid #E5E1D8;
    }

    .data-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #F0ECE4;
        color: #2a2a2a;
        vertical-align: middle;
    }

    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: #FAFAF8; }

    .audit-status {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .audit-status.requested   { background: #E3F2FD; color: #1565C0; }
    .audit-status.offer_sent  { background: #FFF3E0; color: #E65100; }
    .audit-status.offer_accepted { background: #EDE7F6; color: #4527A0; }
    .audit-status.in_progress { background: #E8F5E9; color: #2E7D32; }
    .audit-status.review      { background: #FFF9C4; color: #F57F17; }
    .audit-status.completed   { background: #E8F5E9; color: #1B5E20; }
    .audit-status.archived    { background: #F5F5F5; color: #757575; }

    /* ── USERS ── */
    .user-list { display: flex; flex-direction: column; gap: 10px; }

    .user-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 10px;
        border: 1px solid #E5E1D8;
        background: #FAFAF8;
        font-family: 'Manrope', sans-serif;
    }

    .user-item-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #1A4D3A;
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .user-item-name { font-size: 14px; font-weight: 600; color: #1e1e1e; }
    .user-item-email { font-size: 12px; color: #7a8a80; }
    .user-item-badge { margin-left: auto; }

    .role-badge {
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        background: #E8F5E9;
        color: #2E7D32;
        font-family: 'Manrope', sans-serif;
    }

    .role-badge.admin { background: #1A4D3A; color: #fff; }
</style>
@endpush

@section('content')
{{-- ─── BREADCRUMB ─── --}}
<div style="margin-bottom:16px;font-family:'Manrope',sans-serif;font-size:13px;color:#7a8a80;display:flex;align-items:center;gap:6px;">
    <a href="{{ route('dashboard') }}" style="color:#1A4D3A;text-decoration:none;font-weight:600;">Dashboard</a>
    <i class="ti ti-chevron-right" style="font-size:12px;"></i>
    <span>{{ $company->name }}</span>
</div>

{{-- ─── HEADER ─── --}}
<div class="company-header">
    <div class="company-avatar">
        {{ strtoupper(substr($company->name, 0, 2)) }}
    </div>

    <div class="company-header-info">
        <div class="company-header-top">
            <h1 class="company-name">{{ $company->name }}</h1>
            <span class="company-nip">NIP: {{ $company->nip ?? '—' }}</span>
            <span class="status-badge {{ $company->status }}">
                <i class="ti ti-circle-filled" style="font-size:8px;"></i>
                @if($company->status === 'active') Aktywny
                @elseif($company->status === 'pending') Oczekujący
                @else Nieaktywny
                @endif
            </span>
        </div>
        <div class="company-header-meta">
            @if($company->address || $company->city)
                <span><i class="ti ti-map-pin"></i>{{ trim(($company->address ?? '') . ' ' . ($company->city ?? '')) }}</span>
            @endif
            @if($company->email)
                <span><i class="ti ti-mail"></i>{{ $company->email }}</span>
            @endif
            @if($company->phone)
                <span><i class="ti ti-phone"></i>{{ $company->phone }}</span>
            @endif
        </div>
    </div>

    <div class="company-header-actions">
        <a href="#" class="btn-action {{ $company->status === 'active' ? 'btn-primary-action' : 'btn-secondary-action' }}">
            <i class="ti ti-edit"></i> Edytuj
        </a>

        @if($company->status === 'pending')
            <form method="POST" action="{{ route('companies.accept', $company) }}" style="display:inline-block;">
                @csrf
                <button type="submit" class="btn-action btn-accept-action">
                    <i class="ti ti-check"></i> Akceptuj klienta
                </button>
            </form>
        @elseif($company->status === 'active')
            <span class="status-badge active-inline">
                <i class="ti ti-circle-filled" style="font-size:8px;"></i>
                Aktywny
            </span>
        @endif
    </div>
</div>

{{-- ─── STATS BAR ─── --}}
<div class="stats-bar">
    <div class="stat-card">
        <div class="stat-icon green"><i class="ti ti-clipboard-list"></i></div>
        <div>
            <div class="stat-value">{{ $stats['audits_count'] }}</div>
            <div class="stat-label">Audyty</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="ti ti-file-invoice"></i></div>
        <div>
            <div class="stat-value">{{ $stats['offers_count'] }}</div>
            <div class="stat-label">Oferty</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="ti ti-users"></i></div>
        <div>
            <div class="stat-value">{{ $stats['users_count'] }}</div>
            <div class="stat-label">Użytkownicy</div>
        </div>
    </div>
</div>

{{-- ─── TABS ─── --}}
<div class="tabs-wrap">
    <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('overview', this)">
            <i class="ti ti-layout-grid"></i> Przegląd
        </button>
        <button class="tab-btn" onclick="switchTab('audits', this)">
            <i class="ti ti-clipboard-list"></i> Audyty
            @if($stats['audits_count'] > 0)
                <span class="tab-badge">{{ $stats['audits_count'] }}</span>
            @endif
        </button>
        <button class="tab-btn" onclick="switchTab('offers', this)">
            <i class="ti ti-file-invoice"></i> Oferty
            @if($stats['offers_count'] > 0)
                <span class="tab-badge">{{ $stats['offers_count'] }}</span>
            @endif
        </button>
        <button class="tab-btn" onclick="switchTab('users', this)">
            <i class="ti ti-users"></i> Użytkownicy
            @if($stats['users_count'] > 0)
                <span class="tab-badge">{{ $stats['users_count'] }}</span>
            @endif
        </button>
        <button class="tab-btn" onclick="switchTab('chat', this)">
            <i class="ti ti-message-circle"></i> Chat
        </button>
        <button class="tab-btn" onclick="switchTab('documents', this)">
            <i class="ti ti-paperclip"></i> Dokumenty
        </button>
    </div>

    {{-- ═══ ZAKŁADKA: PRZEGLĄD ═══ --}}
    <div id="tab-overview" class="tab-panel active">
        <div class="overview-grid">
            {{-- Dane firmy --}}
            <div class="card-section">
                <h3><i class="ti ti-building"></i> Dane firmy</h3>
                <div class="info-row">
                    <i class="ti ti-id-badge"></i>
                    <div>
                        <div class="info-label">NIP</div>
                        <div class="info-value {{ $company->nip ? '' : 'empty' }}">
                            {{ $company->nip ?? 'Nie podano' }}
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <i class="ti ti-map-pin"></i>
                    <div>
                        <div class="info-label">Adres</div>
                        <div class="info-value {{ $company->address ? '' : 'empty' }}">
                            {{ $company->address ?? 'Nie podano' }}
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <i class="ti ti-building-community"></i>
                    <div>
                        <div class="info-label">Miasto</div>
                        <div class="info-value {{ $company->city ? '' : 'empty' }}">
                            {{ $company->city ?? 'Nie podano' }}
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <i class="ti ti-mail"></i>
                    <div>
                        <div class="info-label">E-mail</div>
                        <div class="info-value {{ $company->email ? '' : 'empty' }}">
                            {{ $company->email ?? 'Nie podano' }}
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <i class="ti ti-phone"></i>
                    <div>
                        <div class="info-label">Telefon</div>
                        <div class="info-value {{ $company->phone ? '' : 'empty' }}">
                            {{ $company->phone ?? 'Nie podano' }}
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <i class="ti ti-calendar"></i>
                    <div>
                        <div class="info-label">Dodano</div>
                        <div class="info-value">
                            {{ $company->created_at->format('d.m.Y') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ostatnia aktywność --}}
            <div class="card-section">
                <h3><i class="ti ti-activity"></i> Ostatnia aktywność</h3>
                <div class="activity-list">
                    @forelse($company->audits->sortByDesc('created_at')->take(3) as $audit)
                        <div class="activity-item">
                            <div class="activity-icon"><i class="ti ti-clipboard-list"></i></div>
                            <div class="activity-text">
                                Audyt <strong>{{ $audit->auditType->name ?? 'bez typu' }}</strong>
                                — status: <strong>{{ $audit->status }}</strong>
                            </div>
                            <div class="activity-time">{{ $audit->created_at->diffForHumans() }}</div>
                        </div>
                    @empty
                        @forelse($company->offers->sortByDesc('created_at')->take(3) as $offer)
                            <div class="activity-item">
                                <div class="activity-icon" style="background:#FFF3E0;color:#E65100;"><i class="ti ti-file-invoice"></i></div>
                                <div class="activity-text">
                                    Oferta <strong>#{{ $offer->id }}</strong>
                                    — {{ number_format($offer->total_price ?? 0, 2) }} zł
                                </div>
                                <div class="activity-time">{{ $offer->created_at->diffForHumans() }}</div>
                            </div>
                        @empty
                            <div class="empty-tab" style="padding:32px 0;">
                                <i class="ti ti-clock-off" style="font-size:32px;margin-bottom:8px;"></i>
                                <p>Brak aktywności dla tej firmy.</p>
                            </div>
                        @endforelse
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ ZAKŁADKA: AUDYTY ═══ --}}
    <div id="tab-audits" class="tab-panel">
        @if($company->audits->isEmpty())
            <div class="empty-tab">
                <i class="ti ti-clipboard-list"></i>
                <p>Brak audytów dla tej firmy.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Typ audytu</th>
                        <th>Status</th>
                        <th>Postęp</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($company->audits as $audit)
                        <tr>
                            <td style="color:#888;font-size:12px;">{{ $audit->id }}</td>
                            <td style="font-weight:600;">{{ $audit->auditType->name ?? '—' }}</td>
                            <td>
                                <span class="audit-status {{ $audit->status }}">{{ $audit->status }}</span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="flex:1;height:6px;background:#E5E1D8;border-radius:3px;overflow:hidden;">
                                        <div style="height:100%;background:#2E7D32;border-radius:3px;width:{{ $audit->progress ?? 0 }}%"></div>
                                    </div>
                                    <span style="font-size:12px;color:#7a8a80;min-width:32px;">{{ $audit->progress ?? 0 }}%</span>
                                </div>
                            </td>
                            <td style="color:#7a8a80;font-size:12px;">{{ $audit->created_at->format('d.m.Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ═══ ZAKŁADKA: OFERTY ═══ --}}
    <div id="tab-offers" class="tab-panel">
        @if($company->offers->isEmpty())
            <div class="empty-tab">
                <i class="ti ti-file-invoice"></i>
                <p>Brak ofert dla tej firmy.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Wersja</th>
                        <th>Cena netto</th>
                        <th>Koszt dojazdu</th>
                        <th>Łącznie</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($company->offers as $offer)
                        <tr>
                            <td style="color:#888;font-size:12px;">{{ $offer->id }}</td>
                            <td>v{{ $offer->version ?? 1 }}</td>
                            <td>{{ number_format($offer->audit_price ?? 0, 2) }} zł</td>
                            <td>{{ number_format($offer->travel_cost ?? 0, 2) }} zł</td>
                            <td style="font-weight:700;color:#1A4D3A;">{{ number_format($offer->total_price ?? 0, 2) }} zł</td>
                            <td>
                                <span class="audit-status {{ $offer->status }}">{{ $offer->status }}</span>
                            </td>
                            <td style="color:#7a8a80;font-size:12px;">{{ $offer->created_at->format('d.m.Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ═══ ZAKŁADKA: UŻYTKOWNICY ═══ --}}
    <div id="tab-users" class="tab-panel">
        @if($company->users->isEmpty())
            <div class="empty-tab">
                <i class="ti ti-users"></i>
                <p>Brak powiązanych użytkowników.</p>
            </div>
        @else
            <div class="user-list">
                @foreach($company->users as $user)
                    <div class="user-item">
                        <div class="user-item-avatar">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div>
                            <div class="user-item-name">{{ $user->name }}</div>
                            <div class="user-item-email">{{ $user->email }}</div>
                        </div>
                        <div class="user-item-badge">
                            @foreach($user->getRoleNames() as $role)
                                <span class="role-badge {{ in_array($role, ['admin', 'superadmin']) ? 'admin' : '' }}">
                                    {{ $role }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ ZAKŁADKA: CHAT ═══ --}}
    <div id="tab-chat" class="tab-panel">
        <div class="empty-tab">
            <i class="ti ti-message-circle"></i>
            <p>Moduł czatu jest w przygotowaniu.</p>
        </div>
    </div>

    {{-- ═══ ZAKŁADKA: DOKUMENTY ═══ --}}
    <div id="tab-documents" class="tab-panel">
        <div class="empty-tab">
            <i class="ti ti-paperclip"></i>
            <p>Moduł dokumentów jest w przygotowaniu.</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }
</script>
@endpush
