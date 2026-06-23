@extends('layouts.app')

@section('page-title', 'Nowa oferta')

@push('styles')
<style>
    .form-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        align-items: start;
    }
    .form-card {
        background: #fff;
        border: 1px solid #E5E1D8;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }
    .form-card:last-child { margin-bottom: 0; }
    .form-card-header {
        padding: 16px 24px;
        border-bottom: 1px solid #F0EDE6;
        background: #FAFAF6;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-card-header i { font-size: 18px; color: #1A4D3A; }
    .form-card-title {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .form-card-body { padding: 24px; }

    /* ── Fields ────────────────────────────── */
    .field-group { margin-bottom: 16px; }
    .field-group:last-child { margin-bottom: 0; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .field-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #3a3a3a;
        margin-bottom: 5px;
        font-family: 'Manrope', sans-serif;
    }
    .field-label .req { color: #DC2626; margin-left: 2px; }
    .field-input {
        width: 100%;
        background: #FAFAF6;
        border: 1px solid #D0CCC0;
        border-radius: 7px;
        padding: 9px 12px;
        font-size: 14px;
        font-family: 'Lato', sans-serif;
        color: #1A1A1A;
        outline: none;
        transition: border-color .15s, background .15s;
    }
    .field-input:focus { border-color: #1A4D3A; background: #fff; }
    .field-input.error { border-color: #FCA5A5; background: #FFF5F5; }
    .field-error { font-size: 11px; color: #B91C1C; margin-top: 4px; }
    .field-hint  { font-size: 11px; color: #888; margin-top: 4px; }

    .input-group { display: flex; }
    .input-group .field-input { border-radius: 7px 0 0 7px; border-right: none; flex: 1; }
    .input-group-suffix {
        background: #F0EDE6;
        border: 1px solid #D0CCC0;
        border-radius: 0 7px 7px 0;
        padding: 9px 12px;
        font-size: 13px;
        color: #666;
        white-space: nowrap;
    }

    /* ── Full number preview ─────────────── */
    .full-number-preview {
        font-family: 'Lato', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #1A4D3A;
        background: #F0F7F3;
        border: 1px dashed #94C4B0;
        border-radius: 7px;
        padding: 9px 14px;
        letter-spacing: .03em;
    }

    /* ── Warning box ─────────────────────── */
    .warning-box {
        background: #FFFBEB;
        border: 1px solid #FCD34D;
        border-radius: 7px;
        padding: 10px 14px;
        font-size: 12px;
        color: #92400E;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
    }

    /* ── Delegation calculator ───────────── */
    .calc-result {
        background: #F0F7F3;
        border: 1px solid #94C4B0;
        border-radius: 8px;
        padding: 14px 16px;
        margin-top: 16px;
    }
    .calc-result-label { font-size: 11px; font-weight: 700; color: #1A4D3A; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
    .calc-result-value { font-size: 24px; font-weight: 900; font-family: 'Lato', sans-serif; color: #1A4D3A; }

    /* ── Overnights section ──────────────── */
    #overnightSection { display: none; margin-top: 14px; }

    /* ── Sidebar card ────────────────────── */
    .sidebar-meta { font-size: 13px; color: #5a6a60; margin-bottom: 6px; }

    /* ── Submit ──────────────────────────── */
    .btn-submit {
        width: 100%;
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        padding: 13px;
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-submit:hover { background: #143d2d; }

    @media (max-width: 900px) {
        .form-layout { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')

<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <a href="{{ route('offers.index') }}" style="font-size:13px;color:#5a6a60;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
            <i class="ti ti-arrow-left"></i> Powrót do listy ofert
        </a>
        <h1 style="font-family:'Manrope',sans-serif;font-size:20px;font-weight:700;color:#1A4D3A;margin-top:4px;">
            Nowa oferta
        </h1>
    </div>
</div>

<form method="POST" action="{{ route('offers.store') }}" id="offerForm">
@csrf

<div class="form-layout">
{{-- ═══ MAIN COLUMN ═══ --}}
<div>

{{-- SEKCJA 1: Podstawowe dane --}}
<div class="form-card">
    <div class="form-card-header">
        <i class="ti ti-file-invoice"></i>
        <span class="form-card-title">Podstawowe dane</span>
    </div>
    <div class="form-card-body">

        {{-- Numer oferty --}}
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="offer_number">Numer oferty <span class="req">*</span></label>
                <input type="text" id="offer_number" name="offer_number"
                       class="field-input @error('offer_number') error @enderror"
                       value="{{ old('offer_number', $suggestedNumber) }}" required>
                @if($numberExists)
                    <div class="warning-box">
                        <i class="ti ti-alert-triangle"></i>
                        Oferta o tym numerze już istnieje — możesz kontynuować z innym numerem.
                    </div>
                @endif
                @error('offer_number')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label" for="offer_slug">Opis / slug oferty</label>
                <input type="text" id="offer_slug" name="offer_slug"
                       class="field-input @error('offer_slug') error @enderror"
                       value="{{ old('offer_slug') }}"
                       placeholder="np. Białe Certyfikaty kompresory">
                @error('offer_slug')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Podgląd pełnego numeru --}}
        <div class="field-group">
            <label class="field-label">Pełny numer oferty (podgląd live)</label>
            <div class="full-number-preview" id="fullNumberPreview">
                {{ $suggestedNumber }}
            </div>
        </div>

        {{-- Firma + Prowadzący --}}
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="company_id">Firma klienta <span class="req">*</span></label>
                <select id="company_id" name="company_id"
                        class="field-input @error('company_id') error @enderror"
                        {{ $offerRequest ? 'disabled' : '' }} required>
                    <option value="">— wybierz firmę —</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            {{ old('company_id', $offerRequest?->company_id) == $company->id ? 'selected' : '' }}>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
                @if($offerRequest)
                    <input type="hidden" name="company_id" value="{{ $offerRequest->company_id }}">
                    <div class="field-hint">Firma z zapytania ofertowego — zablokowana.</div>
                @endif
                @error('company_id')<div class="field-error">{{ $message }}</div>@enderror
                <div id="distance-info" style="display:none;margin-top:8px;padding:10px 14px;background:#f0f7f3;border:1px solid #c8ddd4;border-radius:8px;font-size:13px;color:#1A4D3A;">
                    <span id="distance-text"></span>
                    <span id="distance-loading" style="display:none;">&#x23F3; Pobieranie odleg&#322;o&#347;ci...</span>
                    <span id="distance-error" style="display:none;color:#b91c1c;"></span>
                </div>
            </div>
            <div>
                <label class="field-label" for="assigned_user_id">Osoba prowadząca (ENESA)</label>
                <select id="assigned_user_id" name="assigned_user_id"
                        class="field-input @error('assigned_user_id') error @enderror">
                    <option value="">— nieprzypisana —</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}"
                            {{ old('assigned_user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_user_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Kwota + Szablon --}}
        <div class="field-group field-row">
            <div>
                <label class="field-label" for="kwota_netto">Kwota netto</label>
                <div class="input-group">
                    <input type="number" id="kwota_netto" name="kwota_netto"
                           class="field-input @error('kwota_netto') error @enderror"
                           value="{{ old('kwota_netto') }}" step="0.01" min="0"
                           placeholder="0.00">
                    <span class="input-group-suffix">zł netto</span>
                </div>
                @error('kwota_netto')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="field-label" for="offer_template_version_id">Szablon oferty</label>
                <select id="offer_template_version_id" name="offer_template_version_id"
                        class="field-input @error('offer_template_version_id') error @enderror">
                    <option value="">— bez szablonu —</option>
                    @foreach($offerTemplateTypes as $type)
                        @if($type->currentVersion())
                            <option value="{{ $type->currentVersion()->id }}"
                                {{ old('offer_template_version_id') == $type->currentVersion()->id ? 'selected' : '' }}>
                                {{ $type->name }} (v.{{ $type->currentVersion()->version_number }})
                            </option>
                        @endif
                    @endforeach
                </select>
                @error('offer_template_version_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        @if($offerRequest)
            <input type="hidden" name="offer_request_id" value="{{ $offerRequest->id }}">
        @endif

        {{-- Status --}}
        <div class="field-group" style="max-width:260px;">
            <label class="field-label" for="status">Status oferty <span class="req">*</span></label>
            <select id="status" name="status" class="field-input @error('status') error @enderror" required>
                <option value="w_toku"         {{ old('status','w_toku') === 'w_toku'         ? 'selected':'' }}>W toku</option>
                <option value="wygrana"        {{ old('status') === 'wygrana'        ? 'selected':'' }}>Wygrana</option>
                <option value="przegrana"      {{ old('status') === 'przegrana'      ? 'selected':'' }}>Przegrana</option>
                <option value="zarchiwizowana" {{ old('status') === 'zarchiwizowana' ? 'selected':'' }}>Zarchiwizowana</option>
            </select>
            @error('status')<div class="field-error">{{ $message }}</div>@enderror
        </div>

    </div>
</div>

{{-- SEKCJA 2: Delegacja --}}
<div class="form-card">
    <div class="form-card-header">
        <i class="ti ti-car"></i>
        <span class="form-card-title">Delegacja</span>
    </div>
    <div class="form-card-body">

        <div class="field-group field-row">
            <div>
                <label class="field-label" for="km_do_klienta">Odległość do klienta (km)</label>
                <div class="input-group">
                    <input type="number" id="km_do_klienta" name="km_do_klienta"
                           class="field-input" value="{{ old('km_do_klienta', 0) }}" min="0"
                           oninput="calcDelegation()">
                    <span class="input-group-suffix">km</span>
                </div>
            </div>
            <div>
                <label class="field-label" for="czas_dojazdu_min">Szac. czas dojazdu</label>
                <div class="input-group">
                    <input type="number" id="czas_dojazdu_min" name="czas_dojazdu_min"
                           class="field-input" value="{{ old('czas_dojazdu_min', 0) }}" min="0">
                    <span class="input-group-suffix">min</span>
                </div>
            </div>
        </div>

        <div class="field-group field-row">
            <div>
                <label class="field-label" for="liczba_wyjazdow">Liczba wyjazdów <span class="req">*</span></label>
                <input type="number" id="liczba_wyjazdow" name="liczba_wyjazdow"
                       class="field-input" value="{{ old('liczba_wyjazdow', 1) }}" min="1" required
                       oninput="calcDelegation()">
            </div>
            <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:500;color:#1A1A1A;">
                    <input type="checkbox" id="czy_kilkudniowy" name="czy_kilkudniowy" value="1"
                           {{ old('czy_kilkudniowy') ? 'checked' : '' }}
                           onchange="toggleOvernight(this)" style="width:16px;height:16px;accent-color:#1A4D3A;">
                    Wyjazd wielodniowy?
                </label>
            </div>
        </div>

        {{-- Nocleg (hidden by default) --}}
        <div id="overnightSection">
            <div class="field-group field-row">
                <div>
                    <label class="field-label" for="liczba_noc">Liczba nocy <span class="req">*</span></label>
                    <input type="number" id="liczba_noc" name="liczba_noc"
                           class="field-input" value="{{ old('liczba_noc', 0) }}" min="0"
                           oninput="calcDelegation()">
                </div>
                <div>
                    <label class="field-label" for="liczba_osob">Liczba osób <span class="req">*</span></label>
                    <input type="number" id="liczba_osob" name="liczba_osob"
                           class="field-input" value="{{ old('liczba_osob', 1) }}" min="1"
                           oninput="calcDelegation()">
                </div>
            </div>
            <div class="field-group" style="max-width:260px;">
                <label class="field-label" for="stawka_noc">Stawka za dobę hotelową</label>
                <div class="input-group">
                    <input type="number" id="stawka_noc" name="stawka_noc"
                           class="field-input" value="{{ old('stawka_noc', 300) }}" min="0" step="0.01"
                           oninput="calcDelegation()">
                    <span class="input-group-suffix">zł / doba</span>
                </div>
            </div>
        </div>

        {{-- Hidden defaults for non-overnight --}}
        <input type="hidden" id="liczba_noc_hidden" name="_noc_placeholder" value="0">
        <input type="hidden" id="liczba_osob_hidden" name="_osob_placeholder" value="1">
        <input type="hidden" id="stawka_noc_hidden" name="_stawka_placeholder" value="300">

        {{-- Calc result --}}
        <div class="calc-result">
            <div class="calc-result-label">Szacowany koszt delegacji</div>
            <div class="calc-result-value" id="calcResult">0,00 zł</div>
        </div>

    </div>
</div>

{{-- SEKCJA 3: Notatki --}}
<div class="form-card">
    <div class="form-card-header">
        <i class="ti ti-notes"></i>
        <span class="form-card-title">Notatki</span>
    </div>
    <div class="form-card-body">
        <textarea id="notes" name="notes" class="field-input" rows="4"
                  placeholder="Dodatkowe uwagi do oferty...">{{ old('notes') }}</textarea>
        @error('notes')<div class="field-error">{{ $message }}</div>@enderror
    </div>
</div>

</div>{{-- /main column --}}

{{-- ═══ SIDEBAR COLUMN ═══ --}}
<div>
    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-send"></i>
            <span class="form-card-title">Utwórz ofertę</span>
        </div>
        <div class="form-card-body">
            @if($offerRequest)
                <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:#1E40AF;">
                    <div style="font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                        <i class="ti ti-link"></i> Powiązane zapytanie
                    </div>
                    <div>{{ $offerRequest->company->name ?? '' }}</div>
                    <div style="color:#93C5FD;margin-top:2px;">#{{ $offerRequest->id }}</div>
                </div>
            @endif

            <div class="field-group">
                <label class="field-label">Pełny numer oferty</label>
                <div class="full-number-preview" id="fullNumberPreviewSidebar" style="font-size:12px;">
                    {{ $suggestedNumber }}
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="ti ti-plus" style="margin-right:6px;"></i> Utwórz ofertę
            </button>

            <div style="margin-top:12px;text-align:center;">
                <a href="{{ route('offers.index') }}" style="font-size:12px;color:#888;text-decoration:none;">
                    Anuluj i wróć do listy
                </a>
            </div>
        </div>
    </div>
</div>
</div>{{-- /form-layout --}}
</form>

@endsection

@push('scripts')
<script>
    // ── Full number live preview ──────────────────────────────
    const numInput  = document.getElementById('offer_number');
    const slugInput = document.getElementById('offer_slug');
    const prevMain  = document.getElementById('fullNumberPreview');
    const prevSide  = document.getElementById('fullNumberPreviewSidebar');

    function slugify(str) {
        return str.trim()
            .toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/gi, '');
    }

    function updatePreview() {
        const num  = numInput.value.trim();
        const slug = slugify(slugInput.value.trim());
        const full = slug ? num + '_' + slug : num;
        if (prevMain)  prevMain.textContent  = full || '—';
        if (prevSide)  prevSide.textContent  = full || '—';
    }

    numInput.addEventListener('input', updatePreview);
    slugInput.addEventListener('input', updatePreview);

    // ── Overnight toggle ──────────────────────────────────────
    function toggleOvernight(checkbox) {
        const sec = document.getElementById('overnightSection');
        sec.style.display = checkbox.checked ? 'block' : 'none';
        calcDelegation();
    }

    // ── Delegation cost calculator ────────────────────────────
    function calcDelegation() {
        const km       = parseFloat(document.getElementById('km_do_klienta').value)  || 0;
        const wyjazdy  = parseFloat(document.getElementById('liczba_wyjazdow').value) || 1;
        const overnight = document.getElementById('czy_kilkudniowy').checked;
        const noc      = parseFloat(document.getElementById('liczba_noc').value)     || 0;
        const osoby    = parseFloat(document.getElementById('liczba_osob').value)    || 1;
        const stawka   = parseFloat(document.getElementById('stawka_noc').value)     || 300;

        const dojazd   = km * 2 * wyjazdy * 0.89;
        const nocleg   = overnight ? noc * osoby * stawka : 0;
        const total    = dojazd + nocleg;

        document.getElementById('calcResult').textContent =
            total.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł';
    }

    // ── Distance Matrix ───────────────────────────────────────
    const distanceInfo    = document.getElementById('distance-info');
    const distanceText    = document.getElementById('distance-text');
    const distanceLoading = document.getElementById('distance-loading');
    const distanceError   = document.getElementById('distance-error');

    function resetDistanceUI() {
        distanceInfo.style.display    = 'none';
        distanceText.textContent      = '';
        distanceLoading.style.display = 'none';
        distanceError.style.display   = 'none';
        distanceError.textContent     = '';
    }

    document.getElementById('company_id').addEventListener('change', function () {
        const companyId = this.value;

        if (!companyId) {
            resetDistanceUI();
            document.getElementById('km_do_klienta').value    = 0;
            document.getElementById('czas_dojazdu_min').value = 0;
            calcDelegation();
            return;
        }

        distanceInfo.style.display    = 'block';
        distanceText.style.display    = 'none';
        distanceError.style.display   = 'none';
        distanceLoading.style.display = 'inline';

        const url = '{{ route('offers.get-distance') }}?company_id=' + encodeURIComponent(companyId);

        fetch(url, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            distanceLoading.style.display = 'none';

            if (data.error) {
                distanceError.textContent   = data.error;
                distanceError.style.display = 'inline';
                return;
            }

            document.getElementById('km_do_klienta').value    = data.km;
            document.getElementById('czas_dojazdu_min').value = data.minutes;
            calcDelegation();

            distanceText.textContent   = '📍 ' + data.address + ' — ' + data.km + ' km, ~' + data.minutes + ' min jazdy';
            distanceText.style.display = 'inline';
        })
        .catch(function () {
            distanceLoading.style.display = 'none';
            distanceError.textContent     = 'Błąd połączenia z serwerem.';
            distanceError.style.display   = 'inline';
        });
    });

    // ── Init ──────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        @if(old('czy_kilkudniowy'))
            document.getElementById('overnightSection').style.display = 'block';
        @endif
        calcDelegation();
        updatePreview();
    });
</script>
@endpush
