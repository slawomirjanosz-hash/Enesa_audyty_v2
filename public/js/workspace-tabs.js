document.addEventListener('DOMContentLoaded', () => {
    const bar = document.querySelector('[data-workspace-tabs]');
    if (!bar) return;
    const container = bar.querySelector('[data-workspace-tabs-items]');
    const prefix = 'workspace-tabs:';
    const storageKey = prefix + bar.dataset.scope;
    const allowedQuery = new Set(['tab','section','month','view','status','q','page','completed_page','sort','direction','scope','user_id','active_sort','active_direction','completed_sort','completed_direction']);
    const sections = {pipeline:'Lejek sprzedaży',tasks:'Zadania',companies:'Firmy',suppliers:'Dostawcy',contacts:'Kontakty',vehicles:'Samochody',delegations:'Delegacje',leaves:'Urlopy',attendance:'Obecności'};
    let entries = [];
    let expired = false;
    let closedCurrent = false;
    function currentUrl() {
        const url = new URL(location.href);
        if (bar.dataset.section && !url.searchParams.has('tab')) url.searchParams.set('tab', bar.dataset.section);
        return url.href;
    }
    function safeUrl(value) {
        try {
            const url = new URL(value, location.origin);
            if (url.origin !== location.origin || !['http:','https:'].includes(url.protocol) || url.username || url.password) return null;
            if (/\/(logout|login|register|rejestracja)(\/|$)/.test(url.pathname)) return null;
            for (const name of [...url.searchParams.keys()]) if (!allowedQuery.has(name)) url.searchParams.delete(name);
            return url.pathname + url.search + url.hash;
        } catch {return null;}
    }
    function identity(value) {
        const url = new URL(value, location.origin);
        // Sections of an individual record share one tab; module sections are separate.
        const path = url.pathname.replace(/\/edit\/?$/, '').replace(/\/$/, '') || '/';
        return path + (['/crm','/hr'].includes(path) ? '?tab=' + (url.searchParams.get('tab') || '') : '');
    }
    function persist() {
        try {sessionStorage.setItem(storageKey, JSON.stringify(entries));} catch {/* Navigation still works without browser storage. */}
    }
    function render() {
        container.replaceChildren();
        const current = identity(currentUrl());
        entries.forEach(entry => {
            const item = document.createElement('div');
            item.className = 'workspace-tab' + (entry.key === current ? ' is-active' : '');
            const link = document.createElement('a');
            link.href = entry.url;
            link.textContent = entry.title;
            link.title = entry.title;
            if (entry.key === current) link.setAttribute('aria-current', 'page');
            const close = document.createElement('button');
            close.type = 'button';
            close.textContent = '×';
            close.title = 'Zamknij zakładkę';
            close.setAttribute('aria-label', 'Zamknij zakładkę: ' + entry.title);
            close.addEventListener('click', () => {
                if (entry.key === current) closedCurrent = true;
                entries = entries.filter(row => row.key !== entry.key);
                persist(); render();
                // Closing removes the shortcut, not the open form or its unsaved data.
                container.querySelector('a[aria-current]')?.focus();
            });
            item.append(link, close); container.append(item);
        });
        bar.hidden = entries.length === 0;
        container.querySelector('.is-active')?.scrollIntoView({block:'nearest',inline:'nearest'});
    }
    function remember() {
        if (expired || closedCurrent) return;
        const url = safeUrl(currentUrl());
        if (!url) return;
        const key = identity(url);
        const section = sections[new URL(url, location.origin).searchParams.get('tab')];
        const title = (bar.dataset.title + (['/crm','/hr'].includes(location.pathname) && section ? ' · ' + section : '')).slice(0, 180);
        const existing = entries.find(entry => entry.key === key);
        if (existing) Object.assign(existing, {url, title});
        else {entries.push({key, url, title}); if (entries.length > 10) entries.shift();}
        persist(); render();
    }
    function clear() {
        expired = true; entries = [];
        try {sessionStorage.removeItem(storageKey);} catch {}
        render();
    }
    function restore() {
        entries = [];
        try {
            Object.keys(sessionStorage).filter(key => key.startsWith(prefix) && key !== storageKey).forEach(key => sessionStorage.removeItem(key));
            const saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            if (Array.isArray(saved)) for (const row of saved.slice(-10)) {
                const url = typeof row?.url === 'string' && safeUrl(row.url);
                if (!url || typeof row.title !== 'string') continue;
                const key = identity(url);
                if (!entries.some(entry => entry.key === key)) entries.push({key,url,title:row.title.slice(0,180)});
            }
        } catch {entries = [];}
    }
    restore();
    remember();
    window.addEventListener('hashchange', remember);
    window.addEventListener('popstate', remember);
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        try {if (!sessionStorage.getItem(storageKey)) {clear(); return;}} catch {}
        restore(); closedCurrent = false; remember();
    });
    // Save in-page section changes made through history.replaceState before leaving.
    window.addEventListener('pagehide', () => {if (!expired) remember();});
    window.addEventListener('session:expired', clear);
    document.addEventListener('submit', event => {
        if (!(event.target instanceof HTMLFormElement) || event.target.action !== bar.dataset.logout) return;
        queueMicrotask(() => {if (!event.defaultPrevented) clear();});
    });
});
