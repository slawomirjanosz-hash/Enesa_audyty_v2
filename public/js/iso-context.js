(() => {
    const definition = window.isoContextDefinition;
    const form = document.getElementById('context-form');
    if (!form || !definition) return;
    let step = 0, dirty = false;
    const status = document.querySelector('[data-status]');
    const facts = () => Object.fromEntries([...form.querySelectorAll('[data-fact]')].map(el => [el.dataset.fact, el.value]));
    function matches(rule, values) {
        if (rule.always) return true;
        if (rule.any) return rule.any.some(child => matches(child, values));
        if (rule.all) return rule.all.every(child => matches(child, values));
        const value = values[rule.field];
        if (value === undefined || value === '') return false;
        switch(rule.op) {
            case '===': return String(value) === String(rule.value);
            case '!==': return String(value) !== String(rule.value);
            case '>': return Number(value) > rule.value;
            case '>=': return Number(value) >= rule.value;
            case '<': return Number(value) < rule.value;
            case '<=': return Number(value) <= rule.value;
            default: return false;
        }
    }
    const article = factor => form.querySelector('[data-factor="'+factor.id+'"]');
    const chosen = () => definition.factors.filter(f => !article(f).hidden && article(f).querySelector('input').checked);
    const text = factor => article(factor).querySelector('textarea')?.value ?? factor.text;
    function filterFactors() {
        const values = facts();
        definition.factors.forEach(f => {
            const el = article(f), active = matches(f.condition, values), checkbox = el.querySelector('input'), edit = el.querySelector('textarea');
            el.hidden = !active;
            checkbox.disabled = !active || f.automatic;
            if (f.automatic) checkbox.checked = active;
            if (edit) { edit.hidden = !checkbox.checked; edit.disabled = !active; }
        });
        form.querySelectorAll('[data-dimension]').forEach(group => group.hidden = ![...group.querySelectorAll('[data-factor]')].some(el => !el.hidden));
        document.querySelector('[data-factor-count]').textContent = 'Dopasowano '+definition.factors.filter(f=>!article(f).hidden).length+' z '+definition.factors.length+' czynników. Wybrano: '+chosen().length+'.';
    }
    function consultant() {
        const selected = chosen();
        const types = {strengths:['W','+'],weaknesses:['W','-'],opportunities:['Z','+'],threats:['Z','-']};
        Object.entries(types).forEach(([key,[area,impact]]) => {
            const list=selected.filter(f=>f.dimension.startsWith(area)&&f.impact===impact);
            document.querySelector('[data-swot-hint="'+key+'"]').textContent = 'Podpowiedź: '+list.length+' wybranych czynników. '+list.map(text).join(' ');
        });
        form.querySelectorAll('[data-suggestions]').forEach(container => {
            container.replaceChildren();
            selected.forEach(f => {
                const button=document.createElement('button');button.type='button';button.textContent='Wstaw: '+text(f).slice(0,75)+'…';
                button.addEventListener('click',()=>{const row=container.closest('[data-conclusion]');row.querySelector('[data-conclusion-field=finding]').value=text(f);row.querySelector('[data-conclusion-field=decision]').value=f.effect;markDirty();});
                container.append(button);
            });
        });
    }
    function preview() {
        const root=document.getElementById('context-document-preview');root.replaceChildren();
        const add=(tag,value)=>{const node=document.createElement(tag);node.textContent=value;root.append(node);return node;};
        const values=facts(), fill=v=>v?.trim()||'[do uzupełnienia]';
        const table=(headers,rows)=>{const wrap=document.createElement('div');wrap.className='table-wrap';const t=document.createElement('table');const head=t.createTHead().insertRow();headers.forEach(h=>{const th=document.createElement('th');th.textContent=h;head.append(th);});const body=t.createTBody();rows.forEach(row=>{const tr=body.insertRow();row.forEach(value=>{tr.insertCell().textContent=String(value);});});wrap.append(t);root.append(wrap);};
        add('strong','D-EnMS-KON-01');add('h2','Kontekst organizacji i strony zainteresowane');
        add('p','PN-EN ISO 50001:2018 · 4.1 i 4.2');
        add('p','Organizacja: '+fill(values.organization));add('p','Zakres systemu: '+fill(values.scope));
        add('p','Data opracowania: '+new Date().toLocaleDateString('pl-PL'));
        add('h3','1. Cel dokumentu');add('p','Identyfikacja czynników wewnętrznych i zewnętrznych oraz wymagań stron zainteresowanych jako podstawa zakresu systemu, ryzyk i szans oraz celów energetycznych.');
        [['W','2. Kontekst wewnętrzny'],['Z','3. Kontekst zewnętrzny']].forEach(([prefix,title])=>{
            add('h3',title);
            Object.entries(definition.dimensions).filter(([d])=>d.startsWith(prefix)).forEach(([dimension,label])=>{
                const items=chosen().filter(f=>f.dimension===dimension);if(!items.length)return;
                add('h4',label);table(['Zidentyfikowany czynnik','Wpływ','Skutek dla systemu'],items.map(f=>[text(f),f.impact==='o'?'○':f.impact,f.effect]));
            });
        });
        add('h3','4. Synteza — analiza SWOT');
        const swot=[...form.querySelectorAll('[data-swot]')];
        table(['Obszar','Analiza'],swot.map(el=>[definition.swot[el.dataset.swot],fill(el.value)]));
        add('h3','5. Strony zainteresowane');
        table(['Strona','Typ','Wymagania i oczekiwania','Wymóg zgodności'],definition.stakeholders.map(row=>row.map(v=>v==='@customers'?(values.customers_co2==='tak'?'Informacja o śladzie węglowym i efektywności wyrobu':'Brak zgłoszonych wymagań energetycznych'):v==='@customerCompliance'?(values.customers_co2==='tak'?'do rozstrzygnięcia':'nie'):v)));
        add('h3','6. Wnioski — jak kontekst ukształtował system');
        const rows=[...form.querySelectorAll('[data-conclusion]')].map((row,i)=>[i+1,...[...row.querySelectorAll('textarea')].map(el=>fill(el.value))]);
        table(['Lp.','Wniosek','Decyzja projektowa','Dokument'],rows);
        add('h3','7. Dokumenty powiązane i aktualizacja');table(['Dokument','Powiązanie'],definition.relatedDocuments);
        add('p','Dokument podlega przeglądowi co najmniej raz w roku, przed Przeglądem Zarządzania, lub przy istotnej zmianie otoczenia organizacji.');
        add('p','Opracował (Energy Manager): ...................................... Data: ....................');
        add('p','Zatwierdził (Zarząd): ...................................... Data: ....................');
        const gaps=[values.organization,values.scope,...swot.map(el=>el.value),...[...form.querySelectorAll('[data-conclusion]')].flatMap(row=>[row.querySelector('[data-conclusion-field=finding]').value,row.querySelector('[data-conclusion-field=decision]').value])].filter(v=>!v?.trim()).length;
        document.querySelector('[data-completeness]').textContent=gaps?'Wersja robocza — do uzupełnienia: '+gaps+' pól.':'Uzupełniono pola dokumentu — gotowy do przeglądu i zatwierdzenia.';
    }
    function show(index) {
        step=Math.max(0,Math.min(3,index));filterFactors();
        if(step===2)consultant();if(step===3)preview();
        form.querySelectorAll('[data-panel]').forEach(el=>el.hidden=Number(el.dataset.panel)!==step);
        document.querySelectorAll('[data-step]').forEach(el=>{if(Number(el.dataset.step)===step)el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});
        document.querySelector('[data-back]').disabled=step===0;document.querySelector('[data-next]').hidden=step===3;
        window.scrollTo({top:0,behavior:'instant'});
    }
    function markDirty(){dirty=true;status.textContent=form.hasAttribute('action')?'Niezapisane zmiany':'Podgląd wzoru';}
    form.addEventListener('input',markDirty);
    form.addEventListener('change',()=>{markDirty();filterFactors();});
    document.querySelectorAll('[data-step]').forEach(el=>el.addEventListener('click',()=>show(Number(el.dataset.step))));
    document.querySelector('[data-back]').addEventListener('click',()=>show(step-1));
    document.querySelector('[data-next]').addEventListener('click',()=>show(step+1));
    form.addEventListener('submit',async event=>{
        if(!form.hasAttribute('action')){event.preventDefault();return;}
        event.preventDefault();
        const exporting=event.submitter?.hasAttribute('data-export');
        const button=event.submitter ?? form.querySelector('[data-save]');
        const url=exporting?button.formAction:form.action;
        const savingDocument=button.dataset.saveDocument;
        const previewing=exporting&&button.formTarget==='_blank';
        const previewWindow=previewing?window.open('about:blank','_blank'):null;
        const revision=new URLSearchParams(new FormData(form)).toString();
        button.disabled=true;status.textContent=exporting?'Generowanie dokumentu…':'Zapisywanie…';
        try {
            const res=await fetch(url,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});
            if(!res.ok){const data=await res.json();throw new Error(Object.values(data.errors??{}).flat().join(' ')||'Nie udało się zapisać.');}
            if(exporting && !savingDocument) {
                const blob=await res.blob(), blobUrl=URL.createObjectURL(blob);
                if(previewWindow)previewWindow.location.href=blobUrl;
                else {
                    const link=document.createElement('a');
                    link.href=blobUrl;link.download=res.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1]??'D-EnMS-KON-01.pdf';
                    document.body.append(link);link.click();link.remove();
                }
                setTimeout(()=>URL.revokeObjectURL(blobUrl),60000);
            }
            dirty=revision!==new URLSearchParams(new FormData(form)).toString();
            if(savingDocument) {
                const notice=document.querySelector('[data-document-saved]');
                notice.querySelector('span').textContent='Dokument '+savingDocument+' zapisano w dokumentacji klienta (punkt 4.1).';
                notice.hidden=false;
            }
            status.textContent=dirty?'Niezapisane zmiany':savingDocument?'Dokument '+savingDocument+' zapisano w dokumentacji klienta.':exporting?'Dokument gotowy. Dane zapisano.':'Zapisano';
        } catch(error){previewWindow?.close();status.textContent=error.message;} finally{button.disabled=false;}
    });
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    show(0);
})();
