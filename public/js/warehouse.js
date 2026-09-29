(() => {
    const form = document.getElementById('wh-document');
    if (!form) return;
    const catalog = document.getElementById('wh-catalog');
    const list = document.getElementById('wh-lines');
    const search = document.getElementById('wh-live-search');
    const rows = Array.from(catalog.tBodies[0].rows);
    const normalize = value => String(value).toLocaleLowerCase('pl').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l').trim();
    const names = new Map(rows.map(row => [row, normalize(row.cells[0].textContent + ' ' + row.cells[1].textContent)]));
    let next = list.children.length;
    // Catalogue drafts must not participate in the document's HTML validation or submission.
    rows.forEach(row => row.querySelectorAll('input,select').forEach(input => input.setAttribute('form', 'wh-catalog-controls')));
    function refresh() {
        const selected = new Set(Array.from(list.children, row => row.dataset.itemId));
        rows.forEach(row => {
            const button = row.querySelector('.wh-pick');
            button.disabled = selected.has(row.dataset.itemId) || selected.size >= 50;
            button.textContent = selected.has(row.dataset.itemId) ? 'Dodano' : 'Dodaj';
        });
        document.getElementById('wh-chosen-count').textContent = list.children.length;
        document.getElementById('wh-none-selected').hidden = list.children.length > 0;
    }
    function filter() {
        const terms = normalize(search.value).split(/\s+/).filter(Boolean);
        let visible = 0;
        rows.forEach(row => {
            row.hidden = !terms.every(term => names.get(row).includes(term));
            if (!row.hidden) visible++;
        });
        document.getElementById('wh-picker-status').textContent = `Widoczne towary: ${visible} z ${rows.length}`;
        document.getElementById('wh-no-results').hidden = visible > 0;
    }
    search.addEventListener('input', filter);
    search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    catalog.addEventListener('click', event => {
        const button = event.target.closest('.wh-pick');
        if (!button || button.disabled || list.children.length >= 50) return;
        const source = button.closest('tr');
        if (Array.from(list.children).some(row => row.dataset.itemId === source.dataset.itemId)) return;
        for (const input of source.querySelectorAll('input[type=number]')) {
            if (!input.value || !input.checkValidity()) {
                document.getElementById('wh-picker-status').textContent = 'Uzupełnij poprawną ilość i cenę wybranego towaru.';
                input.reportValidity(); input.focus(); return;
            }
        }
        const row = source.cloneNode(true);
        row.hidden = false;
        const sourceControls = source.querySelectorAll('[data-field]');
        row.querySelectorAll('[data-field]').forEach((input, index) => {
            input.value = sourceControls[index].value;
            input.removeAttribute('form');
            input.name = `lines[${next}][${input.dataset.field}]`;
            input.setAttribute('aria-invalid', 'false');
            if (input.type === 'number') input.required = true;
        });
        const remove = row.querySelector('.wh-pick');
        remove.className = 'wh-btn danger wh-remove';
        remove.textContent = 'Usuń';
        remove.disabled = false;
        list.append(row);
        next++;
        refresh();
        document.getElementById('wh-picker-status').textContent = `Dodano: ${source.cells[1].textContent.trim()}. Możesz wybrać kolejny towar.`;
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('.wh-remove');
        if (!button) return;
        button.closest('tr').remove();
        refresh();
    });
    form.addEventListener('submit', event => {
        if (!list.children.length) {
            event.preventDefault();
            document.getElementById('wh-picker-status').textContent = 'Dodaj przynajmniej jeden towar do dokumentu.';
            search.focus();
        }
    });
    filter();
    refresh();
})();
