// Preview forms deliberately have no persistence or customer endpoints.
window.formDraftLeaving = true;
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-preview-form]');
    form.addEventListener('submit', event => { event.preventDefault(); event.stopImmediatePropagation(); }, true);
    const nav = document.getElementById('preview-nav');
    form.querySelectorAll('section.plant-card').forEach((card, index) => {
        card.id ||= 'preview-group-' + index;
        const heading = card.querySelector('h2');
        if (!heading) return;
        const link = document.createElement('a');
        link.href = '#' + card.id;
        link.textContent = heading.textContent;
        nav.append(link);
    });
    document.getElementById('preview-section').addEventListener('change', event => { location.href = event.target.value; });
    document.getElementById('preview-reset').addEventListener('click', () => location.reload());
    document.getElementById('preview-conditions').addEventListener('change', event => document.body.classList.toggle('preview-all', event.target.checked));
    function explainConditions() {
        form.querySelectorAll('[data-condition],[data-when]').forEach(field => {
            const raw = field.dataset.condition || field.dataset.when;
            if (!raw || raw === 'null' || field.querySelector(':scope > .preview-condition')) return;
            const note = document.createElement('p');
            note.className = 'preview-condition';
            note.textContent = 'Pole warunkowe — zależność: ' + raw;
            field.prepend(note);
        });
    }
    explainConditions();
    form.addEventListener('click', () => setTimeout(explainConditions, 0));
});
