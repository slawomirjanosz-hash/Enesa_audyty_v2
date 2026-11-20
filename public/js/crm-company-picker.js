import {normalizeSearch} from './crm-pipeline-search.js?v=1';

export function matchingCompanies(options, query) {
    const terms = normalizeSearch(query).split(' ').filter(Boolean);
    return options.filter(option => option.value && terms.every(term => normalizeSearch(option.textContent).includes(term)));
}

if (typeof document !== 'undefined') document.querySelectorAll('[data-company-picker-search]').forEach(wrapper => {
    const select = wrapper.nextElementSibling;
    const input = wrapper.querySelector('input');
    const status = wrapper.querySelector('[role="status"]');
    const options = Array.from(select.options);
    const suggestions = document.createElement('div');
    suggestions.style.cssText = 'max-height:220px;overflow:auto;border:1px solid #D0CCC0;border-radius:7px;background:#fff;margin-top:4px';
    suggestions.hidden = true;
    wrapper.append(suggestions);

    const choose = option => {
        select.replaceChildren(...options);
        select.value = option.value;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        input.value = option.textContent.trim();
        suggestions.hidden = true;
        status.textContent = 'Wybrano firmę: ' + input.value;
    };
    const update = () => {
        const results = matchingCompanies(options, input.value);
        suggestions.replaceChildren();
        suggestions.hidden = !input.value.trim();
        if (suggestions.hidden) {
            select.replaceChildren(...options);
            status.textContent = '';
            return;
        }
        results.slice(0, 50).forEach(option => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = option.textContent;
            button.style.cssText = 'display:block;width:100%;padding:9px 10px;text-align:left;border:0;border-bottom:1px solid #eee;background:#fff;color:inherit;font:inherit;cursor:pointer';
            button.addEventListener('click', () => choose(option));
            suggestions.append(button);
        });
        status.textContent = results.length ? 'Pasujące firmy: ' + results.length : 'Brak pasujących firm.';
    };
    const reset = () => {
        input.value = '';
        suggestions.hidden = true;
        status.textContent = '';
        const current = select.value;
        select.replaceChildren(...options);
        select.value = current;
    };
    input.addEventListener('input', update);
    input.addEventListener('search', update);
    input.addEventListener('keydown', event => {
        if (event.isComposing || !['Enter', 'ArrowDown', 'Escape'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'Escape') {
            suggestions.hidden = true;
            return;
        }
        const results = matchingCompanies(options, input.value);
        if (event.key === 'Enter' && results.length === 1) choose(results[0]);
        else suggestions.querySelector('button')?.focus();
    });
    select.addEventListener('change', reset);
    select.addEventListener('crm-company-reset', reset);
    wrapper.hidden = select.disabled;
});
