const offerSectionPhotos = (() => {
    const state = new WeakMap();
    const panels = new WeakMap();
    let pending = 0;

    document.addEventListener('submit', event => {
        if (event.target.id === 'offer-form' && pending) {
            event.preventDefault();
            event.stopImmediatePropagation();
            alert('Poczekaj, aż zdjęcia zostaną przygotowane.');
        }
    }, true);

    async function prepare(file) {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 15000000) {
            throw new Error('Wybierz zdjęcie JPG, PNG lub WebP do 15 MB.');
        }
        const bitmap = await createImageBitmap(file);
        try {
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 1500 / Math.max(bitmap.width, bitmap.height));
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            for (const quality of [0.82, 0.68, 0.5, 0.36]) {
                const ctx = canvas.getContext('2d', {alpha: false});
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                const source = canvas.toDataURL('image/jpeg', quality);
                if (source.length <= 140000) return source;
                canvas.width = Math.max(1, Math.round(canvas.width * 0.8));
                canvas.height = Math.max(1, Math.round(canvas.height * 0.8));
            }
            throw new Error('Nie udało się zmniejszyć zdjęcia. Wybierz mniejszy plik.');
        } finally {
            bitmap.close();
        }
    }

    function mount(card, photos = []) {
        panels.get(card)?.remove();
        const items = Array.isArray(photos) ? photos.filter(source => typeof source === 'string' && source.startsWith('data:image/jpeg;base64,')).slice(0, 4) : [];
        state.set(card, items);
        const panel = document.createElement('div');
        panel.className = 'offer-section-photos';
        panel.innerHTML = '<button type="button" class="btn-add-section"><i class="ti ti-photo-plus" aria-hidden="true"></i> Dodaj zdjęcia</button><input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden><span role="status"></span><div class="offer-section-photo-list"></div>';
        const [button, picker, status, list] = panel.children;
        const changed = () => card.dispatchEvent(new Event('change', {bubbles: true}));
        const render = () => {
            list.replaceChildren();
            items.forEach((source, index) => {
                const figure = document.createElement('figure');
                const image = document.createElement('img');
                image.src = source;
                image.alt = 'Zdjęcie ' + (index + 1);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-del-section';
                remove.textContent = 'Usuń';
                remove.setAttribute('aria-label', 'Usuń zdjęcie ' + (index + 1));
                remove.onclick = () => { items.splice(index, 1); status.textContent = ''; render(); changed(); };
                figure.append(image, remove);
                list.append(figure);
            });
        };
        button.onclick = () => picker.click();
        picker.onchange = async () => {
            const files = [...picker.files];
            picker.value = '';
            if (!files.length) return;
            pending++;
            button.disabled = true;
            status.textContent = 'Przygotowywanie zdjęć…';
            try {
                for (const file of files) {
                    const source = await prepare(file);
                    if (!panel.isConnected) break;
                    const total = [...document.querySelectorAll('#offer-form .ed-card')].reduce((n, item) => n + (state.get(item)?.length ?? 0), 0);
                    if (items.length >= 4 || total >= 8) throw new Error('Limit to 4 zdjęcia w sekcji i 8 w ofercie.');
                    items.push(source);
                    render();
                    changed();
                }
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message || 'Nie udało się odczytać zdjęcia.';
            } finally {
                pending--;
                button.disabled = false;
            }
        };
        panels.set(card, panel);
        card.append(panel);
        render();
    }

    return {mount, collect: card => [...(state.get(card) ?? [])]};
})();
