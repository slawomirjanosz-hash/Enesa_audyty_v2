import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source = readFileSync(new URL('../../public/js/expense-scan.js', import.meta.url), 'utf8');
function setup({confirmed=true,ok=true,existing=false,payment='paid',evidence='Zapłacono w całości'}={}) {
    const node = extra => ({listeners:{},addEventListener(type,fn){this.listeners[type]=fn;},...extra});
    const button = node({dataset:{photoPick:'expense-photo'},disabled:false});
    const photo = node({files:[{size:100,type:'image/jpeg'}],value:'photo.jpg'});
    const manual = node({});
    const status = {};
    const inputs = Object.fromEntries(['invoice_number','supplier_name','owner_id','payment_status'].map(name=>[name,{value:existing?'original':'',dispatchEvent(){}}]));
    let hidden=false, calls=0, confirmations=0;
    const invoice=node({dataset:{entryChoice:'1',existing:existing?'1':'0'},elements:{namedItem:name=>inputs[name]},classList:{add(){hidden=true;},remove(){hidden=false;}},scrollIntoView(){}});
    const form=node({action:'/expenses/scan',querySelectorAll:()=>[button],setAttribute(){},removeAttribute(){}});
    vm.runInNewContext(source,{
        document:{getElementById:id=>({'expense-scan':form,'expense-form':invoice,'expense-scan-status':status,'expense-photo':photo,'expense-manual':manual}[id])},
        window:{confirm:()=>{confirmations++;return confirmed;}},FormData:class{set(){}},Event:class{},
        fetch:async()=>{calls++;return{ok,json:async()=>({proposal:{invoice_number:'FV/PHOTO',supplier_name:'<script>untrusted</script>',owner_id:'999',payment_status:payment,payment_evidence:evidence},errors:{photo:['Odczyt niedostępny']}})};}
    });
    return {photo,button,status,inputs,manual,hidden:()=>hidden,calls:()=>calls,confirmations:()=>confirmations,read:()=>photo.listeners.change()};
}
test('photo selection automatically fills a new draft and evidence backed payment without saving or changing ownership',async()=>{
    const ui=setup();
    assert.equal(ui.hidden(),true);
    await ui.read();
    assert.equal(ui.inputs.invoice_number.value,'FV/PHOTO');
    assert.equal(ui.inputs.payment_status.value,'paid');
    assert.equal(ui.inputs.owner_id.value,'');
    assert.equal(ui.confirmations(),0);
    assert.equal(ui.calls(),1);
    assert.equal(ui.hidden(),false);
    assert.equal(ui.photo.value,'');
    assert.equal(ui.button.disabled,false);
});
test('existing data needs overwrite confirmation and settlement or missing evidence cannot be set by OCR',async()=>{
    const declined=setup({existing:true,confirmed:false});
    await declined.read(); assert.equal(declined.inputs.invoice_number.value,'original');
    for(const options of [{payment:'settled'},{evidence:''}]) {
        const ui=setup(options); await ui.read(); assert.equal(ui.inputs.payment_status.value,'');
    }
});
test('concurrent reads are prevented and failures allow manual input without changing fields',async()=>{
    const ui=setup({ok:false});
    const first=ui.read(); await ui.read(); await first;
    assert.equal(ui.calls(),1); assert.equal(ui.inputs.invoice_number.value,'');
    assert.equal(ui.status.textContent,'Odczyt niedostępny');
    assert.equal(ui.button.disabled,false); assert.equal(ui.hidden(),false);
    const manual=setup(); manual.manual.listeners.click(); assert.equal(manual.hidden(),false); assert.equal(manual.calls(),0);
});
test('cancelled selection and unsupported or oversized images do not invoke paid analysis',async()=>{
    for(const file of [null,{size:6*1024*1024,type:'image/jpeg'},{size:100,type:'image/heic'}]) {
        const ui=setup();ui.photo.files=file?[file]:[];await ui.read();assert.equal(ui.calls(),0);
    }
});
