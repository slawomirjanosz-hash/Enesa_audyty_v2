<section class="cyl-card" id="cylinder-videos">
    <h2>Filmy butli</h2>
    @if(request('inspection'))<p>Filmy wpisu #{{ request('inspection') }} · <a href="{{ route($routePrefix.'show', $cylinder) }}#cylinder-videos">Pokaż wszystkie</a></p>@endif
    <div class="cyl-videos">
    @forelse($videos as $video)
        <article>
            <h3>{{ $video->title }}</h3>
            <p class="cyl-muted">{{ $video->cylinder_inspection_id ? 'Wpis #'.$video->cylinder_inspection_id : 'Film ogólny butli' }}</p>
            @if($video->external_url)
                @php($player = $video->externalPlayer())
                @if($player)
                    <p class="cyl-muted">Źródło: {{ $player['provider'] }}</p>
                    @if($player['embed'])
                        <div class="cyl-external-player">
                            <button type="button" class="cyl-btn cyl-btn-primary" data-video-embed="{{ $player['embed'] }}" data-video-title="{{ $video->title }}">▶ Odtwórz tutaj</button>
                        </div>
                    @endif
                    <p><a class="cyl-btn" href="{{ $video->external_url }}" target="_blank" rel="noopener noreferrer">Otwórz film u źródła ↗</a></p>
                    <p class="cyl-muted">{{ $player['embed'] ? 'Jeśli podgląd nie działa, otwórz film u źródła. Serwis może wymagać logowania lub zgody właściciela.' : 'Ten serwis otwieramy w nowej karcie — dostęp do filmu zależy od jego ustawień udostępniania.' }}</p>
                @else
                    <p>Link wymaga sprawdzenia przez inspektora.</p>
                @endif
            @else
                <video controls playsinline preload="none" aria-label="{{ $video->title }}"><source src="{{ route($routePrefix.'videos.show', [$cylinder, $video]) }}" type="{{ $video->mime_type }}">Twoja przeglądarka nie obsługuje tego filmu.</video>
            @endif
            <p class="cyl-muted">{{ $video->created_at->format('d.m.Y H:i') }} · {{ $video->external_url ? 'Link zewnętrzny — bez zajmowania miejsca' : \App\Models\Document::formatBytes($video->size) }}</p>
        </article>
    @empty
        <p class="cyl-muted">Nie dodano jeszcze filmów.</p>
    @endforelse
    </div>
    {{ $videos->links() }}
    @if($canManage && !$cylinder->archived_at)
    <details id="cylinder-video-add" @if($videoInspection || $errors->hasAny(['file','external_url','source','title','cylinder_inspection_id'])) open @endif>
        <summary class="cyl-btn">+ Dodaj film</summary>
        <form id="cylinder-video-form" method="post" enctype="multipart/form-data" action="{{ route('cylinders.videos.store', $cylinder) }}" style="margin-top:16px">
            @csrf
            <div class="cyl-grid">
                <div class="cyl-wide"><label for="video-inspection">Powiązany wpis przeglądu</label><select id="video-inspection" name="cylinder_inspection_id"><option value="">Film ogólny butli (bez wpisu)</option>@foreach($inspections as $entry)<option value="{{ $entry->id }}" @selected(old('cylinder_inspection_id', $videoInspection?->id) == $entry->id)>Wpis #{{ $entry->id }} · {{ $entry->inspected_at->format('d.m.Y') }} · {{ $entry->inspector_name }}</option>@endforeach @if($videoInspection && !$inspections->getCollection()->contains('id', $videoInspection->id))<option value="{{ $videoInspection->id }}" selected>Wpis #{{ $videoInspection->id }}</option>@endif</select></div>
                <div><label for="video-title">Tytuł filmu</label><input id="video-title" name="title" maxlength="160" required value="{{ old('title') }}"></div>
                <div><label for="video-source">Źródło filmu</label><select id="video-source" name="source"><option value="file" @selected(old('source','file') === 'file')>Plik z dysku</option><option value="link" @selected(old('source') === 'link')>Link — YouTube, Dysk Google lub inny serwis</option></select></div>
                <div class="cyl-wide" data-video-source="file"><label for="video-file">Plik MP4 lub WebM (do 100 MB)</label><input id="video-file" type="file" name="file" accept="video/mp4,video/webm,.mp4,.webm"><p class="cyl-muted">Prywatny plik, dostępny uprawnionym inspektorom i klientowi tej butli. Obciąża Twój limit dokumentów. Zalecany MP4 H.264/AAC.</p></div>
                <div class="cyl-wide" data-video-source="link"><label for="video-url">Link HTTPS do filmu</label><input id="video-url" name="external_url" type="url" maxlength="2048" placeholder="https://…" value="{{ old('external_url') }}"><p class="cyl-muted">Wklej link, nie kod HTML. YouTube i Dysk Google obsługują podgląd tutaj; inne serwisy otwieramy w nowej karcie. Link nie zajmuje miejsca w systemie.</p><p class="cyl-notice">Udostępnij film właściwym odbiorcom w serwisie źródłowym. Uprawnienia naszej aplikacji nie chronią zewnętrznego linku poza systemem. Nie upubliczniaj poufnych nagrań; możesz wgrać je prywatnie z dysku.</p></div>
            </div>
            <button class="cyl-btn cyl-btn-primary">Dodaj film</button>
        </form>
    </details>
    @endif
</section>
<style>.cyl-external-player{aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;background:#f1f4f3;border-radius:8px}.cyl-external-player iframe{width:100%;height:100%;border:0;border-radius:8px}</style>
<script>
(() => {
    const form = document.getElementById('cylinder-video-form');
    if (form) {
        const source = form.querySelector('[name="source"]');
        const update = () => form.querySelectorAll('[data-video-source]').forEach(section => {
            const active = section.dataset.videoSource === source.value;
            section.hidden = !active;
            section.querySelectorAll('input').forEach(input => { input.disabled = !active; input.required = active; });
        });
        source.addEventListener('change', update);
        update();
    }
    document.querySelectorAll('[data-video-embed]').forEach(button => button.addEventListener('click', () => {
        const frame = document.createElement('iframe');
        frame.src = button.dataset.videoEmbed;
        frame.title = button.dataset.videoTitle;
        frame.allow = 'fullscreen; encrypted-media; picture-in-picture';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        button.parentElement.replaceChildren(frame);
    }));
})();
</script>
