@extends('layouts.app')

@section('page-title', 'Edytuj zapytanie')

@push('styles')
<style>
.page-header { margin-bottom:24px; }
.page-header h1 { font-family:'Manrope',sans-serif; font-size:20px; font-weight:700; color:var(--green); margin:0 0 4px; display:flex; align-items:center; gap:8px; }
.page-header p { font-size:13px; color:#888; margin:0; }
.form-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:20px; }
.form-card-header { padding:14px 20px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; display:flex; align-items:center; gap:10px; }
.form-card-header i { color:var(--green); font-size:17px; }
.form-card-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; color:#1A1A1A; }
.form-card-body { padding:20px; }
.rq-section-title { font-family:'Manrope',sans-serif; font-size:13px; font-weight:800; color:var(--green); margin:20px 0 10px; padding-bottom:6px; border-bottom:1px solid #E5E1D8; }
.rq-section-title:first-child { margin-top:0; }
.field-label { display:block; font-family:'Manrope',sans-serif; font-size:12px; font-weight:700; color:#3a3a3a; margin-bottom:5px; }
.field-label .required { color:#DC2626; }
.field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:9px 12px; font-size:14px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; box-sizing:border-box; }
.field-input:focus { border-color:var(--green); background:#fff; }
.field-group { margin-bottom:16px; }
.btn-primary { display:inline-flex; align-items:center; gap:7px; background:var(--green); color:#F5F0E8; border:none; border-radius:8px; padding:10px 20px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:background .15s; }
.btn-primary:hover { background:#143d2d; }
.btn-secondary { display:inline-flex; align-items:center; gap:7px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:8px; padding:9px 18px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-secondary:hover { background:#F4F1EA; }
.alert-error { background:#FEF2F2; border:1px solid #FCA5A5; color:#B91C1C; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; }
</style>
@endpush

@section('content')

<div style="margin-bottom:14px;">
    <a href="{{ route('companies.show', $offerRequest->company_id) }}#zapytania" style="display:inline-flex;align-items:center;gap:6px;color:var(--green);text-decoration:none;font-size:13px;font-weight:600;">
        <i class="ti ti-arrow-left"></i> Wróć do zapytań
    </a>
</div>
<div class="page-header">
    <h1><i class="ti ti-pencil"></i>Edytuj zapytanie</h1>
    <p>{{ $offerRequest->company?->name }} &mdash; zapytanie #{{ $offerRequest->id }}</p>
</div>

@if($errors->any())
    <div class="alert-error">
        <strong>Popraw b&#322;&#281;dy:</strong>
        <ul style="margin:6px 0 0 16px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('offer-requests.update', $offerRequest) }}">
    @csrf
    @method('PUT')

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-tag"></i>
            <span class="form-card-title">Nazwa zapytania</span>
        </div>
        <div class="form-card-body">
            <label class="field-label" for="title">Nazwa widoczna na dashboardzie</label>
            <input type="text" name="title" id="title" class="field-input"
                   value="{{ old('title', $offerRequest->title) }}"
                   placeholder="np. Audyt energetyczny — hala produkcyjna, Gliwice">
            <p style="font-size:12px;color:#888;margin:6px 0 0;">Ta nazwa ułatwia rozpoznanie ankiety na liście zapytań.</p>
        </div>
    </div>

    @if($offerRequest->offerFormTemplate && !empty($offerRequest->offerFormTemplate->fields))
    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-clipboard-list"></i>
            <span class="form-card-title">{{ $offerRequest->offerFormTemplate->name }}</span>
        </div>
        <div class="form-card-body">
            <div id="dynamic-fields"></div>
        </div>
    </div>
    @endif

    <div class="form-card">
        <div class="form-card-header">
            <i class="ti ti-mail"></i>
            <span class="form-card-title">Tre&#347;&#263; zapytania / wiadomo&#347;&#263; od klienta</span>
        </div>
        <div class="form-card-body">
            <textarea name="tresc" class="field-input" rows="6"
                      placeholder="Tre&#347;&#263; zapytania...">{{ old('tresc', $offerRequest->tresc) }}</textarea>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <a href="{{ route('companies.show', $offerRequest->company_id) }}#zapytania" class="btn-secondary">
            <i class="ti ti-x"></i> Anuluj
        </a>
        <button type="submit" class="btn-primary">
            <i class="ti ti-check"></i> Zapisz zmiany
        </button>
    </div>
</form>

@endsection

@push('scripts')
@if($offerRequest->offerFormTemplate && !empty($offerRequest->offerFormTemplate->fields))
<script>
const TEMPLATE_FIELDS = @json($offerRequest->offerFormTemplate->fields);
const RESPONSES = @json($offerRequest->form_responses ?? []);

function renderRequestFields(fields, container) {
    container.innerHTML = '';
    const nodes = Array.isArray(fields) ? fields : [];
    const hasSections = nodes.some(f => f && f.type === 'section');

    if (hasSections) {
        nodes.forEach(function(sec) {
            if (!sec || sec.type !== 'section') return;
            if (sec.title) {
                const h = document.createElement('div');
                h.className = 'rq-section-title';
                h.textContent = sec.title;
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
    label.textContent = field.label || field.key;
    group.appendChild(label);

    const saved = RESPONSES[field.key];

    if (field.type === 'address') {
        const av = (typeof saved === 'object' && saved) ? saved : {};
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
        let html = '<option value="">&#8212; wybierz &#8212;</option>';
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
    group.appendChild(input);
    parent.appendChild(group);

    if (field.type !== 'select' && saved != null) {
        input.value = saved;
    }

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

        if (saved != null && saved !== '') {
            input.value = saved;
            input.dispatchEvent(new Event('change'));
        }
    }
}

function setBranchDisabled(wrap, disabled) {
    wrap.querySelectorAll('input, select, textarea').forEach(function(elm) { elm.disabled = disabled; });
}

document.addEventListener('DOMContentLoaded', function() {
    const c = document.getElementById('dynamic-fields');
    if (c) renderRequestFields(TEMPLATE_FIELDS, c);
});
</script>
@endif
@endpush
