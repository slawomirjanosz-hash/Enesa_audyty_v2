document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('system-form');
    if (!form) return;
    const section = location.pathname.split('/').filter(Boolean).pop();
    const readRows = root => [...form.querySelectorAll(`[data-repeater="${root}"] .system-row`)].map(row => {
        const field = row.querySelector('[data-key]');
        return {id: field?.dataset.key.split('.')[1], name: row.querySelector(`[name$="[nazwa]"]`)?.value || 'Nowa pozycja'};
    }).filter(row => row.id);
    const selectOptions = (select, rows) => {
        const current = select.value;
        select.replaceChildren(new Option('Wybierz', ''));
        rows.forEach(row => select.add(new Option(row.name, row.id)));
        if (current && !rows.some(row => row.id === current)) select.add(new Option('Usunięte powiązanie — popraw wybór', current));
        select.value = current;
    };
    const matrix = () => {
        if (section !== '7-2') return;
        const host = document.getElementById('mac');
        if (!host) return;
        const competencies = readRows('KOMPETENCJE'), roles = readRows('ROLE');
        const keep = new Set();
        competencies.forEach(c => roles.forEach(r => ['W', 'A'].forEach(level => {
            const key = `MAC.${c.id}.${r.id}.${level}`;
            keep.add(key);
            let field = [...host.querySelectorAll('[data-system-field]')].find(el => el.dataset.key === key);
            if (!field) {
                field = document.createElement('div');
                field.className = 'plant-question';
                Object.assign(field.dataset, {systemField:'', key, required:'1', when:'null'});
                const label = document.createElement('label'), strong = document.createElement('strong'), select = document.createElement('select');
                select.name = 'answers[' + key.split('.').join('][') + ']';
                select.add(new Option('Wybierz', ''));
                ['Nie dotyczy','Wie, gdzie szukać','Pod nadzorem','Samodzielnie','Uczy innych'].forEach((text,i) => select.add(new Option(i+' — '+text, String(i))));
                label.append(strong, select); field.append(label); host.append(field);
            }
            field.querySelector('strong').textContent = `${c.name} / ${r.name} — ${level === 'W' ? 'wymagany' : 'aktualny'}`;
        })));
        host.querySelectorAll('[data-system-field]').forEach(field => { if (!keep.has(field.dataset.key)) field.remove(); });
        const signature = JSON.stringify([competencies, roles]);
        if (host.dataset.matrixSignature !== signature) {
            const fields = new Map([...host.querySelectorAll('[data-system-field]')].map(el => [el.dataset.key, el]));
            const table = document.createElement('table'), head = table.createTHead().insertRow(), body = table.createTBody();
            ['Kompetencja', ...roles.map(r => r.name+' — W / A')].forEach(text => {
                const th = document.createElement('th'); th.textContent = text; head.append(th);
            });
            competencies.forEach(c => {
                const row = body.insertRow(); row.insertCell().textContent = c.name;
                roles.forEach(r => {
                    const cell = row.insertCell(); cell.style.minWidth = '180px';
                    ['W','A'].forEach(level => {
                        const field = fields.get(`MAC.${c.id}.${r.id}.${level}`);
                        field.style.padding = '6px'; field.style.margin = '0';
                        cell.append(field);
                    });
                });
            });
            const scroll = document.createElement('div'); scroll.className = 'plant-scroll'; scroll.dataset.competencyMatrix = ''; scroll.append(table);
            host.querySelector('[data-competency-matrix]')?.remove();
            host.append(scroll); host.dataset.matrixSignature = signature;
        }
        host.querySelectorAll('[data-system-field]').forEach(field => {
            const key = field.dataset.key;
            const partner = [...host.querySelectorAll('[data-system-field]')].find(el => el.dataset.key === key.slice(0,-1)+(key.endsWith('W')?'A':'W'));
            const own = field.querySelector('select').value, other = partner?.querySelector('select').value;
            const gap = own !== '' && other !== '' ? (key.endsWith('W') ? Number(own)-Number(other) : Number(other)-Number(own)) : 0;
            field.style.background = gap >= 2 ? '#fff0ee' : gap === 1 ? '#fff8df' : '';
            const cell = field.closest('td');
            if (cell && key.endsWith('W')) cell.dataset.sortValue = own + '/' + (other || '');
        });
        const pairs = competencies.flatMap(c => roles.map(r => ({id:c.id+'|'+r.id,name:c.name+' / '+r.name})));
        form.querySelectorAll('[data-repeater="LUKI"] [name$="[dotyczy]"]').forEach(el => selectOptions(el, pairs));
    };
    function refresh() {
        form.querySelectorAll('[data-history-row] input, [data-history-row] textarea').forEach(el => { el.readOnly = true; });
        if (section === '7-1') form.querySelectorAll('[data-repeater="ZADANIA"] [name$="[osoba]"]').forEach(el => selectOptions(el, readRows('OSOBY')));
        matrix();
    }
    form.addEventListener('system:rows-changed', refresh);
    form.addEventListener('draft:restored', refresh);
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
});
