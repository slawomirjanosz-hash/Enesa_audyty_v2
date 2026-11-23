@if(! $archivedBoard && \App\Models\CompanySettings::moduleIsEnabled('crm') && (app(\App\Services\AuditorAccessService::class)->hasFullAccess(auth()->user()) || auth()->user()->canAny(['crm.tasks.own.manage', 'crm.tasks.team.manage'])))
<dialog id="board-create-task" class="board-dialog">
    <form method="POST" action="{{ route('crm.tasks.store') }}">
        @csrf
        <input type="hidden" name="return_to_board" value="1">
        <header><h2>Nowe zadanie</h2><button type="button" onclick="this.closest('dialog').close()" aria-label="Zamknij">×</button></header>
        @foreach($errors->taskCreate->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
        <label>Tytuł *<input name="title" value="{{ old('title') }}" required maxlength="255"></label>
        <label>Opis<textarea name="description" rows="3">{{ old('description') }}</textarea></label>
        @if(app(\App\Services\AuditorAccessService::class)->hasFullAccess(auth()->user()) || auth()->user()->can('crm.tasks.team.manage'))
            <label>Przypisany<select name="assigned_to">
            @foreach(\App\Models\User::where('is_active', true)->whereHas('roles')->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['client_admin','client_user']))->orderBy('name')->get() as $person)
                <option value="{{ $person->id }}" @selected((string)old('assigned_to', auth()->id()) === (string)$person->id)>{{ $person->name }}</option>
            @endforeach
            </select></label>
        @else<input type="hidden" name="assigned_to" value="{{ auth()->id() }}">@endif
        <div class="board-form-grid">
            <label>Termin<input name="due_date" type="date" value="{{ old('due_date') }}"></label>
            <label>Godzina<input name="scheduled_time" type="time" value="{{ old('scheduled_time') }}"></label>
            <label>Priorytet<select name="priority">@foreach(['low'=>'Niski','medium'=>'Średni','high'=>'Wysoki'] as $value=>$label)<option value="{{ $value }}" @selected(old('priority','medium') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Status<select name="status">@foreach(['todo'=>'Do zrobienia','in_progress'=>'W trakcie','done'=>'Zrobione'] as $value=>$label)<option value="{{ $value }}" @selected(old('status','todo') === $value)>{{ $label }}</option>@endforeach</select></label>
        </div>
        <footer><button type="button" onclick="this.closest('dialog').close()">Anuluj</button><button type="submit" class="board-primary">Dodaj zadanie</button></footer>
    </form>
</dialog>
@if($errors->taskCreate->any())<script>document.getElementById('board-create-task').showModal();</script>@endif
@endif
