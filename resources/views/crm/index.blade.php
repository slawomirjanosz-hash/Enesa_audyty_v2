@extends('layouts.app')

@section('page-title', 'CRM')

@push('styles')
<style>
.crm-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
.crm-stat { background:#fff; border:1px solid #E5E1D8; border-radius:12px; padding:18px 20px; }
.crm-stat-num { font-size:28px; font-weight:900; color:#1A4D3A; line-height:1; font-family:'Lato',sans-serif; }
.crm-stat-lbl { font-size:11px; color:#888; margin-top:4px; text-transform:uppercase; letter-spacing:.05em; font-family:'Manrope',sans-serif; }
.crm-stat-icon { font-size:20px; color:#1A4D3A; margin-bottom:8px; }

.crm-tabs { display:flex; gap:0; border-bottom:2px solid #E5E1D8; margin-bottom:20px; }
.crm-tab { padding:10px 20px; font-size:13px; font-weight:600; cursor:pointer; color:#888; border-bottom:2px solid transparent; margin-bottom:-2px; font-family:'Manrope',sans-serif; text-decoration:none; display:flex; align-items:center; gap:6px; transition:color .15s; }
.crm-tab:hover { color:#1A4D3A; }
.crm-tab.active { color:#1A4D3A; border-bottom-color:#1A4D3A; }
.crm-tab .tab-count { background:#F0F7F3; color:#1A4D3A; border-radius:20px; font-size:10px; font-weight:700; padding:1px 7px; }
.crm-tab.archive-tab .tab-count { background:#FEF3C7; color:#92400E; }

.table-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; }
.table-card-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; }
.table-card-title { font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; color:#1A1A1A; }

.crm-table { width:100%; border-collapse:collapse; font-family:'Lato',sans-serif; font-size:13px; }
.crm-table th { padding:9px 14px; text-align:left; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #F0EDE6; background:#FAFAF6; cursor:pointer; user-select:none; white-space:nowrap; font-family:'Manrope',sans-serif; }
.crm-table th:hover { color:#1A4D3A; }
.crm-table td { padding:12px 14px; border-bottom:1px solid #F7F5F0; color:#1A1A1A; vertical-align:middle; }
.crm-table tr:last-child td { border-bottom:none; }
.crm-table tr:hover td { background:#FAFAF6; }

.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-green  { background:#DCFCE7; color:#166534; }
.badge-amber  { background:#FEF3C7; color:#92400E; }
.badge-blue   { background:#DBEAFE; color:#1D4ED8; }
.badge-red    { background:#FEE2E2; color:#B91C1C; }
.badge-gray   { background:#F3F4F6; color:#4B5563; }
.badge-purple { background:#EDE9FE; color:#5B21B6; }

.btn-primary { display:inline-flex; align-items:center; gap:6px; background:#1A4D3A; color:#F5F0E8; border:none; border-radius:8px; padding:8px 16px; font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-primary:hover { background:#143d2d; color:#F5F0E8; }
.btn-secondary { display:inline-flex; align-items:center; gap:6px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:8px; padding:7px 14px; font-family:'Manrope',sans-serif; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-secondary:hover { background:#F4F1EA; }

.btn-icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:7px; border:none; cursor:pointer; font-size:15px; transition:background .12s; }
.btn-icon-view   { background:#F0F7F3; color:#1A4D3A; }
.btn-icon-edit   { background:#EFF6FF; color:#1D4ED8; }
.btn-icon-archive{ background:#FEF3C7; color:#92400E; }
.btn-icon-delete { background:#FEE2E2; color:#B91C1C; }
.btn-icon-restore{ background:#DCFCE7; color:#166534; }

.search-box { position:relative; }
.search-box input { font-size:12px; padding:6px 10px 6px 30px; border-radius:6px; border:1px solid #D0CCC0; outline:none; width:200px; font-family:'Lato',sans-serif; }
.search-box input:focus { border-color:#1A4D3A; }
.search-box i { position:absolute; left:8px; top:50%; transform:translateY(-50%); color:#aaa; font-size:15px; }

.toggle-wrap { width:36px; height:20px; border-radius:10px; position:relative; cursor:pointer; transition:background .2s; border:none; padding:0; }
.toggle-wrap .knob { position:absolute; top:3px; width:14px; height:14px; background:#fff; border-radius:50%; transition:left .2s; }

/* Pipeline */
.funnel-stage { display:flex; align-items:stretch; gap:0; margin-bottom:3px; }
.funnel-label { width:120px; flex-shrink:0; display:flex; align-items:center; padding-right:12px; }
.funnel-bar { flex:1; border-radius:8px; padding:10px 12px; min-height:56px; }
.opp-card { background:#fff; border:1px solid #E5E1D8; border-radius:8px; padding:10px 12px; }
.opp-card-title { font-size:12px; font-weight:700; color:#1A1A1A; margin-bottom:2px; font-family:'Manrope',sans-serif; }
.opp-card-sub { font-size:11px; color:#888; }
.opp-card-val { font-size:12px; font-weight:700; color:#1A4D3A; margin-top:4px; font-family:'Lato',sans-serif; }

/* Task tables */
.task-table-wrap { border-radius:12px; overflow:hidden; margin-bottom:16px; }
.task-table-wrap.mine { border:2px solid #1A4D3A; }
.task-table-wrap.team { border:1px solid #E5E1D8; }
.task-hdr { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid #F0EDE6; }
.task-hdr.mine { background:#F0F7F3; }
.task-hdr.team { background:#FAFAF6; }

.archive-info { background:#FEF3C7; border:1px solid #FCD34D; border-radius:8px; padding:10px 16px; margin-bottom:16px; font-size:13px; color:#92400E; display:flex; align-items:center; gap:8px; }

@media(max-width:900px) { .crm-stats { grid-template-columns:repeat(2,1fr); } }
</style>
@endpush

@section('content')
@php
    $currentTab = request('tab', 'companies');
    $userId = auth()->id();
    $isAdmin = auth()->user()->hasRole('admin');

    $stageMeta = [
        'new_lead'    => ['label'=>'Nowy lead',   'color'=>'#EDE9FE','text'=>'#5B21B6','border'=>'#C4B5FD','dot'=>'#7C3AED'],
        'contact'     => ['label'=>'Kontakt',      'color'=>'#DBEAFE','text'=>'#1D4ED8','border'=>'#93C5FD','dot'=>'#2563EB'],
        'offer'       => ['label'=>'Oferta',       'color'=>'#FEF3C7','text'=>'#92400E','border'=>'#FCD34D','dot'=>'#D97706'],
        'negotiation' => ['label'=>'Negocjacje',   'color'=>'#FFEDD5','text'=>'#9A3412','border'=>'#FDBA74','dot'=>'#EA580C'],
        'realization' => ['label'=>'Realizacja',   'color'=>'#DCFCE7','text'=>'#166534','border'=>'#86EFAC','dot'=>'#16A34A'],
        'won'         => ['label'=>'Wygrana',      'color'=>'#D1FAE5','text'=>'#065F46','border'=>'#6EE7B7','dot'=>'#059669'],
        'lost'        => ['label'=>'Przegrana',    'color'=>'#FEE2E2','text'=>'#B91C1C','border'=>'#FCA5A5','dot'=>'#DC2626'],
        'rejected'    => ['label'=>'Odrzucona',    'color'=>'#F3F4F6','text'=>'#4B5563','border'=>'#D1D5DB','dot'=>'#6B7280'],
    ];
    $funnelStages = ['new_lead','contact','offer','negotiation','realization'];
    $endStages    = ['won','lost','rejected'];

    $myTasks    = $tasks->filter(fn($t) => $t->assigned_to == $userId || $t->created_by == $userId);
    $otherTasks = $tasks->filter(fn($t) => $t->assigned_to != $userId && $t->created_by != $userId);

    $priorityMeta = [
        'high'   => ['label'=>'Wysoki',  'class'=>'badge-red'],
        'medium' => ['label'=>'Średni',  'class'=>'badge-amber'],
        'low'    => ['label'=>'Niski',   'class'=>'badge-gray'],
    ];
    $statusMeta = [
        'todo'        => ['label'=>'Do zrobienia', 'class'=>'badge-gray'],
        'in_progress' => ['label'=>'W toku',       'class'=>'badge-blue'],
        'done'        => ['label'=>'Zrobione',     'class'=>'badge-green'],
    ];
@endphp

{{-- HEADER --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h1 style="font-family:'Manrope',sans-serif;font-size:22px;font-weight:700;color:#1A4D3A;margin:0;">
        <i class="ti ti-users"></i> CRM
    </h1>
    <div style="display:flex;gap:8px;">
        @if($currentTab === 'pipeline')
            <button onclick="document.getElementById('modal-opp').style.display='flex'" class="btn-primary">
                <i class="ti ti-plus"></i> Nowa szansa
            </button>
        @elseif($currentTab === 'tasks')
            <button onclick="document.getElementById('modal-task').style.display='flex'" class="btn-primary">
                <i class="ti ti-plus"></i> Nowe zadanie
            </button>
        @else
            <a href="{{ route('dashboard') }}" class="btn-primary">
                <i class="ti ti-plus"></i> Dodaj firmę
            </a>
        @endif
    </div>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="background:#FEF2F2;border:1px solid #FCA5A5;color:#B91C1C;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-alert-circle"></i> {{ session('error') }}
    </div>
@endif

{{-- STATS --}}
<div class="crm-stats">
    <div class="crm-stat">
        <div class="crm-stat-icon"><i class="ti ti-building"></i></div>
        <div class="crm-stat-num">{{ $stats['active_companies'] }}</div>
        <div class="crm-stat-lbl">Aktywne firmy</div>
    </div>
    <div class="crm-stat">
        <div class="crm-stat-icon"><i class="ti ti-layout-dashboard"></i></div>
        <div class="crm-stat-num">{{ $stats['dashboard_companies'] }}</div>
        <div class="crm-stat-lbl">W dashboardzie</div>
    </div>
    <div class="crm-stat">
        <div class="crm-stat-icon"><i class="ti ti-target"></i></div>
        <div class="crm-stat-num">{{ $stats['active_opps'] }}</div>
        <div class="crm-stat-lbl">Aktywne szanse</div>
    </div>
    <div class="crm-stat">
        <div class="crm-stat-icon"><i class="ti ti-checklist"></i></div>
        <div class="crm-stat-num">{{ $stats['open_tasks'] }}</div>
        <div class="crm-stat-lbl">Otwarte zadania</div>
    </div>
</div>

{{-- TABS --}}
<div class="crm-tabs">
    <a href="{{ route('crm.index', ['tab'=>'companies']) }}" class="crm-tab {{ $currentTab==='companies'?'active':'' }}">
        <i class="ti ti-building"></i> Firmy
        <span class="tab-count">{{ $stats['active_companies'] }}</span>
    </a>
    <a href="{{ route('crm.index', ['tab'=>'pipeline']) }}" class="crm-tab {{ $currentTab==='pipeline'?'active':'' }}">
        <i class="ti ti-target"></i> Szanse
        <span class="tab-count">{{ $stats['active_opps'] }}</span>
    </a>
    <a href="{{ route('crm.index', ['tab'=>'tasks']) }}" class="crm-tab {{ $currentTab==='tasks'?'active':'' }}">
        <i class="ti ti-checklist"></i> Zadania
        <span class="tab-count">{{ $stats['open_tasks'] }}</span>
    </a>
    <a href="{{ route('crm.index', ['tab'=>'archive']) }}" class="crm-tab archive-tab {{ $currentTab==='archive'?'active':'' }}">
        <i class="ti ti-archive"></i> Archiwum
        <span class="tab-count">{{ $archivedCompanies->count() }}</span>
    </a>
</div>

{{-- ═══ TAB: FIRMY ═══ --}}
@if($currentTab === 'companies')
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title"><i class="ti ti-building" style="color:#1A4D3A;margin-right:6px;"></i> Aktywne firmy ({{ $companies->count() }})</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <div class="search-box">
                <i class="ti ti-search"></i>
                <input type="text" id="search-companies" placeholder="Szukaj firmy..." oninput="filterTable('companies-tbody', this.value, [0,1,2])">
            </div>
            <select id="filter-dashboard" onchange="filterDashboard()" style="font-size:12px;padding:6px 10px;border-radius:6px;border:1px solid #D0CCC0;outline:none;">
                <option value="all">Wszystkie</option>
                <option value="yes">W dashboardzie</option>
                <option value="no">Bez dashboardu</option>
            </select>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="crm-table">
            <thead>
                <tr>
                    <th onclick="sortTable('companies-tbody',0)">Firma <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('companies-tbody',1)">NIP <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('companies-tbody',2)">Miasto <span class="sort-icon">⇅</span></th>
                    <th>Dashboard</th>
                    <th onclick="sortTable('companies-tbody',4,true)">Oferty <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('companies-tbody',5,true)">Audyty <span class="sort-icon">⇅</span></th>
                    <th style="text-align:center;">Akcje</th>
                </tr>
            </thead>
            <tbody id="companies-tbody">
                @foreach($companies as $company)
                <tr data-dashboard="{{ $company->show_in_dashboard ? 'yes' : 'no' }}">
                    <td>
                        <a href="{{ route('companies.show', $company) }}" style="font-weight:700;color:#1A4D3A;text-decoration:none;">{{ $company->name }}</a>
                    </td>
                    <td style="color:#888;font-size:12px;">{{ $company->nip ?? '—' }}</td>
                    <td style="color:#888;">{{ $company->city ?? '—' }}</td>
                    <td>
                        <button class="toggle-wrap" id="toggle-{{ $company->id }}"
                            onclick="toggleDashboard({{ $company->id }}, this)"
                            style="background:{{ $company->show_in_dashboard ? '#1A4D3A' : '#D1D5DB' }};"
                            title="{{ $company->show_in_dashboard ? 'Widoczna w dashboardzie' : 'Niewidoczna w dashboardzie' }}">
                            <span class="knob" style="left:{{ $company->show_in_dashboard ? '19px' : '3px' }};"></span>
                        </button>
                    </td>
                    <td>{{ $company->offers->count() }}</td>
                    <td>{{ $company->audits->count() }}</td>
                    <td style="text-align:center;">
                        <div style="display:flex;gap:4px;justify-content:center;">
                            <a href="{{ route('companies.show', $company) }}" class="btn-icon btn-icon-view" title="Podgląd">
                                <i class="ti ti-eye"></i>
                            </a>
                            <button type="button" class="btn-icon" title="Edytuj" style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;" onclick="openEditCompanyModal({{ $company->id }}, '{{ addslashes($company->name) }}', '{{ addslashes($company->nip ?? '') }}', '{{ addslashes($company->email ?? '') }}', '{{ addslashes($company->phone ?? '') }}', '{{ addslashes($company->address ?? '') }}', '{{ addslashes($company->city ?? '') }}', '{{ addslashes($company->source ?? '') }}', '{{ addslashes($company->notes ?? '') }}')">
                                <i class="ti ti-pencil"></i>
                            </button>
                            @if($company->offers->count() > 0 || $company->audits->count() > 0)
                                <form method="POST" action="{{ route('crm.companies.archive', $company) }}" style="display:inline;">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-icon btn-icon-archive" title="Archiwizuj"
                                        onclick="return confirm('Firma ma dane — zostanie zarchiwizowana. Kontynuować?')">
                                        <i class="ti ti-archive"></i>
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('crm.companies.destroy', $company) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-delete" title="Usuń"
                                        onclick="return confirm('Czy na pewno usunąć firmę {{ addslashes($company->name) }}?')">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ TAB: PIPELINE ═══ --}}
@if($currentTab === 'pipeline')
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
    <div style="font-size:13px;color:#888;">
        Pipeline: <strong style="color:#1A4D3A;">{{ number_format($opportunities->whereNotIn('stage',['won','lost','rejected'])->sum('value'), 2, ',', ' ') }} zł</strong>
    </div>
</div>

{{-- Lejek --}}
<div style="margin-bottom:20px;">
    @foreach($funnelStages as $stageKey)
    @php
        $meta = $stageMeta[$stageKey];
        $stageOpps = $opportunities->where('stage', $stageKey);
        $activeTotal = $opportunities->whereNotIn('stage',['won','lost','rejected'])->count();
        $pct = $activeTotal > 0 ? round($stageOpps->count() / $activeTotal * 100) : 0;
    @endphp
    <div class="funnel-stage">
        <div class="funnel-label">
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:8px;height:8px;border-radius:50%;background:{{ $meta['dot'] }};flex-shrink:0;"></div>
                <span style="font-size:12px;font-weight:700;color:{{ $meta['text'] }};font-family:'Manrope',sans-serif;">{{ $meta['label'] }}</span>
            </div>
        </div>
        <div class="funnel-bar" style="background:{{ $meta['color'] }};border:1px solid {{ $meta['border'] }};">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:{{ $stageOpps->count() > 0 ? '8px' : '0' }};">
                <span style="font-size:11px;font-weight:600;color:{{ $meta['text'] }};">{{ $stageOpps->count() }} szans · {{ $pct }}%</span>
                <span style="font-size:11px;font-weight:700;color:{{ $meta['text'] }};">{{ number_format($stageOpps->sum('value'), 2, ',', ' ') }} zł</span>
            </div>
            @if($stageOpps->count() > 0)
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($stageOpps as $opp)
                <div class="opp-card" style="width:calc(33.33% - 6px);min-width:160px;max-width:240px;border-left:3px solid {{ $meta['dot'] }};cursor:pointer;"
                    onclick="openEditOpp({{ $opp->id }}, '{{ addslashes($opp->title) }}', {{ $opp->company_id ?? 'null' }}, '{{ $opp->stage }}', {{ $opp->value ?? 'null' }}, '{{ $opp->expected_close_date?->format('Y-m-d') ?? '' }}', {{ $opp->assigned_to ?? 'null' }}, '{{ addslashes($opp->description ?? '') }}', '{{ addslashes($opp->notes ?? '') }}')">
                    <div class="opp-card-title">{{ $opp->title }}</div>
                    <div class="opp-card-sub">{{ $opp->company?->name ?? 'bez klienta' }}</div>
                    @if($opp->value)
                    <div class="opp-card-val">{{ number_format($opp->value, 2, ',', ' ') }} zł</div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div style="font-size:11px;color:{{ $meta['text'] }};opacity:.5;font-style:italic;">Brak szans w tym etapie</div>
            @endif
        </div>
    </div>
    @if(!$loop->last)<div style="width:2px;height:4px;background:#D1D5DB;margin-left:116px;"></div>@endif
    @endforeach
</div>

{{-- Zakończone --}}
<div style="border-top:2px dashed #D1D5DB;padding-top:14px;margin-bottom:24px;">
    <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;font-family:'Manrope',sans-serif;">Zakończone</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
        @foreach($endStages as $stageKey)
        @php $meta = $stageMeta[$stageKey]; $stageOpps = $opportunities->where('stage',$stageKey); @endphp
        <div style="background:{{ $meta['color'] }};border:1px solid {{ $meta['border'] }};border-radius:8px;padding:10px 12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:{{ $stageOpps->count() > 0 ? '8px' : '0' }};">
                <div style="display:flex;align-items:center;gap:6px;">
                    <div style="width:8px;height:8px;border-radius:50%;background:{{ $meta['dot'] }};"></div>
                    <span style="font-size:12px;font-weight:700;color:{{ $meta['text'] }};font-family:'Manrope',sans-serif;">{{ $meta['label'] }}</span>
                </div>
                <span style="font-size:11px;font-weight:600;color:{{ $meta['text'] }};">{{ $stageOpps->count() }}</span>
            </div>
            @foreach($stageOpps as $opp)
            <div class="opp-card" style="border-left:3px solid {{ $meta['dot'] }};margin-bottom:6px;">
                <div class="opp-card-title">{{ $opp->title }}</div>
                <div class="opp-card-sub">{{ $opp->company?->name ?? 'bez klienta' }}</div>
            </div>
            @endforeach
            @if($stageOpps->count() === 0)<div style="font-size:11px;color:{{ $meta['text'] }};opacity:.5;font-style:italic;">Brak</div>@endif
        </div>
        @endforeach
    </div>
</div>

{{-- Tabela szans --}}
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title"><i class="ti ti-target" style="color:#1A4D3A;margin-right:6px;"></i> Wszystkie szanse ({{ $opportunities->count() }})</div>
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text" placeholder="Szukaj szans..." oninput="filterTable('opps-tbody', this.value, [0,1])">
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="crm-table">
            <thead>
                <tr>
                    <th onclick="sortTable('opps-tbody',0)">Szansa <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('opps-tbody',1)">Firma <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('opps-tbody',2)">Etap <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('opps-tbody',3,true)">Wartość <span class="sort-icon">⇅</span></th>
                    <th>Przypisany</th>
                    <th style="text-align:center;">Akcje</th>
                </tr>
            </thead>
            <tbody id="opps-tbody">
                @foreach($opportunities as $opp)
                @php $meta = $stageMeta[$opp->stage] ?? $stageMeta['new_lead']; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $opp->title }}</td>
                    <td style="color:#888;font-size:12px;">{{ $opp->company?->name ?? '—' }}</td>
                    <td>
                        <span class="badge" style="background:{{ $meta['color'] }};color:{{ $meta['text'] }};">{{ $meta['label'] }}</span>
                    </td>
                    <td style="font-weight:700;color:#1A4D3A;">{{ $opp->value ? number_format($opp->value, 2, ',', ' ').' zł' : '—' }}</td>
                    <td style="font-size:12px;color:#555;">{{ $opp->assignedUser?->name ?? '—' }}</td>
                    <td style="text-align:center;">
                        <div style="display:flex;gap:4px;justify-content:center;">
                            <button class="btn-icon btn-icon-edit" title="Edytuj"
                                onclick="openEditOpp({{ $opp->id }}, '{{ addslashes($opp->title) }}', {{ $opp->company_id ?? 'null' }}, '{{ $opp->stage }}', {{ $opp->value ?? 'null' }}, '{{ $opp->expected_close_date?->format('Y-m-d') ?? '' }}', {{ $opp->assigned_to ?? 'null' }}, '{{ addslashes($opp->description ?? '') }}', '{{ addslashes($opp->notes ?? '') }}')"><i class="ti ti-pencil"></i></button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ TAB: ZADANIA ═══ --}}
@if($currentTab === 'tasks')

{{-- Moje zadania --}}
<div class="task-table-wrap mine">
    <div class="task-hdr mine">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:8px;height:8px;border-radius:50%;background:#1A4D3A;"></div>
            <span style="font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:#1A4D3A;">Moje zadania</span>
            <span style="font-size:12px;color:#888;">({{ $myTasks->count() }})</span>
        </div>
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text" placeholder="Szukaj..." oninput="filterTable('my-tasks-tbody', this.value, [0,1,2])">
        </div>
    </div>
    <table class="crm-table" style="background:#fff;">
        <thead>
            <tr>
                <th onclick="sortTable('my-tasks-tbody',0)">Zadanie <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('my-tasks-tbody',1)">Firma <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('my-tasks-tbody',2)">Przypisany <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('my-tasks-tbody',3)">Termin <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('my-tasks-tbody',4)">Priorytet <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('my-tasks-tbody',5)">Status <span class="sort-icon">⇅</span></th>
                <th style="text-align:center;">Akcje</th>
            </tr>
        </thead>
        <tbody id="my-tasks-tbody">
            @forelse($myTasks as $task)
            @php
                $overdue = $task->due_date && $task->due_date->isPast() && $task->status !== 'done';
            @endphp
            <tr>
                <td style="font-weight:600;">{{ $task->title }}</td>
                <td style="color:#888;font-size:12px;">{{ $task->company?->name ?? '—' }}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:26px;height:26px;border-radius:50%;background:#1A4D3A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;">
                            {{ strtoupper(substr($task->assignedUser?->name ?? '?', 0, 2)) }}
                        </div>
                        <span style="font-size:12px;">{{ $task->assignedUser?->name ?? '—' }}</span>
                    </div>
                </td>
                <td style="font-size:12px;color:{{ $overdue ? '#B91C1C' : '#888' }};font-weight:{{ $overdue ? '700' : '400' }};">
                    {{ $task->due_date?->format('d.m.Y') ?? '—' }}
                    @if($overdue)<span style="font-size:10px;margin-left:4px;">⚠</span>@endif
                </td>
                <td><span class="badge {{ $priorityMeta[$task->priority]['class'] }}">{{ $priorityMeta[$task->priority]['label'] }}</span></td>
                <td><span class="badge {{ $statusMeta[$task->status]['class'] }}">{{ $statusMeta[$task->status]['label'] }}</span></td>
                <td style="text-align:center;">
                    <div style="display:flex;gap:4px;justify-content:center;">
                        <button class="btn-icon btn-icon-edit" title="Edytuj" onclick="openEditTask({{ $task->id }}, @js($task->title), @js($task->description), {{ $task->assigned_to ?? 'null' }}, {{ $task->company_id ?? 'null' }}, @js($task->due_date?->format('Y-m-d')), '{{ $task->priority }}', '{{ $task->status }}')">
                            <i class="ti ti-pencil"></i>
                        </button>
                        <form method="POST" action="{{ route('crm.tasks.destroy', $task) }}" style="display:inline;" onsubmit="return confirm('Usunąć zadanie?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon btn-icon-delete" title="Usuń"><i class="ti ti-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:24px;text-align:center;color:#aaa;">Brak zadań</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($isAdmin)
{{-- Zadania zespołu --}}
<div class="task-table-wrap team">
    <div class="task-hdr team">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:#1A1A1A;">Zadania zespołu</span>
            <span style="font-size:12px;color:#888;">({{ $otherTasks->count() }})</span>
        </div>
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text" placeholder="Szukaj..." oninput="filterTable('team-tasks-tbody', this.value, [0,1,2])">
        </div>
    </div>
    <table class="crm-table" style="background:#fff;">
        <thead>
            <tr>
                <th onclick="sortTable('team-tasks-tbody',0)">Zadanie <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('team-tasks-tbody',1)">Firma <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('team-tasks-tbody',2)">Przypisany <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('team-tasks-tbody',3)">Termin <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('team-tasks-tbody',4)">Priorytet <span class="sort-icon">⇅</span></th>
                <th onclick="sortTable('team-tasks-tbody',5)">Status <span class="sort-icon">⇅</span></th>
                <th style="text-align:center;">Akcje</th>
            </tr>
        </thead>
        <tbody id="team-tasks-tbody">
            @forelse($otherTasks as $task)
            @php $overdue = $task->due_date && $task->due_date->isPast() && $task->status !== 'done'; @endphp
            <tr>
                <td style="font-weight:600;">{{ $task->title }}</td>
                <td style="color:#888;font-size:12px;">{{ $task->company?->name ?? '—' }}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:26px;height:26px;border-radius:50%;background:#94C4B0;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;">
                            {{ strtoupper(substr($task->assignedUser?->name ?? '?', 0, 2)) }}
                        </div>
                        <span style="font-size:12px;">{{ $task->assignedUser?->name ?? '—' }}</span>
                    </div>
                </td>
                <td style="font-size:12px;color:{{ $overdue ? '#B91C1C' : '#888' }};font-weight:{{ $overdue ? '700' : '400' }};">
                    {{ $task->due_date?->format('d.m.Y') ?? '—' }}
                    @if($overdue)<span style="font-size:10px;margin-left:4px;">⚠</span>@endif
                </td>
                <td><span class="badge {{ $priorityMeta[$task->priority]['class'] }}">{{ $priorityMeta[$task->priority]['label'] }}</span></td>
                <td><span class="badge {{ $statusMeta[$task->status]['class'] }}">{{ $statusMeta[$task->status]['label'] }}</span></td>
                <td style="text-align:center;">
                    <div style="display:flex;gap:4px;justify-content:center;">
                        <button class="btn-icon btn-icon-edit" title="Edytuj" onclick="openEditTask({{ $task->id }}, @js($task->title), @js($task->description), {{ $task->assigned_to ?? 'null' }}, {{ $task->company_id ?? 'null' }}, @js($task->due_date?->format('Y-m-d')), '{{ $task->priority }}', '{{ $task->status }}')">
                            <i class="ti ti-pencil"></i>
                        </button>
                        <form method="POST" action="{{ route('crm.tasks.destroy', $task) }}" style="display:inline;" onsubmit="return confirm('Usunąć zadanie?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon btn-icon-delete" title="Usuń"><i class="ti ti-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:24px;text-align:center;color:#aaa;">Brak zadań innych użytkowników</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif
@endif

{{-- ═══ TAB: ARCHIWUM ═══ --}}
@if($currentTab === 'archive')
<div class="archive-info">
    <i class="ti ti-info-circle"></i>
    Firmy w archiwum mają zachowane dane. Możesz je przywrócić lub trwale usunąć (tylko jeśli nie mają ofert ani audytów).
</div>
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title"><i class="ti ti-archive" style="color:#92400E;margin-right:6px;"></i> Archiwum firm ({{ $archivedCompanies->count() }})</div>
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text" placeholder="Szukaj w archiwum..." oninput="filterTable('archive-tbody', this.value, [0,1,2])">
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="crm-table">
            <thead>
                <tr>
                    <th onclick="sortTable('archive-tbody',0)">Firma <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('archive-tbody',1)">NIP <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('archive-tbody',2)">Miasto <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('archive-tbody',3,true)">Oferty <span class="sort-icon">⇅</span></th>
                    <th onclick="sortTable('archive-tbody',4,true)">Audyty <span class="sort-icon">⇅</span></th>
                    <th style="text-align:center;">Akcje</th>
                </tr>
            </thead>
            <tbody id="archive-tbody">
                @forelse($archivedCompanies as $company)
                <tr style="opacity:.85;">
                    <td>
                        <span style="font-weight:600;color:#555;">{{ $company->name }}</span>
                    </td>
                    <td style="color:#aaa;font-size:12px;">{{ $company->nip ?? '—' }}</td>
                    <td style="color:#888;">{{ $company->city ?? '—' }}</td>
                    <td style="color:#888;">{{ $company->offers->count() }}</td>
                    <td style="color:#888;">{{ $company->audits->count() }}</td>
                    <td style="text-align:center;">
                        <div style="display:flex;gap:4px;justify-content:center;">
                            <a href="{{ route('companies.show', $company) }}" class="btn-icon btn-icon-view" title="Podgląd">
                                <i class="ti ti-eye"></i>
                            </a>
                            <button type="button" class="btn-icon" title="Edytuj" style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;" onclick="openEditCompanyModal({{ $company->id }}, '{{ addslashes($company->name) }}', '{{ addslashes($company->nip ?? '') }}', '{{ addslashes($company->email ?? '') }}', '{{ addslashes($company->phone ?? '') }}', '{{ addslashes($company->address ?? '') }}', '{{ addslashes($company->city ?? '') }}', '{{ addslashes($company->source ?? '') }}', '{{ addslashes($company->notes ?? '') }}')">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('crm.companies.restore', $company) }}" style="display:inline;">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-icon btn-icon-restore" title="Przywróć">
                                    <i class="ti ti-arrow-back-up"></i>
                                </button>
                            </form>
                            @if($company->offers->count() === 0 && $company->audits->count() === 0)
                            <form method="POST" action="{{ route('crm.companies.destroy', $company) }}" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon btn-icon-delete" title="Usuń trwale"
                                    onclick="return confirm('Trwale usunąć firmę {{ addslashes($company->name) }}? Tej operacji nie można cofnąć.')">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding:40px;text-align:center;color:#aaa;">
                        <i class="ti ti-archive" style="font-size:40px;display:block;margin-bottom:8px;"></i>
                        Archiwum jest puste
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ MODAL: NOWA SZANSA ═══ --}}
<div id="modal-opp" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-family:'Manrope',sans-serif;font-size:16px;font-weight:700;"><i class="ti ti-target" style="color:#1A4D3A;margin-right:8px;"></i>Nowa szansa</div>
            <button onclick="document.getElementById('modal-opp').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;">×</button>
        </div>
        <form method="POST" action="{{ route('crm.opportunities.store') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label class="field-label" style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Tytuł *</label>
                <input type="text" name="title" required class="field-input" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Firma</label>
                    <select name="company_id" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— bez klienta —</option>
                        @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Etap</label>
                    <select name="stage" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        @foreach($stageMeta as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Wartość (zł)</label>
                    <input type="number" name="value" min="0" step="0.01" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Przypisany</label>
                    <select name="assigned_to" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— brak —</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Notatki</label>
                <textarea name="notes" rows="2" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;resize:none;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-opp').style.display='none'" class="btn-secondary">Anuluj</button>
                <button type="submit" class="btn-primary"><i class="ti ti-plus"></i> Dodaj szansę</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: NOWE ZADANIE ═══ --}}
<div id="modal-task" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-family:'Manrope',sans-serif;font-size:16px;font-weight:700;"><i class="ti ti-checklist" style="color:#1A4D3A;margin-right:8px;"></i>Nowe zadanie</div>
            <button onclick="document.getElementById('modal-task').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;">×</button>
        </div>
        <form method="POST" action="{{ route('crm.tasks.store') }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Tytuł *</label>
                <input type="text" name="title" required style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Przypisany</label>
                    <select name="assigned_to" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— brak —</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Firma</label>
                    <select name="company_id" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— brak —</option>
                        @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Termin</label>
                    <input type="date" name="due_date" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Priorytet</label>
                    <select name="priority" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="medium">Średni</option>
                        <option value="high">Wysoki</option>
                        <option value="low">Niski</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Status</label>
                    <select name="status" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="todo">Do zrobienia</option>
                        <option value="in_progress">W toku</option>
                        <option value="done">Zrobione</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Opis</label>
                <textarea name="description" rows="2" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;resize:none;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-task').style.display='none'" class="btn-secondary">Anuluj</button>
                <button type="submit" class="btn-primary"><i class="ti ti-plus"></i> Dodaj zadanie</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: EDYCJA ZADANIA ═══ --}}
<div id="modal-task-edit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-family:'Manrope',sans-serif;font-size:16px;font-weight:700;"><i class="ti ti-pencil" style="color:#1D4ED8;margin-right:8px;"></i>Edytuj zadanie</div>
            <button onclick="document.getElementById('modal-task-edit').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;">×</button>
        </div>
        <form method="POST" id="form-task-edit" action="">
            @csrf @method('PUT')
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Tytuł *</label>
                <input type="text" name="title" id="edit-task-title" required style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Przypisany</label>
                    <select name="assigned_to" id="edit-task-assigned" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— brak —</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Firma</label>
                    <select name="company_id" id="edit-task-company" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— brak —</option>
                        @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Termin</label>
                    <input type="date" name="due_date" id="edit-task-due" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Priorytet</label>
                    <select name="priority" id="edit-task-priority" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="medium">Średni</option>
                        <option value="high">Wysoki</option>
                        <option value="low">Niski</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Status</label>
                    <select name="status" id="edit-task-status" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="todo">Do zrobienia</option>
                        <option value="in_progress">W toku</option>
                        <option value="done">Zrobione</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Opis</label>
                <textarea name="description" id="edit-task-desc" rows="2" style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;resize:none;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-task-edit').style.display='none'" class="btn-secondary">Anuluj</button>
                <button type="submit" class="btn-primary"><i class="ti ti-check"></i> Zapisz zmiany</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-opp" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,.25);max-height:90vh;overflow-y:auto;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-family:'Manrope',sans-serif;font-size:16px;font-weight:700;"><i class="ti ti-target" style="color:#1A4D3A;margin-right:8px;"></i>Edytuj szansę</div>
            <button onclick="closeEditOpp()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;">×</button>
        </div>
        <form id="form-edit-opp" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" id="edit-opp-id" name="opp_id">

            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Tytuł *</label>
                <input type="text" id="edit-opp-title" name="title" required
                    style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Firma</label>
                    <select id="edit-opp-company" name="company_id"
                        style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        <option value="">— bez klienta —</option>
                        @foreach($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Etap</label>
                    <select id="edit-opp-stage" name="stage"
                        style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                        @foreach($stageMeta as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Wartość (zł)</label>
                    <input type="number" id="edit-opp-value" name="value" min="0" step="0.01"
                        style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Termin zamknięcia</label>
                    <input type="date" id="edit-opp-date" name="expected_close_date"
                        style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Przypisany</label>
                <select id="edit-opp-assigned" name="assigned_to"
                    style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;">
                    <option value="">— brak —</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Opis</label>
                <textarea id="edit-opp-description" name="description" rows="2"
                    style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;resize:none;box-sizing:border-box;"></textarea>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;font-family:'Manrope',sans-serif;">Notatki</label>
                <textarea id="edit-opp-notes" name="notes" rows="2"
                    style="width:100%;background:#FAFAF6;border:1px solid #D0CCC0;border-radius:7px;padding:8px 10px;font-size:13px;font-family:'Lato',sans-serif;outline:none;resize:none;box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeEditOpp()" class="btn-secondary">Anuluj</button>
                <button type="submit" class="btn-primary"><i class="ti ti-device-floppy"></i> Zapisz</button>
            </div>
        </form>

        <div style="margin-top:16px;">
            <form method="POST" id="form-delete-opp" style="margin:0;">
                @csrf
                @method('DELETE')
                <button type="submit" onclick="return confirm('Usunąć tę szansę?')"
                    style="display:inline-flex;align-items:center;gap:6px;background:#FEE2E2;color:#B91C1C;border:none;border-radius:7px;padding:8px 14px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">
                    <i class="ti ti-trash"></i> Usuń szansę
                </button>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: Edycja firmy --}}
<div id="modal-edit-company" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:100%;max-width:520px;box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden;">
        <div style="background:#1A4D3A;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
            <span style="color:#fff;font-size:16px;font-weight:700;"><i class="ti ti-building"></i> Edytuj firmę</span>
            <button onclick="closeEditCompanyModal()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <form id="form-edit-company" method="POST" style="padding:24px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit-company-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">Nazwa firmy *</label>
                    <input type="text" id="edit-name" name="name" required
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">NIP</label>
                    <input type="text" id="edit-nip" name="nip"
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">E-mail</label>
                    <input type="email" id="edit-email" name="email"
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">Telefon</label>
                    <input type="text" id="edit-phone" name="phone"
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">Adres</label>
                    <input type="text" id="edit-address" name="address"
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">Miasto</label>
                    <input type="text" id="edit-city" name="city"
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">Źródło</label>
                    <input type="text" id="edit-source" name="source" placeholder="np. polecenie, targi, LinkedIn..."
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;">Notatki</label>
                    <textarea id="edit-notes" name="notes" rows="3"
                        style="width:100%;margin-top:4px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
                <button type="button" onclick="closeEditCompanyModal()"
                    style="padding:9px 20px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">
                    Anuluj
                </button>
                <button type="submit"
                    style="padding:9px 22px;border:none;border-radius:8px;background:#1A4D3A;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">
                    <i class="ti ti-check"></i> Zapisz zmiany
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCompanyModal(id, name, nip, email, phone, address, city, source, notes) {
    document.getElementById('edit-company-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-nip').value = nip;
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-phone').value = phone;
    document.getElementById('edit-address').value = address;
    document.getElementById('edit-city').value = city;
    document.getElementById('edit-source').value = source;
    document.getElementById('edit-notes').value = notes;
    document.getElementById('form-edit-company').action = '/companies/' + id;
    document.getElementById('modal-edit-company').style.display = 'flex';
}
function closeEditCompanyModal() {
    document.getElementById('modal-edit-company').style.display = 'none';
}
document.getElementById('modal-edit-company').addEventListener('click', function(e) {
    if (e.target === this) closeEditCompanyModal();
});
</script>

@endsection

@push('scripts')
<script>
function toggleDashboard(companyId, btn) {
    fetch(`/crm/companies/${companyId}/dashboard`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(r => r.json())
    .then(data => {
        const on = data.show_in_dashboard;
        btn.style.background = on ? '#1A4D3A' : '#D1D5DB';
        btn.querySelector('.knob').style.left = on ? '19px' : '3px';
        const row = btn.closest('tr');
        if (row) row.dataset.dashboard = on ? 'yes' : 'no';
        filterDashboard();
    });
}

function filterDashboard() {
    const val = document.getElementById('filter-dashboard')?.value || 'all';
    const search = document.getElementById('search-companies')?.value.toLowerCase() || '';
    document.querySelectorAll('#companies-tbody tr').forEach(row => {
        const dashMatch = val === 'all' || row.dataset.dashboard === val;
        const text = row.textContent.toLowerCase();
        const searchMatch = !search || text.includes(search);
        row.style.display = dashMatch && searchMatch ? '' : 'none';
    });
}

function filterTable(tbodyId, query, cols) {
    const q = query.toLowerCase();
    document.querySelectorAll(`#${tbodyId} tr`).forEach(row => {
        const text = cols.map(i => row.cells[i]?.textContent || '').join(' ').toLowerCase();
        row.style.display = !q || text.includes(q) ? '' : 'none';
    });
}

const sortState = {};
function sortTable(tbodyId, colIdx, numeric = false) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const key = tbodyId + colIdx;
    sortState[key] = sortState[key] === 'asc' ? 'desc' : 'asc';
    const dir = sortState[key];
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        let av = a.cells[colIdx]?.textContent.trim() || '';
        let bv = b.cells[colIdx]?.textContent.trim() || '';
        if (numeric) { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; }
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1 : -1;
        return 0;
    });
    rows.forEach(r => tbody.appendChild(r));
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('modal-opp').style.display = 'none';
        document.getElementById('modal-task').style.display = 'none';
        document.getElementById('modal-task-edit').style.display = 'none';
    }
});

function openEditTask(id, title, description, assignedTo, companyId, dueDate, priority, status) {
    const baseUrl = '{{ url('/crm/tasks') }}/';
    document.getElementById('form-task-edit').action = baseUrl + id;
    document.getElementById('edit-task-title').value = title || '';
    document.getElementById('edit-task-desc').value = description || '';
    document.getElementById('edit-task-due').value = dueDate || '';
    const sel = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
    sel('edit-task-assigned', assignedTo);
    sel('edit-task-company', companyId);
    sel('edit-task-priority', priority);
    sel('edit-task-status', status);
    document.getElementById('modal-task-edit').style.display = 'flex';
}

function openEditOpp(id, title, companyId, stage, value, closeDate, assignedTo, description, notes) {
    document.getElementById('edit-opp-id').value = id;
    document.getElementById('edit-opp-title').value = title;
    document.getElementById('edit-opp-company').value = companyId || '';
    document.getElementById('edit-opp-stage').value = stage;
    document.getElementById('edit-opp-value').value = value || '';
    document.getElementById('edit-opp-date').value = closeDate || '';
    document.getElementById('edit-opp-assigned').value = assignedTo || '';
    document.getElementById('edit-opp-description').value = description || '';
    document.getElementById('edit-opp-notes').value = notes || '';

    document.getElementById('form-edit-opp').action = '/crm/opportunities/' + id;
    document.getElementById('form-delete-opp').action = '/crm/opportunities/' + id;

    document.getElementById('modal-edit-opp').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditOpp() {
    document.getElementById('modal-edit-opp').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('modal-edit-opp').addEventListener('click', function(e) {
    if (e.target === this) closeEditOpp();
});
</script>
@endpush
