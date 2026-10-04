import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

test('financial preview renders result and flags fields, clearing stale flags on failure', async () => {
    let fail = false, scheduled;
    const input = {name: 'finances[0][equity]', value: '-10', dataset: {}, addEventListener: (_, cb) => { input.changed = cb; }};
    const form = {querySelector: () => ({value: 'csrf'}), querySelectorAll: () => [input]};
    const panel = {dataset: {previewUrl: '/preview'}, closest: () => form};
    vm.runInNewContext(readFileSync(new URL('../../public/js/financial-health.js', import.meta.url), 'utf8'), {
        document: {getElementById: () => panel}, FormData, AbortController,
        setTimeout: cb => { scheduled = cb; }, clearTimeout: () => {},
        fetch: async () => ({ok: !fail, json: async () => ({html: 'Zagrożenie', health: {periods: [{fields: {equity: 'risk'}}]}})}),
    });
    await new Promise(setImmediate);
    assert.equal(panel.innerHTML, 'Zagrożenie');
    assert.equal(input.dataset.financialState, 'risk');
    fail = true;
    input.changed();
    assert.match(panel.textContent, /Przeliczanie/);
    await scheduled();
    assert.equal(input.dataset.financialState, 'unknown');
    assert.match(panel.textContent, /Nie można/);
});
