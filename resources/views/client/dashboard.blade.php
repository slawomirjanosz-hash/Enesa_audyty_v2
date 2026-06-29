@extends('layouts.client')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
    .welcome-block { margin-bottom: 28px; }
    .welcome-block h1 {
        font-family: 'Manrope', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A4D3A;
        margin-bottom: 4px;
    }
    .welcome-block p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #5a6a60;
        margin: 0;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 32px;
    }
    .stat-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 10px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 20px;
    }
    .stat-icon-green  { background: #E8F5E9; color: #2E7D32; }
    .stat-icon-blue   { background: #E3F2FD; color: #1565C0; }
    .stat-icon-orange { background: #FFF3E0; color: #E65100; }
    .stat-value {
        font-family: 'Lato', sans-serif;
        font-size: 28px;
        font-weight: 900;
        color: #1A1A1A;
        line-height: 1;
        margin-bottom: 3px;
    }
    .stat-label {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #555;
    }

    /* Sections */
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .section-title {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #1A4D3A;
        margin: 0;
    }
    .section-link {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #1A4D3A;
        text-decoration: none;
        opacity: 0.75;
    }
    .section-link:hover { opacity: 1; text-decoration: underline; }
    .section-block { margin-bottom: 32px; }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 40px 24px;
        background: #fff;
        border: 1px dashed #D0CCC0;
        border-radius: 12px;
    }
    .empty-state i {
        font-size: 36px;
        color: #C8DDD4;
        display: block;
        margin-bottom: 10px;
    }
    .empty-state p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #888;
        margin: 0 0 14px;
    }
    .empty-state a {
        display: inline-block;
        background: #1A4D3A;
        color: #F4F1EA;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
    }
    .empty-state a:hover { background: #15402f; }

    /* Offer cards */
    .offer-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 10px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 10px;
    }
    .offer-card:last-child { margin-bottom: 0; }
    .offer-card:hover { border-color: #B8D4C8; box-shadow: 0 2px 8px rgba(26,77,58,0.06); }
    .offer-number {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        color: #1A4D3A;
        margin-bottom: 2px;
    }
    .offer-title-text {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: #1A1A1A;
        margin-bottom: 2px;
    }
    .offer-date {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        color: #888;
    }
    .offer-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
        flex-shrink: 0;
    }
    .offer-amount {
        font-family: 'Lato', sans-serif;
        font-size: 15px;
        font-weight: 800;
        color: #1A1A1A;
    }

    /* Request rows */
    .req-row {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 10px;
        padding: 12px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 8px;
    }
    .req-row:last-child { margin-bottom: 0; }
    .req-name {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #1A1A1A;
        margin-bottom: 2px;
    }
    .req-date {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        color: #888;
    }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .badge-w-toku         { background: #DBEAFE; color: #1D4ED8; }
    .badge-wygrana        { background: #DCFCE7; color: #166534; }
    .badge-przegrana      { background: #FEE2E2; color: #B91C1C; }
    .badge-zarchiwizowana { background: #F3F4F6; color: #4B5563; }
    .badge-req-nowe       { background: #DBEAFE; color: #1D4ED8; }
    .badge-req-w-toku     { background: #FEF3C7; color: #92400E; }
    .badge-req-zamkniete  { background: #DCFCE7; color: #166534; }

    @media (max-width: 900px)  { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 540px)  { .stats-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

{{-- Welcome --}}
<div class="welcome-block">
    <h1>Witaj, {{ auth()->user()->name }}</h1>
    <p>{{ $company->name }}</p>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-green"><i class="ti ti-file-invoice"></i></div>
        <div>
            <div class="stat-value">{{ $offers->count() }}</div>
            <div class="stat-label">Oferty</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue"><i class="ti ti-inbox"></i></div>
        <div>
            <div class="stat-value">{{ $offerRequests->count() }}</div>
            <div class="stat-label">Zapytania</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange"><i class="ti ti-bell"></i></div>
        <div>
            <div class="stat-value">{{ $offerRequests->where('status', 'nowe')->count() }}</div>
            <div class="stat-label">Nowe zapytania</div>
        </div>
    </div>
</div>

{{-- Last offers --}}
<div class="section-block">
    <div class="section-header">
        <span class="section-title">Ostatnie oferty</span>
        @if($offers->count() > 3)
            <a href="{{ route('client.offers') }}" class="section-link">Zobacz wszystkie →</a>
        @endif
    </div>

    @if($offers->isEmpty())
        <div class="empty-state">
            <i class="ti ti-file-off"></i>
            <p>Nie masz jeszcze żadnych ofert.</p>
            <a href="{{ route('client.request-offer') }}">Złóż zapytanie ofertowe</a>
        </div>
    @else
        @foreach($offers->take(3) as $offer)
        @php
            $offerBadgeClass = match($offer->status) {
                'w_toku'         => 'badge-w-toku',
                'wygrana'        => 'badge-wygrana',
                'przegrana'      => 'badge-przegrana',
                'zarchiwizowana' => 'badge-zarchiwizowana',
                default          => 'badge-zarchiwizowana',
            };
            $offerStatusLabel = match($offer->status) {
                'w_toku'         => 'W toku',
                'wygrana'        => 'Zaakceptowana',
                'przegrana'      => 'Odrzucona',
                'zarchiwizowana' => 'Archiwalna',
                default          => $offer->status,
            };
        @endphp
        <div class="offer-card">
            <div>
                <div class="offer-number">{{ $offer->offer_full_number ?? $offer->offer_number }}</div>
                @if($offer->offer_title)
                    <div class="offer-title-text">{{ $offer->offer_title }}</div>
                @endif
                <div class="offer-date">{{ $offer->created_at->format('d.m.Y') }}</div>
            </div>
            <div class="offer-right">
                @if($offer->kwota_netto !== null)
                    <span class="offer-amount">{{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł</span>
                @endif
                <span class="badge {{ $offerBadgeClass }}">{{ $offerStatusLabel }}</span>
            </div>
        </div>
        @endforeach
    @endif
</div>

{{-- Requests --}}
<div class="section-block">
    <div class="section-header">
        <span class="section-title">Moje zapytania</span>
        @if($offerRequests->count() > 5)
            <a href="{{ route('client.request-offer') }}" class="section-link">Zobacz wszystkie →</a>
        @endif
    </div>

    @if($offerRequests->isEmpty())
        <div class="empty-state">
            <i class="ti ti-inbox-off"></i>
            <p>Brak zapytań.</p>
        </div>
    @else
        @foreach($offerRequests->take(5) as $req)
        @php
            $reqBadgeClass = match($req->status) {
                'nowe'      => 'badge-req-nowe',
                'w_toku'    => 'badge-req-w-toku',
                'zamknięte' => 'badge-req-zamkniete',
                default     => 'badge-req-nowe',
            };
            $reqStatusLabel = match($req->status) {
                'nowe'      => 'Nowe',
                'w_toku'    => 'W toku',
                'zamknięte' => 'Zamknięte',
                default     => $req->status,
            };
        @endphp
        <div class="req-row">
            <div>
                <div class="req-name">{{ $req->offerFormTemplate?->name ?? 'Zapytanie #' . $req->id }}</div>
                <div class="req-date">{{ $req->created_at->format('d.m.Y H:i') }}</div>
            </div>
            <span class="badge {{ $reqBadgeClass }}">{{ $reqStatusLabel }}</span>
        </div>
        @endforeach
    @endif
</div>

@endsection

