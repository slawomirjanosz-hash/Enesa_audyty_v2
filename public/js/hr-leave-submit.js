// Keep the current request open when validation (including its supervisor) fails.
document.getElementById('leave-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (form.dataset.submitting === 'true') return;
    const error = document.getElementById('leave-submit-error');
    const submit = form.querySelector('button:not([type="button"])');
    error.hidden = true;
    form.dataset.submitting = 'true';
    submit.disabled = true;
    try {
        const response = await fetch(form.action, {
            method: 'POST', body: new FormData(form),
            headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        });
        if (response.ok && response.redirected) {
            window.location.assign(response.url);
            return;
        }
        const data = await response.json().catch(() => ({}));
        const messages = Object.values(data.errors || {}).flat();
        error.textContent = (messages.join('\n') || data.message || 'Nie udało się wysłać wniosku.')
            + '\nWpisane dane pozostają w formularzu. Po uzupełnieniu przełożonego możesz ponownie wysłać ten wniosek.';
        error.hidden = false;
    } catch {
        error.textContent = 'Nie udało się potwierdzić wysłania wniosku. Wpisane dane pozostają w formularzu. Sprawdź listę wniosków przed ponownym wysłaniem.';
        error.hidden = false;
    } finally {
        form.dataset.submitting = 'false';
        submit.disabled = false;
    }
});
