import test from 'node:test';
import assert from 'node:assert/strict';
import {formatAmount} from '../../public/js/financial-amount.js';

test('amounts are grouped with Polish decimals and currency without precision loss', () => {
    assert.equal(formatAmount('141337288.45'), '141 337 288,45 zł');
    assert.equal(formatAmount('-9 871,86 zł'), '-9 871,86 zł');
    assert.equal(formatAmount('999999999999998.99'), '999 999 999 999 998,99 zł');
    assert.equal(formatAmount('0'), '0,00 zł');
    assert.equal(formatAmount(''), '');
    assert.equal(formatAmount('bad'), 'bad');
});
