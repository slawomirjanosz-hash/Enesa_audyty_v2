import test from 'node:test';
import assert from 'node:assert/strict';
import {enableDashboardPriority} from '../../public/js/dashboard-priority.js';

function fixture(save = async () => {}) {
    const events = node => {
        node.handlers = {};
        node.addEventListener = (name, handler) => node.handlers[name] = handler;
        return node;
    };
    const containers = ['clientsGrid', 'companies-table-tbody'].map(id => {
        const container = events({id, children: []});
        container.querySelectorAll = () => container.children;
        container.appendChild = item => {
            container.children = container.children.filter(child => child !== item);
            container.children.push(item);
        };
        container.insertBefore = (item, before) => {
            container.children = container.children.filter(child => child !== item);
            const index = before ? container.children.indexOf(before) : container.children.length;
            container.children.splice(index, 0, item);
        };
        container.closest = () => ({querySelectorAll: () => []});
        container.children = ['1', '2', '3'].map(companyId => {
            const handle = events({});
            const item = events({dataset: {companyId}, parentElement: container, style: {display: companyId === '2' ? 'none' : ''}});
            const classes = new Set();
            item.classList = {add: (...names) => names.forEach(name => classes.add(name)), remove: (...names) => names.forEach(name => classes.delete(name))};
            item.querySelector = () => handle;
            item.closest = () => item;
            item.getBoundingClientRect = () => ({top: 0, height: 40, left: 0, width: 100});
            Object.defineProperty(item, 'nextSibling', {get: () => container.children[container.children.indexOf(item) + 1]});
            return item;
        });
        return container;
    });
    const errors = [];
    enableDashboardPriority({containers, save, onError: error => errors.push(error)});
    const event = {preventDefault() {}, dataTransfer: {setData() {}}, clientY: 0, clientX: 0};
    const drag = container => {
        const item = container.children[2];
        item.querySelector().handlers.pointerdown();
        item.handlers.dragstart(event);
        container.handlers.dragover({...event, target: container.children[0]});
        return item;
    };
    const orders = () => containers.map(container => container.children.map(item => item.dataset.companyId));
    return {containers, errors, event, drag, orders};
}

test('list drag persists all clients including filtered rows and synchronizes tiles', async () => {
    const saved = [];
    const f = fixture(async ids => saved.push(ids));
    const item = f.drag(f.containers[1]);
    await f.containers[1].handlers.drop(f.event);
    item.handlers.dragend();
    assert.deepEqual(saved, [['3', '1', '2']]);
    assert.deepEqual(f.orders(), [['3', '1', '2'], ['3', '1', '2']]);
});

test('tile drag also synchronizes the list', async () => {
    const f = fixture();
    f.drag(f.containers[0]);
    await f.containers[0].handlers.drop(f.event);
    assert.deepEqual(f.orders(), [['3', '1', '2'], ['3', '1', '2']]);
});

test('failed saves restore both views and notify the user', async () => {
    const f = fixture(async () => { throw new Error('Network error'); });
    f.drag(f.containers[1]);
    await f.containers[1].handlers.drop(f.event);
    assert.deepEqual(f.orders(), [['1', '2', '3'], ['1', '2', '3']]);
    assert.equal(f.errors.length, 1);
});

test('cancelled drags and unrelated drops never save a changed order', async () => {
    let writes = 0;
    const f = fixture(async () => writes++);
    f.drag(f.containers[1]).handlers.dragend();
    await f.containers[1].handlers.drop(f.event);
    assert.equal(writes, 0);
    assert.deepEqual(f.orders(), [['1', '2', '3'], ['1', '2', '3']]);
});
