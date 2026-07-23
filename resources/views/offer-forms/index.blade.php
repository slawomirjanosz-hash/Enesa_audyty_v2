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
.toggle-wrap { display:inline-flex; align-items:center; cursor:pointer; }
.toggle-track { width:36px; height:20px; background:#D1D5DB; border-radius:10px; position:relative; transition:background .2s; }
.toggle-track::after { content:''; position:absolute; top:3px; left:3px; width:14px; height:14px; background:#fff; border-radius:50%; transition:left .2s; }
.toggle-wrap.on .toggle-track { background:#1A4D3A; }
.toggle-wrap.on .toggle-track::after { left:19px; }
.field-tag { display:inline-flex; align-items:center; gap:4px; background:#F0F7F3; border:1px solid #94C4B0; border-radius:20px; padding:2px 8px; font-size:11px; font-weight:600; color:#1A4D3A; margin:2px; }
.field-tag.section { background:#EAF3FF; border-color:#93C5FD; color:#1E40AF; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:14px; padding:28px; width:100%; max-width:760px; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-title { font-family:'Manrope',sans-serif; font-size:16px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
.mf-label { display:block; font-size:11px; font-weight:700; color:#555; margin-bottom:4px; font-family:'Manrope',sans-serif; }
.mf-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:8px 10px; font-size:13px; font-family:'Lato',sans-serif; outline:none; transition:border-color .15s; box-sizing:border-box; }
.mf-input:focus { border-color:#1A4D3A; background:#fff; }
.mf-group { margin-bottom:14px; }

.section-block { border:1px solid #CBD9F0; border-radius:10px; margin-bottom:16px; background:#F7FAFF; }
.section-head { display:flex; align-items:center; gap:8px; padding:10px 12px; border-bottom:1px solid #E1E9F7; }
.section-head i.ti-layout-list { color:#1E40AF; }
.section-title-input { flex:1; background:#fff; border:1px solid #C7D6EE; border-radius:7px; padding:7px 10px; font-size:13px; font-weight:700; font-family:'Manrope',sans-serif; color:#1E3A8A; outline:none; }
.section-title-input:focus { border-color:#1E40AF; }
.btn-del-section { background:none; border:none; color:#B91C1C; cursor:pointer; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
.section-fields { padding:10px 12px 0; }

.field-row { border:1px solid #E5E1D8; border-radius:8px; padding:10px 12px; background:#fff; margin-bottom:10px; }
.field-row-main { display:grid; grid-template-columns:1fr 140px 92px auto; gap:8px; align-items:center; }
.field-tools { display:inline-flex; gap:2px; }
.field-tools button { background:none; border:none; cursor:pointer; width:26px; height:26px; display:flex; align-items:center; justify-content:center; border-radius:5px; color:#666; font-size:15px; }
.field-tools button:hover { background:#F0EDE6; color:#1A4D3A; }
.field-tools .del:hover { background:#FEE2E2; color:#DC2626; }
.field-row-options { margin-top:8px; padding:8px 10px; background:#F9F7F4; border-radius:6px; }
.options-tags { min-height:4px; }
.option-tag { display:inline-flex; align-items:center; gap:4px; background:#E8F5E9; border:1px solid #A5D6A7; border-radius:20px; padding:2px 8px; font-size:12px; color:#1B5E20; margin:2px; }
.option-tag button { background:none; border:none; cursor:pointer; color:#4CAF50; font-size:12px; padding:0; line-height:1; }
.field-branches { margin-top:8px; }
.branch-block { border-left:3px solid #FCD34D; background:#FFFBEB; border-radius:0 8px 8px 0; padding:8px 10px 4px; margin:8px 0 8px 6px; }
.branch-head { font-size:11px; color:#92400E; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:4px; }
.btn-add-field { display:inline-flex; align-items:center; gap:6px; background:none; border:1px dashed #94C4B0; color:#1A4D3A; border-radius:6px; padding:6px 14px; font-size:12px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; margin:2px 0 12px; transition:background .12s; }
.btn-add-field:hover { background:#F0F7F3; }
.btn-add-question { display:inline-flex; align-items:center; gap:5px; background:none; border:1px dashed #FCD34D; color:#92400E; border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; margin-bottom:6px; }
.btn-add-question:hover { background:#FEF3C7; }
.btn-add-section { display:inline-flex; align-items:center; gap:6px; background:none; border:1px dashed #93C5FD; color:#1E40AF; border-radius:6px; padding:7px 16px; font-size:12px; font-weight:700; font-family:'Manrope',sans-serif; cursor:pointer; transition:background .12s; }
.btn-add-section:hover { background:#EAF3FF; }
.btn-del-field { background:none; border:none; color:#DC2626; cursor:pointer; font-size:16px; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:5px; }
.btn-del-field:hover { background:#FEE2E2; }
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
                    <th>Struktura</th>
                    <th>Aktywny</th>
                    <th style="text-align:center;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                @php $secs = collect($template->fields ?? [])->where('type', 'section'); @endphp
                <tr>
                    <td>
                        <div style="font-weight:700;">{{ $template->name }}</div>
                        @if($template->description)
                            <div style="font-size:12px;color:#888;margin-top:2px;">{{ Str::limit($template->description, 60) }}</div>
                        @endif
                    </td>
                    <td>
                        @if($secs->isNotEmpty())
                            @foreach($secs as $sec)
                                <span class="field-tag section"><i class="ti ti-layout-list" style="font-size:10px;"></i> {{ $sec['title'] ?? 'Sekcja' }} ({{ count($sec['fields'] ?? []) }})</span>
                            @endforeach
                        @else
                            @foreach($template->flatFields() as $f)
                                <span class="field-tag">{{ $f['label'] }}</span>
                            @endforeach
                        @endif
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
                <label class="mf-label" style="margin-bottom:8px;">Struktura formularza *</label>
                <div id="sections-container"></div>
                <button type="button" class="btn-add-section" onclick="addSection()">
                    <i class="ti ti-layout-list"></i> Dodaj sekcję
                </button>
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
let secCounter = 0;
let keyCounter = 0;
let editingId  = null;

const FIELD_TYPES = [
    { value: 'text',     label: 'Tekst (1 linia)' },
    { value: 'textarea', label: 'Tekst (wieloliniowy)' },
    { value: 'number',   label: 'Liczba' },
    { value: 'date',     label: 'Data' },
    { value: 'select',   label: 'Lista wyboru' },
    { value: 'address',  label: 'Adres (z podpowiedziami)' },
];

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function newKey() { return 'pole_' + (++keyCounter); }
function el(tag, cls, html) {
    const d = document.createElement(tag);
    if (cls)  d.className = cls;
    if (html != null) d.innerHTML = html;
    return d;
}
function findBranch(branchWrap, opt) {
    return [...branchWrap.querySelectorAll(':scope > .branch-block')].find(b => b.dataset.opt === opt) || null;
}

function addSection(data) {
    const sec = el('div', 'section-block');
    sec.dataset.sid = 'sec_' + (++secCounter);

    const head  = el('div', 'section-head', '<i class="ti ti-layout-list"></i>');
    const title = el('input', 'section-title-input');
    title.type = 'text';
    title.placeholder = 'Nazwa sekcji (np. Dane ogólne)';
    title.value = (data && data.title) || '';
    const del = el('button', 'btn-del-section', '<i class="ti ti-trash"></i> Usuń sekcję');
    del.type = 'button';
    del.addEventListener('click', () => sec.remove());
    head.appendChild(title);
    head.appendChild(del);

    const list = el('div', 'section-fields');
    const addBtn = el('button', 'btn-add-field', '<i class="ti ti-plus"></i> Dodaj pole');
    addBtn.type = 'button';
    addBtn.addEventListener('click', () => renderField(list));

    sec.appendChild(head);
    sec.appendChild(list);
    sec.appendChild(addBtn);
    document.getElementById('sections-container').appendChild(sec);

    ((data && data.fields) || []).forEach(f => renderField(list, f));
    return sec;
}

function renderField(listEl, data, beforeNode) {
    const d = data || { label: '', type: 'text', required: false, options: [], branches: {} };
    const row = el('div', 'field-row');
    row.dataset.key = d.key || newKey();

    const main = el('div', 'field-row-main');
    const labelInput = el('input', 'mf-input field-label-input');
    labelInput.type = 'text';
    labelInput.placeholder = 'Treść pytania / nazwa pola...';
    labelInput.value = d.label || '';
    const typeSel = el('select', 'mf-input field-type-select',
        FIELD_TYPES.map(t => `<option value="${t.value}" ${t.value === d.type ? 'selected' : ''}>${t.label}</option>`).join(''));
    const reqLabel = el('label', null,
        '<input type="checkbox" class="field-required-check" ' + (d.required ? 'checked' : '') + ' style="accent-color:#1A4D3A;"> Wymagane');
    reqLabel.style.cssText = 'display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;white-space:nowrap;';
    const tools = el('div', 'field-tools');

    const insBtn = el('button', null, '<i class="ti ti-plus"></i>');
    insBtn.type = 'button';
    insBtn.title = 'Wstaw pytanie powyżej';
    insBtn.addEventListener('click', () => renderField(row.parentNode, null, row));

    const upBtn = el('button', null, '<i class="ti ti-chevron-up"></i>');
    upBtn.type = 'button';
    upBtn.title = 'Przenieś w górę';
    upBtn.addEventListener('click', () => {
        const p = row.previousElementSibling;
        if (p && p.classList.contains('field-row')) row.parentNode.insertBefore(row, p);
    });

    const downBtn = el('button', null, '<i class="ti ti-chevron-down"></i>');
    downBtn.type = 'button';
    downBtn.title = 'Przenieś w dół';
    downBtn.addEventListener('click', () => {
        const n = row.nextElementSibling;
        if (n && n.classList.contains('field-row')) row.parentNode.insertBefore(n, row);
    });

    const delBtn = el('button', 'del', '<i class="ti ti-trash"></i>');
    delBtn.type = 'button';
    delBtn.title = 'Usuń';
    delBtn.addEventListener('click', () => row.remove());

    tools.appendChild(insBtn);
    tools.appendChild(upBtn);
    tools.appendChild(downBtn);
    tools.appendChild(delBtn);

    main.appendChild(labelInput);
    main.appendChild(typeSel);
    main.appendChild(reqLabel);
    main.appendChild(tools);

    const optWrap = el('div', 'field-row-options');
    optWrap.style.display = d.type === 'select' ? 'block' : 'none';
    optWrap.innerHTML = '<div style="font-size:11px;color:#555;font-weight:700;margin-bottom:6px;">Odpowiedzi (opcje listy):</div>';
    const optTags = el('div', 'options-tags');
    const optRow  = el('div');
    optRow.style.cssText = 'display:flex;gap:6px;margin-top:6px;';
    const optInput = el('input', 'mf-input option-input');
    optInput.type = 'text';
    optInput.placeholder = 'Wpisz odpowiedź i naciśnij Enter...';
    optInput.style.cssText = 'font-size:12px;padding:5px 8px;';
    const optAdd = el('button', null, 'Dodaj');
    optAdd.type = 'button';
    optAdd.style.cssText = 'background:#1A4D3A;color:#fff;border:none;border-radius:6px;padding:5px 10px;font-size:12px;cursor:pointer;';
    optWrap.appendChild(optTags);
    optRow.appendChild(optInput);
    optRow.appendChild(optAdd);
    optWrap.appendChild(optRow);

    const branchWrap = el('div', 'field-branches');
    branchWrap.style.display = d.type === 'select' ? 'block' : 'none';

    function currentOptions() {
        return [...optTags.querySelectorAll('.option-value')].map(t => t.dataset.val);
    }
    function syncBranches() {
        const opts = currentOptions();
        [...branchWrap.querySelectorAll(':scope > .branch-block')].forEach(b => {
            if (!opts.includes(b.dataset.opt)) b.remove();
        });
        opts.forEach(opt => {
            if (findBranch(branchWrap, opt)) return;
            const b = el('div', 'branch-block');
            b.dataset.opt = opt;
            const head = el('div', 'branch-head', '<i class="ti ti-corner-down-right"></i> Pytania dla odpowiedzi: „' + esc(opt) + '"');
            const bfields = el('div', 'branch-fields');
            const bAdd = el('button', 'btn-add-question', '<i class="ti ti-plus"></i> Dodaj pytanie');
            bAdd.type = 'button';
            bAdd.addEventListener('click', () => renderField(bfields));
            b.appendChild(head);
            b.appendChild(bfields);
            b.appendChild(bAdd);
            branchWrap.appendChild(b);
        });
    }
    function addOptionTag(val) {
        val = (val || '').trim();
        if (!val) return;
        if (currentOptions().includes(val)) return;
        const tag = el('span', 'option-tag option-value', esc(val) + ' ');
        tag.dataset.val = val;
        const x = el('button', null, '&times;');
        x.type = 'button';
        x.addEventListener('click', () => { tag.remove(); syncBranches(); });
        tag.appendChild(x);
        optTags.appendChild(tag);
        syncBranches();
    }
    optAdd.addEventListener('click', () => { addOptionTag(optInput.value); optInput.value = ''; optInput.focus(); });
    optInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); optAdd.click(); } });

    typeSel.addEventListener('change', () => {
        const isSel = typeSel.value === 'select';
        optWrap.style.display    = isSel ? 'block' : 'none';
        branchWrap.style.display = isSel ? 'block' : 'none';
        if (isSel) syncBranches();
    });

    row.appendChild(main);
    row.appendChild(optWrap);
    row.appendChild(branchWrap);
    if (beforeNode) {
        listEl.insertBefore(row, beforeNode);
    } else {
        listEl.appendChild(row);
    }

    if (d.type === 'select') {
        (d.options || []).forEach(o => addOptionTag(o));
        if (d.branches) {
            Object.keys(d.branches).forEach(opt => {
                const b = findBranch(branchWrap, opt);
                if (b) {
                    const bf = b.querySelector(':scope > .branch-fields');
                    (d.branches[opt] || []).forEach(cf => renderField(bf, cf));
                }
            });
        }
    }
    return row;
}

function collectFieldList(listEl) {
    const out = [];
    listEl.querySelectorAll(':scope > .field-row').forEach(row => {
        const label    = row.querySelector(':scope > .field-row-main > .field-label-input').value.trim();
        const type     = row.querySelector(':scope > .field-row-main > .field-type-select').value;
        const required = row.querySelector(':scope > .field-row-main .field-required-check').checked;
        if (!label) return;

        const f = { key: row.dataset.key, label, type, required };

        if (type === 'select') {
            const optWrap = row.querySelector(':scope > .field-row-options');
            f.options = [...optWrap.querySelectorAll('.option-value')].map(t => t.dataset.val);
            f.branches = {};
            const bw = row.querySelector(':scope > .field-branches');
            bw.querySelectorAll(':scope > .branch-block').forEach(b => {
                f.branches[b.dataset.opt] = collectFieldList(b.querySelector(':scope > .branch-fields'));
            });
        }
        out.push(f);
    });
    return out;
}

function collectFields() {
    const sections = [];
    document.querySelectorAll('#sections-container > .section-block').forEach(sec => {
        const title  = sec.querySelector(':scope > .section-head > .section-title-input').value.trim() || 'Sekcja';
        const fields = collectFieldList(sec.querySelector(':scope > .section-fields'));
        sections.push({ type: 'section', title, fields });
    });
    document.getElementById('f-fields-json').value = JSON.stringify(sections);
}

function loadSections(fields) {
    document.getElementById('sections-container').innerHTML = '';
    secCounter = 0;

    let mx = 0;
    JSON.stringify(fields || []).replace(/pole_(\d+)/g, (m, n) => { mx = Math.max(mx, +n); return m; });
    keyCounter = mx;

    if (!Array.isArray(fields) || fields.length === 0) {
        addSection({ title: 'Sekcja 1' });
        return;
    }
    const hasSections = fields.some(f => f && f.type === 'section');
    if (hasSections) {
        fields.forEach(f => { if (f && f.type === 'section') addSection(f); });
    } else {
        const clean = fields.map(f => { const c = { ...f }; delete c.show_when; return c; });
        addSection({ title: 'Sekcja 1', fields: clean });
    }
}

function openModal() {
    editingId = null;
    document.getElementById('modal-title-text').textContent = 'Nowy formularz';
    document.getElementById('form-template').action = '{{ route("offer-forms.store") }}';
    document.getElementById('method-field').innerHTML = '';
    document.getElementById('f-name').value = '';
    document.getElementById('f-desc').value = '';
    document.getElementById('f-active').checked = true;
    loadSections([]);
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
    loadSections(fields);
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

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
@endpush
