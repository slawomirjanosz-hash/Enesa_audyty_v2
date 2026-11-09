(() => {
    const form = document.getElementById('expense-scan');
    const invoice = document.getElementById('expense-form');
    if (!form || !invoice) return;
    const status = document.getElementById('expense-scan-status');
    const buttons = [...form.querySelectorAll('[data-photo-pick]')];
    const manual = document.getElementById('expense-manual');
    const dirty = new Set();
    invoice.addEventListener('input', event => {if (event.isTrusted && event.target.name) dirty.add(event.target.name);});
    invoice.addEventListener('change', event => {if (event.isTrusted && event.target.name) dirty.add(event.target.name);});
    if (invoice.dataset.entryChoice === '1') invoice.classList.add('exp-awaiting-choice');
    const reveal = () => invoice.classList.remove('exp-awaiting-choice');
    manual.addEventListener('click', () => {reveal(); invoice.scrollIntoView({block:'start',behavior:'smooth'});});
    let pending = false;
    async function read(photo) {
        if (pending || !photo.files.length) return;
        const file = photo.files[0];
        if (file.size > 5 * 1024 * 1024) {status.textContent = 'Zdjęcie jest za duże. Maksymalnie 5 MB. Wybierz mniejsze zdjęcie lub wprowadź dane ręcznie.'; photo.value = ''; return;}
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {status.textContent = 'Wybierz zdjęcie JPG, PNG lub WebP.'; photo.value = ''; return;}
        pending = true;
        buttons.forEach(button => button.disabled = true);
        status.textContent = 'Odczytuję zdjęcie…';
        form.setAttribute('aria-busy', 'true');
        try {
            const body = new FormData(form);
            body.set('photo', file);
            const response = await fetch(form.action, {method:'POST',body,headers:{Accept:'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Nie udało się odczytać zdjęcia. Wpisz dane ręcznie.');
            const proposal = data.proposal || {};
            const fields = ['invoice_number','wz_number','purchase_date','supplier_name','supplier_nip'];
            if (['paid','unpaid','partial'].includes(proposal.payment_status) && typeof proposal.payment_evidence === 'string' && proposal.payment_evidence.trim()) fields.push('payment_status');
            const found = fields.filter(name => typeof proposal[name] === 'string' && proposal[name].trim());
            const conflicts = found.some(name => dirty.has(name) || (invoice.dataset.existing === '1' && invoice.elements.namedItem(name)?.value && invoice.elements.namedItem(name).value !== proposal[name]));
            if (found.length && (!conflicts || window.confirm('Odczytane dane zastąpią uzupełnione pola. Czy je wczytać?'))) {
                for (const name of found) {
                    const input = invoice.elements.namedItem(name);
                    if (input) {input.value = proposal[name]; input.dispatchEvent(new Event('input',{bubbles:true}));}
                }
            }
            reveal();
            status.textContent = (found.length ? 'Odczyt zakończony. Sprawdź dane poniżej i zapisz zakup.' : 'Nie znaleziono czytelnych danych. Uzupełnij formularz ręcznie.') + '\n' + (proposal.notes || '') + (fields.includes('payment_status') ? '\nInformacja o zapłacie na dokumencie: '+proposal.payment_evidence : '');
        } catch (error) {
            reveal();
            status.textContent = error.message === 'Failed to fetch' ? 'Nie potwierdzono odczytu. Nie ponawiamy go automatycznie. Wpisz dane ręcznie.' : error.message;
        } finally {
            photo.value = ''; pending = false;
            buttons.forEach(button => button.disabled = false);
            form.removeAttribute('aria-busy');
        }
    }
    buttons.forEach(button => {
        const input = document.getElementById(button.dataset.photoPick);
        button.addEventListener('click', () => input.click());
        input.addEventListener('change', () => read(input));
    });
    form.addEventListener('submit', event => event.preventDefault());
})();
