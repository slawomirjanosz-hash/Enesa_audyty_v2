import {test} from 'node:test';
import assert from 'node:assert/strict';
import {lineCents} from '../../public/js/project-work-order-math.js';

test('work order prices round fractional quantities to cents like server totals', () => {
    assert.equal(lineCents('1','22500'),2250000);
    assert.equal(lineCents('2.25','1234.56'),277776);
    assert.equal(lineCents('0.01','0.50'),1);
    assert.equal(lineCents('3','0.10'),30);
    assert.equal(lineCents('',''),0);
});
