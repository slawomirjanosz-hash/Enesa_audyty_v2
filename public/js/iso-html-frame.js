// Transport for the original HTML renderer. Authentication and persistence stay in Laravel.
(() => {
    const config = ENESA_HOST;
    // Source helpers concatenate some answer text into formatted notices. Keep
    // their formatting, but never execute markup supplied in a customer answer.
    const html = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
    const tags = new Set(['B','STRONG','EM','I','BR','CODE','SMALL','SPAN','P','UL','OL','LI','DIV','SUP','SUB','H2','H3','H4','TABLE','THEAD','TBODY','TFOOT','TR','TH','TD']);
    Object.defineProperty(Element.prototype, 'innerHTML', {...html, set(value) {
        if (!value) { html.set.call(this, value); return; }
        const template = document.createElement('template');
        html.set.call(template, String(value));
        template.content.querySelectorAll('*').forEach(node => {
            if (!tags.has(node.tagName)) { node.replaceWith(document.createTextNode(node.textContent)); return; }
            [...node.attributes].forEach(attribute => {
                if (attribute.name !== 'class' && !(['colspan','rowspan'].includes(attribute.name) && /^\d{1,3}$/.test(attribute.value))) node.removeAttribute(attribute.name);
            });
        });
        html.set.call(this, html.get.call(template));
    }});
    const send = (action, state = null) => parent.postMessage({enesa: config.channel, action, state}, '*');
    const clone = value => JSON.parse(JSON.stringify(value));
    const capture = () => Object.fromEntries(config.roots.filter(key => key in window).map(key => [key, clone(window[key])]));
    const applyContext = () => Object.entries(config.context || {}).forEach(([key, value]) => { window[key] = clone(value); });
    if (config.section === 'intro') {
        // Correct the supplied prototype's partial sum without changing its archived source.
        WO = config.energyFactors;
        window.policzTJ = () => {
            const result = EnesaPlantEnergy.calculate(ODP.FAKT_NOSNIKI, config.energyFactors, ['nie wiem','unknown'].includes(ODP.FAKT_NOSNIKI));
            // Detailed diagnostics below already distinguish quantities from units;
            // the prototype's blanket calorific-value warning would be misleading.
            BRAKI_WO = [];
            return {wartosc: result.value, opis: result.detail};
        };
        const fillAnswers = window.wypelnijZOdp;
        window.wypelnijZOdp = () => {
            SCHEMA.sekcje.flatMap(section => section.pytania).forEach(question => {
                if (['number','year'].includes(question.typ)) {
                    const number = EnesaPlantEnergy.number(ODP[question.kod]);
                    if (number !== null) ODP[question.kod] = String(number);
                }
                if (question.typ === 'repeater' && Array.isArray(ODP[question.kod])) ODP[question.kod].forEach(row => {
                    if (!row || typeof row !== 'object') return;
                    question.kolumny.forEach((column, index) => {
                        const number = EnesaPlantEnergy.number(row['c' + index]);
                        if (column.typ === 'number' && number !== null) row['c' + index] = String(number);
                    });
                });
            });
            fillAnswers();
        };
        window.policzPaliwa = () => {
            const value = EnesaPlantEnergy.fossil(ODP.FAKT_KOTLOWNIA, ODP.FAKT_CIEPLO_PALIWO);
            return {wartosc: value, opis: value === 'do potwierdzenia' ? 'Paliwo ciepła sieciowego należy potwierdzić u dostawcy.' : value === null ? 'Uzupełnij źródło ciepła i paliwa.' : 'Obliczane na podstawie aktualnego źródła ciepła i wybranych paliw.'};
        };
        const refresh = window.odswiez;
        window.odswiez = () => {
            refresh();
            const question = document.getElementById('q_FAKT_NOSNIKI');
            if (!question) return;
            const result = EnesaPlantEnergy.calculate(ODP.FAKT_NOSNIKI, config.energyFactors, ['nie wiem','unknown'].includes(ODP.FAKT_NOSNIKI));
            let notice = question.querySelector('[data-energy-errors]');
            if (!notice) { notice = document.createElement('div'); notice.dataset.energyErrors = ''; notice.className = 'warn'; notice.setAttribute('role','status'); question.append(notice); }
            notice.textContent = result.value === null ? result.detail : '';
            notice.hidden = result.value !== null;
            question.querySelectorAll('tbody tr').forEach((row, i) => {
                row.dataset.energyIndex ??= String(i);
                const invalid = result.errors[row.dataset.energyIndex];
                row.title = invalid || '';
                row.querySelectorAll('input,select').forEach(control => { control.setAttribute('aria-invalid', invalid ? 'true' : 'false'); control.style.borderColor = invalid ? '#c62828' : ''; });
            });
        };
        document.addEventListener('input', event => { if (event.target.closest('#q_FAKT_NOSNIKI')) odswiez(); });
    }
    if (['4-1','4-2'].includes(config.section)) {
        const calculateSources = window.przeliczWyl;
        window.przeliczWyl = () => {
            calculateSources();
            // Original renderers recompute a partial, two-decimal fuel sum here.
            // Production conditions must use the authoritative current profile facts.
            const facts = config.preview ? ODP : (config.context.ODP || {});
            Object.assign(WYL, EnesaPlantEnergy.sourceFacts(facts, config.energyFactors, !config.preview));
        };
    }
    const initial = capture();
    // The supplied examples declare the renderer's types (in particular boolean
    // switches). Platform form strings must not turn "nie" into a truthy flag.
    if (typeof przyklad === 'function') przyklad();
    const types = capture();
    Object.entries(initial).forEach(([key, value]) => { window[key] = value; });
    const combine = (left, right) => {
        if (left === undefined || left === null) return right;
        if (right === undefined || right === null) return left;
        if (Array.isArray(left) && Array.isArray(right)) return [...left, ...right];
        if (typeof left === 'object' && !Array.isArray(left) && typeof right === 'object' && !Array.isArray(right)) {
            return Object.fromEntries([...new Set([...Object.keys(left), ...Object.keys(right)])].map(key => [key, combine(left[key], right[key])]));
        }
        return left;
    };
    const typed = (value, example, fallback, path) => {
        if (typeof example === 'boolean') return value === true || value === 'tak';
        if (Array.isArray(example)) {
            const row = example.reduce(combine, undefined);
            return Array.isArray(value) ? value.map((item, i) => typed(item, row, fallback?.[i], path + '.*')) : value;
        }
        if (example && typeof example === 'object' || fallback && !Array.isArray(fallback) && typeof fallback === 'object') {
            const rows = example ? Object.values(example) : [];
            const common = config.section === '6-3' && path === 'SEU' ? rows.reduce(combine, undefined) : undefined;
            const booleanMap = rows.length && rows.every(item => typeof item === 'boolean');
            return Object.fromEntries(Object.entries(value || {}).map(([key, item]) => [key, typed(item, booleanMap ? false : combine(example?.[key], common), fallback?.[key], path + '.' + key)]));
        }
        return value;
    };
    const values = config.initialDirty ? types : config.state;
    Object.entries(values || {}).forEach(([key, value]) => { window[key] = typed(clone(value), types[key], initial[key], key); });
    if (!config.preview || !config.initialDirty) applyContext();
    if ('HISTORIA' in window) HISTORIA = config.history || [];
    if ('ROLA' in window) ROLA = config.client ? 'klient' : 'konsultant';
    if (!config.preview) document.querySelectorAll('#rola_klient,#rola_kons').forEach(button => button.remove());
    if (config.section !== 'intro' && config.identity) {
        const identity = document.querySelector('header .sub'); if (identity) identity.textContent = config.identity;
    }
    if (config.client && !config.preview) document.querySelectorAll('#t_spec,#tab_s').forEach(button => button.remove());
    const nativeDraw = window.rysuj;
    if (nativeDraw) window.rysuj = function () {
        if (config.section === '7-5' && typeof WYKAZ !== 'undefined') WYKAZ.forEach(row => { if ((row.rodzaj || '').replaceAll(' ', '') === 'D/Z') row.rodzaj = 'DZ'; });
        nativeDraw();
        const progress = document.getElementById('postep');
        const summary = document.getElementById('postep_strona');
        if (progress && summary) {
            summary.textContent = progress.textContent;
            const percent = progress.textContent.match(/(\d+)%/);
            const track = document.getElementById('tor_strona'); if (track && percent) track.style.width = percent[1] + '%';
        }
        if (!config.editable || (typeof AKT !== 'undefined' && AKT === 'kons' && !config.consultant && !config.preview)) document.querySelectorAll('#tresc input,#tresc textarea,#tresc select,#tresc .dodaj,#tresc .link').forEach(control => { control.disabled = true; });
        send('tab', {id: typeof AKT === 'undefined' ? '' : AKT});
    };
    const operation = name => {
        if (config.preview) { alert('Podgląd HTML: odpowiedzi nie są zapisywane ani zatwierdzane.'); return; }
        if (!config.editable && name === 'save') return;
        send(name, capture());
    };
    if (config.section === 'intro') window.przekaz = id => {
        if (!config.editable) return;
        const section = SCHEMA.sekcje.find(item => item.id === id);
        const missing = widoczne(section).filter(question => !pelna(question));
        if (missing.length) {
            alert('Sekcja ma ' + missing.length + ' pytań bez odpowiedzi:\n\n' + missing.slice(0, 12).map(question => '• ' + question.pytanie).join('\n') + '\n\nOdpowiedź „nie wiem” jest odpowiedzią i nie blokuje przekazania.');
            return;
        }
        STAN[id] = 'przekazana'; odswiez(); operation('save');
    };
    window.zapiszRecznie = () => {
        if (typeof AKT !== 'undefined' && AKT === 'kons') {
            if (!config.consultant) { alert('Analizę można zapisać po zatwierdzeniu ankiety przez klienta.'); return; }
            operation('consultant');
        } else operation('save');
    };
    // These functions update the prototype state only. They must not display a fictitious save.
    window.zapisz = () => send('dirty');
    window.zapiszLokalnie = () => send('dirty');
    if (config.client && !config.preview || !config.editable) document.querySelectorAll('[onclick*="przyklad"]').forEach(button => button.remove());
    if (!config.editable) document.querySelectorAll('[onclick*="wyczysc"]').forEach(button => button.remove());
    const originalExample = window.przyklad;
    if (originalExample) window.przyklad = () => { originalExample(); if (!config.preview) applyContext(); window.rysuj?.(); send('dirty'); };
    if (typeof wyczysc === 'function') {
        const clear = wyczysc; window.wyczysc = () => { clear(); applyContext(); window.rysuj?.(); };
    }
    if (typeof widok === 'function') widok('ankieta');
    if (typeof rysuj === 'function') rysuj();
    if (typeof wypelnijZOdp === 'function') { wypelnijZOdp(); odswiez(); }
    (config.unknown || []).forEach(key => {
        const question = document.getElementById('q_' + key);
        if (!question || ODP[key] !== 'nie wiem') return;
        const badge = document.createElement('span'); badge.className = 'tagw'; badge.textContent = 'Nie wiem — zachowana odpowiedź';
        question.querySelector('.qhead')?.append(badge);
        question.addEventListener('input', () => { if (ODP[key] !== 'nie wiem') badge.remove(); });
        question.addEventListener('change', () => { if (ODP[key] !== 'nie wiem') badge.remove(); });
    });
    if (config.section === 'intro' && !config.editable) document.querySelectorAll('#widok_ankieta input,#widok_ankieta select,#widok_ankieta textarea,#widok_ankieta .dodaj,#widok_ankieta .sek .stopka button').forEach(control => { control.disabled = true; });
    if (config.section !== 'intro' && !config.preview) document.querySelectorAll('input[type="file"],[onclick*="plik"]').forEach(control => {
        control.disabled = true; control.title = 'Profil jest pobierany z aktualnych danych zakładu w platformie.';
    });
    document.addEventListener('input', () => send('dirty'));
    document.addEventListener('change', () => send('dirty'));
    const footer = document.querySelector('.stopka') || document.createElement('div');
    if (!footer.isConnected) document.body.append(footer);
    const actionButton = (label, action) => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'prim';
        button.textContent = label; button.addEventListener('click', () => operation(action)); footer.append(button);
    };
    const submitButtons = [...document.querySelectorAll('[onclick*="zatwierdz()"]')];
    if (!submitButtons.length && config.submit) actionButton('Zatwierdź jako klient', 'submit');
    const originalApprove = window.zatwierdz;
    if (originalApprove) window.zatwierdz = () => {
        const tabs = typeof ZAKLADKI === 'function' ? ZAKLADKI() : ZAKLADKI;
        if (tabs?.length) { AKT = tabs[tabs.length - 1].id; rysuj(); }
        if (!config.submit) return;
        if (typeof braki === 'function' && braki().some(item => typeof item === 'string' || item.kto === 'klient')) return;
        operation('submit');
    };
    submitButtons.forEach(button => {
        button.disabled = !config.submit && !config.preview;
        if (!config.submit) button.title = config.preview ? 'Podgląd zatwierdzeń — bez zmiany danych' : config.submitHint;
    });
    if (config.section === 'intro' && config.editable) actionButton('Zapisz', 'save');
    if (config.consultant) actionButton('Zapisz analizę konsultanta', 'consultant');
    document.querySelectorAll('[onclick*="zapiszRecznie"]').forEach(button => { button.disabled = !config.editable; });
    document.querySelectorAll('a.wroc').forEach(link => { link.addEventListener('click', event => { event.preventDefault(); send('back'); }); });
    // The shared sorter requires semantic table sections. Preserve source grouping rows.
    const tables = root => {
        const found = [...(root.querySelectorAll?.('table') || [])];
        if (root.matches?.('table')) found.push(root);
        found.forEach(table => {
            if (!table.tHead && table.rows[0]?.querySelector('th')) {
                const head = table.createTHead(); head.append(table.rows[0]);
            }
            table.querySelectorAll('tr.grupa,tr.suma,tr.razem').forEach(row => { row.dataset.sortFixed = ''; });
            [...table.rows].forEach(row => {
                if (/^(razem|suma|ogółem|łącznie)(\s|$)/i.test(row.cells[0]?.textContent.trim() || '')) row.dataset.sortFixed = '';
                [...row.cells].forEach(cell => {
                    if (cell.tagName === 'TH' || cell.hasAttribute('data-sort-value') && cell.dataset.isoRawSort !== '1') return;
                    const control = cell.querySelector('input[type="number"],input[type="date"]');
                    const text = control ? control.value : cell.textContent.trim();
                    let raw = null;
                    if (/^\d{4}-\d{2}-\d{2}/.test(text)) raw = text;
                    else {
                        const number = text.replaceAll('\u00a0', ' ').replaceAll('\u202f', ' ').match(/^([+-]?[\d\s.,]+)\s*(?:MWh(?:\/rok)?|kWh(?:\/rok)?|GJ|TJ|kW|MW|PLN|zł(?:\/rok)?|%|godz\.?|h|dni)?$/i);
                        if (number) {
                            let value = number[1].replaceAll(/\s/g, '');
                            if (value.includes(',')) value = value.replaceAll('.', '').replace(',', '.');
                            if (value && Number.isFinite(Number(value))) raw = String(Number(value));
                        }
                    }
                    if (raw !== null) { cell.dataset.sortValue = raw; cell.dataset.isoRawSort = '1'; }
                    else if (cell.dataset.isoRawSort === '1') { delete cell.dataset.sortValue; delete cell.dataset.isoRawSort; }
                });
            });
        });
    };
    tables(document);
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        const element = node.nodeType === 1 ? node : node.parentElement;
        if (element) tables(element.closest('table') || element.parentElement || element);
    }))).observe(document.body, {childList: true, subtree: true});
    document.addEventListener('input', event => { const table = event.target.closest('table'); if (table) tables(table); });
    document.addEventListener('change', event => { const table = event.target.closest('table'); if (table) tables(table); });
    if (config.preview) {
        const message = document.createElement('div'); message.className = 'note';
        message.textContent = 'Podgląd oryginalnego HTML — bez zapisu i rzeczywistych zatwierdzeń.';
        document.body.prepend(message);
    }
    window.addEventListener('message', event => {
        if (event.source !== parent || event.data?.enesa !== config.channel) return;
        if (event.data.action === 'capture') operation(event.data.operation);
    });
    if (config.initialDirty && !config.preview) send('dirty');
})();
