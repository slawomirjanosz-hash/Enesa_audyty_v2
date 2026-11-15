@foreach($w->records('question') as $question)
<section class="plant-card">
    <h3>{{ $question['symbol'] }} · {{ $question['payload']['pytanie'] }}</h3>
    @if(($question['payload']['state']??'')==='open')
    @php($previousAnswer=$service->one($w,'answer','question_id',$question['id']))
    @if($previousAnswer && !$service->hasCurrentAnswer($w,$question))<p class="plant-notice">Treść pytania zmieniła się. Sprawdź poprzednią odpowiedź i zapisz ją ponownie.</p>@endif
    <form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">
        @include('audits.enms.token')
        <input type="hidden" name="operation" value="answer">
        <input type="hidden" name="payload[question_id]" value="{{ $question['id'] }}">
        <label>Odpowiedź<textarea name="payload[text]" @disabled(!$editable)>{{ $previousAnswer['payload']['text']??'' }}</textarea></label>
        @if($editable)<button>Zapisz odpowiedź</button>@endif
    </form>
    @else
    <p>{{ $question['payload']['state']==='closed'?'Pytanie zamknięte po odpowiedzi':'Pytanie wycofane' }}. {{ $question['payload']['closure']['text']??'' }}</p>
    <p>{{ $service->one($w,'answer','question_id',$question['id'])['payload']['text']??'' }}</p>
    @endif
</section>
@endforeach
