(() => {
    const form = document.getElementById('attendance-form'), modal = document.getElementById('attendance-modal');
    if (!form || !modal) return;
    let generation = 0, request;
    const load = async () => {
        const current = ++generation;
        request?.abort();
        if (!form.elements.work_date.value) return;
        const url = new URL(form.dataset.hoursUrl, location.href);
        url.searchParams.set('work_date', form.elements.work_date.value);
        if (form.elements.user_id) url.searchParams.set('user_id', form.elements.user_id.value);
        request = new AbortController();
        try {
            const response = await fetch(url, {signal: request.signal, headers: {'Accept': 'application/json'}});
            if (!response.ok || response.redirected) return;
            const hours = await response.json();
            if (current !== generation) return;
            form.elements.started_at.value = hours.started_at;
            form.elements.finished_at.value = hours.finished_at;
        } catch (_) { /* A failed suggestion never prevents manual attendance entry. */ }
    };
    form.elements.work_date.addEventListener('change', load);
    form.elements.user_id?.addEventListener('change', load);
    ['started_at', 'finished_at'].forEach(name => form.elements[name].addEventListener('input', () => { generation++; request?.abort(); }));
    new MutationObserver(() => { if (modal.classList.contains('open')) load(); })
        .observe(modal, {attributes: true, attributeFilter: ['class']});
})();
