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
    .status-badge.archived { background: #FDECEA; color: #C62828; }

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

    .btn-delete-action { background: #C62828; color: #fff; }
    .btn-delete-action:hover { background: #a91f1f; }

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

    .role-badge.user { background: #F5F5F5; color: #757575; }

    .online-state {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        font-family: 'Manrope', sans-serif;
        color: #757575;
        white-space: nowrap;
    }

    .online-state .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #BDBDBD;
    }

    .online-state.online { color: #2E7D32; }
    .online-state.online .dot { background: #2E7D32; }

    .company-users-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .btn-add-user {
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        padding: 10px 16px;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: background .15s, transform .1s;
    }

    .btn-add-user:hover { background: #153d2e; }

    .user-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .user-action-btn {
        border: 1px solid #D0CCC0;
        background: #F4F1EA;
        color: #1A4D3A;
        padding: 6px 10px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 700;
        font-family: 'Manrope', sans-serif;
        cursor: pointer;
    }

    .user-action-btn.delete {
        background: #fff0f0;
        color: #C62828;
        border-color: #f0c7c7;
    }

    .user-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .user-modal {
        background: #fff;
        border-radius: 14px;
        width: 100%;
        max-width: 520px;
        padding: 30px;
        box-shadow: 0 18px 50px rgba(0,0,0,.22);
        max-height: 90vh;
        overflow-y: auto;
    }

    .user-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 18px;
    }

    .user-modal-header h2 {
        font-family: 'Lato', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A4D3A;
    }

    .user-modal-header p {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        color: #5a6a60;
        margin-top: 4px;
    }

    .modal-close-btn {
        border: none;
        background: transparent;
        font-size: 22px;
        color: #888;
        cursor: pointer;
        line-height: 1;
    }

    .modal-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }

    .modal-field { margin-bottom: 14px; }

    .modal-field label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #3a3a3a;
        margin-bottom: 5px;
        font-family: 'Manrope', sans-serif;
    }

    .modal-field input,
    .modal-field select {
        width: 100%;
        background: #FAFAF6;
        border: 1px solid #D0CCC0;
        border-radius: 8px;
        padding: 11px 12px;
        font-size: 14px;
        font-family: 'Lato', sans-serif;
        outline: none;
    }

    .role-choice {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 8px;
    }

    .role-choice label {
        border: 1px solid #D0CCC0;
        border-radius: 10px;
        padding: 12px;
        background: #FAFAF6;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        color: #2a2a2a;
        font-weight: 600;
    }

    .role-choice input { margin-top: 2px; }

    .modal-submit {
        width: 100%;
        margin-top: 8px;
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        padding: 12px 16px;
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
    }

    .modal-submit:hover { background: #153d2e; }

    @media (max-width: 768px) {
        .modal-grid { grid-template-columns: 1fr; }
        .role-choice { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
{{-- ─── FLASH ALERTS ─── --}}
@if(session('success'))
    <div style="display:flex;align-items:center;gap:10px;background:#E8F5E9;border-left:4px solid #2E7D32;padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:16px;font-size:14px;color:#1B5E20;font-family:'Manrope',sans-serif;">
        <i class="ti ti-circle-check" style="font-size:20px;flex-shrink:0;"></i>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="display:flex;align-items:center;gap:10px;background:#FFEBEE;border-left:4px solid #C62828;padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:16px;font-size:14px;color:#B71C1C;font-family:'Manrope',sans-serif;">
        <i class="ti ti-alert-circle" style="font-size:20px;flex-shrink:0;"></i>
        {{ session('error') }}
    </div>
@endif
@if(session('can_force_assign'))
    <div style="display:flex;align-items:center;gap:14px;background:#FFF8E1;border-left:4px solid #F9A825;padding:14px 16px;border-radius:0 8px 8px 0;margin-bottom:16px;font-family:'Manrope',sans-serif;">
        <i class="ti ti-alert-triangle" style="font-size:22px;color:#F9A825;flex-shrink:0;"></i>
        <div style="flex:1;">
            <p style="margin:0 0 10px;font-size:14px;color:#5D4037;font-weight:600;">Użytkownik z tym adresem email już istnieje w systemie.</p>
            <form method="POST" action="{{ route('companies.users.assignExisting', $company) }}" style="display:inline;">
                @csrf
                <input type="hidden" name="email" value="{{ session('can_force_assign') }}">
                <button type="submit" style="background:#F9A825;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Manrope',sans-serif;">Przypisz istniejącego użytkownika do tej firmy</button>
            </form>
        </div>
    </div>
@endif

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
                @elseif($company->status === 'archived') Zarchiwizowany
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

        @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'superadmin']))
            <button type="button" class="btn-action btn-delete-action" onclick="openCompanyDeleteModal()">
                <i class="ti ti-trash"></i> Usuń firmę
            </button>
        @endif

        @if($company->status === 'pending')
            @if($company->users->count() > 0)
                <form method="POST" action="{{ route('companies.accept', $company) }}" style="display:inline-block;">
                    @csrf
                    <button type="submit" class="btn-action btn-accept-action">
                        <i class="ti ti-check"></i> Akceptuj klienta
                    </button>
                </form>
            @else
                <div style="display:inline-flex;align-items:center;gap:10px;background:#FFF3E0;color:#E65100;padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;">
                    <i class="ti ti-alert-triangle" style="font-size:18px;"></i>
                    Dodaj najpierw użytkownika głównego aby zaakceptować klienta
                </div>
            @endif
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
        <button class="tab-btn" id="tab-btn-requests" onclick="switchTab('requests', this)">
            <i class="ti ti-inbox"></i> Zapytania
            @if(isset($offerRequests) && $offerRequests->isNotEmpty())
                <span class="tab-badge" style="background:#FEE2E2;color:#DC2626;">{{ $offerRequests->count() }}</span>
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

    {{-- ═══ ZAKŁADKA: ZAPYTANIA ═══ --}}
    <div id="tab-requests" class="tab-panel">
        @if(!isset($offerRequests) || $offerRequests->isEmpty())
            <div class="empty-tab">
                <i class="ti ti-inbox"></i>
                <p>Brak zapytań od tej firmy.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Formularz</th>
                        <th>Odpowiedzi klienta</th>
                        <th>Oferty</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th style="text-align:right;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($offerRequests as $req)
                    @php
                        $statusLabel = match($req->status) {
                            'nowe'      => 'Nowe',
                            'w_toku'    => 'W toku',
                            'zamknięte' => 'Zamknięte',
                            default     => $req->status,
                        };
                        $statusStyle = match($req->status) {
                            'nowe'      => 'background:#DBEAFE;color:#1D4ED8;',
                            'w_toku'    => 'background:#FEF3C7;color:#92400E;',
                            'zamknięte' => 'background:#DCFCE7;color:#166534;',
                            default     => 'background:#F3F4F6;color:#4B5563;',
                        };
                        $responses = $req->form_responses ?? [];
                        $fields    = $req->offerFormTemplate?->fields ?? [];
                        $offers    = $req->offers;
                    @endphp
                    <tr>
                        <td style="color:#888;font-size:12px;">{{ $req->id }}</td>
                        <td style="font-weight:600;">{{ $req->offerFormTemplate?->name ?? 'Zapytanie ogólne' }}</td>
                        <td>
                            @if(!empty($responses) && !empty($fields))
                                @foreach($fields as $field)
                                    @php $val = $responses[$field['key']] ?? null; @endphp
                                    @if($val)
                                        <div style="font-size:11px;margin-bottom:2px;">
                                            <span style="color:#888;">{{ $field['label'] }}:</span>
                                            <span style="color:#1A1A1A;font-weight:600;">{{ $val }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                <span style="color:#bbb;font-size:12px;">Brak odpowiedzi</span>
                            @endif
                        </td>
                        <td>
                            @if($offers->isNotEmpty())
                                @foreach($offers as $offer)
                                <div style="font-size:11px;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                                    <a href="{{ route('offers.edit', $offer) }}" style="color:#1A4D3A;text-decoration:none;font-weight:600;">
                                        {{ $offer->offer_full_number ?? $offer->offer_number }}
                                    </a>
                                    <span style="color:#888;font-size:10px;">
                                        @php
                                            $offerStatusLabel = match($offer->status) {
                                                'w_toku'         => 'W toku',
                                                'wygrana'        => 'Wygrana',
                                                'przegrana'      => 'Przegrana',
                                                'zarchiwizowana' => 'Zarchiwizowana',
                                                default          => $offer->status,
                                            };
                                            $offerStatusBg = match($offer->status) {
                                                'wygrana'        => 'background:#DCFCE7;color:#166534;',
                                                'przegrana'      => 'background:#FEE2E2;color:#B91C1C;',
                                                'zarchiwizowana' => 'background:#F3F4F6;color:#4B5563;',
                                                default          => 'background:#DBEAFE;color:#1D4ED8;',
                                            };
                                        @endphp
                                        <span style="display:inline-block;padding:1px 6px;border-radius:12px;font-size:10px;font-weight:600;{{ $offerStatusBg }};">
                                            {{ $offerStatusLabel }}
                                        </span>
                                    </span>
                                </div>
                                @endforeach
                            @else
                                <span style="color:#bbb;font-size:12px;">—</span>
                            @endif
                        </td>
                        <td>
                            <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;{{ $statusStyle }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td style="color:#7a8a80;font-size:12px;">{{ $req->created_at->format('d.m.Y H:i') }}</td>
                        <td style="text-align:right;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                @if($offers->isEmpty())
                                <a href="{{ route('offers.create', ['company_id' => $company->id, 'offer_request_id' => $req->id]) }}"
                                   style="display:inline-flex;align-items:center;gap:4px;background:#1A4D3A;color:#F5F0E8;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:700;text-decoration:none;">
                                    <i class="ti ti-file-plus" style="font-size:12px;"></i> Zrób ofertę
                                </a>
                                @else
                                <a href="{{ route('offers.create', ['company_id' => $company->id, 'offer_request_id' => $req->id]) }}"
                                   style="display:inline-flex;align-items:center;gap:4px;background:#FEF3C7;color:#92400E;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:700;text-decoration:none;">
                                    <i class="ti ti-file-plus" style="font-size:12px;"></i> Nowa oferta
                                </a>
                                @endif
                                <button onclick="markRequestDone({{ $req->id }}, this)"
                                        style="display:inline-flex;align-items:center;gap:4px;background:#F3F4F6;color:#4B5563;border:none;border-radius:6px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;">
                                    <i class="ti ti-check" style="font-size:12px;"></i> Zamknij
                                </button>
                            </div>
                        </td>
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
                        <th>Numer oferty</th>
                        <th>Tytuł</th>
                        <th>Kwota netto</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th style="text-align:right;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($company->offers->where('is_template', false) as $offer)
                    @php
                        $statusStyle = match($offer->status) {
                            'w_toku'         => 'background:#DBEAFE;color:#1D4ED8;',
                            'wygrana'        => 'background:#DCFCE7;color:#166534;',
                            'przegrana'      => 'background:#FEE2E2;color:#B91C1C;',
                            'zarchiwizowana' => 'background:#F3F4F6;color:#4B5563;',
                            default          => 'background:#F3F4F6;color:#4B5563;',
                        };
                        $statusLabel = match($offer->status) {
                            'w_toku'         => 'W toku',
                            'wygrana'        => 'Wygrana',
                            'przegrana'      => 'Przegrana',
                            'zarchiwizowana' => 'Zarchiwizowana',
                            default          => $offer->status,
                        };
                    @endphp
                        <tr>
                            <td style="font-weight:700;font-family:'Lato',sans-serif;">{{ $offer->offer_full_number ?? $offer->offer_number }}</td>
                            <td style="color:#555;">{{ $offer->offer_title ?? '—' }}</td>
                            <td style="font-weight:700;color:#1A4D3A;">{{ number_format($offer->kwota_netto ?? 0, 2) }} zł</td>
                            <td>
                                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;font-family:'Manrope',sans-serif;{{ $statusStyle }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td style="color:#7a8a80;font-size:12px;">{{ $offer->created_at->format('d.m.Y') }}</td>
                            <td style="text-align:right;">
                                <a href="{{ route('offers.edit', $offer) }}" style="display:inline-flex;align-items:center;gap:4px;background:#1A4D3A;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:700;font-family:'Manrope',sans-serif;text-decoration:none;cursor:pointer;">
                                    <i class="ti ti-edit"></i> Edytuj
                                </a>
                                <a href="{{ route('offers.show', $offer) }}" style="display:inline-flex;align-items:center;gap:4px;background:#fff;color:#333;border:1px solid #D0CCC0;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;font-family:'Manrope',sans-serif;text-decoration:none;cursor:pointer;margin-left:4px;">
                                    <i class="ti ti-eye"></i> Podgląd
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ═══ ZAKŁADKA: UŻYTKOWNICY ═══ --}}
    <div id="tab-users" class="tab-panel">
        <div class="company-users-toolbar">
            <div style="font-family:'Manrope',sans-serif;font-size:13px;color:#7a8a80;">
                Zarządzaj użytkownikami przypisanymi do tej firmy.
            </div>
            <button type="button" class="btn-add-user" onclick="openUserModal()">
                <i class="ti ti-user-plus"></i> Dodaj użytkownika
            </button>
        </div>

        @if($company->users->isEmpty())
            <div class="empty-tab">
                <i class="ti ti-users"></i>
                <p>Brak powiązanych użytkowników.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Avatar</th>
                        <th>Imię i nazwisko</th>
                        <th>Email</th>
                        <th>Rola</th>
                        <th>Status</th>
                        <th style="text-align:right;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($company->users as $user)
                        @php
                            $isOnline = $user->is_active && $user->last_seen_at && $user->last_seen_at->greaterThan(now()->subMinutes(15));
                            $roleName = $user->hasRole('client_admin') ? 'Główny kontakt' : 'Użytkownik';
                            $roleClass = $user->hasRole('client_admin') ? 'admin' : 'user';
                        @endphp
                        <tr>
                            <td>
                                <div class="user-item-avatar" style="width:40px;height:40px;">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:700;color:#1e1e1e;">{{ $user->name }}</div>
                                <div style="font-size:12px;color:#7a8a80;">{{ $user->phone ?? 'Brak telefonu' }}</div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="role-badge {{ $roleClass }}">{{ $roleName }}</span>
                            </td>
                            <td>
                                <span class="online-state {{ $isOnline ? 'online' : '' }}">
                                    <span class="dot"></span>
                                    {{ $isOnline ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                            <td>
                                <div class="user-actions">
                                    <button type="button" class="user-action-btn" onclick="editUser({{ $user->id }}, '{{ $user->name }}', '{{ $user->email }}', '{{ $user->phone ?? '' }}', '{{ $user->hasRole('client_admin') ? 'client_admin' : 'client_user' }}')">Edytuj</button>
                                    <form method="POST" action="{{ route('companies.users.destroy', [$company, $user]) }}" style="display:inline;" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika z firmy? Konto użytkownika zostanie zachowane.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="user-action-btn delete">Usuń</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
</div>

{{-- ═══ MODAL USUWANIA / ARCHIWIZACJI FIRMY ═══ --}}
<div id="companyDeleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.58);z-index:10000;align-items:center;justify-content:center;padding:16px;" onclick="closeCompanyDeleteModalOutside(event)">
    <div style="background:#fff;border-radius:14px;max-width:520px;width:100%;padding:30px 28px;box-shadow:0 18px 50px rgba(0,0,0,.22);">
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px;">
            <div style="width:44px;height:44px;border-radius:12px;background:#FDECEA;color:#C62828;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ti ti-trash" style="font-size:22px;"></i>
            </div>
            <div>
                <h2 style="font-family:'Lato',sans-serif;font-size:20px;font-weight:700;color:#1e1e1e;margin:0 0 6px;">Usunąć firmę?</h2>
                <p style="font-family:'Manrope',sans-serif;font-size:14px;line-height:1.7;color:#5a6a60;margin:0;">
                    Czy na pewno chcesz usunąć tę firmę? Jeśli firma ma audyty lub oferty zostanie zarchiwizowana.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;">
            <button type="button" class="btn-action btn-secondary-action" onclick="closeCompanyDeleteModal()">Anuluj</button>
            <form method="POST" action="{{ route('companies.destroy', $company) }}" style="display:inline-block;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-action btn-delete-action">Potwierdź</button>
            </form>
        </div>
    </div>
</div>

{{-- ═══ MODAL DODAJ/EDYTUJ UŻYTKOWNIKA ═══ --}}
<div id="userModalOverlay" class="user-modal-overlay" onclick="closeUserModalOutside(event)">
    <div class="user-modal">
        <div class="user-modal-header">
            <div>
                <h2 id="userModalTitle">Dodaj użytkownika</h2>
                <p id="userModalSubtitle">Utwórz nowe konto powiązane z tą firmą.</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeUserModal()">&times;</button>
        </div>

        {{-- FORMULARZ DODAWANIA --}}
        <form id="userCreateForm" method="POST" action="{{ route('companies.users.store', $company) }}" style="display:none;">
            @csrf

            <div class="modal-grid">
                <div class="modal-field">
                    <label for="user-first-name">Imię</label>
                    <input id="user-first-name" type="text" name="first_name" required>
                </div>
                <div class="modal-field">
                    <label for="user-last-name">Nazwisko</label>
                    <input id="user-last-name" type="text" name="last_name" required>
                </div>
            </div>

            <div class="modal-grid">
                <div class="modal-field">
                    <label for="user-email">Email</label>
                    <input id="user-email" type="email" name="email" required>
                </div>
                <div class="modal-field">
                    <label for="user-phone">Telefon</label>
                    <input id="user-phone" type="text" name="phone">
                </div>
            </div>

            <div class="modal-field">
                <label>Rola</label>
                <div class="role-choice">
                    <label>
                        <input type="radio" name="role" value="client_admin" checked>
                        <span>Główny kontakt</span>
                    </label>
                    <label>
                        <input type="radio" name="role" value="client_user">
                        <span>Użytkownik firmy</span>
                    </label>
                </div>
            </div>

            <div class="modal-field">
                <label for="user-password">Hasło</label>
                <input id="user-password" type="password" name="password" required>
            </div>

            <button type="submit" class="modal-submit">Utwórz użytkownika</button>
        </form>

        {{-- FORMULARZ EDYCJI --}}
        <form id="userEditForm" method="POST" style="display:none;">
            @csrf
            @method('PUT')

            <input type="hidden" id="edit-user-id" name="user_id">

            @if($errors->any())
                <div style="background:#FFEBEE;border-left:4px solid #C62828;padding:12px 16px;border-radius:0 6px 6px 0;margin-bottom:16px;">
                    @foreach($errors->all() as $error)
                        <p style="margin:0;font-size:13px;color:#B71C1C;">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="modal-grid">
                <div class="modal-field">
                    <label for="edit-user-name">Imię i nazwisko</label>
                    <input id="edit-user-name" type="text" name="name" required>
                </div>
                <div class="modal-field">
                    <label for="edit-user-phone">Telefon</label>
                    <input id="edit-user-phone" type="text" name="phone">
                </div>
            </div>

            <div class="modal-field">
                <label for="edit-user-email">Email</label>
                <input id="edit-user-email" type="email" name="email" required>
            </div>

            <div class="modal-field">
                <label>Rola</label>
                <div class="role-choice">
                    <label>
                        <input type="radio" id="edit-role-admin" name="role" value="client_admin">
                        <span>Główny kontakt</span>
                    </label>
                    <label>
                        <input type="radio" id="edit-role-user" name="role" value="client_user">
                        <span>Użytkownik firmy</span>
                    </label>
                </div>
            </div>

            <div class="modal-field">
                <label for="edit-user-password">Nowe hasło <span style="color:#999;font-size:12px;">(opcjonalnie, zostaw puste aby nie zmieniać)</span></label>
                <input id="edit-user-password" type="password" name="password">
            </div>

            <button type="submit" class="modal-submit">Zapisz zmiany</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if($errors->any())
<script>
document.addEventListener("DOMContentLoaded", function(){ openUserModal(); });
</script>
@endif
<script>
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // Auto-open Zapytania tab when URL has #zapytania
    if (window.location.hash === '#zapytania') {
        const btn = document.getElementById('tab-btn-requests');
        if (btn) switchTab('requests', btn);
    }

    function markRequestDone(id, btn) {
        if (!confirm('Zamknąć to zapytanie?')) return;
        fetch('/offer-requests/' + id + '/status', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ status: 'zamknięte' })
        }).then(r => {
            if (r.ok) location.reload();
        });
    }

    function openCompanyDeleteModal() {
        document.getElementById('companyDeleteModal').style.display = 'flex';
    }

    function closeCompanyDeleteModal() {
        document.getElementById('companyDeleteModal').style.display = 'none';
    }

    function closeCompanyDeleteModalOutside(event) {
        if (event.target.id === 'companyDeleteModal') {
            closeCompanyDeleteModal();
        }
    }

    function openUserModal() {
        document.getElementById('userModalOverlay').style.display = 'flex';
    }

    function closeUserModal() {
        document.getElementById('userModalOverlay').style.display = 'none';
        // Reset to create form
        document.getElementById('userCreateForm').style.display = 'block';
        document.getElementById('userEditForm').style.display = 'none';
        document.getElementById('userCreateForm').reset();
    }

    function closeUserModalOutside(event) {
        if (event.target.id === 'userModalOverlay') {
            closeUserModal();
        }
    }

    function editUser(userId, userName, userEmail, userPhone, userRole) {
        // Show edit form, hide create form
        document.getElementById('userCreateForm').style.display = 'none';
        document.getElementById('userEditForm').style.display = 'block';
        
        // Update modal title
        document.getElementById('userModalTitle').textContent = 'Edytuj użytkownika';
        document.getElementById('userModalSubtitle').textContent = 'Zmień dane użytkownika.';
        
        // Populate form fields
        document.getElementById('edit-user-id').value = userId;
        document.getElementById('edit-user-name').value = userName;
        document.getElementById('edit-user-email').value = userEmail;
        document.getElementById('edit-user-phone').value = userPhone;
        
        // Set role radio button
        if (userRole === 'client_admin') {
            document.getElementById('edit-role-admin').checked = true;
        } else {
            document.getElementById('edit-role-user').checked = true;
        }
        
        // Update form action to point to update route
        document.getElementById('userEditForm').action = '/companies/{{ $company->id }}/users/' + userId;
        
        // Open modal
        openUserModal();
    }
</script>
@endpush
