document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('system-form');
    if (!form) return;
    let sequence = Date.now();
    form.addEventListener('system:before-example', () => {
        for (let depth = 0; depth < 3; depth++) {
            form.querySelectorAll('[data-exec-list]').forEach(host => {
                if (![...host.children].some(el => el.hasAttribute('data-exec-row'))) {
                    [...host.children].find(el => el.hasAttribute('data-exec-add'))?.click();
                }
            });
        }
    });
    form.addEventListener('click', event => {
        const add = event.target.closest('[data-exec-add]');
        const remove = event.target.closest('[data-exec-remove]');
        if (add) {
            const host = add.closest('[data-exec-list]');
            const rows = [...host.children].filter(el => el.hasAttribute('data-exec-row'));
            if (rows.length >= 50) { alert('Maksymalnie 50 pozycji w jednej liście.'); return; }
            const template = [...host.children].find(el => el.hasAttribute('data-exec-template'));
            add.insertAdjacentHTML('beforebegin', template.innerHTML.replaceAll(host.dataset.token, String(++sequence)));
        }
        if (remove) {
            if (!confirm('Usunąć pozycję? Zmiana zostanie utrwalona po zapisaniu ankiety.')) return;
            remove.closest('[data-exec-row]').remove();
        }
        if (add || remove) form.dispatchEvent(new Event('input', { bubbles: true }));
    });
});
