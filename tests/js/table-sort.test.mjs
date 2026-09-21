import {test} from 'node:test';
import assert from 'node:assert/strict';
import {compareValues, sortValue, sortBody} from '../../public/js/table-sort.js';

test('Polish amounts, negative amounts, decimals and percentages sort numerically', () => {
    assert.deepEqual(['1 234,50 zł', '12,40 zł', '-100,00 zł'].sort(compareValues), ['-100,00 zł', '12,40 zł', '1 234,50 zł']);
    assert.ok(compareValues('2,5%', '12%') < 0);
    assert.equal(sortValue('1\u00a0234,50 PLN'), 1234.5);
});
test('dates and timestamps sort chronologically including ISO dates', () => {
    assert.ok(compareValues('31.12.2025', '01.01.2026') < 0);
    assert.ok(compareValues('01.01.2026 09:00', '01.01.2026 12:00') < 0);
    assert.equal(sortValue('2026-01-01 12:00:00'), sortValue('01.01.2026 12:00'));
    assert.ok(compareValues('31.12.2025 – 02.01.2026', '01.01.2026 – 03.01.2026') < 0);
});
test('file sizes sort across units', () => {
    assert.ok(compareValues('900 KB', '1 MB') < 0);
    assert.equal(sortValue('1,5 MB'), 1572864);
});
test('empty and unavailable values stay last in both directions', () => {
    for (const direction of ['asc', 'desc']) {
        assert.ok(compareValues('—', '1', direction) > 0);
        assert.ok(compareValues('Brak dostępu', '100', direction) > 0);
    }
});
test('versions and identifiers use natural ordering, not decimal ordering', () => {
    assert.ok(compareValues('2.9', '2.10', 'asc', 'text') < 0);
    assert.ok(compareValues('AUD/2', 'AUD/10') < 0);
    assert.equal(sortValue('12/2026'), '12/2026');
});
function row(value, options = {}) {
    return {
        value, hidden: options.hidden || false,
        matches: () => !!options.fixed,
        cells: [{colSpan: options.span || 1, rowSpan: 1, dataset: {sortValue: value}, hasAttribute: key => key === 'data-sort-value'}],
    };
}
function body(rows) {
    return {rows, insertBefore(item, before) {
        this.rows.splice(this.rows.indexOf(item), 1);
        this.rows.splice(before === null ? this.rows.length : this.rows.indexOf(before), 0, item);
    }};
}
test('section boundaries and totals are preserved, row identity and filter state survive', () => {
    const hidden = row('1', {hidden: true});
    const tableBody = body([row('Section A', {span: 3}), row('20'), hidden, row('Section B', {span: 3}), row('9'), row('2'), row('Total', {fixed: true})]);
    sortBody(tableBody, 0, 'asc');
    assert.deepEqual(tableBody.rows.map(item => item.value), ['Section A', '1', '20', 'Section B', '2', '9', 'Total']);
    assert.equal(tableBody.rows[1], hidden);
    assert.equal(hidden.hidden, true);
    sortBody(tableBody, 0, 'desc');
    assert.deepEqual(tableBody.rows.map(item => item.value), ['Section A', '20', '1', 'Section B', '9', '2', 'Total']);
});
test('equal values keep their original order and no rows are lost', () => {
    const a = row('2'), b = row('2');
    const tableBody = body([a, b]);
    sortBody(tableBody, 0, 'desc');
    assert.deepEqual(tableBody.rows, [a, b]);
});
