@can('delete', $project)
<form class="project-delete-form" method="POST" action="{{ route('projects.destroy', $project) }}" data-confirm="Usunąć projekt {{ $project->number }} — {{ $project->name }}? Projekt zniknie z listy. Powiązane dane i pliki zostaną zachowane; nie jest to trwałe kasowanie." onsubmit="return confirm(this.dataset.confirm)">
    @csrf @method('DELETE')
    <button class="project-delete-button" type="submit" aria-label="Usuń projekt {{ $project->number }} — {{ $project->name }}"><i class="ti ti-trash" aria-hidden="true"></i> Usuń projekt</button>
</form>
@once
<style>.project-delete-form{display:inline-flex;margin:0}.project-delete-button{display:inline-flex;align-items:center;gap:6px;border:1px solid #fecaca;border-radius:7px;background:#fff1f2;color:#b91c1c;padding:9px 12px;font:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}.project-delete-button:hover{background:#fee2e2;border-color:#ef4444}.project-delete-button:focus-visible{outline:3px solid #b91c1c;outline-offset:3px}</style>
@endonce
@endcan
