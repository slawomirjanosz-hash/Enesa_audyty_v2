window.CrmOpportunityHistory = {
    activities(prefix, activities = []) {
        const container = document.getElementById(prefix + '-activity-history');
        container.replaceChildren();
        if (!activities.length) {
            const empty = document.createElement('p');
            empty.textContent = 'Brak wpisów w historii działań.';
            empty.style.cssText = 'font-size:12px;color:#888;margin:0;';
            container.append(empty);
        }
        activities.forEach(activity => {
            const row = document.createElement('div');
            row.style.cssText = 'border-left:2px solid #d8e5dd;padding:4px 10px;';
            const description = document.createElement('div');
            description.textContent = activity.description;
            description.style.cssText = 'font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere;';
            const meta = document.createElement('small');
            meta.textContent = [activity.date, activity.person, 'Zapisał(a): ' + activity.recorder].filter(Boolean).join(' · ');
            meta.style.color = '#7a8a80';
            row.append(description, meta);
            container.append(row);
        });
    },
    tasks(prefix, tasks = []) {
        const container = document.getElementById(prefix + '-linked-tasks');
        const count = document.getElementById(prefix + '-linked-tasks-count');
        container.replaceChildren();
        count.textContent = '(' + tasks.length + ')';
        if (!tasks.length) {
            const empty = document.createElement('div');
            empty.textContent = 'Brak zadań powiązanych z tą szansą.';
            empty.style.cssText = 'font-size:12px;color:#999;padding:9px 0;';
            container.appendChild(empty);
            return;
        }
        const archive = document.createElement('details');
        const summary = document.createElement('summary');
        summary.textContent = 'Zarchiwizowane zadania (' + tasks.filter(task => task.archived).length + ')';
        archive.appendChild(summary);
        tasks.forEach(task => {
            const row = document.createElement('div');
            row.style.cssText = 'border:1px solid #E5E1D8;border-radius:7px;padding:9px 10px;background:#FAFAF6;';
            const title = document.createElement('div');
            title.textContent = task.title;
            title.style.cssText = 'font-size:12px;font-weight:700;color:#333;';
            const meta = document.createElement('div');
            meta.textContent = [task.status, task.assigned, task.due_date].filter(Boolean).join(' · ');
            meta.style.cssText = 'font-size:11px;color:#777;margin-top:3px;';
            row.append(title, meta);
            (task.archived ? archive : container).appendChild(row);
        });
        if (tasks.some(task => task.archived)) container.appendChild(archive);
    },
};
