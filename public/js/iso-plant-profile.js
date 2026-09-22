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
        const field = form?.elements.namedItem(`answers[${condition.key}][value]`);
        element.hidden = !condition.values.includes(field?.value);
    });
    form?.addEventListener('input', event => {
        if (event.target.name?.includes('[value]')) {
            const unknown = event.target.closest('[data-question]')?.querySelector('input[name$="[unknown]"]');
            if (unknown) unknown.checked = false;
        }
        changed();
    });
    form?.addEventListener('change', event => {
        const multi = event.target.closest('.plant-multi');
        if (multi) {
            if (event.target.checked) multi.querySelectorAll('input').forEach(input => {
                if (input !== event.target && (event.target.hasAttribute('data-exclusive') || input.hasAttribute('data-exclusive'))) input.checked = false;
            });
            multi.querySelector('[data-selection-label]').textContent = [...multi.querySelectorAll('input:checked')].map(input => input.dataset.label).join(', ') || 'Wybierz odpowiedzi';
        }
        changed(); conditions();
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
        }
        if (button.hasAttribute('data-remove-row') && window.confirm('Usunąć ten wiersz z edytowanej wersji?')) {
            button.closest('.plant-repeat-row').remove();
            changed();
        }
    });
    form?.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    conditions();
});
