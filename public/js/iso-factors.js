(() => {
    const form = document.getElementById('factor-form');
    if (!form) return;
    let dirty = false;
    let index = document.querySelectorAll('[data-custom-row]').length;
    function update() {
        let done = form.querySelector('[name="answers[FAKT_KLIMAT_ISTOTNY]"]').value && form.querySelector('[name="answers[climate_reason]"]').value.trim() ? 1 : 0;
        const decisions = form.querySelectorAll('[data-decision]');
        decisions.forEach(select => {
            const row = select.closest('[data-factor]');
            const reason = row.querySelector('[data-reason]');
            reason.classList.toggle('factor-hidden', select.value !== 'odrzucony');
            if (select.value === 'potwierdzony' && row.querySelector('details textarea').value.trim() || select.value === 'odrzucony' && reason.querySelector('textarea').value.trim()) done++;
        });
        document.getElementById('factor-progress').textContent = `Wypełnienie: ${Math.floor(100 * done / (decisions.length + 1))}% · ${done} / ${decisions.length + 1}`;
    }
    function changed() { dirty = true; document.getElementById('factor-save-state')?.replaceChildren('Masz niezapisane zmiany.'); update(); }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    document.querySelectorAll('[data-review-action]').forEach(action => action.addEventListener('submit', event => {
        if (dirty) { event.preventDefault(); alert('Najpierw zapisz zmiany w ankiecie.'); }
    }));
    document.getElementById('add-custom').addEventListener('click', () => {
        if (document.querySelectorAll('#custom-rows [data-custom-row]').length >= 20) return;
        const fragment = document.getElementById('custom-template').content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(input => input.name = input.name.replace('__INDEX__', String(index)));
        fragment.querySelector('input[type="hidden"]').value = crypto.randomUUID();
        index++;
        document.getElementById('custom-rows').append(fragment);
        changed();
    });
    form.addEventListener('click', event => { if (event.target.closest('[data-remove-custom]')) { event.target.closest('[data-custom-row]').remove(); changed(); } });
    update();
})();
