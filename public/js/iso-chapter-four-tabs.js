// Tabs affect presentation only: every input remains in its original platform form.
document.addEventListener('DOMContentLoaded', () => {
    const nav = document.querySelector('[data-clause-tabs]');
    if (!nav) return;
    const tabs = [...nav.querySelectorAll('[data-clause-targets]')].map(link => ({link, panels:[...document.querySelectorAll(link.dataset.clauseTargets)]})).filter(tab => {
        if (tab.panels.length) return true;
        tab.link.remove(); return false;
    });
    if (!tabs.length) return;
    nav.setAttribute('role', 'tablist');
    function activate(tab, focus = false) {
        tabs.forEach((item,index) => {
            const active = item === tab;
            item.link.id = 'clause-tab-' + index;
            item.link.setAttribute('role','tab');
            item.link.setAttribute('aria-selected', String(active));
            item.link.setAttribute('aria-controls', item.panels.map(panel=>panel.id).join(' '));
            item.link.tabIndex = active ? 0 : -1;
            item.panels.forEach(panel => { panel.classList.toggle('iso-clause-hidden', !active); panel.setAttribute('aria-labelledby',item.link.id); });
        });
        if(focus) tab.link.focus({preventScroll:true});
        if(focus) tab.link.scrollIntoView({block:'nearest',inline:'nearest'});
        document.dispatchEvent(new Event('questionnaire:tabs-changed'));
    }
    tabs.forEach((tab,index) => {
        tab.link.addEventListener('click', event => {event.preventDefault(); activate(tab); history.replaceState(null,'',tab.link.hash);});
        tab.link.addEventListener('keydown', event => {
            const next = event.key === 'ArrowRight' ? (index+1)%tabs.length : event.key === 'ArrowLeft' ? (index+tabs.length-1)%tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length-1 : null;
            if(next!==null){event.preventDefault();activate(tabs[next],true);}
        });
    });
    function reveal(element) { const tab=tabs.find(t=>t.panels.some(p=>p===element||p.contains(element))); if(tab)activate(tab); }
    document.addEventListener('invalid',event=>reveal(event.target),true);
    document.addEventListener('click',event=>{const a=event.target.closest('a[href^="#"]');if(a&&!nav.contains(a)){const panel=document.getElementById(a.hash.slice(1));if(panel)reveal(panel);}});
    window.addEventListener('hashchange',()=>{const panel=document.getElementById(location.hash.slice(1));if(panel)reveal(panel);});
    const error=document.querySelector('[aria-invalid="true"],.plant-notice.error');
    const selected=tabs.find(t=>t.panels.some(p=>error&&p.contains(error)))||tabs.find(t=>t.panels.some(p=>'#'+p.id===location.hash))||tabs[0];
    activate(selected);
});
