document.querySelectorAll('[data-cylinder-register]').forEach(table => {
    const rows = () => [...table.querySelectorAll('[data-cylinder-select]')];
    const all = table.querySelector('[data-cylinder-select-all]');
    const summary = document.querySelector('[data-cylinder-selection]');
    const update = () => {
        const inputs = rows();
        const count = inputs.filter(input => input.checked).length;
        if (all) {
            all.checked = inputs.length > 0 && count === inputs.length;
            all.indeterminate = count > 0 && count < inputs.length;
            all.disabled = inputs.length === 0;
        }
        if (summary) {
            summary.hidden = count === 0;
            summary.querySelector('[data-cylinder-count]').textContent = count;
        }
    };
    all?.addEventListener('change', () => { rows().forEach(input => { input.checked = all.checked; }); update(); });
    rows().forEach(input => input.addEventListener('change', update));
    document.querySelector('[data-cylinder-clear]')?.addEventListener('click', () => {
        rows().forEach(input => { input.checked = false; });
        update();
    });
    table.querySelectorAll('[data-cylinder-url]').forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.closest('a,button,input,label,[aria-disabled]') || window.getSelection()?.toString()) return;
            window.location.assign(row.dataset.cylinderUrl);
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' && event.target === row) window.location.assign(row.dataset.cylinderUrl);
        });
    });
    update();
});
