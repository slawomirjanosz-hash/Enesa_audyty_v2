export function convertAmount(value, rate, source) {
    if (value === '' || rate === '') return '';
    const amount = Number(String(value).replace(',', '.'));
    const factor = 1 + Number(rate) / 100;
    if (!Number.isFinite(amount) || amount < 0 || !Number.isFinite(factor) || factor <= 0) return '';
    return (Math.round((source === 'net_amount' ? amount * factor : amount / factor) * 100 + 1e-7) / 100).toFixed(2);
}

if (typeof document !== 'undefined') {
    const form = document.getElementById('expense-form');
    if (form) {
        const field = name => form.elements.namedItem(name);
        const project = field('project_id');
        const manualCost = form.querySelector('[data-expense-manual-cost]');
        const updateCost = () => { manualCost.hidden = Boolean(project.value); };
        project.addEventListener('change', updateCost);
        updateCost();
        const steps = [...form.querySelectorAll('[data-expense-step]')];
        const back = form.querySelector('[data-expense-back]');
        const next = form.querySelector('[data-expense-next]');
        const progress = form.querySelector('[data-expense-progress]');
        const save = form.querySelector('button[type="submit"]');
        let current = null;
        const show = index => {
            current = index;
            steps.forEach((step, i) => { step.hidden = index !== null && index !== i; });
            if (!next) return;
            back.hidden = index === null || index === 0;
            next.hidden = index === null || index === steps.length - 1;
            save.hidden = !next.hidden;
            progress.textContent = index === null ? '' : `Krok ${index + 1} z ${steps.length}`;
            if (index !== null) steps[index].querySelector('input, select, textarea')?.focus();
        };
        const advance = () => {
            if (current === null) return;
            const invalid = [...steps[current].querySelectorAll('input, select, textarea')].find(control => !control.validity.valid);
            if (invalid) return invalid.reportValidity();
            if (current < steps.length - 1) show(current + 1);
        };
        document.getElementById('expense-manual')?.addEventListener('click', () => show(0));
        form.addEventListener('expense-photo-ready', () => show(null));
        back?.addEventListener('click', () => show(current - 1));
        next?.addEventListener('click', advance);
        form.addEventListener('keydown', event => {
            if (current === null || event.key !== 'Enter' || event.target.tagName !== 'INPUT' || current === steps.length - 1) return;
            event.preventDefault();
            advance();
        });
        form.addEventListener('invalid', event => {
            const index = steps.indexOf(event.target.closest('[data-expense-step]'));
            if (current !== null && index >= 0 && steps[index].hidden) show(index);
        }, true);
        const mode = field('owner_mode');
        const updateOwner = () => {
            form.querySelector('[data-owner-user]').hidden = mode.value !== 'user';
            form.querySelector('[data-owner-manual]').hidden = mode.value !== 'manual';
            field('owner_id').disabled = mode.value !== 'user';
            field('owner_name').disabled = mode.value !== 'manual';
        };
        if (mode) { mode.addEventListener('change', updateOwner); updateOwner(); }
    }
}
