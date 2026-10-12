(() => {
    const script = document.querySelector('script[data-draft-endpoint]');
    const skip = /^(?:_token|_method|_draft_.*)$/;
    const guard = /^(?:lock_version|source_hash|revision)$/;
    const states = [];
    // Per-tab emergency copies never cross accounts, and expire after one day.
    const localPrefix = 'offer-emergency:'+script?.dataset.draftUser+':';
    try {
        if(script?.dataset.draftCleared)sessionStorage.removeItem(localPrefix+script.dataset.draftCleared);
        Object.keys(sessionStorage).filter(key=>key.startsWith('offer-emergency:')).forEach(key=>{
            const data=JSON.parse(sessionStorage.getItem(key));
            if(!data?.savedAt||Date.now()-data.savedAt>86400000)sessionStorage.removeItem(key);
        });
    }catch(_) { /* Storage can be blocked by browser privacy settings. */ }
    const digest = async value => [...new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(value)))].map(v=>v.toString(16).padStart(2,'0')).join('');
    const controls = form => [...form.querySelectorAll('input[name],select[name],textarea[name]')].filter(el=>!skip.test(el.name)&&!['file','password','submit','button'].includes(el.type));
    const fields = form => controls(form).map(el=>({name:el.name,value:el.value,checked:el.checked,values:el.multiple?[...el.selectedOptions].map(o=>o.value):undefined}));
    function groups(form) {
        return [...form.querySelectorAll('[data-repeat]')].map(el=>({host:el.querySelector('[data-rows]'),template:el.querySelector('template'),parent:el}))
            .concat([...form.querySelectorAll('[data-repeater]')].map(el=>({host:el,template:document.getElementById('template-'+el.dataset.repeater)})))
            .concat([{host:form.querySelector('#custom-rows'),template:document.getElementById('custom-template')},{host:form.querySelector('#stakeholder-custom'),template:document.getElementById('stakeholder-template')}])
            .filter(g=>g.host&&g.template);
    }
    function snapshot(form) {
        return {fields:fields(form),groups:groups(form).map(g=>[...g.host.children].map(row=>Number(row.querySelector('[name]')?.name.match(/\[(\d+)\]/)?.[1]??0))),extra:window.formDraftAdapters?.[form.id]?.capture()??null};
    }
    function restore(form, data) {
        groups(form).forEach((g,i)=>{
            const indices=data.groups?.[i]??[];
            if(indices.some(n=>!Number.isInteger(n)||n<0||n>100000)||indices.length>200)throw Error('Nieprawidłowe wiersze kopii.');
            g.host.replaceChildren();
            indices.forEach(n=>g.host.insertAdjacentHTML('beforeend',g.template.innerHTML.replaceAll('__INDEX__',String(n))));
            if(g.parent)g.parent.dataset.next=String(Math.max(-1,...indices)+1);
        });
        if(data.extra)window.formDraftAdapters?.[form.id]?.restore(data.extra);
        const available=controls(form),used=new Set();
        for(const field of data.fields??[]) {
            if(guard.test(field.name))continue; // Never replace current concurrency/source guards.
            const el=available.find(el=>el.name===field.name&&!used.has(el));
            if(!el)continue;used.add(el);
            if(['checkbox','radio'].includes(el.type))el.checked=!!field.checked;
            else if(el.multiple)[...el.options].forEach(o=>o.selected=field.values?.includes(o.value));
            else el.value=field.value;
        }
        window.formDraftAdapters?.[form.id]?.afterRestore?.();
        form.dispatchEvent(new Event('draft:restored',{bubbles:true}));
        form.dispatchEvent(new Event('change',{bubbles:true}));
    }
    async function mount(form) {
        const initial=snapshot(form),base=await digest(JSON.stringify(initial));
        const key=await digest(location.pathname+'|'+form.action+'|'+(form.dataset.draftName||form.id||'main'));
        const isOffer=form.id==='offer-form',localKey=localPrefix+key;
        const toolbar=document.createElement('div');toolbar.className='draft-toolbar';
        toolbar.innerHTML='<span class="draft-state" role="status" aria-live="polite">Łączenie z autozapisem…</span><label class="draft-switch"><input type="checkbox" role="switch" checked aria-label="Autozapis"><span>Autozapis</span></label><button type="button" class="draft-save">Zapisz</button><button type="button" class="draft-exit">Wyjdź bez zapisywania</button><div class="draft-recovery" hidden><span>Znaleziono kopię roboczą.</span><button type="button" class="draft-restore">Przywróć kopię</button><button type="button" class="draft-discard">Odrzuć kopię</button></div>';
        form.prepend(toolbar);
        ['input','change'].forEach(type=>toolbar.addEventListener(type,event=>event.stopPropagation()));
        const headers=[...document.querySelectorAll('#topbar,#editor-topbar,.plant-nav,.review-section-nav')];
        const offset=()=>{
            const modal=form.closest('dialog,.project-modal,.modal-overlay,.aw-modal,.modal');
            toolbar.style.top=(modal?0:headers.filter(el=>el.getClientRects().length&&['fixed','sticky'].includes(getComputedStyle(el).position)).reduce((sum,el)=>sum+el.getBoundingClientRect().height,0))+'px';
        };
        const resize=new ResizeObserver(offset);headers.forEach(el=>resize.observe(el));offset();
        const label=toolbar.querySelector('.draft-state'),toggle=toolbar.querySelector('input'),recovery=toolbar.querySelector('.draft-recovery');
        const keyInput=document.createElement('input');keyInput.type='hidden';keyInput.name='_draft_key';keyInput.value=key;form.append(keyInput);
        const revInput=document.createElement('input');revInput.type='hidden';revInput.name='_draft_revision';revInput.value='0';form.append(revInput);
        const state={form,toggle,revision:0,ready:false,pending:null,stop:false,last:JSON.stringify(initial),initial,base};states.push(state);
        const report=(text,error=false)=>{label.textContent=text;label.dataset.error=String(error);};
        const removeLocal=()=>{try{sessionStorage.removeItem(localKey);}catch(_) {}};
        const readLocal=()=>{try{return JSON.parse(sessionStorage.getItem(localKey));}catch(_){return null;}};
        state.backup=()=>{
            if(!isOffer||!script.dataset.draftUser||state.stop||!toggle.checked||!recovery.hidden||!form.getClientRects().length)return;
            const current=snapshot(form);
            if(JSON.stringify(current)===JSON.stringify(initial))return;
            try{sessionStorage.setItem(localKey,JSON.stringify({payload:{base,snapshot:current},revision:state.revision,savedAt:Date.now()}));}
            catch(_){report('Nie można zapisać kopii awaryjnej w przeglądarce. Użyj Zapisz.',true);}
        };
        async function api(method,payload) {
            const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),10000);
            let response;
            try{response=await fetch(script.dataset.draftEndpoint+'/'+key,{method,signal:controller.signal,headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':script.dataset.csrf},...(method==='GET'?{}:{body:JSON.stringify({revision:state.revision,payload})})});}
            finally{clearTimeout(timeout);}
            const data=await response.json().catch(()=>({}));
            if(response.status===401||response.status===419||response.redirected){
                state.backup();
                throw Error('Sesja wygasła. Zaloguj się ponownie i wróć do formularza, aby przywrócić kopię.');
            }
            if(!response.ok)throw Error(data.message||'Autozapis niedostępny. Użyj przycisku Zapisz.');
            state.revision=data.revision;revInput.value=String(data.revision);return data;
        }
        const idleLabel=isOffer?'Autozapis oferty · kopia co 30 s':'Autozapis co 5 min · kopia robocza';
        async function connect(recoverLocal=true) {
            const data=await api('GET');state.ready=true;
            const local=isOffer&&recoverLocal?readLocal():null;
            // A confirmed manual save/discard wins. Otherwise retain unsent local
            // edits even if a previous in-flight autosave reached the server.
            const consumed=local&&data.revision>local.revision&&!data.payload;
            const payload=local&&!consumed?local.payload:data.payload;
            if(consumed)removeLocal();
            if(payload){
                state.saved=payload;recovery.hidden=false;
                const compatible=payload.base===base;
                toolbar.querySelector('.draft-restore').disabled=!compatible;
                report(compatible?(local&&!consumed?'Masz awaryjną kopię z tej karty — przywróć ją albo odrzuć.':'Masz kopię roboczą — przywróć ją albo odrzuć.'):'Dane formularza zmieniły się od autozapisu. Kopia nie zostanie nałożona na nowsze dane.',!compatible);
            }else report(idleLabel);
        }
        try {await connect();}catch(error){report(error.message,true);}
        state.save=async()=>{
            state.backup();
            if(state.stop||!toggle.checked||!recovery.hidden)return;
            if(state.pending){await state.pending;return state.save();}
            if(!form.getClientRects().length)return;
            const current=snapshot(form),serialized=JSON.stringify(current);
            if(serialized===state.last)return;
            report('Zapisywanie kopii roboczej…');
            state.pending=(async()=>{
                if(!state.ready){await connect(false);if(!recovery.hidden)return;}
                const data=await api('PUT',{base,snapshot:current});
                state.last=serialized;
                if(isOffer&&JSON.stringify(snapshot(form))===serialized)removeLocal();
                report('Autozapis: '+new Date(data.saved_at).toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'}));
            })().catch(error=>{report(error.message,true);}).finally(()=>state.pending=null);
            await state.pending;
        };
        setInterval(()=>state.save(),isOffer?30000:300000);
        if(isOffer){
            // Also covers programmatic rich-text and price-row changes.
            setInterval(()=>state.backup(),5000);
            form.addEventListener('input',()=>state.backup());
            form.addEventListener('change',()=>state.backup());
        }
        toggle.addEventListener('change',()=>report(toggle.checked?idleLabel:'Autozapis wyłączony — użyj Zapisz'));
        toolbar.querySelector('.draft-restore').addEventListener('click',()=>{
            try {restore(form,state.saved.snapshot);recovery.hidden=true;state.backup();report('Przywrócono kopię roboczą. Zapisz, aby zatwierdzić.');}
            catch(error){report('Nie udało się przywrócić kopii: '+error.message,true);}
        });
        async function discard(exit) {
            if(!confirm(exit?'Odrzucić zmiany od ostatniego ręcznego zapisu, również autozapisane?':'Usunąć tę kopię roboczą?'))return;
            state.stop=true;await state.pending;
            try {
                await api('PUT',null);state.ready=true;recovery.hidden=true;state.saved=null;
                removeLocal();
                if(exit){
                    restore(form,initial);state.last=JSON.stringify(snapshot(form));
                    form.dispatchEvent(new Event('draft:discarded',{bubbles:true}));
                    const modal=form.closest('dialog,.project-modal,.modal-overlay,.aw-modal,.modal');
                    if(modal){if(modal.tagName==='DIALOG')modal.close();else if(modal.classList.contains('project-modal'))modal.classList.remove('open');else modal.style.display='none';document.body.style.overflow='';state.stop=false;report('Odrzucono zmiany.');}
                    else {
                        if(states.some(s=>s!==state&&JSON.stringify(snapshot(s.form))!==JSON.stringify(s.initial))&&!confirm('Inna część strony ma niezapisane zmiany. Wyjść z całej strony?')){state.stop=false;return;}
                        window.formDraftLeaving=true;
                        location.assign(form.dataset.draftExit||location.pathname);
                    }
                }else {state.last=JSON.stringify(initial);state.stop=false;report('Kopia odrzucona · '+idleLabel);}
            }catch(error){state.stop=false;report('Nie udało się odrzucić kopii. '+error.message,true);}
        }
        toolbar.querySelector('.draft-exit').addEventListener('click',()=>discard(true));
        toolbar.querySelector('.draft-discard').addEventListener('click',()=>discard(false));
        const saveButton=()=>[...document.querySelectorAll('button')].filter(b=>b.form===form).find(b=>!toolbar.contains(b)&&b.type==='submit'&&(b.value===form.dataset.draftOperation||b.value==='save'||(!b.name&&/zapisz|utwórz|dodaj audyt|dodaj projekt/i.test(b.textContent))));
        toolbar.querySelector('.draft-save').addEventListener('click',()=>{const button=saveButton();if(button)form.requestSubmit(button);else report('Użyj przycisku zapisu na dole formularza.',true);});
        let submitting=false, waitingToSubmit=false;
        form.addEventListener('submit',async event=>{
            if(submitting)return;
            event.preventDefault();event.stopImmediatePropagation();
            if(waitingToSubmit)return;
            waitingToSubmit=true;state.stop=true;
            const submitter=event.submitter||saveButton();
            try {
                await state.pending;
                // Native click submission is still active during microtasks. Replay in
                // a new task, otherwise requestSubmit silently does nothing in browsers.
                await new Promise(resolve=>setTimeout(resolve,0));
                if(!form.isConnected)return;
                submitting=true;form.requestSubmit(submitter||undefined);
            } finally {submitting=false;waitingToSubmit=false;state.stop=false;}
        },true);
        // Preserve native validation and existing business submit handlers.
        form.addEventListener('submit',event=>queueMicrotask(()=>{if(!event.defaultPrevented)window.formDraftLeaving=true;}));
    }
    window.addEventListener('beforeunload',event=>{
        states.forEach(state=>state.backup());
        if(window.formDraftLeaving)return;
        if(states.some(s=>JSON.stringify(snapshot(s.form))!==JSON.stringify(s.initial))){event.preventDefault();event.returnValue='';}
    });
    window.addEventListener('pageshow',()=>{window.formDraftLeaving=false;states.forEach(state=>state.toggle.checked=true);});
    window.addEventListener('pagehide',()=>states.forEach(state=>state.backup()));
    window.addEventListener('session:expired',()=>states.forEach(state=>state.backup()));
    document.addEventListener('visibilitychange',()=>{
        if(document.visibilityState==='hidden')states.filter(state=>state.form.id==='offer-form').forEach(state=>{state.backup();state.save?.();});
    });
    let leavingForLogout=false;
    document.addEventListener('submit',async event=>{
        const form=event.target;
        if(leavingForLogout||!(form instanceof HTMLFormElement)||new URL(form.action).pathname!=='/logout')return;
        const offers=states.filter(state=>state.form.id==='offer-form');
        if(!offers.length)return;
        event.preventDefault();
        offers.forEach(state=>state.backup());
        // Never prolong authentication indefinitely if the network is unavailable.
        await Promise.race([Promise.all(offers.map(state=>state.save?.())),new Promise(resolve=>setTimeout(resolve,11000))]);
        leavingForLogout=true;window.formDraftLeaving=true;form.requestSubmit(event.submitter||undefined);
    },true);
    document.addEventListener('DOMContentLoaded',()=>setTimeout(()=>document.querySelectorAll('form[data-autosave]').forEach(form=>mount(form).catch(error=>console.error('Autozapis:',error))),0));
})();
