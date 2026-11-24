import {lineCents} from './project-work-order-math.js';
const form = document.getElementById('work-order-form');
const data = JSON.parse(document.getElementById('wo-editor-data').textContent);
const textHost = document.getElementById('wo-text-sections');
const priceHost = document.getElementById('wo-price-sections');
const editors = new WeakMap();
const money = value => new Intl.NumberFormat('pl-PL',{style:'currency',currency:'PLN'}).format(value/100);
const escape = value => String(value ?? '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function card(title, type) {
    const node = document.createElement('section');
    node.className = 'ed-card '+type;
    node.innerHTML = '<div class="ed-card-header"><i class="ti ti-'+(type==='type-text'?'file-text':'calculator')+'"></i><input class="section-name-input" maxlength="120" required aria-label="Nazwa sekcji" value="'+escape(title)+'"><div class="wo-section-actions"><button class="btn-section-action" type="button" data-move="-1" title="Przesuń sekcję wyżej" aria-label="Przesuń sekcję wyżej">↑</button><button class="btn-section-action" type="button" data-move="1" title="Przesuń sekcję niżej" aria-label="Przesuń sekcję niżej">↓</button><button class="btn-del-section" type="button" data-delete>Usuń sekcję</button></div></div><div class="ed-card-body"></div>';
    node.querySelector('[data-delete]').onclick = () => {if(confirm('Usunąć tę sekcję?')) {node.remove();totals();}};
    node.querySelectorAll('[data-move]').forEach(button => button.onclick=()=>{
        const sibling=Number(button.dataset.move)<0?node.previousElementSibling:node.nextElementSibling;
        if(!sibling)return;
        if(Number(button.dataset.move)<0)node.parentElement.insertBefore(node,sibling);
        else node.parentElement.insertBefore(sibling,node);
    });
    return node;
}
function textSection(section={name:'Nowa sekcja', content:'', placement:'after_price'}) {
    const node=card(section.name,'type-text');
    const body=node.querySelector('.ed-card-body');
    body.innerHTML='<label class="field-label">Położenie w dokumencie <select class="field-input" data-placement><option value="before_price">Przed wynagrodzeniem</option><option value="after_price">Po wynagrodzeniu</option></select></label><div class="wo-text-editor"></div>';
    body.querySelector('select').value=section.placement || 'after_price';
    textHost.append(node);
    const target=body.querySelector('.wo-text-editor');
    if(typeof Quill==='undefined') {
        const textarea=document.createElement('textarea');
        textarea.className='field-input';textarea.rows=7;textarea.value=section.content || '';
        textarea.setAttribute('aria-label','Treść sekcji (HTML)');
        target.append(textarea);
        editors.set(node,{html:()=>textarea.value});
    } else {
        const quill=new Quill(target,{theme:'snow',modules:{toolbar:[['bold','italic','underline'],[{header:[2,3,false]}],[{list:'ordered'},{list:'bullet'}],['clean']]}});
        quill.clipboard.dangerouslyPasteHTML(section.content || '');
        editors.set(node,{html:()=>quill.root.innerHTML});
    }
    if(typeof offerSectionCollapse!=='undefined') offerSectionCollapse.mount(node);
}
function priceRow(tbody,row={opis:'',jedn:'usługa',ilosc:1,cena_jedn:0}) {
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input class="cell-input" data-key="opis" aria-label="Opis pozycji" maxlength="1000" required value="'+escape(row.opis)+'"></td><td><input class="cell-input" data-key="ilosc" aria-label="Ilość" type="number" min="0.01" max="100000" step="0.01" required value="'+escape(row.ilosc)+'"></td><td><input class="cell-input" data-key="jedn" aria-label="Jednostka" maxlength="30" required value="'+escape(row.jedn)+'"></td><td><input class="cell-input" data-key="cena_jedn" aria-label="Cena netto" type="number" min="0" max="9999999.99" step="0.01" required value="'+escape(row.cena_jedn)+'"></td><td class="r" data-row-net></td><td><button class="btn-del-row" title="Usuń pozycję" type="button" aria-label="Usuń pozycję">×</button></td>';
    tr.querySelector('button').onclick=()=>{tr.remove();totals();};
    tbody.append(tr);totals();
}
function priceSection(section={name:'Wynagrodzenie ryczałtowe',rows:[{opis:'Realizacja prac',jedn:'kpl',ilosc:1,cena_jedn:0}]}) {
    const node=card(section.name,'type-price');
    node.querySelector('.ed-card-body').innerHTML='<div style="overflow-x:auto"><table class="price-table wo-price"><thead><tr><th>Opis pozycji</th><th>Ilość</th><th>Jednostka</th><th>Cena netto</th><th>Wartość netto</th><th data-sortable="false">Akcje</th></tr></thead><tbody></tbody><tfoot><tr><th colspan="4">Suma sekcji</th><td data-section-net></td><td></td></tr></tfoot></table></div><p style="margin-top:10px"><button type="button" class="btn-add-row" data-add-row>+ Dodaj pozycję</button></p>';
    priceHost.append(node);
    const tbody=node.querySelector('tbody');
    (section.rows || []).forEach(row=>priceRow(tbody,row));
    node.querySelector('[data-add-row]').onclick=()=>priceRow(tbody);
}
function totals() {
    let net=0;
    priceHost.querySelectorAll('section').forEach(section=>{
        let total=0;
        section.querySelectorAll('tbody tr').forEach(row=>{
            const cents=lineCents(row.querySelector('[data-key=ilosc]').value,row.querySelector('[data-key=cena_jedn]').value);
            row.querySelector('[data-row-net]').textContent=money(cents);
            row.querySelector('[data-row-net]').dataset.sortValue=cents;
            total+=cents;
        });
        section.querySelector('[data-section-net]').textContent=money(total);
        net+=total;
    });
    const vat=Math.round(net*Number(document.getElementById('wo-vat').value)/100);
    const advance=Math.round(Number(document.getElementById('wo-advance').value || 0)*100);
    document.getElementById('wo-totals').textContent='Netto: '+money(net)+' · VAT: '+money(vat)+' · Brutto: '+money(net+vat)+' · Pozostało po zaliczce: '+money(net+vat-advance);
}
document.querySelector('[data-add-text]').onclick=()=>textSection();
document.querySelector('[data-add-price]').onclick=()=>priceSection();
form.addEventListener('input',totals);
form.addEventListener('change',totals);
form.addEventListener('submit',()=>{
    document.getElementById('wo-text-json').value=JSON.stringify([...textHost.children].map(section=>({
        name:section.querySelector('.section-name-input').value,
        content:editors.get(section).html(),placement:section.querySelector('[data-placement]').value
    })));
    document.getElementById('wo-price-json').value=JSON.stringify([...priceHost.children].map(section=>({
        name:section.querySelector('.section-name-input').value,
        rows:[...section.querySelectorAll('tbody tr')].map(row=>Object.fromEntries([...row.querySelectorAll('[data-key]')].map(input=>[input.dataset.key,input.value])))
    })));
});
(data.text || []).forEach(textSection);
(data.price || []).forEach(priceSection);
totals();
