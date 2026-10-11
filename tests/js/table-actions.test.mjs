import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {actionDescriptor, isActionHeading, enhanceAction, enhanceActionTable} from '../../public/js/table-actions.js';

function classes() { const values=new Set(); return {add:v=>values.add(v), contains:v=>values.has(v), toggle:(v,on)=>on?values.add(v):values.delete(v)}; }
function control(text='', attributes={}) {
    const el={textContent:text, attributes:{...attributes}, dataset:{}, style:{}, classList:classes(), children:[], hidden:false,
        getAttribute(key){return this.attributes[key]??null;}, setAttribute(key,value){this.attributes[key]=value;},
        closest(){return null;}, querySelector(selector){if(selector==='.app-table-action-glyph')return this.children.find(c=>c.attributes.class==='app-table-action-glyph');if(selector==='i')return this.icon;return null;},
        append(child){child.parent=this;this.children.push(child);}, remove(){this.parent.children.splice(this.parent.children.indexOf(this),1);}};
    el.ownerDocument={createElementNS:()=>control()}; return el;
}

test('Polish action names receive distinct icons and descriptive tooltips',()=>{
    for(const [label,kind] of [['Edytuj','edit'],['Usuń na stałe','delete'],['Kopiuj delegację','copy'],['Przywróć','restore'],['Archiwizuj','archive'],['Podgląd PDF','view'],['Pobierz PDF','download'],['Dodaj','add'],['Zapisz','save'],['Wyślij','send'],['Drukuj','print']]) assert.equal(actionDescriptor(label).kind,kind);
    assert.equal(actionDescriptor('', 'ti ti-trash').label,'Usuń');
    assert.equal(actionDescriptor('×').label,'Usuń');
    assert.equal(actionDescriptor('Nietypowa operacja').label,'Nietypowa operacja');
    assert.equal(actionDescriptor('Nietypowa operacja').kind,'more');
});

test('controls keep their identities handlers destinations names and original text',()=>{
    const button=control('Usuń', {onclick:"return confirm('Usunąć?')", name:'operation',value:'delete',type:'submit'});
    const handler=()=>false; button.onclick=handler;
    enhanceAction(button);
    assert.equal(button.onclick,handler);
    assert.equal(button.attributes.type,'submit'); assert.equal(button.attributes.name,'operation'); assert.equal(button.attributes.value,'delete');
    assert.equal(button.textContent,'Usuń'); assert.equal(button.attributes['aria-label'],'Usuń'); assert.equal(button.attributes.title,'Usuń');
    assert.equal(button.classList.contains('app-table-action-danger'),true);
    assert.equal(button.children[0].attributes['aria-hidden'],'true');
    enhanceAction(button); assert.equal(button.children.length,1);
    const link=control('Kopiuj',{href:'/projects/1/copy',target:'_blank'}); enhanceAction(link);
    assert.equal(link.attributes.href,'/projects/1/copy'); assert.equal(link.attributes.target,'_blank');
});

test('hidden and excluded controls stay untouched while disabled actions remain disabled',()=>{
    for(const mode of ['hidden','style','excluded']) {
        const button=control('Usuń'); if(mode==='hidden')button.hidden=true;if(mode==='style')button.style.display='none';if(mode==='excluded')button.closest=()=>({});
        enhanceAction(button); assert.equal(button.children.length,0); assert.equal(button.classList.contains('app-table-action'),false);
    }
    const button=control('Edytuj'); button.disabled=true; enhanceAction(button); assert.equal(button.disabled,true);
});

test('dynamic label changes refresh the tooltip and symbol without duplicate icons',()=>{
    const button=control('Archiwizuj'); enhanceAction(button);
    button.textContent='Przywróć'; enhanceAction(button);
    assert.equal(button.attributes.title,'Przywróć'); assert.equal(button.children.length,1);
    assert.equal(button.children[0].children[0].attributes.d,actionDescriptor('Przywróć').path);
});

test('only action columns and unnamed trailing actions are enhanced, including new rows',()=>{
    const data=control('Firma A',{href:'/company/1'}), action=control('Edytuj');
    function cell(button){return {colSpan:1,classList:classes(),querySelector:()=>null,querySelectorAll:()=>[button]};}
    const ordinary=cell(data), operations=cell(action); data.parentElement=ordinary;action.parentElement=operations;
    const headers=[{textContent:'Firma',querySelector:()=>null},{textContent:'Akcje',querySelector:()=>null}];
    const table={closest:()=>null,tHead:{rows:[{cells:headers}]},tBodies:[{rows:[{cells:[ordinary,operations]}]}]};
    enhanceActionTable(table); assert.equal(data.children.length,0);assert.equal(action.children.length,1);
    const later=control('Dodaj'), laterCell=cell(later);later.parentElement=laterCell;table.tBodies[0].rows.push({cells:[ordinary,laterCell]});
    headers[1].textContent='';enhanceActionTable(table);assert.equal(later.children.length,1);
    const summary=control('Podsumowanie');table.tBodies[0].rows.push({cells:[{...cell(summary),colSpan:2}]});
    enhanceActionTable(table);assert.equal(summary.children.length,0);
    assert.equal(isActionHeading('Akcja'),true);assert.equal(isActionHeading('Filmy i akcje'),true);assert.equal(isActionHeading('Kwota'),false);
});

test('shared styling fixes dimensions but never forces hidden controls to display',()=>{
    const css=readFileSync(new URL('../../public/css/table-actions.css',import.meta.url),'utf8');
    assert.match(css,/width:32px!important/);assert.match(css,/height:32px!important/);assert.match(css,/row nowrap!important/);
    assert.doesNotMatch(css,/display:inline-flex!important/);assert.match(css,/\[hidden\]\{display:none!important/);
    assert.match(css,/:focus-visible/);
});
