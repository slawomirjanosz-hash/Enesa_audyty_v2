/* Shared by the editable project schedule and its read-only public link. */
window.ProjectGanttColors = {
    palette: {
        'progress-0-10': ['#8b5cf6', '#6d28d9'], 'progress-11-25': ['#facc15', '#eab308'],
        'progress-26-50': ['#fb923c', '#ea580c'], 'progress-51-75': ['#1d4ed8', '#1e3a8a'],
        'progress-76-99': ['#60a5fa', '#3b82f6'], 'progress-100': ['#22c55e', '#15803d'],
    },
    progressClass(progress) {
        const value = Number(progress || 0);
        if (value >= 100) return 'progress-100';
        if (value >= 76) return 'progress-76-99';
        if (value >= 51) return 'progress-51-75';
        if (value >= 26) return 'progress-26-50';
        if (value >= 11) return 'progress-11-25';
        return 'progress-0-10';
    },
    apply(root) {
        if (!root) return;
        root.querySelectorAll('.bar-wrapper.task-row').forEach(wrapper => {
            const name = Object.keys(this.palette).find(key => wrapper.classList.contains(key));
            if (!name) return;
            wrapper.querySelector('.bar')?.style.setProperty('fill', this.palette[name][0], 'important');
            wrapper.querySelector('.bar-progress')?.style.setProperty('fill', this.palette[name][1], 'important');
        });
        root.querySelectorAll('.bar-wrapper.milestone-row').forEach(wrapper => {
            const done = wrapper.classList.contains('done-milestone');
            wrapper.querySelector('.bar')?.style.setProperty('fill', done ? '#16a34a' : '#f59e0b', 'important');
            wrapper.querySelector('.bar')?.style.setProperty('stroke', done ? '#15803d' : '#b45309', 'important');
        });
    },
};
