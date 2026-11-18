<style>
    .full-read-only-notice{padding:8px 16px;border:1px solid #D0CCC0;border-radius:8px;background:#FAFAF6;color:var(--green);font-size:12px;margin-bottom:16px}
    [data-full-read-only-form] input,[data-full-read-only-form] select,[data-full-read-only-form] textarea{opacity:1!important}
</style>
<script>
(() => {
    const notice = document.createElement('div');
    notice.className = 'full-read-only-notice';
    notice.textContent = 'Pełny dostęp tylko do odczytu';
    document.querySelector('main')?.prepend(notice);
    const protect = root => {
        root.querySelectorAll('form').forEach(form => {
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            const path = new URL(form.action, location.href).pathname;
            const navigation = [new URL(@json(route('logout')), location.href).pathname, new URL(@json(route('client-zone.stop')), location.href).pathname].includes(path)
                || path.startsWith(new URL(@json(url('/client-zone/impersonate/')), location.href).pathname + '/');
            if (method === 'GET' || navigation) return;
            form.dataset.fullReadOnlyForm = 'true';
            form.removeAttribute('data-autosave');
            form.querySelectorAll('button[type="submit"],button:not([type]),input[type="submit"]').forEach(button => button.hidden = true);
            form.querySelectorAll('input,textarea').forEach(input => {
                input.readOnly = true;
                if (['checkbox', 'radio', 'file'].includes(input.type)) input.disabled = true;
            });
            form.querySelectorAll('select,[contenteditable]').forEach(control => {
                if (control.tagName === 'SELECT') control.disabled = true;
                else control.contentEditable = 'false';
            });
        });
        root.querySelectorAll('a[href$="/edit"],a[href$="/create"],button .ti-trash,button .ti-pencil,button .ti-device-floppy,a .ti-trash,a .ti-pencil').forEach(control => {
            const action = control.closest('a,button');
            if (action) action.hidden = true;
        });
    };
    protect(document);
    document.addEventListener('submit', event => {
        if (event.target.matches('[data-full-read-only-form]')) event.preventDefault();
    }, true);
    document.addEventListener('DOMContentLoaded', () => protect(document));
    new MutationObserver(records => {
        if (records.some(record => record.addedNodes.length)) protect(document);
    }).observe(document.body, {childList: true, subtree: true});
})();
</script>
