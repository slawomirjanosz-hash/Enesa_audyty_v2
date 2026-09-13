@php
    $sectionPresentations = ($trainingPresentations ?? collect())->get($sectionId, collect());
    $presentationManage = ($canManageTraining ?? false) && isset($auditType);
@endphp
<section class="iso-presentations" aria-label="Prezentacje szkoleniowe">
    <header><div><h3><i class="ti ti-presentation"></i> Prezentacje szkoleniowe</h3><p>Slajdy do punktu {{str_replace('-', '.', $sectionId)}}. Oglądaj w małym lub dużym oknie.</p></div></header>
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
            <div class="iso-presentation-info"><h4>{{$presentation->title}}</h4><p>{{$presentation->description}}</p><small>{{$presentation->slide_count}} slajdów · podgląd bez pobierania PPTX</small>
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
@once
<style>
.iso-presentations{margin:15px 0 20px;padding:15px;border:1px solid #dce6df;border-radius:11px;background:#f8faf8}.iso-presentations header h3{margin:0;font-size:15px;color:var(--green)}.iso-presentations header p{margin:5px 0 12px;color:#627068;font-size:13px}.iso-presentation-card{display:grid;grid-template-columns:180px minmax(0,1fr);align-items:center;gap:15px;background:#fff;border:1px solid #dfe5df;border-radius:10px;padding:12px;margin-top:12px}.iso-presentation-thumb{position:relative;display:block;width:180px;aspect-ratio:16/9;padding:0;border:0;overflow:hidden;background:#162d25;border-radius:8px;cursor:pointer}.iso-presentation-thumb img{width:100%;height:100%;object-fit:contain}.iso-presentation-thumb span{position:absolute;inset:0;display:grid;place-items:center;color:white;font-size:28px;background:#0003}.iso-presentation-info h4{margin:0 0 6px;font-size:15px}.iso-presentation-info p{font-size:13px;line-height:1.6;color:#617067;margin:0 0 7px;white-space:pre-line}.iso-presentation-info small,.iso-presentation-hint,.iso-presentation-empty{font-size:12px;color:#66736b}.iso-presentation-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.iso-presentation-button{display:inline-flex;align-items:center;justify-content:center;border:1px solid #c6d6cc;border-radius:7px;padding:9px 13px;background:var(--green);color:white;font:700 12px Manrope,Arial,sans-serif;cursor:pointer}.iso-presentation-button.secondary{background:#edf3ef;color:#204736}.iso-presentation-button.danger{background:#fff0ef;color:#a32c25;border-color:#f0d1cc}.iso-presentation-edit{grid-column:1/-1;margin-top:10px}.iso-presentation-edit summary{cursor:pointer;color:var(--green);font-size:13px;font-weight:700;padding:8px 0}.iso-presentation-edit form{background:#f5f8f5;padding:12px;border-radius:8px;margin-top:8px}.iso-presentation-edit label{display:block;font-size:13px;margin-bottom:10px}.iso-presentation-edit input,.iso-presentation-edit textarea{display:block;width:100%;box-sizing:border-box;border:1px solid #cdd6d0;border-radius:6px;padding:9px;margin-top:5px;background:white;font:inherit}.iso-presentation-edit [data-upload-status]{display:block;font-size:13px;margin-top:8px}
.iso-slide-dialog{width:min(840px,94vw);max-width:none;border:0;border-radius:12px;padding:0;background:#13251e;color:white;box-shadow:0 20px 60px #0006;margin:auto}.iso-slide-dialog::backdrop{background:#000b}.iso-slide-dialog.large{width:96vw;height:94dvh}.iso-slide-dialog header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px}.iso-slide-dialog header strong{font-size:14px;overflow-wrap:anywhere}.iso-slide-dialog button{padding:9px 12px;border:1px solid #ffffff40;border-radius:7px;background:#ffffff18;color:white;cursor:pointer;font:700 12px Arial,sans-serif}.iso-slide-dialog button:focus-visible{outline:3px solid #fff;outline-offset:2px}.iso-slide-dialog button:disabled{opacity:.4;cursor:default}.iso-slide-stage{display:grid;place-items:center;background:#090f0c;min-height:160px}.iso-slide-stage img{display:block;width:100%;max-height:65dvh;object-fit:contain;user-select:none}.iso-slide-dialog.large .iso-slide-stage{height:calc(94dvh - 130px)}.iso-slide-dialog.large .iso-slide-stage img{height:100%;max-height:100%;min-height:0}.iso-slide-dialog footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px}.iso-slide-error{padding:20px;color:#ffc2bc;text-align:center}.iso-slide-dialog .iso-slide-error[hidden]{display:none}@media(max-width:600px){.iso-presentation-card{grid-template-columns:1fr}.iso-presentation-thumb{width:100%}.iso-slide-dialog header{flex-wrap:wrap}.iso-slide-dialog header strong{width:100%}.iso-slide-dialog.large .iso-slide-stage{height:calc(94dvh - 175px)}}
</style>
<dialog class="iso-slide-dialog" data-slide-dialog aria-labelledby="iso-slide-dialog-title">
    <header><strong id="iso-slide-dialog-title"></strong><div><button type="button" data-slide-size>Duże okno</button> <button type="button" data-slide-close aria-label="Zamknij prezentację">Zamknij ×</button></div></header>
    <div class="iso-slide-stage"><img data-slide-image alt="Slajd prezentacji" draggable="false"><p class="iso-slide-error" data-slide-error hidden>Nie udało się wczytać slajdu. Sprawdź połączenie lub zaloguj się ponownie.</p></div>
    <footer><button type="button" data-slide-prev>← Poprzedni</button><span data-slide-counter aria-live="polite"></span><button type="button" data-slide-next>Następny →</button></footer>
</dialog>
<script>
(() => {
    const dialog = document.querySelector('[data-slide-dialog]');
    document.body.appendChild(dialog);
    const image = dialog.querySelector('[data-slide-image]'), error = dialog.querySelector('[data-slide-error]');
    const previous = dialog.querySelector('[data-slide-prev]'), next = dialog.querySelector('[data-slide-next]');
    let base = '', current = 1, count = 1, opener;
    const size = large => {dialog.classList.toggle('large', large);dialog.querySelector('[data-slide-size]').textContent = large ? 'Małe okno' : 'Duże okno';};
    const show = number => {
        current = Math.max(1, Math.min(number, count));error.hidden = true;image.hidden = false;
        image.alt = 'Slajd '+current+' z '+count;image.src = base + current;
        dialog.querySelector('[data-slide-counter]').textContent = current+' / '+count;
        previous.disabled = current === 1;next.disabled = current === count;
    };
    image.addEventListener('error', () => {image.hidden = true;error.hidden = false;});
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-presentation-open]');if (!trigger) return;
        const card = trigger.closest('[data-slide-base]');opener = trigger;base = card.dataset.slideBase;count = Number(card.dataset.slideCount);
        dialog.querySelector('#iso-slide-dialog-title').textContent = card.dataset.presentationTitle;
        size(trigger.hasAttribute('data-large'));show(1);dialog.showModal();
    });
    previous.addEventListener('click', () => show(current-1));next.addEventListener('click', () => show(current+1));
    dialog.querySelector('[data-slide-size]').addEventListener('click', () => size(!dialog.classList.contains('large')));
    dialog.querySelector('[data-slide-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {image.removeAttribute('src');opener?.focus();});
    dialog.addEventListener('keydown', event => {if(event.key==='ArrowLeft'){event.preventDefault();show(current-1);}if(event.key==='ArrowRight'){event.preventDefault();show(current+1);}});
    document.querySelectorAll('[data-presentation-upload]').forEach(form => form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"],button:not([type])').disabled = true;
        form.querySelector('[data-upload-status]').textContent = 'Zapisywanie i przygotowanie slajdów… Nie zamykaj strony.';
    }));
})();
</script>
@endonce
