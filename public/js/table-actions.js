// Progressive enhancement: keep the original controls, forms, events and permission boundaries.
const paths = {
    edit: 'M12 20h9 M16.5 3.5a2.1 2.1 0 0 1 3 3L9 17l-4 1 1-4Z',
    delete: 'M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7',
    copy: 'M9 9h12v12H9Z M5 15H3V3h12v2',
    download: 'M12 3v12 m-5-5 5 5 5-5 M4 16v5h16v-5',
    upload: 'M12 16V3 m-5 5 5-5 5 5 M4 16v5h16v-5',
    view: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    archive: 'M3 3h18v5H3Z M5 8v13h14V8 M10 12h4',
    restore: 'M3 10a9 9 0 1 1 1 8 M3 3v7h7 M12 7v5l3 2',
    add: 'M12 4v16 M4 12h16',
    save: 'M4 3h13l4 4v14H3V3Z M7 3v6h9V3 M7 21v-8h10v8',
    close: 'm5 5 14 14 M19 5 5 19',
    send: 'm3 3 18 9-18 9 4-9Z M7 12h14',
    lock: 'M5 10h14v11H5Z M8 10V6a4 4 0 0 1 8 0v4 M12 14v3',
    check: 'm4 12 5 5L20 6',
    print: 'M6 9V3h12v6 M6 17H3V9h18v8h-3 M6 14h12v7H6Z M17 12h1',
    file: 'M5 3h9l5 5v13H5Z M14 3v6h5 M8 13h8 M8 17h6',
    link: 'm10 13 4-4 M8 16l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0 M16 8l1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0',
    play: 'm8 4 12 8-12 8Z',
    users: 'M10 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0 M1 21v-4a5 5 0 0 1 10 0v4 M16 4a3 3 0 0 1 0 6 M16 13a5 5 0 0 1 5 5v3',
    more: 'M5 11v2 M12 11v2 M19 11v2',
};

export function actionDescriptor(label, iconClass = '') {
    const raw = String(label || '').trim();
    const text = ({'×':'Usuń', '✕':'Usuń', '+':'Dodaj', '…':'Więcej akcji', '...':'Więcej akcji'})[raw] || raw;
    const normalized = text.toLocaleLowerCase('pl');
    const rules = [
        [/przywr|odarchiw|cofnij usuni/, 'restore'], [/usuń|usun|kasuj|delete/, 'delete'],
        [/archiw/, 'archive'], [/kopi|powiel|duplik/, 'copy'], [/pobierz|eksport|ściągn|download/, 'download'],
        [/podgląd|podglad|zobacz|szczegół|otwórz|otworz|przejd/, 'view'], [/edyt|zmień|zmien|popraw/, 'edit'],
        [/druk/, 'print'], [/pdf|word|excel|protokół|protokol|dokument/, 'file'],
        [/dodaj|utwórz|utworz|nowy|wybierz|przypisz/, 'add'], [/zapisz/, 'save'],
        [/anuluj|zamknij|odrzuć|odrzuc/, 'close'], [/wyślij|wyslij|mail/, 'send'],
        [/odblok|zablok|hasł|hasl|uprawnie|dostęp|dostep/, 'lock'],
        [/zatwier|akcept|zakończ|zakoncz|wykonan/, 'check'], [/wgraj|import/, 'upload'],
        [/link|udostępn|udostepn/, 'link'], [/film|odtwórz|odtworz/, 'play'], [/użytkown|uzytkown|zespół/, 'users'],
    ];
    let kind = rules.find(([pattern]) => pattern.test(normalized))?.[1];
    if (!kind) {
        kind = Object.entries({trash:'delete', pencil:'edit', edit:'edit', copy:'copy', download:'download', eye:'view', archive:'archive', restore:'restore', plus:'add', check:'check', lock:'lock', printer:'print', 'file-type-pdf':'file', player:'play', users:'users', link:'link'}).find(([key]) => iconClass.includes(key))?.[1] || 'more';
    }
    const defaults = {delete:'Usuń', edit:'Edytuj', copy:'Kopiuj', download:'Pobierz', view:'Podgląd', archive:'Archiwizuj', restore:'Przywróć', add:'Dodaj', check:'Zatwierdź', lock:'Uprawnienia', print:'Drukuj', file:'Dokument PDF', play:'Odtwórz', users:'Użytkownicy', link:'Otwórz link', more:'Więcej akcji'};
    return {label:text || defaults[kind] || 'Akcja', kind, path:paths[kind]};
}

export function isActionHeading(text) {
    return /^(akcj[ae]|actions?|operacje|opcje|działania|filmy i akcje)$/i.test(String(text).replace(/[⇅↕↑↓]/g, '').trim());
}

export function enhanceAction(control) {
    if (control.closest('[data-action-icons="false"],.ql-editor,[contenteditable="true"]') || control.querySelector('input,select,textarea,button,a,[role="menu"]')) return;
    // Do not reveal conditional or permission-hidden actions.
    if (control.hidden || control.style.display === 'none' || control.closest('.hidden,.d-none,[aria-hidden="true"]')) return;
    const text = control.textContent.trim();
    const previousText = control.dataset.actionOriginalText;
    const label = previousText !== undefined && previousText !== text ? text : (control.getAttribute('title') || control.getAttribute('aria-label') || text);
    const descriptor = actionDescriptor(label, control.querySelector('i')?.className || '');
    if (control.dataset.actionIconLabel === descriptor.label && control.querySelector('.app-table-action-glyph')) return;
    control.dataset.actionOriginalText = text;
    control.dataset.actionIconLabel = descriptor.label;
    control.setAttribute('title', descriptor.label);
    control.setAttribute('aria-label', descriptor.label);
    control.classList.add('app-table-action');
    control.classList.toggle('app-table-action-danger', descriptor.kind === 'delete');
    control.querySelector('.app-table-action-glyph')?.remove();
    const svg = control.ownerDocument.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'app-table-action-glyph');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    const path = control.ownerDocument.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', descriptor.path);
    svg.append(path);
    control.append(svg);
}

export function enhanceActionTable(table) {
    if (table.closest('[data-action-icons="false"],.ql-editor,[contenteditable="true"],.pdf-preview') || !table.tHead || table.tHead.rows.length !== 1) return;
    const headers = [...table.tHead.rows[0].cells];
    const columns = headers.flatMap((header, i) => isActionHeading(header.textContent) || (!header.textContent.trim() && i === headers.length - 1 && !header.querySelector('input,select')) ? [i] : []);
    for (const body of table.tBodies) for (const row of body.rows) {
        // Summary/empty-state rows are not action rows.
        if ([...row.cells].some(cell => cell.colSpan > 1)) continue;
        for (const index of columns) {
            const cell = row.cells[index];
            if (!cell || cell.querySelector('table')) continue;
            cell.classList.add('app-table-action-cell');
            const controls = [...cell.querySelectorAll('a[href],button')];
            controls.forEach(control => {
                enhanceAction(control);
                if (!control.classList.contains('app-table-action')) return;
                // Flatten only existing action containers, never move controls across forms.
                for (let parent = control.parentElement; parent && parent !== cell; parent = parent.parentElement) {
                    if (parent.matches('div,form,span') && !parent.querySelector('select,textarea,input:not([type="hidden"]),[role="menu"]')) parent.classList.add('app-table-action-group');
                }
            });
        }
    }
}

if (typeof document !== 'undefined') {
    const css = document.createElement('link');
    css.rel = 'stylesheet'; css.href = new URL('../css/table-actions.css?v=1', import.meta.url).href;
    document.head.append(css);
    const scan = root => {
        if (root.matches?.('table')) enhanceActionTable(root);
        root.querySelectorAll?.('table').forEach(enhanceActionTable);
    };
    scan(document);
    const pending = new Set();
    let queued = false;
    new MutationObserver(records => {
        for (const record of records) {
            const element = record.target.nodeType === 1 ? record.target : record.target.parentElement;
            const table = element?.closest('table');
            if (table) pending.add(table);
            for (const node of record.addedNodes) if (node.nodeType === 1) {
                if (node.matches('table')) pending.add(node);
                node.querySelectorAll('table').forEach(item => pending.add(item));
            }
        }
        if (!queued && pending.size) {
            queued = true;
            queueMicrotask(() => { queued = false; const tables = [...pending]; pending.clear(); tables.forEach(enhanceActionTable); });
        }
    }).observe(document.body, {childList:true, subtree:true, characterData:true, attributes:true, attributeFilter:['hidden','style']});
}
