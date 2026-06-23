@extends('layouts.app')

@section('page-title', 'Szablony ofert')

@push('styles')
<style>
    /* ── Toolbar ─────────────────────────────── */
    .toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .toolbar h1 {
        font-family: 'Manrope', sans-serif;
        font-size: 20px;
        font-weight: 700;
        color: #1A4D3A;
    }

    /* ── Buttons ─────────────────────────────── */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: #1A4D3A;
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s;
    }
    .btn-primary:hover { background: #143d2d; color: #F5F0E8; }

    .btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 7px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: background .15s;
    }
    .btn-sm-outline { background:transparent; border:1px solid #D0CCC0; color:#333; }
    .btn-sm-outline:hover { background:#F0EDE6; color:#1A1A1A; }
    .btn-sm-green  { background:#1A4D3A; color:#F5F0E8; }
    .btn-sm-green:hover  { background:#143d2d; }
    .btn-sm-red    { background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; }
    .btn-sm-red:hover    { background:#FEE2E2; }

    /* ── Type card ───────────────────────────── */
    .type-card {
        background: #fff;
        border: 1px solid #E5E1D8;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }
    .type-card-header {
        padding: 16px 20px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #F0EDE6;
    }
    .type-card-title {
        font-family: 'Manrope', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: #1A1A1A;
        margin-bottom: 3px;
    }
    .type-card-desc {
        font-size: 13px;
        color: #6b7a72;
        line-height: 1.4;
    }
    .type-card-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    /* ── Current version badge ───────────────── */
    .badge-version {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
    }
    .badge-active   { background: #DCFCE7; color: #15803D; }
    .badge-inactive { background: #F3F4F6; color: #6B7280; }
    .badge-count    { background: #EFF6FF; color: #1D4ED8; }

    /* ── Versions table ──────────────────────── */
    .versions-table { width:100%; border-collapse:collapse; font-size:13px; }
    .versions-table th {
        padding: 9px 16px;
        text-align: left;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #888;
        background: #FAFAF6;
        border-bottom: 1px solid #F0EDE6;
    }
    .versions-table td {
        padding: 10px 16px;
        color: #1A1A1A;
        border-bottom: 1px solid #F7F5F0;
        vertical-align: middle;
    }
    .versions-table tr:last-child td { border-bottom: none; }
    .versions-table tr:hover td { background: #FAFAF6; }

    /* ── Empty state ─────────────────────────── */
    .empty-state {
        text-align: center;
        padding: 60px 24px;
        color: #6b7a72;
    }
    .empty-state i { font-size: 48px; color: #C8DDD4; display: block; margin-bottom: 14px; }
    .empty-state p { font-size: 15px; font-weight: 500; margin-bottom: 20px; }

    /* ── Modal ───────────────────────────────── */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 9000;
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: #fff;
        border-radius: 14px;
        padding: 32px;
        max-width: 540px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }
    .modal-title { font-family:'Manrope',sans-serif; font-size:18px; font-weight:700; color:#1A4D3A; margin-bottom:6px; }
    .modal-subtitle { font-size:13px; color:#888; margin-bottom:22px; }
    .modal-close-btn { position:absolute; top:14px; right:18px; background:none; border:none; font-size:20px; color:#aaa; cursor:pointer; line-height:1; }
    .modal-close-btn:hover { color:#333; }
    .mf-group { margin-bottom:14px; }
    .mf-label { display:block; font-size:12px; font-weight:700; color:#3a3a3a; margin-bottom:4px; }
    .mf-input  { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:9px 12px; font-size:14px; font-family:'Lato',sans-serif; outline:none; transition:border-color .15s; }
    .mf-input:focus { border-color:#1A4D3A; background:#fff; }
    .mf-textarea { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:7px; padding:10px 12px; font-size:13px; font-family:'Lato',monospace; resize:vertical; min-height:220px; outline:none; transition:border-color .15s; }
    .mf-textarea:focus { border-color:#1A4D3A; background:#fff; }
    .btn-modal-submit { width:100%; background:#1A4D3A; color:#F5F0E8; border:none; border-radius:8px; padding:12px; font-family:'Manrope',sans-serif; font-size:15px; font-weight:700; cursor:pointer; transition:background .15s; }
    .btn-modal-submit:hover { background:#143d2d; }
</style>
@endpush

@section('content')

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div style="background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-alert-circle"></i> {{ session('error') }}
    </div>
@endif

<div class="toolbar">
    <h1><i class="ti ti-file-text" style="vertical-align:middle;margin-right:8px;"></i>Szablony ofert</h1>
    <button type="button" class="btn-primary" onclick="openModal('addTypeModal')">
        <i class="ti ti-plus"></i> Dodaj typ szablonu
    </button>
</div>

@if($types->isEmpty())
    <div style="background:#fff;border:1px solid #E5E1D8;border-radius:12px;">
        <div class="empty-state">
            <i class="ti ti-file-off"></i>
            <p>Brak typów szablonów ofert</p>
            <button type="button" class="btn-primary" onclick="openModal('addTypeModal')">
                <i class="ti ti-plus"></i> Dodaj pierwszy typ
            </button>
        </div>
    </div>
@else
    @foreach($types as $type)
    @php
        $currentVer = $type->offerTemplateVersions->first(); // preloaded where is_current=true
        $allVersions = $type->offerTemplateVersions->sortByDesc('version_number');
    @endphp
    <div class="type-card">
        {{-- Header --}}
        <div class="type-card-header">
            <div style="flex:1;min-width:0;">
                <div class="type-card-title">{{ $type->name }}</div>
                @if($type->description)
                    <div class="type-card-desc">{{ $type->description }}</div>
                @endif
                <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span class="badge-version badge-count">
                        <i class="ti ti-layers-intersect"></i>
                        {{ $type->offer_template_versions_count }} {{ $type->offer_template_versions_count === 1 ? 'wersja' : 'wersji' }}
                    </span>
                    @if($currentVer)
                        <span class="badge-version badge-active">
                            <i class="ti ti-circle-check"></i>
                            v.{{ $currentVer->version_number }} — aktywna
                        </span>
                    @else
                        <span class="badge-version badge-inactive">
                            <i class="ti ti-circle-minus"></i>
                            brak aktywnej wersji
                        </span>
                    @endif
                </div>
            </div>
            <div class="type-card-actions">
                @if($currentVer)
                    <a href="{{ route('offer-templates.versions.preview', $currentVer) }}"
                       target="_blank"
                       class="btn-sm btn-sm-outline" title="Podgląd aktywnej wersji">
                        <i class="ti ti-eye"></i> Podgląd
                    </a>
                @endif
                <button type="button" class="btn-sm btn-sm-green"
                        onclick="openVersionModal({{ $type->id }}, '{{ addslashes($type->name) }}')">
                    <i class="ti ti-upload"></i> Wgraj wersję
                </button>
                <form method="POST" action="{{ route('offer-templates.destroy', $type) }}"
                      onsubmit="return confirm('Na pewno usunąć typ szablonu \'{{ addslashes($type->name) }}\'?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-sm btn-sm-red" title="Usuń typ">
                        <i class="ti ti-trash"></i>
                    </button>
                </form>
            </div>
        </div>

        {{-- Versions list --}}
        @if($type->offer_template_versions_count > 0)
        @php
            // Load all versions for this type to show the list
            $allVersionsList = \App\Models\OfferTemplateVersion::where('offer_template_type_id', $type->id)
                ->with('uploader')
                ->orderByDesc('version_number')
                ->get();
        @endphp
        <table class="versions-table">
            <thead>
                <tr>
                    <th>Wersja</th>
                    <th>Wgrał(a)</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th style="text-align:right;width:120px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($allVersionsList as $version)
                <tr>
                    <td>
                        <span style="font-family:'Lato',sans-serif;font-weight:700;color:#1A4D3A;">
                            v.{{ $version->version_number }}
                        </span>
                    </td>
                    <td style="color:#5a6a60;">{{ $version->uploader?->name ?? '—' }}</td>
                    <td style="color:#888;font-size:12px;white-space:nowrap;">{{ $version->created_at->format('d.m.Y, H:i') }}</td>
                    <td>
                        @if($version->is_current)
                            <span class="badge-version badge-active">
                                <i class="ti ti-circle-check"></i> Aktywna
                            </span>
                        @else
                            <span class="badge-version badge-inactive">Nieaktywna</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:4px;justify-content:flex-end;">
                            <a href="{{ route('offer-templates.versions.preview', $version) }}"
                               target="_blank" class="btn-sm btn-sm-outline" style="padding:4px 10px;">
                                <i class="ti ti-eye"></i>
                            </a>
                            @if(! $version->is_current)
                                <form method="POST" action="{{ route('offer-templates.versions.set-current', $version) }}">
                                    @csrf
                                    <button type="submit" class="btn-sm btn-sm-green" style="padding:4px 10px;"
                                            title="Ustaw jako aktywną">
                                        <i class="ti ti-circle-check"></i> Ustaw aktywną
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="padding:24px;text-align:center;color:#aaa;font-size:13px;">
            <i class="ti ti-file-off" style="font-size:28px;display:block;margin-bottom:8px;color:#D0CCC0;"></i>
            Brak wgranych wersji — kliknij "Wgraj wersję"
        </div>
        @endif
    </div>
    @endforeach
@endif


{{-- ══════════════════════════════════════════
     MODAL: Dodaj typ szablonu
══════════════════════════════════════════ --}}
<div id="addTypeModal" class="modal-overlay" onclick="closeModalOutside(event,'addTypeModal')">
    <div class="modal-box" onclick="event.stopPropagation()">
        <button class="modal-close-btn" onclick="closeModal('addTypeModal')">&times;</button>
        <div class="modal-title"><i class="ti ti-file-plus" style="margin-right:8px;"></i>Nowy typ szablonu</div>
        <div class="modal-subtitle">Zdefiniuj nowy typ szablonu oferty (np. AEP, ISO 50001, Białe Certyfikaty).</div>

        <form method="POST" action="{{ route('offer-templates.store') }}">
            @csrf
            <div class="mf-group">
                <label class="mf-label" for="type_name">Nazwa typu <span style="color:#DC2626;">*</span></label>
                <input type="text" id="type_name" name="name" class="mf-input"
                       placeholder="np. Białe Certyfikaty" required>
            </div>
            <div class="mf-group">
                <label class="mf-label" for="type_desc">Opis (opcjonalny)</label>
                <textarea id="type_desc" name="description" class="mf-input"
                          style="min-height:80px;resize:vertical;" rows="3"
                          placeholder="Krótki opis szablonu..."></textarea>
            </div>
            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-plus" style="margin-right:6px;"></i> Dodaj typ szablonu
            </button>
        </form>
    </div>
</div>

{{-- ══════════════════════════════════════════
     MODAL: Wgraj nową wersję HTML
══════════════════════════════════════════ --}}
<div id="versionModal" class="modal-overlay" onclick="closeModalOutside(event,'versionModal')">
    <div class="modal-box" onclick="event.stopPropagation()" style="max-width:680px;">
        <button class="modal-close-btn" onclick="closeModal('versionModal')">&times;</button>
        <div class="modal-title" id="versionModalTitle"><i class="ti ti-upload" style="margin-right:8px;"></i>Wgraj nową wersję</div>
        <div class="modal-subtitle" id="versionModalSubtitle">Wklej treść HTML szablonu oferty. Zostanie ustawiona jako aktywna wersja.</div>

        <form method="POST" id="versionForm" action="">
            @csrf
            <div class="mf-group">
                <label class="mf-label" for="html_content">Treść HTML <span style="color:#DC2626;">*</span></label>
                <textarea id="html_content" name="html_content" class="mf-textarea"
                          placeholder="<!DOCTYPE html>&#10;<html>&#10;  <body>&#10;    ...&#10;  </body>&#10;</html>"
                          required></textarea>
                <div style="font-size:11px;color:#888;margin-top:4px;">
                    <i class="ti ti-info-circle"></i> Wklej cały kod HTML szablonu. Nowa wersja zostanie oznaczona jako aktywna.
                </div>
            </div>
            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-upload" style="margin-right:6px;"></i> Wgraj i ustaw jako aktywną
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }
    function closeModalOutside(e, id) {
        if (e.currentTarget === e.target) closeModal(id);
    }

    function openVersionModal(typeId, typeName) {
        const form  = document.getElementById('versionForm');
        const title = document.getElementById('versionModalTitle');
        const sub   = document.getElementById('versionModalSubtitle');

        form.action = '/offer-templates/' + typeId + '/versions';
        title.innerHTML = '<i class="ti ti-upload" style="margin-right:8px;"></i>Wgraj wersję — ' + typeName;
        sub.textContent = 'Nowa wersja zostanie natychmiast ustawiona jako aktywna dla: ' + typeName;

        document.getElementById('html_content').value = '';
        openModal('versionModal');
    }

    @if($errors->any())
        openModal('addTypeModal');
    @endif
</script>
@endpush
