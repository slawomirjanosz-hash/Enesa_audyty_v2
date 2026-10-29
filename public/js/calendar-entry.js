document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('#calendar-editor');
    if (!dialog) return;
    const form = dialog.querySelector('form');
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
        dialog.showModal();
        form.elements.title.focus();
    });
    dialog.querySelectorAll('[data-calendar-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    form.addEventListener('submit', () => { form.querySelector('[type="submit"]').disabled = true; });
    if (dialog.hasAttribute('data-reopen')) dialog.showModal();
});
