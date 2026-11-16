export function dragAxis(dx, dy) {
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 6) return null;
    return Math.abs(dy) > Math.abs(dx) ? 'vertical' : 'horizontal';
}

export function targetRow(rows, y) {
    return rows.reduce((nearest, row) => !nearest || Math.abs(row.y - y) < Math.abs(nearest.y - y) ? row : nearest, null);
}

export function bindProjectGanttReorder(container, move) {
    let gesture = null;
    let replaying = false;
    let suppressClick = false;

    function clear() {
        if (gesture) {
            gesture.wrapper.style.translate = '';
            gesture.wrapper.style.opacity = '';
        }
        gesture = null;
    }

    container.addEventListener('mousedown', event => {
        if (replaying || event.button !== 0) return;
        suppressClick = false;
        const wrapper = event.target.closest('.bar-wrapper[data-id^="task-"]');
        if (!wrapper || !event.target.closest('.bar, .bar-progress, .bar-label') || event.target.closest('.handle')) return;
        clear();
        const rows = Array.from(container.querySelectorAll('.bar-wrapper[data-id^="task-"]'), row => {
            const rect = row.querySelector('.bar').getBoundingClientRect();
            return {id: row.dataset.id, y: rect.top + rect.height / 2};
        });
        const source = rows.find(row => row.id === wrapper.dataset.id);
        gesture = {wrapper, rows, source, axis: null, down: event};
        // Delay Frappe's horizontal gesture until the intended axis is known.
        event.preventDefault();
        event.stopPropagation();
    }, true);

    document.addEventListener('mousemove', event => {
        if (!gesture) return;
        const {down, wrapper} = gesture;
        if (!wrapper.isConnected || !(event.buttons & 1)) { clear(); return; }
        const dy = event.clientY - down.clientY;
        gesture.axis ||= dragAxis(event.clientX - down.clientX, dy);
        if (gesture.axis === 'horizontal') {
            clear();
            replaying = true;
            try {
                down.target.dispatchEvent(new MouseEvent('mousedown', {
                    bubbles: true, button: 0, buttons: 1, clientX: down.clientX, clientY: down.clientY, view: window,
                }));
            } finally { replaying = false; }
        } else if (gesture.axis === 'vertical') {
            event.stopPropagation();
            wrapper.style.translate = '0 ' + dy + 'px';
            wrapper.style.opacity = '0.65';
        }
    }, true);

    document.addEventListener('mouseup', event => {
        if (!gesture) return;
        const {axis, rows, source, down, wrapper} = gesture;
        clear();
        event.stopPropagation();
        if (axis !== 'vertical' || !wrapper.isConnected) return;
        suppressClick = true;
        const target = targetRow(rows, source.y + event.clientY - down.clientY);
        if (target && target.id !== source.id) move(source.id, target.id);
    }, true);

    container.addEventListener('click', event => {
        if (!suppressClick) return;
        suppressClick = false;
        event.stopPropagation();
    }, true);
    window.addEventListener('blur', clear);
}
