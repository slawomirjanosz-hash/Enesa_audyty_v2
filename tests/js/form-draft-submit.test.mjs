import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source=fs.readFileSync(new URL('../../public/js/form-drafts.js',import.meta.url),'utf8');
const start=source.indexOf('let submitting=');
const end=source.indexOf('// Preserve native validation',start);
function harness(pending=null){
    let handler;
    const tasks=[],calls=[],state={pending,stop:false};
    const form={isConnected:true,addEventListener(type,fn){handler=fn;},requestSubmit(button){calls.push(button);handler({});}};
    vm.runInNewContext(source.slice(start,end),{form,state,saveButton:()=>null,setTimeout:fn=>tasks.push(fn)});
    const click=button=>handler({submitter:button,preventDefault(){},stopImmediatePropagation(){}});
    return {tasks,calls,state,form,click};
}
test('client approval waits for a new browser task and preserves the clicked operation',async()=>{
    const h=harness(),button={name:'operation',value:'submit'};
    const work=h.click(button);
    await Promise.resolve();
    assert.equal(h.calls.length,0);
    assert.equal(h.tasks.length,1);
    h.tasks.shift()();await work;
    assert.deepEqual(h.calls,[button]);
    assert.equal(h.state.stop,false);
});
test('waits for in-flight autosave and ignores repeated clicks',async()=>{
    let resolve;const pending=new Promise(r=>resolve=r),h=harness(pending),button={value:'save'};
    const first=h.click(button);await h.click({value:'submit'});
    assert.equal(h.tasks.length,0);resolve();await new Promise(r=>setImmediate(r));
    h.tasks.shift()();await first;
    assert.deepEqual(h.calls,[button]);
});
test('does not submit a form removed during autosave',async()=>{
    const h=harness(),work=h.click({value:'submit'});
    await Promise.resolve();h.form.isConnected=false;h.tasks.shift()();await work;
    assert.equal(h.calls.length,0);assert.equal(h.state.stop,false);
});
