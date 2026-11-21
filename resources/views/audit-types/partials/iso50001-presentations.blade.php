@php
    $sectionPresentations = ($trainingPresentations ?? collect())->get($sectionId, collect());
    $presentationManage = ($canManageTraining ?? false) && isset($auditType);
@endphp
<section class="iso-presentations" aria-label="Prezentacje szkoleniowe">
    <header class="iso-point-title"><h3><i class="ti ti-presentation" aria-hidden="true"></i> Prezentacje szkoleniowe</h3><x-iso-help label="Informacje o prezentacjach szkoleniowych">Slajdy do sekcji {{$sectionLabel ?? str_replace('-', '.', $sectionId)}}. Oglądaj w małym lub dużym oknie.</x-iso-help></header>
    @if($presentationManage)
        <details class="iso-presentation-edit"><summary>+ Dodaj prezentację PowerPoint</summary>
            <form method="POST" enctype="multipart/form-data" action="{{route('audit-types.presentations.store', $auditType)}}" data-presentation-upload>
                @csrf<input type="hidden" name="section_id" value="{{$sectionId}}">
                <label>Tytuł prezentacji<input name="title" required maxlength="255" placeholder="np. Kontekst organizacji — część A"></label>
                <label>Krótki opis<textarea name="description" rows="2" maxlength="2000"></textarea></label>
                <label>Plik PowerPoint (.pptx)<input type="file" name="presentation_file" accept=".pptx" required></label>
                <p class="iso-presentation-hint">Do 20 MB i 60 slajdów. Statyczny podgląd bez animacji, notatek i osadzonych filmów. Usuń zewnętrzne linki przed wgraniem. Przygotowanie może potrwać około minuty. Zachowaj oryginał u siebie — system przechowuje tylko obrazy slajdów.</p>
                <button type="submit" class="iso-presentation-button">Dodaj prezentację</button><span role="status" data-upload-status></span>
            </form>
        </details>
    @endif
    @forelse($sectionPresentations as $presentation)
        @php
            $presentationSlideUrl = isset($audit)
                ? route(($clientView ?? false) ? 'client.audits.presentations.slide' : 'audits.presentations.slide', [$audit, $presentation, 1])
                : route('audit-types.presentations.slide', [$auditType, $presentation, 1]);
        @endphp
        <article class="iso-presentation-card" data-slide-base="{{rtrim($presentationSlideUrl, '1')}}" data-slide-count="{{$presentation->slide_count}}" data-presentation-title="{{$presentation->title}}">
            <button type="button" class="iso-presentation-thumb" data-presentation-open aria-label="Otwórz prezentację: {{$presentation->title}}"><img src="{{$presentationSlideUrl}}" loading="lazy" alt="Pierwszy slajd: {{$presentation->title}}" draggable="false"><span><i class="ti ti-player-play-filled"></i></span></button>
            <div class="iso-presentation-info"><h4>{{$presentation->title}}</h4><p>{{$presentation->displayDescription()}}</p><small>{{$presentation->slide_count}} slajdów</small>
                <div class="iso-presentation-actions"><button type="button" class="iso-presentation-button" data-presentation-open>Otwórz prezentację</button><button type="button" class="iso-presentation-button secondary" data-presentation-open data-large>Duże okno</button></div>
            </div>
            @if($presentationManage)
                <details class="iso-presentation-edit"><summary>Edytuj lub podmień</summary>
                    <form method="POST" enctype="multipart/form-data" action="{{route('audit-types.presentations.update', [$auditType, $presentation])}}" data-presentation-upload>@csrf @method('PUT')
                        <input type="hidden" name="section_id" value="{{$sectionId}}">
                        <label>Tytuł<input name="title" required maxlength="255" value="{{$presentation->title}}"></label>
                        <label>Opis<textarea name="description" maxlength="2000" rows="2">{{$presentation->description}}</textarea></label>
                        <label>Nowy PPTX (opcjonalnie)<input type="file" name="presentation_file" accept=".pptx"></label>
                        <button class="iso-presentation-button">Zapisz zmiany</button><span role="status" data-upload-status></span>
                    </form>
                    <form method="POST" action="{{route('audit-types.presentations.destroy', [$auditType, $presentation])}}" onsubmit="return confirm('Usunąć tę prezentację i wszystkie jej slajdy?')">@csrf @method('DELETE')<button class="iso-presentation-button danger">Usuń prezentację</button></form>
                </details>
            @endif
        </article>
    @empty
        <p class="iso-presentation-empty">Prezentacja dla tego punktu nie została jeszcze dodana.</p>
    @endforelse
</section>
@include('audit-types.partials.iso50001-presentation-viewer')
