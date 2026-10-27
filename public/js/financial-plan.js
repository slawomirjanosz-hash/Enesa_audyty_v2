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

// Tooltips use the browser's top layer so tables cannot clip their contents.
let activeTip = null;
let tipAnchor = null;
let closeTipTimer;
const closeTip = () => {
    if (activeTip?.matches(':popover-open')) activeTip.hidePopover();
    activeTip = null;
    tipAnchor = null;
};
const placeTip = () => {
    if (!activeTip || !tipAnchor) return;
    const anchor = tipAnchor.getBoundingClientRect();
    const box = activeTip.getBoundingClientRect();
    const left = Math.max(12, Math.min(window.innerWidth - box.width - 12, anchor.left));
    const below = anchor.bottom + 9;
    const top = below + box.height <= window.innerHeight - 12 ? below : Math.max(12, anchor.top - box.height - 9);
    activeTip.style.left = left + 'px';
    activeTip.style.top = top + 'px';
};
const showTip = anchor => {
    clearTimeout(closeTipTimer);
    if (tipAnchor === anchor && activeTip) return;
    closeTip();
    const tip = document.getElementById(anchor.dataset.fpTip);
    if (!tip) return;
    activeTip = tip;
    tipAnchor = anchor;
    tip.showPopover();
    placeTip();
};
const scheduleTipClose = () => {
    clearTimeout(closeTipTimer);
    closeTipTimer = setTimeout(closeTip, 160);
};
document.addEventListener('pointerover', event => {
    const anchor = event.target.closest('[data-fp-tip]');
    if (anchor) showTip(anchor);
    else if (event.target.closest('.fp-tooltip')) clearTimeout(closeTipTimer);
});
document.addEventListener('pointerout', event => {
    if (event.target.closest('[data-fp-tip],.fp-tooltip')) scheduleTipClose();
});
document.addEventListener('focusin', event => {
    const anchor = event.target.closest('[data-fp-tip]');
    if (anchor) showTip(anchor);
});
document.addEventListener('focusout', event => {
    if (event.target.closest('[data-fp-tip]')) scheduleTipClose();
});
document.addEventListener('click', event => {
    const anchor = event.target.closest('[data-fp-tip]');
    if (anchor) {
        event.preventDefault();
        showTip(anchor);
    } else if (!event.target.closest('.fp-tooltip')) closeTip();
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeTip();
    if (['Enter', ' '].includes(event.key) && event.target.matches('g[data-fp-tip]')) {
        event.preventDefault();
        showTip(event.target);
    }
});
window.addEventListener('resize', placeTip);
document.addEventListener('scroll', event => {
    if (!event.target.closest?.('.fp-tooltip')) closeTip();
}, true);

const plan = document.getElementById('financial-plan');
const live = document.getElementById('fp-live-calculation');
const status = document.getElementById('fp-refresh-status');
if (plan && live && status) {
    let busy = false;
    let stopped = false;
    let fingerprint = '';
    let pendingData = null;
    const apply = data => {
        if (stopped) return;
        if (!data.complete) {
            document.getElementById('fp-history')?.remove();
            const editor = document.getElementById('line-form');
            if (editor) {
                const message = document.createElement('p');
                message.textContent = 'Część źródeł jest niedostępna. Odśwież stronę przed edycją pozycji planu.';
                editor.replaceChildren(message);
            }
        }
        // Never replace an in-progress delete form or another control the user is editing.
        if (data.complete && live.querySelector('input:focus,textarea:focus,select:focus')) { pendingData = data; return; }
        pendingData = null;
        if (data.fingerprint === fingerprint) return;
        const details = new Map([...live.querySelectorAll('details[data-fp-details]')].map(node => [node.dataset.fpDetails, node.open]));
        const scrolls = new Map([...live.querySelectorAll('[data-fp-scroll]')].map(node => [node.dataset.fpScroll, node.scrollLeft]));
        const sorts = [...live.querySelectorAll('table[data-fp-table]')].flatMap(table => {
            const header = table.querySelector('th[aria-sort]');
            return header ? [{key: table.dataset.fpTable, column: header.cellIndex, direction: header.getAttribute('aria-sort')}] : [];
        });
        const x = window.scrollX, y = window.scrollY;
        closeTip();
        live.innerHTML = data.html;
        fingerprint = data.fingerprint;
        document.getElementById('fp-period-label').textContent = data.period;
        live.querySelectorAll('details[data-fp-details]').forEach(node => {
            if (details.has(node.dataset.fpDetails)) node.open = details.get(node.dataset.fpDetails);
        });
        // The shared table-sort observer enhances new tables before this frame.
        requestAnimationFrame(() => {
            sorts.forEach(sort => {
                const table = [...live.querySelectorAll('table[data-fp-table]')].find(node => node.dataset.fpTable === sort.key);
                const button = table?.tHead?.rows[0]?.cells[sort.column]?.querySelector('.app-table-sort');
                button?.click();
                if (sort.direction === 'descending') button?.click();
            });
            live.querySelectorAll('[data-fp-scroll]').forEach(node => { node.scrollLeft = scrolls.get(node.dataset.fpScroll) || 0; });
            window.scrollTo(x, y);
        });
    };
    const refresh = async () => {
        if (busy || stopped || document.hidden) return;
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(plan.dataset.calculationUrl, {headers: {Accept: 'application/json', 'X-Plan-Fingerprint': fingerprint}, cache: 'no-store', credentials: 'same-origin', signal: controller.signal});
            if ([401, 403, 404].includes(response.status)) {
                stopped = true;
                pendingData = null;
                closeTip();
                const message = document.createElement('p');
                message.className = 'fp-note fp-error';
                message.setAttribute('role', 'alert');
                message.textContent = 'Brak dostępu do aktualnego planu. Odśwież stronę, aby sprawdzić sesję i uprawnienia.';
                plan.replaceChildren(message);
                return;
            }
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Refresh failed');
            const data = await response.json();
            if (typeof data.fingerprint !== 'string' || (data.fingerprint !== fingerprint && typeof data.html !== 'string')) throw new Error('Invalid calculation');
            apply(data);
            status.textContent = Number(data.revision) === Number(plan.dataset.revision)
                ? 'Aktualne · ' + new Date().toLocaleTimeString('pl-PL') + ' · co 5 s'
                : 'Wyliczenia aktualne. Pozycje planu zmieniono w innej karcie — odśwież stronę przed zapisem formularza.';
            status.classList.remove('fp-refresh-error');
        } catch {
            status.textContent = 'Nie udało się odświeżyć. Widoczne są ostatnie pobrane dane; spróbujemy ponownie.';
            status.classList.add('fp-refresh-error');
        } finally {
            clearTimeout(timeout);
            busy = false;
        }
    };
    live.addEventListener('focusout', () => {
        if (pendingData) setTimeout(() => { const data = pendingData; pendingData = null; apply(data); }, 0);
    });
    document.getElementById('fp-refresh')?.addEventListener('click', refresh);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    setInterval(refresh, 5000);
    refresh();
}
