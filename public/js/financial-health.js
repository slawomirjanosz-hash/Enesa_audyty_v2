const panel = document.getElementById('financial-health');
if (panel) {
    const form = panel.closest('form');
    let timer, pending, revision = 0;
    const preview = async () => {
        const current = ++revision;
        pending?.abort();
        pending = new AbortController();
        const body = new FormData();
        body.set('_token', form.querySelector('[name="_token"]').value);
        form.querySelectorAll('input[name^="finances["]').forEach(input => {
            if (!input.name.endsWith('[source]')) body.set(input.name, input.value);
        });
        try {
            const response = await fetch(panel.dataset.previewUrl, {method: 'POST', body, signal: pending.signal, headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('preview');
            const result = await response.json();
            if (current !== revision) return;
            panel.innerHTML = result.html;
            form.querySelectorAll('[data-financial-state]').forEach(input => {
                const match = input.name.match(/^finances\[(\d+)\]\[(\w+)\]$/);
                input.dataset.financialState = result.health.periods[match[1]]?.fields[match[2]] || 'clear';
            });
        } catch (error) {
            if (error.name !== 'AbortError' && current === revision) {
                panel.textContent = 'Nie można teraz przeliczyć oceny. Sprawdź format kwot. Ocena zostanie ponownie obliczona przy zapisie raportu.';
                form.querySelectorAll('[data-financial-state]').forEach(input => { input.dataset.financialState = 'unknown'; });
            }
        }
    };
    form.querySelectorAll('input[name^="finances["]').forEach(input => input.addEventListener('input', () => {
        ++revision;
        pending?.abort();
        clearTimeout(timer);
        panel.textContent = 'Przeliczanie oceny finansowej…';
        timer = setTimeout(preview, 500);
    }));
    preview();
}
