document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', event => {
        if (event.target.closest('[data-add-conclusion]')) {
            const host = document.querySelector('[data-conclusions]');
            if (host.children.length >= 20) return;
            const row = host.firstElementChild.cloneNode(true);
            const indices = [...host.querySelectorAll('textarea')].map(el=>Number(el.name.match(/\[(\d+)\]/)?.[1]||0));
            const next = Math.max(0,...indices)+1;
            row.querySelectorAll('textarea').forEach(el=>{el.name=el.name.replace(/\[\d+\]/,'['+next+']');el.value='';});
            host.appendChild(row);
        }
        const remove=event.target.closest('[data-remove-conclusion]');
        if (remove && remove.closest('[data-conclusions]').children.length>1) remove.closest('.system-row').remove();
    });
    const form = document.getElementById('system-form');
    if (!form) return;
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => { if (window.formDraftLeaving) return; if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    document.querySelectorAll('[data-review-action]').forEach(action => action.addEventListener('submit', event => {
        if (dirty) { event.preventDefault(); alert('Najpierw zapisz zmienione odpowiedzi.'); }
    }));
    const name = key => 'answers[' + key.split('.').join('][') + ']';
    const values = key => [...form.querySelectorAll('[name]')].filter(el => el.name === name(key) || el.name === name(key) + '[]').filter(el => el.type !== 'hidden' && (el.type !== 'checkbox' || el.checked)).map(el => el.value.trim()).filter(Boolean);
    function update() {
        let total = 0, done = 0;
        form.querySelectorAll('[data-system-field]').forEach(el => {
            const condition = JSON.parse(el.dataset.when || 'null');
            const visible = !condition || condition[1].includes(values(condition[0])[0] || '');
            el.hidden = !visible;
            if (visible && el.dataset.required === '1') {
                total++;
                if (values(el.dataset.key).length) done++;
            }
        });
        const section = window.location.pathname.split('/').filter(Boolean).pop();
        const minimum = section.startsWith('6-') ? Object.fromEntries([...form.querySelectorAll('[data-repeater]')].filter(el=>el.dataset.repeater!=='REWIZJE').map(el=>[el.dataset.repeater,1])) : section === '5-1' ? {CZLON:1,DECYZJE:3} : section === '5-2' ? {PRZEGLADY:1} : section === '5-3' ? {ROLE:1,UPR:1} : {GRANICE:1,UPRAW:1};
        Object.entries(minimum).forEach(([root,min]) => {
            const container = form.querySelector(`[data-repeater="${root}"]`);
            if (container) total += Math.max(0, min-container.querySelectorAll('.system-row').length);
        });
        if (section === '5-3') {
            for (let i=1;i<=18;i++) {
                const code = 'MAT-'+String(i).padStart(2,'0');
                const owners = [...form.querySelectorAll('[data-system-field]')].filter(el=>el.dataset.key.endsWith('.MAT.'+code) && values(el.dataset.key)[0]==='O').length;
                total++;
                if (owners===1 || (owners>1 && values('UWAGI.'+code).length)) done++;
            }
        }
        const indicator = document.querySelector('[data-system-progress]');
        indicator.textContent = `Wypełnienie: ${total ? Math.floor(100 * done / total) : 0}% · ${done} / ${total}`;
        const energy = form.querySelector('[data-energy-fields]');
        if (energy) {
            const ready = [...form.querySelectorAll('[data-repeater="GRANICE"] .system-row')].some(row => ['nazwa','adres','fizyczna'].every(key => [...row.querySelectorAll('[name]')].some(el => el.name.endsWith(`[${key}]`) && el.value.trim())));
            energy.hidden = !ready;
            form.querySelector('[data-energy-locked]').hidden = ready;
        }
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    let next = Math.max(0, ...[...form.querySelectorAll('[data-key]')].map(el => Number(el.dataset.key.split('.')[1]) || 0)) + 1;
    form.addEventListener('draft:discarded', () => { dirty = false; });
    form.addEventListener('draft:restored', () => { next=Math.max(0,...[...form.querySelectorAll('[data-key]')].map(el=>Number(el.dataset.key.split('.')[1])||0))+1; update(); });
    form.addEventListener('click', event => {
        const add = event.target.closest('[data-add-row]');
        if (add) {
            const root = add.dataset.addRow;
            const host = form.querySelector(`[data-repeater="${root}"]`);
            if (host.children.length >= 20) { alert('Maksymalnie 20 pozycji.'); return; }
            host.insertAdjacentHTML('beforeend', document.getElementById('template-' + root).innerHTML.replaceAll('__INDEX__', String(next++)));
            dirty = true;
            update();
        }
        const remove = event.target.closest('[data-remove-row]');
        if (remove && confirm('Usunąć tę pozycję z formularza? Zmiana zostanie utrwalona po zapisaniu.')) { remove.closest('.system-row').remove(); dirty = true; update(); }
    });
    document.getElementById('system-example')?.addEventListener('click', () => {
        if (!confirm('Uzupełnić puste pola klienta przykładowymi danymi? Sprawdź je przed zapisaniem.')) return;
        for (let round = 0; round < 2; round++) {
            form.querySelectorAll('[data-system-field]').forEach(field => {
                if (field.hidden || values(field.dataset.key).length || field.dataset.key.includes('.MAT.')) return;
                field.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(el => {
                    if (el.type === 'checkbox') el.checked = true;
                    else if (el.tagName === 'SELECT') el.selectedIndex = Math.min(1, el.options.length - 1);
                    else if (el.type === 'date') el.value = new Date().toISOString().slice(0, 10);
                    else if (el.dataset.example) el.value = el.dataset.example;
                    else if (field.dataset.key==='POL.wstep') el.value = 'Zobowiązujemy się do realizacji następujących zasad.';
                    else if (/^ROLE\.\d+\.id$/.test(field.dataset.key)) el.value = 'R' + field.dataset.key.split('.')[1];
                    else el.value = 'PRZYKŁAD — do weryfikacji: ' + field.querySelector('strong').textContent;
                });
            });
            update();
        }
        const roleRows = [...form.querySelectorAll('[data-repeater="ROLE"] .system-row')];
        roleRows.forEach((row,index)=>row.querySelectorAll('[data-system-field]').forEach(field=>{
            if(field.dataset.key.includes('.MAT.')) {
                const select=field.querySelector('select');
                if(!select.value) select.value=index===0?'O':'I';
            }
        }));
        form.dispatchEvent(new Event('input', {bubbles:true}));
    });
    update();
});
