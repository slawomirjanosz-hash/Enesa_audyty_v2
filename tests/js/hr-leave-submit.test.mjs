import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/hr-leave-submit.js', import.meta.url), 'utf8');

test('missing supervisor keeps entered leave data and allows retry after assignment', async () => {
    let handler, assigned = false, redirected;
    const error = {hidden: true};
    const button = {disabled: false};
    const values = {start_date: '2026-11-02', days: '3', notes: 'Moje dane'};
    const form = {action: '/hr/leaves', dataset: {}, values, querySelector: () => button,
        addEventListener: (event, callback) => { handler = callback; }};
    vm.runInNewContext(source, {
        document: {getElementById: id => id === 'leave-form' ? form : error},
        FormData: class {constructor(form) {this.values = {...form.values};}},
        fetch: async (url, options) => {
            assert.deepEqual(options.body.values, values);
            return assigned ? {ok: true, redirected: true, url: '/hr?tab=leaves'}
                : {ok: false, json: async () => ({errors: {supervisor: ['Uzupełnij przełożonego.']}})};
        },
        window: {location: {assign: url => {redirected = url;}}},
    });
    await handler({preventDefault() {}, currentTarget: form});
    assert.equal(error.hidden, false);
    assert.match(error.textContent, /Uzupełnij przełożonego/);
    assert.equal(button.disabled, false);
    assert.equal(redirected, undefined);
    assert.equal(form.values.notes, 'Moje dane');
    assigned = true;
    await handler({preventDefault() {}, currentTarget: form});
    assert.equal(redirected, '/hr?tab=leaves');
});
