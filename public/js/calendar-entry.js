document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('#calendar-editor');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const updateKind = () => {
        const task = form.elements.calendar_kind.value === 'task';
        dialog.querySelectorAll('[data-crm-task-fields]').forEach(section => {
            section.hidden = !task;
            section.querySelectorAll('input,select,textarea').forEach(field => field.disabled = !task);
        });
        dialog.querySelector('[data-calendar-task-note]').hidden = !task;
        dialog.querySelector('[data-calendar-meeting-note]').hidden = task;
    };
    const filterLeads = () => {
        if (!form.elements.crm_opportunity_id) return;
        const company = form.elements.company_id.value;
        Array.from(form.elements.crm_opportunity_id.options).forEach(option => {
            if (!option.value) return;
            option.hidden = Boolean(company) && option.dataset.companyId !== company;
            option.disabled = option.hidden;
        });
        if (form.elements.crm_opportunity_id.selectedOptions[0]?.disabled) form.elements.crm_opportunity_id.value = '';
    };
    form.elements.calendar_kind.addEventListener('change', updateKind);
    form.elements.company_id?.addEventListener('change', filterLeads);
    form.elements.crm_opportunity_id?.addEventListener('change', () => {
        const option = form.elements.crm_opportunity_id.selectedOptions[0];
        if (option?.value) form.elements.company_id.value = option.dataset.companyId || '';
        filterLeads();
    });
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-calendar-new]');
        const day = event.target.closest('[data-calendar-date]');
        if (!trigger && (!day || event.target.closest('a, button, input, select'))) return;
        form.reset();
        form.elements.title.value = '';
        form.elements.description.value = '';
        form.elements.scheduled_time.value = '';
        dialog.querySelector('[role="alert"]')?.setAttribute('hidden', '');
        form.elements.due_date.value = trigger?.dataset.date || day.dataset.calendarDate;
        form.elements.calendar_kind.value = trigger?.dataset.calendarNew || 'task';
        if (!form.elements.calendar_kind.value) form.elements.calendar_kind.value = 'meeting';
        updateKind();
        filterLeads();
        dialog.showModal();
        form.elements.title.focus();
    });
    dialog.querySelectorAll('[data-calendar-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    form.addEventListener('submit', () => { form.querySelector('[type="submit"]').disabled = true; });
    updateKind();
    filterLeads();
    if (dialog.hasAttribute('data-reopen')) dialog.showModal();
});
