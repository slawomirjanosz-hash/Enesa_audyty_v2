<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 10pt;
    color: #1A1A1A;
    line-height: 1.5;
    background: #fff;
}

/* NAGŁÓWEK */
.hdr-tbl { width: 100%; border-collapse: collapse; }
.hdr-tbl td { vertical-align: middle; }

.logo-sq {
    width: 48px; height: 48px;
    background: #1A4D3A;
    border-radius: 6px;
    color: #F5F0E8;
    font-size: 9pt;
    font-weight: bold;
    text-align: center;
    line-height: 1.2;
    padding-top: 11px;
    letter-spacing: .5px;
}
.brand-name { font-size: 16pt; font-weight: bold; color: #1A4D3A; line-height: 1; }
.brand-sub  { font-size: 7.5pt; color: #888; margin-top: 3px; }

.doc-ref-num   { font-size: 9pt; font-weight: bold; color: #1A4D3A; text-align: right; }
.doc-ref-dates { font-size: 8pt; color: #888; text-align: right; line-height: 1.9; margin-top: 3px; }

/* LINIA */
.header-line { height: 3px; background: #1A4D3A; margin: 10px 0 20px 0; border: none; display: block; }

/* TYTUŁ */
.offer-title     { text-align: center; margin-bottom: 20px; }
.offer-title-main { font-size: 13pt; font-weight: bold; color: #1A1A1A; line-height: 1.4; }
.offer-title-sub  { font-size: 8.5pt; color: #888; margin-top: 4px; }

/* STRONY */
.party-accent { width: 3px; background: #1A4D3A; padding: 0; }
.party-inner {
    background: #F9F7F4;
    border-top: 1px solid #E5E1D8;
    border-right: 1px solid #E5E1D8;
    border-bottom: 1px solid #E5E1D8;
    padding: 11px 13px;
    vertical-align: top;
}
.party-lbl  { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .1em; color: #888; margin-bottom: 5px; }
.party-name { font-size: 11pt; font-weight: bold; color: #1A1A1A; margin-bottom: 3px; line-height: 1.3; }
.party-det  { font-size: 8.5pt; color: #555; line-height: 1.75; }

/* SEKCJA LABEL */
.sec-hdr-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.sec-lbl-text {
    font-size: 7.5pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: .1em; color: #1A4D3A; white-space: nowrap; padding-right: 8px;
}
.sec-lbl-line { width: 100%; border-bottom: 1px solid #E5E1D8; }
.sec-block   { margin-bottom: 18px; }
.sec-content { font-size: 9.5pt; line-height: 1.8; color: #1A1A1A; }
.sec-content ol, .sec-content ul { padding-left: 18px; }
.sec-content li { margin-bottom: 2px; }

/* TABELA WYCENY */
.price-tbl { width: 100%; border-collapse: collapse; margin-bottom: 4px; font-size: 9pt; }
.price-tbl th {
    padding: 6px 8px; text-align: left; font-size: 7.5pt; font-weight: bold;
    text-transform: uppercase; letter-spacing: .06em; color: #888;
    background: #F4F1EA; border-bottom: 1.5px solid #E5E1D8;
}
.price-tbl th.r { text-align: right; }
.price-tbl td { padding: 7px 8px; font-size: 9pt; border-bottom: 1px solid #F0EDE6; color: #1A1A1A; vertical-align: middle; }
.price-tbl td.r { text-align: right; }
.price-tbl td.muted { color: #888; font-size: 8pt; }
.price-tbl tr:last-child td { border-bottom: none; }
.section-name-row td { background: #F4F1EA; font-weight: bold; font-size: 8pt; color: #1A4D3A; padding: 5px 8px; }

/* DELEGACJE */
.deleg-outer { border: 1px solid #E5E1D8; background: #F9F7F4; border-radius: 6px; margin-bottom: 6px; overflow: hidden; }
.deleg-row-tbl { width: 100%; border-collapse: collapse; }
.deleg-row-tbl td { padding: 6px 12px; font-size: 9pt; border-bottom: 1px solid #F0EDE6; color: #555; }
.deleg-row-tbl td.r { text-align: right; }
.deleg-row-tbl tr:last-child td { border-bottom: none; font-weight: bold; color: #1A1A1A; }

/* PODSUMOWANIE */
.sum-tbl { width: 100%; border-collapse: collapse; margin-top: 12px; margin-bottom: 20px; font-size: 9.5pt; }
.sum-tbl td { padding: 7px 11px; border-bottom: 1px solid #F0EDE6; }
.sum-tbl td.r { text-align: right; font-weight: 600; }
.sum-tbl td.muted { color: #555; }
.sum-total td {
    background: #1A4D3A; color: #fff !important; font-size: 12pt;
    font-weight: bold; padding: 12px 13px; border-bottom: none;
}
.sum-total td.r { text-align: right; font-size: 16pt; font-weight: 900; }

/* TERMIN + WARUNKI */
.terms-tbl { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.terms-cell { width: 50%; vertical-align: top; }
.terms-cell-l { padding-right: 10px; }
.terms-cell-r { padding-left: 10px; }

/* FOOTER */
.footer-sep { height: 1px; background: #E5E1D8; margin: 28px 0 14px 0; border: none; display: block; }
.footer-tbl { width: 100%; border-collapse: collapse; }
.footer-info { font-size: 8pt; color: #aaa; line-height: 1.8; vertical-align: bottom; }
.sign-cell { text-align: right; vertical-align: bottom; }
.sign-line-div { width: 150px; height: 1px; background: #555; margin: 0 0 4px auto; }
.sign-label { font-size: 8pt; color: #888; text-align: right; }
</style>
</head>
<body>

{{-- NAGŁÓWEK --}}
<table class="hdr-tbl">
<tr>
    <td style="width:56px; padding-right:12px;">
        <div class="logo-sq">EN<br>ESA</div>
    </td>
    <td>
        <div class="brand-name">ENESA</div>
        <div class="brand-sub">Efektywność Energetyczna · Białe Certyfikaty · ISO 50001</div>
    </td>
    <td style="text-align:right; white-space:nowrap;">
        <div class="doc-ref-num">OFERTA NR {{ $offer->fullNumber() }}</div>
        <div class="doc-ref-dates">
            Data wystawienia: <strong>{{ $offer->created_at->format('d.m.Y') }}</strong><br>
            @if($offer->valid_until)
                Ważna do: <strong>{{ $offer->valid_until->format('d.m.Y') }}</strong>
            @endif
        </div>
    </td>
</tr>
</table>

<div class="header-line"></div>

{{-- TYTUŁ --}}
<div class="offer-title">
    <div class="offer-title-main">{{ $offer->offer_title ?? 'Oferta handlowa' }}</div>
    <div class="offer-title-sub">Oferta handlowa przygotowana przez ENESA Sp. z o.o.</div>
</div>

{{-- WYSTAWCA / ODBIORCA --}}
<table style="width:100%; border-collapse:separate; border-spacing:12px 0; margin-bottom:22px;">
<tr>
    {{-- Wystawca --}}
    <td style="width:50%; padding:0; vertical-align:top;">
        <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td class="party-accent">&nbsp;</td>
            <td class="party-inner">
                <div class="party-lbl">Wystawca oferty</div>
                <div class="party-name">{{ $companySettings->name ?? 'ENESA Sp. z o.o.' }}</div>
                <div class="party-det">
                    @if($companySettings?->address){{ $companySettings->address }}<br>@endif
                    @if($companySettings?->postcode || $companySettings?->city)
                        {{ trim(($companySettings->postcode ?? '').' '.($companySettings->city ?? '')) }}<br>
                    @endif
                    @if($companySettings?->nip)NIP: {{ $companySettings->nip }}<br>@endif
                    @if($companySettings?->phone)tel. {{ $companySettings->phone }}<br>@endif
                    @if($companySettings?->email){{ $companySettings->email }}<br>@endif
                    @if($offer->assignedUser)
                        Opiekun: {{ $offer->assignedUser->name }}
                    @endif
                </div>
            </td>
        </tr>
        </table>
    </td>

    <td style="width:12px;"></td>

    {{-- Odbiorca --}}
    <td style="width:50%; padding:0; vertical-align:top;">
        <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td class="party-accent">&nbsp;</td>
            <td class="party-inner">
                <div class="party-lbl">Odbiorca oferty</div>
                <div class="party-name">{{ $offer->company->name ?? '—' }}</div>
                <div class="party-det">
                    @if($offer->company->address ?? false){{ $offer->company->address }}<br>@endif
                    @if($offer->company->city ?? false){{ $offer->company->city }}<br>@endif
                    @if($offer->company->nip ?? false)NIP: {{ $offer->company->nip }}<br>@endif
                    @if($offer->company->email ?? false){{ $offer->company->email }}<br>@endif
                    @if($offer->company->phone ?? false)tel. {{ $offer->company->phone }}@endif
                </div>
            </td>
        </tr>
        </table>
    </td>
</tr>
</table>

{{-- PRZEDMIOT OFERTY --}}
@if(!empty($offer->content_subject))
<div class="sec-block">
    <table class="sec-hdr-tbl"><tr>
        <td class="sec-lbl-text">Przedmiot oferty</td>
        <td class="sec-lbl-line"></td>
    </tr></table>
    <div class="sec-content">{!! $offer->content_subject !!}</div>
</div>
@endif

{{-- ZAKRES PRAC --}}
@if(!empty($offer->content_scope))
<div class="sec-block">
    <table class="sec-hdr-tbl"><tr>
        <td class="sec-lbl-text">Zakres prac</td>
        <td class="sec-lbl-line"></td>
    </tr></table>
    <div class="sec-content">{!! $offer->content_scope !!}</div>
</div>
@endif

{{-- TABELA WYCENY (price_sections) --}}
@php
    $sections = $offer->price_sections ?? [];
    $multiSection = count($sections) > 1;

    // Suma wszystkich z_narzutem ze wszystkich sekcji
    $netServices = 0;
    foreach ($sections as $sec) {
        foreach ($sec['rows'] ?? [] as $row) {
            $netServices += (float)($row['z_narzutem'] ?? 0);
        }
    }
@endphp

@if(count($sections) > 0)
<div class="sec-block">
    <table class="sec-hdr-tbl"><tr>
        <td class="sec-lbl-text">Wycena</td>
        <td class="sec-lbl-line"></td>
    </tr></table>

    <table class="price-tbl">
        <thead>
        <tr>
            <th style="width:5%">#</th>
            <th>Opis pozycji</th>
            <th class="r" style="width:10%">Ilość</th>
            <th class="r" style="width:12%">Jedn.</th>
            @if($offer->show_unit_prices)
                <th class="r" style="width:16%">Cena jedn.</th>
                <th class="r" style="width:18%">Wartość netto</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach($sections as $section)
            @if($multiSection)
            <tr class="section-name-row">
                <td colspan="{{ $offer->show_unit_prices ? 6 : 4 }}">{{ $section['name'] }}</td>
            </tr>
            @endif
            @foreach($section['rows'] ?? [] as $i => $row)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td>{{ $row['opis'] }}</td>
                <td class="r">{{ $row['ilosc'] }}</td>
                <td class="r">{{ $row['jedn'] }}</td>
                @if($offer->show_unit_prices)
                    <td class="r">{{ number_format((float)($row['cena_jedn'] ?? 0), 2, ',', ' ') }} zł</td>
                    <td class="r"><strong>{{ number_format((float)($row['z_narzutem'] ?? 0), 2, ',', ' ') }} zł</strong></td>
                @endif
            </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- DELEGACJE --}}
@if($offer->offerDelegation)
@php
    $del      = $offer->offerDelegation;
    $stawkaKm = (float)($del->stawka_km ?? 1.10);
    $km       = (int)($del->km_do_klienta ?? 0);
    $wyjazdy  = (int)($del->liczba_wyjazdow ?? 1);
    $osoby    = (int)($del->liczba_osob ?? 1);
    $noce     = (int)($del->liczba_noc ?? 0);
    $stawkaNoc = (float)($del->stawka_noc ?? 0);

    $kosztKm  = $km * 2 * $wyjazdy * $stawkaKm;
    $kosztNoc = $noce * $osoby * $stawkaNoc;
    $totalDel = $kosztKm + $kosztNoc;
@endphp

@if($totalDel > 0)
<div class="sec-block">
    <table class="sec-hdr-tbl"><tr>
        <td class="sec-lbl-text">Delegacje</td>
        <td class="sec-lbl-line"></td>
    </tr></table>

    <div class="deleg-outer">
        <table class="deleg-row-tbl">
            @if($km > 0)
            <tr>
                <td>Delegacja</td>
                <td class="r">{{ number_format($totalDel, 2, ',', ' ') }} zł</td>
            </tr>
            @endif
        </table>
    </div>
</div>
@endif
@endif

{{-- PODSUMOWANIE --}}
@php
    $totalNet = $netServices + ($totalDel ?? 0);
@endphp
<table class="sum-tbl">
    <tr>
        <td class="muted">Suma usług netto</td>
        <td class="r">{{ number_format($netServices, 2, ',', ' ') }} zł</td>
    </tr>
    @if(($totalDel ?? 0) > 0)
    <tr>
        <td class="muted">Delegacje netto</td>
        <td class="r">{{ number_format($totalDel, 2, ',', ' ') }} zł</td>
    </tr>
    @endif
    <tr class="sum-total">
        <td>ŁĄCZNIE NETTO</td>
        <td class="r">{{ number_format($totalNet, 2, ',', ' ') }} zł</td>
    </tr>
</table>

{{-- TERMIN + WARUNKI --}}
<table class="terms-tbl">
<tr>
    <td class="terms-cell terms-cell-l">
        <div style="font-size:7.5pt; font-weight:bold; text-transform:uppercase; letter-spacing:.1em; color:#1A4D3A; border-bottom:1px solid #E5E1D8; padding-bottom:4px; margin-bottom:8px;">Termin realizacji</div>
        <div class="sec-content">
            @if(!empty($offer->content_deadline))
                {!! $offer->content_deadline !!}
            @else
                Do uzgodnienia po podpisaniu umowy.
            @endif
        </div>
    </td>
    <td class="terms-cell terms-cell-r">
        <div style="font-size:7.5pt; font-weight:bold; text-transform:uppercase; letter-spacing:.1em; color:#1A4D3A; border-bottom:1px solid #E5E1D8; padding-bottom:4px; margin-bottom:8px;">Warunki p&#322;atno&#347;ci</div>
        <div class="sec-content">
            @if(!empty($offer->content_payment))
                {!! $offer->content_payment !!}
            @else
                Przelew bankowy, 14 dni od wystawienia faktury.
            @endif
        </div>
    </td>
</tr>
</table>

{{-- UWAGI --}}
@if($offer->notes)
<div class="sec-block">
    <table class="sec-hdr-tbl"><tr>
        <td class="sec-lbl-text">Uwagi</td>
        <td class="sec-lbl-line"></td>
    </tr></table>
    <div class="sec-content">{!! nl2br(e($offer->notes)) !!}</div>
</div>
@endif

{{-- FOOTER --}}
<div class="footer-sep"></div>
<table class="footer-tbl">
<tr>
    <td class="footer-info">
        {{ $companySettings->name ?? 'ENESA Sp. z o.o.' }}
        @if($companySettings?->address) · {{ $companySettings->address }}@endif
        @if($companySettings?->postcode || $companySettings?->city)
            , {{ trim(($companySettings->postcode ?? '').' '.($companySettings->city ?? '')) }}
        @endif
        <br>
        @if($companySettings?->nip)NIP: {{ $companySettings->nip }} · @endif
        @if($companySettings?->email){{ $companySettings->email }}@endif
        <br>
        Oferta ważna 30 dni od daty wystawienia. Wszystkie ceny podano w kwotach netto.
    </td>
    <td class="sign-cell">
        <div class="sign-line-div"></div>
        <div class="sign-label">{{ $offer->assignedUser->name ?? 'Przedstawiciel ENESA' }}<br>Podpis i pieczęć</div>
    </td>
</tr>
</table>

</body>
</html>