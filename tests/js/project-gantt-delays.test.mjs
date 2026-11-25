import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';
const env={window:{}};
runInNewContext(readFileSync(new URL('../../public/js/project-gantt-delays.js',import.meta.url),'utf8'),env);
const delays=env.window.ProjectGanttDelays;

test('delay range expands the chart without altering planned task dates',()=>{
    class Gantt { setup_gantt_dates(){this.gantt_end=new Date('2026-05-20T00:00:00');} }
    const items=[{end:'2026-05-10',overdue_end:'2026-07-12',overdue_days:63}];
    const Extended=delays.withRange(Gantt,items),chart=new Extended();chart.setup_gantt_dates();
    assert.ok(chart.gantt_end>new Date('2026-07-12T00:00:00'));
    assert.equal(items[0].end,'2026-05-10');
    assert.equal(delays.withRange(Gantt,[]),Gantt);
});

test('red tail starts after planned bar and redraw is idempotent',()=>{
    let tails=[];
    const bar={getAttribute:key=>({x:100,width:50,y:40,height:20}[key])};
    const wrapper={dataset:{id:'task-1'},querySelector:()=>bar,prepend:node=>tails.push(node)};
    const root={querySelectorAll:selector=>selector==='.bar-wrapper'?[wrapper]:tails.map(node=>({remove:()=>{tails=tails.filter(t=>t!==node);}}))};
    env.document={createElementNS:()=>({attrs:{},style:{},setAttribute(k,v){this.attrs[k]=v;}})};
    const items=[{id:'task-1',overdue_days:2}];
    for(let i=0;i<2;i++)delays.draw(root,{options:{step:168,column_width:140}},items);
    assert.equal(tails.length,1);
    assert.equal(tails[0].attrs.x,150);
    assert.equal(tails[0].attrs.width,40);
    assert.equal(tails[0].style.fill,'#ef4444');
    assert.equal(bar.getAttribute('width'),50);
});
