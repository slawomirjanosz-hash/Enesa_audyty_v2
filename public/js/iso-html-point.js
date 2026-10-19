(() => {
    const send = (action, profile = null) => parent.postMessage({enesa: ENESA_POINT.channel, action, profile}, '*');
    window.widok = name => {
        if (name === 'ankieta') { send('open'); return; }
        ['strona','spec'].forEach(key => {
            const panel = document.getElementById('w_' + key); if (panel) panel.style.display = key === name ? '' : 'none';
            document.getElementById('t_' + key)?.classList.toggle('akt', key === name);
        });
        window.scrollTo(0, 0);
    };
    document.querySelectorAll('[data-profile]').forEach(button => button.addEventListener('click', () => send('open', button.dataset.profile)));
    document.querySelector('a.wroc')?.addEventListener('click', event => { event.preventDefault(); send('back'); });
    document.querySelectorAll('a[href="#"]:not(.wroc)').forEach(link => {
        link.setAttribute('aria-disabled', 'true'); link.title = 'Plik źródłowy nie zawiera docelowego odnośnika.';
        link.addEventListener('click', event => event.preventDefault());
    });
    window.addEventListener('message', event => {
        if (event.source === parent && event.data?.enesa === ENESA_POINT.channel && event.data.action === 'choose') document.querySelector('[data-profile-cards]')?.scrollIntoView({behavior:'smooth'});
    });
    widok('strona');
})();
