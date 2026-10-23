import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const view = readFileSync('resources/views/projects/public-gantt.blade.php', 'utf8');
const render = view.slice(view.indexOf("function render(mode="), view.indexOf('\nrender();'));
const colors = view.match(/const ProjectGanttColors=.*?;/)[0];

test('missing chart library displays a useful message instead of an empty chart', () => {
    const container = {};
    vm.runInNewContext(`${colors}\n${render}\nrender();`, {
        window: {}, document: {getElementById: () => container},
    });
    assert.match(container.textContent, /Nie udało się załadować wykresu/);
});

test('missing optional color script does not prevent drawing or switching chart views', () => {
    const draws = [];
    const container = {};
    vm.runInNewContext(`${colors}\n${render}\nrender();render('Month');render('Day');`, {
        window: {}, document: {getElementById: () => container},
        items: [{id: 'task-1', name: 'Task', start: '2026-10-06', end: '2026-10-07', progress: 20}],
        projectStartDate: '2026-09-24', projectEndDate: '2027-01-18',
        setTimeout: () => {}, markers: () => {},
        Gantt: function (selector, tasks, options) { draws.push({selector, tasks, options}); },
    });
    assert.deepEqual(draws.map(draw => draw.options.view_mode), ['Week', 'Month', 'Day']);
    for (const draw of draws) {
        assert.equal(draw.options.readonly, true);
        assert.equal(draw.tasks.length, 2);
        assert.equal(draw.tasks[1].name, 'Task');
    }
});
