import {normalizeSearch} from './crm-pipeline-search.js?v=1';

document.querySelectorAll('[data-company-picker-search]').forEach(wrapper => {
    const select = wrapper.nextElementSibling;
    const input = wrapper.querySelector('input');
    const status = wrapper.querySelector('[role="status"]');
    const options = Array.from(select.options, option => ({option, name: normalizeSearch(option.textContent)}));
    const matches = () => {
        const terms = normalizeSearch(input.value).split(' ').filter(Boolean);
        return options.filter(({option, name}) => option.value && terms.every(term => name.includes(term)));
    };
    const update = () => {
        const current = select.value;
        const results = new Set(matches().map(({option}) => option));
        select.replaceChildren(...options.map(({option}) => option).filter(option =>
            !option.value || option.value === current || results.has(option)));
        select.value = current;
        status.textContent = input.value.trim() ? `Pasujące firmy: ${results.size}` : '';
    };
    const reset = () => {
        input.value = '';
        update();
    };
    input.addEventListener('input', update);
    input.addEventListener('keydown', event => {
        if (event.isComposing || !['Enter', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const results = matches();
        if (event.key === 'Enter' && results.length === 1) {
            select.value = results[0].option.value;
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }
        select.focus();
    });
    select.addEventListener('change', reset);
    select.addEventListener('crm-company-reset', reset);
    wrapper.hidden = select.disabled;
});
