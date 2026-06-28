@extends('layouts.app')

@section('page-title', 'Formularze zapytań')

@push('styles')
<style>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.page-title { font-family:'Manrope',sans-serif; font-size:22px; font-weight:700; color:#1A4D3A; margin:0; }
.table-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; }
.table-card-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; }
.table-card-title { font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; color:#1A1A1A; }
.crm-table { width:100%; border-collapse:collapse; font-size:13px; }
.crm-table th { padding:9px 14px; text-align:left; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #F0EDE6; background:#FAFAF6; font-family:'Manrope',sans-serif; }
.crm-table td { padding:12px 14px; border-bottom:1px solid #F7F5F0; color:#1A1A1A; vertical-align:middle; }
.crm-table tr:last-child td { border-bottom:none; }
.crm-table tr:hover td { background:#FAFAF6; }
.btn-primary { display:inline-flex; align-items:center; gap:6px; background:#1A4D3A; color:#F5F0E8; border:none; border-radius:8px; padding:8px 16px; font-family:'Manrope',sans-serif; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-primary:hover { background:#143d2d; color:#F5F0E8; }
.btn-secondary { display:inline-flex; align-items:center; gap:6px; background:#fff; color:#333; border:1px solid #D0CCC0; border-radius:8px; padding:7px 14px; font-family:'Manrope',sans-serif; font-size:13px; font-weight:600; cursor:pointer; transition:background .15s; }
.btn-secondary:hover { background:#F4F1EA; }
.btn-icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:7px; border:none; cursor:pointer; font-size:15px; transition:background .12s; }
.btn-icon-edit { background:#EFF6FF; color:#1D4ED8; }
.btn-icon-delete { background:#FEE2E2; color:#B91C1C; }
.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-green { background:#DCFCE7; color:#166534; }
.badge-gray { background:#F3F4F6; color:#4B5563; }
.toggle-wrap { display:inline-flex; align-items:center; cursor:pointer; }
.toggle-track { width:36px; height:20px; background:#D1D5DB; border-radius:10px; position:relative; transition:background .2s; }
.toggle-track::after { content:''; position:absolute; top:3px; left:3px; width:14px; height:14px; background:#fff; border-radius:50%; transition:left .2s; }
.toggle-wrap.on .toggle-track { background:#1A4D3A; }
.toggle-wrap.on .toggle-track::after { left:19px; }
.field-tag { display:inline-flex; align-items:center; gap:4px; background:#F0F7F3; border:1px solid #94C4B0; border-radius:20px; padding:2px 8px; font-size:11px; font-weight:600; color:#1A4D3A; margin:2px; }
.field-tag.conditional { background:#FEF3C7; border-color:#FCD34D; color:#92400E; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:14px; padding:28px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-title { font-family:'Manrope',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
.mf-label { display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; font-family:'Manrope',sans-serif; }
.mf-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:8px 10px; font-size:13px; font-family:'Lato',sans-serif; outline:none; transition:border-color .15s; box-sizing:border-box; }
.mf-input:focus { border-color:#1A4D3A; background:#fff; }
.mf-group { margin-bottom:14px; }
.field-builder { border:1px solid #E5E1D8; border-radius:10px; overflow:hidden; margin-bottom:14px; }
.field-builder-header { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; }
.field-builder-title { font-family:'Manrope',sans-serif; font-size:12px; font-weight:700; color:#1A4D3A; }
.field-row { border-bottom:1px solid #F7F5F0; padding:10px 14px; background:#fff; }
.field-row:last-child { border-bottom:none; }
.field-row-main { display:grid; grid-template-columns:1fr 130px 90px 32px; gap:8px; align-items:center; }
.field-row-options { margin-top:8px; padding:8px 10px; background:#F9F7F4; border-radius:6px; }
.field-row-condition { margin-top:8px; padding:8px 10px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:6px; }
.option-tag { display:inline-flex; align-items:center; gap:4px; background:#E8F5E9; border:1px solid #A5D6A7; border-radius:20px; padding:2px 8px; font-size:12px; color:#1B5E20; margin:2px; }
.option-tag button { background:none; border:none; cursor:pointer; color:#4CAF50; font-size:12px; padding:0; line-height:1; }
.btn-add-field { display:inline-flex; align-items:center; gap:6px; background:none; border:1px dashed #94C4B0; color:#1A4D3A; border-radius:6px; padding:6px 14px; font-size:12px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; margin:8px 14px 10px; transition:background .12s; }
.btn-add-field:hover { background:#F0F7F3; }
.btn-del-field { background:none; border:none; color:#DC2626; cursor:pointer; font-size:16px; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:5px; }
.btn-del-field:hover { background:#FEE2E2; }
.condition-select { background:#FAFAF6; border:1px solid #D0CCC0; border-radius:6px; padding:5px 8px; font-size:12px; font-family:'Lato',sans-serif; outline:none; }
.empty-state { text-align:center; padding:60px 24px; color:#888; }
.empty-state i { font-size:48px; color:#D0CCC0; margin-bottom:12px; display:block; }
</style>
@endpush

@section('content')

<div class="page-header">
    <h1 class="page-title"><i class="ti ti-clipboard-list" style="margin-right:8px;"></i>Formularze zapytań</h1>
    <button class="btn-primary" onclick="openModal()">
        <i class="ti ti-plus"></i> Nowy formularz
    </button>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif

<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title"><i class="ti ti-clipboard-list" style="color:#1A4D3A;margin-right:6px;"></i> Szablony formularzy ({{ $templates->count() }})</div>
    </div>
    @if($templates->isEmpty())
        <div class="empty-state">
            <i class="ti ti-clipboard-list"></i>
            <p>Brak formularzy — utwórz pierwszy szablon.</p>
        </div>
    @else
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Nazwa formularza</th>
                    <th>Pola</th>
                    <th>Aktywny</th>
                    <th style="text-align:center;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                <tr>
                    <td>
                        <div style="font-weight:700;">{{ $template->name }}</div>
                        @if($template->description)
                            <div style="font-size:12px;color:#888;margin-top:2px;">{{ Str::limit($template->description, 60) }}</div>
                        @endif
                    </td>
                    <td>
                        @foreach($template->fields as $field)
                            <span class="field-tag {{ isset($field['show_when']) ? 'conditional' : '' }}">
                                @if(isset($field['show_when']))<i class="ti ti-git-branch" style="font-size:10px;"></i>@endif
                                {{ $field['label'] }}
                            </span>
                        @endforeach
                    </td>
                    <td>
                        <button class="toggle-wrap {{ $template->is_active ? 'on' : '' }}"
                                onclick="toggleActive({{ $template->id }}, this)">
                            <span class="toggle-track"></span>
                        </button>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex;gap:4px;justify-content:center;">
                            <button class="btn-icon btn-icon-edit" onclick="openEditModal({{ $template->id }}, @js($template->name), @js($template->description), @js($template->fields), {{ $template->is_active ? 'true' : 'false' }})">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('offer-forms.destroy', $template) }}" style="display:inline;" onsubmit="return confirm('Usunąć formularz?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon btn-icon-delete"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- MODAL --}}
<div id="modal-form" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div class="modal-title" style="margin-bottom:0;">
                <i class="ti ti-clipboard-list" style="color:#1A4D3A;"></i>
                <span id="modal-title-text">Nowy formularz</span>
            </div>
            <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#888;line-height:1;">&times;</button>
        </div>

        <form id="form-template" method="POST" action="{{ route('offer-forms.store') }}">
            @csrf
            <span id="method-field"></span>

            <div class="mf-group">
                <label class="mf-label">Nazwa formularza *</label>
                <input type="text" name="name" id="f-name" class="mf-input" required placeholder="np. Audyt Energetyczny">
            </div>
            <div class="mf-group">
                <label class="mf-label">Opis (widoczny dla klienta)</label>
                <textarea name="description" id="f-desc" class="mf-input" rows="2" placeholder="Krótki opis..."></textarea>
            </div>

            <div class="mf-group">
                <label class="mf-label" style="margin-bottom:8px;">Pola formularza *</label>
                <div class="field-builder">
                    <div class="field-builder-header">
                        <span class="field-builder-title">Definicja pól</span>
                        <span style="font-size:11px;color:#888;">Pola z <i class="ti ti-git-branch" style="font-size:10px;color:#92400E;"></i> są warunkowe</span>
                    </div>
                    <div id="fields-container"></div>
                    <button type="button" class="btn-add-field" onclick="addField()">
                        <i class="ti ti-plus"></i> Dodaj pole
                    </button>
                </div>
            </div>

            <div class="mf-group" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="f-active" value="1" checked style="width:16px;height:16px;accent-color:#1A4D3A;">
                <label for="f-active" class="mf-label" style="margin:0;cursor:pointer;">Formularz aktywny</label>
            </div>

            <input type="hidden" name="fields" id="f-fields-json">

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" onclick="closeModal()" class="btn-secondary">Anuluj</button>
                <button type="submit" id="btn-submit-main" class="btn-primary" onclick="collectFields()">
                    <i class="ti ti-device-floppy"></i> Zapisz formularz
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
let fieldCounter = 0;
let editingId = null;

const FIELD_TYPES = [
    { value: 'text',     label: 'Tekst (1 linia)' },
    { value: 'textarea', label: 'Tekst (wieloliniowy)' },
    { value: 'number',   label: 'Liczba' },
    { value: 'date',     label: 'Data' },
    { value: 'select',   label: 'Lista wyboru' },
];

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function buildTypeOptions(selected) {
    return FIELD_TYPES.map(t =>
        `<option value="${t.value}" ${t.value === selected ? 'selected' : ''}>${t.label}</option>`
    ).join('');
}

function getFieldsForCondition(excludeId) {
    const rows = document.querySelectorAll('#fields-container .field-row');
    let opts = '<option value="">— wybierz pole —</option>';
    rows.forEach(row => {
        if (row.id === excludeId) return;
        const label = row.querySelector('.field-label-input')?.value?.trim();
        const key   = row.dataset.key;
        if (label && key) {
            opts += `<option value="${escHtml(key)}">${escHtml(label)}</option>`;
        }
    });
    return opts;
}

function getOptionsForField(fieldKey) {
    const rows = document.querySelectorAll('#fields-container .field-row');
    let opts = '<option value="">— wybierz wartość —</option>';
    rows.forEach(row => {
        if (row.dataset.key !== fieldKey) return;
        const tags = row.querySelectorAll('.option-value');
        tags.forEach(tag => {
            opts += `<option value="${escHtml(tag.dataset.val)}">${escHtml(tag.dataset.val)}</option>`;
        });
    });
    return opts;
}

function addField(data) {
    const id  = 'field-' + (fieldCounter++);
    const key = data?.key || 'pole_' + fieldCounter;
    const d   = data || { label: '', type: 'text', required: false, options: [], show_when: null };

    const div = document.createElement('div');
    div.className = 'field-row';
    div.id = id;
    div.dataset.key = key;

    div.innerHTML = `
        <div class="field-row-main">
            <input type="text" class="mf-input field-label-input" placeholder="Nazwa pola..." value="${escHtml(d.label)}" oninput="refreshConditionDropdowns()">
            <select class="mf-input field-type-select" onchange="onTypeChange(this, '${id}')">
                ${buildTypeOptions(d.type)}
            </select>
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;white-space:nowrap;">
                <input type="checkbox" class="field-required-check" ${d.required ? 'checked' : ''} style="accent-color:#1A4D3A;"> Wymagane
            </label>
            <button type="button" class="btn-del-field" onclick="removeField('${id}')">
                <i class="ti ti-trash"></i>
            </button>
        </div>
        <div class="field-row-options" id="options-${id}" style="display:${d.type === 'select' ? 'block' : 'none'};">
            <div style="font-size:11px;color:#555;font-weight:700;margin-bottom:6px;">Opcje listy:</div>
            <div class="options-tags" id="tags-${id}"></div>
            <div style="display:flex;gap:6px;margin-top:6px;">
                <input type="text" class="mf-input option-input" id="opt-input-${id}" placeholder="Wpisz opcję i naciśnij Enter..." style="font-size:12px;padding:5px 8px;" onkeydown="if(event.key==='Enter'){event.preventDefault();addOption('${id}');}">
                <button type="button" onclick="addOption('${id}')" style="background:#1A4D3A;color:#fff;border:none;border-radius:6px;padding:5px 10px;font-size:12px;cursor:pointer;">Dodaj</button>
            </div>
        </div>
        <div class="field-row-condition" id="condition-${id}" style="display:none;">
            <div style="font-size:11px;color:#92400E;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:4px;">
                <i class="ti ti-git-branch" style="font-size:11px;"></i> Reguła warunkowa — pokaż to pole gdy:
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <select class="condition-select condition-field-select" id="cond-field-${id}" onchange="onCondFieldChange('${id}')">
                    ${getFieldsForCondition(id)}
                </select>
                <span style="font-size:12px;color:#555;">równa się</span>
                <div id="cond-val-wrap-${id}" style="display:contents;">
                    <input type="text" class="condition-select" id="cond-val-${id}" placeholder="Wartość warunku..." style="min-width:140px;">
                </div>
                <button type="button" onclick="removeCondition('${id}')" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:12px;">Usuń regułę</button>
            </div>
        </div>
        <div style="margin-top:8px;display:flex;gap:8px;">
            <button type="button" onclick="toggleCondition('${id}')" id="cond-btn-${id}" style="background:none;border:1px dashed #FCD34D;color:#92400E;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;">
                <i class="ti ti-git-branch" style="font-size:11px;"></i> Dodaj regułę warunkową
            </button>
        </div>
    `;
    document.getElementById('fields-container').appendChild(div);

    if (d.options && d.options.length > 0) {
        d.options.forEach(opt => addOptionValue(id, opt));
    }

    if (d.show_when) {
        showConditionPanel(id, d.show_when.field, d.show_when.value);
    }
}

function onTypeChange(sel, id) {
    const optPanel = document.getElementById('options-' + id);
    if (optPanel) optPanel.style.display = sel.value === 'select' ? 'block' : 'none';
}

function addOption(id) {
    const input = document.getElementById('opt-input-' + id);
    const val = input.value.trim();
    if (!val) return;
    addOptionValue(id, val);
    input.value = '';
    input.focus();
    refreshConditionDropdowns();
}

function addOptionValue(id, val) {
    const tags = document.getElementById('tags-' + id);
    const span = document.createElement('span');
    span.className = 'option-tag option-value';
    span.dataset.val = val;
    span.innerHTML = `${escHtml(val)} <button type="button" onclick="this.parentElement.remove();refreshConditionDropdowns();">&times;</button>`;
    tags.appendChild(span);
}

function removeField(id) {
    document.getElementById(id)?.remove();
    refreshConditionDropdowns();
}

function toggleCondition(id) {
    const panel = document.getElementById('condition-' + id);
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        refreshConditionDropdowns();
    } else {
        panel.style.display = 'none';
    }
}

function removeCondition(id) {
    const panel = document.getElementById('condition-' + id);
    if (panel) panel.style.display = 'none';
    const fieldSel = document.getElementById('cond-field-' + id);
    if (fieldSel) fieldSel.value = '';
    const wrap = document.getElementById('cond-val-wrap-' + id);
    if (wrap) wrap.innerHTML = `<input type="text" class="condition-select" id="cond-val-${id}" placeholder="Wartość warunku..." style="min-width:140px;">`;
}

function onCondFieldChange(id) {
    const fieldKey = document.getElementById('cond-field-' + id)?.value;
    const wrap = document.getElementById('cond-val-wrap-' + id);
    if (!wrap) return;

    if (!fieldKey) {
        wrap.innerHTML = `<input type="text" class="condition-select" id="cond-val-${id}" placeholder="Wartość warunku..." style="min-width:140px;">`;
        return;
    }

    let refType = 'text';
    document.querySelectorAll('#fields-container .field-row').forEach(row => {
        if (row.dataset.key === fieldKey) {
            refType = row.querySelector('.field-type-select')?.value || 'text';
        }
    });

    if (refType === 'select') {
        const opts = getOptionsForField(fieldKey);
        wrap.innerHTML = `<select class="condition-select" id="cond-val-${id}">${opts}</select>`;
    } else {
        wrap.innerHTML = `<input type="text" class="condition-select" id="cond-val-${id}" placeholder="Wartość warunku..." style="min-width:140px;">`;
    }
}

function showConditionPanel(id, fieldKey, fieldVal) {
    const panel = document.getElementById('condition-' + id);
    if (panel) panel.style.display = 'block';
    setTimeout(() => {
        const fieldSel = document.getElementById('cond-field-' + id);
        if (fieldSel) {
            fieldSel.innerHTML = getFieldsForCondition(id);
            fieldSel.value = fieldKey;
            onCondFieldChange(id);
            setTimeout(() => {
                const valEl = document.getElementById('cond-val-' + id);
                if (valEl) valEl.value = fieldVal;
            }, 10);
        }
    }, 50);
}

function refreshConditionDropdowns() {
    document.querySelectorAll('#fields-container .field-row').forEach(row => {
        const id = row.id;
        const panel = document.getElementById('condition-' + id);
        if (!panel || panel.style.display === 'none') return;
        const fieldSel = document.getElementById('cond-field-' + id);
        const curField = fieldSel?.value;
        const curVal   = document.getElementById('cond-val-' + id)?.value;
        if (fieldSel) {
            fieldSel.innerHTML = getFieldsForCondition(id);
            if (curField) fieldSel.value = curField;
        }
        if (curField) {
            onCondFieldChange(id);
            if (curVal) {
                const valEl = document.getElementById('cond-val-' + id);
                if (valEl) valEl.value = curVal;
            }
        }
    });
}

function collectFields() {
    const rows = document.querySelectorAll('#fields-container .field-row');
    const fields = [];
    rows.forEach((row, i) => {
        const label    = row.querySelector('.field-label-input')?.value?.trim();
        const type     = row.querySelector('.field-type-select')?.value;
        const required = row.querySelector('.field-required-check')?.checked;
        const key      = row.dataset.key || 'pole_' + i;
        if (!label) return;

        const field = { key, label, type, required };

        if (type === 'select') {
            field.options = [];
            row.querySelectorAll('.option-value').forEach(tag => {
                if (tag.dataset.val) field.options.push(tag.dataset.val);
            });
        }

        const condPanel = document.getElementById('condition-' + row.id);
        if (condPanel && condPanel.style.display !== 'none') {
            const condField = document.getElementById('cond-field-' + row.id)?.value;
            const condVal   = document.getElementById('cond-val-' + row.id)?.value;
            if (condField && condVal) {
                field.show_when = { field: condField, value: condVal };
            }
        }

        fields.push(field);
    });
    document.getElementById('f-fields-json').value = JSON.stringify(fields);
}

function openModal() {
    editingId = null;
    document.getElementById('modal-title-text').textContent = 'Nowy formularz';
    document.getElementById('form-template').action = '{{ route("offer-forms.store") }}';
    document.getElementById('method-field').innerHTML = '';
    document.getElementById('f-name').value = '';
    document.getElementById('f-desc').value = '';
    document.getElementById('f-active').checked = true;
    document.getElementById('fields-container').innerHTML = '';
    fieldCounter = 0;
    addField();
    document.getElementById('modal-form').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function openEditModal(id, name, description, fields, isActive) {
    editingId = id;
    document.getElementById('modal-title-text').textContent = 'Edytuj formularz';
    document.getElementById('form-template').action = '/offer-forms/' + id;
    document.getElementById('method-field').innerHTML = '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('f-name').value = name || '';
    document.getElementById('f-desc').value = description || '';
    document.getElementById('f-active').checked = isActive;
    document.getElementById('fields-container').innerHTML = '';
    fieldCounter = 0;
    if (fields && fields.length > 0) {
        fields.forEach(f => addField(f));
    } else {
        addField();
    }
    document.getElementById('modal-form').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modal-form').classList.remove('open');
    document.body.style.overflow = '';
}

function toggleActive(id, btn) {
    fetch('/offer-forms/' + id + '/toggle', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(r => r.json())
    .then(data => { btn.classList.toggle('on', data.is_active); });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});
</script>
@endpush
