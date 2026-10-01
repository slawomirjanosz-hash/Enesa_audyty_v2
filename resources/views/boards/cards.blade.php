@once
@push('styles')<link rel="stylesheet" href="{{asset('css/board.css')}}">@endpush
@push('scripts')<script src="{{asset('js/board.js')}}" defer></script>@endpush
@endonce
@php
    $boardAccess = app(\App\Services\BoardAccessService::class);
    $boardPermissions = [];
@endphp
<div class="task-board" data-board>
    <header class="board-toolbar"><div><h2>Zadania</h2><p class="board-muted">Szary: do zrobienia · Pomarańczowy: w trakcie · Zielony: gotowe · Czerwony: po terminie</p></div>@if($boardManage ?? false)<button type="button" class="board-primary" data-board-new>+ Dodaj zadanie</button>@endif</header>
    <p data-board-message role="alert" hidden></p>
    <div class="board-columns">
    @foreach(['todo'=>'Do zrobienia','in_progress'=>'W trakcie','done'=>'Zrobione'] as $status=>$label)
    <section class="board-column" data-column="{{$status}}" aria-label="{{$label}}"><h3>{{$label}} <span data-count>{{$cards->where('status',$status)->count()}}</span></h3><div data-card-list>
    @foreach($cards->where('status',$status) as $card)
    @php
        $owner = $card->project ?? $card->audit;
        $permissionKey = ($card->project_id ? 'p' : 'a').$owner->id;
        $boardPermissions[$permissionKey] ??= [
            'view' => $boardAccess->view(auth()->user(), $owner),
            'manage' => $boardAccess->manage(auth()->user(), $owner),
        ];
        $permissions = $boardPermissions[$permissionKey];
        $canStatus = !auth()->user()->hasAnyRole(['client_admin','client_user']) && $permissions['view'] && ($card->assigned_to === auth()->id() || $permissions['manage']);
        $canEditCard = ($boardManage ?? false) && $permissions['manage'];
    @endphp
    <article class="board-card board-{{$card->color()}}" data-card="{{$card->id}}" data-revision="{{$card->revision}}" data-status="{{$card->status}}" draggable="{{$canStatus?'true':'false'}}" data-status-url="{{route('board.status',$card)}}">
        @if($personalBoard ?? false)<a class="board-owner" href="{{route($card->project_id?'projects.show':'audits.show',[$owner,'tab'=>'tasks'])}}">{{$owner->number}} · {{$card->project_id?$owner->name:$owner->title}}</a>@endif
        <h4>{{$card->title}}</h4>
        @if($card->description)<p class="board-description">{{$card->description}}</p>@endif
        <div class="board-muted">Etap: {{$card->stage?->title ?? 'Bez etapu'}}</div>
        <div class="board-card-meta"><span>{{$card->assignee?->name ?? 'Nieprzypisane'}}</span><time datetime="{{$card->due_date?->format('Y-m-d')}}">{{$card->due_date?->format('d.m.Y') ?? 'Bez terminu'}}</time></div>
        <small class="board-overdue" @if($card->color()!=='red') hidden @endif>Po terminie</small>
        <div class="board-card-actions">
        @if($canStatus)<label class="board-status-label">Status<select data-card-status aria-label="Status: {{$card->title}}">@foreach(['todo'=>'Do zrobienia','in_progress'=>'W trakcie','done'=>'Zrobione'] as $value=>$text)<option value="{{$value}}" @selected($card->status===$value)>{{$text}}</option>@endforeach</select></label>@else<span class="board-muted">{{$label}}</span>@endif
        @if($canEditCard)<button type="button" data-board-edit data-values="{{json_encode($card->only(['id','revision','title','description','stage_task_id','assigned_to','status'])+['due_date'=>$card->due_date?->format('Y-m-d')])}}" data-update-url="{{route('board.update',$card)}}">Edytuj</button><button type="button" data-board-delete data-delete-url="{{route('board.destroy',$card)}}">Usuń</button>@endif
        </div>
    </article>
    @endforeach
    </div></section>
    @endforeach
    </div>
</div>
