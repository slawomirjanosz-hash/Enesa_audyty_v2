const host = document.getElementById('line-form');
const selector = host?.querySelector('[data-fp-kind]');

if (host && selector) {
    const refresh = () => {
        host.querySelectorAll('[data-fp-kinds]').forEach(group => {
            const visible = group.dataset.fpKinds.split(' ').includes(selector.value);
            group.hidden = !visible;
            group.querySelectorAll('input,select,textarea').forEach(control => {
                control.disabled = !visible;
            });
        });
    };
    selector.addEventListener('change', refresh);
    refresh();
}
