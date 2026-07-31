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
        width: 30px; height: 30px;
        border-radius: 7px;
        text-decoration: none;
        font-size: 16px;
        transition: background .12s;
    }
    .btn-icon-view  { color: var(--green); background: #F0F7F3; }
    .btn-icon-view:hover  { background: #d4edde; }
    .btn-icon-edit  { color: #1D4ED8; background: #EFF6FF; }
    .btn-icon-edit:hover  { background: #DBEAFE; }
    .btn-icon-pdf   { color: #B91C1C; background: #FEF2F2; }
    .btn-icon-pdf:hover   { background: #FEE2E2; }

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
    .sort-icon { font-size:10px; color:#bbb; margin-left:3px; }
    .offers-table th { cursor:pointer; user-select:none; }
    .offers-table th:hover { color:var(--green); }
</style>
@endpush

@section('content')

@php
    $statusLabels = [
        'w_toku'         => 'W toku',
        'wygrana'        => 'Wygrana',
        'przegrana'      => 'Przegrana',
        'zarchiwizowana' => 'Zarchiwizowana',
    ];
    $activeStatus = request('status');
@endphp

{{-- Nagłówek strony --}}
<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
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
    <a href="{{ route('offers.create') }}" class="btn-primary">
        <i class="ti ti-plus"></i> Nowa oferta
    </a>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
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

{{-- Tabela --}}
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title">
            <i class="ti ti-list"></i>
            @if($activeStatus)
                {{ $statusLabels[$activeStatus] ?? $activeStatus }}
                <span style="font-weight:400;color:#888;">({{ $offers->total() }})</span>
            @else
                Wszystkie oferty
                <span style="font-weight:400;color:#888;">({{ $offers->total() }})</span>
            @endif
        </div>
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text" id="search-offers" placeholder="Szukaj po numerze, tytule, kliencie..." oninput="filterOffersTable(this.value)">
        </div>
    </div>

    @if($offers->isEmpty())
        <div class="empty-state">
            <div><i class="ti ti-file-off"></i></div>
            <p>Brak ofert{{ $activeStatus ? ' o tym statusie' : '' }}.</p>
            @if(!$activeStatus)
                <a href="{{ route('offers.create') }}" class="btn-primary" style="display:inline-flex;">
                    <i class="ti ti-plus"></i> Utwórz pierwszą ofertę
                </a>
            @endif
        </div>
    @else
        <div style="overflow-x:auto;">
            <table class="offers-table">
                <thead>
                    <tr>
                        <th onclick="sortOffersTable(0)">Numer oferty <span class="sort-icon">⇅</span></th>
                        <th onclick="sortOffersTable(1)">Tytuł <span class="sort-icon">⇅</span></th>
                        <th onclick="sortOffersTable(2)">Klient <span class="sort-icon">⇅</span></th>
                        <th onclick="sortOffersTable(3)">Prowadzi <span class="sort-icon">⇅</span></th>
                        <th onclick="sortOffersTable(4,true)">Kwota netto <span class="sort-icon">⇅</span></th>
                        <th onclick="sortOffersTable(5)">Status <span class="sort-icon">⇅</span></th>
                        <th onclick="sortOffersTable(6)">Data <span class="sort-icon">⇅</span></th>
                        <th style="text-align:center;">Akcje</th>
                    </tr>
                </thead>
                <tbody id="offers-tbody">
                    @foreach($offers as $offer)
                    <tr>
                        <td>
                            <a href="{{ route('offers.show', $offer) }}"
                               style="color:var(--green);font-weight:700;text-decoration:none;font-family:'Manrope',sans-serif;font-size:13px;">
                                {{ $offer->fullNumber() }}
                            </a>
                        </td>
                        <td style="color:#555;">{{ $offer->offer_title ?? '—' }}</td>
                        <td style="color:#333;">{{ $offer->company?->name ?? '—' }}</td>
                        <td style="color:#555;">{{ $offer->assignedUser?->name ?? '—' }}</td>
                        <td>
                            @if($offer->kwota_netto && $offer->kwota_netto > 0)
                                <span style="font-family:'Lato',sans-serif;font-weight:700;">
                                    {{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł
                                </span>
                            @else
                                <em style="color:#aaa;font-size:12px;">— brak —</em>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeClass = match($offer->status) {
                                    'w_toku'         => 'badge-blue',
                                    'wygrana'        => 'badge-green',
                                    'przegrana'      => 'badge-red',
                                    'zarchiwizowana' => 'badge-gray',
                                    default          => 'badge-gray',
                                };
                                $badgeLabel = match($offer->status) {
                                    'w_toku'         => 'W toku',
                                    'wygrana'        => 'Wygrana',
                                    'przegrana'      => 'Przegrana',
                                    'zarchiwizowana' => 'Zarchiwizowana',
                                    default          => $offer->status,
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        </td>
                        <td style="color:#666;font-size:13px;white-space:nowrap;">
                            {{ $offer->created_at->format('d.m.Y') }}
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="{{ route('offers.show', $offer) }}" class="btn-icon btn-icon-view" title="Podgląd">
                                <i class="ti ti-eye"></i>
                            </a>
                            <button type="button" onclick="openPdfModal('{{ route('offers.pdf', $offer) }}', 'Oferta {{ $offer->offer_full_number }}')" class="btn-icon btn-icon-pdf" title="Podgląd PDF" style="margin-left:4px;border:none;cursor:pointer;">
                                <i class="ti ti-file-type-pdf"></i>
                            </button>
                            <form method="POST" action="{{ route('offers.save-to-storage', $offer) }}" style="display:inline;margin-left:4px;">
                                @csrf
                                <button type="submit" class="btn-icon" style="background:#EFF6FF;color:#2563EB;border:none;cursor:pointer;" title="Zapisz na dysku">
                                    <i class="ti ti-device-floppy"></i>
                                </button>
                            </form>
                            <a href="{{ route('offers.edit', $offer) }}" class="btn-icon btn-icon-edit" title="Edytuj" style="margin-left:4px;">
                                <i class="ti ti-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('offers.clone', $offer) }}" style="display:inline;margin-left:4px;">
                                @csrf
                                <input type="hidden" name="mode" value="offer">
                                <button type="submit"
                                        style="background:#F0F7F3;border:none;cursor:pointer;color:var(--green);padding:6px 7px;border-radius:6px;display:inline-flex;align-items:center;"
                                        title="Kopiuj ofertę">
                                    <i class="ti ti-copy"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('offers.destroy', $offer) }}"
                                  onsubmit="return confirm('Czy na pewno chcesz usunąć ofertę {{ $offer->offer_full_number }}?')"
                                  style="display:inline;margin-left:4px;">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        style="background:none;border:none;cursor:pointer;color:#DC2626;padding:4px 6px;border-radius:6px;display:inline-flex;align-items:center;"
                                        title="Usuń ofertę">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($offers->hasPages())
            <div style="padding:16px 20px;border-top:1px solid #F0EDE6;">
                {{ $offers->links() }}
            </div>
        @endif
    @endif
</div>

@endsection

@push('scripts')
<script>
function filterOffersTable(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#offers-tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = !q || text.includes(q) ? '' : 'none';
    });
}

const offersSortState = {};
function sortOffersTable(colIdx, numeric = false) {
    const tbody = document.getElementById('offers-tbody');
    if (!tbody) return;
    offersSortState[colIdx] = offersSortState[colIdx] === 'asc' ? 'desc' : 'asc';
    const dir = offersSortState[colIdx];
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        let av = a.cells[colIdx]?.textContent.trim() || '';
        let bv = b.cells[colIdx]?.textContent.trim() || '';
        if (numeric) {
            av = parseFloat(av.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
            bv = parseFloat(bv.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
        }
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1 : -1;
        return 0;
    });
    rows.forEach(r => tbody.appendChild(r));
}
</script>
@endpush
