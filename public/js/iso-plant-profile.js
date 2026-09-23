window.plantConditionVisible = (condition, form) => {
    const input = form?.elements.namedItem(`answers[${condition.key}][value]`);
    const value = input?.value;
    if (form?.elements.namedItem(`answers[${condition.key}][unknown]`)?.checked || value === undefined || value === '' || ['unknown','nie wiem'].includes(value)) return false;
    if (condition.operator === '>') return Number.isFinite(Number(value)) && Number(value) > Number(condition.values[0]);
    if (condition.operator === '!=') return !condition.values.includes(value);
    return condition.values.includes(value);
};
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('plant-form');
    const nav = document.querySelector('.plant-nav');
    if (nav) new ResizeObserver(() => document.documentElement.style.setProperty('--plant-nav-height', `${nav.offsetHeight + 16}px`)).observe(nav);
    let dirty = false;
    const changed = () => {
        dirty = true;
        const label = document.getElementById('plant-save-state');
        if (label) label.textContent = 'Masz niezapisane zmiany.';
    };
    const conditions = () => document.querySelectorAll('[data-condition]').forEach(element => {
        const condition = JSON.parse(element.dataset.condition);
        element.hidden = !window.plantConditionVisible(condition, form);
    });
    const factors = JSON.parse(document.getElementById('plant-energy-factors')?.textContent || '{}');
    const calculate = () => {
        const get = key => form?.elements.namedItem(`answers[${key}][value]`)?.value;
        const put = (key, value, help) => {
            const output = document.querySelector(`[data-auto-value="${key}"]`);
            if (output) output.textContent = value ?? 'Nie podano';
            const note = document.querySelector(`[data-auto-help="${key}"]`);
            if (note) note.textContent = help;
        };
        const rows = [...form.querySelectorAll('[data-question="FAKT_NOSNIKI"] [data-rows] .plant-repeat-row')];
        let total = 0, complete = rows.length > 0 && !form.elements.namedItem('answers[FAKT_NOSNIKI][unknown]')?.checked;
        rows.forEach(row => {
            const cell = key => row.querySelector(`[name$="[${key}]"]`)?.value;
            const f = factors[cell('c0')]?.[cell('c2')];
            if (f === undefined || cell('c1') === '' || !Number.isFinite(Number(cell('c1')))) complete = false;
            else total += Number(cell('c1')) * f;
        });
        put('ZUZYCIE_TJ', complete ? `${Math.round(total / 1000 * 1e6) / 1e6} TJ` : null, complete ? 'Suma wszystkich pozycji. Wartości opałowe orientacyjne — wymagają weryfikacji.' : 'Uzupełnij wszystkie nośniki, ilości i obsługiwane jednostki. Nie podajemy sumy częściowej.');
        const heat = get('FAKT_KOTLOWNIA');
        const fuels = [...form.querySelectorAll('[name="answers[FAKT_CIEPLO_PALIWO][value][]"]:checked')].map(input => input.value);
        let fossil = null;
        if (heat === 'brak') fossil = 'nie';
        else if (heat === 'siec') fossil = 'do potwierdzenia';
        else if (['wlasne','oba'].includes(heat)) fossil = fuels.some(v => ['gaz ziemny','olej','węgiel'].includes(v)) ? 'tak' : heat === 'oba' ? 'do potwierdzenia' : fuels.length ? 'nie' : null;
        put('FAKT_CIEPLO_PALIWA', fossil, fossil === 'do potwierdzenia' ? 'Potwierdź paliwo ciepła sieciowego u dostawcy.' : 'Obliczane na podstawie źródła ciepła i wybranych paliw.');
    };
    form?.addEventListener('input', event => {
        if (event.target.name?.includes('[value]')) {
            const unknown = event.target.closest('[data-question]')?.querySelector('input[name$="[unknown]"]');
            if (unknown) unknown.checked = false;
        }
        changed(); conditions(); calculate();
    });
    form?.addEventListener('change', event => {
        const multi = event.target.closest('.plant-multi');
        if (multi) {
            if (event.target.checked) multi.querySelectorAll('input').forEach(input => {
                if (input !== event.target && (event.target.hasAttribute('data-exclusive') || input.hasAttribute('data-exclusive'))) input.checked = false;
            });
            multi.querySelector('[data-selection-label]').textContent = [...multi.querySelectorAll('input:checked')].map(input => input.dataset.label).join(', ') || 'Wybierz odpowiedzi';
        }
        changed(); conditions(); calculate();
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) { event.preventDefault(); return; }
        if (button.hasAttribute('data-add-row')) {
            const repeat = button.closest('[data-repeat]');
            const index = Number(repeat.dataset.next);
            repeat.dataset.next = index + 1;
            repeat.querySelector('[data-rows]').insertAdjacentHTML('beforeend', repeat.querySelector('template').innerHTML.replaceAll('__INDEX__', String(index)));
            changed();
            calculate();
        }
        if (button.hasAttribute('data-remove-row') && window.confirm('Usunąć ten wiersz z edytowanej wersji?')) {
            button.closest('.plant-repeat-row').remove();
            changed();
            calculate();
        }
    });
    form?.addEventListener('submit', () => { dirty = false; });
    document.querySelectorAll('#approvals form').forEach(approvalForm => approvalForm.addEventListener('submit', event => {
        if (!dirty) return;
        event.preventDefault();
        window.alert('Najpierw zapisz zmienione odpowiedzi. Zatwierdzenie musi dotyczyć zapisanej treści ankiety.');
        document.getElementById('plant-save-state')?.scrollIntoView({block: 'center', behavior: 'smooth'});
    }));
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    conditions(); calculate();
});
