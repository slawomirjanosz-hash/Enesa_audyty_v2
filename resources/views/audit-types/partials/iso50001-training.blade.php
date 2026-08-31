<style>
.iso-training-toolbar{display:flex;gap:10px;justify-content:space-between;align-items:center;margin:22px 0 12px}.iso-training-search{display:flex;align-items:center;gap:8px;border:1px solid #d9d6ce;border-radius:8px;padding:9px 11px;background:#fff;flex:1}.iso-training-search input{border:0;outline:0;width:100%;font:12px Manrope}.iso-training-add,.iso-training-cancel{border:0;border-radius:8px;padding:10px 13px;font:800 11px Manrope;cursor:pointer}.iso-training-add{background:var(--green);color:#fff}.iso-training-cancel{background:#edf1ed;color:#516158}.iso-video-form{padding:15px;border:1px solid #dfe5df;background:#fafbf8;border-radius:10px;margin-bottom:13px}.iso-video-form>div:not(.iso-video-form-actions){display:flex;flex-direction:column;gap:5px;margin-bottom:10px}.iso-video-form label{font-size:10px;font-weight:800;color:#59675f}.iso-video-form input,.iso-video-form textarea{border:1px solid #d8d5cd;border-radius:7px;padding:9px;font:12px Manrope}.iso-video-form>small{color:#718078}.iso-video-form-actions{display:flex;justify-content:flex-end;gap:7px;margin-top:10px}.iso-training-table-wrap{overflow:auto;border:1px solid #e5e1d8;border-radius:10px}.iso-training-table{width:100%;border-collapse:collapse}.iso-training-table th,.iso-training-table td{padding:11px;text-align:left;border-bottom:1px solid #eee;font-size:11px}.iso-training-table th{background:#f7f6f2;color:#647169;font-size:9px;text-transform:uppercase}.iso-training-table tr:last-child td{border-bottom:0}.iso-video-link{display:inline-flex;align-items:center;gap:5px;color:#c5161d;font-weight:800;text-decoration:none;white-space:nowrap}.iso-video-delete{border:0;background:#feecec;color:#b91c1c;border-radius:6px;padding:7px;cursor:pointer}.iso-video-empty{text-align:center;padding:25px;color:#78847d;font-size:12px}@media(max-width:650px){.iso-training-toolbar{align-items:stretch;flex-direction:column}.iso-training-add{justify-content:center}}
</style>
<div class="iso-training-toolbar">
    <label class="iso-training-search"><i class="ti ti-search"></i><input type="search" data-iso-video-search placeholder="Szukaj po temacie, opisie lub adresie filmu…"></label>
    @if($canManageTraining ?? false)<button type="button" class="iso-training-add" onclick="document.getElementById('iso-video-form').hidden=false;this.hidden=true"><i class="ti ti-plus"></i> Dodaj film</button>@endif
</div>
@if($canManageTraining ?? false)
<form id="iso-video-form" class="iso-video-form" method="POST" action="{{ route('audit-types.training-videos.store', $auditType) }}" hidden>
    @csrf
    <div><label>Temat szkolenia</label><input name="topic" value="{{ old('topic') }}" required maxlength="255"></div>
    <div><label>Krótki opis</label><textarea name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea></div>
    <div><label>Link do filmu na YouTube</label><input name="youtube_url" type="url" value="{{ old('youtube_url') }}" placeholder="https://www.youtube.com/watch?v=…" required></div>
    <small>Najpierw opublikuj film na YouTube, a następnie wklej tutaj jego adres.</small>
    <div class="iso-video-form-actions"><button type="button" class="iso-training-cancel" onclick="this.closest('form').hidden=true;document.querySelector('.iso-training-add').hidden=false">Anuluj</button><button class="iso-training-add">Zapisz film</button></div>
</form>
@endif
<div class="iso-training-table-wrap">
<table class="iso-training-table">
    <thead><tr><th>LP.</th><th>Temat szkolenia</th><th>Krótki opis</th><th>Film</th>@if($canManageTraining ?? false)<th>Akcje</th>@endif</tr></thead>
    <tbody data-iso-video-rows>
    @forelse($trainingVideos as $video)
        <tr data-search="{{ Str::lower($video->topic.' '.$video->description.' '.$video->youtube_url) }}">
            <td>{{ $loop->iteration }}</td><td><strong>{{ $video->topic }}</strong></td><td>{{ $video->description ?: '—' }}</td>
            <td><a class="iso-video-link" href="{{ $video->youtube_url }}" target="_blank" rel="noopener"><i class="ti ti-brand-youtube"></i> Otwórz film</a></td>
            @if($canManageTraining ?? false)<td><form method="POST" action="{{ route('audit-types.training-videos.destroy', [$auditType, $video]) }}" onsubmit="return confirm('Usunąć ten film z listy?')">@csrf @method('DELETE')<button class="iso-video-delete" aria-label="Usuń film"><i class="ti ti-trash"></i></button></form></td>@endif
        </tr>
    @empty
        <tr data-empty-row><td colspan="{{ ($canManageTraining ?? false) ? 5 : 4 }}" class="iso-video-empty">Nie dodano jeszcze żadnych filmów szkoleniowych.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="iso-video-empty" data-no-video-results hidden>Brak filmów pasujących do wyszukiwania.</div>
<script>
document.querySelector('[data-iso-video-search]')?.addEventListener('input', event => {
    const query = event.target.value.toLocaleLowerCase('pl').trim();
    const rows = [...document.querySelectorAll('[data-iso-video-rows] tr[data-search]')];
    rows.forEach(row => row.hidden = !row.dataset.search.toLocaleLowerCase('pl').includes(query));
    const noResults = document.querySelector('[data-no-video-results]');
    if (noResults) noResults.hidden = rows.length === 0 || rows.some(row => !row.hidden);
});
</script>
