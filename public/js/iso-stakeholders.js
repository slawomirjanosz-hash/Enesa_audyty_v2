document.addEventListener('DOMContentLoaded', () => {
    const dirty = new Set();
    const forms = [...document.querySelectorAll('[data-questionnaire-form]')];
    forms.forEach(form => {
        form.addEventListener('draft:discarded', () => dirty.delete(form));
        form.addEventListener('draft:restored', () => { index = Math.max(-1,...[...form.querySelectorAll('#stakeholder-custom [name]')].map(el=>Number(el.name.match(/\[(\d+)\]/)?.[1]??-1)))+1; });
        form.addEventListener('input', () => dirty.add(form));
        form.addEventListener('change', () => dirty.add(form));
        form.addEventListener('submit', event => {
            if ([...dirty].some(other => other !== form) && !confirm('Niezapisane zmiany w drugiej części formularza zostaną utracone. Kontynuować zapis tej części?')) { event.preventDefault(); return; }
            dirty.clear();
        });
    });
    document.querySelectorAll('[data-review-action]').forEach(form => form.addEventListener('submit', event => {
        if (dirty.size) { event.preventDefault(); alert('Najpierw zapisz zmiany w ankiecie lub ocenie konsultanta.'); }
    }));
    window.addEventListener('beforeunload', event => { if (window.formDraftLeaving) return; if (dirty.size) { event.preventDefault(); event.returnValue = ''; } });
    let index = document.querySelectorAll('[data-custom-party]').length;
    document.getElementById('stakeholder-add')?.addEventListener('click', () => {
        const rows = document.getElementById('stakeholder-custom');
        if (rows.children.length >= 20) return;
        const fragment = document.getElementById('stakeholder-template').content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(input => input.name = input.name.replace('__INDEX__', String(index)));
        fragment.querySelector('input[type=hidden]').value = crypto.randomUUID();
        index++; rows.append(fragment); dirty.add(document.getElementById('stakeholder-form'));
    });
    document.getElementById('stakeholder-custom')?.addEventListener('click', event => {
        if (event.target.closest('[data-remove-party]')) { event.target.closest('[data-custom-party]').remove(); dirty.add(document.getElementById('stakeholder-form')); }
    });
});
