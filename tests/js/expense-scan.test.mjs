import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/expense-scan.js', import.meta.url), 'utf8');
function setup(confirmed = true, ok = true) {
    const button = {disabled: false}, status = {}, photo = {files: [{size: 100}], value: 'photo.jpg'};
    const inputs = Object.fromEntries(['invoice_number', 'supplier_name', 'owner_id', 'payment_status'].map(name => [name, {value: 'original', dispatchEvent() {}}]));
    const form = {action: '/expenses/scan', addEventListener(type, fn) {this.submit = fn;}, querySelector() {return button;}};
    let calls = 0;
    vm.runInNewContext(source, {
        document: {getElementById: id => ({'expense-scan': form, 'expense-scan-status': status, 'expense-photo': photo}[id]), querySelector: selector => inputs[selector.match(/name="([^"]+)"/)[1]]},
        window: {confirm: () => confirmed}, FormData: class {}, Event: class {},
        fetch: async () => {calls++; return {ok, json: async () => ({proposal: {invoice_number: 'FV/PHOTO', supplier_name: '<script>untrusted</script>', owner_id: '999', payment_status: 'paid'}, errors: {photo: ['Odczyt niedostępny']}})};},
    });
    return {form, button, status, photo, inputs, calls: () => calls};
}

test('OCR requires acceptance and can fill only document fields without saving the invoice', async () => {
    const ui = setup();
    await ui.form.submit({preventDefault() {}});
    assert.equal(ui.inputs.invoice_number.value, 'FV/PHOTO');
    assert.equal(ui.inputs.supplier_name.value, '<script>untrusted</script>');
    assert.equal(ui.inputs.owner_id.value, 'original');
    assert.equal(ui.inputs.payment_status.value, 'original');
    assert.equal(ui.calls(), 1);
    assert.equal(ui.photo.value, '');
    assert.equal(ui.button.disabled, false);
    const declined = setup(false);
    await declined.form.submit({preventDefault() {}});
    assert.equal(declined.inputs.invoice_number.value, 'original');
});

test('duplicate clicks do not repeat a paid request and failures keep manual fields intact', async () => {
    const ui = setup(true, false);
    const first = ui.form.submit({preventDefault() {}});
    await ui.form.submit({preventDefault() {}});
    await first;
    assert.equal(ui.calls(), 1);
    assert.equal(ui.inputs.invoice_number.value, 'original');
    assert.equal(ui.status.textContent, 'Odczyt niedostępny');
    assert.equal(ui.photo.value, '');
    assert.equal(ui.button.disabled, false);
});
