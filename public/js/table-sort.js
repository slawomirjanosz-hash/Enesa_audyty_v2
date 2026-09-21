const collator = new Intl.Collator('pl', {numeric: true, sensitivity: 'base'});

export function sortValue(raw) {
    const text = String(raw ?? '').replace(/\u00a0|\u202f/g, ' ').trim();
    if (!text || /^(—|–|-|brak.*|nie ustalono|nieprzypisane|brak dostępu)$/i.test(text)) return null;
    const date = text.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (date) return Date.UTC(+date[3], +date[2] - 1, +date[1], +(date[4] || 0), +(date[5] || 0), +(date[6] || 0));
    const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (iso) return Date.UTC(+iso[1], +iso[2] - 1, +iso[3], +(iso[4] || 0), +(iso[5] || 0), +(iso[6] || 0));
    const size = text.match(/^([\d., ]+)\s*(B|KB|MB|GB|TB)$/i);
    if (size) return Number(size[1].replace(/ /g, '').replace(',', '.')) * 1024 ** ['B', 'KB', 'MB', 'GB', 'TB'].indexOf(size[2].toUpperCase());
    // Require a whole numeric value: do not interpret identifiers (e.g. 12/2026) as amounts.
    if (/^[+-]?\d[\d ]*(?:[.,]\d+)?\s*(?:zł|PLN|EUR|€|%|szt\.?|dni|godz\.?|kg|m²|m³|m|kWh|TJ)?$/i.test(text)) {
        return Number(text.replace(/\s/g, '').replace(',', '.').replace(/[^\d.+-].*$/, ''));
    }
    return text;
}

export function compareValues(left, right, direction = 'asc', type = 'auto') {
    if (type === 'text' && String(left ?? '').trim() && String(right ?? '').trim()) {
        const result = collator.compare(String(left), String(right));
        return direction === 'desc' ? -result : result;
    }
    const a = sortValue(left), b = sortValue(right);
    if (a === null || b === null) return a === b ? 0 : a === null ? 1 : -1;
    const result = typeof a === 'number' && typeof b === 'number' ? a - b : collator.compare(String(a), String(b));
    return direction === 'desc' ? -result : result;
}

function cellValue(cell) {
    if (!cell) return '';
    if (cell.hasAttribute('data-sort-value')) return cell.dataset.sortValue;
    const control = cell.querySelector('select,input:not([type=hidden]):not([type=checkbox]):not([type=radio]),textarea');
    if (control) return control.tagName === 'SELECT' ? control.selectedOptions[0]?.textContent : control.value;
    const clone = cell.cloneNode(true);
    clone.querySelectorAll('button,form,[aria-hidden=true],.mini-actions').forEach(node => node.remove());
    // Secondary notes should not change ordering of the primary value.
    return clone.innerText?.trim() || clone.textContent.trim();
}

export function sortBody(body, column, direction, type = 'auto') {
    const rows = [...body.rows];
    let group = [];
    const flush = before => {
        group.sort((a, b) => compareValues(cellValue(a.cells[column]), cellValue(b.cells[column]), direction, type));
        group.forEach(row => body.insertBefore(row, before));
        group = [];
    };
    rows.forEach(row => {
        // Section headings, subtotals and expandable detail rows stay in place.
        if (row.matches('[data-sort-fixed],.total-row,.subtotal-row') || [...row.cells].some(cell => cell.colSpan > 1 || cell.rowSpan > 1)) flush(row);
        else group.push(row);
    });
    flush(null);
}

const initialized = new WeakSet();
export function enhanceTable(table) {
    if (table.closest('[data-sortable="false"],.ql-editor,[contenteditable="true"]') || !table.tHead || table.tHead.rows.length !== 1) return;
    const headers = [...table.tHead.rows[0].cells];
    headers.forEach((header, column) => {
        if (initialized.has(header)) return;
        initialized.add(header);
        const label = header.textContent.replace(/[⇅↕↑↓]/g, '').trim();
        if (!label || /^(akcj[ae]|filmy i akcje|lp\.?|#|wybierz|zaznacz)$/i.test(label) || header.querySelector('input') || header.dataset.sortable === 'false') return;
        const keys = table.dataset.serverSort?.split(',');
        // Preserve dedicated sort controls; supplement only missing columns.
        if (!keys && header.querySelector('button,a')) return;
        header.removeAttribute('onclick');
        header.onclick = null;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'app-table-sort';
        button.textContent = label;
        button.title = `Sortuj: ${label}`;
        const marker = document.createElement('span');
        marker.className = 'app-table-sort-marker';
        marker.setAttribute('aria-hidden', 'true');
        marker.textContent = '↕';
        button.append(' ', marker);
        header.replaceChildren(button);
        const params = new URLSearchParams(window.location.search);
        const prefix = table.dataset.sortPrefix || 'table';
        if (keys && params.get(`${prefix}_sort`) === keys[column]) {
            header.setAttribute('aria-sort', params.get(`${prefix}_direction`) === 'desc' ? 'descending' : 'ascending');
            marker.textContent = params.get(`${prefix}_direction`) === 'desc' ? '↓' : '↑';
        }
        button.addEventListener('click', event => {
            event.stopPropagation();
            const direction = header.getAttribute('aria-sort') === 'ascending' ? 'desc' : 'asc';
            if (keys?.[column]) {
                const url = new URL(window.location.href);
                url.searchParams.set(`${prefix}_sort`, keys[column]);
                url.searchParams.set(`${prefix}_direction`, direction);
                url.searchParams.delete(table.dataset.pageParameter || 'page');
                window.location.assign(url);
                return;
            }
            headers.forEach(item => {
                item.removeAttribute('aria-sort');
                const icon = item.querySelector('.app-table-sort-marker');
                if (icon) icon.textContent = '↕';
            });
            header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
            marker.textContent = direction === 'asc' ? '↑' : '↓';
            const type = header.dataset.sortType || (/wersja|rewizja|numer|identyfikator|nip/i.test(label) ? 'text' : 'auto');
            [...table.tBodies].forEach(body => sortBody(body, column, direction, type));
            table.dispatchEvent(new CustomEvent('table:sorted', {detail: {column, direction}}));
        });
    });
}

if (typeof document !== 'undefined') {
    const scan = root => {
        if (root.matches?.('table')) enhanceTable(root);
        root.querySelectorAll?.('table').forEach(enhanceTable);
    };
    scan(document);
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === 1) {
            scan(node);
            const table = node.closest('table');
            if (table) enhanceTable(table);
        }
    }))).observe(document.body, {childList: true, subtree: true});
}
