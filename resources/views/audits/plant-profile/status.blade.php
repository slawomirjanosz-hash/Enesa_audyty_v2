<div aria-label="Status ankiety" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 18px">
@if(request()->routeIs('client.*') && in_array($profile->status,['auditor_corrected','returned']))<strong role="status">Do sprawdzenia i zatwierdzenia przez Ciebie</strong>
@elseif(!request()->routeIs('client.*') && $profile->status==='submitted' && $profile->client_changes)<strong role="status">Klient poprawił odpowiedzi — sprawdź zmiany i zatwierdź</strong>@endif
@if($profile->status==='auditor_corrected')<strong style="padding:5px 10px;border-radius:7px;background:#fff2cc;color:#795700">Poprawiony przez audytora — oczekuje na klienta</strong>@endif
@foreach(['W trakcie opracowania'=>!$profile->client_approval, 'Zaakceptowane przez klienta'=>(bool)$profile->client_approval, 'Zaakceptowane przez audytora'=>(bool)$profile->auditor_approval, 'Wygenerowano dokument'=>(bool)$profile->document_id] as $label=>$reached)
<span style="padding:5px 10px;border-radius:7px;font-size:13px;background:{{$reached?'#e3f2e8':'#f0f1ef'}};color:{{$reached?'#1a4d3a':'#647169'}}">{{$reached?'✓':'○'}} {{$label}}</span>
@endforeach
</div>
