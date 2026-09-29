(() => {
    const form = document.getElementById('wh-document');
    if (!form) return;
    const list = document.getElementById('wh-lines');
    const add = document.getElementById('wh-add-line');
    let next = Math.max(...Array.from(list.children, row => Number(row.dataset.index))) + 1;
    const template = list.firstElementChild.cloneNode(true);
    function update(row) {
        const option = row.querySelector('.wh-item').selectedOptions[0];
        row.querySelector('.wh-revision').value = option?.dataset.revision || '';
        row.querySelector('.wh-stock').textContent = option?.dataset.quantity ? `Stan: ${option.dataset.quantity} ${option.dataset.unit}` : '';
    }
    async function search(row) {
        const button = row.querySelector('.wh-find');
        const status = row.querySelector('.wh-search-status');
        button.disabled = true;
        status.textContent = 'Wyszukiwanie…';
        try {
            const url = new URL(form.dataset.lookup, location.href);
            url.searchParams.set('q', row.querySelector('.wh-search').value.trim());
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error();
            const items = await response.json();
            const select = row.querySelector('.wh-item');
            select.replaceChildren(new Option('Wybierz pozycję', ''));
            items.forEach(item => {
                const option = new Option(`${item.sku} — ${item.name}`, item.id);
                Object.assign(option.dataset, {revision: item.revision, quantity: item.quantity, unit: item.unit});
                select.add(option);
            });
            if (items.length === 1) select.value = items[0].id;
            update(row);
            status.textContent = items.length ? `Wyniki: ${items.length}${items.length === 25 ? ' (zawęź wyszukiwanie)' : ''}` : 'Nie znaleziono aktywnej pozycji.';
            select.focus();
        } catch {
            status.textContent = 'Nie udało się pobrać pozycji. Spróbuj ponownie.';
        } finally {
            button.disabled = false;
        }
    }
    list.addEventListener('click', event => {
        const row = event.target.closest('.wh-line');
        if (!row) return;
        if (event.target.closest('.wh-find')) search(row);
        if (event.target.closest('.wh-remove')) {
            if (list.children.length === 1) return;
            row.remove();
            add.disabled = false;
        }
    });
    list.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('.wh-search')) {
            event.preventDefault();
            search(event.target.closest('.wh-line'));
        }
    });
    list.addEventListener('change', event => {
        if (event.target.matches('.wh-item')) update(event.target.closest('.wh-line'));
    });
    add.addEventListener('click', () => {
        if (list.children.length >= 50) return;
        const row = template.cloneNode(true);
        const previous = row.dataset.index;
        row.dataset.index = next;
        row.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/lines\[\d+\]/, `lines[${next}]`));
        row.querySelectorAll('[id]').forEach(el => el.id = el.id.replace(new RegExp(`-${previous}$`), `-${next}`));
        row.querySelectorAll('label[for]').forEach(el => el.htmlFor = el.htmlFor.replace(new RegExp(`-${previous}$`), `-${next}`));
        row.querySelectorAll('input').forEach(el => {el.value = ''; el.setAttribute('aria-invalid', 'false');});
        row.querySelector('.wh-item').replaceChildren(new Option('Wyszukaj i wybierz pozycję', ''));
        row.querySelector('.wh-item').setAttribute('aria-invalid', 'false');
        row.querySelector('.wh-stock').textContent = '';
        row.querySelector('.wh-search-status').textContent = '';
        list.append(row);
        next++;
        add.disabled = list.children.length >= 50;
        row.querySelector('.wh-search').focus();
    });
    form.addEventListener('submit', event => {
        const values = Array.from(list.querySelectorAll('.wh-item'), input => input.value);
        if (new Set(values).size !== values.length) {
            event.preventDefault();
            alert('Towar może wystąpić tylko raz w dokumencie. Połącz ilości w jednym wierszu.');
        }
    });
})();
