@extends('layouts.app')

@section('page-title', $offer->fullNumber())

@push('styles')
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
    width:100%; border:1px solid transparent; border-radius:5px;
    padding:5px 8px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A;
    background:transparent; outline:none; transition:border-color .12s, background .12s;
    box-sizing:border-box;
}
.cell-input:hover { border-color:#D0CCC0; background:#FAFAF6; }
.cell-input:focus { border-color:#1A4D3A; background:#fff; }
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
.unit-col { /* visible by default */ }
.hide-units .unit-col { display:none; }

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
    </div>
    <div class="etb-right">
        <label class="toggle-wrap" title="PokaĹĽ ceny jednostkowe klientowi (widoczne w PDF)">
            <input type="checkbox" id="show-unit-toggle"
                   {{ $offer->show_unit_prices ? 'checked' : '' }}
                   onchange="toggleUnitPrices(this)">
            <span class="toggle-track"></span>
            <span class="toggle-label">Ceny jedn. w PDF</span>
        </label>
        <a href="{{ route('offers.pdf', $offer) }}" target="_blank" class="btn-secondary">
            <i class="ti ti-file-type-pdf"></i> Podgląd PDF
        </a>
        <button type="button" class="btn-secondary" onclick="document.getElementById('modal-save-tpl').style.display='flex'">
            <i class="ti ti-bookmark"></i> Zapisz jako szablon
        </button>
        <button type="submit" form="offer-form" class="btn-primary">
            <i class="ti ti-device-floppy"></i> Zapisz
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
            <div style="display:flex;align-items:center;gap:8px;">
                <label style="font-size:11px;opacity:.8;white-space:nowrap;">WaĹĽna do:</label>
                <input type="date" name="valid_until" value="{{ $validUntilDefault }}"
                       style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);border-radius:6px;padding:4px 10px;color:#fff;font-size:13px;font-family:'Lato',sans-serif;outline:none;cursor:pointer;">
            </div>
            <span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
        </div>
    </div>

    <div class="doc-parties">
        <div class="doc-party">
            <div class="doc-party-label">Wystawca</div>
            <div class="doc-party-name">ENESA Sp. z o.o.</div>
            <div class="doc-party-line">
                ul. Konarskiego 18C<br>
                44-100 Gliwice<br>
                NIP: â€” do uzupeĹ‚nienia â€”<br>
                tel.: â€”<br>
                system@enesa.pl
            </div>
        </div>
        <div class="doc-party">
            <div class="doc-party-label">Odbiorca</div>
            <div class="doc-party-name">{{ $offer->company?->name ?? 'â€”' }}</div>
            <div class="doc-party-line">
                {{ $offer->company?->address ?? '' }}
                @if($offer->company?->address && $offer->company?->city), @endif
                {{ $offer->company?->city ?? '' }}<br>
                @if($offer->company?->nip) NIP: {{ $offer->company->nip }}<br> @endif
                @if($offer->company?->email) {{ $offer->company->email }} @endif
            </div>
        </div>
    </div>

    <div class="doc-title-wrap">
        <input type="text" name="offer_title"
               class="doc-title-input"
               value="{{ old('offer_title', $offer->offer_title) }}"
               placeholder="Wpisz tytuĹ‚ oferty â€” bÄ™dzie widoczny na dokumencie">
    </div>
</div>

{{-- â”€â”€ SEKCJA B1: PRZEDMIOT OFERTY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-target"></i>
        <span class="ed-card-title">Przedmiot oferty</span>
    </div>
    <div class="rte-toolbar">
        <button type="button" class="rte-btn" onclick="fmt('bold')"><b>B</b></button>
        <button type="button" class="rte-btn" onclick="fmt('italic')"><i>I</i></button>
        <button type="button" class="rte-btn" onclick="fmt('underline')"><u>U</u></button>
        <button type="button" class="rte-btn" onclick="fmt('insertUnorderedList')"><i class="ti ti-list"></i></button>
        <button type="button" class="rte-btn" onclick="fmt('insertOrderedList')"><i class="ti ti-list-numbers"></i></button>
    </div>
    <div class="rich-editor" id="editor-subject" contenteditable="true"
         data-placeholder="Opisz przedmiot oferty...">{!! $offer->content_subject ?? '' !!}</div>
</div>

{{-- â”€â”€ SEKCJA B2: ZAKRES PRAC â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-list-check"></i>
        <span class="ed-card-title">Zakres prac</span>
    </div>
    <div class="rte-toolbar">
        <button type="button" class="rte-btn" onclick="fmt('bold')"><b>B</b></button>
        <button type="button" class="rte-btn" onclick="fmt('italic')"><i>I</i></button>
        <button type="button" class="rte-btn" onclick="fmt('underline')"><u>U</u></button>
        <button type="button" class="rte-btn" onclick="fmt('insertUnorderedList')"><i class="ti ti-list"></i></button>
        <button type="button" class="rte-btn" onclick="fmt('insertOrderedList')"><i class="ti ti-list-numbers"></i></button>
    </div>
    <div class="rich-editor" id="editor-scope" contenteditable="true"
         data-placeholder="Opisz zakres prac...">{!! $offer->content_scope ?? '' !!}</div>
</div>

{{-- â”€â”€ SEKCJA C: WYCENA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}

{{-- C1: Sekcja gĹ‚Ăłwna --}}
<div class="ed-card" id="section-main">
    <div class="ed-card-header">
        <i class="ti ti-calculator"></i>
        <input type="text" class="section-name-input" id="section-main-name" value="Wycena ogĂłlna">
    </div>
    <div style="overflow-x:auto;">
        <table class="price-table" id="table-main">
            <thead>
                <tr>
                    <th style="width:28px;"></th>
                    <th>Opis pozycji</th>
                    <th class="unit-col" style="width:70px;">Jedn.</th>
                    <th class="unit-col" style="width:80px;">IloĹ›Ä‡</th>
                    <th class="unit-col" style="width:130px;">Cena jedn. netto</th>
                    <th style="width:130px;">WartoĹ›Ä‡ netto</th>
                    <th style="width:130px;">Z narzutem</th>
                    <th style="width:32px;"></th>
                </tr>
            </thead>
            <tbody id="tbody-main"></tbody>
        </table>
    </div>
    <div style="padding:10px 16px;">
        <button type="button" class="btn-add-row" onclick="addRow('tbody-main')">
            <i class="ti ti-plus"></i> Dodaj pozycjÄ™
        </button>
    </div>
</div>

{{-- C2: Dynamiczne sekcje --}}
<div id="dynamic-sections"></div>
<div style="margin-bottom:16px;">
    <button type="button" class="btn-add-section" onclick="addSection()">
        <i class="ti ti-section"></i> Dodaj sekcjÄ™ wyceny
    </button>
</div>

{{-- C3: Delegacje --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-car"></i>
        <span class="ed-card-title">Delegacja</span>
    </div>
    <div class="ed-card-body">
        <div class="deleg-grid">
            <div>
                <label class="field-label">OdlegĹ‚oĹ›Ä‡ do klienta</label>
                <div class="input-group">
                    <input type="number" id="d_km" class="field-input" min="0"
                           value="{{ old('km_do_klienta', $d?->km_do_klienta ?? 0) }}" oninput="calcDeleg()">
                    <span class="input-suffix">km</span>
                </div>
            </div>
            <div>
                <label class="field-label">Stawka za km</label>
                <div class="input-group">
                    <input type="number" id="d_stawka_km" class="field-input" min="0" step="0.01"
                           value="{{ old('stawka_km', $d?->stawka_km ?? 1.10) }}" oninput="calcDeleg()">
                    <span class="input-suffix">zĹ‚/km</span>
                </div>
            </div>
            <div>
                <label class="field-label">Czas dojazdu</label>
                <div class="input-group">
                    <input type="number" id="d_czas" class="field-input" min="0"
                           value="{{ old('czas_dojazdu_min', $d?->czas_dojazdu_min ?? 0) }}">
                    <span class="input-suffix">min</span>
                </div>
            </div>
            <div>
                <label class="field-label">Liczba wyjazdĂłw</label>
                <input type="number" id="d_wyjazdy" class="field-input" min="1"
                       value="{{ old('liczba_wyjazdow', $d?->liczba_wyjazdow ?? 1) }}" oninput="calcDeleg()">
            </div>
        </div>

        <div style="margin-top:14px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-family:'Manrope',sans-serif;font-size:13px;font-weight:600;color:#555;">
                <input type="checkbox" id="d_kilkudniowy" value="1"
                       {{ ($d?->czy_kilkudniowy || old('czy_kilkudniowy')) ? 'checked' : '' }}
                       onchange="toggleOvernight(this);calcDeleg();"
                       style="width:16px;height:16px;accent-color:#1A4D3A;">
                Wyjazd wielodniowy?
            </label>
        </div>

        <div id="overnightSection" style="{{ ($d?->czy_kilkudniowy || old('czy_kilkudniowy')) ? '' : 'display:none;' }}margin-top:12px;">
            <div class="deleg-grid">
                <div>
                    <label class="field-label">Liczba nocy</label>
                    <input type="number" id="d_noc" class="field-input" min="0"
                           value="{{ old('liczba_noc', $d?->liczba_noc ?? 0) }}" oninput="calcDeleg()">
                </div>
                <div>
                    <label class="field-label">Liczba osĂłb</label>
                    <input type="number" id="d_osoby" class="field-input" min="1"
                           value="{{ old('liczba_osob', $d?->liczba_osob ?? 1) }}" oninput="calcDeleg()">
                </div>
                <div>
                    <label class="field-label">Stawka za dobÄ™ hotelowÄ…</label>
                    <div class="input-group">
                        <input type="number" id="d_stawka_noc" class="field-input" min="0" step="0.01"
                               value="{{ old('stawka_noc', $d?->stawka_noc ?? 300) }}" oninput="calcDeleg()">
                        <span class="input-suffix">zĹ‚</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="background:#F0F7F3;border:1px solid #94C4B0;border-radius:8px;padding:12px 16px;margin-top:16px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-family:'Manrope',sans-serif;font-size:11px;font-weight:700;color:#1A4D3A;text-transform:uppercase;letter-spacing:.05em;">Koszt delegacji netto</span>
            <span id="deleg-result" style="font-family:'Lato',sans-serif;font-size:20px;font-weight:900;color:#1A4D3A;">0,00 zĹ‚</span>
        </div>
    </div>
</div>

{{-- â”€â”€ SEKCJA B3: TERMIN REALIZACJI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-calendar-time"></i>
        <span class="ed-card-title">Termin realizacji</span>
    </div>
    <div class="rte-toolbar">
        <button type="button" class="rte-btn" onclick="fmt('bold')"><b>B</b></button>
        <button type="button" class="rte-btn" onclick="fmt('italic')"><i>I</i></button>
        <button type="button" class="rte-btn" onclick="fmt('underline')"><u>U</u></button>
        <button type="button" class="rte-btn" onclick="fmt('insertUnorderedList')"><i class="ti ti-list"></i></button>
        <button type="button" class="rte-btn" onclick="fmt('insertOrderedList')"><i class="ti ti-list-numbers"></i></button>
    </div>
    <div class="rich-editor" id="editor-deadline" contenteditable="true"
         data-placeholder="Opisz termin realizacji...">{!! $offer->content_deadline ?? '' !!}</div>
</div>

{{-- â”€â”€ SEKCJA B4: WARUNKI PĹATNOĹšCI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-credit-card"></i>
        <span class="ed-card-title">Warunki pĹ‚atnoĹ›ci</span>
    </div>
    <div class="rte-toolbar">
        <button type="button" class="rte-btn" onclick="fmt('bold')"><b>B</b></button>
        <button type="button" class="rte-btn" onclick="fmt('italic')"><i>I</i></button>
        <button type="button" class="rte-btn" onclick="fmt('underline')"><u>U</u></button>
        <button type="button" class="rte-btn" onclick="fmt('insertUnorderedList')"><i class="ti ti-list"></i></button>
        <button type="button" class="rte-btn" onclick="fmt('insertOrderedList')"><i class="ti ti-list-numbers"></i></button>
    </div>
    <div class="rich-editor" id="editor-payment" contenteditable="true"
         data-placeholder="Opisz warunki pĹ‚atnoĹ›ci...">{!! $offer->content_payment ?? '' !!}</div>
</div>

{{-- â”€â”€ C4: NARZUT + PODSUMOWANIE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
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
                <input type="number" id="markup-pct" class="field-input" min="0" max="999" step="0.1"
                       value="0" oninput="syncMarkup('pct')" style="border-radius:7px 0 0 7px;">
                <span class="input-suffix">%</span>
            </div>
            <div class="input-group" style="width:140px;">
                <input type="number" id="markup-zl" class="field-input" min="0" step="0.01"
                       value="0" oninput="syncMarkup('zl')" style="border-radius:7px 0 0 7px;">
                <span class="input-suffix">zĹ‚</span>
            </div>
        </div>
        {{-- Summary rows --}}
        <div class="summary-row sub">
            <span class="summary-label">Suma usĹ‚ug netto</span>
            <span class="summary-value" id="sum-services">0,00 zĹ‚</span>
        </div>
        <div class="summary-row sub">
            <span class="summary-label">Delegacje netto</span>
            <span class="summary-value" id="sum-deleg">0,00 zĹ‚</span>
        </div>
        <div class="summary-row markup">
            <span class="summary-label" style="color:#92400E;">Narzut</span>
            <span class="summary-value" id="sum-markup" style="color:#92400E;">0,00 zĹ‚</span>
        </div>
        <div class="summary-row total">
            <span class="summary-label" style="font-size:15px;">ĹÄ„CZNIE NETTO</span>
            <span class="summary-value" id="sum-total">0,00 zĹ‚</span>
        </div>
    </div>
</div>

{{-- â”€â”€ NOTATKI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-notes"></i>
        <span class="ed-card-title">Notatki wewnÄ™trzne</span>
    </div>
    <div class="ed-card-body">
        <textarea name="notes" class="field-input" rows="3"
                  placeholder="Uwagi wewnÄ™trzne (niewidoczne w PDF)...">{{ old('notes', $offer->notes) }}</textarea>
    </div>
</div>

{{-- Inne pola z oryginalnego formularza potrzebne do walidacji --}}
<input type="hidden" name="offer_number"     value="{{ $offer->offer_number }}">
<input type="hidden" name="offer_slug"       value="{{ $offer->offer_slug }}">
<input type="hidden" name="company_id"       value="{{ $offer->company_id }}">
<input type="hidden" name="assigned_user_id" value="{{ $offer->assigned_user_id }}">
<input type="hidden" name="status"           value="{{ $offer->status }}">
<input type="hidden" name="liczba_wyjazdow"  value="1" id="h-wyjazdy">
<input type="hidden" name="liczba_noc"       value="0" id="h-noc">
<input type="hidden" name="liczba_osob"      value="1" id="h-osoby">
<input type="hidden" name="stawka_noc"       value="300" id="h-stawka-noc">
<input type="hidden" name="kwota_netto"      id="h-kwota-netto" value="{{ $offer->kwota_netto }}">

{{-- Rich editor hidden inputs --}}
<input type="hidden" id="hidden-content-subject"  name="content_subject"  value="{{ $offer->content_subject }}">
<input type="hidden" id="hidden-content-scope"    name="content_scope"    value="{{ $offer->content_scope }}">
<input type="hidden" id="hidden-content-deadline" name="content_deadline" value="{{ $offer->content_deadline }}">
<input type="hidden" id="hidden-content-payment"  name="content_payment"  value="{{ $offer->content_payment }}">
<input type="hidden" id="hidden-price-sections"   name="price_sections"   value="">
<input type="hidden" id="hidden-show-unit"        name="show_unit_prices" value="{{ $offer->show_unit_prices ? '1' : '0' }}">

{{-- Delegation hidden inputs (sync from JS) --}}
<input type="hidden" name="km_do_klienta"    id="h-km">
<input type="hidden" name="stawka_km"        id="h-stawka-km">
<input type="hidden" name="czas_dojazdu_min" id="h-czas">
<input type="hidden" name="czy_kilkudniowy"  id="h-kilkudniowy" value="0">

</form>

{{-- ═══ MODAL: ZAPISZ JAKO SZABLON ═══ --}}
<div id="modal-save-tpl"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div style="font-family:'Manrope',sans-serif;font-size:16px;font-weight:700;color:#1A1A1A;display:flex;align-items:center;gap:8px;">
                <i class="ti ti-bookmark" style="color:#1A4D3A;"></i> Zapisz jako szablon
            </div>
            <button type="button" onclick="document.getElementById('modal-save-tpl').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;line-height:1;">×</button>
        </div>
        <p style="font-size:13px;color:#666;font-family:'Lato',sans-serif;margin-bottom:16px;">
            Zapisze treść, zakres, warunki i sekcje wyceny jako szablon wielokrotnego użytku.
        </p>
        <form method="POST" action="{{ route('offers.save-as-template', $offer) }}">
            @csrf
            <div style="margin-bottom:16px;">
                <label class="field-label" style="font-size:12px;font-weight:700;color:#3a3a3a;display:block;margin-bottom:5px;font-family:'Manrope',sans-serif;">
                    Nazwa szablonu <span style="color:#DC2626;">*</span>
                </label>
                <input type="text" name="name"
                       class="field-input"
                       placeholder="np. Audyt energetyczny — zakres standardowy"
                       required autofocus>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button"
                        onclick="document.getElementById('modal-save-tpl').style.display='none'"
                        class="btn-secondary">Anuluj</button>
                <button type="submit" class="btn-primary">
                    <i class="ti ti-bookmark"></i> Zapisz szablon
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DATA INIT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
let priceSections = @json($offer->price_sections ?? null);
if (!priceSections || !Array.isArray(priceSections) || priceSections.length === 0) {
    priceSections = [{ id: 'main', name: 'Wycena ogĂłlna', rows: [] }];
}
// Ensure main section exists
if (!priceSections[0]) priceSections[0] = { id: 'main', name: 'Wycena ogĂłlna', rows: [] };

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
    tr.dataset.rid = rid;
    tr.innerHTML = `
        <td style="text-align:center;color:#ccc;cursor:grab;"><i class="ti ti-grip-vertical"></i></td>
        <td><input class="cell-input" type="text" placeholder="Opis pozycji..." value="${escHtml(d.opis)}"></td>
        <td class="unit-col"><input class="cell-input" type="text" value="${escHtml(d.jedn)}" style="width:60px;"></td>
        <td class="unit-col"><input class="cell-input num-input ilosc-input" type="number" value="${d.ilosc}" min="0" step="0.01" style="width:70px;" oninput="recalcRow(this.closest('tr'))"></td>
        <td class="unit-col"><input class="cell-input num-input cena-input" type="number" value="${d.cena_jedn}" min="0" step="0.01" style="width:110px;" oninput="recalcRow(this.closest('tr'))"></td>
        <td><span class="cell-readonly wartosc-display">${makePl(d.ilosc * d.cena_jedn)}</span> <span style="font-size:11px;color:#999;">zĹ‚</span></td>
        <td><input class="cell-input num-input narzut-input" type="number" value="${d.z_narzutem}" min="0" step="0.01" style="width:110px;" oninput="recalcAll()"> <span style="font-size:11px;color:#999;">zĹ‚</span></td>
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

function recalcRow(tr) {
    const ilosc    = parseFloat(tr.querySelector('.ilosc-input')?.value) || 0;
    const cena     = parseFloat(tr.querySelector('.cena-input')?.value)  || 0;
    const wartosc  = ilosc * cena;
    const display  = tr.querySelector('.wartosc-display');
    if (display) display.textContent = makePl(wartosc);

    // auto-set z_narzutem based on global markup %
    const narzutInput = tr.querySelector('.narzut-input');
    if (narzutInput && globalMarkup.pct > 0) {
        narzutInput.value = makePl(wartosc * (1 + globalMarkup.pct / 100));
    } else if (narzutInput && globalMarkup.pct === 0) {
        narzutInput.value = makePl(wartosc);
    }
    recalcAll();
}

function recalcAll() {
    let sumServices = 0;

    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        const val = parseFloat(tr.querySelector('.narzut-input')?.value) || 0;
        sumServices += val;
    });

    const delegCost = parsePl(document.getElementById('deleg-result').textContent);

    const markupZl = parseFloat(document.getElementById('markup-zl').value) || 0;
    const total    = sumServices + delegCost + markupZl;

    document.getElementById('sum-services').textContent = makePl(sumServices) + ' zĹ‚';
    document.getElementById('sum-deleg').textContent    = makePl(delegCost) + ' zĹ‚';
    document.getElementById('sum-markup').textContent   = makePl(markupZl) + ' zĹ‚';
    document.getElementById('sum-total').textContent    = makePl(total) + ' zĹ‚';

    document.getElementById('h-kwota-netto').value = total.toFixed(2);
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
    const km       = parseFloat(document.getElementById('d_km').value)        || 0;
    const stawkaKm = parseFloat(document.getElementById('d_stawka_km').value) || 1.10;
    const wyjazdy  = parseFloat(document.getElementById('d_wyjazdy').value)   || 1;
    const over     = document.getElementById('d_kilkudniowy').checked;
    const noc      = parseFloat(document.getElementById('d_noc')?.value)      || 0;
    const osoby    = parseFloat(document.getElementById('d_osoby')?.value)    || 1;
    const stawkaNoc= parseFloat(document.getElementById('d_stawka_noc')?.value) || 300;

    const deleg = (km * 2 * wyjazdy * stawkaKm) + (over ? noc * osoby * stawkaNoc : 0);
    document.getElementById('deleg-result').textContent = makePl(deleg) + ' zĹ‚';
    recalcAll();
}

function toggleOvernight(cb) {
    document.getElementById('overnightSection').style.display = cb.checked ? 'block' : 'none';
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// UNIT PRICES TOGGLE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function toggleUnitPrices(cb) {
    document.querySelectorAll('.price-table').forEach(t => {
        t.classList.toggle('hide-units', !cb.checked);
    });
    document.getElementById('hidden-show-unit').value = cb.checked ? '1' : '0';
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
                <i class="ti ti-trash"></i> UsuĹ„ sekcjÄ™
            </button>
        </div>
        <div style="overflow-x:auto;">
            <table class="price-table" id="table-${sid}">
                <thead>
                    <tr>
                        <th style="width:28px;"></th>
                        <th>Opis pozycji</th>
                        <th class="unit-col" style="width:70px;">Jedn.</th>
                        <th class="unit-col" style="width:80px;">IloĹ›Ä‡</th>
                        <th class="unit-col" style="width:130px;">Cena jedn. netto</th>
                        <th style="width:130px;">WartoĹ›Ä‡ netto</th>
                        <th style="width:130px;">Z narzutem</th>
                        <th style="width:32px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-${sid}"></tbody>
            </table>
        </div>
        <div style="padding:10px 16px;">
            <button type="button" class="btn-add-row" onclick="addRow('tbody-${sid}')">
                <i class="ti ti-plus"></i> Dodaj pozycjÄ™
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
    if (!confirm('UsunÄ…Ä‡ tÄ™ sekcjÄ™ wyceny?')) return;
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
    mainSection.name = document.getElementById('section-main-name')?.value || 'Wycena ogĂłlna';
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

function collectRow(tr) {
    const inputs = tr.querySelectorAll('.cell-input');
    return {
        opis:      inputs[0]?.value || '',
        jedn:      inputs[1]?.value || 'szt',
        ilosc:     parseFloat(tr.querySelector('.ilosc-input')?.value)  || 0,
        cena_jedn: parseFloat(tr.querySelector('.cena-input')?.value)   || 0,
        z_narzutem:parseFloat(tr.querySelector('.narzut-input')?.value) || 0,
    };
}

function syncDelegHiddens() {
    document.getElementById('h-km').value          = document.getElementById('d_km').value || 0;
    document.getElementById('h-stawka-km').value   = document.getElementById('d_stawka_km').value || 1.10;
    document.getElementById('h-czas').value        = document.getElementById('d_czas').value || 0;
    document.getElementById('h-wyjazdy').value     = document.getElementById('d_wyjazdy').value || 1;
    document.getElementById('h-kilkudniowy').value = document.getElementById('d_kilkudniowy').checked ? '1' : '0';
    document.getElementById('h-noc').value         = document.getElementById('d_noc')?.value || 0;
    document.getElementById('h-osoby').value       = document.getElementById('d_osoby')?.value || 1;
    document.getElementById('h-stawka-noc').value  = document.getElementById('d_stawka_noc')?.value || 300;
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// FORM SUBMIT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
document.getElementById('offer-form').addEventListener('submit', function () {
    // Collect rich editor content
    document.getElementById('hidden-content-subject').value  = document.getElementById('editor-subject').innerHTML;
    document.getElementById('hidden-content-scope').value    = document.getElementById('editor-scope').innerHTML;
    document.getElementById('hidden-content-deadline').value = document.getElementById('editor-deadline').innerHTML;
    document.getElementById('hidden-content-payment').value  = document.getElementById('editor-payment').innerHTML;

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

    // Apply unit toggle initial state
    toggleUnitPrices(document.getElementById('show-unit-toggle'));

    // Init delegation calc
    calcDeleg();
});
</script>
@endpush

