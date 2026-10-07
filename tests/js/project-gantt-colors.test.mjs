import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

test('shared Gantt colors cover all progress boundaries and repaint read-only bars',()=>{
    const context={window:{}};
    vm.runInNewContext(readFileSync('public/js/project-gantt-colors.js','utf8'),context);
    const colors=context.window.ProjectGanttColors;
    for(const [value,expected] of [[0,'0-10'],[10,'0-10'],[11,'11-25'],[25,'11-25'],[26,'26-50'],[50,'26-50'],[51,'51-75'],[75,'51-75'],[76,'76-99'],[99,'76-99'],[100,'100']]){
        assert.equal(colors.progressClass(value),'progress-'+expected);
        const painted={};
        const wrapper={classList:{contains:key=>key==='progress-'+expected},querySelector:selector=>({style:{setProperty:(key,value,priority)=>painted[selector]=[value,priority]}})};
        colors.apply({querySelectorAll:selector=>selector.includes('task-row')?[wrapper]:[]});
        assert.deepEqual(painted['.bar'],[colors.palette['progress-'+expected][0],'important']);
        assert.deepEqual(painted['.bar-progress'],[colors.palette['progress-'+expected][1],'important']);
    }
    for(const done of [false,true]){
        const painted={};
        const wrapper={classList:{contains:()=>done},querySelector:()=>({style:{setProperty:(key,value)=>painted[key]=value}})};
        colors.apply({querySelectorAll:selector=>selector.includes('milestone-row')?[wrapper]:[]});
        assert.equal(painted.fill,done?'#16a34a':'#f59e0b');
    }
    for(const file of ['show','public-gantt']){
        const view=readFileSync(`resources/views/projects/${file}.blade.php`,'utf8');
        assert.ok(view.includes('js/project-gantt-colors.js'));
        assert.ok(view.includes('ProjectGanttColors.progressClass'));
        assert.ok(view.includes('ProjectGanttColors.apply'));
    }
});

test('CRM archive controls are icon-only and have accessible tooltips',()=>{
    const view=readFileSync('resources/views/crm/index.blade.php','utf8');
    const buttons=[...view.matchAll(/<button[^>]*title="Archiwizuj zadanie"[^>]*>(.*?)<\/button>/g)];
    assert.equal(buttons.length,2);
    for(const [html,body] of buttons){
        assert.ok(html.includes('btn-icon btn-icon-archive'));
        assert.ok(html.includes('aria-label="Archiwizuj zadanie"'));
        assert.equal(body.replace(/<[^>]*>/g,'').trim(),'');
    }
});
