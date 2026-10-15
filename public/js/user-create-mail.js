(() => {
    const form = document.getElementById('create-user-form');
    const dialog = document.getElementById('create-user-mail-dialog');
    if (!form || !dialog) return;
    const choice = document.getElementById('create-user-send-email');
    let confirmed = false, submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (confirmed) {
            submitting = true;
            form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = true);
            return;
        }
        event.preventDefault();
        document.getElementById('create-user-mail-recipient').textContent = document.getElementById('add_email').value;
        if (!dialog.open) dialog.showModal();
    });
    dialog.querySelectorAll('[data-create-user-mail]').forEach(button => button.addEventListener('click', () => {
        if (submitting) return;
        choice.value = button.dataset.createUserMail;
        confirmed = true;
        dialog.close();
        form.requestSubmit();
        // If browser validation stopped submission, ask again on the next save.
        if (!submitting) confirmed = false;
    }));
    document.getElementById('create-user-mail-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', () => { confirmed = false; choice.value = '0'; });
    dialog.addEventListener('close', () => document.querySelector('#create-user-form [type="submit"]')?.focus());
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        confirmed = submitting = false;
        choice.value = '0';
        form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = false);
    });
})();
