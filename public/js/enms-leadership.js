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

// Readiness links use real URLs, so opening a new tab and browser Back work as usual.
const revealEnmsTarget = () => {
    let target;
    try {
        target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    } catch {
        return;
    }
    if (!target?.matches('[data-enms-jump-target]')) return;
    for (let parent = target; parent; parent = parent.parentElement) {
        if (parent.matches('details')) parent.open = true;
    }
    // A previously linked field may no longer apply after another user changes the answer.
    if (target.closest('[hidden]')) target = target.closest('.enms-declaration');
    if (!target) return;
    document.querySelectorAll('.enms-jump-target').forEach(el => el.classList.remove('enms-jump-target'));
    target.classList.add('enms-jump-target');
    target.style.scrollMarginTop = `${(document.querySelector('.iso-clause-tabs')?.offsetHeight || 0) + 20}px`;
    const controls = [...target.querySelectorAll('input:not([type="hidden"]), select, textarea')]
        .filter(el => !el.matches(':disabled') && !el.readOnly && el.getClientRects().length);
    const empty = el => ['checkbox', 'radio'].includes(el.type) ? !el.checked : !el.value.trim();
    const focus = controls.find(empty) || controls[0] || target.querySelector('a, button:not(:disabled)') || target;
    if (focus === target) target.setAttribute('tabindex', '-1');
    focus.focus({preventScroll: true});
    target.scrollIntoView({block: 'start'});
};
window.addEventListener('hashchange', revealEnmsTarget);
// Wait for native fragment navigation, which otherwise resets focus after deferred scripts.
window.addEventListener('pageshow', revealEnmsTarget);
