import {matchesSearch} from './crm-pipeline-search.js?v=1';

export function filterList(root, selector, query) {
    let count = 0;
    root.querySelectorAll(selector).forEach(item => {
        if (item.tagName === 'TR' && item.querySelector('td[colspan]')) return;
        const clone = item.cloneNode(true);
        clone.querySelectorAll('button,form,select,[aria-hidden="true"]').forEach(node => node.remove());
        const visible = matchesSearch(item.dataset.searchText || item.dataset.importantContactSearch || clone.textContent, query);
        item.style.display = visible ? '' : 'none';
        if (visible) count++;
    });
    return count;
}

if (typeof document !== 'undefined') {
    window.appListMatches = matchesSearch;
    document.querySelectorAll('[data-list-search]').forEach(input => {
        const refresh = () => {
            let count = filterList(document, input.dataset.listSearch, input.value);
            const legacy = document.getElementById('search-companies');
            if (legacy && typeof window.filterDashboard === 'function') {
                legacy.value = input.value;
                window.filterDashboard();
                count = [...document.querySelectorAll('#companies-tbody tr')].filter(row => !row.querySelector('td[colspan]') && row.style.display !== 'none').length;
            }
            input.parentElement.querySelector('[data-list-search-result]').textContent = input.value.trim()
                ? (count ? `Znaleziono: ${count}.` : 'Brak pasujących wyników.') : '';
        };
        input.addEventListener('input', refresh);
        input.addEventListener('search', refresh);
    });
    document.querySelectorAll('[data-list-search-form]').forEach(form => {
        const input = form.querySelector('input[type=search]');
        const key = 'list-search-focus:' + location.pathname;
        let timer;
        try {
            if (sessionStorage.getItem(key) === input.value) {
                sessionStorage.removeItem(key);
                input.focus();
            }
        } catch (_) { /* Storage may be disabled. */ }
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.requestSubmit(), 650);
        });
        form.addEventListener('submit', () => {
            clearTimeout(timer);
            try { sessionStorage.setItem(key, input.value); } catch (_) { /* Optional focus restoration. */ }
        });
    });
}
