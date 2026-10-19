document.querySelectorAll('[data-iso-html-editor]').forEach(host => {
    const frame = host.querySelector('iframe');
    const form = host.querySelector('[data-iso-html-transport]');
    const approvals = host.querySelector('[data-iso-html-approvals]');
    const tools = host.querySelector('[data-iso-html-tools]');
    document.querySelectorAll('[data-iso-html-native] .questionnaire-excel').forEach(control => tools.append(control));
    const related = [...document.querySelectorAll('[data-iso-html-native] section')].find(section => section.querySelector(':scope > h2')?.textContent.trim() === 'Dane powiązane');
    if (related) {
        const details = document.createElement('details'); details.className = 'plant-card';
        const summary = document.createElement('summary'); summary.textContent = 'Dane powiązane'; details.append(summary);
        related.querySelector(':scope > h2').remove(); details.append(...related.childNodes); related.remove(); tools.append(details);
    }
    const native = document.getElementById(host.dataset.approvals);
    if (native) { native.removeAttribute('data-iso-panel'); native.hidden = false; approvals.append(native); }
    // 4.1 approves the saved consultant analysis together with the questionnaire.
    const analysis = document.querySelector('[data-swot-form]');
    const approve = analysis?.querySelector('[name="operation"][value="approve"]');
    if (approve) {
        const action = document.createElement('form'); action.method = 'post'; action.action = analysis.action;
        for (const [name, value] of new FormData(analysis)) {
            if (name === 'operation') continue;
            const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; action.append(input);
        }
        action.append(approve.cloneNode(true)); approvals.append(action);
    }
    if (host.dataset.section === '4-2') {
        const consultant = document.getElementById('consultant');
        if (consultant) approvals.append(consultant);
    }
    approvals.hidden = host.dataset.section !== 'intro';
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    tools.addEventListener('submit', event => { if (!event.defaultPrevented) dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    approvals.addEventListener('submit', event => { if (dirty) { event.preventDefault(); alert('Najpierw zapisz zmiany w ankiecie lub analizie konsultanta.'); } });
    window.addEventListener('message', event => {
        if (event.source !== frame.contentWindow || event.data?.enesa !== host.dataset.channel) return;
        const message = event.data;
        if (message.action === 'back') {
            const link = document.querySelector('header.plant-header a');
            if (link) location.href = link.href;
            return;
        }
        if (message.action === 'dirty') { dirty = true; return; }
        if (message.action === 'tab') { approvals.hidden = host.dataset.section !== 'intro' && message.state.id !== 'zatw'; return; }
        if (!['save', 'submit', 'consultant'].includes(message.action)) return;
        if (!message.state || typeof message.state !== 'object') return;
        form.elements.operation.value = message.action === 'consultant' && host.dataset.section === '4-1' ? 'save_swot' : message.action;
        form.elements.html_state.value = JSON.stringify(message.state);
        dirty = false;
        form.requestSubmit();
    });
});
