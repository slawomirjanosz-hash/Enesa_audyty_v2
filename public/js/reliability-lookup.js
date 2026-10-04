document.addEventListener('DOMContentLoaded', async () => {
    const panel = document.getElementById('rel-auto-lookup');
    if (!panel) return;
    // Do not allow editing during the one-time refresh, so reloading never loses input.
    const fieldsets = [];
    document.querySelectorAll('.rel-wrap form').forEach(form => {
        const fieldset = document.createElement('fieldset');
        fieldset.style.cssText = 'border:0;padding:0;margin:0;min-width:0;display:contents';
        while (form.firstChild) fieldset.append(form.firstChild);
        fieldset.disabled = true;
        form.append(fieldset);
        fieldsets.push(fieldset);
    });
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 25000);
    try {
        const response = await fetch(panel.dataset.url, {
            method: 'POST', signal: controller.signal,
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': panel.dataset.token},
            body: JSON.stringify({krs: panel.dataset.krs || null})
        });
        if (!response.ok || !(await response.json()).checked) throw new Error('lookup');
        location.reload();
    } catch {
        panel.textContent = 'Nie udało się odświeżyć rejestrów. Możesz pracować dalej; odśwież stronę, aby ponowić sprawdzenie. Brak wyniku nie oznacza braku zagrożeń.';
        fieldsets.forEach(fieldset => { fieldset.replaceWith(...fieldset.childNodes); });
    } finally {
        clearTimeout(timer);
    }
});
