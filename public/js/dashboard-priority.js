export function enableDashboardPriority({containers, save, onError}) {
    const items = container => [...container.querySelectorAll('[data-company-id]')];
    let savedOrder = items(containers[0]).map(item => item.dataset.companyId);
    let dragged = null;
    let saving = false;
    const restore = order => containers.forEach(container => {
        const byId = new Map(items(container).map(item => [item.dataset.companyId, item]));
        order.forEach(id => container.appendChild(byId.get(id)));
    });
    const clear = () => containers.forEach(container => items(container).forEach(item => {
        item.draggable = false;
        item.classList.remove('dashboard-dragging', 'dashboard-drag-target');
    }));
    containers.forEach(container => {
        items(container).forEach(item => {
            const handle = item.querySelector('.priority-drag-handle');
            handle?.addEventListener('pointerdown', () => item.draggable = !saving);
            handle?.addEventListener('pointerup', () => item.draggable = false);
            item.addEventListener('dragstart', event => {
                if (!item.draggable || saving) return event.preventDefault();
                dragged = item;
                item.classList.add('dashboard-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.dataset.companyId);
            });
            item.addEventListener('dragend', () => {
                // A cancelled drag must not leave an unsaved order on screen.
                if (dragged) restore(savedOrder);
                dragged = null;
                clear();
            });
        });
        container.addEventListener('dragover', event => {
            if (!dragged || dragged.parentElement !== container) return;
            event.preventDefault();
            const target = event.target.closest('[data-company-id]');
            items(container).forEach(item => item.classList.remove('dashboard-drag-target'));
            if (!target || target.parentElement !== container || target === dragged) return;
            target.classList.add('dashboard-drag-target');
            const bounds = target.getBoundingClientRect();
            const after = event.clientY > bounds.top + bounds.height / 2
                || (container.id === 'clientsGrid' && Math.abs(event.clientY - (bounds.top + bounds.height / 2)) < bounds.height / 3 && event.clientX > bounds.left + bounds.width / 2);
            container.insertBefore(dragged, after ? target.nextSibling : target);
        });
        container.addEventListener('drop', async event => {
            if (!dragged || dragged.parentElement !== container) return;
            event.preventDefault();
            // Include hidden clients so a search never drops them from the saved order.
            const order = items(container).map(item => item.dataset.companyId);
            dragged = null;
            saving = true;
            clear();
            restore(order);
            try {
                await save(order);
                savedOrder = order;
                const table = containers[1].closest('table');
                table.querySelectorAll('[aria-sort]').forEach(header => header.removeAttribute('aria-sort'));
                table.querySelectorAll('.app-table-sort-marker').forEach(marker => marker.textContent = '↕');
            } catch (error) {
                restore(savedOrder);
                onError(error);
            } finally {
                saving = false;
            }
        });
    });
}
