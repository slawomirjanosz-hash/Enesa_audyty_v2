document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('system-form');
    if (!form) return;
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    document.querySelectorAll('[data-review-action]').forEach(action => action.addEventListener('submit', event => {
        if (dirty) { event.preventDefault(); alert('Najpierw zapisz zmienione odpowiedzi.'); }
    }));
    const name = key => 'answers[' + key.split('.').join('][') + ']';
    const values = key => [...form.querySelectorAll('[name]')].filter(el => el.name === name(key) || el.name === name(key) + '[]').filter(el => el.type !== 'hidden' && (el.type !== 'checkbox' || el.checked)).map(el => el.value.trim()).filter(Boolean);
    function update() {
        let total = 0, done = 0;
        form.querySelectorAll('[data-system-field]').forEach(el => {
            const condition = JSON.parse(el.dataset.when || 'null');
            const visible = !condition || condition[1].includes(values(condition[0])[0] || '');
            el.hidden = !visible;
            if (visible && el.dataset.required === '1') {
                total++;
                if (values(el.dataset.key).length) done++;
            }
        });
        ['GRANICE', 'UPRAW'].forEach(root => {
            const container = form.querySelector(`[data-repeater="${root}"]`);
            if (container && !container.querySelector('.system-row')) total++;
        });
        const indicator = document.querySelector('[data-system-progress]');
        indicator.textContent = `Wypełnienie: ${total ? Math.floor(100 * done / total) : 0}% · ${done} / ${total}`;
        const energy = form.querySelector('[data-energy-fields]');
        if (energy) {
            const ready = [...form.querySelectorAll('[data-repeater="GRANICE"] .system-row')].some(row => ['nazwa','adres','fizyczna'].every(key => [...row.querySelectorAll('[name]')].some(el => el.name.endsWith(`[${key}]`) && el.value.trim())));
            energy.hidden = !ready;
            form.querySelector('[data-energy-locked]').hidden = ready;
        }
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    let next = Math.max(0, ...[...form.querySelectorAll('[data-key]')].map(el => Number(el.dataset.key.split('.')[1]) || 0)) + 1;
    form.addEventListener('click', event => {
        const add = event.target.closest('[data-add-row]');
        if (add) {
            const root = add.dataset.addRow;
            const host = form.querySelector(`[data-repeater="${root}"]`);
            if (host.children.length >= 20) { alert('Maksymalnie 20 pozycji.'); return; }
            host.insertAdjacentHTML('beforeend', document.getElementById('template-' + root).innerHTML.replaceAll('__INDEX__', String(next++)));
            dirty = true;
            update();
        }
        const remove = event.target.closest('[data-remove-row]');
        if (remove && confirm('Usunąć tę pozycję z formularza? Zmiana zostanie utrwalona po zapisaniu.')) { remove.closest('.system-row').remove(); dirty = true; update(); }
    });
    document.getElementById('system-example')?.addEventListener('click', () => {
        if (!confirm('Uzupełnić puste pola klienta przykładowymi danymi? Sprawdź je przed zapisaniem.')) return;
        for (let round = 0; round < 2; round++) {
            form.querySelectorAll('[data-system-field]').forEach(field => {
                if (field.hidden || values(field.dataset.key).length) return;
                field.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(el => {
                    if (el.type === 'checkbox') el.checked = true;
                    else if (el.tagName === 'SELECT') el.selectedIndex = Math.min(1, el.options.length - 1);
                    else if (el.type === 'date') el.value = new Date().toISOString().slice(0, 10);
                    else el.value = 'PRZYKŁAD — do weryfikacji: ' + field.querySelector('strong').textContent;
                });
            });
            update();
        }
        form.dispatchEvent(new Event('input', {bubbles:true}));
    });
    update();
});
