import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

test('project dependency suggests predecessor end and retains duration and manual date editing', () => {
    const source=readFileSync('resources/views/projects/show.blade.php','utf8');
    const script=source.slice(source.indexOf("document.getElementById('gantt-task-dependency')?.addEventListener('change'"),source.indexOf("document.getElementById('gantt-task-form')?.addEventListener('submit'"));
    const controls={};
    for(const id of ['dependency','start','end','duration','type']) {
        controls['gantt-task-'+id]={value:'',listeners:{},addEventListener(type,fn){this.listeners[type]=fn;},dispatchEvent(event){this.listeners[event.type]?.({target:this});}};
    }
    const field=id=>controls['gantt-task-'+id];
    vm.runInNewContext(script, {document:{getElementById:id=>controls[id]},Event:class{constructor(type){this.type=type;}},projectTimelineItems:[{id:'task-1',end:'2026-10-24'},{id:'task-2',end:'2026-12-31'},{id:'task-3',end:null}],localDate:date=>`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`,taskDurationDays:()=>1});
    const change=(id,value)=>{field(id).value=value;field(id).dispatchEvent({type:'change'});};
    field('type').value='task';field('duration').value='3';
    change('dependency','task-1');
    assert.equal(field('start').value,'2026-10-24');assert.equal(field('end').value,'2026-10-26');
    change('dependency','task-2');assert.equal(field('end').value,'2027-01-02');
    change('start','2027-02-01');assert.equal(field('end').value,'2027-02-03');
    change('dependency','');assert.equal(field('start').value,'2027-02-01');
    change('dependency','task-3');assert.equal(field('start').value,'2027-02-01');
    field('type').value='milestone';change('dependency','task-1');
    assert.equal(field('start').value,field('end').value);
});
