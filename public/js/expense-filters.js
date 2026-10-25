(() => {
    const form = document.getElementById('expense-filters');
    if (!form) return;
    const results = document.getElementById('expense-results');
    const status = document.getElementById('expense-filter-status');
    let timer, request, generation = 0;
    const search = async () => {
        const current = ++generation;
        request?.abort();
        request = new AbortController();
        const url = new URL(window.location.href);
        for (const [key, value] of new FormData(form)) url.searchParams.set(key, value);
        url.searchParams.delete('page');
        status.textContent = 'Wyszukuję…';
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal: request.signal, headers: {'X-Expense-Results': '1'}});
            if (!response.ok || response.redirected) throw new Error();
            const html = await response.text();
            if (current !== generation) return;
            window.history.replaceState(null, '', url);
            results.innerHTML = html;
            status.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) status.textContent = 'Nie udało się odświeżyć listy. Spróbuj ponownie przyciskiem Filtruj.';
        } finally {
            if (current === generation) results.removeAttribute('aria-busy');
        }
    };
    const schedule = () => {
        clearTimeout(timer);
        generation++;
        request?.abort();
        timer = setTimeout(search, 300);
    };
    form.elements.namedItem('search').addEventListener('input', schedule);
    form.elements.namedItem('month').addEventListener('change', schedule);
    form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); search(); });
    document.getElementById('expense-extra-columns').addEventListener('change', event => {
        results.classList.toggle('exp-show-extra', event.target.checked);
    });
})();
