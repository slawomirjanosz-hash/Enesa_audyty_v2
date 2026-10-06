import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source=readFileSync('resources/views/projects/show.blade.php','utf8');
const functions=source.slice(source.indexOf('function captureProjectGanttScroll()'),source.indexOf('function renderProjectGantt()'));
function fixture() {
    const inner={scrollLeft:400};
    const container={clientWidth:800,scrollLeft:0,hasSvg:true,starts:[900,500],
        querySelector(selector){return selector==='svg.gantt'?(this.hasSvg?{}:null):inner;},
        querySelectorAll(){return this.starts.map(x=>({getAttribute:()=>String(x)}));}};
    const context=vm.createContext({document:{getElementById:()=>container}});
    vm.runInContext('let projectGanttScrollLeft=null;'+functions,context);
    return {container,inner,run:code=>vm.runInContext(code,context)};
}
test('Gantt begins at earliest task then retains manually chosen scroll including zero',()=>{
    const {container,inner,run}=fixture();
    run('restoreProjectGanttScroll()');assert.equal(container.scrollLeft,484);assert.equal(inner.scrollLeft,0);
    container.scrollLeft=1250;run('captureProjectGanttScroll()');
    container.scrollLeft=0;container.starts=[100,200];run('restoreProjectGanttScroll()');assert.equal(container.scrollLeft,1250);
    container.scrollLeft=0;run('captureProjectGanttScroll();restoreProjectGanttScroll()');assert.equal(container.scrollLeft,0);
});
test('empty and hidden Gantt do not overwrite saved position and a new page starts fresh',()=>{
    const {container,run}=fixture();
    container.hasSvg=false;run('captureProjectGanttScroll();restoreProjectGanttScroll()');
    container.hasSvg=true;run('restoreProjectGanttScroll()');assert.equal(container.scrollLeft,484);
    container.clientWidth=0;container.scrollLeft=0;run('captureProjectGanttScroll()');
    container.clientWidth=800;run('restoreProjectGanttScroll()');assert.equal(container.scrollLeft,484);
    const next=fixture();next.run('restoreProjectGanttScroll()');assert.equal(next.container.scrollLeft,484);
});
