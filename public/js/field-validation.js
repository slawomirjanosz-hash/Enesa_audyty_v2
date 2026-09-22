document.addEventListener('DOMContentLoaded', () => {
    const data = JSON.parse(document.getElementById('field-validation-data')?.textContent || '{}');
    const forms = [...document.forms];
    forms.forEach((form, index) => {
        if (!form.id) form.id = `validation-form-${index}`;
        form.addEventListener('submit', () => {
            if (form.method.toLowerCase() === 'get') return;
            let marker = form.querySelector('input[name="_validation_form"]');
            if (!marker) { marker = document.createElement('input'); marker.type = 'hidden'; marker.name = '_validation_form'; form.append(marker); }
            marker.value = form.id;
        });
    });
    const controls = [...document.querySelectorAll('input:not([type=hidden]),select,textarea')];
    const keyOf = input => (input.name || input.dataset.completionName || '').replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
    const reveal = input => {
        for (let parent = input.parentElement; parent; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
            if (parent.classList.contains('iso-action-panel') || parent.classList.contains('aw-modal')) parent.classList.add('open');
            if (parent.classList.contains('aw-pane')) document.querySelector(`[data-audit-tab="${parent.id.replace('aw-', '')}"]`)?.click();
            if (parent.dataset.isoPanel && typeof window.showIsoSection === 'function') window.showIsoSection(parent.dataset.isoPanel);
            if (parent.dataset.panel) document.querySelector(`[data-step="${parent.dataset.panel}"]`)?.click();
            if (parent.hidden) parent.hidden = false;
        }
    };
    let sequence = 0;
    const mark = (input, messages) => {
        const previous = input.dataset.fieldErrorId;
        if (previous) document.getElementById(previous)?.remove();
        const message = document.createElement('span');
        message.id = `field-error-${++sequence}`;
        message.className = 'field-error-message';
        message.textContent = [...new Set(messages)].join(' ');
        input.classList.add('field-invalid');
        input.setAttribute('aria-invalid', 'true');
        const descriptions = (input.getAttribute('aria-describedby') || '').split(' ').filter(id => id && id !== previous);
        input.setAttribute('aria-describedby', [...descriptions, message.id].join(' '));
        input.dataset.fieldErrorId = message.id;
        input.insertAdjacentElement('afterend', message);
        input.closest('.plant-question,.plant-repeat-row')?.classList.add('has-field-error');
    };
    const matching = new Map();
    Object.entries(data.errors || {}).forEach(([key, messages]) => {
        const candidates = controls.filter(input => keyOf(input) === key || keyOf(input).startsWith(`${key}.`) || (input.name.endsWith('[]') && key.startsWith(`${keyOf(input)}.`)));
        const submitted = candidates.filter(input => input.form?.id === data.form);
        (submitted.length ? submitted : candidates).forEach(input => matching.set(input, [...(matching.get(input) || []), ...messages]));
    });
    matching.forEach((messages, input) => mark(input, messages));
    const focusError = input => { reveal(input); requestAnimationFrame(() => { input.focus({preventScroll:true}); input.scrollIntoView({block:'center', behavior:'smooth'}); }); };
    const first = controls.find(input => matching.has(input) && !input.disabled);
    if (first) requestAnimationFrame(() => focusError(first));
    let firstNative = null;
    document.addEventListener('invalid', event => {
        event.preventDefault();
        mark(event.target, [event.target.validationMessage || 'Sprawdź wartość pola.']);
        if (!firstNative) {
            firstNative = event.target;
            setTimeout(() => { focusError(firstNative); firstNative = null; }, 0);
        }
    }, true);
    document.addEventListener('input', event => {
        const input = event.target;
        if (!input.dataset.fieldErrorId || !input.validity.valid) return;
        const id = input.dataset.fieldErrorId;
        document.getElementById(id)?.remove();
        input.classList.remove('field-invalid');
        input.removeAttribute('aria-invalid');
        input.setAttribute('aria-describedby', (input.getAttribute('aria-describedby') || '').split(' ').filter(value => value !== id).join(' '));
        delete input.dataset.fieldErrorId;
        const group = input.closest('.has-field-error');
        if (group && !group.querySelector('.field-invalid')) group.classList.remove('has-field-error');
    });
});
