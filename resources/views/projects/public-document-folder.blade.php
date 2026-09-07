<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $share->folder->name }} — udostępnione dokumenty</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f1ea;color:#1d2822;font-family:Arial,sans-serif}.top{background:#1a4d3a;color:#fff;padding:18px 24px}.top-inner,.wrap{width:min(980px,calc(100% - 28px));margin:auto}.brand{display:flex;align-items:center;gap:12px;font-weight:800}.brand img{max-height:36px;max-width:150px}.wrap{padding:32px 0}.card{background:#fff;border:1px solid #e1ddd3;border-radius:14px;padding:22px;margin-bottom:16px;box-shadow:0 8px 30px rgba(25,55,42,.05)}h1{font-size:23px;margin:0 0 7px}.lead{margin:0;color:#66736b}.notice{padding:12px 14px;border-radius:8px;background:#ecfdf3;color:#166534;margin-bottom:16px}.upload{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:15px;background:#f5faf7;border:1px dashed #99b7a8;border-radius:10px}.btn{border:0;border-radius:8px;padding:10px 15px;background:#1a4d3a;color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex}.file{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:16px;padding:13px 4px;border-bottom:1px solid #eee}.file:last-child{border-bottom:0}.file-name{font-weight:800;overflow-wrap:anywhere}.muted{color:#77827b;font-size:12px;margin-top:4px}.empty{text-align:center;color:#89928d;padding:28px}@media(max-width:650px){.file{grid-template-columns:1fr}.file .btn{justify-self:start}}
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
        @forelse($share->folder->documents as $document)
            <div class="file">
                <div><div class="file-name">{{ $document->original_filename }}</div><div class="muted">{{ $document->formattedSize() }} · {{ $document->created_at->format('d.m.Y H:i') }}</div></div>
                <span class="muted">{{ $document->uploader?->name ?? 'Uczestnik zewnętrzny' }}</span>
                <a class="btn" href="{{ URL::signedRoute('public.project-documents.download', [$share, $document]) }}">Pobierz</a>
            </div>
        @empty
            <div class="empty">Ten katalog jest jeszcze pusty.</div>
        @endforelse
    </section>
</main>
</body>
</html>
