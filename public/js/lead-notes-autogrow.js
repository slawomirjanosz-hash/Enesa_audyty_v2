document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-lead-notes-autogrow]').forEach(field => {
        const resize = () => {
            if (!field.getClientRects().length || !field.clientWidth) return;
            const style = getComputedStyle(field);
            const border = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
            const padding = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
            field.style.height = 'auto';
            field.style.height = (field.scrollHeight + (style.boxSizing === 'border-box' ? border : -padding)) + 'px';
            field.style.overflowY = 'hidden';
        };
        field.addEventListener('input', resize);
        field.addEventListener('change', resize);
        field.addEventListener('focus', resize);
        field.form?.addEventListener('reset', () => requestAnimationFrame(resize));
        // Hidden modals have no usable width. Recalculate when opened or resized,
        // but ignore height-only changes to avoid an observer feedback loop.
        let previousWidth = -1;
        const observer = new ResizeObserver(entries => {
            const width = entries[0].contentRect.width;
            if (width !== previousWidth) {
                previousWidth = width;
                resize();
            }
        });
        observer.observe(field);
        resize();
        document.fonts?.ready.then(resize);
    });
});
