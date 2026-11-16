import {test} from 'node:test';
import assert from 'node:assert/strict';
import {dragAxis, targetRow} from '../../public/js/project-gantt-reorder.js';

test('bar drag waits for deliberate movement and selects the dominant axis', () => {
    assert.equal(dragAxis(3, -5), null);
    assert.equal(dragAxis(8, 3), 'horizontal');
    assert.equal(dragAxis(-3, -8), 'vertical');
});

test('vertical drop finds the nearest task, including both chart boundaries', () => {
    const rows = [{id:'task-1', y:50}, {id:'task-2', y:90}, {id:'task-3', y:130}];
    assert.equal(targetRow(rows, -100).id, 'task-1');
    assert.equal(targetRow(rows, 102).id, 'task-2');
    assert.equal(targetRow(rows, 400).id, 'task-3');
});
