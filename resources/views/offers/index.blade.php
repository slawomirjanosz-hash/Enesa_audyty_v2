@extends('layouts.app')

@section('page-title', 'Oferty')

@push('styles')
<style>
    /* ── Stat cards ─────────────────────────────────── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: #fff;
        border: 2px solid #E5E1D8;
        border-radius: 12px;
        padding: 18px 20px;
        text-decoration: none;
        display: block;
        transition: border-color .15s, box-shadow .15s;
    }
    .stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .stat-card.active { border-color: var(--sc); background: var(--sc-bg); }
    .stat-card-count {
        font-family: 'Lato', sans-serif;
        font-size: 28px;
        font-weight: 900;
        color: var(--sc);
        line-height: 1;
    }
    .stat-card-label {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        color: #666;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-top: 4px;
    }
    .stat-card-icon {
        font-size: 20px;
        color: var(--sc);
        margin-bottom: 8px;
    }

    /* ── Table ──────────────────────────────────────── */
    .offers-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Lato', sans-serif;
        font-size: 14px;
    }
    .offers-table th {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        color: #666;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 10px 14px;
        text-align: left;
        border-bottom: 2px solid #E5E1D8;
        background: #FAFAF6;
        white-space: nowrap;
    }
    .offers-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #F0EDE6;
        color: #1A1A1A;
        vertical-align: middle;
    }
    .offers-table tr:last-child td { border-bottom: none; }
    .offers-table tr:hover td { background: #FAFAF6; }

    /* ── Badges ─────────────────────────────────────── */
    .badge {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        font-family: 'Manrope', sans-serif;
        white-space: nowrap;
    }
    .badge-blue   { background: #DBEAFE; color: #1D4ED8; }
    .badge-green  { background: #DCFCE7; color: #166534; }
    .badge-red    { background: #FEE2E2; color: #B91C1C; }
    .badge-gray   { background: #F3F4F6; color: #4B5563; }

    /* ── Action buttons ─────────────────────────────── */
    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 27px; height: 27px;
        border-radius: 7px;
        text-decoration: none;
        font-size: 15px;
        border: 0;
        cursor: pointer;
        transition: background .12s;
    }
    .btn-icon-edit  { color: #1D4ED8; background: #EFF6FF; }
    .btn-icon-edit:hover  { background: #DBEAFE; }
    .btn-icon-pdf   { color: #B91C1C; background: #FEF2F2; }
    .btn-icon-pdf:hover   { background: #FEE2E2; }
    .offer-amount { font-family:'Lato',sans-serif; font-weight:700; white-space:nowrap; }
    .offer-actions-cell { text-align:center; white-space:nowrap; }
    .offer-actions { display:inline-flex; align-items:center; gap:4px; }
    .offer-more-actions { width:38px; height:27px; border:1px solid #cfd8d2; border-radius:6px; background:#fff; color:var(--green); cursor:pointer; }

    /* ── Card wrapper ───────────────────────────────── */
    .table-card {
        background: #fff;
        border: 1px solid #E5E1D8;
        border-radius: 12px;
        overflow: hidden;
    }
    .table-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid #F0EDE6;
        background: #FAFAF6;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .table-card-title {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #1A1A1A;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .table-card-title i { color: var(--green); }

    /* ── Empty state ────────────────────────────────── */
    .empty-state {
        padding: 60px 20px;
        text-align: center;
        color: #888;
    }
    .empty-state i { font-size: 48px; color: #D0CCC0; margin-bottom: 12px; }
    .empty-state p { font-family: 'Manrope', sans-serif; font-size: 14px; margin-bottom: 16px; }

    /* ── Btn primary ────────────────────────────────── */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--green);
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        padding: 9px 16px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-primary:hover { background: #143d2d; color: #F5F0E8; }

    /* ── Active filter pill ─────────────────────────── */
    .filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #F0F7F3;
        border: 1px solid #94C4B0;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 12px;
        font-family: 'Manrope', sans-serif;
        color: var(--green);
        text-decoration: none;
    }
    .filter-pill:hover { background: #d4edde; }

    @media (max-width: 900px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 500px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .table-card-header { align-items: flex-start; flex-direction: column; gap: 10px; }
        .table-card-header .search-box { width: 100%; }
        .search-box input { width: 100%; }
        .offers-table { min-width: 820px; }
    }

    .search-box { position:relative; }
    .search-box input { font-size:12px; padding:6px 10px 6px 30px; border-radius:6px; border:1px solid #D0CCC0; outline:none; width:220px; font-family:'Lato',sans-serif; }
    .search-box input:focus { border-color:var(--green); }
    .search-box i { position:absolute; left:8px; top:50%; transform:translateY(-50%); color:#aaa; font-size:15px; }
    .offer-status-select { field-sizing:content; max-width:130px; padding:2px 5px; border:0; cursor:pointer; text-overflow:ellipsis; }
    .offers-table th { cursor:pointer; user-select:none; }
    .offers-table th:hover { color:var(--green); }
</style>
@endpush

@section('content')

{{-- Nagłówek strony --}}
@include('partials.header-search-styles')
<div class="app-search-header">
    <div>
        <h1 style="font-family:'Manrope',sans-serif;font-size:22px;font-weight:700;color:var(--green);margin:0;">
            <i class="ti ti-file-invoice" style="margin-right:8px;"></i>Oferty
        </h1>
        @if($activeStatus)
            <div style="margin-top:6px;">
                <a href="{{ route('offers.index') }}" class="filter-pill">
                    <i class="ti ti-x" style="font-size:11px;"></i>
                    Filtr: {{ $statusLabels[$activeStatus] ?? $activeStatus }}
                </a>
            </div>
        @endif
    </div>
    <div class="app-search-toolbar">
    @include('partials.list-search', ['serverSearch' => true, 'searchDelay' => 1500, 'searchResetParameters' => ['archive_page'], 'searchPlaceholder' => 'Szukaj: numer, oferta, klient, osoba…'])
    <a href="{{ route('offers.create') }}" class="btn-primary">
        <i class="ti ti-plus"></i> Nowa oferta
    </a>
    </div>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif

@if($errors->has('status') || $errors->has('new_status'))
    <div role="alert" style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
        {{ $errors->first('new_status') ?: $errors->first('status') }}
    </div>
@endif

{{-- Karty statystyk --}}
<div class="stats-grid">
    <a href="{{ route('offers.index', ['status' => 'w_toku']) }}"
       class="stat-card {{ $activeStatus === 'w_toku' ? 'active' : '' }}"
       style="--sc:#1D4ED8;--sc-bg:#EFF6FF;">
        <div class="stat-card-icon"><i class="ti ti-clock"></i></div>
        <div class="stat-card-count">{{ $stats['w_toku'] }}</div>
        <div class="stat-card-label">W toku</div>
    </a>
    <a href="{{ route('offers.index', ['status' => 'wygrana']) }}"
       class="stat-card {{ $activeStatus === 'wygrana' ? 'active' : '' }}"
       style="--sc:#166534;--sc-bg:#F0FDF4;">
        <div class="stat-card-icon"><i class="ti ti-trophy"></i></div>
        <div class="stat-card-count">{{ $stats['wygrana'] }}</div>
        <div class="stat-card-label">Wygrane</div>
    </a>
    <a href="{{ route('offers.index', ['status' => 'przegrana']) }}"
       class="stat-card {{ $activeStatus === 'przegrana' ? 'active' : '' }}"
       style="--sc:#B91C1C;--sc-bg:#FEF2F2;">
        <div class="stat-card-icon"><i class="ti ti-x"></i></div>
        <div class="stat-card-count">{{ $stats['przegrana'] }}</div>
        <div class="stat-card-label">Przegrane</div>
    </a>
    <a href="{{ route('offers.index', ['status' => 'zarchiwizowana']) }}"
       class="stat-card {{ $activeStatus === 'zarchiwizowana' ? 'active' : '' }}"
       style="--sc:#4B5563;--sc-bg:#F9FAFB;">
        <div class="stat-card-icon"><i class="ti ti-archive"></i></div>
        <div class="stat-card-count">{{ $stats['zarchiwizowana'] }}</div>
        <div class="stat-card-label">Zarchiwizowane</div>
    </a>
</div>

@if($activeStatus !== 'zarchiwizowana')
    @include('offers.partials.index-table', ['rows' => $offers, 'isArchive' => false])
@endif

@if($archivedOffers !== null)
    @include('offers.partials.index-table', ['rows' => $archivedOffers, 'isArchive' => true])
@endif

@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-offer-action]').forEach(select => {
    select.addEventListener('change', () => {
        const action = select.value;
        select.value = '';
        if (!action) return;
        const forms = select.closest('.offer-actions').querySelectorAll('form[data-action]');
        [...forms].find(form => form.dataset.action === action)?.requestSubmit();
    });
});

document.querySelectorAll('[data-offer-status-select]').forEach(select => {
    select.addEventListener('change', () => {
        const form = select.form;
        if (select.value === '__add') {
            select.value = select.dataset.currentStatus;
            const name = window.prompt('Wpisz nazwę nowego statusu oferty:')?.trim();
            if (!name) return;
            form.elements.new_status.value = name;
        } else {
            form.elements.new_status.value = '';
        }
        form.requestSubmit();
    });
});
</script>
@endpush
