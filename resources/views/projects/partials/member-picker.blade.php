@php($selectedMembers = array_map('intval', (array) $selectedMembers))
<details class="project-member-picker" data-member-picker>
    <summary>Kto może widzieć projekt <span data-member-count>Wybrano: {{count($selectedMembers)}}</span></summary>
    <div class="project-member-panel">
        <input type="search" data-member-search aria-label="Szukaj użytkownika" placeholder="Szukaj po imieniu lub nazwisku…" autocomplete="off">
        <div class="project-member-options">
            @foreach($users as $user)
            <label class="project-member-option"><input type="checkbox" name="member_ids[]" value="{{$user->id}}" @checked(in_array((int)$user->id, $selectedMembers, true))><span>{{$user->name}}</span></label>
            @endforeach
        </div>
        <small data-member-empty hidden>Brak pasujących użytkowników.</small>
    </div>
</details>
@once
<style>
.field .project-member-picker{border:1px solid #d5d0c5;border-radius:8px;background:#fff;min-width:0}
.project-member-picker summary{cursor:pointer;padding:11px 12px;font-size:13px;font-weight:600;color:var(--green,#1a4d3a)}
.project-member-picker summary span{display:inline-block;margin-left:8px;font-size:12px;font-weight:400;color:#647169}
.project-member-panel{padding:0 10px 10px}.project-member-options{max-height:240px;overflow:auto;margin-top:8px}
.field .project-member-option{display:flex;flex-direction:row;justify-content:flex-start;align-items:center;gap:10px;padding:9px 6px;margin:0;font-size:13px;font-weight:400;cursor:pointer;text-align:left}
.field .project-member-option[hidden]{display:none}.field .project-member-option input[type=checkbox]{width:16px;height:16px;flex:0 0 16px;margin:0;padding:0;accent-color:var(--green,#1a4d3a)}
.project-member-option span{white-space:nowrap}.project-member-option:hover{background:#f0f5f2;border-radius:5px}
.project-member-picker summary:focus-visible{outline:2px solid var(--green,#1a4d3a);outline-offset:2px}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('[data-member-picker]').forEach(picker=>{
        const search=picker.querySelector('[data-member-search]');
        const rows=[...picker.querySelectorAll('.project-member-option')];
        const normalize=value=>value.toLocaleLowerCase('pl').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/ł/g,'l');
        const update=()=>{
            picker.querySelector('[data-member-count]').textContent='Wybrano: '+picker.querySelectorAll('input[type=checkbox]:checked').length;
            const query=normalize(search.value.trim());
            rows.forEach(row=>row.hidden=!normalize(row.textContent).includes(query));
            picker.querySelector('[data-member-empty]').hidden=rows.some(row=>!row.hidden);
        };
        picker.addEventListener('input',update);
        picker.addEventListener('change',update);
        picker.addEventListener('toggle',update);
        picker.addEventListener('keydown',event=>{if(event.key==='Escape'){picker.open=false;picker.querySelector('summary').focus();}if(event.key==='Enter'&&event.target===search)event.preventDefault();});
        picker.closest('form')?.addEventListener('reset',()=>setTimeout(update,0));
        update();
    });
});
</script>
@endonce
