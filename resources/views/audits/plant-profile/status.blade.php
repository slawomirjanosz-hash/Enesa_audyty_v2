<div aria-label="Status ankiety" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 18px">
@if(request()->routeIs('client.*') && in_array($profile->status,['auditor_corrected','returned']))<strong role="status">Do sprawdzenia i zatwierdzenia przez Ciebie</strong>
@elseif(!request()->routeIs('client.*') && $profile->status==='submitted' && $profile->client_changes)<strong role="status">Klient poprawił odpowiedzi — sprawdź zmiany i zatwierdź</strong>@endif
@if($profile->status==='auditor_corrected')<strong style="padding:5px 10px;border-radius:7px;background:#fff2cc;color:#795700">Poprawiony przez audytora — oczekuje na klienta</strong>@endif
@include('audits.partials.questionnaire-status', ['statusRecord'=>$profile, 'statusStale'=>false])
</div>
