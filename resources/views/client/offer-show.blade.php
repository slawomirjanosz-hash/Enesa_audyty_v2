@extends('layouts.client')

@section('title', $offer->offer_full_number ?? $offer->offer_number)
@section('page-title', $offer->offer_full_number ?? $offer->offer_number)

@push('styles')
<style>
    /* Back button */
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #5a6a60;
        text-decoration: none;
        margin-bottom: 20px;
    }
    .back-btn:hover { color: var(--green); }

    /* Offer header */
    .offer-header {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .offer-header h1 {
        font-family: 'Manrope', sans-serif;
        font-size: 20px;
        font-weight: 800;
        color: var(--green);
        margin: 0;
    }
    .status-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .badge-w-toku    { background: #DBEAFE; color: #1D4ED8; }
    .badge-wygrana   { background: #DCFCE7; color: #166534; }
    .badge-przegrana { background: #FEE2E2; color: #B91C1C; }
    .badge-other     { background: #F3F4F6; color: #4B5563; }

    /* Action banners */
    .banner-accept {
        background: #F0FDF4;
        border: 1.5px solid #86EFAC;
        border-radius: 12px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }
    .banner-accept-text h3 {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #166534;
        margin: 0 0 4px;
    }
    .banner-accept-text p {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        color: #15803D;
        margin: 0;
    }
    .btn-accept {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--green);
        color: #F4F1EA;
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 700;
        padding: 10px 22px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        flex-shrink: 0;
    }
    .btn-accept:hover { background: #15402f; }

    .banner-accepted {
        background: #DCFCE7;
        border: 1.5px solid #86EFAC;
        border-radius: 12px;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
    }
    .banner-accepted i {
        font-size: 22px;
        color: #166534;
        flex-shrink: 0;
    }
    .banner-accepted span {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #166534;
    }

    /* Content card */
    .offer-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 12px;
        margin-bottom: 16px;
        overflow: hidden;
    }
    .offer-card-header {
        background: #F4F1EA;
        padding: 12px 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        color: var(--green);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .offer-card-body {
        padding: 18px 20px;
    }

    /* Meta row */
    .meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        padding: 18px 20px;
    }
    .meta-item label {
        display: block;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 4px;
    }
    .meta-item span {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 600;
        color: #1A1A1A;
    }

    /* Offer title (large) */
    .offer-main-title {
        font-family: 'Manrope', sans-serif;
        font-size: 18px;
        font-weight: 700;
        color: #1A1A1A;
        padding: 20px 20px 0;
        margin-bottom: 4px;
    }

    /* Rich content sections */
    .content-section {
        padding: 18px 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #2A2A2A;
        line-height: 1.7;
        border-top: 0.5px solid #F0EDE6;
    }
    .content-section:first-child {
        border-top: none;
    }
    .content-section h4 {
        font-size: 12px;
        font-weight: 700;
        color: var(--green);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 10px;
    }
    .content-section .content-body { margin: 0; }
    .content-section .content-body p { margin: 0 0 8px; }
    .content-section .content-body p:last-child { margin-bottom: 0; }

    /* Summary */
    .summary-card {
        background: var(--green);
        border-radius: 12px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .summary-label {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #A8D4BE;
    }
    .summary-amount {
        font-family: 'Lato', sans-serif;
        font-size: 32px;
        font-weight: 900;
        color: #F4F1EA;
        line-height: 1;
    }
    .summary-vat {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        color: #A8D4BE;
        margin-top: 4px;
    }

    /* Action buttons row */
    .actions-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 24px;
        padding: 16px 20px;
        background: #F4F1EA;
        border-radius: 10px;
        border: 1px solid #E5E1D8;
    }
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff;
        color: var(--green);
        border: 1.5px solid #C8DDD4;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        padding: 9px 18px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-secondary:hover { background: #F4F1EA; border-color: var(--green); }
    
    .btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #FEE2E2;
        color: #B91C1C;
        border: 1.5px solid #FECACA;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        padding: 9px 18px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-danger:hover { background: #FCA5A5; border-color: #B91C1C; }
    
    .btn-warning {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #FEF3C7;
        color: #92400E;
        border: 1.5px solid #FCD34D;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        padding: 9px 18px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-warning:hover { background: #FCD34D; border-color: #92400E; }

    /* Flash messages */
    .flash-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        border-radius: 10px;
        padding: 12px 18px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #166534;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .flash-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        border-radius: 10px;
        padding: 12px 18px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #B91C1C;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media print {
        * { margin: 0 !important; padding: 0 !important; }
        body { background: white !important; }
        .back-btn, .banner-accept, .banner-accepted, .actions-row, .flash-success, .flash-error { display: none !important; }
        .offer-card, .summary-card { border: 1px solid #999 !important; page-break-inside: avoid; }
        .offer-header, .offer-main-title, .meta-grid, .content-section, .summary-card { page-break-inside: avoid; }
        .offer-card-header { background: #f0f0f0 !important; }
        .summary-card { background: #f9f9f9 !important; color: #000 !important; }
        .summary-amount { color: #000 !important; }
        .banner-accept-text { display: block !important; }
    }

    @media (max-width: 600px) {
        .banner-accept { flex-direction: column; align-items: flex-start; }
        .summary-card  { flex-direction: column; }
    }
</style>
@endpush

@section('content')

{{-- Back button --}}
<a href="{{ route('client.offers') }}" class="back-btn">
    <i class="ti ti-arrow-left"></i> Powrót do ofert
</a>

{{-- Flash messages --}}
@if(session('success'))
    <div class="flash-success"><i class="ti ti-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="flash-error"><i class="ti ti-alert-circle"></i> {{ session('error') }}</div>
@endif

{{-- Offer header --}}
<div class="offer-header">
    <h1>{{ $offer->offer_full_number ?? $offer->offer_number }}</h1>
    @php
        [$badgeClass, $statusLabel] = match($offer->status) {
            'w_toku'         => ['badge-w-toku',    'W toku'],
            'wygrana'        => ['badge-wygrana',   'Zaakceptowana'],
            'przegrana'      => ['badge-przegrana', 'Odrzucona'],
            'w_negocjacji'   => ['badge-other',     'W negocjacji'],
            'zarchiwizowana' => ['badge-other',     'Archiwalna'],
            default          => ['badge-other',      $offer->status],
        };
    @endphp
    <span class="status-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
</div>

{{-- Action buttons (top) --}}
<div class="actions-row">
    <a href="{{ route('offers.pdf', $offer) }}" target="_blank" class="btn-secondary">
        <i class="ti ti-file-type-pdf"></i> Pobierz PDF
    </a>
    <button type="button" onclick="window.print()" class="btn-secondary">
        <i class="ti ti-printer"></i> Drukuj
    </button>
</div>

{{-- Action banner --}}
@if($offer->status === 'w_toku')
    <div class="banner-accept">
        <div class="banner-accept-text">
            <h3>Czy akceptujesz tę ofertę?</h3>
            <p>Po akceptacji nasz zespół skontaktuje się z Tobą w celu realizacji.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <form method="POST" action="{{ route('client.offers.accept', $offer) }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn-accept">
                    <i class="ti ti-circle-check"></i> Zaakceptuj
                </button>
            </form>
            <form method="POST" action="{{ route('client.offers.negotiate', $offer) }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn-warning">
                    <i class="ti ti-message-circle"></i> Negocjuj
                </button>
            </form>
            <form method="POST" action="{{ route('client.offers.reject', $offer) }}" style="display: inline;" onsubmit="return confirm('Czy na pewno chcesz odrzucić tę ofertę?');">
                @csrf
                <button type="submit" class="btn-danger">
                    <i class="ti ti-circle-x"></i> Odrzuć
                </button>
            </form>
        </div>
    </div>
@elseif($offer->status === 'wygrana')
    <div class="banner-accepted">
        <i class="ti ti-rosette-discount-check"></i>
        <span>Oferta zaakceptowana — dziękujemy! Nasz zespół skontaktuje się z Tobą wkrótce.</span>
    </div>
@elseif($offer->status === 'w_negocjacji')
    <div class="banner-accepted" style="background: #FEF3C7; border-color: #FCD34D;">
        <i class="ti ti-message-circle" style="color: #92400E;"></i>
        <span style="color: #92400E;">Oferta wysłana do negocjacji — czekamy na Twoją odpowiedź.</span>
    </div>
@elseif($offer->status === 'przegrana')
    <div class="banner-accepted" style="background: #FEE2E2; border-color: #FCA5A5;">
        <i class="ti ti-circle-x" style="color: #B91C1C;"></i>
        <span style="color: #B91C1C;">Oferta została odrzucona.</span>
    </div>
@endif

{{-- Meta info --}}
<div class="offer-card">
    <div class="meta-grid">
        @if($offer->offer_title)
            <div class="meta-item" style="grid-column: 1 / -1;">
                <label>Tytuł oferty</label>
                <span style="font-size:16px;font-weight:700;color:var(--green);">{{ $offer->offer_title }}</span>
            </div>
        @endif
        @if($offer->valid_until)
            <div class="meta-item">
                <label>Ważna do</label>
                <span>{{ $offer->valid_until->format('d.m.Y') }}</span>
            </div>
        @endif
        @if($offer->assignedUser)
            <div class="meta-item">
                <label>Opiekun</label>
                <span>{{ $offer->assignedUser->name }}</span>
            </div>
        @endif
        <div class="meta-item">
            <label>Data wystawienia</label>
            <span>{{ $offer->created_at->format('d.m.Y') }}</span>
        </div>
    </div>
</div>

{{-- Content sections --}}
@if($offer->content_subject || $offer->content_scope || $offer->content_deadline || $offer->content_payment)
<div class="offer-card">
    @if($offer->content_subject)
        <div class="content-section">
            <h4>Przedmiot oferty</h4>
            <div class="content-body">{!! $offer->content_subject !!}</div>
        </div>
    @endif
    @if($offer->content_scope)
        <div class="content-section">
            <h4>Zakres prac</h4>
            <div class="content-body">{!! $offer->content_scope !!}</div>
        </div>
    @endif
    @if($offer->content_deadline)
        <div class="content-section">
            <h4>Termin realizacji</h4>
            <div class="content-body">{!! $offer->content_deadline !!}</div>
        </div>
    @endif
    @if($offer->content_payment)
        <div class="content-section">
            <h4>Warunki płatności</h4>
            <div class="content-body">{!! $offer->content_payment !!}</div>
        </div>
    @endif
</div>
@endif

{{-- Summary --}}
@if($offer->kwota_netto !== null)
<div class="summary-card">
    <div>
        <div class="summary-label">Wartość oferty netto</div>
        <div class="summary-amount">{{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł</div>
        <div class="summary-vat">+ VAT zgodnie z obowiązującymi przepisami</div>
    </div>
</div>
@endif

@endsection
