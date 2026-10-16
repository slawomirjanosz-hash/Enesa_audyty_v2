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
    if (!form) return;
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        const choice = document.getElementById('edit-user-send-email');
        choice.value = '0';
        if (document.getElementById('edit_password').value) {
            choice.value = window.confirm(`Czy wysłać wiadomość z nowym hasłem na adres ${document.getElementById('edit_email').value}?\n\nOK — zapisz i wyślij e-mail.\nAnuluj — zapisz bez wysyłania e-maila.`) ? '1' : '0';
        }
        submitting = true;
        form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = true);
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        submitting = false;
        document.getElementById('edit-user-send-email').value = '0';
        form.querySelectorAll('[type="submit"]').forEach(button => button.disabled = false);
    });
})();
