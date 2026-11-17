import {test} from 'node:test';
import assert from 'node:assert/strict';
import {filterList} from '../../public/js/list-search.js';

test('shared local search handles Polish terms and restores rows without sorting or deleting', () => {
    const row = text => ({tagName:'TR',style:{},querySelector:()=>null, dataset:{},cloneNode:()=>({textContent:text,querySelectorAll:()=>[]})});
    const rows = [row('Łódź Kowalski Zadanie'),row('Kraków Nowak')];
    const root = {querySelectorAll:()=>rows};
    assert.equal(filterList(root, 'tr', 'lodz zadanie'), 1);
    assert.equal(rows[0].style.display, '');
    assert.equal(rows[1].style.display, 'none');
    assert.equal(filterList(root, 'tr', ''), 2);
    assert.equal(rows[1].style.display, '');
    assert.equal(filterList(root, 'tr', 'absent'), 0);
});

test('task search matches a surname together with words from the description', () => {
    const row = {
        tagName: 'TR',
        style: {},
        dataset: {searchExtra: 'Wymiana sprzętu'},
        querySelector: () => null,
        cloneNode: () => ({textContent: 'Odbiór · Przemysław Zagozda', querySelectorAll: () => []}),
    };
    const root = {querySelectorAll: () => [row]};
    assert.equal(filterList(root, '.task-table-wrap tbody tr', 'zagozda sprzetu'), 1);
    assert.equal(filterList(root, '.task-table-wrap tbody tr', 'nowak sprzetu'), 0);
});
