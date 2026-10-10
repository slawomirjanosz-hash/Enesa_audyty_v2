(() => {
    const dialog = document.getElementById('cylinder-media-dialog');
    if (!dialog) return;
    const content = dialog.querySelector('[data-inspection-content]');
    let pending;
    document.addEventListener('click', async event => {
        const link = event.target.closest('[data-inspection-media]');
        if (!link) return;
        event.preventDefault();
        pending?.abort();
        const request = pending = new AbortController();
        content.textContent = 'Ładowanie załączników…';
        if (!dialog.open) dialog.showModal();
        try {
            const response = await fetch(link.href, {signal: request.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok || response.redirected) throw new Error('Niedostępny podgląd');
            const html = await response.text();
            if (!request.signal.aborted) content.innerHTML = html;
        } catch (error) {
            if (error.name !== 'AbortError') content.textContent = 'Nie udało się otworzyć załączników. Odśwież stronę i spróbuj ponownie.';
        }
    });
    dialog.querySelector('[data-inspection-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending?.abort(); content.replaceChildren(); });
    dialog.addEventListener('click', event => {
        const link = event.target.closest('[data-inspection-image]');
        if (!link) return;
        event.preventDefault();
        const large = content.querySelector('[data-inspection-large]');
        large.src = link.href;
        large.hidden = false;
    });
})();
