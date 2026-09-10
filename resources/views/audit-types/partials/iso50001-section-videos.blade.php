@php($sectionVideos = $trainingVideos->where('section_id', $sectionId))
<section class="iso-section-training">
    <div class="iso-section-training-head"><div><h3><i class="ti ti-video"></i> Film szkoleniowy</h3><p>Materiały wyjaśniające wymagania punktu {{ str_replace('-', '.', $sectionId) }} i sposób przygotowania dokumentacji.</p></div>@if($canManageTraining ?? false)<button type="button" class="iso-training-add" data-section-video-add>Dodaj film</button>@endif</div>
    @if($canManageTraining ?? false)
    <form class="iso-video-form iso-section-video-form" method="POST" action="{{ route('audit-types.training-videos.store', $auditType) }}" hidden>@csrf
        <input type="hidden" name="section_id" value="{{ $sectionId }}">
        <div><label>Temat filmu</label><input name="topic" required maxlength="255" placeholder="np. Jak wykonać analizę kontekstu"></div>
        <div><label>Krótki opis – czego dotyczy film</label><textarea name="description" rows="2" maxlength="1000" required></textarea></div>
        <div><label>Link do filmu na YouTube</label><input name="youtube_url" type="url" required placeholder="https://www.youtube.com/watch?v=…"></div>
        <div class="iso-video-form-actions"><button type="button" class="iso-training-cancel" data-section-video-cancel>Anuluj</button><button class="iso-training-add">Zapisz film</button></div>
    </form>
    @endif
    <div class="iso-section-video-list">
        @forelse($sectionVideos as $video)<article class="iso-section-video-card">
            @if($video->youtubeEmbedUrl())<button type="button" class="iso-video-preview" data-section-video-preview data-embed="{{ $video->youtubeEmbedUrl() }}" data-youtube="{{ $video->youtube_url }}" data-title="{{ $video->topic }}"><img src="{{ $video->youtubeThumbnailUrl() }}" alt="Miniatura filmu: {{ $video->topic }}" loading="lazy"><span><i class="ti ti-player-play-filled"></i></span></button>@endif
            <div><h4>{{ $video->topic }}</h4><p>{{ $video->description }}</p><a href="{{ $video->youtube_url }}" target="_blank" rel="noopener"><i class="ti ti-brand-youtube"></i> Otwórz w YouTube</a></div>
            @if($canManageTraining ?? false)<form method="POST" action="{{ route('audit-types.training-videos.destroy', [$auditType, $video]) }}" onsubmit="return confirm('Usunąć ten film?')">@csrf @method('DELETE')<button class="iso-video-delete" aria-label="Usuń film"><i class="ti ti-trash"></i></button></form>@endif
        </article>@empty<div class="iso-section-video-empty">Film dla tego punktu nie został jeszcze dodany.</div>@endforelse
    </div>
</section>
@once
<style>
.iso-section-training{margin:15px 0 18px;padding:15px;border:1px solid #dce6df;border-radius:11px;background:#f8faf8}.iso-section-training-head{display:flex;justify-content:space-between;gap:14px;align-items:center}.iso-section-training-head h3{margin:0;color:#244c3c;font-size:15px}.iso-section-training-head p{margin:4px 0 0;color:#66736b;font-size:13px}.iso-section-video-form{margin-top:13px}.iso-section-video-list{display:grid;gap:10px;margin-top:12px}.iso-section-video-card{display:grid;grid-template-columns:180px minmax(0,1fr) auto;gap:14px;align-items:center;background:#fff;border:1px solid #e1e5e1;border-radius:9px;padding:11px}.iso-section-video-card .iso-video-preview{width:180px}.iso-section-video-card h4{margin:0 0 5px;font-size:14px}.iso-section-video-card p{margin:0 0 7px;color:#627068;font-size:13px;line-height:1.5}.iso-section-video-card a{color:#c5161d;font-size:12px;font-weight:800;text-decoration:none}.iso-section-video-empty{color:#7b8780;font-size:13px;padding:10px 0}.iso-section-video-modal{position:fixed;inset:0;z-index:2600;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:20px}.iso-section-video-modal[hidden]{display:none}.iso-section-video-modal>div{width:min(980px,100%);background:#101411;border-radius:12px;overflow:hidden}.iso-section-video-modal header{display:flex;justify-content:space-between;align-items:center;padding:12px 15px;color:#fff}.iso-section-video-modal header div{display:flex;gap:8px}.iso-section-video-modal a,.iso-section-video-modal button{border:0;border-radius:6px;padding:7px 9px;font:800 11px Manrope;text-decoration:none;cursor:pointer}.iso-section-video-modal a{background:#c5161d;color:#fff}.iso-section-video-modal button{background:#303a35;color:#fff}.iso-section-video-modal iframe{display:block;width:100%;aspect-ratio:16/9;border:0;background:#000}@media(max-width:700px){.iso-section-video-card{grid-template-columns:1fr}.iso-section-video-card .iso-video-preview{width:100%}}
</style>
<div class="iso-section-video-modal" data-section-video-modal hidden><div><header><strong data-section-video-title>Film szkoleniowy</strong><div><a data-section-video-youtube target="_blank" rel="noopener">YouTube</a><button type="button" data-section-video-close>Zamknij</button></div></header><iframe data-section-video-player src="about:blank" title="Film szkoleniowy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div></div>
<script>
document.querySelector('[data-section-video-add]')?.addEventListener('click',event=>{event.currentTarget.hidden=true;document.querySelector('.iso-section-video-form').hidden=false});
document.querySelector('[data-section-video-cancel]')?.addEventListener('click',()=>{document.querySelector('.iso-section-video-form').hidden=true;document.querySelector('[data-section-video-add]').hidden=false});
const sectionVideoModal=document.querySelector('[data-section-video-modal]');
document.querySelectorAll('[data-section-video-preview]').forEach(button=>button.addEventListener('click',()=>{sectionVideoModal.querySelector('[data-section-video-player]').src=button.dataset.embed+'?autoplay=1&rel=0';sectionVideoModal.querySelector('[data-section-video-youtube]').href=button.dataset.youtube;sectionVideoModal.querySelector('[data-section-video-title]').textContent=button.dataset.title;sectionVideoModal.hidden=false}));
const closeSectionVideo=()=>{if(!sectionVideoModal)return;sectionVideoModal.hidden=true;sectionVideoModal.querySelector('[data-section-video-player]').src='about:blank'};
sectionVideoModal?.querySelector('[data-section-video-close]')?.addEventListener('click',closeSectionVideo);
sectionVideoModal?.addEventListener('click',event=>{if(event.target===sectionVideoModal)closeSectionVideo()});
</script>
@endonce
