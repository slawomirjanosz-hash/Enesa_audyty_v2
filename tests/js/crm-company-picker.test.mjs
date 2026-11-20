import {test} from 'node:test';
import assert from 'node:assert/strict';
import {matchingCompanies} from '../../public/js/crm-company-picker.js';

test('company suggestions match Polish names and multiple fragments without selecting the empty choice', () => {
    const options = [
        {value: '', textContent: '— bez klienta —'},
        {value: '1', textContent: 'PHH Polski Holding Hotelowy'},
        {value: '2', textContent: 'Łódzka Fabryka Maszyn'},
    ];
    assert.deepEqual(matchingCompanies(options, 'hotel phh').map(option => option.value), ['1']);
    assert.deepEqual(matchingCompanies(options, 'lodzka').map(option => option.value), ['2']);
    assert.equal(matchingCompanies(options, 'nieistniejąca').length, 0);
    assert.equal(options.length, 3);
});
