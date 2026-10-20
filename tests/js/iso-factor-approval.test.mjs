import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../../resources/iso50001/html-2026-10-20/4-1.html',import.meta.url),'utf8');
const logic=source.slice(source.indexOf('function brakiKlienta()'),source.indexOf('function zatwierdz()'));
function missing(items){const context={braki:()=>items};vm.createContext(context);vm.runInContext(logic,context);return context.brakiKlienta();}
test('latest 4.1 client approval ignores consultant work but blocks unassigned missing items',()=>{
 const rows=['Brak odpowiedzi',{kto:'klient',opis:'Brak decyzji'},{opis:'Brak bez przypisania'},{kto:'konsultant',opis:'Analiza po przekazaniu'}];
 assert.equal(missing(rows).length,3);
 assert.equal(missing([{kto:'konsultant'}]).length,0);
 assert.equal(missing([]).length,0);
});
