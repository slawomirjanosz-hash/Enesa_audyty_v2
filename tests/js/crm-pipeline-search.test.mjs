import {test} from 'node:test';
import assert from 'node:assert/strict';
import {matchesSearch, filterPipeline} from '../../public/js/crm-pipeline-search.js';

test('search matches all terms, Polish letters, NIP and offer numbers', () => {
    const text = 'Łódź Żółć Pompy Firma 123-456-78-90 OF/2026/004 Jan Kowalski';
    for (const query of ['lodz zolc', '1234567890', 'of 2026 004', 'pompy kowalski', '', '   ']) {
        assert.equal(matchesSearch(text, query), true, query);
    }
    assert.equal(matchesSearch(text, 'pompy nowak'), false);
});

test('filter cards and rows together, recalculate totals and restore on clearing', () => {
    const rows = [
        {dataset: {pipelineId: '1', pipelineStage: 'offer', pipelineValue: '100', pipelineSearch: 'Łódź Pompy'}, style: {}},
        {dataset: {pipelineId: '2', pipelineStage: 'won', pipelineValue: '200', pipelineSearch: 'Kraków'}, style: {}},
    ];
    const cards = rows.map(row => ({dataset: {pipelineCard: row.dataset.pipelineId}, style: {}}));
    const total = {}, count = {}, result = {}, summary = {dataset: {pipelineSummary: 'offer'}};
    const root = {
        querySelectorAll: selector => ({'[data-pipeline-id]': rows, '[data-pipeline-card]': cards,
            '[data-pipeline-total]': [total], '[data-pipeline-matches]': [count], '[data-pipeline-summary]': [summary]})[selector] || [],
        getElementById: () => result,
    };
    assert.equal(filterPipeline(root, 'lodz'), 1);
    assert.equal(cards[1].style.display, 'none');
    assert.equal(rows[1].style.display, 'none');
    assert.equal(summary.textContent, '1 szans · 100%');
    assert.equal(count.textContent, 1);
    assert.match(total.textContent, /100,00/);
    assert.equal(filterPipeline(root, 'absent'), 0);
    assert.equal(result.textContent, 'Brak pasujących szans.');
    assert.equal(filterPipeline(root, ''), 2);
    assert.equal(cards[1].style.display, '');
    assert.equal(rows[1].style.display, '');
    assert.equal(result.textContent, '');
    assert.match(total.textContent, /100,00/);
});
