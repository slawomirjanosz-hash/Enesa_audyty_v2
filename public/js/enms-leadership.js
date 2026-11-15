document.querySelectorAll('[data-enms-declaration]').forEach(form => {
    const state = form.querySelector('[data-enms-state]');
    const render = () => form.querySelectorAll('[data-enms-states]').forEach(panel => {
        panel.hidden = !panel.dataset.enmsStates.split(' ').includes(state.value);
    });
    state.addEventListener('change', render);
    render();
});

document.querySelectorAll('[data-enms-register]').forEach(form => {
    const value = key => form.querySelector(`[name="payload[${key}]"]`)?.value || '';
    const visible = (key, show) => {
        const field = form.querySelector(`[data-enms-field="${key}"]`);
        if (field) field.hidden = !show;
    };
    const render = () => {
        if (form.dataset.enmsRegister === 'verification') {
            const shown = value('dostepnosc') === 'okazany';
            ['wiarygodnosc', 'zakresInformacji', 'sposob'].forEach(key => visible(key, shown));
            visible('probka', shown && value('sposob') === 'próbka');
            visible('uwaga', !!value('wiarygodnosc') && value('wiarygodnosc') !== 'potwierdzona');
        }
        if (form.dataset.enmsRegister === 'coverage') {
            visible('coPotwierdza', !!value('zakres') && value('zakres') !== 'zadne');
            visible('czegoBrakuje', !!value('zakres') && value('zakres') !== 'pelne');
        }
        if (form.dataset.enmsRegister === 'assessment') {
            ['period_from', 'period_until'].forEach(key => visible(key, value('mode') === 'okresowa'));
            visible('event', value('mode') === 'zdarzeniowa');
        }
    };
    form.addEventListener('change', render);
    render();
});
