<dialog id="cylinder-photo-viewer" aria-label="Zdjęcie butli"><div class="cyl-photo-toolbar"><strong>Zdjęcie butli</strong><button type="button" class="cyl-btn" data-close-photo>Zamknij ×</button></div><img alt="Powiększone zdjęcie butli"><p data-photo-error hidden>Nie można wczytać zdjęcia. Spróbuj ponownie.</p></dialog>
<style>#cylinder-photo-viewer>[hidden]{display:none}</style>
<script>
(() => {
    const dialog = document.getElementById('cylinder-photo-viewer');
    document.body.appendChild(dialog);
    const image = dialog.querySelector('img');
    const error = dialog.querySelector('[data-photo-error]');
    document.querySelectorAll('[data-cylinder-photo]').forEach(link => link.addEventListener('click', event => {
        if(event.ctrlKey || event.metaKey || event.shiftKey) return;
        event.preventDefault();
        error.hidden = true;
        image.hidden = false;
        image.alt = link.querySelector('img').alt;
        image.src = link.href;
        dialog.showModal();
    }));
    image.addEventListener('error', () => { image.hidden = true; error.hidden = false; });
    dialog.querySelector('[data-close-photo]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if(event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => image.removeAttribute('src'));
})();
</script>
