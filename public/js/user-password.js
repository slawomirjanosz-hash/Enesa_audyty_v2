(() => {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?';
    function generatePassword() {
        // Reject values outside the largest multiple of the alphabet length.
        const limit = 256 - (256 % alphabet.length);
        let password;
        do {
            password = '';
            while (password.length < 20) {
                const bytes = crypto.getRandomValues(new Uint8Array(32));
                for (const byte of bytes) {
                    if (byte < limit && password.length < 20) password += alphabet[byte % alphabet.length];
                }
            }
        } while (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[!@#$%&*?]/.test(password));
        return password;
    }
    document.querySelectorAll('[data-generate-password]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.generatePassword);
            input.value = generatePassword();
            input.type = 'text';
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.focus();
            input.select();
        });
    });
    const form = document.getElementById('editForm');
    const dialog = document.getElementById('edit-user-password-dialog');
    if (!form || !dialog) return;
    const choice = document.getElementById('edit-user-send-email');
    let submitting = false, confirmed = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (!confirmed) {
            choice.value = '0';
            if (document.getElementById('edit_password').value) {
                event.preventDefault();
                document.getElementById('edit-user-password-recipient').textContent = document.getElementById('edit_email').value;
                if (!dialog.open) dialog.showModal();
                return;
            }
        }
        confirmed = false;
        submitting = true;
        form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = true);
    });
    dialog.querySelectorAll('[data-edit-user-mail]').forEach(button => button.addEventListener('click', () => {
        if (submitting) return;
        choice.value = button.dataset.editUserMail;
        confirmed = true;
        dialog.close();
        form.requestSubmit();
        if (!submitting) { confirmed = false; choice.value = '0'; }
    }));
    document.getElementById('edit-user-password-back').addEventListener('click', () => {
        confirmed = false;
        choice.value = '0';
        dialog.close();
    });
    document.getElementById('edit-user-password-discard').addEventListener('click', () => {
        confirmed = false;
        choice.value = '0';
        form.reset();
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_password').type = 'password';
        dialog.close();
        window.closeEditModal();
    });
    dialog.addEventListener('cancel', () => { confirmed = false; choice.value = '0'; });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        submitting = confirmed = false;
        choice.value = '0';
        if (dialog.open) dialog.close();
        form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = false);
    });
})();
