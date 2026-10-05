window.formDraftAdapters = window.formDraftAdapters || {};
window.formDraftAdapters['offer-form'] = {
    afterRestore() {
        const company=document.getElementById('company_id');
        const lead=document.getElementById('crm_opportunity_id');
        if(company&&lead)[...lead.options].forEach(option=>{
            if(!option.value)return;
            option.hidden=option.disabled=option.dataset.companyId!==company.value;
        });
    },
    capture() {
        return {
            prices:collectSections(),texts:collectTextSections(),delegations:JSON.parse(JSON.stringify(delegSections)),
            markup:document.getElementById('markup-pct').value,
            showUnit:document.getElementById('show-unit-toggle').checked,
            rawRows:[...document.querySelectorAll('.price-table tbody tr')].map(row=>[...row.querySelectorAll('input,select')].map(el=>el.value))
        };
    },
    restore(data) {
        document.getElementById('tbody-main').replaceChildren();
        document.getElementById('dynamic-sections').replaceChildren();
        document.getElementById('text-sections-container').replaceChildren();
        textQuills = {};
        (data.prices||[]).forEach((section,i)=>{
            if(i===0){document.getElementById('section-main-name').value=section.name;(section.rows||[]).forEach(row=>addRow('tbody-main',row));}
            else addSection(section);
        });
        (data.texts||[]).forEach(section=>addTextSection(section));
        delegSections=data.delegations||[];delegRender();
        document.getElementById('markup-pct').value=data.markup;
        document.getElementById('show-unit-toggle').checked=!!data.showUnit;
        toggleUnitPrices(document.getElementById('show-unit-toggle'));
        [...document.querySelectorAll('.price-table tbody tr')].forEach((row,i)=>[...row.querySelectorAll('input,select')].forEach((el,j)=>{
            const value=data.rawRows?.[i]?.[j];if(value===undefined)return;
            if(el.tagName==='SELECT'&&![...el.options].some(o=>o.value===value))el.add(new Option(value,value));
            el.value=value;
        }));
        recalcAll();
        document.getElementById('offer-form').style.display='block';
        document.getElementById('modal-template-pick')?.remove();
        const topbar=document.getElementById('editor-topbar');if(topbar)topbar.style.display='flex';
    }
};
