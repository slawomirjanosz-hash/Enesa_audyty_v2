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
        background: var(--green);
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
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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
    a.stat-card {
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        transition: box-shadow .15s, transform .15s;
    }
    a.stat-card:hover {
        box-shadow: 0 4px 14px rgba(0,0,0,.10);
        transform: translateY(-1px);
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
        background: var(--green);
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
        color: var(--green);
        border: 1px solid #C8DDD4;
        border-radius: 6px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s, border-color .15s;
    }
    .tile-btn-secondary:hover { background: #F0F8F4; border-color: var(--green); }

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
        .page-header { gap:14px; }
        .page-header > div:last-child { width:100%; flex-wrap:wrap; }
        .dashboard-search-box { order:3; width:100%; }
        .dashboard-search-box input { width:100%; }
    }

    /* ── View toggle ──────────────────────── */
    .view-toggle {
        display: flex;
        gap: 4px;
        padding: 4px;
        background: #F4F1EA;
        border-radius: 7px;
    }
    .view-toggle-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: none;
        background: transparent;
        color: #888;
        cursor: pointer;
        border-radius: 5px;
        font-size: 16px;
        transition: background .15s, color .15s;
    }
    .view-toggle-btn.active {
        background: #fff;
        color: var(--green);
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .view-toggle-btn:hover { color: var(--green); }

    /* ── Table view ───────────────────────── */
    .companies-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 12px;
        overflow: hidden;
        display: none;
    }
    .companies-table.active {
        display: table;
    }
    .companies-table th {
        padding: 12px 16px;
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
    .companies-table td {
        padding: 12px 16px;
        font-size: 13px;
        color: #1A1A1A;
        border-bottom: 1px solid #F7F5F0;
    }
    .companies-table tr:last-child td {
        border-bottom: none;
    }
    .companies-table tr:hover td {
        background: #FAFAF6;
    }
    .table-status {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 99px;
        font-family: 'Manrope', sans-serif;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }
    .table-status-pending { background: #FFF3E0; color: #E65100; }
    .table-status-active { background: #E8F5E9; color: #2E7D32; }
    .table-status-other { background: #F0EDE6; color: #888; }
    .table-status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        margin-right: 6px;
        vertical-align: middle;
    }
    .table-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 6px 12px;
        background: var(--green);
        color: #F5F0E8;
        border: none;
        border-radius: 5px;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s;
    }
    .table-btn-primary:hover { background: #153d2e; }

    .dashboard-search-box { position:relative; }
    .dashboard-search-box input { font-size:13px; padding:8px 12px 8px 34px; border-radius:7px; border:1px solid #D0CCC0; outline:none; width:300px; font-family:'Lato',sans-serif; }
    .dashboard-search-box input:focus { border-color:var(--green); }
    .dashboard-search-box i { position:absolute; left:10px; top:16px; color:#aaa; font-size:16px; }
    .dashboard-search-result { min-height:15px; margin-top:4px; color:#777; font-size:11px; }
    .companies-table th { cursor:pointer; user-select:none; }
    .companies-table th:hover { color:#fff; }
    .sort-icon-dash { font-size:10px; opacity:.6; margin-left:3px; }
</style>
@endpush

@section('content')

{{-- ══════ NAGŁÓWEK ══════ --}}
<div class="page-header">
    <div>
        <span class="page-badge">{{ $auditsEnabled ? 'Widok audytora' : 'Widok kart klientów' }}</span>
        <h1 class="page-title">Klienci wymagający uwagi</h1>
    </div>
    <div style="display:flex;gap:12px;align-items:center;">
        <div class="dashboard-search-box">
            <i class="ti ti-search"></i>
            <input type="search" id="dashboard-company-search" autocomplete="off"
                   placeholder="Szukaj: firma, oferta, zapytanie{{ $auditsEnabled ? ', audyt' : '' }}{{ $projectsEnabled ? ', projekt' : '' }}…"
                   oninput="searchDashboardCompanies(this.value)">
            <div id="dashboard-search-result" class="dashboard-search-result" aria-live="polite"></div>
        </div>
        <div class="view-toggle">
            <button class="view-toggle-btn active" id="viewTilesBtn" onclick="switchView('tiles')" title="Widok kafelków">
                <i class="ti ti-layout-grid"></i>
            </button>
            <button class="view-toggle-btn" id="viewTableBtn" onclick="switchView('table')" title="Widok tabeli">
                <i class="ti ti-table"></i>
            </button>
        </div>
        <a href="#" class="btn-add" onclick="openModal()">
            <i class="ti ti-plus"></i>Dodaj firmę
        </a>
    </div>
</div>

{{-- ══════ STATYSTYKI ══════ --}}
<div class="stats-grid">

    @if($auditsEnabled)
    {{-- Aktywne audyty --}}
    <a href="{{ route('crm.index', ['tab' => 'audits']) }}" class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="ti ti-clipboard-list"></i>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active_audits'] }}</div>
            <div class="stat-label">Aktywne audyty</div>
            <div class="stat-sub" style="color:#2E7D32;">↑ w tym tygodniu</div>
        </div>
    </a>
    @endif

    @if($projectsEnabled)
    <a href="{{ route('projects.index') }}" class="stat-card">
        <div class="stat-icon stat-icon-green"><i class="ti ti-folders"></i></div>
        <div>
            <div class="stat-value">{{ $stats['active_projects'] }}</div>
            <div class="stat-label">Aktywne projekty</div>
            <div class="stat-sub">Przejdź do projektów</div>
        </div>
    </a>
    @endif

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
    <a href="{{ route('crm.index', ['tab' => 'tasks']) }}" class="stat-card">
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
    </a>

</div>

{{-- ══════ SIATKA KLIENTÓW ══════ --}}
<div class="clients-grid" id="clientsGrid">
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
        @php
            $relatedSearchData = collect()
                ->merge($company->audits->pluck('title'))
                ->merge($company->projects->flatMap(fn ($project) => [$project->number, $project->name, $project->description]))
                ->merge($company->offers->flatMap(fn ($offer) => [
                    $offer->offer_title,
                    $offer->offer_full_number,
                    $offer->offer_number,
                    $offer->offer_slug,
                ]))
                ->merge($company->offerRequests->flatMap(fn ($request) => [
                    $request->title,
                    $request->offerFormTemplate?->name,
                ]))
                ->filter()
                ->implode(' ');
            $searchData = implode(' ', array_filter([
                $company->name, $company->nip, $company->email, $company->phone,
                $company->address, $company->city, $company->source, $company->notes, $company->status,
                $relatedSearchData,
            ]));
        @endphp
        <div class="client-tile" data-company-search="{{ $searchData }}" style="border-left-color: {{ $borderColor }};">

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
                @if($auditsEnabled)
                <div class="tile-info-row" data-dashboard-metric="audits">
                    <i class="ti ti-clipboard"></i>
                    <span>{{ $company->audits->count() }} {{ $company->audits->count() === 1 ? 'audyt' : ($company->audits->count() < 5 ? 'audyty' : 'audytów') }}</span>
                </div>
                @endif
                @if($projectsEnabled)
                <div class="tile-info-row" data-dashboard-metric="projects">
                    <i class="ti ti-folders"></i>
                    <span>{{ $company->projects->count() }} {{ $company->projects->count() === 1 ? 'projekt' : ($company->projects->count() < 5 ? 'projekty' : 'projektów') }}</span>
                </div>
                @endif
                <div class="tile-info-row">
                    <i class="ti ti-file-invoice"></i>
                    <span>{{ $company->offers->count() }} {{ $company->offers->count() === 1 ? 'oferta' : ($company->offers->count() < 5 ? 'oferty' : 'ofert') }}</span>
                </div>
                <div class="tile-info-row">
                    <i class="ti ti-message"></i>
                    <span>0 wiadomości</span>
                </div>
            </div>

            {{-- Nowe zapytania w kafelku — tylko badge --}}
            @php $tileRequests = $newRequests->get($company->id, collect()); @endphp
            @if($tileRequests->isNotEmpty())
            <div style="margin-bottom:8px;">
                <a href="{{ route('companies.show', $company) }}#zapytania" style="display:inline-flex;align-items:center;gap:6px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:5px 10px;text-decoration:none;">
                    <i class="ti ti-inbox" style="font-size:13px;color:#DC2626;"></i>
                    <span style="font-size:12px;font-weight:700;color:#DC2626;">{{ $tileRequests->count() }} nowe {{ $tileRequests->count() === 1 ? 'zapytanie' : 'zapytania' }}</span>
                </a>
            </div>
            @endif

            {{-- Przyciski --}}
            <div class="tile-footer">
                <a href="{{ route('companies.show', $company) }}" class="tile-btn-primary">
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

{{-- ══════ WIDOK TABELI ══════ --}}
<table class="companies-table" id="companiesTable">
    <thead>
        <tr>
            <th onclick="sortCompaniesTable(0)">Firma <span class="sort-icon-dash">⇅</span></th>
            <th onclick="sortCompaniesTable(1)">NIP <span class="sort-icon-dash">⇅</span></th>
            <th onclick="sortCompaniesTable(2)">Status <span class="sort-icon-dash">⇅</span></th>
            @if($auditsEnabled)<th data-dashboard-metric="audits" onclick="sortCompaniesTable(3,true)">Audyty <span class="sort-icon-dash">⇅</span></th>@endif
            @if($projectsEnabled)<th data-dashboard-metric="projects" onclick="sortCompaniesTable({{$auditsEnabled ? 4 : 3}},true)">Projekty <span class="sort-icon-dash">⇅</span></th>@endif
            <th onclick="sortCompaniesTable({{3 + (int)$auditsEnabled + (int)$projectsEnabled}},true)">Oferty <span class="sort-icon-dash">⇅</span></th>
            <th onclick="sortCompaniesTable({{4 + (int)$auditsEnabled + (int)$projectsEnabled}},true)">Użytkownicy <span class="sort-icon-dash">⇅</span></th>
            <th style="text-align:right;">Akcje</th>
        </tr>
    </thead>
    <tbody id="companies-table-tbody">
        @forelse($companies as $company)
            @php
                $isOnline = $company->users->contains(
                    fn($u) => $u->last_seen_at && $u->last_seen_at->gt(now()->subMinutes(5))
                );
            @endphp
            @php
                $relatedSearchData = collect()
                    ->merge($company->audits->pluck('title'))
                    ->merge($company->projects->flatMap(fn ($project) => [$project->number, $project->name, $project->description]))
                    ->merge($company->offers->flatMap(fn ($offer) => [
                        $offer->offer_title,
                        $offer->offer_full_number,
                        $offer->offer_number,
                        $offer->offer_slug,
                    ]))
                    ->merge($company->offerRequests->flatMap(fn ($request) => [
                        $request->title,
                        $request->offerFormTemplate?->name,
                    ]))
                    ->filter()
                    ->implode(' ');
                $searchData = implode(' ', array_filter([
                    $company->name, $company->nip, $company->email, $company->phone,
                    $company->address, $company->city, $company->source, $company->notes, $company->status,
                    $relatedSearchData,
                ]));
            @endphp
            <tr data-company-search="{{ $searchData }}">
                <td>
                    <strong>{{ $company->name }}</strong>
                </td>
                <td style="font-family:monospace;font-size:12px;">{{ $company->nip ?? '—' }}</td>
                <td>
                    <span class="table-status {{ match($company->status) {
                        'pending' => 'table-status-pending',
                        'active'  => 'table-status-active',
                        default   => 'table-status-other',
                    } }}">
                        <span class="table-status-dot {{ $isOnline ? 'dot-green' : 'dot-gray' }}" style="display:inline-block;"></span>
                        {{ match($company->status) {
                            'pending' => 'Oczekuje',
                            'active'  => 'Aktywny',
                            default   => ucfirst($company->status),
                        } }}
                    </span>
                </td>
                @if($auditsEnabled)<td data-dashboard-metric="audits" style="text-align:center;">{{ $company->audits->count() }}</td>@endif
                @if($projectsEnabled)<td data-dashboard-metric="projects" style="text-align:center;">{{ $company->projects->count() }}</td>@endif
                <td style="text-align:center;">{{ $company->offers->count() }}</td>
                <td style="text-align:center;">{{ $company->users->count() }}</td>
                <td style="text-align:right;">
                    <a href="{{ route('companies.show', $company) }}" class="table-btn-primary">
                        <i class="ti ti-eye"></i>Otwórz
                    </a>
                </td>
            </tr>
        @empty
        @endforelse
    </tbody>
</table>

{{-- ══════ ZAAKCEPTOWANE OFERTY ══════ --}}
@if($acceptedOffers->isNotEmpty())
<div style="margin-top:32px;margin-bottom:32px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <span style="font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;color:var(--green);">
            <i class="ti ti-rosette-discount-check" style="margin-right:6px;"></i>Zaakceptowane oferty
        </span>
        <span style="font-family:'Manrope',sans-serif;font-size:12px;color:#888;">(ostatnie 30 dni)</span>
    </div>
    <div style="background:#fff;border:0.5px solid #E5E1D8;border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-family:'Manrope',sans-serif;font-size:13px;">
            <thead>
                <tr style="background:#F4F1EA;">
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:0.4px;">Firma</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:0.4px;">Numer oferty</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:0.4px;">Data akceptacji</th>
                    <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:0.4px;">Kwota netto</th>
                    <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:0.4px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acceptedOffers as $ao)
                <tr style="border-top:0.5px solid #F0EDE6;">
                    <td style="padding:10px 16px;">
                        <a href="{{ route('companies.show', $ao->company) }}" style="color:var(--green);font-weight:600;text-decoration:none;">{{ $ao->company?->name ?? '—' }}</a>
                    </td>
                    <td style="padding:10px 16px;">
                        <a href="{{ route('offers.edit', $ao) }}" style="color:#1A1A1A;text-decoration:none;font-weight:500;">{{ $ao->offer_full_number ?? $ao->offer_number }}</a>
                    </td>
                    <td style="padding:10px 16px;color:#5a6a60;">{{ $ao->updated_at->format('d.m.Y H:i') }}</td>
                    <td style="padding:10px 16px;text-align:right;font-family:'Lato',sans-serif;font-weight:700;color:#1A1A1A;">
                        @if($ao->kwota_netto !== null)
                            {{ number_format($ao->kwota_netto, 2, ',', ' ') }} zł
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding:10px 16px;text-align:center;">
                        <span style="display:inline-block;padding:2px 10px;border-radius:20px;background:#DCFCE7;color:#166534;font-size:11px;font-weight:700;">Zaakceptowana</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ══════ MODAL DODAJ KLIENTA ══════ --}}
<div id="addClientModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:36px;max-width:500px;width:95%;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:22px;color:#aaa;cursor:pointer;line-height:1;">&times;</button>
        <div style="font-family:'Manrope',sans-serif;font-size:18px;font-weight:700;color:var(--green);margin-bottom:6px;">
            <i class="ti ti-building" style="margin-right:8px;"></i>Dodaj firmę
        </div>
        <div style="font-size:13px;color:#888;margin-bottom:24px;">Wypełnij dane firmy lub pobierz automatycznie z GUS.</div>

        @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;font-size:13px;margin-bottom:16px;">
            <strong style="color:#b91c1c;">Popraw błędy:</strong>
            <ul style="margin:6px 0 0 16px;color:#b91c1c;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('companies.store') }}">
            @csrf

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Rodzaj firmy *</label>
                <select id="company-type" name="company_type" onchange="updateCompanyTypeFields()" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;" required>
                    <option value="client" {{old('company_type','client')==='client'?'selected':''}}>Klient</option>
                    <option value="supplier" {{old('company_type')==='supplier'?'selected':''}}>Dostawca</option>
                </select>
            </div>

            {{-- NIP + GUS --}}
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">NIP firmy</label>
                <div style="display:flex;gap:8px;">
                    <input id="nip-input" type="text" name="nip" placeholder="np. 527-000-11-22"
                           value="{{ old('nip') }}"
                           style="flex:1;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;"
                           oninput="this.value=this.value.replace(/[^0-9\-]/g,'')"
                           maxlength="13">
                    <button type="button" onclick="fetchFromGus()"
                            style="padding:10px 14px;background:rgba(26,77,58,0.08);border:1px solid rgba(26,77,58,0.25);border-radius:6px;color:var(--green);font-family:'Lato',sans-serif;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
                        Pobierz z GUS
                    </button>
                </div>
                <div id="gus-status" style="margin-top:6px;font-size:12px;color:#888;"></div>
            </div>

            {{-- Nazwa firmy --}}
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Nazwa firmy<span style="color:#b91c1c;">*</span></label>
                <input id="company-name" type="text" name="name" required
                       value="{{ old('name') }}"
                       style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                       placeholder="Pobrana z GUS lub wpisz ręcznie">
            </div>

            {{-- Adres + Miasto --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Adres</label>
                    <input id="company-address" type="text" name="address"
                           value="{{ old('address') }}"
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="ul. Przykładowa 1">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Miasto</label>
                    <input id="company-city" type="text" name="city"
                           value="{{ old('city') }}"
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="Warszawa">
                </div>
            </div>

            {{-- Email + Telefon --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Email</label>
                    <input type="email" name="email"
                           value="{{ old('email') }}"
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="biuro@firma.pl">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Telefon</label>
                    <input type="tel" name="phone"
                           value="{{ old('phone') }}"
                           style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font-size:14px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;"
                           placeholder="+48 000 000 000">
                </div>
            </div>

            <div id="supplier-profile-fields" style="display:none;margin-bottom:18px;">
                <div style="margin-bottom:12px;"><label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Co może dostarczać / jakie świadczy usługi</label><textarea name="supplier_capabilities" rows="3" style="width:100%;box-sizing:border-box;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font:inherit;" placeholder="np. dostawy armatury, montaż instalacji…">{{old('supplier_capabilities')}}</textarea></div>
                <div><label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;">Materiały i asortyment</label><textarea name="supplier_materials" rows="3" style="width:100%;box-sizing:border-box;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:6px;padding:10px 12px;font:inherit;" placeholder="np. pompy, przewody, zawory…">{{old('supplier_materials')}}</textarea></div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" id="company-submit-label"
                        style="flex:1;background:var(--green);color:#F5F0E8;border:none;border-radius:8px;padding:12px;font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:background .15s;">
                    <i class="ti ti-plus" style="margin-right:6px;"></i><span>Dodaj klienta</span>
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
    // View toggle functionality
    function switchView(view) {
        const grid = document.getElementById('clientsGrid');
        const table = document.getElementById('companiesTable');
        const btnTiles = document.getElementById('viewTilesBtn');
        const btnTable = document.getElementById('viewTableBtn');

        if (view === 'tiles') {
            grid.style.display = 'grid';
            table.classList.remove('active');
            btnTiles.classList.add('active');
            btnTable.classList.remove('active');
        } else {
            grid.style.display = 'none';
            table.classList.add('active');
            btnTiles.classList.remove('active');
            btnTable.classList.add('active');
        }

        // Save preference to localStorage
        localStorage.setItem('dashboardView', view);
    }

    // Load saved view preference on page load
    document.addEventListener('DOMContentLoaded', function() {
        const savedView = localStorage.getItem('dashboardView') || 'tiles';
        switchView(savedView);

        @if($errors->any())
        openModal();
        @endif
    });

    function resetAddClientForm() {
        const form = document.querySelector('#addClientModal form');
        const fields = ['company-name', 'company-address', 'company-city', 'nip-input'];
        const status = document.getElementById('gus-status');

        form.reset();

        fields.forEach(function(id) {
            document.getElementById(id).style.borderColor = '#D0CCC0';
        });

        status.textContent = '';
        status.style.color = '#888';
        updateCompanyTypeFields();
    }

    function updateCompanyTypeFields() {
        const type = document.getElementById('company-type')?.value || 'client';
        const supplierFields = document.getElementById('supplier-profile-fields');
        const label = document.querySelector('#company-submit-label span');
        if (supplierFields) supplierFields.style.display = type === 'supplier' ? 'block' : 'none';
        if (label) label.textContent = type === 'supplier' ? 'Dodaj dostawcę' : 'Dodaj klienta';
    }

    function openModal() {
        resetAddClientForm();
        document.getElementById('addClientModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('addClientModal').style.display = 'none';
        resetAddClientForm();
    }

    function fetchFromGus() {
        const nip = document.getElementById('nip-input').value.replace(/[^0-9]/g, '');
        const status = document.getElementById('gus-status');

        if (nip.length !== 10) {
            status.style.color = '#b91c1c';
            status.textContent = 'NIP musi mieć 10 cyfr.';
            return;
        }

        status.style.color = '#888';
        status.textContent = 'Pobieranie danych z GUS...';

        fetch('{{ route("companies.fetchGus") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ nip: nip }),
        })
        .then(async function(r) {
            const data = await r.json();

            if (!r.ok) {
                throw new Error(data.error || 'Nie udało się pobrać danych z GUS.');
            }

            return data;
        })
        .then(d => {
            const name = document.getElementById('company-name');
            const addr = document.getElementById('company-address');
            const city = document.getElementById('company-city');

            name.value = d.name    || '';
            addr.value = d.address || '';
            city.value = d.city    || '';

            if (d.name)    { name.style.borderColor = '#2E7D32'; }
            if (d.address) { addr.style.borderColor = '#2E7D32'; }
            if (d.city)    { city.style.borderColor = '#2E7D32'; }

            status.style.color = '#2E7D32';
            status.textContent = 'Dane pobrane poprawnie.';
        })
        .catch(function(error) {
            status.style.color = '#b91c1c';
            status.textContent = error.message;
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    document.getElementById('addClientModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    function normalizeCompanySearch(value) {
        return String(value || '')
            .toLocaleLowerCase('pl-PL')
            .replace(/ł/g, 'l')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function searchDashboardCompanies(query) {
        const normalizedQuery = normalizeCompanySearch(query);
        const terms = normalizedQuery.split(' ').filter(Boolean);
        let matches = 0;

        document.querySelectorAll('[data-company-search]').forEach(element => {
            const searchable = normalizeCompanySearch(element.dataset.companySearch);
            const compactSearchable = searchable.replace(/\s/g, '');
            const isMatch = terms.every(term => searchable.includes(term) || compactSearchable.includes(term));
            element.style.display = isMatch ? '' : 'none';
            if (isMatch && element.classList.contains('client-tile')) matches++;
        });

        const result = document.getElementById('dashboard-search-result');
        if (result) {
            result.textContent = normalizedQuery
                ? `Znaleziono: ${matches} ${matches === 1 ? 'firmę' : 'firm'}.`
                : '';
        }
    }

    const companiesSortState = {};
    function sortCompaniesTable(colIdx, numeric = false) {
        const tbody = document.getElementById('companies-table-tbody');
        if (!tbody) return;
        companiesSortState[colIdx] = companiesSortState[colIdx] === 'asc' ? 'desc' : 'asc';
        const dir = companiesSortState[colIdx];
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            let av = a.cells[colIdx]?.textContent.trim() || '';
            let bv = b.cells[colIdx]?.textContent.trim() || '';
            if (numeric) {
                av = parseFloat(av.replace(/[^\d.-]/g, '')) || 0;
                bv = parseFloat(bv.replace(/[^\d.-]/g, '')) || 0;
            }
            if (av < bv) return dir === 'asc' ? -1 : 1;
            if (av > bv) return dir === 'asc' ? 1 : -1;
            return 0;
        });
        rows.forEach(r => tbody.appendChild(r));
    }
</script>
@endpush

@endsection
