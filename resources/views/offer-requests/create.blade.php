@extends('layouts.app')

@section('page-title', 'Nowe zapytanie')

@push('styles')
<style>
.page-header { margin-bottom:24px; }
.page-header h1 { font-family:'Manrope',sans-serif; font-size:20px; font-weight:700; color:#1A4D3A; margin:0 0 4px; display:flex; align-items:center; gap:8px; }
.page-header p { font-size:13px; color:#888; margin:0; }
.form-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:20px; }
.form-card-header { padding:14px 20px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; display:flex; align-items:center; gap:10px; }
.form-card-header i { color:#1A4D3A; font-size:17px; }
.form-card-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.form-card-body { padding:20px; }
.field-label { display:block; font-family:'Manrope',sans-serif; font-size:12px; font-weight:700; color:#3a3a3a; margin-bottom:5px; }
.field-label .required { color:#DC2626; margin-left:2px; }
.field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:9px 12px; font-size:14px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; box-sizing:border-box; }
.field-input:focus { border-color:#1A4D3A; background:#fff; }
.field-group { margin-bottom:16px; }
.templates-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:14px; }
.template-card { background:#FAFAF6; border:2px solid #E5E1D8; border-radius:10px; padding:16px; cursor:pointer; transition:border-color .15s, box-shadow .15s; }
.template-card:hover { border-color:#1A4D3A; box-shadow:0 2px 10px rgba(26,77,58,.10); }
.template-card.selected { border-color:#1A4D3A; background:#F0F7F3; }
.template-card-icon { width:36px; height:36px; background:#E8F5E9; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:17px; color:#1A4D3A; margin-bottom:10px; }
.template-card-name { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.btn-primary { display:inline-flex; align-items:center; gap:7px; background:#1A4D3A; color:#F5F0E8; border:none; border-radius:8px; padding:10px 20px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:background .15s; }
.btn-primary:hover { background:#143d2d; }
.btn-secondary { display:inline-flex; align-items:center; gap:7px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:8px; padding:9px 18px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-secondary:hover { background:#F4F1EA; }
.alert-error { background:#FEF2F2; border:1px solid #FCA5A5; color:#B91C1C; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; }
.hint { font-size:12px; color:#888; margin-top:6px; line-height:1.5; }
</style>
@endpush

@section('content')

<div class="page-header">
    <h1><i class="ti ti-mail-plus"></i>Nowe zapytanie</h1>
    <p>Załóż zapytanie ofertowe ręcznie — np. na podstawie maila otrzymanego od klienta.</p>
</div>

@if($errors->any())
    <div class="alert-error">
        <strong>Popraw błędy:</strong>
        <ul style="margin:6px 0 0 16px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('offer-requests.store') }}" id="manual-request-form">
    @csrf

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-building"></i>
            <span class="form-card-title">Firma klienta</span>
        </div>
        <div class="form-card-body">
            <div class="field-group" style="margin-bottom:0;">
                <label class="field-label" for="company_id">Wybierz firmę <span class="required">*</span></label>
                <select name="company_id" id="company_id" class="field-input" required>
                    <option value="">— wybierz firmę —</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ old('company_id', $preselectedCompanyId) == $company->id ? 'selected' : '' }}>
                            {{ $company->name }}@if($company->city) — {{ $company->city }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-tag"></i>
            <span class="form-card-title">Nazwa zapytania</span>
        </div>
        <div class="form-card-body">
            <input type="text" name="title" value="{{ old('title') }}" class="field-input"
                   placeholder="np. Audyt energetyczny — hala produkcyjna, Gliwice">
            <p class="hint">Krótka nazwa, po której rozpoznasz to zapytanie na liście.</p>
        </div>
    </div>

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-clipboard-list"></i>
            <span class="form-card-title">Formularz zapytania (opcjonalnie)</span>
        </div>
        <div class="form-card-body">
            @if($templates->isEmpty())
                <p class="hint">Brak aktywnych formularzy — możesz pominąć ten krok i wkleić treść maila poniżej.</p>
            @else
                <div class="templates-grid">
                    @foreach($templates as $template)
                    <div class="template-card" id="card-{{ $template->id }}" onclick="selectTemplate({{ $template->id }}, @js($template->name), @js($template->fields))">
                        <div class="template-card-icon"><i class="ti ti-clipboard-list"></i></div>
                        <div class="template-card-name">{{ $template->name }}</div>
                    </div>
                    @endforeach
                </div>
                <p class="hint">Wybór formularza jest opcjonalny — możesz też ograniczyć się do wklejenia treści maila poniżej.</p>
            @endif

            <div id="dynamic-fields-wrap" style="display:none;margin-top:18px;padding-top:18px;border-top:1px solid #F0EDE6;">
                <div id="dynamic-fields"></div>
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-mail"></i>
            <span class="form-card-title">Treść zapytania / wiadomość od klienta</span>
        </div>
        <div class="form-card-body">
            <textarea name="tresc" class="field-input" rows="6"
                      placeholder="Wklej tutaj treść maila od klienta lub opisz zapytanie własnymi słowami...">{{ old('tresc') }}</textarea>
            <p class="hint">To pole jest widoczne w karcie firmy razem z resztą zapytania i pomaga zachować pełny kontekst rozmowy z klientem.</p>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <a href="{{ url()->previous() }}" class="btn-secondary">
            <i class="ti ti-x"></i> Anuluj
        </a>
        <button type="submit" class="btn-primary">
            <i class="ti ti-check"></i> Utwórz zapytanie
        </button>
    </div>
</form>

@endsection

@push('scripts')
<script>
function renderRequestFields(fields, container) {
    container.innerHTML = '';
    const nodes = Array.isArray(fields) ? fields : [];
    const hasSections = nodes.some(f => f && f.type === 'section');

    if (hasSections) {
        nodes.forEach(function(sec) {
            if (!sec || sec.type !== 'section') return;
            if (sec.title) {
                const h = document.createElement('div');
                h.textContent = sec.title;
                h.style.cssText = "font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#1A4D3A;margin:18px 0 10px;padding-bottom:6px;border-bottom:1px solid #E5E1D8;";
                container.appendChild(h);
            }
            (sec.fields || []).forEach(function(f) { renderRespField(container, f); });
        });
    } else {
        nodes.forEach(function(f) { renderRespField(container, f); });
    }
}

function renderRespField(parent, field) {
    const group = document.createElement('div');
    group.className = 'field-group';

    const label = document.createElement('label');
    label.className = 'field-label';
    label.innerHTML = field.label + (field.required ? ' <span class="required">*</span>' : '');
    group.appendChild(label);

    if (field.type === 'address') {
        const av = {};
        const grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:1fr 2fr;gap:8px;';
        [['zip','Kod pocztowy'],['city','Miejscowość'],['street','Ulica'],['no','Nr']].forEach(function(p) {
            const w = document.createElement('div');
            const l = document.createElement('div');
            l.textContent = p[1];
            l.style.cssText = 'font-size:11px;font-weight:700;color:#777;margin-bottom:3px;';
            const i = document.createElement('input');
            i.type = 'text';
            i.className = 'field-input';
            i.name = 'form_responses[' + field.key + '][' + p[0] + ']';
            i.value = av[p[0]] || '';
            w.appendChild(l);
            w.appendChild(i);
            grid.appendChild(w);
        });
        group.appendChild(grid);
        parent.appendChild(group);
        return;
    }

    let input;
    if (field.type === 'select') {
        input = document.createElement('select');
        input.className = 'field-input';
        let html = '<option value="">— wybierz —</option>';
        (field.options || []).forEach(function(o) {
            const v = String(o).replace(/"/g, '&quot;');
            html += '<option value="' + v + '">' + v + '</option>';
        });
        input.innerHTML = html;
    } else if (field.type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = 3;
        input.style.resize = 'vertical';
        input.className = 'field-input';
    } else {
        input = document.createElement('input');
        input.type = field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text');
        input.className = 'field-input';
    }
    input.name = 'form_responses[' + field.key + ']';
    if (field.required) input.required = true;
    group.appendChild(input);
    parent.appendChild(group);

    if (field.type === 'select') {
        const host = document.createElement('div');
        host.style.cssText = 'margin-left:4px;';
        parent.appendChild(host);

        const wraps = {};
        Object.keys(field.branches || {}).forEach(function(opt) {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:none;border-left:3px solid #FCD34D;padding-left:12px;margin:4px 0 8px;';
            (field.branches[opt] || []).forEach(function(cf) { renderRespField(wrap, cf); });
            setBranchDisabled(wrap, true);
            host.appendChild(wrap);
            wraps[opt] = wrap;
        });

        input.addEventListener('change', function() {
            Object.keys(wraps).forEach(function(opt) {
                const show = (opt === input.value);
                wraps[opt].style.display = show ? 'block' : 'none';
                setBranchDisabled(wraps[opt], !show);
            });
        });
    }
}

function setBranchDisabled(wrap, disabled) {
    wrap.querySelectorAll('input, select, textarea').forEach(function(elm) { elm.disabled = disabled; });
}

function selectTemplate(id, name, fields) {
    document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('card-' + id).classList.add('selected');

    let existingHidden = document.getElementById('template-id-input');
    if (!existingHidden) {
        existingHidden = document.createElement('input');
        existingHidden.type = 'hidden';
        existingHidden.name = 'offer_form_template_id';
        existingHidden.id = 'template-id-input';
        document.getElementById('manual-request-form').appendChild(existingHidden);
    }
    existingHidden.value = id;

    const container = document.getElementById('dynamic-fields');
    renderRequestFields(fields, container);

    document.getElementById('dynamic-fields-wrap').style.display = 'block';
}
</script>
@endpush