document.addEventListener('click',event=>{
    const add=event.target.closest('[data-report-add]');
    const remove=event.target.closest('[data-report-remove]');
    const section=(add||remove)?.closest('.iso-report-summary');
    if(!section)return;
    if(add){
        const key=add.dataset.reportAdd;
        const host=section.querySelector(`[data-report-rows="${key}"]`);
        if(host.children.length>=20)return;
        const next=Math.max(-1,...[...host.querySelectorAll('[name]')].map(el=>Number(el.name.match(/\[(\d+)\]/)?.[1]??-1)))+1;
        const fragment=section.querySelector(`[data-report-template="${key}"]`).content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace('__INDEX__',String(next)));
        host.append(fragment);
    }else remove.closest('[data-report-row]').remove();
    section.dispatchEvent(new Event('input',{bubbles:true}));
});
