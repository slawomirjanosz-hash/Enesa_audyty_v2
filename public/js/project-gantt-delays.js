/* Delay is an overlay. It never changes the editable planned bar or its drag handles. */
window.ProjectGanttDelays = {
    withRange(Gantt, items) {
        const last = items.filter(item => Number(item.overdue_days) > 0).map(item => item.overdue_end).sort().at(-1);
        if (!last) return Gantt;
        return class extends Gantt {
            setup_gantt_dates() {
                super.setup_gantt_dates();
                const end = new Date(last + 'T00:00:00');
                end.setMonth(end.getMonth() + 1);
                if (end > this.gantt_end) this.gantt_end = end;
            }
        };
    },
    draw(root, gantt, items) {
        if (!root || !gantt) return;
        root.querySelectorAll('.gantt-delay-tail').forEach(node => node.remove());
        const ns = 'http://www.w3.org/2000/svg';
        items.filter(item => Number(item.overdue_days) > 0).forEach(item => {
            const wrapper = [...root.querySelectorAll('.bar-wrapper')].find(node => node.dataset.id === item.id);
            const bar = wrapper?.querySelector('.bar');
            if (!bar) return;
            const rect = document.createElementNS(ns, 'rect');
            const width = Number(item.overdue_days) * 24 / Number(gantt.options.step) * Number(gantt.options.column_width);
            rect.setAttribute('class', 'gantt-delay-tail');
            rect.setAttribute('x', Number(bar.getAttribute('x')) + Number(bar.getAttribute('width')));
            rect.setAttribute('y', bar.getAttribute('y'));
            rect.setAttribute('width', width);
            rect.setAttribute('height', bar.getAttribute('height'));
            rect.setAttribute('rx', '2');
            rect.style.fill = '#ef4444';
            rect.style.pointerEvents = 'none';
            rect.setAttribute('aria-label', 'Przekroczenie terminu: ' + item.overdue_days + ' dni');
            wrapper.prepend(rect);
        });
    },
};
