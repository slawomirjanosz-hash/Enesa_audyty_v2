@once
<style>
.iso-presentations{margin:15px 0 20px;padding:15px;border:1px solid #dce6df;border-radius:11px;background:#f8faf8}.iso-presentations header h3{margin:0;font-size:15px;color:var(--green)}.iso-presentations header p{margin:5px 0 12px;color:#627068;font-size:13px}.iso-presentation-card{display:grid;grid-template-columns:180px minmax(0,1fr);align-items:end;gap:15px;background:#fff;border:1px solid #dfe5df;border-radius:10px;padding:12px;margin-top:12px}.iso-presentation-thumb{position:relative;display:block;width:180px;aspect-ratio:16/9;padding:0;border:0;overflow:hidden;background:#162d25;border-radius:8px;cursor:pointer}.iso-presentation-thumb img{width:100%;height:100%;object-fit:contain}.iso-presentation-thumb span{position:absolute;inset:0;display:grid;place-items:center;color:white;font-size:28px;background:#0003}.iso-presentation-info h4{margin:0 0 6px;font-size:15px}.iso-presentation-info p{font-size:13px;line-height:1.6;color:#617067;margin:0 0 7px;white-space:pre-line}.iso-presentation-info small,.iso-presentation-hint,.iso-presentation-empty{font-size:12px;color:#66736b}.iso-presentation-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.iso-presentation-button{display:inline-flex;align-items:center;justify-content:center;border:1px solid #c6d6cc;border-radius:7px;padding:9px 13px;background:var(--green);color:white;font:700 12px Manrope,Arial,sans-serif;cursor:pointer}.iso-presentation-button.secondary{background:#edf3ef;color:#204736}.iso-presentation-button.danger{background:#fff0ef;color:#a32c25;border-color:#f0d1cc}.iso-presentation-edit{grid-column:1/-1;margin-top:10px}.iso-presentation-edit summary{cursor:pointer;color:var(--green);font-size:13px;font-weight:700;padding:8px 0}.iso-presentation-edit form{background:#f5f8f5;padding:12px;border-radius:8px;margin-top:8px}.iso-presentation-edit label{display:block;font-size:13px;margin-bottom:10px}.iso-presentation-edit input,.iso-presentation-edit textarea{display:block;width:100%;box-sizing:border-box;border:1px solid #cdd6d0;border-radius:6px;padding:9px;margin-top:5px;background:white;font:inherit}.iso-presentation-edit [data-upload-status]{display:block;font-size:13px;margin-top:8px}
.iso-slide-dialog{width:min(840px,94vw);max-width:none;border:0;border-radius:12px;padding:0;background:#13251e;color:white;box-shadow:0 20px 60px #0006;margin:auto}.iso-slide-dialog::backdrop{background:#000b}.iso-slide-dialog.large{width:100vw;height:100dvh;max-height:none;border-radius:0}.iso-slide-viewer{background:#13251e;color:#fff}.iso-slide-dialog.large .iso-slide-viewer{display:flex;flex-direction:column;width:100%;height:100%;min-height:0}.iso-slide-viewer:fullscreen{width:100vw;height:100dvh}.iso-slide-dialog header,.iso-slide-dialog footer{flex-shrink:0}.iso-slide-dialog header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px}.iso-slide-dialog header strong{font-size:14px;overflow-wrap:anywhere}.iso-slide-dialog button{padding:9px 12px;border:1px solid #ffffff40;border-radius:7px;background:#ffffff18;color:white;cursor:pointer;font:700 12px Arial,sans-serif}.iso-slide-dialog button:focus-visible{outline:3px solid #fff;outline-offset:2px}.iso-slide-dialog button:disabled{opacity:.4;cursor:default}.iso-slide-stage{display:grid;place-items:center;background:#090f0c;min-height:160px}.iso-slide-stage img{display:block;width:100%;max-height:65dvh;object-fit:contain;user-select:none}.iso-slide-dialog.large .iso-slide-stage{flex:1;min-height:0;overflow:hidden}.iso-slide-dialog.large .iso-slide-stage img{height:100%;max-height:100%;min-height:0}.iso-slide-dialog footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px}.iso-slide-error{padding:20px;color:#ffc2bc;text-align:center}.iso-slide-dialog .iso-slide-error[hidden]{display:none}@media(max-width:600px){.iso-presentation-card{grid-template-columns:1fr}.iso-presentation-thumb{width:100%}.iso-slide-dialog header{flex-wrap:wrap}.iso-slide-dialog header strong{width:100%}}
</style>
<dialog class="iso-slide-dialog" data-slide-dialog aria-labelledby="iso-slide-dialog-title">
    <div class="iso-slide-viewer" data-slide-viewer><header><strong id="iso-slide-dialog-title"></strong><div><button type="button" data-slide-size aria-pressed="false">Pełny ekran</button> <button type="button" data-slide-close aria-label="Zamknij prezentację">Zamknij ×</button></div></header>
    <div class="iso-slide-stage"><img data-slide-image alt="Slajd prezentacji" draggable="false"><p class="iso-slide-error" data-slide-error hidden>Nie udało się wczytać slajdu. Sprawdź połączenie lub zaloguj się ponownie.</p></div>
    <footer><button type="button" data-slide-prev>← Poprzedni</button><span data-slide-counter aria-live="polite"></span><button type="button" data-slide-next>Następny →</button></footer></div>
</dialog>
<script>
(() => {
    const dialog = document.querySelector('[data-slide-dialog]');
    document.body.appendChild(dialog);
    const viewer = dialog.querySelector('[data-slide-viewer]'), sizeButton = dialog.querySelector('[data-slide-size]');
    const image = dialog.querySelector('[data-slide-image]'), error = dialog.querySelector('[data-slide-error]');
    const previous = dialog.querySelector('[data-slide-prev]'), next = dialog.querySelector('[data-slide-next]');
    let base = '', current = 1, count = 1, opener, wasFullscreen = false;
    const size = large => {dialog.classList.toggle('large', large);sizeButton.textContent = large ? 'Małe okno' : 'Pełny ekran';sizeButton.setAttribute('aria-pressed', String(large));};
    const shrink = async () => {
        if (document.fullscreenElement === viewer) await document.exitFullscreen();
        size(false);
    };
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
        size(false);show(1);dialog.showModal();
    });
    previous.addEventListener('click', () => show(current-1));next.addEventListener('click', () => show(current+1));
    sizeButton.addEventListener('click', async () => {
        if (dialog.classList.contains('large')) {await shrink();return;}
        size(true);
        if (viewer.requestFullscreen && document.fullscreenEnabled) {
            try {await viewer.requestFullscreen();}
            catch { /* Keep the full-window view if browser fullscreen is unavailable. */ }
        }
    });
    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement === viewer) {wasFullscreen = true;size(true);}
        else if (wasFullscreen) {wasFullscreen = false;size(false);}
    });
    dialog.querySelector('[data-slide-close]').addEventListener('click', async () => {await shrink();dialog.close();});
    dialog.addEventListener('cancel', event => {
        if (dialog.classList.contains('large')) {event.preventDefault();shrink();}
    });
    dialog.addEventListener('close', () => {shrink();image.removeAttribute('src');opener?.focus();});
    dialog.addEventListener('keydown', event => {if(event.key==='ArrowLeft'){event.preventDefault();show(current-1);}if(event.key==='ArrowRight'){event.preventDefault();show(current+1);}});
    document.querySelectorAll('[data-presentation-upload]').forEach(form => form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"],button:not([type])').disabled = true;
        form.querySelector('[data-upload-status]').textContent = 'Zapisywanie i przygotowanie slajdów… Nie zamykaj strony.';
    }));
})();
</script>
@endonce
