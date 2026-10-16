import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {webcrypto} from 'node:crypto';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/user-password.js', import.meta.url), 'utf8');
function setup(consent = false) {
    const node = extra => ({listeners: {}, addEventListener(type, fn) {this.listeners[type] = fn;}, ...extra});
    const password = {value: '', type: 'password', dispatchEvent() {}, focus() {}, select() {}};
    const generator = node({dataset: {generatePassword: 'edit_password'}});
    const choice = {value: '0'}, submit = {disabled: false};
    const form = node({querySelectorAll: () => [submit]});
    const prompts = [];
    const window = node({confirm(message) {prompts.push(message); return consent;}});
    const elements = {editForm: form, edit_password: password, 'edit-user-send-email': choice, edit_email: {value: 'current@example.test'}};
    vm.runInNewContext(source, {crypto: webcrypto, Uint8Array, Event: class {}, window, document: {
        querySelectorAll: () => [generator], getElementById: id => elements[id],
    }});
    return {generator, password, form, choice, prompts, window, submit};
}

test('generator uses strong distinct passwords without saving or sending', () => {
    const ui = setup();
    const passwords = new Set();
    for (let i = 0; i < 100; i++) {
        ui.generator.listeners.click();
        assert.equal(ui.password.value.length, 20);
        for (const pattern of [/[A-Z]/, /[a-z]/, /[0-9]/, /[!@#$%&*?]/]) assert.match(ui.password.value, pattern);
        passwords.add(ui.password.value);
    }
    assert.equal(passwords.size, 100);
    assert.equal(ui.password.type, 'text');
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.prompts.length, 0);
});

for (const consent of [false, true]) test(`save asks before password mail, consent=${consent}`, () => {
    const ui = setup(consent);
    ui.generator.listeners.click();
    ui.form.listeners.submit({preventDefault() {assert.fail('first save blocked');}});
    assert.equal(ui.choice.value, consent ? '1' : '0');
    assert.match(ui.prompts[0], /current@example.test/);
    assert.equal(ui.submit.disabled, true);
    let blocked = false;
    ui.form.listeners.submit({preventDefault() {blocked = true;}});
    assert.equal(blocked, true);
    assert.equal(ui.prompts.length, 1);
    ui.window.listeners.pageshow({persisted: true});
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.submit.disabled, false);
});

test('saving other user details does not ask or send password mail', () => {
    const ui = setup(true);
    ui.form.listeners.submit({preventDefault() {assert.fail('save blocked');}});
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.prompts.length, 0);
});
