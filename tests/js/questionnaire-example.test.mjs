import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/questionnaire-example.js', import.meta.url), 'utf8');
function screen(fields, accept = true) {
    let click, confirmations = 0;
    const status = {textContent: ''};
    const form = {elements: fields, querySelectorAll: selector => selector === '[data-repeat]' ? [] : fields};
    const button = {dataset: {questionnaireExample: 'test'}, parentElement: {querySelector: () => status}};
    for (const f of fields) {
        Object.assign(f, {events: [], dispatchEvent(event) { this.events.push(event.type); },
            matches() { return !!this.disabled; }, closest() { return null; }});
    }
    vm.runInNewContext(source, {document: {getElementById: () => form, addEventListener: (_, fn) => { click = fn; }},
        window: {confirm: () => { confirmations++; return accept; }}, Event: class { constructor(type) { this.type = type; } }});
    return {run: () => click({target: {closest: () => button}}), status, confirmations: () => confirmations};
}
const field = (name, value = '', extra = {}) => ({name, value, type: 'text', tagName: 'INPUT', ...extra});

test('sample filling preserves entered values and never touches consultant, hidden, readonly or disabled fields', () => {
    const fields = [field('answers[name]'), field('answers[existing]', 'Klient'),
        field('answers[swot][strengths]'), field('consultant[X][zgodnosc]'), field('answers[X][compliance]'),
        field('lock_version', '2', {type: 'hidden'}), field('answers[auto]', '', {readOnly: true}),
        field('answers[locked]', '', {disabled: true})];
    const app = screen(fields); app.run();
    assert.match(fields[0].value, /Dane testowe/);
    assert.equal(fields[1].value, 'Klient');
    fields.slice(2).forEach(f => assert.deepEqual(f.events, []));
    assert.deepEqual(fields[0].events, ['input', 'change']);
    app.run(); assert.match(app.status.textContent, /Nie znaleziono/);
});

test('sample filling respects numeric bounds, allowed choices and existing checkbox groups', () => {
    const fields = [field('answers[n]', '', {type: 'number', min: '1', max: '10'}),
        field('answers[decision]', '', {tagName: 'SELECT', options: [{value: ''}, {value: 'potwierdzony'}]}),
        field('answers[multi][]', 'a', {type: 'checkbox', checked: false}),
        field('answers[multi][]', 'b', {type: 'checkbox', checked: true})];
    screen(fields).run();
    assert.equal(fields[0].value, '10'); assert.equal(fields[1].value, 'potwierdzony');
    assert.equal(fields[2].checked, false); assert.equal(fields[3].checked, true);
});

test('cancelling test data confirmation leaves form untouched', () => {
    const fields = [field('answers[name]')]; const app = screen(fields, false); app.run();
    assert.equal(fields[0].value, ''); assert.equal(app.confirmations(), 1);
});
