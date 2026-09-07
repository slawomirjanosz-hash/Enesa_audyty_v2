<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $share->folder->name }} — udostępnione dokumenty</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f1ea;color:#1d2822;font-family:Arial,sans-serif}.top{background:#1a4d3a;color:#fff;padding:18px 24px}.top-inner,.wrap{width:min(980px,calc(100% - 28px));margin:auto}.brand{display:flex;align-items:center;gap:12px;font-weight:800}.brand img{max-height:36px;max-width:150px}.wrap{padding:32px 0}.card{background:#fff;border:1px solid #e1ddd3;border-radius:14px;padding:22px;margin-bottom:16px;box-shadow:0 8px 30px rgba(25,55,42,.05)}h1{font-size:23px;margin:0 0 7px}.lead{margin:0;color:#66736b}.notice{padding:12px 14px;border-radius:8px;background:#ecfdf3;color:#166534;margin-bottom:16px}.upload{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:15px;background:#f5faf7;border:1px dashed #99b7a8;border-radius:10px}.btn{border:0;border-radius:8px;padding:10px 15px;background:#1a4d3a;color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex}.muted{color:#77827b;font-size:12px;margin-top:4px}.empty{text-align:center;color:#89928d;padding:28px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:12px 9px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap}th{font-size:12px;color:#657169}td:first-child{width:100%;white-space:normal}.file-name{font-weight:800;overflow-wrap:anywhere}.document-sort-button{display:inline-flex;align-items:center;gap:4px;padding:0;border:0;background:none;color:inherit;font:inherit;font-weight:800;cursor:pointer}.document-sort-button:after{content:'↕';color:#9aa39e}.document-sort-button[data-direction="asc"]:after{content:'↑';color:#1a4d3a}.document-sort-button[data-direction="desc"]:after{content:'↓';color:#1a4d3a}@media(max-width:650px){.card{padding:15px}th,td{padding:10px 7px}}
    </style>
</head>
<body>
<header class="top"><div class="top-inner brand">
    @if($companySettings)<img src="{{ $companySettings->logoUrl() }}" alt="Logo">@endif
    <span>{{ $companySettings?->name ?: config('app.name', 'Firma') }}</span>
</div></header>
<main class="wrap">
    @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
    <section class="card">
        <h1>{{ $share->folder->name }}</h1>
        <p class="lead">Udostępniony katalog projektu {{ $share->folder->project->name }}.</p>
        @if($share->expires_at)<p class="muted">Link jest ważny do {{ $share->expires_at->format('d.m.Y H:i') }}.</p>@endif
    </section>
    @if($share->allowsUpload())
    <section class="card">
        <h2 style="font-size:16px;margin-top:0">Dodaj plik</h2>
        <form class="upload" method="POST" enctype="multipart/form-data" action="{{ URL::signedRoute('public.project-documents.upload', $share) }}">
            @csrf
            <input type="file" name="files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
            <button class="btn">Wgraj pliki</button>
        </form>
        @error('file')<div style="color:#b91c1c;margin-top:8px;font-size:13px">{{ $message }}</div>@enderror
        <p class="muted">Jednorazowo do 20 plików. PDF, Word, Excel, obrazy lub ZIP, maksymalnie 20 MB na plik.</p>
    </section>
    @endif
    <section class="card">
        <h2 style="font-size:16px;margin-top:0">Dokumenty ({{ $share->folder->documents->count() }})</h2>
        @if($share->folder->documents->isNotEmpty())
        <div class="table-wrap"><table class="document-sortable-table">
            <thead><tr>
                <th><button type="button" class="document-sort-button" data-document-sort="name">Plik</button></th>
                <th><button type="button" class="document-sort-button" data-document-sort="size" data-sort-type="number">Rozmiar</button></th>
                <th><button type="button" class="document-sort-button" data-document-sort="uploader">Dodał</button></th>
                <th><button type="button" class="document-sort-button" data-document-sort="date" data-sort-type="number">Data</button></th>
                <th></th>
            </tr></thead>
            <tbody>@foreach($share->folder->documents as $document)
                @php($uploaderName = $document->uploader?->name ?? 'Uczestnik zewnętrzny')
                <tr data-document-name="{{ $document->original_filename }}" data-document-size="{{ $document->size }}" data-document-uploader="{{ $uploaderName }}" data-document-date="{{ $document->created_at->timestamp }}">
                    <td class="file-name">{{ $document->original_filename }}</td><td>{{ $document->formattedSize() }}</td><td>{{ $uploaderName }}</td><td>{{ $document->created_at->format('d.m.Y H:i') }}</td>
                    <td><a class="btn" href="{{ URL::signedRoute('public.project-documents.download', [$share, $document]) }}">Pobierz</a></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @else
            <div class="empty">Ten katalog jest jeszcze pusty.</div>
        @endif
    </section>
</main>
<script>
const collator = new Intl.Collator('pl', {numeric: true, sensitivity: 'base'});
document.querySelectorAll('[data-document-sort]').forEach(button => button.addEventListener('click', () => {
    const table = button.closest('table');
    const key = button.dataset.documentSort;
    const direction = button.dataset.direction === 'asc' ? 'desc' : 'asc';
    const numeric = button.dataset.sortType === 'number';
    table.querySelectorAll('[data-document-sort]').forEach(item => delete item.dataset.direction);
    button.dataset.direction = direction;
    [...table.tBodies[0].rows].sort((left, right) => {
        const property = 'document' + key.charAt(0).toUpperCase() + key.slice(1);
        const comparison = numeric
            ? Number(left.dataset[property]) - Number(right.dataset[property])
            : collator.compare(left.dataset[property] || '', right.dataset[property] || '');
        return direction === 'asc' ? comparison : -comparison;
    }).forEach(row => table.tBodies[0].appendChild(row));
}));
</script>
</body>
</html>
