@php
    $trainingSections = collect($chapters)->flatMap(fn($chapter) => [$chapter, ...($chapter['items'] ?? [])]);
    $videosBySection = $trainingVideos->groupBy(fn($video) => $video->section_id ?: 'training');
@endphp
<style>
.iso-video-preview{position:relative;width:150px;aspect-ratio:16/9;border:0;border-radius:8px;overflow:hidden;padding:0;background:#17211c;cursor:pointer;display:block}.iso-video-preview img{width:100%;height:100%;object-fit:cover;display:block}.iso-video-preview span{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.16);color:#fff;font-size:31px;transition:background .15s}.iso-video-preview:hover span{background:rgba(0,0,0,.35)}.iso-video-dialog{position:fixed;inset:0;z-index:2500;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:20px}.iso-video-dialog[hidden]{display:none}.iso-video-modal-box{width:min(980px,100%);background:#101411;border-radius:13px;overflow:hidden;box-shadow:0 22px 70px rgba(0,0,0,.45)}.iso-video-modal-head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:13px 16px;color:#fff}.iso-video-modal-head strong{font-size:13px}.iso-video-modal-actions{display:flex;align-items:center;gap:8px}.iso-video-modal-actions a,.iso-video-modal-actions button{border:0;border-radius:7px;padding:8px 10px;text-decoration:none;font:800 11px Manrope;cursor:pointer}.iso-video-modal-actions a{background:#c5161d;color:#fff}.iso-video-modal-actions button{background:#28322d;color:#fff}.iso-video-player{width:100%;aspect-ratio:16/9;border:0;display:block;background:#000}
.iso-training-toolbar{display:flex;gap:10px;justify-content:space-between;align-items:center;margin:22px 0 12px}.iso-training-search{display:flex;align-items:center;gap:8px;border:1px solid #d9d6ce;border-radius:8px;padding:9px 11px;background:#fff;flex:1}.iso-training-search input{border:0;outline:0;width:100%;font:12px Manrope}.iso-training-add,.iso-training-cancel{border:0;border-radius:8px;padding:10px 13px;font:800 11px Manrope;cursor:pointer}.iso-training-add{background:var(--green);color:#fff}.iso-training-cancel{background:#edf1ed;color:#516158}.iso-video-form{padding:15px;border:1px solid #dfe5df;background:#fafbf8;border-radius:10px;margin-bottom:13px}.iso-video-form>div:not(.iso-video-form-actions){display:flex;flex-direction:column;gap:5px;margin-bottom:10px}.iso-video-form label{font-size:10px;font-weight:800;color:#59675f}.iso-video-form input,.iso-video-form textarea{border:1px solid #d8d5cd;border-radius:7px;padding:9px;font:12px Manrope}.iso-video-form>small{color:#718078}.iso-video-form-actions{display:flex;justify-content:flex-end;gap:7px;margin-top:10px}.iso-training-table-wrap{overflow:auto;border:1px solid #e5e1d8;border-radius:10px}.iso-training-table{width:100%;border-collapse:collapse}.iso-training-table th,.iso-training-table td{padding:11px;text-align:left;border-bottom:1px solid #eee;font-size:11px}.iso-training-table th{background:#f7f6f2;color:#647169;font-size:9px;text-transform:uppercase}.iso-training-table tr:last-child td{border-bottom:0}.iso-video-link{display:inline-flex;align-items:center;gap:5px;color:#c5161d;font-weight:800;text-decoration:none;white-space:nowrap}.iso-video-delete{border:0;background:#feecec;color:#b91c1c;border-radius:6px;padding:7px;cursor:pointer}.iso-video-empty{text-align:center;padding:25px;color:#78847d;font-size:12px}@media(max-width:650px){.iso-training-toolbar{align-items:stretch;flex-direction:column}.iso-training-add{justify-content:center}}
.iso-training-table th,.iso-training-table td{font-size:13px;vertical-align:middle}.iso-training-table th{font-size:11px}.iso-training-table .iso-training-number{width:80px;white-space:nowrap;font-weight:800;color:var(--green)}.iso-training-description{min-width:230px}.iso-training-description p{margin:5px 0 0;color:#647169;font-size:12px;line-height:1.6}.iso-training-media{width:120px;text-align:center!important}.iso-training-materials{display:flex;align-items:flex-start;justify-content:center;gap:12px;flex-wrap:wrap}.iso-training-material{display:flex;align-items:center;flex-direction:column;gap:6px;max-width:150px}.iso-training-material .iso-training-media-button{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;padding:0;border:1px solid #c6d6cc;border-radius:8px;background:#edf3ef;color:var(--green);cursor:pointer}.iso-training-media-button i{font-size:28px}.iso-training-media-button:hover{background:#dcebe2}.iso-training-media-button:focus-visible{outline:3px solid #6d9782;outline-offset:2px}.iso-training-material small,.iso-training-unavailable{font-size:11px;color:#718078}.iso-training-material small{text-align:center;overflow-wrap:anywhere}.iso-training-video-actions{display:flex;gap:5px}.iso-video-form select{border:1px solid #d8d5cd;border-radius:7px;padding:9px;background:white;font:12px Manrope}.iso-video-dialog{display:block;width:min(980px,calc(100vw - 32px));height:fit-content;max-height:calc(100dvh - 32px);max-width:none;margin:auto;padding:0;border:0;border-radius:13px;background:#101411;overflow:auto}.iso-video-dialog:not([open]){display:none}.iso-video-dialog::backdrop{background:rgba(0,0,0,.72)}.iso-video-player{max-height:calc(100dvh - 130px)}@media(max-width:650px){.iso-training-media{width:90px}.iso-training-description{min-width:200px}.iso-video-modal-head{flex-wrap:wrap}.iso-video-modal-head strong{width:100%}}
.iso-training-materials .iso-training-media-button{position:relative;width:128px;height:72px;padding:0;overflow:hidden;background:#10251d;border-radius:7px}.iso-training-media-button img{display:block;width:100%;height:100%;object-fit:cover}.iso-training-media-button>span{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:white;font-size:25px;text-shadow:0 1px 5px #000}.iso-training-media{min-width:150px}</style>
<div class="iso-training-toolbar">
    <label class="iso-training-search"><i class="ti ti-search"></i><input type="search" data-iso-video-search aria-label="Szukaj materiałów szkoleniowych" placeholder="Szukaj po temacie, numerze punktu lub opisie…"></label>
    @if($canManageTraining ?? false)<button type="button" class="iso-training-add" onclick="document.getElementById('iso-video-form').hidden=false;this.hidden=true"><i class="ti ti-plus"></i> Dodaj film</button>@endif
</div>
@if($canManageTraining ?? false)
<form id="iso-video-form" class="iso-video-form" method="POST" action="{{ route('audit-types.training-videos.store', $auditType) }}" data-store-action="{{ route('audit-types.training-videos.store', $auditType) }}" hidden>
    @csrf
    <input type="hidden" name="_method" value="POST" data-video-method>
    <div><label for="iso-video-section">Punkt audytu</label><select id="iso-video-section" name="section_id"><option value="">Materiały ogólne</option>@foreach($trainingSections as $trainingSection)<option value="{{ $trainingSection['id'] }}" @selected(old('section_id')===$trainingSection['id'])>{{ $trainingSection['number'] }} — {{ $trainingSection['title'] }}</option>@endforeach</select></div>
    <div><label>Temat szkolenia</label><input name="topic" value="{{ old('topic') }}" required maxlength="255"></div>
    <div><label>Krótki opis</label><textarea name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea></div>
    <div><label>Link do filmu na YouTube</label><input name="youtube_url" type="url" value="{{ old('youtube_url') }}" placeholder="https://www.youtube.com/watch?v=…" required></div>
    <small>Najpierw opublikuj film na YouTube, a następnie wklej tutaj jego adres.</small>
    <div class="iso-video-form-actions"><button type="button" class="iso-training-cancel" data-video-form-cancel>Anuluj</button><button class="iso-training-add" data-video-submit>Zapisz film</button></div>
</form>
@endif
<div class="iso-training-table-wrap">
<table class="iso-training-table" aria-label="Materiały szkoleniowe według punktów ISO 50001">
    <thead><tr><th data-sort-type="text">Nr punktu</th><th>Opis punktu</th><th class="iso-training-media" data-sortable="false">Prezentacja</th><th class="iso-training-media" data-sortable="false">Film</th></tr></thead>
    <tbody data-iso-video-rows>
    @foreach($trainingSections as $trainingSection)
        @php
            $rowPresentations = ($trainingPresentations ?? collect())->get($trainingSection['id'], collect());
            $rowVideos = $videosBySection->get($trainingSection['id'], collect());
            $searchText = implode(' ', [$trainingSection['number'], $trainingSection['title'], $trainingSection['description'], $rowPresentations->pluck('title')->implode(' '), $rowPresentations->pluck('description')->implode(' '), $rowVideos->pluck('topic')->implode(' '), $rowVideos->pluck('description')->implode(' '), $rowVideos->pluck('youtube_url')->implode(' ')]);
        @endphp
        <tr data-training-section="{{ $trainingSection['id'] }}" data-search="{{ Str::lower($searchText) }}">
            <td class="iso-training-number" data-sort-value="{{ $trainingSection['number'] }}">{{ $trainingSection['number'] }}</td>
            <td class="iso-training-description" data-sort-value="{{ $trainingSection['title'] }}"><strong>{{ $trainingSection['title'] }}</strong><p>{{ $trainingSection['description'] }}</p></td>
            <td class="iso-training-media"><div class="iso-training-materials">
                @forelse($rowPresentations as $presentation)
                    @php
                        $slideUrl = isset($audit)
                            ? route(($clientView ?? false) ? 'client.audits.presentations.slide' : 'audits.presentations.slide', [$audit, $presentation, 1])
                            : route('audit-types.presentations.slide', [$auditType, $presentation, 1]);
                    @endphp
                    <div class="iso-training-material" data-slide-base="{{ substr($slideUrl, 0, -1) }}" data-slide-count="{{ $presentation->slide_count }}" data-presentation-title="{{ $presentation->title }}">
                        <button type="button" class="iso-training-media-button" data-presentation-open title="{{ $presentation->title }} — {{ $presentation->slide_count }} slajdów" aria-label="Otwórz prezentację: {{ $presentation->title }}"><img src="{{ $slideUrl }}" loading="lazy" alt="Pierwszy slajd: {{ $presentation->title }}"><span><i class="ti ti-player-play-filled" aria-hidden="true"></i></span></button>
                        
                    </div>
                @empty<span class="iso-training-unavailable">Brak materiału</span>@endforelse
            </div></td>
            <td class="iso-training-media"><div class="iso-training-materials">
                @forelse($rowVideos as $video)
                    <div class="iso-training-material">
                        @if($video->youtubeEmbedUrl())
                            <button type="button" class="iso-training-media-button" data-video-preview data-embed="{{ $video->youtubeEmbedUrl() }}" data-youtube="{{ $video->youtube_url }}" data-title="{{ $video->topic }}" title="{{ $video->topic }}{{ $video->description ? ' — '.$video->description : '' }}" aria-label="Odtwórz: {{ $video->topic }}"><img src="{{ $video->youtubeThumbnailUrl() }}" loading="lazy" alt="Miniatura filmu: {{ $video->topic }}"><span><i class="ti ti-player-play-filled" aria-hidden="true"></i></span></button>
                        @else<a class="iso-video-link" href="{{ $video->youtube_url }}" target="_blank" rel="noopener">Otwórz w YouTube</a>@endif
                        
                        @if($canManageTraining ?? false)<div class="iso-training-video-actions"><button type="button" class="iso-video-delete" style="background:#e8f1ff;color:#1d4ed8" data-video-edit data-action="{{ route('audit-types.training-videos.update', [$auditType, $video]) }}" data-section="{{ $video->section_id }}" data-topic="{{ $video->topic }}" data-description="{{ $video->description }}" data-url="{{ $video->youtube_url }}" aria-label="Edytuj film: {{ $video->topic }}"><i class="ti ti-edit"></i></button><form method="POST" action="{{ route('audit-types.training-videos.destroy', [$auditType, $video]) }}" onsubmit="return confirm('Usunąć ten film z listy?')">@csrf @method('DELETE')<button class="iso-video-delete" aria-label="Usuń film: {{ $video->topic }}"><i class="ti ti-trash"></i></button></form></div>@endif
                    </div>
                @empty<span class="iso-training-unavailable">Brak materiału</span>@endforelse
            </div></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
<div class="iso-video-empty" data-no-video-results hidden>Brak materiałów pasujących do wyszukiwania.</div>
@include('audit-types.partials.iso50001-presentation-viewer')
@if($canManageTraining ?? false)<details class="iso-presentation-edit"><summary>Zarządzaj prezentacjami punktu 2</summary>@include('audit-types.partials.iso50001-presentations',['sectionId'=>'training','sectionLabel'=>'2 Filmy szkoleniowe'])</details>@endif
<dialog class="iso-video-dialog" data-iso-video-modal aria-labelledby="iso-video-modal-title"><div class="iso-video-modal-box"><div class="iso-video-modal-head"><strong id="iso-video-modal-title">Film szkoleniowy</strong><div class="iso-video-modal-actions"><a data-video-youtube href="#" target="_blank" rel="noopener"><i class="ti ti-brand-youtube"></i> Otwórz w YouTube</a><button type="button" data-video-close><i class="ti ti-x"></i> Zamknij</button></div></div><iframe class="iso-video-player" data-video-player src="about:blank" title="Odtwarzacz filmu szkoleniowego" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div></dialog>
<script>
document.querySelector('[data-iso-video-search]')?.addEventListener('input', event => {
    const query = event.target.value.toLocaleLowerCase('pl').trim();
    const rows = [...document.querySelectorAll('[data-iso-video-rows] tr[data-search]')];
    rows.forEach(row => row.hidden = !row.dataset.search.toLocaleLowerCase('pl').includes(query));
    const noResults = document.querySelector('[data-no-video-results]');
    if (noResults) noResults.hidden = rows.length === 0 || rows.some(row => !row.hidden);
});
const videoForm = document.getElementById('iso-video-form');
const resetVideoForm = () => {
    if (!videoForm) return;
    videoForm.reset();
    videoForm.action = videoForm.dataset.storeAction;
    videoForm.querySelector('[data-video-method]').value = 'POST';
    videoForm.querySelector('[data-video-submit]').textContent = 'Zapisz film';
    videoForm.hidden = true;
    document.querySelector('.iso-training-add')?.removeAttribute('hidden');
};
document.querySelectorAll('[data-video-edit]').forEach(button => button.addEventListener('click', () => {
    videoForm.action = button.dataset.action;
    videoForm.querySelector('[data-video-method]').value = 'PUT';
    videoForm.elements.section_id.value = button.dataset.section || '';
    videoForm.elements.topic.value = button.dataset.topic;
    videoForm.elements.description.value = button.dataset.description;
    videoForm.elements.youtube_url.value = button.dataset.url;
    videoForm.querySelector('[data-video-submit]').textContent = 'Zapisz zmiany';
    videoForm.hidden = false;
    document.querySelector('.iso-training-add')?.setAttribute('hidden', '');
    videoForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
}));
videoForm?.querySelector('[data-video-form-cancel]')?.addEventListener('click', resetVideoForm);
const videoModal = document.querySelector('[data-iso-video-modal]');
document.body.appendChild(videoModal);
let videoOpener;
const closeVideoModal = () => {
    if (!videoModal) return;
    videoModal.close();
};
document.querySelectorAll('[data-video-preview]').forEach(button => button.addEventListener('click', () => {
    if (!videoModal) return;
    videoOpener = button;
    videoModal.querySelector('[data-video-player]').src = button.dataset.embed + '?autoplay=1&rel=0';
    videoModal.querySelector('[data-video-youtube]').href = button.dataset.youtube;
    videoModal.querySelector('#iso-video-modal-title').textContent = button.dataset.title;
    videoModal.showModal();
}));
videoModal?.addEventListener('close', () => {videoModal.querySelector('[data-video-player]').src = 'about:blank';videoOpener?.focus();});
videoModal?.querySelector('[data-video-close]')?.addEventListener('click', closeVideoModal);
videoModal?.addEventListener('click', event => { if (event.target === videoModal) closeVideoModal(); });
</script>
