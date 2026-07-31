@extends('layouts.app')

@section('page-title', 'Nowa oferta')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<style>
/* ── Editor topbar ─────────────────────────────────── */
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
    margin: -20px -20px 20px -20px;
}
.etb-left  { display: flex; align-items: center; gap: 10px; }
.etb-right { display: flex; align-items: center; gap: 10px; }
.toggle-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.toggle-wrap input { display: none; }
.toggle-track {
    width: 40px; height: 22px; background: #D0CCC0;
    border-radius: 11px; position: relative; transition: background .2s;
}
.toggle-track::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 16px; height: 16px; background: #fff; border-radius: 50%;
    transition: left .2s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
}
.toggle-wrap input:checked + .toggle-track { background: var(--green); }
.toggle-wrap input:checked + .toggle-track::after { left: 21px; }
.toggle-label { font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 600; color: #555; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--green); color: #F5F0E8; border: none; border-radius: 8px;
    padding: 8px 16px; font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 700;
    text-decoration: none; cursor: pointer; transition: background .15s; white-space: nowrap;
}
.btn-primary:hover { background: #143d2d; color: #F5F0E8; }
.btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    background: #fff; color: #333; border: 1px solid #D0CCC0; border-radius: 8px;
    padding: 7px 14px; font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 600;
    text-decoration: none; cursor: pointer; transition: background .15s;
}
.btn-secondary:hover { background: #F4F1EA; }
.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-blue { background:#DBEAFE; color:#1D4ED8; }
.ed-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:16px; }
.ed-card-header { padding:13px 20px; border-bottom:1px solid #F0EDE6; background:#FAFAF6; display:flex; align-items:center; gap:10px; }
.ed-card.type-text { border-left: 4px solid var(--green); }
.ed-card.type-text .ed-card-header { background: #F0F7F3; }
.ed-card.type-price { border-left: 4px solid #D97706; }
.ed-card.type-price .ed-card-header { background: #FFF8E8; }
.ed-card.type-deleg { border-left: 4px solid #2563EB; }
.ed-card.type-deleg .ed-card-header { background: #EFF6FF; }
.ed-card-header > i { font-size:17px; color:var(--green); }
.ed-card-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.ed-card-body { padding:20px; }
.doc-header-bar { background:var(--green); color:#fff; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.doc-header-bar .offer-num { font-family:'Lato',sans-serif; font-size:15px; font-weight:900; letter-spacing:.04em; }
.doc-header-bar .doc-date  { font-size:12px; opacity:.8; }
.doc-parties { display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid #F0EDE6; }
.doc-party { padding:18px 22px; }
.doc-party:first-child { border-right:1px solid #F0EDE6; }
.doc-party-label { font-family:'Manrope',sans-serif; font-size:10px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:.07em; margin-bottom:8px; }
.doc-party-name  { font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; color:#1A1A1A; margin-bottom:4px; }
.doc-party-line  { font-family:'Lato',sans-serif; font-size:12px; color:#555; line-height:1.7; }
.doc-title-wrap  { padding:16px 22px; }
.doc-title-input { width:100%; border:none; outline:none; font-family:'Manrope',sans-serif; font-size:18px; font-weight:700; color:var(--green); background:transparent; border-bottom:2px dashed #94C4B0; padding:4px 0; transition:border-color .15s; }
.doc-title-input:focus { border-color:var(--green); }
.doc-title-input::placeholder { color:#bbb; font-weight:400; }
.rte-toolbar { display:flex; gap:4px; padding:8px 16px; border-bottom:1px solid #F0EDE6; background:#FAFAF6; flex-wrap:wrap; }
.rte-btn { min-width:30px; height:28px; padding:0 8px; background:#fff; border:1px solid #D0CCC0; border-radius:5px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-family:'Lato',sans-serif; color:#333; transition:background .12s; }
.rte-btn:hover { background:#F0EDE6; }
.rich-editor { min-height:90px; padding:14px 20px; font-family:'Lato',sans-serif; font-size:14px; color:#1A1A1A; line-height:1.8; outline:none; }
.rich-editor:empty::before { content:attr(data-placeholder); color:#bbb; pointer-events:none; }
.rich-editor ul, .rich-editor ol { padding-left:20px; }
.section-name-input { flex:1; border:none; outline:none; background:transparent; font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; border-bottom:1px dashed #bbb; padding:2px 4px; }
.price-table { width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed; }
.price-table td, .price-table th { overflow:hidden; }
.price-table th { font-family:'Manrope',sans-serif; font-size:10px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.05em; padding:8px 10px; border-bottom:2px solid #E5E1D8; background:#FAFAF6; white-space:nowrap; text-align:left; }
.price-table td { padding:6px 6px; border-bottom:1px solid #F0EDE6; vertical-align:middle; }
.price-table tr:last-child td { border-bottom:none; }
.col-drag  { width:32px; }
.col-opis  { width:auto; min-width:180px; }
.col-jedn  { width:100px; }
.col-ilosc { width:80px; }
.col-cena  { width:110px; }
.col-netto { width:110px; }
.col-narzut{ width:120px; }
.col-del   { width:40px; }
.cell-input { width:100%; border:1px solid #D8D4C8; border-radius:5px; padding:6px 9px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A; background:#fff; outline:none; transition:border-color .12s, box-shadow .12s; box-sizing:border-box; }
.cell-input::placeholder { color: #B0AA9E; }
.cell-input:hover { border-color:#94C4B0; }
.cell-input:focus { border-color:var(--green); box-shadow: 0 0 0 2px rgba(26,77,58,0.08); }
.cell-readonly { font-family:'Lato',sans-serif; font-size:13px; color:#333; font-weight:700; padding:5px 8px; }
.btn-add-row { background:none; border:1px dashed #94C4B0; color:var(--green); border-radius:6px; padding:6px 14px; font-size:12px; font-family:'Manrope',sans-serif; font-weight:700; cursor:pointer; transition:background .12s; }
.btn-add-row:hover { background:#F0F7F3; }
.btn-del-row { background:none; border:none; color:#DC2626; cursor:pointer; font-size:16px; padding:4px; border-radius:4px; display:flex; align-items:center; justify-content:center; }
.btn-del-row:hover { background:#FEE2E2; }
.btn-del-section { margin-left:auto; background:none; border:1px solid #FCA5A5; color:#B91C1C; border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s; }
.btn-del-section:hover { background:#FEE2E2; }
.btn-add-section { display:inline-flex; align-items:center; gap:6px; border:1px dashed #94C4B0; color:var(--green); background:none; border-radius:8px; padding:8px 16px; font-size:12px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s; }
.btn-add-section:hover { background:#F0F7F3; }

.deleg-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
.field-label { display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; font-family:'Manrope',sans-serif; }
.field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:8px 10px; font-size:13px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; box-sizing:border-box; }
.field-input:focus { border-color:var(--green); background:#fff; }
.input-group { display:flex; }
.input-group .field-input { border-radius:7px 0 0 7px; border-right:none; }
.input-suffix { background:#F0EDE6; border:1px solid #D0CCC0; border-radius:0 7px 7px 0; padding:8px 10px; font-size:12px; color:#666; white-space:nowrap; }
.summary-row { display:flex; justify-content:space-between; padding:9px 16px; font-size:13px; }
.summary-row.sub { background:#fff; border-bottom:1px solid #F0EDE6; }
.summary-row.markup { background:#FFFBEB; border-bottom:1px solid #FDE68A; }
.summary-row.total { background:var(--green); color:#fff; border-radius:0 0 10px 10px; }
.summary-label { font-family:'Manrope',sans-serif; font-weight:600; }
.summary-value { font-family:'Lato',sans-serif; font-weight:900; font-size:15px; }
.summary-row.total .summary-value { font-size:20px; }
.markup-bar { background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.markup-bar .field-label { color:#92400E; }
</style>
@endpush

@section('content')
@php
    $validUntilDefault = now()->addDays(30)->format('Y-m-d');
@endphp

{{-- ═══ MODAL WYBORU SZABLONU ═══ --}}
@if($offerRequest)
<div id="modal-template-pick" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="font-family:'Manrope',sans-serif;font-size:18px;font-weight:700;color:var(--green);margin-bottom:6px;display:flex;align-items:center;gap:10px;">
            <i class="ti ti-file-plus"></i> Nowa oferta
        </div>
        <div style="font-size:13px;color:#888;margin-bottom:24px;font-family:'Manrope',sans-serif;">
            Zapytanie: <strong style="color:#1A1A1A;">{{ $offerRequest->offerFormTemplate?->name ?? 'Ogólne' }}</strong>
            od <strong style="color:#1A1A1A;">{{ $offerRequest->company?->name }}</strong>
        </div>

        <div style="font-size:12px;font-weight:700;color:#555;font-family:'Manrope',sans-serif;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">
            Wybierz sposób tworzenia oferty:
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px;">

            {{-- Opcja: pusta oferta --}}
            <button onclick="pickTemplate(null)" style="display:flex;align-items:center;gap:16px;background:#fff;border:2px solid #E5E1D8;border-radius:10px;padding:16px 20px;cursor:pointer;text-align:left;transition:border-color .15s;" onmouseover="this.style.borderColor='var(--green)'" onmouseout="this.style.borderColor='#E5E1D8'">
                <div style="width:44px;height:44px;background:#F0F7F3;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="ti ti-file" style="font-size:22px;color:var(--green);"></i>
                </div>
                <div>
                    <div style="font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:#1A1A1A;margin-bottom:3px;">Pusta oferta</div>
                    <div style="font-size:12px;color:#888;">Zacznij od zera — wypełnij wszystko ręcznie</div>
                </div>
            </button>

            {{-- Opcja: wybierz szablon --}}
            @if($offerTemplates->isNotEmpty())
                @foreach($offerTemplates as $tpl)
                <button onclick="pickTemplate({{ $tpl->id }})" style="display:flex;align-items:center;gap:16px;background:#fff;border:2px solid #E5E1D8;border-radius:10px;padding:16px 20px;cursor:pointer;text-align:left;transition:border-color .15s;" onmouseover="this.style.borderColor='var(--green)'" onmouseout="this.style.borderColor='#E5E1D8'">
                    <div style="width:44px;height:44px;background:#FFFBEB;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="ti ti-template" style="font-size:22px;color:#92400E;"></i>
                    </div>
                    <div>
                        <div style="font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:#1A1A1A;margin-bottom:3px;">{{ $tpl->offer_title ?? 'Szablon #'.$tpl->id }}</div>
                        <div style="font-size:12px;color:#888;">Użyj tego szablonu jako bazy oferty</div>
                    </div>
                </button>
                @endforeach
            @else
                <div style="background:#FAFAF6;border:1px dashed #D0CCC0;border-radius:10px;padding:16px 20px;font-size:13px;color:#888;font-family:'Manrope',sans-serif;text-align:center;">
                    <i class="ti ti-template" style="font-size:20px;display:block;margin-bottom:6px;color:#D0CCC0;"></i>
                    Brak szablonów — idź do Strefa Ofert → Szablony ofert aby je dodać
                </div>
            @endif
        </div>

        <button onclick="closeTemplatePick()" style="width:100%;background:none;border:1px solid #D0CCC0;border-radius:8px;padding:10px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:600;color:#888;cursor:pointer;">
            Anuluj — wróć do karty firmy
        </button>
    </div>
</div>
@endif

{{-- ═══ EDITOR TOPBAR ═══ --}}
@if($offerRequest)
<div id="editor-topbar" style="display:none;">
@else
<div id="editor-topbar">
@endif
    <div class="etb-left">
        <a href="{{ route('offers.index') }}" class="btn-secondary" style="padding:6px 10px;">
            <i class="ti ti-arrow-left"></i>
        </a>
        <span style="font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;color:var(--green);">
            Nowa oferta
        </span>
        <span class="badge badge-blue">W toku</span>
    </div>
    <div class="etb-right">
        <label class="toggle-wrap" title="Pokaż ceny jednostkowe klientowi (widoczne w PDF)">
            <input type="checkbox" id="show-unit-toggle" onchange="toggleUnitPrices(this)">
            <span class="toggle-track"></span>
            <span class="toggle-label">Ceny jedn. w PDF</span>
        </label>
        <button type="submit" form="offer-form" class="btn-primary">
            <i class="ti ti-plus"></i> Utwórz ofertę
        </button>
    </div>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div style="background:#FEF2F2;border:1px solid #FCA5A5;color:#B91C1C;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;">
        <strong>Popraw błędy formularza:</strong>
        <ul style="margin:6px 0 0 16px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ═══ FORM ═══ --}}
<form id="offer-form" method="POST" action="{{ route('offers.store') }}" @if($offerRequest) style="display:none;" @endif>
@csrf

{{-- ── SEKCJA A: NAGŁÓWEK DOKUMENTU ─────────── --}}
<div class="ed-card">
    <div class="doc-header-bar">
        <div>
            <div class="offer-num">{{ $suggestedNumber }}</div>
            <div class="doc-date">Nowa oferta · {{ now()->format('d.m.Y') }}</div>
        </div>
    </div>

    {{-- Firma klienta i osoba prowadząca --}}
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
            <div class="doc-party-label">Odbiorca — wybierz firmę <span style="color:#DC2626;">*</span></div>
            <select name="company_id" id="company_id"
                    class="field-input @error('company_id') error @enderror"
                    style="margin-bottom:8px;"
                    {{ $offerRequest ? 'disabled' : '' }} required>
                <option value="">— wybierz firmę klienta —</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}"
                        data-name="{{ $company->name }}"
                        data-address="{{ $company->address ?? '' }}"
                        data-city="{{ $company->city ?? '' }}"
                        {{ old('company_id', $selectedCompanyId) == $company->id ? 'selected' : '' }}>
                        {{ $company->name }}
                        @if($company->city) — {{ $company->city }} @endif
                    </option>
                @endforeach
            </select>
            @if($offerRequest)
                <input type="hidden" name="company_id" value="{{ $offerRequest->company_id }}">
                <div class="doc-party-name">{{ $offerRequest->company?->name }}</div>
                <div class="doc-party-line">
                    {{ $offerRequest->company?->address ?? '' }}
                    @if($offerRequest->company?->city), {{ $offerRequest->company->city }} @endif
                </div>
            @endif
            @error('company_id')<div style="font-size:11px;color:#B91C1C;margin-top:4px;">{{ $message }}</div>@enderror
            <div id="distance-info" style="display:none;margin-top:6px;font-size:12px;color:var(--green);font-family:'Lato',sans-serif;"></div>
        </div>
    </div>

    {{-- Osoba prowadząca + status --}}
    <div style="padding:14px 22px;border-bottom:1px solid #F0EDE6;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;align-items:end;">
        <div>
            <label class="field-label">Osoba prowadząca (ENESA)</label>
            <select name="assigned_user_id" class="field-input">
                <option value="">— nieprzypisana —</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('assigned_user_id', $selectedCrmOpportunity?->assigned_to) == $user->id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label">Powiązana szansa CRM</label>
            <select name="crm_opportunity_id" id="crm_opportunity_id" class="field-input">
                <option value="">— bez powiązania —</option>
                @foreach($crmOpportunities as $opportunity)
                    <option value="{{ $opportunity->id }}"
                            data-company-id="{{ $opportunity->company_id }}"
                            {{ old('crm_opportunity_id', $selectedCrmOpportunity?->id) == $opportunity->id ? 'selected' : '' }}>
                        {{ $opportunity->title }} — {{ $opportunity->company?->name ?? 'bez firmy' }}
                    </option>
                @endforeach
            </select>
            <div id="crm-opportunity-help" style="font-size:11px;color:#888;margin-top:4px;">Dostępne są tylko leady wybranej firmy.</div>
            @error('crm_opportunity_id')<div style="font-size:11px;color:#B91C1C;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="field-label">Numer oferty <span style="color:#DC2626;">*</span></label>
            <input type="text" name="offer_number" class="field-input @error('offer_number') error @enderror"
                   value="{{ old('offer_number', $suggestedNumber) }}" required>
            @error('offer_number')<div style="font-size:11px;color:#B91C1C;margin-top:4px;">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="field-label">Status <span style="color:#DC2626;">*</span></label>
            <select name="status" class="field-input" required>
                <option value="w_toku" selected>W toku</option>
                <option value="wygrana">Wygrana</option>
                <option value="przegrana">Przegrana</option>
                <option value="zarchiwizowana">Zarchiwizowana</option>
            </select>
        </div>
        <div>
            <label class="field-label">Data utworzenia</label>
            <input type="date" name="created_at" class="field-input"
                   value="{{ old('created_at', now()->format('Y-m-d')) }}">
        </div>
    </div>

    <div class="doc-title-wrap">
        <input type="text" name="offer_title"
               class="doc-title-input"
               value="{{ old('offer_title', $selectedCrmOpportunity?->title) }}"
               placeholder="Wpisz tytuł oferty — będzie widoczny na dokumencie">
    </div>
</div>

@if($offerRequest)
    <input type="hidden" name="offer_request_id" value="{{ $offerRequest->id }}">
@endif

{{-- ── SEKCJE OPISOWE (dynamiczne) ───────────── --}}
<div id="text-sections-container"></div>
<div style="margin-bottom:16px;">
    <button type="button" class="btn-add-section" onclick="addTextSection()">
        <i class="ti ti-file-text"></i> Dodaj sekcję opisową
    </button>
</div>

{{-- ── SEKCJA C: WYCENA ───────────────────────── --}}
<div class="ed-card type-price" id="section-main">
    <div class="ed-card-header">
        <i class="ti ti-calculator"></i>
        <input type="text" class="section-name-input" id="section-main-name" value="Wycena ogólna">
    </div>
    <div style="overflow-x:auto;">
        <table class="price-table" id="table-main">
            <thead>
                <tr>
                    <th class="col-drag"></th>
                    <th class="col-opis">Opis pozycji</th>
                    <th class="col-jedn">Jednostka</th>
                    <th class="col-ilosc">Ilość</th>
                    <th class="col-cena">Cena jedn.</th>
                    <th class="col-netto">Wartość netto</th>
                    <th class="col-narzut">Z narzutem</th>
                    <th class="col-del"></th>
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

<div id="dynamic-sections"></div>
<div style="margin-bottom:16px;">
    <button type="button" class="btn-add-section" onclick="addSection()">
        <i class="ti ti-section"></i> Dodaj sekcję wyceny
    </button>
</div>

{{-- C3: Delegacje --}}
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

{{-- ── C4: NARZUT + PODSUMOWANIE ──────────────── --}}
<div class="ed-card" style="overflow:hidden;">
    <div class="ed-card-header">
        <i class="ti ti-report-money"></i>
        <span class="ed-card-title">Podsumowanie wyceny</span>
    </div>
    <div class="ed-card-body" style="padding:0;">
        <div class="markup-bar" style="border-radius:0;border-left:none;border-right:none;border-top:none;">
            <span style="font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;color:#92400E;">Narzut globalny:</span>
            <div class="input-group" style="width:120px;">
                <input type="text" id="markup-pct" class="field-input" value="0" placeholder="0" oninput="validateDecimal(this); syncMarkup('pct')" onkeydown="return allowDecimalInput(event)" style="border-radius:7px 0 0 7px;">
                <span class="input-suffix">%</span>
            </div>
            <div class="input-group" style="width:140px;">
                <input type="text" id="markup-zl" class="field-input" value="0" placeholder="0" oninput="validateDecimal(this); syncMarkup('zl')" onkeydown="return allowDecimalInput(event)" style="border-radius:7px 0 0 7px;">
                <span class="input-suffix">zł</span>
            </div>
        </div>
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
                       value="{{ old('valid_until', $validUntilDefault) }}" required>
            </div>
        </div>
    </div>
</div>

{{-- ── NOTATKI ─────────────────────────────────── --}}
<div class="ed-card">
    <div class="ed-card-header">
        <i class="ti ti-notes"></i>
        <span class="ed-card-title">Notatki wewnętrzne</span>
    </div>
    <div class="ed-card-body">
        <textarea name="notes" class="field-input" rows="3"
                  placeholder="Uwagi wewnętrzne (niewidoczne w PDF)...">{{ old('notes') }}</textarea>
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
                  placeholder="Dodatkowe informacje, warunki, uwagi — będą widoczne w PDF oferty...">{{ old('additional_description') }}</textarea>
    </div>
</div>

{{-- Hidden fields --}}

<input type="hidden" name="liczba_wyjazdow" value="1" id="h-wyjazdy">
<input type="hidden" name="liczba_noc"      value="0" id="h-noc">
<input type="hidden" name="liczba_osob"     value="1" id="h-osoby">
<input type="hidden" name="stawka_noc"      value="300" id="h-stawka-noc">
<input type="hidden" name="kwota_netto"     id="h-kwota-netto" value="0">
<input type="hidden" id="hidden-text-sections" name="text_sections" value="">
<input type="hidden" id="hidden-price-sections"   name="price_sections"   value="">
<input type="hidden" id="hidden-show-unit"        name="show_unit_prices" value="0">
<input type="hidden" name="km_do_klienta"    id="h-km">
<input type="hidden" name="stawka_km"        id="h-stawka-km">
<input type="hidden" name="czas_dojazdu_min" id="h-czas">
<input type="hidden" name="czy_kilkudniowy"  id="h-kilkudniowy" value="0">

</form>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
let priceSections = null;
let sectionCounter = 100;
let rowCounter     = 1000;
const globalMarkup = { pct: 0, zl: 0 };

function makePl(n) {
    return Number(n).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function parsePl(str) {
    return parseFloat(String(str).replace(/\s/g,'').replace(',','.').replace(/[^\d.-]/g,'')) || 0;
}

/* ── Unit select ── */
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

function addRow(tbodyId, rowData) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const rid = 'r' + (rowCounter++);
    const d = rowData || { opis: '', jedn: 'szt', ilosc: 1, cena_jedn: 0, z_narzutem: 0 };
    const tr = document.createElement('tr');
    tr.id = 'row-' + rid;
    tr.innerHTML = `
        <td class="col-drag" style="text-align:center;color:#ccc;cursor:grab;">
            <i class="ti ti-grip-vertical"></i>
        </td>
        <td class="col-opis">
            <input class="cell-input opis-input" type="text"
                   placeholder="Opis pozycji..."
                   value="${escHtml(d.opis)}"
                   onkeydown="if(event.key==='Enter'){event.preventDefault();addRow('${tbodyId}');}">
        </td>
        <td class="col-jedn">
            <select class="cell-input unit-select" onchange="handleUnitChange(this)" data-prev="${escHtml(d.jedn)}">
                ${buildUnitOptions(d.jedn)}
            </select>
        </td>
        <td class="col-ilosc">
            <input class="cell-input ilosc-input" type="text"
                   value="${d.ilosc}" placeholder="0"
                   oninput="validateDecimal(this); recalcRow(document.getElementById('row-${rid}'))" onkeydown="return allowDecimalInput(event)">
        </td>
        <td class="col-cena">
            <input class="cell-input cena-input" type="text"
                   value="${d.cena_jedn}" placeholder="0"
                   oninput="validateDecimal(this); recalcRow(document.getElementById('row-${rid}'))" onkeydown="return allowDecimalInput(event)">
        </td>
        <td class="col-netto" style="white-space:nowrap;text-align:right;">
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;">
                <span class="wartosc-display">${makePl(d.ilosc * d.cena_jedn)}</span>
                <span style="font-size:11px;color:#999;">zł</span>
            </div>
        </td>
        <td class="col-narzut" style="white-space:nowrap;text-align:right;">
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;">
                <span class="z-narzutem-display cell-readonly">${makePl(d.ilosc * d.cena_jedn)}</span>
                <span style="font-size:11px;color:#999;">zł</span>
            </div>
        </td>
        <td class="col-del">
            <button type="button" class="btn-del-row" onclick="removeRow(this)" title="Usuń pozycję">
                <i class="ti ti-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('.opis-input').focus();
    recalcAll();
}

function removeRow(btn) { btn.closest('tr').remove(); recalcAll(); }

function recalcRow(tr) {
    if (!tr) return;
    const ilosc = parseValue(tr.querySelector('.ilosc-input')?.value) || 0;
    const cena  = parseValue(tr.querySelector('.cena-input')?.value)  || 0;
    const net   = ilosc * cena;
    const display = tr.querySelector('.wartosc-display');
    if (display) display.textContent = makePl(net);
    const pct = parseValue(document.getElementById('markup-pct').value) || 0;
    const zNDisplay = tr.querySelector('.z-narzutem-display');
    if (zNDisplay) zNDisplay.textContent = makePl(net * (1 + pct / 100));
    recalcAll();
}

function recalcAll() {
    let sumNetto = 0;
    const pct = parseValue(document.getElementById('markup-pct').value) || 0;
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        const ilosc = parseValue(tr.querySelector('.ilosc-input')?.value) || 0;
        const cena  = parseValue(tr.querySelector('.cena-input')?.value)  || 0;
        const net   = ilosc * cena;
        sumNetto += net;
        const zNDisplay = tr.querySelector('.z-narzutem-display');
        if (zNDisplay) zNDisplay.textContent = makePl(net * (1 + pct / 100));
    });
    const delegCost = parsePl(document.getElementById('deleg-result').textContent);
    const markupZl  = sumNetto * (pct / 100);
    document.getElementById('markup-zl').value = markupZl.toFixed(2);
    const total     = sumNetto + delegCost + markupZl;
    document.getElementById('sum-services').textContent = makePl(sumNetto) + ' zł';
    document.getElementById('sum-deleg').textContent    = makePl(delegCost) + ' zł';
    document.getElementById('sum-markup').textContent   = makePl(markupZl) + ' zł';
    document.getElementById('sum-total').textContent    = makePl(total) + ' zł';
    document.getElementById('h-kwota-netto').value = total.toFixed(2);
}

function syncMarkup(source) {
    let sumNetto = 0;
    document.querySelectorAll('.price-table tbody tr').forEach(tr => {
        sumNetto += (parseValue(tr.querySelector('.ilosc-input')?.value) || 0)
                  * (parseValue(tr.querySelector('.cena-input')?.value)  || 0);
    });
    if (source === 'pct') {
        const pct = parseValue(document.getElementById('markup-pct').value) || 0;
        globalMarkup.pct = pct;
        globalMarkup.zl  = sumNetto * (pct / 100);
    } else {
        const zl = parseValue(document.getElementById('markup-zl').value) || 0;
        globalMarkup.zl  = zl;
        const pct = sumNetto > 0 ? (zl / sumNetto) * 100 : 0;
        globalMarkup.pct = pct;
        document.getElementById('markup-pct').value = pct.toFixed(2);
    }
    recalcAll();
}

function parseValue(val) {
    if (!val) return 0;
    val = val.toString().replace(',', '.');
    return parseFloat(val) || 0;
}

function allowDecimalInput(event) {
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
    let val = input.value.toString().replace('.', ',');
    val = val.replace(/[^0-9,\-]/g, '');
    const parts = val.split(',');
    if (parts.length > 2) val = parts[0] + ',' + parts.slice(1).join('');
    if (parts.length === 2 && parts[1].length > 2) val = parts[0] + ',' + parts[1].substring(0, 2);
    input.value = val;
}

function calcDeleg() {
    const km       = parseValue(document.getElementById('d_km').value)        || 0;
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

function toggleUnitPrices(cb) {
    document.getElementById('hidden-show-unit').value = cb.checked ? '1' : '0';
}

function addSection(sectionData) {
    const sid  = 'sec' + (sectionCounter++);
    const name = sectionData?.name || 'Nowa sekcja';
    const card = document.createElement('div');
    card.className = 'ed-card type-price';
    card.id = 'section-' + sid;
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
                <thead><tr>
                    <th class="col-drag"></th>
                    <th class="col-opis">Opis pozycji</th>
                    <th class="col-jedn">Jednostka</th>
                    <th class="col-ilosc">Ilość</th>
                    <th class="col-cena">Cena jedn.</th>
                    <th class="col-netto">Wartość netto</th>
                    <th class="col-narzut">Z narzutem</th>
                    <th class="col-del"></th>
                </tr></thead>
                <tbody id="tbody-${sid}"></tbody>
            </table>
        </div>
        <div style="padding:10px 16px;">
            <button type="button" class="btn-add-row" onclick="addRow('tbody-${sid}')">
                <i class="ti ti-plus"></i> Dodaj pozycję
            </button>
        </div>
    `;
    document.getElementById('dynamic-sections').appendChild(card);
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

function collectSections() {
    const sections = [];
    const main = {
        id: 'main',
        name: document.getElementById('section-main-name')?.value || 'Wycena ogólna',
        markup_pct: parseValue(document.getElementById('markup-pct')?.value) || 0,
        rows: [],
    };
    document.querySelectorAll('#tbody-main tr').forEach(tr => main.rows.push(collectRow(tr)));
    sections.push(main);
    document.querySelectorAll('#dynamic-sections .ed-card').forEach(card => {
        const sec = { id: card.id.replace('section-', ''), name: card.querySelector('.section-name-input')?.value || 'Sekcja', rows: [] };
        card.querySelectorAll('tbody tr').forEach(tr => sec.rows.push(collectRow(tr)));
        sections.push(sec);
    });
    return sections;
}

function collectRow(tr) {
    const ilosc   = parseValue(tr.querySelector('.ilosc-input')?.value) || 0;
    const cena    = parseValue(tr.querySelector('.cena-input')?.value)  || 0;
    const pct     = parseValue(document.getElementById('markup-pct').value) || 0;
    return {
        opis:       tr.querySelector('input[type="text"]')?.value || '',
        jedn:       tr.querySelector('.unit-select')?.value       || 'szt',
        ilosc:      ilosc,
        cena_jedn:  cena,
        z_narzutem: ilosc * cena * (1 + pct / 100),
    };
}

function syncDelegHiddens() {
    // Save delegations JSON
    if (typeof delegSections !== 'undefined' && delegSections.length) {
        delegSave();
        // Populate legacy hidden fields from first location for backward compat
        const first = delegSections[0];
        document.getElementById('h-km').value          = first.km || 0;
        document.getElementById('h-stawka-km').value   = first.stawka_km || 1.10;
        document.getElementById('h-czas').value        = 0;
        document.getElementById('h-wyjazdy').value     = first.wyjazdy || 1;
        document.getElementById('h-kilkudniowy').value = (first.noce > 0) ? '1' : '0';
        document.getElementById('h-noc').value         = first.noce || 0;
        document.getElementById('h-osoby').value       = first.osoby || 1;
        document.getElementById('h-stawka-noc').value  = first.stawka_noc || 300;
    }
}

/* ── Dynamiczne sekcje opisowe ─────────────────────────────── */
let textSectionCounter = 0;
let textQuills = {}; // mapa: sectionId -> instancja Quill

const DEFAULT_TEXT_SECTIONS = [
    { name: 'Przedmiot oferty',  content: '', placement: 'before_price' },
    { name: 'Zakres prac',       content: '', placement: 'before_price' },
    { name: 'Termin realizacji', content: '', placement: 'after_price' },
    { name: 'Warunki płatności', content: '', placement: 'after_price' },
];

function addTextSection(sectionData) {
    const sid = 'txt' + (textSectionCounter++);
    const name = sectionData?.name || 'Nowa sekcja';
    const content = sectionData?.content || '';
    const placement = sectionData?.placement || (textSectionCounter <= 2 ? 'before_price' : 'after_price');

    const card = document.createElement('div');
    card.className = 'ed-card type-text';
    card.id = 'text-section-' + sid;
    card.style.marginBottom = '16px';
    card.innerHTML = `
        <div class="ed-card-header">
            <i class="ti ti-file-text"></i>
            <input type="text" class="section-name-input text-section-name" value="${name.replace(/"/g,'&quot;')}" style="flex:1;">
            <select class="text-section-placement" title="Położenie sekcji w PDF" style="max-width:165px;">
                <option value="before_price" ${placement === 'before_price' ? 'selected' : ''}>Przed wyceną</option>
                <option value="after_price" ${placement === 'after_price' ? 'selected' : ''}>Po wycenie</option>
            </select>
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
            <button type="button" class="rte-btn rte-btn-ai" onclick="aiGenerateTable('${sid}')"><i class="ti ti-table"></i> Tabela AI</button>
        </div>
        <div id="editor-${sid}" style="min-height:90px;font-size:14px;"></div>
    `;
    document.getElementById('text-sections-container').appendChild(card);

    const toolbarOptions = [['bold','italic','underline'],[{header:[2,3,false]}],[{list:'ordered'},{list:'bullet'}],['clean']];
    textQuills[sid] = new Quill('#editor-' + sid, { theme: 'snow', modules: { table: true, toolbar: toolbarOptions } });
    if (content) textQuills[sid].clipboard.dangerouslyPasteHTML(content);
}

function removeTextSection(sid) {
    if (!confirm('Usunąć tę sekcję?')) return;
    document.getElementById('text-section-' + sid)?.remove();
    delete textQuills[sid];
}

function fmtOn(editorId, cmd, value) {
    // Quill toolbar buttons już wywołują execCommand poprawnie przez moduł toolbar,
    // ta funkcja jest fallbackiem — jeśli natywny toolbar Quill jest podłączony
    // (patrz modules.toolbar wyżej), te przyciski są opcjonalne i mogą być usunięte
    // jeśli powodują konflikt. Zostaw jak jest — Quill sam obsługuje formatowanie
    // przez swój wewnętrzny toolbar UI, ten kod tylko zapewnia kompatybilność wizualną
    // z istniejącym stylem .rte-btn.
    document.execCommand(cmd, false, value || null);
}

function collectTextSections() {
    const sections = [];
    document.querySelectorAll('#text-sections-container .ed-card').forEach(card => {
        const sid = card.id.replace('text-section-', '');
        const name = card.querySelector('.text-section-name')?.value || 'Sekcja';
        const content = textQuills[sid] ? textQuills[sid].root.innerHTML : '';
        const placement = card.querySelector('.text-section-placement')?.value || 'before_price';
        sections.push({ name, content, placement });
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
        const companyName = document.querySelector('#company_id option:checked')?.text?.split('—')[0]?.trim() || '';
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

async function aiGenerateTable(sid) {
    const description = prompt('Opisz nagłówki i dane tabeli. AI nie dopisze cen ani innych danych, których tu nie podasz.');
    if (!description || !description.trim()) return;

    const card = document.getElementById('text-section-' + sid);
    const quill = textQuills[sid];
    const name = card.querySelector('.text-section-name')?.value || 'Sekcja';
    const btn = card.querySelectorAll('.rte-btn-ai')[1];
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader-2"></i> Tworzę tabelę...';

    try {
        const offerTitle = document.querySelector('input[name="offer_title"]')?.value || '';
        const companyName = document.querySelector('#company_id option:checked')?.text?.split('—')[0]?.trim() || '';
        const res = await fetch('{{ route("offers.ai-assist") }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ field: 'custom_' + sid, section_name: name, mode: 'generate_table', table_request: description, offer_title: offerTitle, company_name: companyName })
        });
        const data = await res.json();
        if (data.html) {
            const at = quill.getSelection(true)?.index ?? quill.getLength() - 1;
            quill.clipboard.dangerouslyPasteHTML(at, data.html);
        } else alert('Błąd AI: ' + (data.error || 'nieznany'));
    } catch (e) { alert('Błąd połączenia z AI'); }
    finally { btn.disabled = false; btn.innerHTML = original; }
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('offer-form').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
            e.preventDefault();
        }
    });

    document.getElementById('offer-form').addEventListener('submit', function () {
        document.querySelectorAll('.ilosc-input, .cena-input').forEach(input => {
            if (input.value) input.value = input.value.toString().replace(',', '.');
        });
        document.getElementById('hidden-text-sections').value    = JSON.stringify(collectTextSections());
        document.getElementById('hidden-price-sections').value   = JSON.stringify(collectSections());
        document.getElementById('hidden-show-unit').value        = document.getElementById('show-unit-toggle').checked ? '1' : '0';
        syncDelegHiddens();
    });
window.scrollTo(0, 0);
addRow('tbody-main');
DEFAULT_TEXT_SECTIONS.forEach(s => addTextSection(s));
toggleUnitPrices(document.getElementById('show-unit-toggle'));
delegRender();
setTimeout(() => {
    window.scrollTo(0, 0);
    document.getElementById('company_id')?.focus();
}, 50);

    /* ── Distance Matrix ── */
    const companySelect = document.getElementById('company_id');
    const distanceInfo  = document.getElementById('distance-info');

    function fetchDistance(companyId) {
        if (!companyId) return;
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
    }

    // Wire btn-fetch-distance for manual trigger
    const fetchDistBtn = document.getElementById('btn-fetch-distance');
    if (fetchDistBtn) {
        fetchDistBtn.addEventListener('click', function () {
            const cid = document.getElementById('company_id')?.value;
            if (cid) fetchDistance(cid);
        });
    }

    const crmOpportunitySelect = document.getElementById('crm_opportunity_id');
    const filterCrmOpportunities = (companyId) => {
        if (!crmOpportunitySelect) return;

        Array.from(crmOpportunitySelect.options).forEach((option) => {
            if (!option.value) return;
            const belongsToSelectedCompany = option.dataset.companyId === String(companyId);
            option.hidden = !belongsToSelectedCompany;
            option.disabled = !belongsToSelectedCompany;
        });

        const selected = crmOpportunitySelect.options[crmOpportunitySelect.selectedIndex];
        if (selected?.value && selected.disabled) crmOpportunitySelect.value = '';
    };

    if (companySelect) {
        companySelect.addEventListener('change', function () {
            fetchDistance(this.value);
            filterCrmOpportunities(this.value);
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;
            const addr = [opt.dataset.address, opt.dataset.city].filter(Boolean).join(', ');
            if (typeof delegSections !== 'undefined' && delegSections.length > 0) {
                delegSections[0].nazwa = opt.dataset.name || 'Siedziba zamawiającego';
                delegSections[0].adres = addr;
                delegRender();
                if (addr) delegFetchKm(0);
            }
        });
        // Auto-fetch if company already pre-selected (e.g. from offerRequest)
        if (companySelect.value) {
            fetchDistance(companySelect.value);
            filterCrmOpportunities(companySelect.value);
        }
    }
    filterCrmOpportunities(companySelect?.value || '');
});

async function aiAssist(field, mode, quillInstance) {
    const statusEl = document.querySelector('.ai-status-' + field.replace('content_', ''));
    if (statusEl) statusEl.style.display = 'inline-flex';

    const offerTitle  = document.querySelector('input[name="offer_title"]')?.value || '';
    const companyName = document.querySelector('select[name="company_id"] option:checked')?.text?.split('—')[0]?.trim() || '';
    const current     = quillInstance.root.innerHTML;

    try {
        const response = await fetch('{{ route("offers.ai-assist") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ field, mode, current, offer_title: offerTitle, company_name: companyName }),
        });

        const data = await response.json();

        if (data.html && data.html.trim() !== '') {
            quillInstance.clipboard.dangerouslyPasteHTML(data.html);
        } else if (data.error) {
            alert('Błąd AI: ' + data.error);
        } else {
            alert('AI nie zwróciło treści. Sprawdź czy wpisałeś tekst do poprawy.');
        }
    } catch (e) {
        alert('Błąd połączenia z AI');
    } finally {
        if (statusEl) statusEl.style.display = 'none';
    }
}

function pickTemplate(templateId) {
    document.getElementById('modal-template-pick')?.remove();
    document.getElementById('editor-topbar').style.display = 'flex';
    document.getElementById('offer-form').style.display = 'block';

    if (templateId) {
        fetch('/offers/template/' + templateId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.offer_title) {
                document.querySelector('input[name="offer_title"]').value = data.offer_title;
            }
            document.getElementById('text-sections-container').innerHTML = '';
            textQuills = {};
            if (data.text_sections && data.text_sections.length > 0) {
                data.text_sections.forEach(s => addTextSection(s));
            } else {
                addTextSection({ name: 'Przedmiot oferty', content: data.content_subject || '' });
                addTextSection({ name: 'Zakres prac', content: data.content_scope || '' });
                addTextSection({ name: 'Termin realizacji', content: data.content_deadline || '' });
                addTextSection({ name: 'Warunki płatności', content: data.content_payment || '' });
            }
            if (data.price_sections) {
                const sections = typeof data.price_sections === 'string'
                    ? JSON.parse(data.price_sections)
                    : data.price_sections;
                document.getElementById('tbody-main').innerHTML = '';
                document.getElementById('dynamic-sections').innerHTML = '';
                sections.forEach((sec, i) => {
                    if (i === 0) {
                        document.getElementById('section-main-name').value = sec.name || 'Wycena ogólna';
                        (sec.rows || []).forEach(r => addRow('tbody-main', r));
                    } else {
                        addSection(sec);
                    }
                });
            }
            recalcAll();
        })
        .catch(() => console.warn('Nie udało się załadować szablonu'));
    }
}

function closeTemplatePick() {
    window.history.back();
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_key') }}&libraries=places"></script>
<script>
// ── Delegacje builder ──────────────────────────────────────────────────────

const DELEG_STAWKA_KM  = 1.10;
const DELEG_STAWKA_NOC = 200;
const DIST_URL = '{{ route("offers.get-distance") }}';

let delegSections = @json(old('delegations') ? json_decode(old('delegations'), true) : []);

if (!delegSections || delegSections.length === 0) {
    delegSections = [{
        nazwa: 'Siedziba zamawiającego',
        adres: '', km: 0, wyjazdy: 1, osoby: 1, noce: 0,
        stawka_km: DELEG_STAWKA_KM, stawka_noc: DELEG_STAWKA_NOC
    }];
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
                    <i class="ti ti-map-pin" style="color:var(--green);font-size:16px;"></i>
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
                            style="display:inline-flex;align-items:center;gap:4px;background:var(--green);color:#fff;border:none;border-radius:6px;padding:7px 10px;font-size:11px;font-family:'Manrope',sans-serif;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;">
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

            <div id="deleg-total-${idx}" style="background:#E8F5E9;border-radius:7px;padding:9px 14px;font-size:13px;font-family:'Manrope',sans-serif;color:var(--green);font-weight:700;">
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

delegRender();
</script>
@endpush
