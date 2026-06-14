@extends('layouts.app')

@section('page-title', $auditType->name . ' — Wersje')

@push('styles')
<style>
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #1A4D3A;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        margin-bottom: 20px;
    }
    .back-link:hover { text-decoration: underline; }

    .page-header {
        margin-bottom: 28px;
    }
    .page-header-title {
        font-family: 'Manrope', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .page-header-sub {
        font-size: 13px;
        color: #888;
        margin-top: 3px;
    }

    .slug-badge {
        display: inline-block;
        background: #F4F1EA;
        color: #888;
        font-family: 'Lato', monospace;
        font-size: 12px;
        padding: 2px 10px;
        border-radius: 4px;
        margin-left: 10px;
        vertical-align: middle;
    }

    .card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #E5E1D8;
        overflow: hidden;
        margin-bottom: 24px;
    }
    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px;
        border-bottom: 1px solid #F0EDE6;
    }
    .card-header-title {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #1A1A1A;
    }

    .versions-table { width: 100%; border-collapse: collapse; }
    .versions-table th {
        padding: 11px 16px;
        text-align: left;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #888;
        background: #FAFAF6;
        border-bottom: 1px solid #F0EDE6;
    }
    .versions-table td {
        padding: 14px 16px;
        font-size: 14px;
        color: #1A1A1A;
        border-bottom: 1px solid #F7F5F0;
        vertical-align: middle;
    }
    .versions-table tr:last-child td { border-bottom: none; }
    .versions-table tr:hover td { background: #FAFAF6; }

    .badge-current {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #E8F5E9;
        color: #1A4D3A;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #A5D6A7;
    }
    .badge-draft {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #F4F1EA;
        color: #888;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
        transition: background .15s, border-color .15s;
    }
    .btn-set-current {
        background: #E8F5E9;
        color: #1A4D3A;
        border-color: #A5D6A7;
    }
    .btn-set-current:hover { background: #C8E6C9; }
    .btn-preview {
        background: #EEF2FF;
        color: #3730A3;
        border-color: #C7D2FE;
    }
    .btn-preview:hover { background: #E0E7FF; }

    .upload-card { background: #fff; border-radius: 12px; border: 1px solid #E5E1D8; padding: 28px; }
    .upload-card-title {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #1A1A1A;
        margin-bottom: 20px;
    }

    .form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 200px; }
    .form-label { font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .04em; }
    .form-control {
        padding: 10px 14px;
        border: 1px solid #D5D0C8;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Manrope', sans-serif;
        color: #1A1A1A;
        background: #fff;
        transition: border-color .15s;
    }
    .form-control:focus { outline: none; border-color: #1A4D3A; }
    .form-error { font-size: 12px; color: #C62828; margin-top: 4px; }

    .btn-submit {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #1A4D3A;
        color: #fff;
        padding: 11px 22px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-submit:hover { background: #143d2d; }

    .alert-success {
        background: #E8F5E9;
        border: 1px solid #A5D6A7;
        color: #1B5E20;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-error {
        background: #FFEBEE;
        border: 1px solid #FFCDD2;
        color: #B71C1C;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #888;
    }
    .empty-state i { font-size: 36px; color: #C5C0B5; display: block; margin-bottom: 10px; }

    /* Preview modal */
    .preview-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .preview-overlay.open { display: flex; }
    .preview-modal {
        background: #fff;
        border-radius: 12px;
        width: 90vw;
        max-width: 1100px;
        height: 85vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .preview-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        border-bottom: 1px solid #E5E1D8;
        font-family: 'Manrope', sans-serif;
        font-weight: 700;
        font-size: 15px;
    }
    .preview-modal-header button {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #888;
        line-height: 1;
    }
    .preview-modal-header button:hover { color: #1A1A1A; }
    .preview-iframe {
        flex: 1;
        border: none;
        width: 100%;
    }
</style>
@endpush

@section('content')
<a href="{{ route('audit-types.index') }}" class="back-link">
    <i class="ti ti-arrow-left"></i> Powrót do listy
</a>

<div class="page-header">
    <div class="page-header-title">
        {{ $auditType->name }}
        <span class="slug-badge">{{ $auditType->slug }}</span>
    </div>
    <div class="page-header-sub">Zarządzaj wersjami formularza HTML dla tego typu audytu</div>
</div>

@if(session('success'))
    <div class="alert-success"><i class="ti ti-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert-error"><i class="ti ti-alert-circle"></i> {{ session('error') }}</div>
@endif

{{-- Versions Table --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <i class="ti ti-versions" style="margin-right:8px;color:#1A4D3A;"></i>
            Wersje formularza
            <span style="font-size:12px;font-weight:500;color:#888;margin-left:8px;">({{ $auditType->versions->count() }})</span>
        </div>
    </div>

    @if($auditType->versions->isEmpty())
        <div class="empty-state">
            <i class="ti ti-file-off"></i>
            <p>Brak wersji. Dodaj pierwszą wersję poniżej.</p>
        </div>
    @else
        <table class="versions-table">
            <thead>
                <tr>
                    <th>Wersja</th>
                    <th>Data przesłania</th>
                    <th>Autor</th>
                    <th>Status</th>
                    <th style="text-align:right;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($auditType->versions as $version)
                    <tr>
                        <td>
                            <span style="font-family:'Lato',monospace;font-weight:700;font-size:14px;">
                                {{ $version->version_number }}
                            </span>
                        </td>
                        <td style="color:#555;font-size:13px;">
                            {{ $version->created_at->format('d.m.Y, H:i') }}
                        </td>
                        <td style="color:#555;font-size:13px;">
                            {{ $version->creator?->name ?? '—' }}
                        </td>
                        <td>
                            @if($version->is_current)
                                <span class="badge-current"><i class="ti ti-star-filled" style="font-size:10px;"></i> Aktualna</span>
                            @else
                                <span class="badge-draft"><i class="ti ti-file"></i> Archiwalna</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;justify-content:flex-end;gap:8px;">
                                @if(!$version->is_current)
                                    <form method="POST" action="{{ route('audit-types.versions.set-current', $version) }}">
                                        @csrf
                                        <button type="submit" class="btn-action btn-set-current" title="Ustaw jako aktualną">
                                            <i class="ti ti-star"></i> Ustaw jako aktualną
                                        </button>
                                    </form>
                                @endif
                                <button type="button"
                                    class="btn-action btn-preview"
                                    onclick="openPreview('{{ e($version->version_number) }}', {{ $version->id }})"
                                    title="Podgląd HTML">
                                    <i class="ti ti-eye"></i> Podgląd
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Upload Form --}}
<div class="upload-card">
    <div class="upload-card-title"><i class="ti ti-upload" style="margin-right:8px;color:#1A4D3A;"></i>Dodaj nową wersję</div>

    <form method="POST" action="{{ route('audit-types.versions.store', $auditType) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="version_number">Numer wersji <span style="color:#C62828;">*</span></label>
                <input type="text" id="version_number" name="version_number"
                    class="form-control @error('version_number') is-invalid @enderror"
                    placeholder="np. v1.4"
                    value="{{ old('version_number') }}"
                    required>
                @error('version_number')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="html_file">Plik HTML <span style="color:#C62828;">*</span></label>
                <input type="file" id="html_file" name="html_file"
                    class="form-control @error('html_file') is-invalid @enderror"
                    accept=".html,.htm"
                    required>
                @error('html_file')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <button type="submit" class="btn-submit">
            <i class="ti ti-upload"></i> Prześlij wersję
        </button>
    </form>
</div>

{{-- Preview Modal --}}
<div class="preview-overlay" id="previewOverlay" onclick="closePreviewOutside(event)">
    <div class="preview-modal">
        <div class="preview-modal-header">
            <span id="previewTitle">Podgląd HTML</span>
            <button onclick="closePreview()" title="Zamknij">&times;</button>
        </div>
        <iframe class="preview-iframe" id="previewFrame" src="" sandbox="allow-same-origin allow-scripts"></iframe>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openPreview(versionNumber, versionId) {
        document.getElementById('previewTitle').textContent = 'Podgląd: ' + versionNumber;
        document.getElementById('previewFrame').src = '{{ url('/audit-types/versions') }}/' + versionId + '/preview';
        document.getElementById('previewOverlay').classList.add('open');
    }

    function closePreview() {
        document.getElementById('previewOverlay').classList.remove('open');
        document.getElementById('previewFrame').src = '';
    }

    function closePreviewOutside(event) {
        if (event.target === document.getElementById('previewOverlay')) {
            closePreview();
        }
    }
</script>
@endpush
