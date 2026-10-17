(() => {
    const data = document.getElementById('user-permission-data');
    if (!data) return;
    const sets = JSON.parse(data.textContent);
    const isClient = prefix => ['client_admin', 'client_user'].includes(document.getElementById(`${prefix}_role`).value);
    function fill(prefix, permissions, message = '') {
        const panel = document.getElementById(`${prefix}_permissions_panel`);
        if (!panel) return;
        const selected = new Set(isClient(prefix) ? [] : permissions);
        panel.querySelectorAll('[name="permissions[]"]').forEach(input => input.checked = selected.has(input.value));
        panel.hidden = isClient(prefix);
        document.getElementById(`${prefix}_permissions_status`).textContent = message;
    }
    function loadRole(prefix) {
        fill(prefix, sets.roles[document.getElementById(`${prefix}_role`).value] || [], 'Wczytano zestaw roli. Możesz go teraz indywidualnie zmienić.');
    }
    document.querySelectorAll('[data-load-role]').forEach(button => button.addEventListener('click', () => {
        if (window.confirm('Zastąpić zaznaczone uprawnienia zestawem wybranej roli? Zmiana zostanie zapisana dopiero po zapisaniu formularza.')) loadRole(button.dataset.loadRole);
    }));
    document.querySelectorAll('[data-copy-permissions]').forEach(button => button.addEventListener('click', () => {
        const prefix = button.dataset.copyPermissions;
        const source = document.getElementById(`${prefix}_copy_permissions`).value;
        if (!source || !sets.users[source]) return;
        if (window.confirm('Zastąpić zaznaczone uprawnienia kopią uprawnień wybranego użytkownika?')) {
            fill(prefix, sets.users[source], 'Skopiowano uprawnienia. Kopia będzie niezależna od konta źródłowego.');
        }
    }));
    document.getElementById('add_role')?.addEventListener('change', () => loadRole('add'));
    document.getElementById('edit_role')?.addEventListener('change', () => {
        // A role change alone does not replace an existing employee's permissions.
        const panel = document.getElementById('edit_permissions_panel');
        if (panel) panel.hidden = isClient('edit');
        if (isClient('edit')) fill('edit', []);
    });
    window.loadIndividualPermissions = id => {
        fill('edit', sets.users[id] || []);
        const copy = document.getElementById('edit_copy_permissions');
        if (copy) copy.value = '';
    };
})();
