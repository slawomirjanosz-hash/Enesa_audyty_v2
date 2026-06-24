<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        color: #1A1A1A;
        background: #fff;
        line-height: 1.5;
    }
    @page {
        margin: 18mm 15mm 20mm 15mm;
        size: A4;
    }

    /* ── Header ─────────────────────────────── */
    .page-header {
        display: table;
        width: 100%;
        border-bottom: 3px solid #1A4D3A;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .header-logo { display: table-cell; vertical-align: middle; width: 50%; }
    .header-meta { display: table-cell; vertical-align: middle; text-align: right; width: 50%; }
    .logo-name {
        font-size: 24pt;
        font-weight: bold;
        color: #1A4D3A;
        letter-spacing: 3px;
    }
    .logo-tagline { font-size: 8pt; color: #666; letter-spacing: 1px; }
    .offer-number-label { font-size: 8pt; color: #888; }
    .offer-number-value { font-size: 14pt; font-weight: bold; color: #1A4D3A; }
    .offer-date { font-size: 9pt; color: #555; margin-top: 3px; }

    /* ── Offer title ─────────────────────────── */
    .offer-title {
        text-align: center;
        font-size: 15pt;
        font-weight: bold;
        color: #1A4D3A;
        margin: 14px 0;
        padding: 10px 0;
        border-top: 1px solid #E5E1D8;
        border-bottom: 1px solid #E5E1D8;
    }

    /* ── Parties ─────────────────────────────── */
    .parties-table { width: 100%; margin-bottom: 16px; }
    .party-cell { width: 50%; vertical-align: top; padding: 12px 14px; }
    .party-issuer { border-right: 1px solid #E5E1D8; }
    .party-box {
        border: 1px solid #E5E1D8;
        border-radius: 4px;
        overflow: hidden;
    }
    .party-header {
        background: #1A4D3A;
        color: #fff;
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 5px 10px;
    }
    .party-body { padding: 10px; }
    .party-name { font-size: 11pt; font-weight: bold; color: #1A1A1A; margin-bottom: 4px; }
    .party-detail { font-size: 9pt; color: #444; line-height: 1.7; }

    /* ── Section ─────────────────────────────── */
    .section { margin-bottom: 14px; page-break-inside: avoid; }
    .section-header {
        background: #1A4D3A;
        color: #fff;
        font-size: 9pt;
        font-weight: bold;
        letter-spacing: .5px;
        padding: 6px 12px;
        border-radius: 3px 3px 0 0;
    }
    .section-body {
        border: 1px solid #E5E1D8;
        border-top: none;
        padding: 12px;
        font-size: 10pt;
        line-height: 1.7;
        border-radius: 0 0 3px 3px;
    }
    .section-body ul { padding-left: 16px; }
    .section-body ol { padding-left: 16px; }
    .section-body li { margin-bottom: 3px; }

    /* ── Price table ─────────────────────────── */
    .price-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 4px; }
    .price-table th {
        background: #F0F7F3;
        color: #1A4D3A;
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 6px 8px;
        border: 1px solid #C8DDD4;
        text-align: left;
    }
    .price-table td {
        padding: 6px 8px;
        border: 1px solid #E5E1D8;
        vertical-align: top;
    }
    .price-table tr:nth-child(even) td { background: #FAFAF6; }
    .price-table .num { text-align: right; }
    .price-table .bold { font-weight: bold; }

    /* ── Summary ─────────────────────────────── */
    .summary-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .summary-table td { padding: 7px 10px; font-size: 10pt; }
    .summary-sub { border-bottom: 1px solid #F0EDE6; }
    .summary-total {
        background: #1A4D3A;
        color: #fff;
        font-weight: bold;
        font-size: 12pt;
    }
    .summary-markup { background: #FFFBEB; }

    /* ── Footer ──────────────────────────────── */
    .page-footer {
        margin-top: 24px;
        padding-top: 10px;
        border-top: 1px solid #E5E1D8;
        font-size: 8pt;
        color: #888;
    }
    .footer-table { width: 100%; }
    .footer-left { vertical-align: top; width: 60%; }
    .footer-right {
        vertical-align: bottom;
        text-align: right;
        width: 40%;
        padding-top: 30px;
        border-top: 1px solid #999;
        font-size: 8pt;
        color: #555;
    }

    .valid-until {
        font-size: 9pt;
        color: #555;
        margin-top: 4px;
    }
</style>
</head>
<body>

@php
    $d = $offer->offerDelegation;
    $priceSections = $offer->price_sections ?? [];

    // Compute totals
    $sumServices = 0;
    foreach ($priceSections as $section) {
        foreach ($section['rows'] ?? [] as $row) {
            $sumServices += (float)($row['z_narzutem'] ?? 0);
        }
    }
    $delegCost = $d ? $d->kosztDelegacji() : 0;
    $total = $sumServices + $delegCost;
@endphp

{{-- PAGE HEADER --}}
<div class="page-header">
    <table style="width:100%;"><tr>
        <td class="header-logo" style="vertical-align:middle;">
            <div class="logo-name">ENESA</div>
            <div class="logo-tagline">Efektywność energetyczna</div>
        </td>
        <td class="header-meta" style="vertical-align:middle;text-align:right;">
            <div class="offer-number-label">OFERTA NR</div>
            <div class="offer-number-value">{{ $offer->fullNumber() }}</div>
            <div class="offer-date">Data wystawienia: {{ $offer->created_at->format('d.m.Y') }}</div>
            @if($offer->valid_until)
                <div class="valid-until">Ważna do: {{ $offer->valid_until->format('d.m.Y') }}</div>
            @endif
        </td>
    </tr></table>
</div>

{{-- OFFER TITLE --}}
@if($offer->offer_title)
<div class="offer-title">{{ $offer->offer_title }}</div>
@endif

{{-- PARTIES --}}
<table class="parties-table"><tr>
    <td style="width:50%;vertical-align:top;padding-right:8px;">
        <div class="party-box">
            <div class="party-header">Wystawca</div>
            <div class="party-body">
                <div class="party-name">ENESA Sp. z o.o.</div>
                <div class="party-detail">
                    ul. Konarskiego 18C<br>
                    44-100 Gliwice<br>
                    NIP: — do uzupełnienia —<br>
                    system@enesa.pl
                </div>
            </div>
        </div>
    </td>
    <td style="width:50%;vertical-align:top;padding-left:8px;">
        <div class="party-box">
            <div class="party-header">Odbiorca</div>
            <div class="party-body">
                <div class="party-name">{{ $offer->company?->name ?? '—' }}</div>
                <div class="party-detail">
                    {{ $offer->company?->address ?? '' }}
                    @if($offer->company?->address && $offer->company?->city), @endif
                    {{ $offer->company?->city ?? '' }}<br>
                    @if($offer->company?->nip) NIP: {{ $offer->company->nip }}<br> @endif
                    @if($offer->company?->email) {{ $offer->company->email }} @endif
                </div>
            </div>
        </div>
    </td>
</tr></table>

{{-- PRZEDMIOT OFERTY --}}
@if($offer->content_subject)
<div class="section">
    <div class="section-header">Przedmiot oferty</div>
    <div class="section-body">{!! $offer->content_subject !!}</div>
</div>
@endif

{{-- ZAKRES PRAC --}}
@if($offer->content_scope)
<div class="section">
    <div class="section-header">Zakres prac</div>
    <div class="section-body">{!! $offer->content_scope !!}</div>
</div>
@endif

{{-- WYCENA --}}
@if(count($priceSections) > 0)
    @foreach($priceSections as $section)
        @if(!empty($section['rows']))
        <div class="section">
            <div class="section-header">{{ $section['name'] ?? 'Wycena' }}</div>
            <div class="section-body" style="padding:0;">
                <table class="price-table">
                    <thead>
                        <tr>
                            <th>Opis pozycji</th>
                            @if($offer->show_unit_prices)
                                <th style="width:55px;">Jedn.</th>
                                <th class="num" style="width:55px;">Ilość</th>
                                <th class="num" style="width:90px;">Cena jedn.</th>
                                <th class="num" style="width:90px;">Wartość netto</th>
                            @endif
                            <th class="num" style="width:90px;">Z narzutem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                        <tr>
                            <td>{{ $row['opis'] ?? '' }}</td>
                            @if($offer->show_unit_prices)
                                <td>{{ $row['jedn'] ?? 'szt' }}</td>
                                <td class="num">{{ number_format($row['ilosc'] ?? 0, 2, ',', ' ') }}</td>
                                <td class="num">{{ number_format($row['cena_jedn'] ?? 0, 2, ',', ' ') }} zł</td>
                                <td class="num">{{ number_format(($row['ilosc'] ?? 0) * ($row['cena_jedn'] ?? 0), 2, ',', ' ') }} zł</td>
                            @endif
                            <td class="num bold">{{ number_format($row['z_narzutem'] ?? 0, 2, ',', ' ') }} zł</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach
@endif

{{-- DELEGACJA --}}
@if($d && ($d->km_do_klienta > 0 || $d->liczba_wyjazdow > 0))
<div class="section">
    <div class="section-header">Koszty dojazdu</div>
    <div class="section-body" style="padding:0;">
        <table class="price-table">
            <thead>
                <tr>
                    <th>Opis</th>
                    <th class="num" style="width:80px;">Wartość</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Dojazd do klienta: {{ $d->km_do_klienta ?? 0 }} km × 2 kierunki
                        × {{ $d->liczba_wyjazdow ?? 1 }} wyjazd(ów)
                        × {{ number_format($d->stawka_km ?? 1.10, 2, ',', '.') }} zł/km
                    </td>
                    <td class="num bold">{{ number_format(($d->km_do_klienta ?? 0) * 2 * ($d->liczba_wyjazdow ?? 1) * (float)($d->stawka_km ?? 1.10), 2, ',', ' ') }} zł</td>
                </tr>
                @if($d->czy_kilkudniowy)
                <tr>
                    <td>
                        Nocleg: {{ $d->liczba_noc ?? 0 }} noc(y)
                        × {{ $d->liczba_osob ?? 1 }} os.
                        × {{ number_format($d->stawka_noc ?? 300, 2, ',', '.') }} zł/noc
                    </td>
                    <td class="num bold">{{ number_format(($d->liczba_noc ?? 0) * ($d->liczba_osob ?? 1) * (float)($d->stawka_noc ?? 300), 2, ',', ' ') }} zł</td>
                </tr>
                @endif
                <tr>
                    <td style="font-weight:bold;">RAZEM delegacje</td>
                    <td class="num bold">{{ number_format($d->kosztDelegacji(), 2, ',', ' ') }} zł</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- SUMMARY --}}
<table class="summary-table">
    <tr class="summary-sub">
        <td>Suma usług netto</td>
        <td style="text-align:right;font-weight:bold;">{{ number_format($sumServices, 2, ',', ' ') }} zł</td>
    </tr>
    @if($delegCost > 0)
    <tr class="summary-sub">
        <td>Delegacje netto</td>
        <td style="text-align:right;font-weight:bold;">{{ number_format($delegCost, 2, ',', ' ') }} zł</td>
    </tr>
    @endif
    <tr class="summary-total">
        <td style="font-size:13pt;">ŁĄCZNIE NETTO</td>
        <td style="text-align:right;font-size:14pt;">{{ number_format($total, 2, ',', ' ') }} zł</td>
    </tr>
</table>

{{-- TERMIN REALIZACJI --}}
@if($offer->content_deadline)
<div class="section" style="margin-top:14px;">
    <div class="section-header">Termin realizacji</div>
    <div class="section-body">{!! $offer->content_deadline !!}</div>
</div>
@endif

{{-- WARUNKI PŁATNOŚCI --}}
@if($offer->content_payment)
<div class="section">
    <div class="section-header">Warunki płatności</div>
    <div class="section-body">{!! $offer->content_payment !!}</div>
</div>
@endif

{{-- FOOTER --}}
<div class="page-footer">
    <table class="footer-table"><tr>
        <td class="footer-left">
            ENESA Sp. z o.o. · ul. Konarskiego 18C, 44-100 Gliwice
            @if($offer->assignedUser)
                <br>Osoba odpowiedzialna: {{ $offer->assignedUser->name }}
            @endif
        </td>
        <td class="footer-right">
            Podpis i pieczęć Wystawcy
        </td>
    </tr></table>
</div>

</body>
</html>
