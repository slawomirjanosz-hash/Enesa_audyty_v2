@extends('layouts.app')

@section('page-title', 'Wszystkie dokumenty')

@push('styles')
<style>
.page-header { margin-bottom:20px; }
.page-header h1 { font-family:'Manrope',sans-serif; font-size:22px; font-weight:700; color:var(--green); margin:0; }
.documents-total-size { display:inline-flex;align-items:center;margin-left:8px;padding:4px 10px;border-radius:999px;background:#EDF4EF;color:#285740;font:700 12px 'Manrope',sans-serif;vertical-align:middle; }
.search-box { position:relative; }
.search-box input { font-size:12px; padding:6px 10px 6px 30px; border-radius:6px; border:1px solid #D0CCC0; outline:none; width:260px; font-family:'Lato',sans-serif; }
.search-box i { position:absolute; left:8px; top:50%; transform:translateY(-50%); color:#aaa; font-size:15px; }
.docs-table { width:100%; border-collapse:collapse; font-family:'Lato',sans-serif; font-size:13px; }
.docs-table th { padding:9px 14px; text-align:left; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #F0EDE6; background:#FAFAF6; font-family:'Manrope',sans-serif; }
.docs-table td { padding:12px 14px; border-bottom:1px solid #F7F5F0; color:#1A1A1A; vertical-align:middle; }
.docs-table tr:last-child td { border-bottom:none; }
.docs-table tr:hover td { background:#FAFAF6; }
.badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:700; font-family:'Manrope',sans-serif; }
.badge-blue { background:#DBEAFE; color:#1D4ED8; }
.badge-green { background:#DCFCE7; color:#166534; }
.badge-gray { background:#F3F4F6; color:#4B5563; }
.btn-icon { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; text-decoration:none; border:none; cursor:pointer; }

.folder-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:12px; }
.folder-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:#FAFAF6; cursor:pointer; user-select:none; transition:background .15s; }
.folder-header:hover { background:#F0EDE6; }
.folder-header-left { display:flex; align-items:center; gap:10px; }
.folder-header-left i.ti-folder { font-size:20px; color:#D97706; }
.folder-name { font-family:'Manrope',sans-serif; font-size:14px; font-weight:700; color:#1A1A1A; }
.folder-count { font-family:'Manrope',sans-serif; font-size:12px; color:#888; background:#F0EDE6; padding:2px 10px; border-radius:20px; }
.folder-chevron { font-size:16px; color:#aaa; transition:transform .2s; }
.folder-chevron.open { transform:rotate(180deg); }
.folder-body { display:none; }
.folder-body.open { display:block; }
</style>
@endpush

@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <h1><i class="ti ti-folder" style="margin-right:8px;"></i>Wszystkie dokumenty <span class="documents-total-size">{{ $totalSize }}</span></h1>
    <div class="search-box">
        <i class="ti ti-search"></i>
        <input type="text" id="search-docs" placeholder="Szukaj po firmie, nazwie pliku..." oninput="filterFolders(this.value)">
    </div>
</div>

@if(session('success'))
    <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#166534;border-radius:8px;padding:11px 16px;margin-bottom:14px;font-size:13px;">{{ session('success') }}</div>
@endif

@if($documents->isEmpty())
    <div style="background:#fff;border:1px solid #E5E1D8;border-radius:12px;padding:50px;text-align:center;color:#888;">
        <i class="ti ti-folder-off" style="font-size:40px;display:block;margin-bottom:10px;color:#D0CCC0;"></i>
        Brak dokumentów w systemie.
    </div>
@else
    @foreach($documents as $companyName => $companyDocs)
    <div class="folder-card" data-folder-name="{{ strtolower($companyName) }}">
        <div class="folder-header" onclick="toggleFolder(this)">
            <div class="folder-header-left">
                <i class="ti ti-folder"></i>
                <span class="folder-name">{{ $companyName }}</span>
                <span class="folder-count">{{ $companyDocs->count() }} {{ $companyDocs->count() === 1 ? 'dokument' : 'dokumentów' }} · {{ $folderSizes->get($companyName) }}</span>
            </div>
            <i class="ti ti-chevron-down folder-chevron"></i>
        </div>
        <div class="folder-body">
            <table class="docs-table">
                <thead>
                    <tr>
                        <th>Nazwa pliku</th>
                        <th>Typ</th>
                        <th>Rozmiar</th>
                        <th>Data zapisu</th>
                        <th>Dodał</th>
                        <th style="text-align:right;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($companyDocs as $doc)
                    @php
                        $typeLabel = match($doc->type) {
                            'offer_pdf' => 'Oferta PDF',
                            'audit_pdf' => 'Audyt PDF',
                            default => 'Plik',
                        };
                        $typeClass = match($doc->type) {
                            'offer_pdf' => 'badge-blue',
                            'audit_pdf' => 'badge-green',
                            default => 'badge-gray',
                        };
                    @endphp
                    <tr>
                        <td style="font-weight:600;">
                            {{ $doc->displayFilename() }}
                        </td>
                        <td><span class="badge {{ $typeClass }}">{{ $typeLabel }}</span></td>
                        <td style="color:#888;">{{ $doc->formattedSize() }}</td>
                        <td style="color:#7a8a80;">{{ $doc->updated_at->format('d.m.Y H:i') }}</td>
                        <td style="color:#888;">{{ $doc->uploader?->name ?? 'System' }}</td>
                        <td style="text-align:right;">
                            <a href="{{ route('documents.download', $doc) }}" class="btn-icon" style="background:#F0F7F3;color:var(--green);" title="Pobierz">
                                <i class="ti ti-download"></i>
                            </a>
                            @if($doc->offer_id)
                            <a href="{{ route('offers.show', $doc->offer_id) }}" class="btn-icon" style="background:#EFF6FF;color:#1D4ED8;" title="Otwórz ofertę">
                                <i class="ti ti-external-link"></i>
                            </a>
                            @endif
                            <form method="POST" action="{{ route('documents.destroy', $doc) }}" style="display:inline;" onsubmit="return confirm('Usunąć ten dokument?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon" style="background:#FEF2F2;color:#DC2626;" title="Usuń">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
@endif

@endsection

@push('scripts')
<script>
function toggleFolder(header) {
    const body = header.nextElementSibling;
    const chevron = header.querySelector('.folder-chevron');
    body.classList.toggle('open');
    chevron.classList.toggle('open');
}

function filterFolders(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.folder-card').forEach(card => {
        const folderName = card.dataset.folderName || '';
        const rows = card.querySelectorAll('tbody tr');
        let anyRowMatches = false;

        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const matches = !q || rowText.includes(q) || folderName.includes(q);
            row.style.display = matches ? '' : 'none';
            if (matches) anyRowMatches = true;
        });

        card.style.display = (!q || folderName.includes(q) || anyRowMatches) ? '' : 'none';

        if (q && anyRowMatches) {
            const body = card.querySelector('.folder-body');
            const chevron = card.querySelector('.folder-chevron');
            body.classList.add('open');
            chevron.classList.add('open');
        }
    });
}
</script>
@endpush
