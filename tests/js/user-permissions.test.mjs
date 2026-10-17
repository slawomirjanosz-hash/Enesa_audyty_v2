import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../public/js/user-permissions.js', import.meta.url), 'utf8');
function setup() {
    const node = extra => ({listeners: {}, addEventListener(type, fn) {this.listeners[type] = fn;}, ...extra});
    const sets = {roles: {auditor: ['dashboard.view'], accounting: ['calendar.view']}, users: {2: ['hr.vehicles.responsible', 'calendar.view', 'uneditable.permission']}};
    const elements = {'user-permission-data': {textContent: JSON.stringify(sets)}};
    const buttons = {role: [], copy: []};
    for (const prefix of ['add', 'edit']) {
        const inputs = ['dashboard.view', 'calendar.view', 'hr.vehicles.responsible'].map(value => ({value, checked: false}));
        elements[`${prefix}_permissions_panel`] = {inputs, hidden: false, querySelectorAll() {return inputs;}};
        elements[`${prefix}_role`] = node({value: 'auditor'});
        elements[`${prefix}_copy_permissions`] = {value: '2'};
        elements[`${prefix}_permissions_status`] = {};
        buttons.role.push(node({dataset: {loadRole: prefix}}));
        buttons.copy.push(node({dataset: {copyPermissions: prefix}}));
    }
    const window = {consent: true, confirm() {return this.consent;}};
    vm.runInNewContext(source, {window, document: {getElementById: id => elements[id], querySelectorAll: selector => selector.includes('data-load-role') ? buttons.role : buttons.copy}});
    const selected = prefix => elements[`${prefix}_permissions_panel`].inputs.filter(i => i.checked).map(i => i.value);
    return {window, elements, buttons, selected};
}

test('new account role loads defaults and only explicit reload resets existing employee permissions', () => {
    const ui = setup();
    ui.elements.add_role.listeners.change();
    assert.deepEqual(ui.selected('add'), ['dashboard.view']);
    ui.window.loadIndividualPermissions(2);
    assert.deepEqual(ui.selected('edit'), ['calendar.view', 'hr.vehicles.responsible']);
    ui.elements.edit_role.value = 'accounting';
    ui.elements.edit_role.listeners.change();
    assert.deepEqual(ui.selected('edit'), ['calendar.view', 'hr.vehicles.responsible']);
    ui.window.consent = false;
    ui.buttons.role[1].listeners.click();
    assert.deepEqual(ui.selected('edit'), ['calendar.view', 'hr.vehicles.responsible']);
    ui.window.consent = true;
    ui.buttons.role[1].listeners.click();
    assert.deepEqual(ui.selected('edit'), ['calendar.view']);
});

test('copy creates an independent editable selection without uneditable rights or a server write', () => {
    const ui = setup();
    ui.buttons.copy[0].listeners.click();
    assert.deepEqual(ui.selected('add'), ['calendar.view', 'hr.vehicles.responsible']);
    ui.elements.add_permissions_panel.inputs[2].checked = false;
    assert.deepEqual(ui.selected('add'), ['calendar.view']);
    ui.window.loadIndividualPermissions(2);
    assert.deepEqual(ui.selected('edit'), ['calendar.view', 'hr.vehicles.responsible']);
    ui.window.loadIndividualPermissions(999);
    assert.deepEqual(ui.selected('edit'), []);
});

test('switching to client clears internal permissions and hides the editor', () => {
    const ui = setup();
    ui.window.loadIndividualPermissions(2);
    ui.elements.edit_role.value = 'client_user';
    ui.elements.edit_role.listeners.change();
    assert.deepEqual(ui.selected('edit'), []);
    assert.equal(ui.elements.edit_permissions_panel.hidden, true);
    ui.elements.edit_copy_permissions.value = '2';
    ui.buttons.copy[1].listeners.click();
    assert.deepEqual(ui.selected('edit'), []);
});
