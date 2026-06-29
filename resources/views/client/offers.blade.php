@extends('layouts.client')

@section('title', 'Oferty')
@section('page-title', 'Oferty')

@push('styles')
<style>
    .page-header {
        margin-bottom: 24px;
    }
    .page-header h1 {
        font-family: 'Manrope', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A4D3A;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .page-header p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #5a6a60;
        margin: 0;
    }

    .empty-state {
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

    .offers-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .offer-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 12px;
        padding: 18px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .offer-card:hover {
        border-color: #B8D4C8;
        box-shadow: 0 2px 8px rgba(26,77,58,0.06);
    }
    a.offer-card {
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }
    .offer-left {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .offer-number {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        color: #1A4D3A;
    }
    .offer-title {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 500;
        color: #1A1A1A;
    }
    .offer-date {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        color: #888;
    }
    .offer-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex-shrink: 0;
    }
    .offer-amount {
        font-family: 'Lato', sans-serif;
        font-size: 17px;
        font-weight: 800;
        color: #1A1A1A;
    }
    .offer-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
    }
    .badge-w-toku        { background: #DBEAFE; color: #1D4ED8; }
    .badge-wygrana       { background: #DCFCE7; color: #166534; }
    .badge-przegrana     { background: #FEE2E2; color: #B91C1C; }
    .badge-zarchiwizowana{ background: #F3F4F6; color: #4B5563; }

    @media (max-width: 640px) {
        .offer-card { flex-direction: column; align-items: flex-start; }
        .offer-right { align-items: flex-start; }
    }
</style>
@endpush

@section('content')

<div class="page-header">
    <h1><i class="ti ti-file-invoice"></i> Oferty</h1>
    <p>Oferty przygotowane dla Twojej firmy przez ENESA</p>
</div>

@if($offers->isEmpty())
    <div class="empty-state">
        <i class="ti ti-file-off"></i>
        <p>Brak ofert dla Twojej firmy.</p>
    </div>
@else
    <div class="offers-list">
        @foreach($offers as $offer)
        @php
            $badgeClass = match($offer->status) {
                'w_toku'         => 'badge-w-toku',
                'wygrana'        => 'badge-wygrana',
                'przegrana'      => 'badge-przegrana',
                'zarchiwizowana' => 'badge-zarchiwizowana',
                default          => 'badge-zarchiwizowana',
            };
            $statusLabel = match($offer->status) {
                'w_toku'         => 'W toku',
                'wygrana'        => 'Zaakceptowana',
                'przegrana'      => 'Odrzucona',
                'zarchiwizowana' => 'Archiwalna',
                default          => $offer->status,
            };
        @endphp
        <a href="{{ route('client.offers.show', $offer) }}" class="offer-card">
            <div class="offer-left">
                <span class="offer-number">{{ $offer->offer_full_number ?? $offer->offer_number }}</span>
                @if($offer->offer_title)
                    <span class="offer-title">{{ $offer->offer_title }}</span>
                @endif
                <span class="offer-date">{{ $offer->created_at->format('d.m.Y') }}</span>
            </div>
            <div class="offer-right">
                @if($offer->kwota_netto !== null)
                    <span class="offer-amount">{{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł</span>
                @endif
                <span class="offer-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
            </div>
        </a>
        @endforeach
    </div>
@endif

@endsection

