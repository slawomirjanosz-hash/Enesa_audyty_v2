(() => {
    const config = typeof ENESA_HOST === 'undefined' ? ENESA_POINT : ENESA_HOST;
    const pending = new Map(); let id = 0;
    Object.defineProperty(navigator, 'clipboard', {value:{writeText(text) {
        return new Promise((resolve, reject) => {
            const key = ++id; pending.set(key, {resolve, reject});
            parent.postMessage({enesa:config.channel, action:'copy', text:String(text), id:key}, '*');
        });
    }}});
    window.addEventListener('message', event => {
        if (event.source !== parent || event.data?.enesa !== config.channel || event.data.action !== 'copied') return;
        const request = pending.get(event.data.id); if (!request) return;
        pending.delete(event.data.id);
        if (event.data.ok) request.resolve();
        else { alert('Przeglądarka zablokowała kopiowanie. Zaznacz tekst i skopiuj go ręcznie.'); request.reject(new Error('Clipboard permission denied')); }
    });
})();
