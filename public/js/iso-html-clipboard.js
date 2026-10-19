// Keep the source prototype's copy button working without granting its frame an origin.
if (!window.enesaClipboardBound) {
    window.enesaClipboardBound = true;
    window.addEventListener('message', event => {
        if (event.data?.action !== 'copy' || typeof event.data.text !== 'string' || event.data.text.length > 1000000) return;
        const frame = [...document.querySelectorAll('iframe[data-clipboard-channel]')].find(item => item.contentWindow === event.source && item.dataset.clipboardChannel === event.data.enesa);
        if (!frame) return;
        const reply = ok => frame.contentWindow.postMessage({enesa:event.data.enesa, action:'copied', id:event.data.id, ok}, '*');
        if (!navigator.clipboard?.writeText) { reply(false); return; }
        navigator.clipboard.writeText(event.data.text).then(() => reply(true), () => reply(false));
    });
}
