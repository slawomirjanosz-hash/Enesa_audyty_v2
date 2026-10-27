(() => {
    const form = document.getElementById('activity-log-search');
    if (!form) return;
    const results = document.getElementById('activity-log-results');
    const status = document.getElementById('activity-search-status');
    let timer, controller, generation = 0;
    const search = async () => {
        const current = ++generation;
        controller?.abort();
        controller = new AbortController();
        const url = new URL(form.action);
        for (const [key, value] of new FormData(form)) url.searchParams.set(key, value);
        status.textContent = 'Wyszukuję w historii…';
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal: controller.signal, headers: {'X-Activity-Results': '1'}});
            if (!response.ok || response.redirected) throw new Error();
            const html = await response.text();
            if (current !== generation) return;
            window.history.replaceState(null, '', url);
            results.innerHTML = html;
            status.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) status.textContent = 'Nie udało się odświeżyć historii. Spróbuj ponownie przyciskiem Szukaj.';
        } finally {
            if (current === generation) results.removeAttribute('aria-busy');
        }
    };
    form.elements.namedItem('search').addEventListener('input', () => {
        clearTimeout(timer);
        generation++;
        controller?.abort();
        timer = setTimeout(search, 300);
    });
    form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); search(); });
})();
