document.addEventListener('change', event => {
    const operation = event.target.closest('[data-expense-operation]');
    if (operation?.value !== 'returned') return;

    const form = operation.form;
    const dialog = form.querySelector('dialog');
    const note = form.elements.namedItem('note');

    note.disabled = false;
    note.required = true;
    dialog.addEventListener('close', () => {
        note.disabled = true;
        note.required = false;
        operation.value = '';
        operation.focus();
    }, { once: true });
    dialog.showModal();
    note.focus();
});

document.addEventListener('click', event => {
    event.target.closest('[data-expense-cancel]')?.closest('dialog').close();
});

document.addEventListener('submit', event => {
    const form = event.target.closest('.exp-operation');
    const operation = form?.querySelector('[data-expense-operation]');
    if (operation?.value !== 'returned') return;

    form.elements.namedItem('action').value = 'returned';
    operation.disabled = true;
});
