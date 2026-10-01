/* Only mounted by staff views. Normal save endpoints retain all permission and approval checks. */
(() => {
    const excluded = /\[(?:swot|conclusions|compliance|zgodnosc|unknown|detail|source)\]/;
    const eligible = input => input.name?.startsWith('answers[')
        && !excluded.test(input.name) && !input.matches(':disabled') && !input.readOnly
        && !['hidden', 'file', 'submit', 'button'].includes(input.type)
        && !input.closest('[hidden], [data-calculated]')
        && !input.closest('[data-question]')?.querySelector('[name$="[unknown]"]:checked');
    const notify = input => {
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const example = input => {
        if (input.tagName === 'SELECT') {
            const options = [...input.options].filter(o => o.value && !o.disabled);
            // Use an explicit positive decision, never a consultant compliance decision.
            return (options.find(o => ['potwierdzony', 'potwierdzona', 'MWh'].includes(o.value))
                || options.find(o => !['unknown', 'nie wiem', 'pending'].includes(o.value)) )?.value ?? '';
        }
        if (input.type === 'date') return new Date().toISOString().slice(0, 10);
        if (input.type === 'number' || input.inputMode === 'decimal') {
            const min = input.min === '' ? 0 : Number(input.min);
            const max = input.max === '' ? Infinity : Number(input.max);
            return String(Math.min(max, Math.max(min, 100)));
        }
        if (input.type === 'email') return 'test@example.invalid';
        if (input.type === 'tel') return '000000000';
        if (input.name.includes('[powod]')) return 'Dane testowe — przykładowe uzasadnienie decyzji.';
        return 'Dane testowe — przykładowa odpowiedź klienta.'.slice(0, input.maxLength > 0 ? input.maxLength : 2000);
    };
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-questionnaire-example]');
        if (!button) return;
        const form = document.getElementById(button.dataset.questionnaireExample);
        if (!form || !window.confirm('Uzupełnić puste odpowiedzi klienta fikcyjnymi danymi testowymi? Istniejące odpowiedzi pozostaną bez zmian. Nic nie zostanie zapisane ani zatwierdzone.')) return;
        const roots = () => button.dataset.exampleScope ? [...form.querySelectorAll(button.dataset.exampleScope)] : [form];
        let count = 0;
        // Multiple passes reveal conditional fields after their parent answer is filled.
        for (let pass = 0; pass < 3; pass++) {
            roots().forEach(root => root.querySelectorAll('[data-repeat]').forEach(repeat => {
                if (repeat.closest('[hidden]') || repeat.closest('[data-question]')?.querySelector('[name$="[unknown]"]:checked')) return;
                const add = repeat.querySelector('[data-add-row]');
                if (!repeat.querySelector('[data-rows] .plant-repeat-row') && add && !add.matches(':disabled')) add.click();
            }));
            roots().forEach(root => root.querySelectorAll('input, select, textarea').forEach(input => {
                if (!eligible(input)) return;
                if (['checkbox', 'radio'].includes(input.type)) {
                    const group = [...form.elements].filter(field => field.name === input.name);
                    if (group.some(field => field.checked)) return;
                    input.checked = true;
                } else {
                    if (input.value.trim() !== '') return;
                    input.value = example(input);
                    if (!input.value) return;
                }
                count++; notify(input);
            }));
        }
        button.parentElement.querySelector('[data-example-message]').textContent = count
            ? `Uzupełniono ${count} pól danymi testowymi. Sprawdź odpowiedzi i kliknij Zapisz. Oceny konsultanta i zatwierdzenia pozostają bez zmian.`
            : 'Nie znaleziono pustych, dostępnych pól klienta. Istniejące odpowiedzi pozostawiono bez zmian.';
    });
})();
