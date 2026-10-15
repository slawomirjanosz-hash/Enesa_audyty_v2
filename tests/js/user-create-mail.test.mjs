import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/user-create-mail.js', import.meta.url), 'utf8');
function setup() {
    const node = extra => ({listeners: {}, addEventListener(type, listener) { this.listeners[type] = listener; }, ...extra});
    const submitButton = {disabled: false, focus() {}};
    const choice = {value: '0'}, recipient = {}, email = {value: 'new@example.test'};
    const yes = node({dataset: {createUserMail: '1'}}), no = node({dataset: {createUserMail: '0'}}), cancel = node({});
    const dialog = node({open: false, showModal() {this.open = true;}, close() {this.open = false;}, querySelectorAll() {return [yes, no];}});
    const posts = [];
    const form = node({valid: true, querySelectorAll() {return [submitButton];}, requestSubmit() {
        if (!this.valid) return;
        let prevented = false;
        this.listeners.submit({preventDefault() {prevented = true;}});
        if (!prevented) posts.push(choice.value);
    }});
    const elements = {'create-user-form': form, 'create-user-mail-dialog': dialog, 'create-user-send-email': choice,
        'create-user-mail-recipient': recipient, add_email: email, 'create-user-mail-cancel': cancel};
    const window = node({});
    vm.runInNewContext(source, {document: {getElementById: id => elements[id], querySelector: () => submitButton}, window});
    return {form, dialog, choice, recipient, email, yes, no, cancel, posts, window, submitButton};
}

test('save asks first with current email and does not create account before a choice', () => {
    const ui = setup();
    ui.form.requestSubmit();
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.recipient.textContent, ui.email.value);
    assert.deepEqual(ui.posts, []);
    ui.cancel.listeners.click();
    assert.equal(ui.dialog.open, false);
    assert.deepEqual(ui.posts, []);
    ui.email.value = 'corrected@example.test';
    ui.form.requestSubmit();
    assert.equal(ui.recipient.textContent, 'corrected@example.test');
});

for (const [button, expected] of [['yes', '1'], ['no', '0']]) {
    test(`${button} submits the corresponding mail choice once and blocks duplicate saves`, () => {
        const ui = setup();
        ui.form.requestSubmit();
        ui[button].listeners.click();
        assert.deepEqual(ui.posts, [expected]);
        assert.equal(ui.dialog.open, false);
        assert.equal(ui.submitButton.disabled, true);
        ui[button].listeners.click();
        ui.form.requestSubmit();
        assert.deepEqual(ui.posts, [expected]);
    });
}

test('Escape cancels without a post and validation failure asks again after correction', () => {
    const ui = setup();
    ui.form.requestSubmit();
    ui.dialog.listeners.cancel();
    ui.dialog.close();
    assert.deepEqual(ui.posts, []);
    ui.form.requestSubmit();
    ui.form.valid = false;
    ui.yes.listeners.click();
    assert.deepEqual(ui.posts, []);
    ui.form.valid = true;
    ui.form.requestSubmit();
    assert.equal(ui.dialog.open, true);
    assert.deepEqual(ui.posts, []);
    ui.no.listeners.click();
    assert.deepEqual(ui.posts, ['0']);
});

test('browser back restoration resets consent and enables a new decision', () => {
    const ui = setup();
    ui.form.requestSubmit(); ui.yes.listeners.click();
    ui.window.listeners.pageshow({persisted: true});
    assert.equal(ui.choice.value, '0');
    assert.equal(ui.submitButton.disabled, false);
    ui.form.requestSubmit();
    assert.equal(ui.dialog.open, true);
    assert.deepEqual(ui.posts, ['1']);
});
