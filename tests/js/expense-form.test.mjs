import {test} from 'node:test';
import assert from 'node:assert/strict';
import {convertAmount} from '../../public/js/expense-form.js';

test('the last entered amount determines the other amount at the selected VAT rate', () => {
    assert.equal(convertAmount('100', '23', 'net_amount'), '123.00');
    assert.equal(convertAmount('246', '23', 'gross_amount'), '200.00');
    assert.equal(convertAmount('100', '8', 'net_amount'), '108.00');
    assert.equal(convertAmount('100', '5', 'net_amount'), '105.00');
    assert.equal(convertAmount('100,50', '0', 'gross_amount'), '100.50');
    assert.equal(convertAmount('1.15', '23', 'net_amount'), '1.41');
    assert.equal(convertAmount('', '23', 'net_amount'), '');
    assert.equal(convertAmount('100', '', 'net_amount'), '');
});
