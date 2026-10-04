document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.board-filter').forEach(filter => {
        const all=filter.querySelector('[data-filter-all]');
        const boxes=[...filter.querySelectorAll('input[type=checkbox]:not([data-filter-all])')];
        filter.addEventListener('change', event => {
            if(event.target===all) boxes.forEach(box=>box.checked=all.checked);
            const count=boxes.filter(box=>box.checked).length;
            all.checked=count===boxes.length && count>0;
            all.indeterminate=count>0 && count<boxes.length;
            filter.querySelector('[data-filter-count]').textContent=all.checked?'Wszystkie':`${count} wybr.`;
        });
        filter.addEventListener('keydown',event=>{if(event.key==='Escape') {filter.open=false;filter.querySelector('summary').focus();}});
    });
    const board = document.querySelector('[data-board]');
    if (!board) return;
    const dialog = document.querySelector('#board-editor');
    const form = dialog?.querySelector('form');
    let dragged = null;
    const message = board.querySelector('[data-board-message]');
    const report = (box, text) => { box.textContent = text; box.hidden = false; };
    async function request(url, method, data) {
        const response = await fetch(url, {method, headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || form?.querySelector('[name="_token"]')?.value}, body:JSON.stringify(data)});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) { const error = new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Nie udało się zapisać. Odśwież stronę i spróbuj ponownie.'); error.fields = result.errors || {}; throw error; }
        return result;
    }
    async function status(card, next) {
        const select = card.querySelector('[data-card-status]');
        if (!select || card.dataset.busy) return;
        card.dataset.busy = '1'; select.disabled = true; message.hidden = true;
        try {
            const result = await request(card.dataset.statusUrl, 'PATCH', {status:next,revision:Number(card.dataset.revision)});
            card.dataset.revision = result.revision; card.dataset.status = next; select.value = next;
            card.classList.remove('board-gray','board-orange','board-green','board-red'); card.classList.add(`board-${result.color}`);
            const overdue = card.querySelector('.board-overdue'); if(overdue) overdue.hidden = result.color !== 'red';
            board.querySelector(`[data-column="${next}"] [data-card-list]`).append(card);
            board.querySelectorAll('[data-column]').forEach(column => column.querySelector('[data-count]').textContent = column.querySelectorAll('[data-card]').length);
        } catch(error) { select.value = card.dataset.status; report(message,error.message); }
        finally { delete card.dataset.busy; select.disabled = false; }
    }
    board.addEventListener('submit',async event=>{
        const people=event.target.closest('[data-participants]');
        if(!people) return;
        event.preventDefault();
        const button=people.querySelector('button');button.disabled=true;
        try {await request(people.action,'PUT',{participants:[...people.querySelectorAll('input:checked')].map(el=>Number(el.value)),revision:Number(people.closest('[data-card]').dataset.revision)});location.reload();}
        catch(error){report(message,error.message);button.disabled=false;}
    });
    board.addEventListener('change', event => { if(event.target.matches('[data-card-status]')) status(event.target.closest('[data-card]'),event.target.value); });
    board.addEventListener('dragstart', event => { dragged = event.target.closest('[data-card][draggable="true"]'); if(!dragged) return; event.dataTransfer.setData('text/plain', dragged.dataset.card); event.dataTransfer.effectAllowed = 'move'; });
    board.addEventListener('dragend', () => { dragged = null; board.querySelectorAll('.drag-over').forEach(el=>el.classList.remove('drag-over')); });
    board.querySelectorAll('[data-column]').forEach(column => {
        column.addEventListener('dragover', event => { if(dragged) { event.preventDefault(); column.classList.add('drag-over'); } });
        column.addEventListener('dragleave', () => column.classList.remove('drag-over'));
        column.addEventListener('drop', event => { event.preventDefault(); column.classList.remove('drag-over'); if(dragged) status(dragged,column.dataset.column); dragged = null; });
    });
    board.addEventListener('click', async event => {
        if(event.target.closest('[data-board-new]')) { form.reset(); form.elements.stage_task_id.value = form.dataset.defaultStage || ''; form.elements.due_date.value = form.elements.stage_task_id.selectedOptions[0]?.dataset.due || ''; form.dataset.updateUrl = ''; form.querySelector('[data-board-heading]').textContent = 'Nowe zadanie'; form.querySelector('[data-board-error]').hidden=true; form.querySelectorAll('[aria-invalid]').forEach(el=>el.removeAttribute('aria-invalid')); dialog.showModal(); }
        const edit = event.target.closest('[data-board-edit]');
        if(edit) { const values = JSON.parse(edit.dataset.values); values.revision = Number(edit.closest('[data-card]').dataset.revision); values.status = edit.closest('[data-card]').dataset.status; form.reset(); Object.entries(values).forEach(([key,value])=>{if(form.elements.namedItem(key)) form.elements.namedItem(key).value=value ?? '';}); form.dataset.updateUrl=edit.dataset.updateUrl; form.querySelector('[data-board-heading]').textContent='Edytuj zadanie'; form.querySelector('[data-board-error]').hidden=true; dialog.showModal(); }
        const remove = event.target.closest('[data-board-delete]');
        if(remove && confirm('Usunąć to zadanie z tablicy?')) { remove.disabled=true; try { await request(remove.dataset.deleteUrl,'DELETE',{revision:Number(remove.closest('[data-card]').dataset.revision)}); location.reload(); } catch(error){report(message,error.message);remove.disabled=false;} }
    });
    if(!form) return;
    dialog.querySelectorAll('[data-board-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
    form.elements.stage_task_id.addEventListener('change',()=>{ form.elements.due_date.value=form.elements.stage_task_id.selectedOptions[0]?.dataset.due || ''; });
    form.addEventListener('submit',async event=>{
        event.preventDefault(); const submit=form.querySelector('[type="submit"]'); submit.disabled=true;
        form.querySelectorAll('[aria-invalid]').forEach(el=>el.removeAttribute('aria-invalid'));
        try { await request(form.dataset.updateUrl || form.action,form.dataset.updateUrl?'PUT':'POST',Object.fromEntries(new FormData(form))); location.reload(); }
        catch(error){report(form.querySelector('[data-board-error]'),error.message);Object.keys(error.fields).forEach(key=>form.elements.namedItem(key)?.setAttribute('aria-invalid','true'));}
        finally{submit.disabled=false;}
    });
});
