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
.btn-icon-edit   { background:#EFF6FF; color:#1D4ED8; }
.btn-icon-delete { background:#FEE2E2; color:#B91C1C; }

.badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-green { background:#DCFCE7; color:#166534; }
.badge-gray  { background:#F3F4F6; color:#4B5563; }

.toggle-wrap { display:inline-flex; align-items:center; cursor:pointer; background:none; border:none; padding:0; }
.toggle-track { width:36px; height:20px; background:#D1D5DB; border-radius:10px; position:relative; transition:background .2s; }
.toggle-track::after { content:''; position:absolute; top:3px; left:3px; width:14px; height:14px; background:#fff; border-radius:50%; transition:left .2s; box-shadow:0 1px 3px rgba(0,0,0,.25); }
.toggle-wrap.on .toggle-track { background:#1A4D3A; }
.toggle-wrap.on .toggle-track::after { left:19px; }

.field-tag { display:inline-flex; align-items:center; gap:4px; background:#F0F7F3; border:1px solid #94C4B0; border-radius:20px; padding:2px 8px; font-size:11px; font-weight:600; color:#1A4D3A; margin:2px; }

/* Modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:14px; padding:28px; width:100%; max-width:620px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-title { font-family:'Manrope',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
.mf-label { display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; font-family:'Manrope',sans-serif; }
.mf-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:8px 10px; font-size:13px; font-family:'Lato',sans-serif; outline:none; transition:border-color .15s; box-sizing:border-box; }
.mf-input:focus { border-color:#1A4D3A; background:#fff; }
.mf-group { margin-bottom:14px; }

.field-builder { border:1px solid #E5E1D8; border-radius:10px; overflow:hidden; margin-bottom:14px; }
.field-builder-header { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#FAFAF6; border-bottom:1px solid #F0EDE6; }
.field-builder-title { font-family:'Manrope',sans-serif; font-size:12px; font-weight:700; color:#1A4D3A; }
.field-row { display:grid; grid-template-columns:1fr 120px 80px 36px; gap:8px; padding:10px 14px; border-bottom:1px solid #F7F5F0; align-items:center; }
.field-row:last-child { border-bottom:none; }
.btn-add-field { display:inline-flex; align-items:center; gap:6px; background:none; border:1px dashed #94C4B0; color:#1A4D3A; border-radius:6px; padding:6px 14px; font-size:12px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; margin:8px 14px 10px; transition:background .12s; }
.btn-add-field:hover { background:#F0F7F3; }
.btn-del-field { background:none; border:none; color:#DC2626; cursor:pointer; font-size:16px; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:5px; }
.btn-del-field:hover { background:#FEE2E2; }

.empty-state { text-align:center; padding:60px 24px; color:#888; }
.empty-state i { font-size:48px; color:#D0CCC0; margin-bottom:12px; display:block; }
</style>
@endpush

@section('content')

<div class="page-header">
    <h1 class="page-title"><i class="ti ti-forms" style="margin-right:8px;"></i>Formularze zapytań</h1>
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
        <div class="table-card-title"><i class="ti ti-forms" style="color:#1A4D3A;margin-right:6px;"></i> Szablony formularzy ({{ $templates->count() }})</div>
    </div>

    @if($templates->isEmpty())
        <div class="empty-state">
            <i class="ti ti-forms"></i>
            <p>Brak formularzy — utwórz pierwszy szablon klikając "Nowy formularz".</p>
        </div>
    @else
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Nazwa formularza</th>
                    <th>Opis</th>
                    <th>Pola</th>
                    <th>Aktywny</th>
                    <th style="text-align:center;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                <tr>
                    <td style="font-weight:700;">{{ $template->name }}</td>
                    <td style="color:#888;font-size:12px;max-width:200px;">{{ Str::limit($template->description, 60) }}</td>
                    <td>
                        @foreach($template->fields as $field)
                            <span class="field-tag">
                                <i class="ti ti-{{ match($field['type'] ?? 'text') {
                                    'textarea' => 'align-left',
                                    'number'   => 'hash',
                                    'date'     => 'calendar',
                                    default    => 'cursor-text'
                                } }}"></i>
                                {{ $field['label'] }}
                            </span>
                        @endforeach
                    </td>
                    <td>
                        <button class="toggle-wrap {{ $template->is_active ? 'on' : '' }}"
                                onclick="toggleActive({{ $template->id }}, this)"
                                title="{{ $template->is_active ? 'Aktywny' : 'Nieaktywny' }}">
                            <span class="toggle-track"></span>
                        </button>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex;gap:4px;justify-content:center;">
                            <button class="btn-icon btn-icon-edit" title="Edytuj"
                                onclick="openEditModal({{ $template->id }}, @js($template->name), @js($template->description), @js($template->fields), {{ $template->is_active ? 'true' : 'false' }})">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('offer-forms.destroy', $template) }}" style="display:inline;" onsubmit="return confirm('Usunąć formularz {{ addslashes($template->name) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon btn-icon-delete" title="Usuń"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ══════ MODAL: NOWY FORMULARZ ══════ --}}
<div id="modal-form" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div class="modal-title" style="margin-bottom:0;">
                <i class="ti ti-forms" style="color:#1A4D3A;"></i>
                <span id="modal-title-text">Nowy formularz</span>
            </div>
            <button onclick="closeModal()" style="background:none;border:none;font-size:22px;color:#aaa;cursor:pointer;line-height:1;">&times;</button>
        </div>

        <form id="form-create" method="POST" action="{{ route('offer-forms.store') }}">
            @csrf
            <input type="hidden" id="form-fields-json" name="fields" value="[]">

            <div class="mf-group">
                <label class="mf-label">Nazwa formularza <span style="color:#b91c1c;">*</span></label>
                <input type="text" name="name" id="input-name" class="mf-input" placeholder="np. Formularz zapytania o audyt energetyczny" required>
            </div>

            <div class="mf-group">
                <label class="mf-label">Opis (opcjonalnie)</label>
                <textarea name="description" id="input-description" class="mf-input" rows="2" placeholder="Krótki opis przeznaczenia formularza..."></textarea>
            </div>

            <div class="mf-group" style="display:flex;align-items:center;gap:10px;">
                <label class="mf-label" style="margin-bottom:0;">Aktywny</label>
                <button type="button" id="toggle-active-btn" class="toggle-wrap on" onclick="toggleActiveBtn(this)">
                    <span class="toggle-track"></span>
                </button>
                <input type="hidden" name="is_active" id="input-is-active" value="1">
            </div>

            <div class="field-builder">
                <div class="field-builder-header">
                    <span class="field-builder-title"><i class="ti ti-list-details" style="margin-right:4px;"></i>Pola formularza</span>
                    <span style="font-size:11px;color:#888;">Etykieta · Typ · Wymagane</span>
                </div>
                <div id="fields-list"></div>
                <button type="button" class="btn-add-field" onclick="addField()">
                    <i class="ti ti-plus"></i> Dodaj pole
                </button>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                <button type="button" class="btn-secondary" onclick="closeModal()">Anuluj</button>
                <button type="submit" id="btn-submit-create" class="btn-primary">
                    <i class="ti ti-check"></i> Zapisz formularz
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══════ MODAL: EDYTUJ FORMULARZ ══════ --}}
<div id="modal-edit" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <div class="modal-title" style="margin-bottom:0;">
                <i class="ti ti-pencil" style="color:#1A4D3A;"></i> Edytuj formularz
            </div>
            <button onclick="closeEditModal()" style="background:none;border:none;font-size:22px;color:#aaa;cursor:pointer;line-height:1;">&times;</button>
        </div>

        <form id="form-edit" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit-fields-json" name="fields" value="[]">

            <div class="mf-group">
                <label class="mf-label">Nazwa formularza <span style="color:#b91c1c;">*</span></label>
                <input type="text" name="name" id="edit-name" class="mf-input" required>
            </div>

            <div class="mf-group">
                <label class="mf-label">Opis (opcjonalnie)</label>
                <textarea name="description" id="edit-description" class="mf-input" rows="2"></textarea>
            </div>

            <div class="mf-group" style="display:flex;align-items:center;gap:10px;">
                <label class="mf-label" style="margin-bottom:0;">Aktywny</label>
                <button type="button" id="edit-toggle-active-btn" class="toggle-wrap" onclick="toggleActiveBtn(this)">
                    <span class="toggle-track"></span>
                </button>
                <input type="hidden" name="is_active" id="edit-is-active" value="0">
            </div>

            <div class="field-builder">
                <div class="field-builder-header">
                    <span class="field-builder-title"><i class="ti ti-list-details" style="margin-right:4px;"></i>Pola formularza</span>
                    <span style="font-size:11px;color:#888;">Etykieta · Typ · Wymagane</span>
                </div>
                <div id="edit-fields-list"></div>
                <button type="button" class="btn-add-field" onclick="addField('edit')">
                    <i class="ti ti-plus"></i> Dodaj pole
                </button>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Anuluj</button>
                <button type="submit" id="btn-submit-edit" class="btn-primary">
                    <i class="ti ti-check"></i> Zapisz zmiany
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Helpers ───────────────────────────────────────────────
let fieldIndex = 0;

function makeFieldRow(prefix, label, type, required) {
    const idx = fieldIndex++;
    const reqChecked = required ? 'checked' : '';
    return `<div class="field-row" id="frow-${prefix}-${idx}">
        <input type="text" class="mf-input field-label" placeholder="Etykieta pola" value="${escHtml(label)}">
        <select class="mf-input field-type">
            <option value="text"     ${type==='text'     ? 'selected':''}>Tekst</option>
            <option value="textarea" ${type==='textarea' ? 'selected':''}>Długi tekst</option>
            <option value="number"   ${type==='number'   ? 'selected':''}>Liczba</option>
            <option value="date"     ${type==='date'     ? 'selected':''}>Data</option>
            <option value="select"   ${type==='select'   ? 'selected':''}>Lista wyboru</option>
        </select>
        <label style="display:flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#555;cursor:pointer;">
            <input type="checkbox" class="field-required" ${reqChecked} style="accent-color:#1A4D3A;"> Wymagane
        </label>
        <button type="button" class="btn-del-field" onclick="removeField('frow-${prefix}-${idx}')">
            <i class="ti ti-trash"></i>
        </button>
    </div>`;
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function addField(prefix) {
    prefix = prefix || 'new';
    const container = prefix === 'edit'
        ? document.getElementById('edit-fields-list')
        : document.getElementById('fields-list');
    container.insertAdjacentHTML('beforeend', makeFieldRow(prefix, '', 'text', false));
}

function removeField(rowId) {
    const el = document.getElementById(rowId);
    if (el) el.remove();
}

function serializeFields(prefix) {
    prefix = prefix || 'new';
    const container = prefix === 'edit'
        ? document.getElementById('edit-fields-list')
        : document.getElementById('fields-list');
    const jsonInput = prefix === 'edit'
        ? document.getElementById('edit-fields-json')
        : document.getElementById('form-fields-json');

    const fields = [];
    container.querySelectorAll('.field-row').forEach(row => {
        const label    = row.querySelector('.field-label').value.trim();
        const type     = row.querySelector('.field-type').value;
        const required = row.querySelector('.field-required').checked;
        if (label) fields.push({ label, type, required });
    });

    jsonInput.value = JSON.stringify(fields);
}

function toggleActiveBtn(btn) {
    btn.classList.toggle('on');
    const isOn = btn.classList.contains('on');
    const inputId = btn.id === 'edit-toggle-active-btn' ? 'edit-is-active' : 'input-is-active';
    document.getElementById(inputId).value = isOn ? '1' : '0';
}

// ── Modal: Nowy ───────────────────────────────────────────
function openModal() {
    document.getElementById('input-name').value        = '';
    document.getElementById('input-description').value = '';
    document.getElementById('fields-list').innerHTML   = '';
    document.getElementById('form-fields-json').value  = '[]';

    const btn = document.getElementById('toggle-active-btn');
    btn.classList.add('on');
    document.getElementById('input-is-active').value = '1';

    fieldIndex = 0;
    addField('new');

    document.getElementById('modal-form').classList.add('open');
}

function closeModal() {
    document.getElementById('modal-form').classList.remove('open');
}

// ── Modal: Edytuj ─────────────────────────────────────────
function openEditModal(id, name, description, fields, isActive) {
    const form = document.getElementById('form-edit');
    form.action = '/offer-forms/' + id;

    document.getElementById('edit-name').value        = name        || '';
    document.getElementById('edit-description').value = description || '';

    const btn = document.getElementById('edit-toggle-active-btn');
    if (isActive) {
        btn.classList.add('on');
        document.getElementById('edit-is-active').value = '1';
    } else {
        btn.classList.remove('on');
        document.getElementById('edit-is-active').value = '0';
    }

    const container = document.getElementById('edit-fields-list');
    container.innerHTML = '';
    fieldIndex = 0;
    if (Array.isArray(fields) && fields.length > 0) {
        fields.forEach(f => {
            container.insertAdjacentHTML('beforeend',
                makeFieldRow('edit', f.label || '', f.type || 'text', !!f.required));
        });
    } else {
        addField('edit');
    }

    document.getElementById('modal-edit').classList.add('open');
}

function closeEditModal() {
    document.getElementById('modal-edit').classList.remove('open');
}

// ── Toggle active (inline) ────────────────────────────────
function toggleActive(id, btn) {
    fetch('/offer-forms/' + id + '/toggle', {
        method: 'PATCH',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.is_active) {
            btn.classList.add('on');
            btn.title = 'Aktywny';
        } else {
            btn.classList.remove('on');
            btn.title = 'Nieaktywny';
        }
    })
    .catch(() => alert('Błąd — nie udało się zmienić statusu.'));
}

// ── Anti double-submit ──────────────────────────────────
function lockSubmit(btn, label) {
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2" style="animation:spin .7s linear infinite;"></i> ' + label;
    btn.style.opacity = '0.7';
}

document.getElementById('form-create').addEventListener('submit', function(e) {
    serializeFields('new');
    const fields = JSON.parse(document.getElementById('form-fields-json').value || '[]');
    if (!fields.length) {
        e.preventDefault();
        alert('Dodaj co najmniej jedno pole formularza.');
        return;
    }
    lockSubmit(document.getElementById('btn-submit-create'), 'Zapisywanie...');
});

document.getElementById('form-edit').addEventListener('submit', function(e) {
    serializeFields('edit');
    const fields = JSON.parse(document.getElementById('edit-fields-json').value || '[]');
    if (!fields.length) {
        e.preventDefault();
        alert('Dodaj co najmniej jedno pole formularza.');
        return;
    }
    lockSubmit(document.getElementById('btn-submit-edit'), 'Zapisywanie...');
});

// CSS spin keyframe
const style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }';
document.head.appendChild(style);

// ── Close on Escape / backdrop click ─────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeEditModal(); }
});
['modal-edit'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});
</script>
@endpush
