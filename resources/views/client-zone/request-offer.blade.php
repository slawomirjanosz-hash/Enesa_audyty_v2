@extends('layouts.client-zone')

@section('page-title', 'Zapytaj o ofertę')

@push('styles')
<style>
.page-header { margin-bottom:24px; }
.page-header h1 { font-family:'Manrope',sans-serif; font-size:20px; font-weight:700; color:#1A4D3A; margin:0 0 4px; }
.page-header p { font-size:13px; color:#888; margin:0; }
.templates-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:16px; margin-bottom:32px; }
.template-card { background:#fff; border:2px solid #E5E1D8; border-radius:12px; padding:20px; cursor:pointer; transition:border-color .15s, box-shadow .15s; }
.template-card:hover { border-color:#2E6B52; box-shadow:0 4px 16px rgba(46,107,82,.10); }
.template-card.selected { border-color:#2E6B52; background:#F0F7F3; }
.template-card-icon { width:42px; height:42px; background:#E8F5E9; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; color:#2E6B52; margin-bottom:12px; }
.template-card-name { font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; color:#1A1A1A; margin-bottom:4px; }
.template-card-desc { font-size:12px; color:#888; line-height:1.5; }
.form-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:24px; display:none; }
.form-card.visible { display:block; }
.form-card-header { padding:16px 20px; background:#2E6B52; color:#fff; display:flex; align-items:center; gap:10px; }
.form-card-title { font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; }
.form-card-body { padding:24px; }
.field-group { margin-bottom:18px; }
.field-label { display:block; font-family:'Manrope',sans-serif; font-size:12px; font-weight:700; color:#3a3a3a; margin-bottom:5px; }
.field-label .required { color:#DC2626; margin-left:2px; }
.field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:9px 12px; font-size:14px; font-family:'Lato',sans-serif; color:#1A1A1A; outline:none; transition:border-color .15s; box-sizing:border-box; }
.field-input:focus { border-color:#2E6B52; background:#fff; }
.btn-primary { display:inline-flex; align-items:center; gap:7px; background:#2E6B52; color:#F5F0E8; border:none; border-radius:8px; padding:10px 20px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:background .15s; }
.btn-primary:hover { background:#265c46; }
.btn-secondary { display:inline-flex; align-items:center; gap:7px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:8px; padding:9px 18px; font-family:'Manrope',sans-serif; font-size:14px; font-weight:600; cursor:pointer; transition:background .15s; }
.btn-secondary:hover { background:#F4F1EA; }
.section-title { font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; color:#1A1A1A; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.request-item { background:#fff; border:1px solid #E5E1D8; border-radius:10px; padding:16px 20px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
.request-item-left { display:flex; flex-direction:column; gap:3px; }
.request-item-name { font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; color:#1A1A1A; }
.request-item-date { font-size:12px; color:#888; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-new    { background:#DBEAFE; color:#1D4ED8; }
.badge-inprog { background:#FEF3C7; color:#92400E; }
.badge-closed { background:#DCFCE7; color:#166534; }
.info-banner { background:#FEF3C7; border:1px solid #FCD34D; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:13px; color:#92400E; display:flex; align-items:center; gap:8px; }
</style>
@endpush

@section('content')

<div class="info-banner">
    <i class="ti ti-eye"></i>
    Przeglądasz widok klienta firmy <strong>{{ $company->name }}</strong>. Formularz jest w trybie podglądu.
</div>

<div class="page-header">
    <h1><i class="ti ti-send" style="margin-right:8px;"></i>Zapytać o ofertę</h1>
    <p>Wybierz rodzaj usługi i wypełnij formularz — odezwiemy się w ciągu 1 dnia roboczego.</p>
</div>

<div class="section-title"><i class="ti ti-list-check" style="color:#2E6B52;"></i> Wybierz rodzaj zapytania</div>

@if($templates->isEmpty())
    <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:14px 16px;font-size:13px;color:#92400E;margin-bottom:24px;">
        <i class="ti ti-alert-triangle" style="margin-right:6px;"></i>
        Brak dostępnych formularzy.
    </div>
@else
    <div class="templates-grid">
        @foreach($templates as $template)
        <div class="template-card" id="card-{{ $template->id }}" onclick="selectTemplate({{ $template->id }}, @js($template->name), @js($template->fields))">
            <div class="template-card-icon"><i class="ti ti-clipboard-list"></i></div>
            <div class="template-card-name">{{ $template->name }}</div>
            @if($template->description)
                <div class="template-card-desc">{{ $template->description }}</div>
            @endif
        </div>
        @endforeach
    </div>
@endif

<div class="form-card" id="form-card">
    <div class="form-card-header">
        <i class="ti ti-clipboard-text"></i>
        <span class="form-card-title" id="form-card-title">Wypełnij formularz</span>
    </div>
    <div class="form-card-body">
        <div id="dynamic-fields"></div>
        <div style="display:flex;gap:10px;margin-top:8px;">
            <button type="button" class="btn-secondary" onclick="cancelForm()">
                <i class="ti ti-x"></i> Anuluj
            </button>
            <button type="button" class="btn-primary" disabled style="opacity:.6;">
                <i class="ti ti-eye"></i> Tryb podglądu
            </button>
        </div>
    </div>
</div>

@if($myRequests->isNotEmpty())
<div style="margin-top:32px;">
    <div class="section-title"><i class="ti ti-history" style="color:#2E6B52;"></i> Zapytania firmy {{ $company->name }}</div>
    @foreach($myRequests as $req)
    @php
        $badgeClass = match($req->status) {
            'nowe'      => 'badge-new',
            'w_toku'    => 'badge-inprog',
            'zamknięte' => 'badge-closed',
            default     => 'badge-new',
        };
        $statusLabel = match($req->status) {
            'nowe'      => 'Nowe',
            'w_toku'    => 'W toku',
            'zamknięte' => 'Zamknięte',
            default     => $req->status,
        };
    @endphp
    <div class="request-item">
        <div class="request-item-left">
            <div class="request-item-name">{{ $req->offerFormTemplate?->name ?? 'Zapytanie #'.$req->id }}</div>
            <div class="request-item-date">{{ $req->created_at->format('d.m.Y H:i') }}</div>
        </div>
        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
    </div>
    @endforeach
</div>
@else
    <div style="text-align:center;padding:40px;color:#888;">
        <i class="ti ti-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#D0CCC0;"></i>
        <p style="font-family:'Manrope',sans-serif;font-size:14px;">Brak zapytań dla tej firmy.</p>
    </div>
@endif

@endsection

@push('scripts')
<script>
function renderRequestFields(fields, container) {
    container.innerHTML = '';
    const nodes = Array.isArray(fields) ? fields : [];
    const hasSections = nodes.some(f => f && f.type === 'section');

    if (hasSections) {
        nodes.forEach(sec => {
            if (!sec || sec.type !== 'section') return;
            if (sec.title) {
                const h = document.createElement('div');
                h.textContent = sec.title;
                h.style.cssText = "font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#1A4D3A;margin:18px 0 10px;padding-bottom:6px;border-bottom:1px solid #E5E1D8;";
                container.appendChild(h);
            }
            (sec.fields || []).forEach(f => renderRespField(container, f));
        });
    } else {
        nodes.forEach(f => renderRespField(container, f));
    }
}

function renderRespField(parent, field) {
    const group = document.createElement('div');
    group.className = 'field-group';

    const label = document.createElement('label');
    label.className = 'field-label';
    label.innerHTML = field.label + (field.required ? ' <span class="required">*</span>' : '');
    group.appendChild(label);

    let input;
    if (field.type === 'select') {
        input = document.createElement('select');
        input.className = 'field-input';
        let html = '<option value="">— wybierz —</option>';
        (field.options || []).forEach(o => {
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
        Object.keys(field.branches || {}).forEach(opt => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:none;border-left:3px solid #FCD34D;padding-left:12px;margin:4px 0 8px;';
            (field.branches[opt] || []).forEach(cf => renderRespField(wrap, cf));
            setBranchDisabled(wrap, true);
            host.appendChild(wrap);
            wraps[opt] = wrap;
        });

        input.addEventListener('change', () => {
            Object.keys(wraps).forEach(opt => {
                const show = (opt === input.value);
                wraps[opt].style.display = show ? 'block' : 'none';
                setBranchDisabled(wraps[opt], !show);
            });
        });
    }
}

function setBranchDisabled(wrap, disabled) {
    wrap.querySelectorAll('input, select, textarea').forEach(elm => { elm.disabled = disabled; });
}

function selectTemplate(id, name, fields) {
    document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('card-' + id).classList.add('selected');
    document.getElementById('form-card-title').textContent = name;

    renderRequestFields(fields, container);

    document.getElementById('form-card').classList.add('visible');
    document.getElementById('form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelForm() {
    document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('form-card').classList.remove('visible');
    document.getElementById('dynamic-fields').innerHTML = '';
}
</script>
@endpush
