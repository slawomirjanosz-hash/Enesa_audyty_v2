const dialog = document.getElementById('project-finance-dialog');
const configElement = document.getElementById('project-finance-editor-config');

if (dialog && configElement) {
    const config = JSON.parse(configElement.textContent);
    const form = dialog.querySelector('form');
    const fields = ['type', 'name', 'document_number', 'supplier_company_id', 'supplier', 'entry_date', 'payment_date', 'amount', 'status', 'finance_group_id', 'notes'];
    let opener = null;
    let previousOverflow = '';

    const open = (mode, id, recovery = null) => {
        const entry = config.entries[String(id)];
        if (!entry || !['edit', 'copy'].includes(mode) || (mode === 'copy' && entry.type !== 'invoice')) return;
        opener = document.activeElement;
        form.reset();
        const copying = mode === 'copy';
        const liveStatus = document.querySelector(`[data-finance-entry-id="${id}"] .project-async-status`)?.value;
        const values = {...entry, ...(liveStatus ? {status: liveStatus} : {}), ...(copying ? {document_number: '', status: 'planned'} : {}), ...(recovery || {})};
        fields.forEach(name => {
            const value = values[name];
            form.elements.namedItem(name).value = ['string', 'number'].includes(typeof value) ? value : '';
        });
        form.action = copying ? config.store_url : entry.update_url;
        form.elements.namedItem('_method').disabled = copying;
        form.elements.namedItem('_finance_editor_mode').value = mode;
        form.elements.namedItem('_finance_editor_entry').value = id;
        document.getElementById('project-finance-dialog-title').textContent = copying ? 'Kopiuj fakturę dla klienta' : 'Edytuj pozycję finansową';
        document.getElementById('project-finance-editor-save').textContent = copying ? 'Zapisz kopię' : 'Zapisz zmiany';
        document.getElementById('project-finance-copy-note').hidden = !copying;
        // Reuse the project's supplier/group visibility rules after filling the form.
        form.elements.namedItem('type').dispatchEvent(new Event('change', {bubbles: true}));
        if (!dialog.open) {
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
        }
    };

    document.querySelectorAll('[data-finance-editor-open]').forEach(button => {
        button.addEventListener('click', () => open(button.dataset.financeEditorOpen, button.dataset.financeEntry));
    });
    dialog.querySelectorAll('[data-finance-editor-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('cancel', event => event.preventDefault());
    dialog.addEventListener('close', () => {
        document.body.style.overflow = previousOverflow;
        opener?.focus({preventScroll: true});
    });
    if (config.recovery) open(config.recovery.mode, config.recovery.id, config.recovery.values);
}
