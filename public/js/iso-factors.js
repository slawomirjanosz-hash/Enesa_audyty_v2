(() => {
    const form = document.getElementById('factor-form');
    if (!form) return;
    let dirty = false;
    let swotDirty = false;
    const swotForm = document.querySelector('[data-swot-form]');
    let conclusionIndex = 0;
    document.getElementById('add-swot-conclusion')?.addEventListener('click', () => {
        const host = document.getElementById('swot-conclusions');
        if (host.querySelectorAll('[data-swot-conclusion]').length >= 20) return;
        conclusionIndex = Math.max(conclusionIndex, ...[...host.querySelectorAll('[name]')].map(el => Number(el.name.match(/\[(\d+)\]/)?.[1] ?? 0))) + 1;
        const fragment = document.getElementById('template-swot-conclusions').content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(el => el.name = el.name.replace('__INDEX__', String(conclusionIndex)));
        host.append(fragment); swotDirty = true;
    });
    swotForm?.addEventListener('click', event => { if (event.target.closest('[data-remove-conclusion]')) { event.target.closest('[data-swot-conclusion]').remove(); swotDirty = true; } });
    swotForm?.addEventListener('input', () => { swotDirty = true; });
    swotForm?.addEventListener('draft:restored', () => { swotDirty = true; });
    let index = document.querySelectorAll('[data-custom-row]').length;
    form.addEventListener('draft:restored', () => { index = Math.max(-1,...[...form.querySelectorAll('#custom-rows [name]')].map(el=>Number(el.name.match(/\[(\d+)\]/)?.[1]??-1)))+1; });
    form.addEventListener('draft:discarded', () => { dirty = false; });
    swotForm?.addEventListener('draft:discarded', () => { swotDirty = false; });
    let previousClimate = form.querySelector('[name="answers[FAKT_KLIMAT_ISTOTNY]"]').value;
    function update() {
        const climate = form.querySelector('[name="answers[FAKT_KLIMAT_ISTOTNY]"]').value;
        form.querySelectorAll('[data-climate-visibility]').forEach(row => {
            const choices = JSON.parse(row.dataset.climateVisibility);
            row.hidden = !choices[climate];
            if (climate !== previousClimate) {
                const decision = row.querySelector('[data-decision]');
                if (decision) decision.value = '';
            }
            const basis = row.querySelector('[data-climate-basis]');
            if (basis) basis.value = climate;
        });
        previousClimate = climate;
        const climateText = form.querySelector('[data-factor="KTX-ZK-02"] [data-factor-text]');
        if(climateText) climateText.textContent = 'Oceniono zmianę klimatu jako nieistotną dla zdolności systemu zarządzania energią do osiągania zamierzonych wyników. Uzasadnienie: ' + form.querySelector('[name="answers[climate_reason]"]').value;
        let done = form.querySelector('[name="answers[FAKT_KLIMAT_ISTOTNY]"]').value && form.querySelector('[name="answers[climate_reason]"]').value.trim() ? 1 : 0;
        done += form.querySelector('[name="answers[PRAWO][data]"]').value ? 1 : 0;
        const decisions = [...form.querySelectorAll('[data-decision]')].filter(el=>!el.closest('[data-factor]').hidden);
        decisions.forEach(select => {
            const row = select.closest('[data-factor]');
            const reason = row.querySelector('[data-reason]');
            reason.classList.toggle('factor-hidden', select.value !== 'odrzucony');
            row.querySelector('[data-significance]').classList.toggle('factor-hidden', select.value !== 'potwierdzony');
            const importance = row.querySelector('[data-importance]').value;
            const conclusion = importance !== 'W' || [...row.querySelectorAll('[data-conclusion],[name*="[podstawa]"]')].every(el => el.value.trim());
            if (select.value === 'potwierdzony' && importance && conclusion && row.querySelector('details textarea').value.trim() || select.value === 'odrzucony' && reason.querySelector('textarea').value.trim()) done++;
        });
        const customRows = [...form.querySelectorAll('#custom-rows [data-custom-row]')];
        customRows.forEach(row => { if (['tresc', 'wplyw', 'wymiar', 'skutek'].every(key => row.querySelector(`[name$="[${key}]"]`)?.value.trim())) done++; });
        const total = decisions.length + 2 + customRows.length;
        document.getElementById('factor-progress').textContent = `Wypełnienie: ${Math.floor(100 * done / total)}% · ${done} / ${total}`;
    }
    function changed() { dirty = true; document.getElementById('factor-save-state')?.replaceChildren('Masz niezapisane zmiany.'); update(); }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('submit', event => {
        if (swotDirty && !confirm('Niezapisane zmiany SWOT zostaną utracone. Zapisać tylko odpowiedzi ankiety?')) { event.preventDefault(); return; }
        dirty = false; swotDirty = false;
    });
    window.addEventListener('beforeunload', event => { if (window.formDraftLeaving) return; if (dirty || swotDirty) { event.preventDefault(); event.returnValue = ''; } });
    document.querySelectorAll('[data-review-action]').forEach(action => action.addEventListener('submit', event => {
        if (dirty) { event.preventDefault(); alert('Najpierw zapisz zmiany w ankiecie.'); }
        else if (swotDirty && action !== swotForm) { event.preventDefault(); alert('Najpierw zapisz analizę SWOT.'); }
        else if (action === swotForm) { swotDirty = false; }
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
