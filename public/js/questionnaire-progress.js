document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-completion-form]').forEach(indicator => {
        const form = document.querySelector(indicator.dataset.completionForm);
        if (!form) return;
        const names = JSON.parse(indicator.dataset.completionFields || '[]');
        const filled = input => input && (input.type === 'checkbox' || input.type === 'radio' ? input.checked : input.value.trim() !== '');
        const update = () => {
            let answered = 0, total = 0;
            if (indicator.dataset.completionMode === 'plant') {
                form.querySelectorAll('[data-question]').forEach(question => {
                    question.classList.remove('plant-unanswered');
                    if (question.hasAttribute('data-calculated')) return;
                    if (question.dataset.condition) {
                        const condition = JSON.parse(question.dataset.condition);
                        if (window.plantConditionVisible ? !window.plantConditionVisible(condition, form) : !condition.values.includes(form.elements.namedItem(`answers[${condition.key}][value]`)?.value)) return;
                    }
                    total++;
                    const unknown = question.querySelector('input[name$="[unknown]"]');
                    const inputs = [...question.querySelectorAll('input,select,textarea')].filter(input => input.name.includes('[value]') && !input.name.endsWith('[id]'));
                    const complete = filled(unknown) || inputs.some(filled);
                    if (complete) answered++;
                    if (form.hasAttribute('data-highlight-unanswered')) question.classList.toggle('plant-unanswered', !complete);
                });
            } else {
                total = names.length;
                answered = names.filter(name => filled(form.elements.namedItem(name) || [...form.querySelectorAll('[data-completion-name]')].find(input => input.dataset.completionName === name))).length;
            }
            const percent = total ? Math.floor(100 * answered / total) : 0;
            indicator.querySelector('[data-completion-text]').textContent = `${answered}/${total} pytań · ${percent}%`;
            indicator.querySelector('progress').value = percent;
        };
        form.addEventListener('input', () => queueMicrotask(update));
        form.addEventListener('change', () => queueMicrotask(update));
        new MutationObserver(update).observe(form, {childList:true, subtree:true});
        update();
    });
});
