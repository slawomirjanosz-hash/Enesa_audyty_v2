(() => {
    if (window.vehicleLocationModalReady) return;
    window.vehicleLocationModalReady = true;
    let modal, opener, generation = 0;
    const initialize = root => {
        const form = root.querySelector('#location-plan');
        if (!form || form.dataset.ready) return;
        form.dataset.ready = '1';
        const config = JSON.parse(root.querySelector('[data-location-plan-config]').textContent);
        const rows = root.querySelector('#location-periods');
        const renumber = () => rows.querySelectorAll('fieldset').forEach((row, i) =>
            row.querySelectorAll('[data-location-field]').forEach(input => input.name = 'periods[' + i + '][' + input.dataset.locationField + ']'));
        const add = (data = {}) => {
            const row = root.querySelector('#location-period-template').content.firstElementChild.cloneNode(true);
            row.querySelectorAll('[data-location-field]').forEach(input => input.value = data[input.dataset.locationField] ?? '');
            const permanent = row.querySelector('[data-permanent]'), end = row.querySelector('[data-location-field="ends_on"]'), start = row.querySelector('[data-location-field="starts_on"]');
            if (!data.starts_on) { start.value = config.start; end.value = config.end; }
            const sync = () => { end.disabled = permanent.checked; end.required = !permanent.checked; end.closest('label').hidden = permanent.checked; start.closest('label').hidden = permanent.checked; };
            permanent.checked = !!data.starts_on && !data.ends_on;
            sync();
            permanent.addEventListener('change', () => { if (permanent.checked) { start.value = config.today; end.value = ''; } sync(); });
            row.querySelector('[data-remove-period]').addEventListener('click', () => { row.remove(); renumber(); });
            rows.append(row);
            renumber();
        };
        config.periods.forEach(add);
        root.querySelector('#add-location-period').addEventListener('click', () => add());
        if (root.closest('#vehicle-location-modal')) form.addEventListener('submit', async event => {
            event.preventDefault();
            const message = root.querySelector('[data-location-error]');
            message.textContent = '';
            const save = form.querySelector('[type="submit"]');
            save.disabled = true;
            try {
                const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || (response.status === 409 ? 'Przypisania zmieniły się. Zamknij i otwórz okno ponownie.' : 'Nie udało się zapisać przypisań.'));
                const label = data.location || 'Nieokreślona';
                document.querySelectorAll('[data-vehicle-location-open]').forEach(button => {
                    if (button.dataset.vehicleLocationOpen !== opener.dataset.vehicleLocationOpen) return;
                    button.textContent = label;
                    const cell = button.closest('td');
                    if (cell) cell.dataset.sortValue = data.location || '';
                });
                close();
            } catch (error) { message.textContent = error.message || 'Nie udało się zapisać. Spróbuj ponownie.'; }
            finally { save.disabled = false; }
        });
    };
    const close = () => {
        generation++;
        if (modal) { modal.hidden = true; modal.querySelector('.hr-modal-card').replaceChildren(); }
        document.body.style.overflow = '';
        opener?.focus();
    };
    document.addEventListener('click', async event => {
        if (event.target.closest('[data-close-location-modal]')) { event.preventDefault(); close(); return; }
        const button = event.target.closest('[data-vehicle-location-open]');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        opener = button;
        const current = ++generation;
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'vehicle-location-modal';
            modal.className = 'hr-modal';
            modal.innerHTML = '<div class="hr-modal-card" role="dialog" aria-modal="true" aria-label="Lokalizacje samochodu" tabindex="-1"></div>';
            document.body.append(modal);
            modal.addEventListener('keydown', e => {
                if (e.key !== 'Tab') return;
                const focusable = [...modal.querySelectorAll('button,input,select,a[href]')].filter(el => !el.disabled && el.getClientRects().length);
                if (!focusable.length) return;
                if (e.shiftKey && document.activeElement === focusable[0]) { e.preventDefault(); focusable.at(-1).focus(); }
                else if (!e.shiftKey && document.activeElement === focusable.at(-1)) { e.preventDefault(); focusable[0].focus(); }
            });
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        const card = modal.querySelector('.hr-modal-card');
        card.innerHTML = '<button type="button" class="hr-btn danger" data-close-location-modal>Zamknij</button><p role="status">Wczytuję lokalizacje…</p>';
        card.focus();
        try {
            const response = await fetch(button.dataset.vehicleLocationOpen, {headers: {'X-Vehicle-Location-Modal': '1'}});
            if (!response.ok || response.redirected) throw new Error();
            const html = await response.text();
            if (current !== generation) return;
            card.innerHTML = html;
            initialize(card);
            card.querySelector('[data-close-location-modal]')?.focus();
        } catch (_) {
            if (current === generation) card.innerHTML = '<button type="button" class="hr-btn danger" data-close-location-modal>Zamknij</button><p role="alert">Nie udało się otworzyć lokalizacji. Spróbuj ponownie.</p>';
        }
    });
    document.querySelectorAll('[data-location-plan-content]').forEach(initialize);
})();
