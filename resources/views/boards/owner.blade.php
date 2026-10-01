@php
    $boardAccess = app(\App\Services\BoardAccessService::class);
    $boardManage = $boardAccess->manage(auth()->user(), $boardOwner);
    $boardType = $boardOwner instanceof \App\Models\Project ? 'project' : 'audit';
    $cards = \App\Models\BoardTask::where($boardType.'_id', $boardOwner->id)->with(['assignee','stage','project','audit'])->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->paginate(90, ['*'], 'board_page')->appends(['tab'=>'tasks']);
@endphp
@include('boards.cards')
@if($cards->hasPages())<p>Na tej stronie {{$cards->count()}} z {{$cards->total()}} zadań. Liczniki kolumn dotyczą tej strony.</p>{{$cards->links()}}@endif
@if($boardManage)
<dialog id="board-editor" class="board-dialog">
    <form data-board-form action="{{route('board.store', [$boardType, $boardOwner->id])}}">
        @csrf
        <header><h2 data-board-heading>Nowe zadanie</h2><button type="button" data-board-close aria-label="Zamknij">×</button></header>
        <p data-board-error role="alert" hidden></p>
        <input type="hidden" name="revision" value="1">
        <label>Nazwa zadania *<input name="title" required maxlength="255"></label>
        <label>Opis<textarea name="description" rows="3" maxlength="10000"></textarea></label>
        <label>Etap harmonogramu<select name="stage_task_id"><option value="">Bez etapu</option>@foreach($boardOwner->tasks as $stage)<option value="{{$stage->id}}" data-due="{{$stage->due_date?->format('Y-m-d')}}">{{$stage->title}} — {{$stage->due_date?->format('d.m.Y') ?? 'bez terminu'}}</option>@endforeach</select></label>
        <div class="board-form-grid">
        <label>Osoba odpowiedzialna<select name="assigned_to"><option value="">Nieprzypisane</option>@foreach(collect([$boardOwner->manager])->filter()->merge($boardOwner->members)->unique('id')->filter(fn($u) => $u->is_active && !$u->hasAnyRole(['client_admin','client_user'])) as $person)<option value="{{$person->id}}">{{$person->name}}</option>@endforeach</select></label>
        <label>Termin<input type="date" name="due_date"></label>
        <label>Status<select name="status"><option value="todo">Do zrobienia</option><option value="in_progress">W trakcie</option><option value="done">Zrobione</option></select></label>
        </div>
        <p class="board-muted">Termin jest pobierany z etapu. Możesz go zmienić — przesunięcie etapu zachowa ustaloną różnicę dni.</p>
        <footer><button type="button" data-board-close>Anuluj</button><button type="submit" class="board-primary">Zapisz zadanie</button></footer>
    </form>
</dialog>
@endif
