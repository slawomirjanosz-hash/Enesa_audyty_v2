@extends('layouts.app')

@section('page-title', 'Edytuj ofertę')

@push('styles')
<style>
    .form-layout { display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start; }
    .form-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:16px; }
    .form-card:last-child { margin-bottom:0; }
    .form-card-header { padding:16px 24px; border-bottom:1px solid #F0EDE6; background:#FAFAF6; display:flex; align-items:center; gap:10px; }
    .form-card-header i { font-size:18px; color:#1A4D3A; }
    .form-card-title { font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; color:#1A1A1A; }
    .form-card-body { padding:24px; }
    .field-group { margin-bottom:16px; }
    .field-group:last-child { margin-bottom:0; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .field-label { display:block; font-size:12px; font-weight:700; color:#3a3a3a; margin-bottom:5px; font-family:'Manrope',sans-serif; }
    .field-label .req { color:#DC2626; margin-left:2px; }
    .field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:9px 12px; font-size:14px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; }
    .field-input:focus { border-color:#1A4D3A; background:#fff; }
    .field-input.error { border-color:#FCA5A5; background:#FFF5F5; }
    .field-error { font-size:11px; color:#B91C1C; margin-top:4px; }
    .input-group { display:flex; }
    .input-group .field-input { border-radius:7px 0 0 7px; border-right:none; flex:1; }
    .input-group-suffix { background:#F0EDE6; border:1px solid #D0CCC0; border-radius:0 7px 7px 0; padding:9px 12px; font-size:13px; color:#666; white-space:nowrap; }
    .full-number-preview { font-family:'Lato',sans-serif; font-size:14px; font-weight:700; color:#1A4D3A; background:#F0F7F3; border:1px dashed #94C4B0; border-radius:7px; padding:9px 14px; letter-spacing:.03em; }
    .calc-result { background:#F0F7F3; border:1px solid #94C4B0; border-radius:8px; padding:14px 16px; margin-top:16px; }
    .calc-result-label { font-size:11px; font-weight:700; color:#1A4D3A; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
    .calc-result-value { font-size:24px; font-weight:900; font-family:'Lato',sans-serif; color:#1A4D3A; }
    #overnightSection { display:none; margin-top:14px; }
    .btn-submit { width:100%; background:#1A4D3A; color:#F5F0E8; border:none; border-radius:8px; padding:13px; font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; cursor:pointer; transition:background .15s; }
    .btn-submit:hover { background:#143d2d; }
    @media (max-width:900px) { .form-layout { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')

@php $d = $offer->offerDelegation; @endphp

<div style="margin-bottom:20px;">
    <a href="{{ route('offers.show', $offer) }}" style="font-size:13px;color:#5a6a60;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
        <i class="ti ti-arrow-left"></i> Powrót do oferty
    </a>
    <h1 style="font-family:'Manrope',sans-serif;font-size:20px;font-weight:700;color:#1A4D3A;margin-top:4px;">
        Edytuj: {{ $offer->fullNumber() }}
    </h1>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ route('offers.update', $offer) }}" id="offerForm">
@csrf
@method('PUT')

<div class="form-layout">
<div>

{{-- Podstawowe dane --}}
<div class="form-card">
    <div class="form-card-header"><i class="ti ti-file-invoice"></i><span class="form-card-title">Podstawowe dane</span></div>
    <div class="form-card-body">
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="offer_number">Numer oferty <span class="req">*</span></label>
                <input type="text" id="offer_number" name="offer_number"
                       class="field-input @error('offer_number') error @enderror"
                       value="{{ old('offer_number', $offer->offer_number) }}" required>
                @error('offer_number')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label" for="offer_slug">Opis / slug</label>
                <input type="text" id="offer_slug" name="offer_slug"
                       class="field-input @error('offer_slug') error @enderror"
                       value="{{ old('offer_slug', $offer->offer_slug) }}"
                       placeholder="np. Białe Certyfikaty kompresory">
                @error('offer_slug')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Pełny numer (podgląd live)</label>
            <div class="full-number-preview" id="fullNumberPreview">{{ $offer->fullNumber() }}</div>
        </div>
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="company_id">Firma klienta <span class="req">*</span></label>
                <select id="company_id" name="company_id" class="field-input @error('company_id') error @enderror" required>
                    <option value="">— wybierz —</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ old('company_id', $offer->company_id) == $company->id ? 'selected':'' }}>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
                @error('company_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label" for="assigned_user_id">Osoba prowadząca</label>
                <select id="assigned_user_id" name="assigned_user_id" class="field-input">
                    <option value="">— nieprzypisana —</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ old('assigned_user_id', $offer->assigned_user_id) == $user->id ? 'selected':'' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="kwota_netto">Kwota netto</label>
                <div class="input-group">
                    <input type="number" id="kwota_netto" name="kwota_netto"
                           class="field-input @error('kwota_netto') error @enderror"
                           value="{{ old('kwota_netto', $offer->kwota_netto) }}" step="0.01" min="0">
                    <span class="input-group-suffix">zł netto</span>
                </div>
                @error('kwota_netto')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label" for="offer_template_version_id">Szablon oferty</label>
                <select id="offer_template_version_id" name="offer_template_version_id" class="field-input">
                    <option value="">— bez szablonu —</option>
                    @foreach($offerTemplateTypes as $type)
                        @if($type->currentVersion())
                            <option value="{{ $type->currentVersion()->id }}"
                                {{ old('offer_template_version_id', $offer->offer_template_version_id) == $type->currentVersion()->id ? 'selected':'' }}>
                                {{ $type->name }} (v.{{ $type->currentVersion()->version_number }})
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field-group" style="max-width:260px;">
            <label class="field-label" for="status">Status <span class="req">*</span></label>
            <select id="status" name="status" class="field-input" required>
                <option value="w_toku"         {{ old('status', $offer->status) === 'w_toku'         ? 'selected':'' }}>W toku</option>
                <option value="wygrana"        {{ old('status', $offer->status) === 'wygrana'        ? 'selected':'' }}>Wygrana</option>
                <option value="przegrana"      {{ old('status', $offer->status) === 'przegrana'      ? 'selected':'' }}>Przegrana</option>
                <option value="zarchiwizowana" {{ old('status', $offer->status) === 'zarchiwizowana' ? 'selected':'' }}>Zarchiwizowana</option>
            </select>
        </div>
    </div>
</div>

{{-- Delegacja --}}
<div class="form-card">
    <div class="form-card-header"><i class="ti ti-car"></i><span class="form-card-title">Delegacja</span></div>
    <div class="form-card-body">
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="km_do_klienta">Odległość (km)</label>
                <div class="input-group">
                    <input type="number" id="km_do_klienta" name="km_do_klienta" class="field-input"
                           value="{{ old('km_do_klienta', $d?->km_do_klienta ?? 0) }}" min="0" oninput="calcDelegation()">
                    <span class="input-group-suffix">km</span>
                </div>
            </div>
            <div>
                <label class="field-label" for="czas_dojazdu_min">Czas dojazdu</label>
                <div class="input-group">
                    <input type="number" id="czas_dojazdu_min" name="czas_dojazdu_min" class="field-input"
                           value="{{ old('czas_dojazdu_min', $d?->czas_dojazdu_min ?? 0) }}" min="0">
                    <span class="input-group-suffix">min</span>
                </div>
            </div>
        </div>
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="liczba_wyjazdow">Liczba wyjazdów <span class="req">*</span></label>
                <input type="number" id="liczba_wyjazdow" name="liczba_wyjazdow" class="field-input"
                       value="{{ old('liczba_wyjazdow', $d?->liczba_wyjazdow ?? 1) }}" min="1" required oninput="calcDelegation()">
            </div>
            <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:500;color:#1A1A1A;">
                    <input type="checkbox" id="czy_kilkudniowy" name="czy_kilkudniowy" value="1"
                           {{ old('czy_kilkudniowy', $d?->czy_kilkudniowy) ? 'checked':'' }}
                           onchange="toggleOvernight(this)" style="width:16px;height:16px;accent-color:#1A4D3A;">
                    Wyjazd wielodniowy?
                </label>
            </div>
        </div>
        <div id="overnightSection">
            <div class="field-group field-row">
                <div>
                    <label class="field-label" for="liczba_noc">Liczba nocy</label>
                    <input type="number" id="liczba_noc" name="liczba_noc" class="field-input"
                           value="{{ old('liczba_noc', $d?->liczba_noc ?? 0) }}" min="0" oninput="calcDelegation()">
                </div>
                <div>
                    <label class="field-label" for="liczba_osob">Liczba osób</label>
                    <input type="number" id="liczba_osob" name="liczba_osob" class="field-input"
                           value="{{ old('liczba_osob', $d?->liczba_osob ?? 1) }}" min="1" oninput="calcDelegation()">
                </div>
            </div>
            <div class="field-group" style="max-width:260px;">
                <label class="field-label" for="stawka_noc">Stawka za dobę</label>
                <div class="input-group">
                    <input type="number" id="stawka_noc" name="stawka_noc" class="field-input"
                           value="{{ old('stawka_noc', $d?->stawka_noc ?? 300) }}" min="0" step="0.01" oninput="calcDelegation()">
                    <span class="input-group-suffix">zł / doba</span>
                </div>
            </div>
        </div>
        <div class="calc-result">
            <div class="calc-result-label">Szacowany koszt delegacji</div>
            <div class="calc-result-value" id="calcResult">0,00 zł</div>
        </div>
    </div>
</div>

{{-- Notatki --}}
<div class="form-card">
    <div class="form-card-header"><i class="ti ti-notes"></i><span class="form-card-title">Notatki</span></div>
    <div class="form-card-body">
        <textarea name="notes" class="field-input" rows="4"
                  placeholder="Dodatkowe uwagi...">{{ old('notes', $offer->notes) }}</textarea>
    </div>
</div>

</div>{{-- /main --}}

{{-- Sidebar --}}
<div>
    <div class="form-card">
        <div class="form-card-header"><i class="ti ti-device-floppy"></i><span class="form-card-title">Zapisz zmiany</span></div>
        <div class="form-card-body">
            <div class="field-group">
                <label class="field-label">Pełny numer (podgląd)</label>
                <div class="full-number-preview" id="fullNumberPreviewSidebar">{{ $offer->fullNumber() }}</div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="ti ti-device-floppy" style="margin-right:6px;"></i> Zapisz zmiany
            </button>
            <div style="margin-top:12px;text-align:center;">
                <a href="{{ route('offers.show', $offer) }}" style="font-size:12px;color:#888;text-decoration:none;">Anuluj</a>
            </div>
        </div>
    </div>
</div>
</div>{{-- /form-layout --}}
</form>

@endsection

@push('scripts')
<script>
    const numInput  = document.getElementById('offer_number');
    const slugInput = document.getElementById('offer_slug');
    const prevMain  = document.getElementById('fullNumberPreview');
    const prevSide  = document.getElementById('fullNumberPreviewSidebar');

    function slugify(str) {
        return str.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/gi, '');
    }
    function updatePreview() {
        const num  = numInput.value.trim();
        const slug = slugify(slugInput.value.trim());
        const full = slug ? num + '_' + slug : num;
        if (prevMain) prevMain.textContent = full || '—';
        if (prevSide) prevSide.textContent = full || '—';
    }
    function toggleOvernight(cb) {
        document.getElementById('overnightSection').style.display = cb.checked ? 'block' : 'none';
        calcDelegation();
    }
    function calcDelegation() {
        const km      = parseFloat(document.getElementById('km_do_klienta').value)  || 0;
        const wyjazdy = parseFloat(document.getElementById('liczba_wyjazdow').value) || 1;
        const over    = document.getElementById('czy_kilkudniowy').checked;
        const noc     = parseFloat(document.getElementById('liczba_noc').value)     || 0;
        const osoby   = parseFloat(document.getElementById('liczba_osob').value)    || 1;
        const stawka  = parseFloat(document.getElementById('stawka_noc').value)     || 300;
        const total   = (km * 2 * wyjazdy * 0.89) + (over ? noc * osoby * stawka : 0);
        document.getElementById('calcResult').textContent =
            total.toLocaleString('pl-PL', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' zł';
    }
    document.addEventListener('DOMContentLoaded', function() {
        numInput.addEventListener('input', updatePreview);
        slugInput.addEventListener('input', updatePreview);
        @if(($d?->czy_kilkudniowy) || old('czy_kilkudniowy'))
            document.getElementById('overnightSection').style.display = 'block';
        @endif
        calcDelegation();
    });
</script>
@endpush
