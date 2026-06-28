@extends('layouts.app')

@section('page-title', 'Ustawienia — Użytkownicy')

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
    .settings-tab:hover { color: #1A4D3A; }
    .settings-tab.active { color: #1A4D3A; border-bottom-color: #1A4D3A; }

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
        justify-content: space-between;
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

    /* ── Table ────────────────────────────── */
    .users-table { width: 100%; border-collapse: collapse; }
    .users-table th {
        padding: 11px 16px;
        text-align: left;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #888;
        background: #FAFAF6;
        border-bottom: 1px solid #F0EDE6;
    }
    .sort-indicator { font-size: 10px; color: #1A4D3A; margin-left: 2px; }
    .users-table td {
        padding: 14px 16px;
        font-size: 14px;
        color: #1A1A1A;
        border-bottom: 1px solid #F7F5F0;
        vertical-align: middle;
    }
    .users-table tr:last-child td { border-bottom: none; }
    .users-table tr:hover td { background: #FAFAF6; }

    /* ── Avatar ───────────────────────────── */
    .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #1A4D3A;
        color: #F5F0E8;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .user-cell { display: flex; align-items: center; gap: 12px; }
    .user-name { font-weight: 600; font-size: 14px; }
    .user-email { font-size: 12px; color: #888; }

    /* ── Role badges ──────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .badge-superadmin { background: #0d3b12; color: #A8D5B5; }
    .badge-admin      { background: rgba(26,77,58,0.12); color: #1A4D3A; }
    .badge-auditor-senior { background: rgba(100,60,180,0.10); color: #6433A0; }
    .badge-auditor    { background: rgba(30,80,150,0.10); color: #1E5096; }

    /* ── Online status ────────────────────── */
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .dot-online  { background: #22c55e; }
    .dot-offline { background: #d1d5db; }

    /* ── Action buttons ───────────────────── */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 7px;
        border: 1px solid #E5E1D8;
        background: transparent;
        color: #5a6a60;
        cursor: pointer;
        transition: background .15s, color .15s;
        text-decoration: none;
    }
    .btn-action:hover { background: #F4F1EA; color: #1A4D3A; }
    .btn-action.danger:hover { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .btn-action i { font-size: 16px; }
    .btn-action.success:hover { background: #e8f5e9; color: #1B5E20; border-color: #a5d6a7; }

    /* ── Add button ───────────────────────── */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-primary:hover { background: #153d2e; }

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

    /* ── Pagination ───────────────────────── */
    .pagination-wrap { padding: 16px 24px; border-top: 1px solid #F0EDE6; }

    /* ── Modal ────────────────────────────── */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9000; align-items: center; justify-content: center; pointer-events: none; }
    .modal-overlay.open { display: flex; pointer-events: auto; }
    .modal-box { background: #fff; border-radius: 14px; padding: 36px; max-width: 500px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; pointer-events: auto; }
    .modal-close-btn { position: absolute; top: 14px; right: 18px; background: none; border: none; font-size: 20px; color: #aaa; cursor: pointer; line-height: 1; }
    .modal-close-btn:hover { color: #333; }
    .modal-title { font-family: 'Manrope', sans-serif; font-size: 18px; font-weight: 700; color: #1A4D3A; margin-bottom: 6px; }
    .modal-subtitle { font-size: 13px; color: #888; margin-bottom: 24px; }
    .mf-group { margin-bottom: 14px; }
    .mf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .mf-label { display: block; font-size: 12px; font-weight: 700; color: #3a3a3a; margin-bottom: 4px; }
    .mf-input { width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 6px; padding: 9px 12px; font-size: 14px; font-family: 'Lato', sans-serif; outline: none; transition: border-color .15s; }
    .mf-input:focus { border-color: #2E7D32; background: #fff; }
    .mf-select { width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 6px; padding: 9px 12px; font-size: 14px; font-family: 'Lato', sans-serif; outline: none; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }
    .mf-select:focus { border-color: #2E7D32; }
    .modal-divider { border: none; border-top: 1px solid #E5E1D8; margin: 20px 0; }
    .btn-modal-submit { width: 100%; background: #1A4D3A; color: #F5F0E8; border: none; border-radius: 8px; padding: 12px; font-family: 'Manrope', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .15s; }
    .btn-modal-submit:hover { background: #153d2e; }

    /* ── Edit modal ───────────────────────── */
    .modal-overlay-edit { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9000; align-items: center; justify-content: center; pointer-events: none; }
    .modal-overlay-edit.open { display: flex; pointer-events: auto; }
</style>
@endpush

@section('content')

{{-- Zakładki --}}
<div class="settings-tabs">
    <a href="{{ route('settings.users.index') }}" class="settings-tab {{ !request()->has('tab') ? 'active' : '' }}">
        <i class="ti ti-users" style="margin-right:6px;"></i>Wszyscy użytkownicy
    </a>
    <a href="{{ route('settings.company') }}" class="settings-tab">
        <i class="ti ti-building" style="margin-right:6px;"></i>Dane firmy
    </a>
    <a href="#" class="settings-tab">
        <i class="ti ti-shield-lock" style="margin-right:6px;"></i>Role
    </a>
    <a href="{{ route('settings.users.index') }}?tab=archiwum" class="settings-tab {{ request('tab') === 'archiwum' ? 'active' : '' }}">
        <i class="ti ti-archive" style="margin-right:6px;"></i>Archiwum
        @if(($archivedStaff->count() + $archivedClients->count()) > 0)
            <span style="background:#C62828;color:#fff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;margin-left:4px;">{{ $archivedStaff->count() + $archivedClients->count() }}</span>
        @endif
    </a>
</div>

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

@if(request('tab') === 'archiwum')
{{-- ══════ ZAKŁADKA: ARCHIWUM ══════ --}}
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-header-title">Zarchiwizowani pracownicy ENESA</div>
            <div class="card-header-sub">{{ $archivedStaff->count() }} usuniętych kont</div>
        </div>
    </div>

    @if($archivedStaff->isEmpty())
        <div style="text-align:center;padding:40px;color:#888;">
            <i class="ti ti-archive" style="font-size:32px;display:block;margin-bottom:8px;"></i>
            Brak zarchiwizowanych pracowników ENESA
        </div>
    @else
        <table class="users-table" id="archivedStaffTable">
            <thead>
                <tr>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(0, 'archivedStaffTable')">Użytkownik <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(1, 'archivedStaffTable')">Rola <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(2, 'archivedStaffTable')">Data usunięcia <span class="sort-indicator"></span></th>
                    <th style="text-align:right;width:200px;">Akcja</th>
                </tr>
            </thead>
            <tbody>
                @foreach($archivedStaff as $archivedUser)
                    @php
                        $initials = collect(explode(' ', $archivedUser->name))
                            ->take(2)->map(fn($w) => strtoupper(substr($w,0,1)))->implode('');
                        $role = $archivedUser->roles->first()?->name ?? '—';
                    @endphp
                    <tr data-sort-name="{{ strtolower($archivedUser->name) }}" data-sort-role="{{ $role }}" data-sort-date="{{ $archivedUser->deleted_at->format('Y-m-d') }}">
                        <td>
                            <div class="user-cell">
                                <div class="avatar" style="background:#9e9e9e;">{{ $initials }}</div>
                                <div>
                                    <div class="user-name" style="color:#888;">{{ $archivedUser->name }}</div>
                                    <div class="user-email">{{ $archivedUser->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:#F4F1EA;color:#888;">{{ $role }}</span>
                        </td>
                        <td style="color:#888;font-size:13px;">
                            {{ $archivedUser->deleted_at->format('d.m.Y') }}
                        </td>
                        <td>
                            <div style="display:flex;justify-content:flex-end;gap:6px;">
                                <form method="POST" action="{{ route('settings.users.restore', $archivedUser) }}">
                                    @csrf
                                    <button type="submit" class="btn-action" title="Przywróć" style="width:auto;padding:6px 12px;gap:6px;">
                                        <i class="ti ti-restore"></i> Przywróć
                                    </button>
                                </form>
                                <button type="button" class="btn-action success" title="Przypisz do firmy" style="width:auto;padding:6px 12px;gap:6px;background:#1B5E20;color:#fff;border-color:#1B5E20;" data-user-id="{{ $archivedUser->id }}" data-user-name="{{ e($archivedUser->name) }}" data-user-email="{{ e($archivedUser->email) }}" onclick="openAssignToCompanyModal(this)">
                                    <i class="ti ti-building-plus"></i> Przypisz
                                </button>
                                @if(auth()->user()->hasRole('superadmin') || auth()->user()->hasRole('admin'))
                                <form method="POST" action="{{ route('settings.users.forceDestroy', $archivedUser) }}" onsubmit="return confirm('Czy na pewno chcesz trwale usunąć użytkownika {{ e($archivedUser->name) }}? Tej operacji nie można cofnąć.');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action danger" title="Usuń na stałe" style="width:auto;padding:6px 12px;gap:6px;background:#C62828;color:#fff;border-color:#C62828;">
                                        <i class="ti ti-trash"></i> Usuń
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div>
            <div class="card-header-title">Zarchiwizowani klienci</div>
            <div class="card-header-sub">{{ $archivedClients->count() }} usuniętych kont klientów</div>
        </div>
    </div>

    @if($archivedClients->isEmpty())
        <div style="text-align:center;padding:40px;color:#888;">
            <i class="ti ti-archive" style="font-size:32px;display:block;margin-bottom:8px;"></i>
            Brak zarchiwizowanych klientów
        </div>
    @else
        <table class="users-table" id="archivedClientsTable">
            <thead>
                <tr>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(0, 'archivedClientsTable')">Użytkownik <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(1, 'archivedClientsTable')">Rola <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(2, 'archivedClientsTable')">Firma <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortArchivedTable(3, 'archivedClientsTable')">Data usunięcia <span class="sort-indicator"></span></th>
                    <th style="text-align:right;width:200px;">Akcja</th>
                </tr>
            </thead>
            <tbody>
                @foreach($archivedClients as $archivedClient)
                    @php
                        $initials = collect(explode(' ', $archivedClient->name))
                            ->take(2)->map(fn($w) => strtoupper(substr($w,0,1)))->implode('');
                        $role = $archivedClient->roles->first()?->name ?? '—';
                        $companyNames = $archivedClient->companies->pluck('name')->implode(', ') ?: 'Brak danych';
                    @endphp
                    <tr data-sort-name="{{ strtolower($archivedClient->name) }}" data-sort-role="{{ $role }}" data-sort-company="{{ strtolower($companyNames) }}" data-sort-date="{{ $archivedClient->deleted_at->format('Y-m-d') }}">
                        <td>
                            <div class="user-cell">
                                <div class="avatar" style="background:#6b7a70;">{{ $initials }}</div>
                                <div>
                                    <div class="user-name">{{ $archivedClient->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:#F4F1EA;color:#888;">{{ $role }}</span>
                        </td>
                        <td style="color:#888;font-size:13px;">{{ $companyNames }}</td>
                        <td style="color:#888;font-size:13px;">{{ $archivedClient->deleted_at->format('d.m.Y') }}</td>
                        <td>
                            <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('settings.users.restore', $archivedClient) }}">
                                    @csrf
                                    <button type="submit" class="btn-action" title="Przywróć" style="width:auto;padding:6px 12px;gap:6px;">
                                        <i class="ti ti-restore"></i> Przywróć
                                    </button>
                                </form>
                                <button type="button" class="btn-action success" title="Przypisz do firmy" style="width:auto;padding:6px 12px;gap:6px;background:#1B5E20;color:#fff;border-color:#1B5E20;" data-user-id="{{ $archivedClient->id }}" data-user-name="{{ e($archivedClient->name) }}" data-user-email="{{ e($archivedClient->email) }}" onclick="openAssignToCompanyModal(this)">
                                    <i class="ti ti-building-plus"></i> Przypisz
                                </button>
                                @if(auth()->user()->hasRole('superadmin') || auth()->user()->hasRole('admin'))
                                <form method="POST" action="{{ route('settings.users.forceDestroy', $archivedClient) }}" onsubmit="return confirm('Czy na pewno chcesz trwale usunąć użytkownika {{ e($archivedClient->name) }}? Tej operacji nie można cofnąć.');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action danger" title="Usuń na stałe" style="width:auto;padding:6px 12px;gap:6px;background:#C62828;color:#fff;border-color:#C62828;">
                                        <i class="ti ti-trash"></i> Usuń
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div>
            <div class="card-header-title">Użytkownicy bez firmy</div>
            <div class="card-header-sub">{{ $orphanUsers->count() }} użytkowników z rolą client_admin / client_user</div>
        </div>
    </div>

    @if($orphanUsers->isEmpty())
        <div style="text-align:center;padding:40px;color:#888;">
            <i class="ti ti-user-off" style="font-size:32px;display:block;margin-bottom:8px;"></i>
            Brak użytkowników bez firmy
        </div>
    @else
        <table class="users-table">
            <thead>
                <tr>
                    <th>Użytkownik</th>
                    <th>Email</th>
                    <th>Data utworzenia</th>
                    <th style="text-align:right;width:260px;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orphanUsers as $orphanUser)
                    @php
                        $initials = collect(explode(' ', $orphanUser->name))
                            ->take(2)->map(fn($w) => strtoupper(substr($w,0,1)))->implode('');
                    @endphp
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="avatar" style="background:#6b7a70;">{{ $initials }}</div>
                                <div>
                                    <div class="user-name">{{ $orphanUser->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $orphanUser->email }}</td>
                        <td style="color:#888;font-size:13px;">{{ $orphanUser->created_at->format('d.m.Y') }}</td>
                        <td>
                            <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('settings.users.destroy', $orphanUser) }}" onsubmit="return confirm('Czy na pewno chcesz usunąć trwale tego użytkownika?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action danger" title="Usuń trwale" style="width:auto;padding:6px 12px;gap:6px;background:#C62828;color:#fff;border-color:#C62828;">
                                        <i class="ti ti-trash"></i> Usuń trwale
                                    </button>
                                </form>
                                <button type="button" class="btn-action success" title="Przypisz do firmy" style="width:auto;padding:6px 12px;gap:6px;background:#1B5E20;color:#fff;border-color:#1B5E20;" data-user-id="{{ $orphanUser->id }}" data-user-name="{{ e($orphanUser->name) }}" onclick="openAssignCompanyModal(this)">
                                    <i class="ti ti-building-plus"></i> Przypisz do firmy
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div id="assignCompanyModal" class="modal-overlay" onclick="closeModalOutside(event,'assignCompanyModal')">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeAssignCompanyModal()">&times;</button>
        <div class="modal-title"><i class="ti ti-building-plus" style="margin-right:8px;"></i>Przypisz do firmy</div>
        <div class="modal-subtitle" id="assignCompanySubtitle">Wybierz firmę dla użytkownika.</div>

        <form method="POST" id="assignCompanyForm" action="">
            @csrf
            <div class="mf-group">
                <label class="mf-label" for="assign_company_id">Firma</label>
                <select id="assign_company_id" name="company_id" class="mf-select mf-input" required>
                    <option value="">— wybierz firmę —</option>
                    @forelse($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @empty
                        <option value="">Brak dostępnych firm</option>
                    @endforelse
                </select>
            </div>

            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-check" style="margin-right:6px;"></i>Przypisz
            </button>
        </form>
    </div>
</div>

{{-- Modal: przypisz archiwalnego użytkownika do firmy --}}
<div id="assignToCompanyModal" class="modal-overlay" onclick="closeModalOutside(event,'assignToCompanyModal')">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeAssignToCompanyModal()">&times;</button>
        <div class="modal-title"><i class="ti ti-building-plus" style="margin-right:8px;"></i>Przypisz użytkownika do firmy</div>

        <div id="assignToCompanyUserInfo" style="background:#F4F1EA;border-radius:8px;padding:12px 14px;margin-bottom:20px;">
            <div id="assignToCompanyName" style="font-weight:700;font-size:14px;color:#1A1A1A;"></div>
            <div id="assignToCompanyEmail" style="font-size:12px;color:#888;margin-top:2px;"></div>
        </div>

        <form method="POST" id="assignToCompanyForm" action="">
            @csrf
            <div class="mf-group">
                <label class="mf-label" for="assign_to_company_id">Firma</label>
                <select id="assign_to_company_id" name="company_id" class="mf-select mf-input" required>
                    <option value="">— wybierz firmę —</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mf-group">
                <label class="mf-label">Rola</label>
                <div style="display:flex;gap:16px;padding-top:4px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="radio" name="role" value="client_admin" required> Główny kontakt
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="radio" name="role" value="client_user" required> Użytkownik firmy
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-check" style="margin-right:6px;"></i>Przypisz
            </button>
        </form>
    </div>
</div>

@else
{{-- Tabela wszystkich użytkowników --}}
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-header-title">Wszyscy użytkownicy w systemie</div>
            <div class="card-header-sub">{{ $allUsers->count() }} użytkowników</div>
        </div>
        @if(auth()->user()->hasRole('superadmin') || auth()->user()->hasRole('admin'))
            <button class="btn-primary" onclick="openAddModal()">
                <i class="ti ti-user-plus"></i> Dodaj użytkownika
            </button>
        @endif
    </div>

    @if($allUsers->isEmpty())
        <div style="text-align:center;padding:40px;color:#888;">
            <i class="ti ti-users" style="font-size:32px;display:block;margin-bottom:8px;"></i>
            Brak użytkowników w systemie
        </div>
    @else
        <table class="users-table" id="usersTable">
            <thead>
                <tr>
                    <th style="cursor:pointer;" onclick="sortTable(0)">Użytkownik <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortTable(1)">Email <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortTable(2)">Role <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortTable(3)">Firma <span class="sort-indicator"></span></th>
                    <th style="cursor:pointer;" onclick="sortTable(4)">Status <span class="sort-indicator"></span></th>
                    <th style="text-align:right;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allUsers as $user)
                    @php
                        $initials = collect(explode(' ', $user->name))
                            ->take(2)->map(fn($w) => strtoupper(substr($w,0,1)))->implode('');
                        $role = $user->roles->first()?->name ?? '—';
                        $isActive = !$user->deleted_at;
                        $companyNames = $user->companies->pluck('name')->implode(', ') ?: '—';
                        $currentUser = auth()->user();
                        $canDelete = false;
                        
                        if ($currentUser->hasRole('superadmin')) {
                            $canDelete = $role !== 'superadmin' && $isActive;
                        } elseif ($currentUser->hasRole('admin')) {
                            $canDelete = !in_array($role, ['superadmin', 'admin']) && $isActive;
                        }
                    @endphp
                    <tr data-user-id="{{ $user->id }}" data-sort-name="{{ strtolower($user->name) }}" data-sort-email="{{ strtolower($user->email) }}" data-sort-role="{{ $role }}" data-sort-company="{{ strtolower($companyNames) }}" data-sort-status="{{ $isActive ? 'aktywny' : 'usuniety' }}">
                        <td>
                            <div class="user-cell">
                                <div class="avatar" style="background:{{ $isActive ? '#1A4D3A' : '#999' }};">{{ $initials }}</div>
                                <div>
                                    <div class="user-name" style="color:{{ $isActive ? '#1A1A1A' : '#999' }};">{{ $user->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-family:monospace;font-size:12px;">{{ $user->email }}</td>
                        <td>
                            @if($role === 'superadmin')
                                <span class="badge badge-superadmin"><i class="ti ti-crown"></i> Super Admin</span>
                            @elseif($role === 'admin')
                                <span class="badge badge-admin"><i class="ti ti-shield"></i> Admin</span>
                            @elseif($role === 'auditor_senior')
                                <span class="badge badge-auditor-senior"><i class="ti ti-clipboard-check"></i> Audytor Senior</span>
                            @elseif($role === 'auditor')
                                <span class="badge badge-auditor"><i class="ti ti-clipboard-check"></i> Audytor</span>
                            @elseif($role === 'client_admin')
                                <span class="badge" style="background:rgba(13,59,18,0.15);color:#0D3B12;">Klient - Admin</span>
                            @elseif($role === 'client_user')
                                <span class="badge" style="background:rgba(13,59,18,0.10);color:#0D3B12;">Klient - User</span>
                            @else
                                <span class="badge" style="background:#F4F1EA;color:#888;">{{ $role }}</span>
                            @endif
                        </td>
                        <td style="color:#888;font-size:13px;">{{ $companyNames }}</td>
                        <td>
                            @if($isActive)
                                <span style="color:#166534;font-size:13px;">✓ Aktywny</span>
                            @else
                                <span style="color:#999;font-size:13px;">✗ Usunięty</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex; justify-content:flex-end; gap:6px;">
                                @if($canDelete)
                                    <button class="btn-action"
                                        title="Edytuj"
                                        onclick="openEditModal(
                                            {{ $user->id }},
                                            '{{ e($user->name) }}',
                                            '{{ e($user->email) }}',
                                            '{{ e($user->phone ?? '') }}',
                                            '{{ $role }}'
                                        )">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                    <form method="POST"
                                        action="{{ route('settings.users.destroy', $user) }}"
                                        onsubmit="return confirm('Czy na pewno chcesz usunąć użytkownika {{ e($user->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-action danger" title="Usuń">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size:11px;color:#A8D5B5;padding:0 4px;">chroniony</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; padding:40px; color:#888;">
                            <i class="ti ti-users" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                            Brak użytkowników
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>

@endif

{{-- ══════ MODAL DODAJ ══════ --}}
<div id="addModal" class="modal-overlay" onclick="closeModalOutside(event,'addModal')">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeAddModal()">&times;</button>
        <div class="modal-title"><i class="ti ti-user-plus" style="margin-right:8px;"></i>Nowy użytkownik</div>
        <div class="modal-subtitle">Wypełnij dane — hasło zostanie wygenerowane automatycznie.</div>

        @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#b91c1c;">
            <strong>Błędy formularza:</strong>
            <ul style="margin:4px 0 0 16px;padding:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('settings.users.store') }}">
            @csrf
            <div class="mf-row">
                <div class="mf-group">
                    <label class="mf-label" for="add_name">Imię i nazwisko *</label>
                    <input id="add_name" type="text" name="name" class="mf-input" placeholder="Jan Kowalski" required value="{{ old('name') }}" style="{{ $errors->has('name') ? 'border-color:#ef4444;' : '' }}">
                </div>
                <div class="mf-group">
                    <label class="mf-label" for="add_email">Adres e-mail *</label>
                    <input id="add_email" type="email" name="email" class="mf-input" placeholder="jan@enesa.pl" required value="{{ old('email') }}" style="{{ $errors->has('email') ? 'border-color:#ef4444;' : '' }}">
                </div>
            </div>
            <div class="mf-row">
                <div class="mf-group">
                    <label class="mf-label" for="add_phone">Telefon</label>
                    <input id="add_phone" type="tel" name="phone" class="mf-input" placeholder="+48 000 000 000" value="{{ old('phone') }}">
                </div>
                <div class="mf-group">
                    <label class="mf-label" for="add_role">Rola *</label>
                    <select id="add_role" name="role" class="mf-select mf-input" required style="{{ $errors->has('role') ? 'border-color:#ef4444;' : '' }}">
                        <option value="">— wybierz —</option>
                        <option value="admin"          {{ old('role') === 'admin'          ? 'selected' : '' }}>Administrator</option>
                        <option value="auditor_senior" {{ old('role') === 'auditor_senior' ? 'selected' : '' }}>Audytor Senior</option>
                        <option value="auditor"        {{ old('role') === 'auditor'        ? 'selected' : '' }}>Audytor</option>
                    </select>
                </div>
            </div>
            <div class="mf-group">
                <label class="mf-label" for="add_password">Hasło (opcjonalnie — auto-generowane jeśli puste)</label>
                <input id="add_password" type="password" name="password" class="mf-input" placeholder="min. 8 znaków">
            </div>

            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-user-plus" style="margin-right:6px;"></i>Utwórz użytkownika
            </button>
        </form>
    </div>
</div>

{{-- ══════ MODAL EDYTUJ ══════ --}}
<div id="editModal" class="modal-overlay-edit" onclick="closeModalOutside(event,'editModal')">
    <div class="modal-box">
        <button class="modal-close-btn" onclick="closeEditModal()">&times;</button>
        <div class="modal-title"><i class="ti ti-pencil" style="margin-right:8px;"></i>Edytuj użytkownika</div>
        <div class="modal-subtitle">Zmień dane — hasło zostaw puste jeśli nie chcesz go zmieniać.</div>

        <form method="POST" id="editForm" action="">
            @csrf
            @method('PUT')
            <div class="mf-row">
                <div class="mf-group">
                    <label class="mf-label" for="edit_name">Imię i nazwisko</label>
                    <input id="edit_name" type="text" name="name" class="mf-input" required>
                </div>
                <div class="mf-group">
                    <label class="mf-label" for="edit_email">Adres e-mail</label>
                    <input id="edit_email" type="email" name="email" class="mf-input" required>
                </div>
            </div>
            <div class="mf-row">
                <div class="mf-group">
                    <label class="mf-label" for="edit_phone">Telefon</label>
                    <input id="edit_phone" type="tel" name="phone" class="mf-input" placeholder="+48 000 000 000">
                </div>
                <div class="mf-group">
                    <label class="mf-label" for="edit_role">Rola</label>
                    <select id="edit_role" name="role" class="mf-select mf-input" required>
                        <option value="admin">Administrator</option>
                        <option value="auditor_senior">Audytor Senior</option>
                        <option value="auditor">Audytor</option>
                        <option value="client_admin">Klient - Admin</option>
                        <option value="client_user">Klient - User</option>
                    </select>
                </div>
            </div>
            <div class="mf-group">
                <label class="mf-label" for="edit_password">Nowe hasło (zostaw puste, by nie zmieniać)</label>
                <input id="edit_password" type="password" name="password" class="mf-input" placeholder="min. 8 znaków">
            </div>

            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-device-floppy" style="margin-right:6px;"></i>Zapisz zmiany
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    // Auto-open add modal if there were validation errors
    @if($errors->any() && old('name') !== null)
    document.addEventListener('DOMContentLoaded', function() { openAddModal(); });
    @endif

    function openEditModal(id, name, email, phone, role) {
        const form = document.getElementById('editForm');
        form.action = '/settings/users/' + id;
        document.getElementById('edit_name').value  = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_role').value  = role;
        document.getElementById('edit_password').value = '';
        document.getElementById('editModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function openAssignCompanyModal(el) {
        const userId = el.dataset.userId;
        const userName = el.dataset.userName;
        
        document.getElementById('assignCompanyForm').action = '/settings/users/' + userId + '/assign-company';
        document.getElementById('assignCompanySubtitle').textContent = 'Wybierz firmę dla użytkownika ' + userName + '.';
        document.getElementById('assign_company_id').value = '';
        document.getElementById('assignCompanyModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeAssignCompanyModal() {
        document.getElementById('assignCompanyModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function openAssignToCompanyModal(el) {
        const userId = el.dataset.userId;
        const userName = el.dataset.userName;
        const userEmail = el.dataset.userEmail;
        
        document.getElementById('assignToCompanyForm').action = '/settings/users/' + userId + '/assign-to-company';
        document.getElementById('assignToCompanyName').textContent = userName;
        document.getElementById('assignToCompanyEmail').textContent = userEmail;
        document.getElementById('assign_to_company_id').value = '';
        document.querySelectorAll('#assignToCompanyForm input[name="role"]').forEach(r => r.checked = false);
        document.getElementById('assignToCompanyModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeAssignToCompanyModal() {
        document.getElementById('assignToCompanyModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function closeModalOutside(event, id) {
        if (event.target === document.getElementById(id)) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeAssignCompanyModal();
            closeAssignToCompanyModal();
        }
    });

    // Sorting for users table
    let sortState = { column: null, ascending: true };
    
    function sortTable(columnIndex) {
        const table = document.getElementById('usersTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr[data-user-id]'));
        
        const sortKeys = ['data-sort-name', 'data-sort-email', 'data-sort-role', 'data-sort-company', 'data-sort-status'];
        const key = sortKeys[columnIndex];
        
        // Toggle sort direction if same column clicked
        if (sortState.column === columnIndex) {
            sortState.ascending = !sortState.ascending;
        } else {
            sortState.column = columnIndex;
            sortState.ascending = true;
        }
        
        // Sort rows
        rows.sort((a, b) => {
            const aVal = a.getAttribute(key) || '';
            const bVal = b.getAttribute(key) || '';
            const comparison = aVal.localeCompare(bVal, 'pl');
            return sortState.ascending ? comparison : -comparison;
        });
        
        // Update sort indicators
        table.querySelectorAll('th .sort-indicator').forEach(el => el.textContent = '');
        const th = table.querySelectorAll('th')[columnIndex];
        if (th) {
            const indicator = th.querySelector('.sort-indicator');
            if (indicator) {
                indicator.textContent = sortState.ascending ? ' ▲' : ' ▼';
            }
        }
        
        // Reorder rows in tbody
        rows.forEach(row => tbody.appendChild(row));
    }

    // Sorting for archived tables
    let archivedSortState = {};
    
    function sortArchivedTable(columnIndex, tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        let sortKeys;
        if (tableId === 'archivedStaffTable') {
            sortKeys = ['data-sort-name', 'data-sort-role', 'data-sort-date'];
        } else if (tableId === 'archivedClientsTable') {
            sortKeys = ['data-sort-name', 'data-sort-role', 'data-sort-company', 'data-sort-date'];
        } else {
            return;
        }
        
        const key = sortKeys[columnIndex];
        
        // Initialize or toggle sort state for this table
        if (!archivedSortState[tableId]) {
            archivedSortState[tableId] = { column: null, ascending: true };
        }
        
        const state = archivedSortState[tableId];
        if (state.column === columnIndex) {
            state.ascending = !state.ascending;
        } else {
            state.column = columnIndex;
            state.ascending = true;
        }
        
        // Sort rows
        rows.sort((a, b) => {
            const aVal = a.getAttribute(key) || '';
            const bVal = b.getAttribute(key) || '';
            const comparison = aVal.localeCompare(bVal, 'pl');
            return state.ascending ? comparison : -comparison;
        });
        
        // Update sort indicators
        table.querySelectorAll('th .sort-indicator').forEach(el => el.textContent = '');
        const th = table.querySelectorAll('th')[columnIndex];
        if (th) {
            const indicator = th.querySelector('.sort-indicator');
            if (indicator) {
                indicator.textContent = state.ascending ? ' ▲' : ' ▼';
            }
        }
        
        // Reorder rows in tbody
        rows.forEach(row => tbody.appendChild(row));
    }
</script>
@endpush
