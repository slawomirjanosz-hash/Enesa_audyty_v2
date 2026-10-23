(() => {
    const form = document.getElementById('expense-scan');
    if (!form) return;
    const status = document.getElementById('expense-scan-status');
    let pending = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (pending) return;
        const photo = document.getElementById('expense-photo');
        if (!photo.files.length) return;
        if (photo.files[0].size > 5 * 1024 * 1024) { status.textContent = 'Zdjęcie jest za duże. Maksymalnie 5 MB.'; return; }
        pending = true;
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        status.textContent = 'Odczytuję zdjęcie…';
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {Accept: 'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Nie udało się odczytać zdjęcia. Wpisz dane ręcznie.');
            const proposal = data.proposal || {};
            const fields = ['invoice_number', 'wz_number', 'purchase_date', 'supplier_name', 'supplier_nip'];
            const found = fields.filter(name => typeof proposal[name] === 'string' && proposal[name].trim());
            if (found.length && window.confirm('Wczytać odczytane dane do formularza? Zastąpią zawartość odpowiednich pól. Sprawdź je przed zapisaniem.')) {
                for (const name of found) {
                    const input = document.querySelector(`#expense-form [name="${name}"]`);
                    if (input) { input.value = proposal[name]; input.dispatchEvent(new Event('input', {bubbles: true})); }
                }
            }
            status.textContent = `${data.notice || ''}\n${proposal.notes || ''}${found.length ? '' : '\nNie znaleziono czytelnych danych. Uzupełnij formularz ręcznie.'}`;
        } catch (error) {
            status.textContent = error.message === 'Failed to fetch' ? 'Nie potwierdzono odczytu. Nie ponawiamy go automatycznie. Wpisz dane ręcznie.' : error.message;
        } finally {
            photo.value = '';
            pending = false;
            button.disabled = false;
        }
    });
})();
