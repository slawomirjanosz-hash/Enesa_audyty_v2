@extends('layouts.app')

@section('page-title', $offer->fullNumber())

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<style>
/* â”€â”€ Editor topbar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
#editor-topbar {
    position: sticky;
    top: var(--topbar, 60px);
    z-index: 50;
    background: #fff;
    border-bottom: 1px solid #E5E1D8;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    gap: 14px;
    flex-wrap: wrap;
    margin: -20px -20px 20px -20px; /* bleed to content edges */
}
.etb-left  { display: flex; align-items: center; gap: 10px; }
.etb-right { display: flex; align-items: center; gap: 10px; }

/* â”€â”€ Toggle switch â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.toggle-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.toggle-wrap input { display: none; }
.toggle-track {
    width: 40px; height: 22px;
    background: #D0CCC0;
    border-radius: 11px;
    position: relative;
    transition: background .2s;
}
.toggle-track::after {
    content: '';
    position: absolute;
    top: 3px; left: 3px;
    width: 16px; height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: left .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.25);
}
.toggle-wrap input:checked + .toggle-track { background: #1A4D3A; }
.toggle-wrap input:checked + .toggle-track::after { left: 21px; }
.toggle-label { font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 600; color: #555; }

/* â”€â”€ Badges â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-blue   { background:#DBEAFE; color:#1D4ED8; }
.badge-green  { background:#DCFCE7; color:#166534; }
.badge-red    { background:#FEE2E2; color:#B91C1C; }
.badge-gray   { background:#F3F4F6; color:#4B5563; }

/* â”€â”€ Buttons â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: #1A4D3A; color: #F5F0E8; border: none;
    border-radius: 8px; padding: 8px 16px;
    font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 700;
    text-decoration: none; cursor: pointer; transition: background .15s;
    white-space: nowrap;
}
.btn-primary:hover { background: #143d2d; color: #F5F0E8; }
.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    background: #fff; color: #333; border: 1px solid #D0CCC0;
    border-radius: 8px; padding: 7px 14px;
    font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 600;
    text-decoration: none; cursor: pointer; transition: background .15s;
}
.btn-secondary:hover { background: #F4F1EA; }

/* â”€â”€ Editor card â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.ed-card {
    background: #fff;
    border: 1px solid #E5E1D8;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
}
.ed-card-header {
    padding: 13px 20px;
    border-bottom: 1px solid #F0EDE6;
    background: #FAFAF6;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ed-card-header > i { font-size: 17px; color: #1A4D3A; }
.ed-card-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.ed-card-body { padding: 20px; }
.ed-card.type-text { border-left: 4px solid #1A4D3A; }
.ed-card.type-text .ed-card-header { background: #F0F7F3; }
.ed-card.type-price { border-left: 4px solid #D97706; }
.ed-card.type-price .ed-card-header { background: #FFF8E8; }
.ed-card.type-deleg { border-left: 4px solid #2563EB; }
.ed-card.type-deleg .ed-card-header { background: #EFF6FF; }

/* â”€â”€ Document header card â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.doc-header-bar {
    background: #1A4D3A;
    color: #fff;
    padding: 14px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.doc-header-bar .offer-num { font-family:'Lato',sans-serif; font-size:15px; font-weight:900; letter-spacing:.04em; }
.doc-header-bar .doc-date  { font-size:12px; opacity:.8; }
.doc-parties { display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid #F0EDE6; }
.doc-party { padding:18px 22px; }
.doc-party:first-child { border-right:1px solid #F0EDE6; }
.doc-party-label { font-family:'Manrope',sans-serif; font-size:10px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:.07em; margin-bottom:8px; }
.doc-party-name  { font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; color:#1A1A1A; margin-bottom:4px; }
.doc-party-line  { font-family:'Lato',sans-serif; font-size:12px; color:#555; line-height:1.7; }
.doc-title-wrap  { padding:16px 22px; }
.doc-title-input {
    width:100%; border:none; outline:none;
    font-family:'Manrope',sans-serif; font-size:18px; font-weight:700; color:#1A4D3A;
    background:transparent;
    border-bottom:2px dashed #94C4B0;
    padding:4px 0;
    transition:border-color .15s;
}
.doc-title-input:focus { border-color:#1A4D3A; }
.doc-title-input::placeholder { color:#bbb; font-weight:400; }

/* â”€â”€ Rich text editor â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.rte-toolbar {
    display:flex; gap:4px; padding:8px 16px;
    border-bottom:1px solid #F0EDE6;
    background:#FAFAF6;
    flex-wrap:wrap;
}
.rte-btn {
    min-width:30px; height:28px; padding:0 8px;
    background:#fff; border:1px solid #D0CCC0;
    border-radius:5px; font-size:13px; font-weight:700;
    cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
    font-family:'Lato',sans-serif; color:#333;
    transition:background .12s;
}
.rte-btn:hover { background:#F0EDE6; }
.rte-btn-ai {
    margin-left:auto; background:#1A4D3A; color:#fff; border-color:#1A4D3A;
    font-size:12px; font-weight:600; padding:0 10px; gap:4px;
}
.rte-btn-ai:hover { background:#14392b; border-color:#14392b; color:#fff; }
.rte-btn-ai:disabled { opacity:.6; cursor:not-allowed; }
.rich-editor {
    min-height:90px; padding:14px 20px;
    font-family:'Lato',sans-serif; font-size:14px; color:#1A1A1A; line-height:1.8;
    outline:none;
}
.rich-editor:empty::before { content:attr(data-placeholder); color:#bbb; pointer-events:none; }
.rich-editor ul, .rich-editor ol { padding-left:20px; }

/* â”€â”€ Price table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.section-name-input {
    flex:1; border:none; outline:none; background:transparent;
    font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A;
    border-bottom:1px dashed #bbb; padding:2px 4px;
}
.price-table { width:100%; border-collapse:collapse; font-size:13px; }
.price-table th {
    font-family:'Manrope',sans-serif; font-size:10px; font-weight:700;
    color:#888; text-transform:uppercase; letter-spacing:.05em;
    padding:8px 10px; border-bottom:2px solid #E5E1D8; background:#FAFAF6;
    white-space:nowrap; text-align:left;
}
.price-table td { padding:6px 6px; border-bottom:1px solid #F0EDE6; vertical-align:middle; }
.price-table tr:last-child td { border-bottom:none; }
.cell-input {
    width:100%; border:1px solid #D8D4C8; border-radius:5px;
    padding:6px 9px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A;
    background:#fff; outline:none; transition:border-color .12s, box-shadow .12s;
    box-sizing:border-box;
}
.cell-input::placeholder { color: #B0AA9E; }
.cell-input:hover { border-color:#94C4B0; }
.cell-input:focus { border-color:#1A4D3A; box-shadow: 0 0 0 2px rgba(26,77,58,0.08); }
.cell-readonly { font-family:'Lato',sans-serif; font-size:13px; color:#333; font-weight:700; padding:5px 8px; }
.btn-add-row {
    background:none; border:1px dashed #94C4B0; color:#1A4D3A; border-radius:6px;
    padding:6px 14px; font-size:12px; font-family:'Manrope',sans-serif; font-weight:700;
    cursor:pointer; transition:background .12s;
}
.btn-add-row:hover { background:#F0F7F3; }
.btn-del-row {
    background:none; border:none; color:#DC2626; cursor:pointer;
    font-size:16px; padding:4px; border-radius:4px;
    display:flex; align-items:center; justify-content:center;
}
.btn-del-row:hover { background:#FEE2E2; }
.btn-del-section {
    margin-left:auto; background:none; border:1px solid #FCA5A5; color:#B91C1C;
    border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700;
    font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s;
}
.btn-del-section:hover { background:#FEE2E2; }
.btn-add-section {
    display:inline-flex; align-items:center; gap:6px;
    border:1px dashed #94C4B0; color:#1A4D3A; background:none;
    border-radius:8px; padding:8px 16px; font-size:12px; font-weight:700;
    font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s;
}
.btn-add-section:hover { background:#F0F7F3; }

/* â”€â”€ Show/hide unit price columns â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */


/* â”€â”€ Delegation fields â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.deleg-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
.field-label { display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; font-family:'Manrope',sans-serif; }
.field-input {
    width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px;
    padding:8px 10px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A;
    outline:none; transition:border-color .15s; box-sizing:border-box;
}
.field-input:focus { border-color:#1A4D3A; background:#fff; }
.input-group { display:flex; }
.input-group .field-input { border-radius:7px 0 0 7px; border-right:none; }
.input-suffix {
    background:#F0EDE6; border:1px solid #D0CCC0; border-radius:0 7px 7px 0;
    padding:8px 10px; font-size:12px; color:#666; white-space:nowrap;
}

/* â”€â”€ Summary bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.summary-row { display:flex; justify-content:space-between; padding:9px 16px; font-size:13px; }
.summary-row.sub { background:#fff; border-bottom:1px solid #F0EDE6; }
.summary-row.markup { background:#FFFBEB; border-bottom:1px solid #FDE68A; }
.summary-row.total { background:#1A4D3A; color:#fff; border-radius:0 0 10px 10px; }
.summary-label { font-family:'Manrope',sans-serif; font-weight:600; }
.summary-value { font-family:'Lato',sans-serif; font-weight:900; font-size:15px; }
.summary-row.total .summary-value { font-size:20px; }

/* â”€â”€ Field row â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.markup-bar { background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.markup-bar .field-label { color:#92400E; }
</style>
@endpush

@section('content')
@php
    $d = $offer->offerDelegation;
    $statusLabel = match($offer->status) {
        'w_toku'         => ['label' => 'W toku',        'class' => 'badge-blue'],
        'wygrana'        => ['label' => 'Wygrana',       'class' => 'badge-green'],
        'przegrana'      => ['label' => 'Przegrana',     'class' => 'badge-red'],
        'zarchiwizowana' => ['label' => 'Zarchiwizowana','class' => 'badge-gray'],
        default          => ['label' => $offer->status,  'class' => 'badge-gray'],
    };
    $validUntilDefault = $offer->valid_until
        ? $offer->valid_until->format('Y-m-d')
        : now()->addDays(30)->format('Y-m-d');

    $existingTextSections = $offer->text_sections;
    if (empty($existingTextSections)) {
        $existingTextSections = [
            ['name' => 'Przedmiot oferty', 'content' => $offer->content_subject ?? ''],
            ['name' => 'Zakres prac', 'content' => $offer->content_scope ?? ''],
            ['name' => 'Termin realizacji', 'content' => $offer->content_deadline ?? ''],
            ['name' => 'Warunki płatności', 'content' => $offer->content_payment ?? ''],
        ];
    }
@endphp

{{-- â•â•â• EDITOR TOPBAR â•â•â• --}}
<div id="editor-topbar">
    <div class="etb-left">
        <a href="{{ route('offers.show', $offer) }}" class="btn-secondary" style="padding:6px 10px;">
            <i class="ti ti-arrow-left"></i>
        </a>
        <span style="font-family:'Lato',sans-serif;font-size:15px;font-weight:900;color:#1A4D3A;letter-spacing:.02em;">
            {{ $offer->fullNumber() }}
        </span>
        <span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
        @if($offer->is_template)
            <span style="background:#F0F7F3;color:#1A4D3A;border:1px solid #94C4B0;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;font-family:'Manrope',sans-serif;">
                <i class="ti ti-bookmark"></i> Szablon
            </span>
        @endif
    </div>
    <div class="etb-right">
        <label class="toggle-wrap" title="Pokaż ceny jednostkowe klientowi (widoczne w PDF)">
            <input type="checkbox" id="show-unit-toggle"
                   {{ $offer->show_unit_prices ? 'checked' : '' }}
                   onchange="toggleUnitPrices(this)">
            <span class="toggle-track"></span>
            <span class="toggle-label">Ceny jedn. w PDF</span>
        </label>
        @if(!$offer->is_template)
            <button type="button" onclick="openPdfModal('{{ route('offers.pdf', $offer) }}', 'Oferta {{ $offer->offer_full_number }}')" class="btn-secondary">
                <i class="ti ti-file-type-pdf"></i> Podgląd PDF
            </button>
            <a href="{{ route('offers.download-word', $offer) }}" class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                <i class="ti ti-file-type-doc"></i> Pobierz DOCX
            </a>
        @endif
        <button type="button" class="btn-secondary" onclick="document.getElementById('modal-clone').style.display='flex'">
            <i class="ti ti-copy"></i> Zapisz jako...
        </button>
        <button type="submit" form="offer-form" class="btn-primary">
            <i class="ti ti-device-floppy"></i> {{ $offer->is_template ? 'Zapisz szablon' : 'Zapisz ofertę' }}
        </button>
    </div>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif

{{-- â•â•â• FORM â•â•â• --}}
<form id="offer-form" method="POST" action="{{ route('offers.update', $offer) }}">
@csrf
@method('PUT')

{{-- â”€â”€ SEKCJA A: NAGĹĂ“WEK DOKUMENTU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="doc-header-bar">
        <div>
            <div class="offer-num">{{ $offer->offer_number }}</div>
            <div class="doc-date">Wystawiona: {{ $offer->created_at->format('d.m.Y') }}</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
        </div>
    </div>

    <div class="doc-parties">
        <div class="doc-party">
            <div class="doc-party-label">Wystawca</div>
            <div class="doc-party-name">{{ $companySettings->name ?? 'ENESA Sp. z o.o.' }}</div>
            <div class="doc-party-line">
                @if($companySettings?->address){{ $companySettings->address }}<br>@endif
                @if($companySettings?->postcode || $companySettings?->city)
                    {{ trim(($companySettings->postcode ?? '').' '.($companySettings->city ?? '')) }}<br>
                @endif
                @if($companySettings?->nip)NIP: {{ $companySettings->nip }}<br>@endif
                @if($companySettings?->phone)tel. {{ $companySettings->phone }}<br>@endif
                @if($companySettings?->email){{ $companySettings->email }}@endif
            </div>
        </div>
        <div class="doc-party">
            @if($offer->is_template)
                <div class="doc-party-label">Odbiorca</div>
                <input type="hidden" name="company_id" value="">
                <div style="padding:10px 12px;background:#F5F0E8;border:1px dashed #C8B89A;border-radius:8px;font-size:13px;color:#666;font-family:'Manrope',sans-serif;">
                    <i class="ti ti-bookmark" style="color:#1A4D3A;margin-right:6px;"></i>
                    Szablon nie jest przypisany do firmy
                </div>
            @else
                <div class="doc-party-label">Odbiorca &mdash; zmień firmę</div>
                <select name="company_id" id="company_id_select" class="field-input" style="margin-bottom:8px;" onchange="updateCompanyInfo(this)">
                    <option value="">— wybierz firmę klienta —</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}"
                            data-name="{{ $c->name }}"
                            data-address="{{ $c->address ?? '' }}"
                            data-city="{{ $c->city ?? '' }}"
                            data-nip="{{ $c->nip ?? '' }}"
                            data-email="{{ $c->email ?? '' }}"
                            {{ $offer->company_id == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}@if($c->city) — {{ $c->city }}@endif
                        </option>
                    @endforeach
                </select>
                <div id="company-info-display">
                    <div class="doc-party-name" id="disp-name">{{ $offer->company?->name ?? '—' }}</div>
                    <div class="doc-party-line" id="disp-details">
                        {{ $offer->company?->address ?? '' }}
                        @if($offer->company?->address && $offer->company?->city), @endif
                        {{ $offer->company?->city ?? '' }}
                        @if($offer->company?->nip)<br>NIP: {{ $offer->company->nip }}@endif
                        @if($offer->company?->email)<br>{{ $offer->company->email }}@endif
                    </div>
                </div>
                <div id="company-distance-info" style="margin-top:6px;font-size:12px;color:#1A4D3A;display:none;"></div>
            @endif
        </div>
    </div>

    <div class="doc-title-wrap">
        <input type="text" name="offer_title"
               class="doc-title-input"
               value="{{ old('offer_title', $offer->offer_title) }}"
               placeholder="Wpisz tytuł oferty — będzie widoczny na dokumencie">
    </div>
</div>

{{-- ── SEKCJE OPISOWE (dynamiczne) ───────────── --}}
<div id="text-sections-container"></div>
<div style="margin-bottom:16px;">
    <button type="button" class="btn-add-section" onclick="addTextSection()">
        <i class="ti ti-file-text"></i> Dodaj sekcję opisową
    </button>
</div>

{{-- â”€â”€ SEKCJA C: WYCENA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}

{{-- C1: Sekcja gĹ‚Ăłwna --}}
<div class="ed-card type-price" id="section-main">
    <div class="ed-card-header">
        <i class="ti ti-calculator"></i>
        <input type="text" class="section-name-input" id="section-main-name" value="Wycena ogólna">
    </div>
    <div style="overflow-x:auto;">
        <table class="price-table" id="table-main">
            <thead>
                <tr>
                    <th style="width:28px;"></th>
                    <th style="min-width:160px;">Opis pozycji</th>
                    <th style="width:90px;">Jednostka</th>
                    <th style="width:70px;">Ilość</th>
                    <th style="width:90px;">Cena jedn. netto</th>
                    <th style="width:120px;text-align:right;">Wartość netto</th>
                    <th style="width:120px;text-align:right;">Kwota</th>
                    <th style="width:32px;"></th>
                </tr>
            </thead>
            <tbody id="tbody-main"></tbody>
        </table>
    </div>
    <div style="padding:10px 16px;">
        <button type="button" class="btn-add-row" onclick="addRow('tbody-main')">
            <i class="ti ti-plus"></i> Dodaj pozycję
        </button>
    </div>
</div>

{{-- C2: Dynamiczne sekcje --}}
<div id="dynamic-sections"></div>
<div style="margin-bottom:16px;">
    <button type="button" class="btn-add-section" onclick="addSection()">
        <i class="ti ti-section"></i> Dodaj sekcję wyceny
    </button>
</div>

{{-- DELEGACJE --}}
<div class="ed-card type-deleg" id="section-delegacje">
    <div class="ed-card-header">
        <i class="ti ti-car"></i>
        <span class="ed-card-title">Delegacje</span>
    </div>
    <div class="ed-card-body">
        <div id="deleg-sections"></div>
        <div style="margin-top:4px;">
            <button type="button" class="btn-add-section" onclick="delegAddSection()">
                <i class="ti ti-plus"></i> Dodaj lokalizację
            </button>
        </div>
        <input type="hidden" name="delegations" id="delegations-json">
        <span id="deleg-result" style="display:none;">0,00 zł</span>
    </div>
</div>

{{-- C4: NARZUT + PODSUMOWANIE --}}
<div class="ed-card" style="overflow:hidden;">
    <div class="ed-card-header">
        <i class="ti ti-report-money"></i>
        <span class="ed-card-title">Podsumowanie wyceny</span>
    </div>
    <div class="ed-card-body" style="padding:0;">
        {{-- Markup bar --}}
        <div class="markup-bar" style="border-radius:0;border-left:none;border-right:none;border-top:none;">
            <span style="font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;color:#92400E;">Narzut globalny:</span>
            <div class="input-group" style="width:120px;">
                <input type="text" id="markup-pct" class="field-input decimal-input" 
                       value="0" 
                       placeholder="0"
                       oninput="validateDecimal(this); syncMarkup('pct')" 
                       onkeydown="return allowDecimalInput(event)"
                       style="border-radius:7px 0 0 7px;">
                <span class="input-suffix">%</span>
            </div>
            <div class="input-group" style="width:140px;">
                <input type="text" id="markup-zl" class="field-input decimal-input" 
                       value="0" 
                       placeholder="0"
                       oninput="validateDecimal(this); syncMarkup('zl')" 
                       onkeydown="return allowDecimalInput(event)"
                       style="border-radius:7px 0 0 7px;">
                <span class="input-suffix">zł</span>
            </div>
        </div>
        {{-- Summary rows --}}
        <div class="summary-row sub">
            <span class="summary-label">Suma usług netto</span>
            <span class="summary-value" id="sum-services">0,00 zł</span>
        </div>
        <div class="summary-row sub">
            <span class="summary-label">Delegacje netto</span>
            <span class="summary-value" id="sum-deleg">0,00 zł</span>
        </div>
        <div class="summary-row markup">
            <span class="summary-label" style="color:#92400E;">Narzut</span>
            <span class="summary-value" id="sum-markup" style="color:#92400E;">0,00 zł</span>
        </div>
        <div class="summary-row total">
            <span class="summary-label" style="font-size:15px;">ŁĄCZNIE NETTO</span>
            <span class="summary-value" id="sum-total">0,00 zł</span>
        </div>
    </div>
</div>

{{-- â”€â”€ NOTATKI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-notes"></i>
        <span class="ed-card-title">Notatki wewnętrzne</span>
    </div>
    <div class="ed-card-body">
        <textarea name="notes" class="field-input" rows="3"
                  placeholder="Uwagi wewnętrzne (niewidoczne w PDF)...">{{ old('notes', $offer->notes) }}</textarea>
    </div>
</div>

{{-- ── TERMIN WAŻNOŚCI ─────────────────────────── --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-calendar-check"></i>
        <span class="ed-card-title">Termin ważności oferty</span>
    </div>
    <div class="ed-card-body">
        <div style="display:grid;grid-template-columns:1fr;">
            <div>
                <label class="field-label">Ważna do <span style="color:#DC2626;">*</span></label>
                <input type="date" name="valid_until" class="field-input" 
                       value="{{ old('valid_until', $offer->valid_until?->format('Y-m-d')) }}" required>
            </div>
        </div>
    </div>
</div>

{{-- ── OPIS DODATKOWY ────────────────────────── --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-file-description"></i>
        <span class="ed-card-title">Opis dodatkowy</span>
    </div>
    <div class="ed-card-body">
        <textarea name="additional_description" class="field-input" rows="4"
                  placeholder="Dodatkowe informacje, warunki, uwagi — będą widoczne w PDF oferty...">{{ old('additional_description', $offer->additional_description) }}</textarea>
    </div>
</div>

{{-- Inne pola z oryginalnego formularza potrzebne do walidacji --}}
<input type="hidden" name="offer_number"     value="{{ $offer->offer_number }}">
<input type="hidden" name="offer_slug"       value="{{ $offer->offer_slug }}">

{{-- Osoba prowadząca + status --}}
<div style="padding:14px 22px;border-bottom:1px solid #F0EDE6;display:grid;grid-template-columns:1fr 1fr 180px 160px;gap:14px;align-items:end;">
    <div>
        <label class="field-label">Osoba prowadząca (ENESA)</label>
        <select name="assigned_user_id" class="field-input">
            <option value="">— nieprzypisana —</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ $offer->assigned_user_id == $user->id ? 'selected' : '' }}>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label">Numer oferty</label>
        <input type="text" name="offer_number" class="field-input" value="{{ $offer->offer_number }}" required>
    </div>
    <div>
        <label class="field-label">Status</label>
        <select name="status" class="field-input" required>
            <option value="w_toku"         {{ $offer->status === 'w_toku'         ? 'selected' : '' }}>W toku</option>
            <option value="wygrana"        {{ $offer->status === 'wygrana'        ? 'selected' : '' }}>Wygrana</option>
            <option value="przegrana"      {{ $offer->status === 'przegrana'      ? 'selected' : '' }}>Przegrana</option>
            <option value="zarchiwizowana" {{ $offer->status === 'zarchiwizowana' ? 'selected' : '' }}>Zarchiwizowana</option>
        </select>
    </div>
    <div>
        <label class="field-label">Data utworzenia</label>
        <input type="date" name="created_at" class="field-input"
               value="{{ old('created_at', $offer->created_at->format('Y-m-d')) }}">
    </div>
</div>
<input type="hidden" name="liczba_wyjazdow"  value="1" id="h-wyjazdy">
<input type="hidden" name="liczba_noc"       value="0" id="h-noc">
<input type="hidden" name="liczba_osob"      value="1" id="h-osoby">
<input type="hidden" name="stawka_noc"       value="300" id="h-stawka-noc">
<input type="hidden" name="kwota_netto"      id="h-kwota-netto" value="{{ $offer->kwota_netto }}">

{{-- Rich editor hidden inputs --}}
<input type="hidden" id="hidden-text-sections"    name="text_sections"    value="">
<input type="hidden" id="hidden-price-sections"   name="price_sections"   value="">
<input type="hidden" id="hidden-show-unit"        name="show_unit_prices" value="{{ $offer->show_unit_prices ? '1' : '0' }}">

{{-- Delegation hidden inputs (sync from JS) --}}
<input type="hidden" name="km_do_klienta"    id="h-km">
<input type="hidden" name="stawka_km"        id="h-stawka-km">
<input type="hidden" name="czas_dojazdu_min" id="h-czas">
<input type="hidden" name="czy_kilkudniowy"  id="h-kilkudniowy" value="0">

</form>

{{-- ═══ MODAL: ZAPISZ JAKO... ═══ --}}
<div id="modal-clone"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-family:'Manrope',sans-serif;font-size:16px;font-weight:700;color:#1A1A1A;">
                <i class="ti ti-copy" style="color:#1A4D3A;margin-right:8px;"></i>Zapisz jako...
            </div>
            <button type="button" onclick="document.getElementById('modal-clone').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;line-height:1;">×</button>
        </div>

        {{-- Zapisz jako nowa oferta --}}
        <form method="POST" action="{{ route('offers.clone', $offer) }}" style="margin-bottom:12px;">
            @csrf
            <input type="hidden" name="mode" value="offer">
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:5px;font-family:'Manrope',sans-serif;">
                    Firma klienta (opcjonalnie)
                </label>
                <select name="company_id" class="field-input">
                    <option value="">— taka sama jak obecna —</option>
                    @foreach(\App\Models\Company::orderBy('name')->get() as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:#1A4D3A;color:#F5F0E8;border:none;border-radius:8px;padding:11px;font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:8px;">
                <i class="ti ti-file-plus"></i> Utwórz nową ofertę z tej treści
            </button>
        </form>

        {{-- Zapisz jako szablon --}}
        <form method="POST" action="{{ route('offers.clone', $offer) }}">
            @csrf
            <input type="hidden" name="mode" value="template">
            <button type="submit"
                    style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:#fff;color:#1A4D3A;border:2px solid #1A4D3A;border-radius:8px;padding:10px;font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;cursor:pointer;">
                <i class="ti ti-bookmark"></i> Zapisz jako szablon
            </button>
        </form>

        <div style="margin-top:12px;font-size:11px;color:#aaa;text-align:center;">
            Szablon to oferta-wzorzec którą możesz wielokrotnie wykorzystać.
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DATA INIT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
let priceSections = @json($offer->price_sections ?? null);
if (!priceSections || !Array.isArray(priceSections) || priceSections.length === 0) {
    priceSections = [{ id: 'main', name: 'Wycena ogólna', rows: [] }];
}
// Ensure main section exists
if (!priceSections[0]) priceSections[0] = { id: 'main', name: 'Wycena ogólna', rows: [] };

let sectionCounter = 100;
let rowCounter     = 1000;
const globalMarkup = { pct: 0, zl: 0 };

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// RTE HELPERS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function fmt(cmd) {
    document.execCommand(cmd, false, null);
}

// Focus tracking for RTE toolbar (each toolbar targets the editor in its card)
document.querySelectorAll('.rte-toolbar .rte-btn').forEach(btn => {
    btn.addEventListener('mousedown', e => e.preventDefault()); // keep editor focus
});

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// PRICE TABLE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function makePl(n) {
    return Number(n).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addRow(tbodyId, rowData) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const rid = 'r' + (rowCounter++);
    const d   = rowData || { opis: '', jedn: 'szt', ilosc: 1, cena_jedn: 0, z_narzutem: 0 };
    const tr  = document.createElement('tr');
    tr.id = 'row-' + rid;
    tr.dataset.rid = rid;
    tr.innerHTML = `
        <td style="text-align:center;color:#ccc;cursor:grab;"><i class="ti ti-grip-vertical"></i></td>
        <td><input class="cell-input" type="text" placeholder="Opis pozycji..." value="${escHtml(d.opis)}"></td>
        <td style="width:90px;"><select class="cell-input unit-select" style="width:90px;" data-prev="${escHtml(d.jedn)}" onchange="handleUnitChange(this)">${buildUnitOptions(d.jedn)}</select></td>
        <td style="width:70px;"><input class="cell-input qty-input" type="text" value="${d.ilosc}" placeholder="0" style="width:68px;" oninput="validateDecimal(this); recalcRow('${rid}')" onkeydown="return allowDecimalInput(event)"></td>
        <td style="width:110px;"><div style="display:flex;align-items:center;gap:4px;"><input class="cell-input price-input" type="text" value="${d.cena_jedn}" placeholder="0" style="width:80px;" oninput="validateDecimal(this); recalcRow('${rid}')" onkeydown="return allowDecimalInput(event)"><span style="font-size:11px;color:#999;white-space:nowrap;">zł</span></div></td>
        <td style="text-align:right;white-space:nowrap;padding-right:8px;"><span class="net-display">${makePl(d.ilosc * d.cena_jedn)}</span>&nbsp;<span style="font-size:11px;color:#999;">zł</span></td>
        <td style="text-align:right;white-space:nowrap;padding-right:10px;font-weight:600;"><span class="markup-display">${makePl(d.z_narzutem)}</span>&nbsp;<span style="font-size:11px;color:#999;white-space:nowrap;">zł</span><input type="hidden" class="markup-input" value="${d.z_narzutem}"></td>
        <td><button type="button" class="btn-del-row" onclick="removeRow(this)"><i class="ti ti-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    recalcAll();
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function removeRow(btn) {
    btn.closest('tr').remove();
    recalcAll();
}

function parsePl(str) {
    return parseFloat(str.replace(/\s/g,'').replace(',','.').replace(/[^\d.-]/g,'')) || 0;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// MARKUP SYNC
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function syncMarkup(source) {
    let sumBase = 0;
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        const ilosc = parseFloat(tr.querySelector('.ilosc-input')?.value) || 0;
        const cena  = parseFloat(tr.querySelector('.cena-input')?.value)  || 0;
        sumBase += ilosc * cena;
    });

    if (source === 'pct') {
        const pct = parseFloat(document.getElementById('markup-pct').value) || 0;
        globalMarkup.pct = pct;
        const zl = sumBase * (pct / 100);
        globalMarkup.zl = zl;
        document.getElementById('markup-zl').value = zl.toFixed(2);
    } else {
        const zl = parseFloat(document.getElementById('markup-zl').value) || 0;
        globalMarkup.zl = zl;
        const pct = sumBase > 0 ? (zl / sumBase) * 100 : 0;
        globalMarkup.pct = pct;
        document.getElementById('markup-pct').value = pct.toFixed(2);
    }
    recalcAll();
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DELEGATION CALC
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function calcDeleg() {
    const kmEl = document.getElementById('d_km');
    if (!kmEl) return; // delegacje obsługuje teraz dynamiczny builder (delegRender)
    const km       = parseValue(kmEl.value)        || 0;
    const stawkaKm = parseValue(document.getElementById('d_stawka_km').value) || 1.10;
    const wyjazdy  = parseValue(document.getElementById('d_wyjazdy').value)   || 1;
    const over     = document.getElementById('d_kilkudniowy').checked;
    const noc      = parseValue(document.getElementById('d_noc')?.value)      || 0;
    const osoby    = parseValue(document.getElementById('d_osoby')?.value)    || 1;
    const stawkaNoc= parseValue(document.getElementById('d_stawka_noc')?.value) || 300;

    const deleg = (km * 2 * wyjazdy * stawkaKm) + (over ? noc * osoby * stawkaNoc : 0);
    document.getElementById('deleg-result').textContent = makePl(deleg) + ' zł';
    recalcAll();
}

function toggleOvernight(cb) {
    document.getElementById('overnightSection').style.display = cb.checked ? 'block' : 'none';
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT PRICES TOGGLE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function toggleUnitPrices(cb) {
    document.getElementById('hidden-show-unit').value = cb.checked ? '1' : '0';

    // Toggle hide-units class on all price tables in UI
    document.querySelectorAll('.price-table').forEach(t => {
        t.classList.toggle('hide-units', !cb.checked);
    });

    // Update PDF link to pass current toggle state as query param
    const pdfLink = document.getElementById('pdf-link');
    if (pdfLink) {
        const base = pdfLink.href.split('?')[0];
        pdfLink.href = base + '?unit=' + (cb.checked ? '1' : '0');
    }

    // Also save to DB via AJAX
    fetch('{{ route('offers.unit-prices', $offer) }}', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ show_unit_prices: cb.checked ? 1 : 0 })
    });
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DYNAMIC SECTIONS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function addSection(sectionData) {
    const sid  = 'sec' + (sectionCounter++);
    const name = sectionData?.name || 'Nowa sekcja';
    const container = document.getElementById('dynamic-sections');

    const card = document.createElement('div');
    card.className  = 'ed-card';
    card.id         = 'section-' + sid;
    card.style.marginBottom = '16px';
    card.innerHTML = `
        <div class="ed-card-header">
            <i class="ti ti-calculator"></i>
            <input type="text" class="section-name-input" value="${escHtml(name)}">
            <button type="button" class="btn-del-section" onclick="removeSection('${sid}')">
                <i class="ti ti-trash"></i> Usuń sekcję
            </button>
        </div>
        <div style="overflow-x:auto;">
            <table class="price-table" id="table-${sid}">
                <thead>
                    <tr>
                        <th style="width:28px;"></th>
                        <th>Opis pozycji</th>
                        <th class="unit-col" style="width:70px;">Jedn.</th>
                        <th class="unit-col" style="width:80px;">Ilość</th>
                        <th class="unit-col" style="width:130px;">Cena jedn. netto</th>
                        <th style="width:130px;">Wartość netto</th>
                        <th style="width:130px;text-align:right;">Kwota</th>
                        <th style="width:32px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-${sid}"></tbody>
            </table>
        </div>
        <div style="padding:10px 16px;">
            <button type="button" class="btn-add-row" onclick="addRow('tbody-${sid}')">
                <i class="ti ti-plus"></i> Dodaj pozycję
            </button>
        </div>
    `;
    container.appendChild(card);

    // Apply current unit visibility
    const unitToggle = document.getElementById('show-unit-toggle');
    if (!unitToggle.checked) {
        document.getElementById('table-' + sid).classList.add('hide-units');
    }

    // Load rows if from data
    if (sectionData?.rows?.length) {
        sectionData.rows.forEach(r => addRow('tbody-' + sid, r));
    } else {
        addRow('tbody-' + sid);
    }
}

function removeSection(sid) {
    if (!confirm('Usunąć tę sekcję wyceny?')) return;
    document.getElementById('section-' + sid)?.remove();
    recalcAll();
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// COLLECT DATA BEFORE SUBMIT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function collectSections() {
    const sections = [];

    // Main section
    const mainSection = { id: 'main', name: '', rows: [] };
    mainSection.name = document.getElementById('section-main-name')?.value || 'Wycena ogólna';
    document.querySelectorAll('#tbody-main tr').forEach(tr => {
        mainSection.rows.push(collectRow(tr));
    });
    sections.push(mainSection);

    // Dynamic sections
    document.querySelectorAll('#dynamic-sections .ed-card').forEach(card => {
        const sec = { id: card.id.replace('section-', ''), name: '', rows: [] };
        sec.name = card.querySelector('.section-name-input')?.value || 'Sekcja';
        card.querySelectorAll('tbody tr').forEach(tr => {
            sec.rows.push(collectRow(tr));
        });
        sections.push(sec);
    });

    return sections;
}

function syncDelegHiddens() {
    // Delegacje pochodzą teraz z dynamicznego buildera (delegSections).
    // Wypełniamy stare ukryte pola na podstawie pierwszej lokalizacji,
    // aby zachować zgodność z istniejącym zapisem OfferDelegation.
    if (typeof delegSections === 'undefined' || !delegSections.length) return;
    const first = delegSections[0];
    const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
    setVal('h-km',          first.km || 0);
    setVal('h-stawka-km',   first.stawka_km || 1.10);
    setVal('h-czas',        0);
    setVal('h-wyjazdy',     first.wyjazdy || 1);
    setVal('h-kilkudniowy', (first.noce > 0) ? '1' : '0');
    setVal('h-noc',         first.noce || 0);
    setVal('h-osoby',       first.osoby || 1);
    setVal('h-stawka-noc',  first.stawka_noc || 300);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// FORM SUBMIT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
document.getElementById('offer-form').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
        e.preventDefault();
    }
});

document.getElementById('offer-form').addEventListener('submit', function () {
    // Convert decimal separators from comma to dot for numeric fields
    const decimalFields = [
        'd_km', 'd_stawka_km', 'd_czas', 'd_wyjazdy', 'd_noc', 'd_osoby', 'd_stawka_noc',
        'markup-pct', 'markup-zl'
    ];
    
    decimalFields.forEach(id => {
        const field = document.getElementById(id);
        if (field && field.value) {
            field.value = field.value.toString().replace(',', '.');
        }
    });
    
    // Also convert table inputs (qty and price)
    document.querySelectorAll('.qty-input, .price-input').forEach(input => {
        if (input.value) {
            input.value = input.value.toString().replace(',', '.');
        }
    });
    
    // Collect dynamic text sections
    document.getElementById('hidden-text-sections').value = JSON.stringify(collectTextSections());

    // Collect price sections
    document.getElementById('hidden-price-sections').value = JSON.stringify(collectSections());

    // Show_unit_prices
    document.getElementById('hidden-show-unit').value = document.getElementById('show-unit-toggle').checked ? '1' : '0';

    // Delegation
    syncDelegHiddens();
});

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// INIT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
/* ── Unit helpers (overrides) ── */
const UNIT_OPTIONS = ['szt','godz','dni','kg','km','m','m\u00B2','m\u00B3','l','t','kpl','us\u0142uga'];
function buildUnitOptions(selected) {
    return UNIT_OPTIONS.map(u =>
        `<option value="${escHtml(u)}" ${u === selected ? 'selected' : ''}>${escHtml(u)}</option>`
    ).join('') + `<option value="__custom__">+ Dodaj jednostk\u0119...</option>`;
}
function handleUnitChange(sel) {
    if (sel.value === '__custom__') {
        const custom = prompt('Wpisz nazw\u0119 nowej jednostki:');
        if (custom && custom.trim()) {
            const val = custom.trim();
            document.querySelectorAll('.unit-select').forEach(s => {
                if (![...s.options].some(o => o.value === val)) {
                    const opt = document.createElement('option');
                    opt.value = val; opt.textContent = val;
                    s.insertBefore(opt, s.lastElementChild);
                }
            });
            sel.value = val;
            sel.dataset.prev = val;
        } else {
            sel.value = sel.dataset.prev || 'szt';
        }
    } else {
        sel.dataset.prev = sel.value;
    }
}

/* ── recalcRow override: takes row ID (string), not DOM element ── */
function recalcRow(id) {
    const tr = document.getElementById('row-' + id);
    if (!tr) return;
    const qty   = parseFloat(tr.querySelector('.qty-input')?.value)   || 0;
    const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
    const net   = qty * price;
    const pct   = parseFloat(document.getElementById('markup-pct').value) || 0;
    const zn    = net * (1 + pct / 100);
    const netDisplay = tr.querySelector('.net-display');
    if (netDisplay) netDisplay.textContent = makePl(net);
    const markupInput = tr.querySelector('.markup-input');
    if (markupInput) markupInput.value = zn.toFixed(2);
    const markupDisplay = tr.querySelector('.markup-display');
    if (markupDisplay) markupDisplay.textContent = makePl(zn);
    recalcAll();
}

/* ── recalcAll override: uses .markup-input ── */
function recalcAll() {
    let sumNetto = 0;
    const pct    = parseFloat(document.getElementById('markup-pct').value) || 0;
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.qty-input')?.value)   || 0;
        const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
        const net   = qty * price;
        sumNetto += net;
    });
    const delegCost = parsePl(document.getElementById('deleg-result').textContent);
    const markupZl  = sumNetto * (pct / 100);
    document.getElementById('markup-zl').value = markupZl.toFixed(2);
    const total     = sumNetto + delegCost + markupZl;
    document.getElementById('sum-services').textContent = makePl(sumNetto) + ' z\u0142';
    document.getElementById('sum-deleg').textContent    = makePl(delegCost) + ' z\u0142';
    document.getElementById('sum-markup').textContent   = makePl(markupZl) + ' z\u0142';
    document.getElementById('sum-total').textContent    = makePl(total) + ' z\u0142';
    document.getElementById('h-kwota-netto').value = total.toFixed(2);
}

/* ── syncMarkup override: uses .qty-input / .price-input ── */
function syncMarkup(source) {
    let sumNetto = 0;
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        sumNetto += (parseFloat(tr.querySelector('.qty-input')?.value)   || 0)
                  * (parseFloat(tr.querySelector('.price-input')?.value) || 0);
    });
    if (source === 'pct') {
        const pct = parseFloat(document.getElementById('markup-pct').value) || 0;
        globalMarkup.pct = pct;
        globalMarkup.zl  = sumNetto * (pct / 100);
    } else {
        const zl = parseFloat(document.getElementById('markup-zl').value) || 0;
        globalMarkup.zl  = zl;
        const pct = sumNetto > 0 ? (zl / sumNetto) * 100 : 0;
        globalMarkup.pct = pct;
        document.getElementById('markup-pct').value = pct.toFixed(2);
    }
    // Recalculate Kwota for each row with updated markup
    document.querySelectorAll('.price-table tbody tr[data-rid]').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.qty-input')?.value)   || 0;
        const price = parseFloat(tr.querySelector('.price-input')?.value) || 0;
        const pct   = parseFloat(document.getElementById('markup-pct').value) || 0;
        const zn    = qty * price * (1 + pct / 100);
        const markupInput = tr.querySelector('.markup-input');
        if (markupInput) markupInput.value = zn.toFixed(2);
        const markupDisplay = tr.querySelector('.markup-display');
        if (markupDisplay) markupDisplay.textContent = makePl(zn);
    });
    recalcAll();
}

/* ── collectRow override: uses new selectors ── */
function collectRow(tr) {
    const qty   = parseFloat(tr.querySelector('.qty-input')?.value)    || 0;
    const price = parseFloat(tr.querySelector('.price-input')?.value)  || 0;
    const pct   = parseFloat(document.getElementById('markup-pct').value) || 0;
    return {
        opis:       tr.querySelector('input[type="text"]')?.value || '',
        jedn:       tr.querySelector('.unit-select')?.value           || 'szt',
        ilosc:      qty,
        cena_jedn:  price,
        z_narzutem: qty * price * (1 + pct / 100),
    };
}

document.addEventListener('DOMContentLoaded', function () {
    // Load main section rows
    const mainRows = priceSections[0]?.rows || [];
    if (mainRows.length > 0) {
        mainRows.forEach(r => addRow('tbody-main', r));
    } else {
        addRow('tbody-main');
    }

    // Load dynamic sections
    priceSections.slice(1).forEach(sec => addSection(sec));

    // Load existing text sections (or fallback to old columns)
    const existingTextSections = @json($existingTextSections);
    existingTextSections.forEach(s => addTextSection(s));

    // Apply unit toggle initial state
    toggleUnitPrices(document.getElementById('show-unit-toggle'));

    // Init delegation calc
    const distanceInfo = document.getElementById('company-distance-info');
    calcDeleg();

    /* ── Distance Matrix ── */
    const companySelect = document.getElementById('company_id');
    if (companySelect) {
        companySelect.addEventListener('change', function () {
            const companyId = this.value;
            if (!companyId) {
                if (distanceInfo) distanceInfo.style.display = 'none';
                return;
            }
            fetch("{{ route('offers.get-distance') }}?company_id=" + encodeURIComponent(companyId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.km !== undefined) {
                    if (typeof delegSections !== 'undefined' && delegSections[0]) {
                        delegSections[0].km = data.km;
                        if (typeof delegRender === 'function') delegRender();
                    }
                    if (distanceInfo) {
                        distanceInfo.textContent = '\uD83D\uDCCD ' + data.address + ' \u2014 ' + data.km + ' km (' + data.minutes + ' min)';
                        distanceInfo.style.display = 'block';
                    }
                } else if (distanceInfo) {
                    distanceInfo.textContent = '\u26A0\uFE0F ' + (data.error || 'Nie uda\u0142o si\u0119 pobra\u0107 odleg\u0142o\u015bci.');
                    distanceInfo.style.display = 'block';
                }
            })
            .catch(() => {
                if (distanceInfo) {
                    distanceInfo.textContent = '\u26A0\uFE0F B\u0142\u0105d po\u0142\u0105czenia z serwerem.';
                    distanceInfo.style.display = 'block';
                }
            });
        });
    }

    /* also trigger Distance Matrix for edit (fetch current company) */
    const fetchDistBtn = document.getElementById('btn-fetch-distance');
    if (fetchDistBtn) fetchDistBtn.onclick = function () {
        const hiddenCid = document.querySelector('select[name="company_id"], input[name="company_id"]');
        if (!hiddenCid || !hiddenCid.value) return;
        fetch("{{ route('offers.get-distance') }}?company_id=" + encodeURIComponent(hiddenCid.value), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.km !== undefined) {
                if (typeof delegSections !== 'undefined' && delegSections[0]) {
                    delegSections[0].km = data.km;
                    if (typeof delegRender === 'function') delegRender();
                }
                if (distanceInfo) {
                    distanceInfo.textContent = '\uD83D\uDCCD ' + data.address + ' \u2014 ' + data.km + ' km (' + data.minutes + ' min)';
                    distanceInfo.style.display = 'block';
                }
            } else if (distanceInfo) {
                distanceInfo.textContent = '\u26A0\uFE0F ' + (data.error || 'Nie uda\u0142o si\u0119 pobra\u0107.');
                distanceInfo.style.display = 'block';
            }
        });
    };
}); // DOMContentLoaded

function updateCompanyInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('disp-name').textContent = opt.dataset.name || '—';
    let details = '';
    if (opt.dataset.address) details += opt.dataset.address;
    if (opt.dataset.address && opt.dataset.city) details += ', ';
    if (opt.dataset.city) details += opt.dataset.city;
    if (opt.dataset.nip) details += '\nNIP: ' + opt.dataset.nip;
    if (opt.dataset.email) details += '\n' + opt.dataset.email;
    document.getElementById('disp-details').innerText = details;

    const distInfo = document.getElementById('company-distance-info');
    fetch("{{ route('offers.get-distance') }}?company_id=" + encodeURIComponent(opt.value), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.km !== undefined) {
            if (typeof delegSections !== 'undefined' && delegSections[0]) {
                delegSections[0].km = data.km;
                if (typeof delegRender === 'function') delegRender();
            }
            distInfo.textContent = '\uD83D\uDCCD ' + data.address + ' \u2014 ' + data.km + ' km (' + data.minutes + ' min)';
            distInfo.style.display = 'block';
        }
    })
    .catch(() => {});

    // Uzupełnij pierwszą lokalizację delegacji adresem nowego klienta
    if (typeof delegSections !== 'undefined' && delegSections.length > 0) {
        const addr = [opt.dataset.address, opt.dataset.city].filter(Boolean).join(', ');
        delegSections[0].nazwa = opt.dataset.name || 'Siedziba zamawiającego';
        delegSections[0].adres = addr;
        delegRender();
        if (addr) delegFetchKm(0);
    }
}

/* ── Funkcje do obsługi wartości z przecinkami ── */
function allowDecimalInput(event) {
    // Allow: 0-9, comma, dot, backspace, delete, arrows, tab
    const key = event.key;
    if (/^[0-9.,\-]$/.test(key) || 
        ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'].includes(key) ||
        event.ctrlKey) {
        return true;
    }
    event.preventDefault();
    return false;
}

function validateDecimal(input) {
    if (!input.value) return;
    // Replace dot with comma if needed (for Polish locale)
    let val = input.value.toString();
    val = val.replace('.', ',');
    
    // Allow only numbers, comma, and minus (for negative values if needed)
    val = val.replace(/[^0-9,\-]/g, '');
    
    // Ensure only one comma
    const parts = val.split(',');
    if (parts.length > 2) {
        val = parts[0] + ',' + parts.slice(1).join('');
    }
    
    // Limit to 2 decimal places
    if (parts.length === 2 && parts[1].length > 2) {
        val = parts[0] + ',' + parts[1].substring(0, 2);
    }
    
    input.value = val;
}

// Helper: convert text value (with comma) to number
function parseValue(val) {
    if (!val) return 0;
    val = val.toString().replace(',', '.');
    return parseFloat(val) || 0;
}

// Override parseFloat in calculations - wrap all parseFloat calls
const originalParseFloat = window.parseFloat;

// Update recalcRow to use parseValue
const originalRecalcRow = window.recalcRow;
window.recalcRow = function(rid) {
    if (!rid) return;
    const tr = document.querySelector(`tr[data-rid="${rid}"]`);
    if (!tr) return;
    const qty   = parseValue(tr.querySelector('.qty-input')?.value);
    const price = parseValue(tr.querySelector('.price-input')?.value);
    const net   = qty * price;
    const pct   = parseValue(document.getElementById('markup-pct').value);
    const zn    = net * (1 + pct / 100);
    const netDisplay = tr.querySelector('.net-display');
    if (netDisplay) netDisplay.textContent = makePl(net);
    const markupInput = tr.querySelector('.markup-input');
    if (markupInput) markupInput.value = zn.toFixed(2);
    const markupDisplay = tr.querySelector('.markup-display');
    if (markupDisplay) markupDisplay.textContent = makePl(zn);
    recalcAll();
};

// Update recalcAll to use parseValue
const originalRecalcAll = window.recalcAll;
window.recalcAll = function() {
    let sumNetto = 0;
    const pct    = parseValue(document.getElementById('markup-pct').value);
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        const qty   = parseValue(tr.querySelector('.qty-input')?.value);
        const price = parseValue(tr.querySelector('.price-input')?.value);
        const net   = qty * price;
        sumNetto += net;
    });
    const delegCost = parsePl(document.getElementById('deleg-result').textContent);
    const markupZl  = sumNetto * (pct / 100);
    document.getElementById('markup-zl').value = markupZl.toFixed(2);
    const total     = sumNetto + delegCost + markupZl;
    document.getElementById('sum-services').textContent = makePl(sumNetto) + ' z\u0142';
    document.getElementById('sum-deleg').textContent    = makePl(delegCost) + ' z\u0142';
    document.getElementById('sum-markup').textContent   = makePl(markupZl) + ' z\u0142';
    document.getElementById('sum-total').textContent    = makePl(total) + ' z\u0142';
    document.getElementById('h-kwota-netto').value = total.toFixed(2);
};

// Update syncMarkup to use parseValue
const originalSyncMarkup = window.syncMarkup;
window.syncMarkup = function(source) {
    let sumNetto = 0;
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        sumNetto += parseValue(tr.querySelector('.qty-input')?.value)
                  * parseValue(tr.querySelector('.price-input')?.value);
    });
    if (source === 'pct') {
        const pct = parseValue(document.getElementById('markup-pct').value);
        globalMarkup.pct = pct;
        globalMarkup.zl  = sumNetto * (pct / 100);
    } else {
        const zl = parseValue(document.getElementById('markup-zl').value);
        globalMarkup.zl  = zl;
        const pct = sumNetto > 0 ? (zl / sumNetto) * 100 : 0;
        globalMarkup.pct = pct;
        document.getElementById('markup-pct').value = pct.toFixed(2);
    }
    document.querySelectorAll('.price-table tbody tr[data-rid]').forEach(tr => {
        const qty   = parseValue(tr.querySelector('.qty-input')?.value);
        const price = parseValue(tr.querySelector('.price-input')?.value);
        const pct   = parseValue(document.getElementById('markup-pct').value);
        const zn    = qty * price * (1 + pct / 100);
        const markupInput = tr.querySelector('.markup-input');
        if (markupInput) markupInput.value = zn.toFixed(2);
        const markupDisplay = tr.querySelector('.markup-display');
        if (markupDisplay) markupDisplay.textContent = makePl(zn);
    });
    recalcAll();
};

// Update collectRow to use parseValue
const originalCollectRow = window.collectRow;
window.collectRow = function(tr) {
    const qty   = parseValue(tr.querySelector('.qty-input')?.value);
    const price = parseValue(tr.querySelector('.price-input')?.value);
    const pct   = parseValue(document.getElementById('markup-pct').value);
    return {
        opis:       tr.querySelector('input[type="text"]')?.value || '',
        jedn:       tr.querySelector('.unit-select')?.value           || 'szt',
        ilosc:      qty,
        cena_jedn:  price,
        z_narzutem: qty * price * (1 + pct / 100),
    };
};

/* ── Dynamiczne sekcje opisowe ─────────────────────── */
let textSectionCounter = 0;
let textQuills = {};

function addTextSection(sectionData) {
    const sid = 'txt' + (textSectionCounter++);
    const name = sectionData?.name || 'Nowa sekcja';
    const content = sectionData?.content || '';

    const card = document.createElement('div');
    card.className = 'ed-card type-text';
    card.id = 'text-section-' + sid;
    card.style.marginBottom = '16px';
    card.innerHTML = `
        <div class="ed-card-header">
            <i class="ti ti-file-text"></i>
            <input type="text" class="section-name-input text-section-name" value="${name.replace(/"/g,'&quot;')}" style="flex:1;">
            <button type="button" class="btn-del-section" onclick="removeTextSection('${sid}')">
                <i class="ti ti-trash"></i> Usuń sekcję
            </button>
        </div>
        <div class="rte-toolbar" data-target="editor-${sid}">
            <button type="button" class="rte-btn" onclick="fmtOn('editor-${sid}','bold')"><b>B</b></button>
            <button type="button" class="rte-btn" onclick="fmtOn('editor-${sid}','italic')"><i>I</i></button>
            <button type="button" class="rte-btn" onclick="fmtOn('editor-${sid}','underline')"><u>U</u></button>
            <button type="button" class="rte-btn" onclick="fmtOn('editor-${sid}','list','bullet')"><i class="ti ti-list"></i></button>
            <button type="button" class="rte-btn" onclick="fmtOn('editor-${sid}','list','ordered')"><i class="ti ti-list-numbers"></i></button>
            <button type="button" class="rte-btn rte-btn-ai" onclick="aiAssistDynamic('${sid}')"><i class="ti ti-wand"></i> Popraw AI</button>
        </div>
        <div id="editor-${sid}" style="min-height:90px;font-size:14px;"></div>
    `;
    document.getElementById('text-sections-container').appendChild(card);

    const toolbarOptions = [['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['clean']];
    textQuills[sid] = new Quill('#editor-' + sid, { theme: 'snow', modules: { toolbar: toolbarOptions } });
    if (content) textQuills[sid].clipboard.dangerouslyPasteHTML(content);
}

function removeTextSection(sid) {
    if (!confirm('Usunąć tę sekcję?')) return;
    document.getElementById('text-section-' + sid)?.remove();
    delete textQuills[sid];
}

function fmtOn(editorId, cmd, value) {
    document.execCommand(cmd, false, value || null);
}

function collectTextSections() {
    const sections = [];
    document.querySelectorAll('#text-sections-container .ed-card').forEach(card => {
        const sid = card.id.replace('text-section-', '');
        const name = card.querySelector('.text-section-name')?.value || 'Sekcja';
        const content = textQuills[sid] ? textQuills[sid].root.innerHTML : '';
        sections.push({ name, content });
    });
    return sections;
}

async function aiAssistDynamic(sid) {
    const card = document.getElementById('text-section-' + sid);
    const name = card.querySelector('.text-section-name')?.value || 'Sekcja';
    const quill = textQuills[sid];
    const current = quill.root.innerHTML.trim();
    if (!current || current === '<p><br></p>') {
        alert('Najpierw wpisz tekst do poprawy.');
        return;
    }
    const btn = card.querySelector('.rte-btn-ai');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader-2"></i> Poprawiam...';
    try {
        const offerTitle = document.querySelector('input[name="offer_title"]')?.value || '';
        const companySel = document.getElementById('company_id_select');
        const companyName = companySel?.options[companySel.selectedIndex]?.text?.split('—')[0]?.trim() || '';
        const res = await fetch('{{ route("offers.ai-assist") }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ field: 'custom_' + sid, section_name: name, mode: 'improve', current, offer_title: offerTitle, company_name: companyName })
        });
        const data = await res.json();
        if (data.html) quill.clipboard.dangerouslyPasteHTML(data.html);
        else alert('Błąd AI: ' + (data.error || 'nieznany'));
    } catch(e) { alert('Błąd połączenia z AI'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places"></script>
<script>
// ── Delegacje builder ──────────────────────────────────────────────

const DELEG_STAWKA_KM  = 1.10;
const DELEG_STAWKA_NOC = 200;
const DIST_URL = '{{ route("offers.get-distance") }}';

let delegSections = @json(old('delegations') ? json_decode(old('delegations'), true) : ($offer->delegations ?? []));

// Jeśli brak danych JSON ale istnieje stary offerDelegation — importuj go (zachowanie 
// zapisanych wcześniej delegacji w starym formacie)
@if($offer->delegations === null && $offer->offerDelegation)
delegSections = [{
    nazwa:    'Siedziba zamawiającego',
    adres:    '',
    km:       {{ (int)($offer->offerDelegation->km_do_klienta ?? 0) }},
    wyjazdy:  {{ (int)($offer->offerDelegation->liczba_wyjazdow ?? 1) }},
    osoby:    {{ (int)($offer->offerDelegation->liczba_osob ?? 1) }},
    noce:     {{ (int)($offer->offerDelegation->liczba_noc ?? 0) }},
    stawka_km:  {{ (float)($offer->offerDelegation->stawka_km ?? 1.10) }},
    stawka_noc: {{ (float)($offer->offerDelegation->stawka_noc ?? 200) }},
}];
@endif

if (!delegSections) {
    delegSections = [];
}

function delegFmt(n) {
    return parseFloat(n || 0).toLocaleString('pl-PL', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function escD(s) {
    return (s || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function delegRender() {
    const wrap = document.getElementById('deleg-sections');
    if (!wrap) return;
    wrap.innerHTML = '';

    if (delegSections.length === 0) {
        wrap.innerHTML = '<div style="padding:14px;text-align:center;color:#aaa;font-size:13px;font-family:\'Manrope\',sans-serif;">Brak lokalizacji — kliknij „Dodaj lokalizację"</div>';
        delegSave(); return;
    }

    delegSections.forEach(function(sec, idx) {
        const total = delegCalc(sec);
        const div = document.createElement('div');
        div.style.cssText = 'background:#FAFAF6;border:1px solid #E5E1D8;border-radius:10px;padding:18px;margin-bottom:14px;';
        div.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="ti ti-map-pin" style="color:#1A4D3A;font-size:16px;"></i>
                    <strong style="font-family:'Manrope',sans-serif;font-size:13px;color:#1A1A1A;">
                        Lokalizacja ${idx + 1}
                    </strong>
                </div>
                <button type="button" onclick="delegRemoveSection(${idx})"
                    style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:4px;">
                    <i class="ti ti-trash"></i> Usuń
                </button>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label class="field-label">Nazwa lokalizacji</label>
                    <input type="text" class="field-input" placeholder="np. Siedziba, Fabryka Kraków"
                        value="${escD(sec.nazwa)}"
                        oninput="delegSections[${idx}].nazwa=this.value;delegSave()">
                </div>
                <div>
                    <label class="field-label">Adres / Miasto</label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" class="field-input"
                            id="deleg-adres-${idx}"
                            placeholder="np. Warszawa lub ul. Jana 1, Kraków"
                            value="${escD(sec.adres)}"
                            oninput="delegSections[${idx}].adres=this.value;delegSave()"
                            style="flex:1;min-width:0;">
                        <button type="button" onclick="delegFetchKm(${idx})"
                            id="deleg-mapbtn-${idx}"
                            style="display:inline-flex;align-items:center;gap:4px;background:#1A4D3A;color:#fff;border:none;border-radius:6px;padding:7px 10px;font-size:11px;font-family:'Manrope',sans-serif;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;">
                            <i class="ti ti-map-pin"></i> Pobierz km
                        </button>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px;">
                <div>
                    <label class="field-label">Km (w jedną stronę)</label>
                    <input type="number" class="field-input" min="0" step="1"
                        id="deleg-km-${idx}"
                        value="${sec.km}"
                        oninput="delegSections[${idx}].km=+this.value;delegUpdateTotal(${idx})">
                </div>
                <div>
                    <label class="field-label">Liczba wyjazdów</label>
                    <input type="number" class="field-input" min="1" step="1"
                        value="${sec.wyjazdy}"
                        oninput="delegSections[${idx}].wyjazdy=+this.value;delegUpdateTotal(${idx})">
                </div>
                <div>
                    <label class="field-label">Liczba osób</label>
                    <input type="number" class="field-input" min="1" step="1"
                        value="${sec.osoby}"
                        oninput="delegSections[${idx}].osoby=+this.value;delegUpdateTotal(${idx})">
                </div>
                <div>
                    <label class="field-label">Liczba noclegów</label>
                    <input type="number" class="field-input" min="0" step="1"
                        value="${sec.noce}"
                        oninput="delegSections[${idx}].noce=+this.value;delegUpdateTotal(${idx})">
                </div>
                <div>
                    <label class="field-label">Stawka za km (zł)</label>
                    <input type="number" class="field-input" min="0" step="0.01"
                        value="${sec.stawka_km}"
                        oninput="delegSections[${idx}].stawka_km=+this.value;delegUpdateTotal(${idx})">
                </div>
                <div>
                    <label class="field-label">Stawka za nocleg (zł)</label>
                    <input type="number" class="field-input" min="0" step="1"
                        value="${sec.stawka_noc}"
                        oninput="delegSections[${idx}].stawka_noc=+this.value;delegUpdateTotal(${idx})">
                </div>
            </div>

            <div id="deleg-total-${idx}" style="background:#E8F5E9;border-radius:7px;padding:9px 14px;font-size:13px;font-family:'Manrope',sans-serif;color:#1A4D3A;font-weight:700;">
                Koszt tej lokalizacji: ${delegFmt(total)} zł
            </div>
        `;
        wrap.appendChild(div);
        delegInitAutocomplete(idx);
    });

    delegSave();
}

function delegCalc(sec) {
    const km  = (sec.km || 0) * 2 * (sec.wyjazdy || 1) * (sec.stawka_km || DELEG_STAWKA_KM);
    const noc = (sec.noce || 0) * (sec.osoby || 1) * (sec.stawka_noc || DELEG_STAWKA_NOC);
    return km + noc;
}

function delegUpdateTotal(idx) {
    const el = document.getElementById('deleg-total-' + idx);
    if (el) el.textContent = 'Koszt tej lokalizacji: ' + delegFmt(delegCalc(delegSections[idx])) + ' zł';
    delegSave();
}

function delegAddSection() {
    delegSections.push({
        nazwa: '', adres: '', km: 0, wyjazdy: 1, osoby: 1, noce: 0,
        stawka_km: DELEG_STAWKA_KM, stawka_noc: DELEG_STAWKA_NOC
    });
    delegRender();
    const wrap = document.getElementById('deleg-sections');
    if (wrap && wrap.lastElementChild) {
        wrap.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function delegRemoveSection(idx) {
    delegSections.splice(idx, 1);
    delegRender();
}

function delegSave() {
    const jsonEl = document.getElementById('delegations-json');
    if (jsonEl) jsonEl.value = JSON.stringify(delegSections);
    const grand = delegSections.reduce(function(s, sec) { return s + delegCalc(sec); }, 0);
    const dr = document.getElementById('deleg-result');
    if (dr) dr.textContent = delegFmt(grand) + ' zł';
    if (typeof recalcAll === 'function') recalcAll();
}

async function delegFetchKm(idx) {
    const adresEl = document.getElementById('deleg-adres-' + idx);
    const kmEl    = document.getElementById('deleg-km-' + idx);
    const btn     = document.getElementById('deleg-mapbtn-' + idx);
    const adres   = adresEl ? adresEl.value.trim() : '';

    if (!adres) { alert('Wpisz adres lub miasto przed pobraniem odległości.'); return; }

    const origHTML = btn ? btn.innerHTML : '';
    if (btn) { btn.innerHTML = '<i class="ti ti-loader-2"></i>'; btn.disabled = true; }

    try {
        const res  = await fetch(DIST_URL + '?destination=' + encodeURIComponent(adres), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.km !== undefined) {
            delegSections[idx].km = data.km;
            if (kmEl) kmEl.value = data.km;
            delegUpdateTotal(idx);
            const totalEl = document.getElementById('deleg-total-' + idx);
            if (totalEl) {
                totalEl.innerHTML = 'Koszt tej lokalizacji: <strong>'
                    + delegFmt(delegCalc(delegSections[idx])) + ' zł</strong>'
                    + '&nbsp;&nbsp;—&nbsp;&nbsp;📍 ' + data.km + ' km (' + data.minutes + ' min od Gliwic)';
            }
        } else {
            alert('Błąd: ' + (data.error || 'Sprawdź wpisany adres.'));
        }
    } catch (e) {
        alert('Błąd połączenia z serwerem.');
    } finally {
        if (btn) { btn.innerHTML = origHTML; btn.disabled = false; }
    }
}

function delegInitAutocomplete(idx) {
    const input = document.getElementById('deleg-adres-' + idx);
    if (!input || typeof google === 'undefined' || !google.maps || !google.maps.places) return;
    const ac = new google.maps.places.Autocomplete(input, {
        componentRestrictions: { country: 'pl' },
        fields: ['formatted_address'],
        types: ['geocode']
    });
    ac.addListener('place_changed', function() {
        const place = ac.getPlace();
        if (!place || !place.formatted_address) return;
        delegSections[idx].adres = place.formatted_address;
        input.value = place.formatted_address;
        delegSave();
        delegFetchKm(idx);
    });
}

// Auto-uzupełnienie przy ładowaniu — pierwsza lokalizacja z danych firmy
if (delegSections.length > 0 && !delegSections[0].adres) {
    const compSel = document.getElementById('company_id_select');
    if (compSel && compSel.value) {
        const opt = compSel.options[compSel.selectedIndex];
        if (opt) {
            const addr = [opt.dataset.address, opt.dataset.city].filter(Boolean).join(', ');
            if (addr) {
                delegSections[0].nazwa = opt.dataset.name || 'Siedziba zamawiającego';
                delegSections[0].adres = addr;
            }
        }
    }
}

delegRender();

// Auto-fetch km dla pierwszej lokalizacji
if (delegSections.length > 0 && delegSections[0].adres) {
    delegFetchKm(0);
}
</script>
@endpush
