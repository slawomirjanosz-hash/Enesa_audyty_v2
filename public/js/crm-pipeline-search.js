export function normalizeSearch(value) {
    return String(value || '').toLocaleLowerCase('pl-PL').replace(/ł/g, 'l')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
}

export function matchesSearch(text, query) {
    const searchable = normalizeSearch(text);
    const compact = searchable.replace(/\s/g, '');
    return normalizeSearch(query).split(' ').filter(Boolean)
        .every(term => searchable.includes(term) || compact.includes(term));
}

export function filterPipeline(root, query) {
    const matches = new Set();
    const stages = new Map();
    let activeCount = 0, activeValue = 0;
    const money = value => new Intl.NumberFormat('pl-PL', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(value) + ' zł';
    root.querySelectorAll('[data-pipeline-id]').forEach(row => {
        const visible = matchesSearch(row.dataset.pipelineSearch, query);
        row.style.display = visible ? '' : 'none';
        if (!visible) return;
        matches.add(row.dataset.pipelineId);
        const stage = row.dataset.pipelineStage;
        const value = Number(row.dataset.pipelineValue) || 0;
        const totals = stages.get(stage) || {count: 0, value: 0};
        totals.count++;
        totals.value += value;
        stages.set(stage, totals);
        if (!['won', 'lost', 'rejected'].includes(stage)) {
            activeCount++;
            activeValue += value;
        }
    });
    root.querySelectorAll('[data-pipeline-card]').forEach(card => {
        card.style.display = matches.has(card.dataset.pipelineCard) ? '' : 'none';
    });
    root.querySelectorAll('[data-pipeline-summary]').forEach(label => {
        const count = stages.get(label.dataset.pipelineSummary)?.count || 0;
        label.textContent = `${count} szans · ${activeCount ? Math.round(count / activeCount * 100) : 0}%`;
    });
    root.querySelectorAll('[data-pipeline-sum]').forEach(label => {
        label.textContent = money(stages.get(label.dataset.pipelineSum)?.value || 0);
    });
    root.querySelectorAll('[data-pipeline-count]').forEach(label => {
        label.textContent = stages.get(label.dataset.pipelineCount)?.count || 0;
    });
    root.querySelectorAll('[data-pipeline-total]').forEach(label => { label.textContent = money(activeValue); });
    root.querySelectorAll('[data-pipeline-matches]').forEach(label => { label.textContent = matches.size; });
    const result = root.getElementById('pipeline-search-result');
    if (result) result.textContent = normalizeSearch(query)
        ? (matches.size ? `Znaleziono szans: ${matches.size}.` : 'Brak pasujących szans.') : '';
    return matches.size;
}

if (typeof document !== 'undefined') {
    const input = document.getElementById('pipeline-search');
    if (input) {
        const refresh = () => filterPipeline(document, input.value);
        input.addEventListener('input', refresh);
        input.addEventListener('search', refresh);
        window.addEventListener('pageshow', refresh);
        refresh();
    }
}
