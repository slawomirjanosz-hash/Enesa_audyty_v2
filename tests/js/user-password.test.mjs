import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {webcrypto} from 'node:crypto';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/user-password.js', import.meta.url), 'utf8');
function setup() {
    const node = extra => ({listeners: {}, addEventListener(type, fn) {this.listeners[type] = fn;}, ...extra});
    const password = {value: '', type: 'password', dispatchEvent() {}, focus() {}, select() {}};
    const generator = node({dataset: {generatePassword: 'edit_password'}});
    const choice = {value: '0'}, submit = {disabled: false};
    let requests = 0, closed = 0, resets = 0;
    const form = node({querySelectorAll: () => [submit], reset() {resets++;}, requestSubmit() {this.listeners.submit({preventDefault() {assert.fail('confirmed submission blocked');}}); requests++;}});
    const saveButtons = ['0', '1'].map(value => node({dataset: {editUserMail: value}}));
    const dialog = node({open: false, showModal() {this.open = true;}, close() {this.open = false;}, querySelectorAll: () => saveButtons});
    const back = node(), discard = node(), recipient = {};
    const window = node({closeEditModal() {closed++;}});
    const elements = {editForm: form, edit_password: password, 'edit-user-send-email': choice, edit_email: {value: 'current@example.test'}, 'edit-user-password-dialog': dialog, 'edit-user-password-recipient': recipient, 'edit-user-password-back': back, 'edit-user-password-discard': discard};
    vm.runInNewContext(source, {crypto: webcrypto, Uint8Array, Event: class {}, window, document: {
        querySelectorAll: () => [generator], getElementById: id => elements[id],
    }});
    return {generator, password, form, choice, dialog, back, discard, recipient, saveButtons, window, submit, stats: () => ({requests, closed, resets})};
}
function ask(ui) {
    let prevented = false;
    ui.form.listeners.submit({preventDefault() {prevented = true;}});
    assert.equal(prevented, true);
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.submit.disabled, false);
}

test('generator uses strong distinct passwords without saving or sending', () => {
    const ui = setup(), passwords = new Set();
    for (let i = 0; i < 100; i++) {
        ui.generator.listeners.click();
        assert.equal(ui.password.value.length, 20);
        for (const pattern of [/[A-Z]/, /[a-z]/, /[0-9]/, /[!@#$%&*?]/]) assert.match(ui.password.value, pattern);
        passwords.add(ui.password.value);
    }
    assert.equal(passwords.size, 100);
    assert.equal(ui.password.type, 'text');
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.stats().requests, 0);
});

for (const mail of [0, 1]) test(`password saves only after explicit choice, mail=${mail}`, () => {
    const ui = setup();
    ui.generator.listeners.click();
    ask(ui);
    assert.equal(ui.recipient.textContent, 'current@example.test');
    assert.equal(ui.stats().requests, 0);
    ui.saveButtons[mail].listeners.click();
    assert.equal(ui.choice.value, String(mail));
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.submit.disabled, true);
    assert.equal(ui.stats().requests, 1);
    ui.saveButtons[mail].listeners.click();
    assert.equal(ui.stats().requests, 1);
    let blocked = false;
    ui.form.listeners.submit({preventDefault() {blocked = true;}});
    assert.equal(blocked, true);
    ui.window.listeners.pageshow({persisted: true});
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.submit.disabled, false);
    ask(ui);
});

test('discard clears the generated password and closes edit without any submission', () => {
    const ui = setup();
    ui.generator.listeners.click();
    ask(ui);
    ui.discard.listeners.click();
    assert.deepEqual(ui.stats(), {requests: 0, closed: 1, resets: 1});
    assert.equal(ui.password.value, '');
    assert.equal(ui.password.type, 'password');
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.submit.disabled, false);
});

test('back and Escape preserve draft without saving and require a fresh choice', () => {
    const ui = setup();
    ui.generator.listeners.click();
    const password = ui.password.value;
    ask(ui);
    ui.back.listeners.click();
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.password.value, password);
    assert.equal(ui.stats().requests, 0);
    ask(ui);
    ui.dialog.listeners.cancel();
    ui.dialog.close();
    assert.equal(ui.stats().requests, 0);
    assert.equal(ui.choice.value, '0');
    ask(ui);
});

test('saving other user details does not ask or send password mail', () => {
    const ui = setup();
    ui.form.listeners.submit({preventDefault() {assert.fail('save blocked');}});
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.submit.disabled, true);
});

test('failed browser validation clears pending consent and permits returning to edit', () => {
    const ui = setup();
    ui.form.requestSubmit = () => {};
    ui.generator.listeners.click();
    ask(ui);
    ui.saveButtons[1].listeners.click();
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.submit.disabled, false);
    ask(ui);
});
