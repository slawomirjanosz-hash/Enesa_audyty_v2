@php
    $review=$review??$profile;
    $htmlConfiguration=app(\App\Services\IsoHtmlQuestionnaire::class)->configuration($htmlSection,get_defined_vars());
    $htmlFrame=app(\App\Services\IsoHtmlQuestionnaire::class)->frame($htmlSection,$htmlConfiguration);
    $htmlParameters=in_array($htmlSection,['intro','4-1','4-2'])?[$audit,$profile]:[$audit,$profile,$htmlSection];
@endphp
<section data-iso-html-editor data-channel="{{$htmlConfiguration['channel']}}" data-section="{{$htmlSection}}" data-approvals="approvals">
<div data-iso-html-tools></div>
@if(session('success'))<div class="plant-notice success" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź odpowiedzi:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if($stale??false)<div class="plant-notice">Dane źródłowe zmieniły się. Sprawdź odpowiedzi i zatwierdź ankietę ponownie.</div>@endif
@if(isset($ready)&&!$ready)<div class="plant-notice">Najpierw zatwierdź aktualne dane źródłowe tego punktu.</div>@endif
@if(isset($sources['ready'])&&!$sources['ready'])<div class="plant-notice">Możesz zapisywać odpowiedzi. Przed zatwierdzeniem sprawdź i zatwierdź aktualne punkty źródłowe wskazane w danych powiązanych.</div>@endif
@if($review->review_note)<div class="plant-notice">Uwagi audytora: {{$review->review_note}}</div>@endif
@if(request()->boolean('source_example'))<div class="plant-notice">Wczytano przykład AutoParts. Odpowiedzi i analiza wymagają zapisu i zwykłego obiegu zatwierdzeń.</div>@endif
@if($htmlSection==='intro')<div class="plant-notice">Przekazanie sekcji zapisuje jej odpowiedzi i stan. Cały profil przekaż audytorowi przyciskiem „Zatwierdź jako klient” po uzupełnieniu wszystkich sekcji.</div>@endif
<iframe title="Ankieta {{str_replace('-','.',$htmlSection)}} — układ źródłowy ENESA" data-clipboard-channel="{{$htmlConfiguration['channel']}}" sandbox="allow-scripts allow-modals allow-downloads" srcdoc="{{$htmlFrame}}" style="width:100%;height:calc(100vh - 150px);min-height:600px;border:0"></iframe>
<form data-iso-html-transport method="post" action="{{route($prefix.'update',$htmlParameters)}}">@csrf
<input type="hidden" name="lock_version" value="{{$review->lock_version}}">
<input type="hidden" name="source_hash" value="{{$htmlSection==='intro'?'':($sources['hash']??$hash??$questionnaire->hash($profile))}}">
@if($htmlSection==='intro')
<details class="plant-card"><summary>Dane identyfikacyjne zakładu i kontakt</summary>
<label>Stan danych na dzień<input type="date" name="as_of_date" value="{{old('as_of_date',$profile->as_of_date?->format('Y-m-d'))}}" @disabled(!$htmlConfiguration['editable'])></label>
@foreach($questionnaire->questions($profile->definition) as $identityQuestion)@if(!str_starts_with($identityQuestion['key'],'FAKT_') && $identityQuestion['type']==='text')
<label>{{$identityQuestion['label']}}<input name="html_meta[{{$identityQuestion['key']}}]" value="{{$profile->answers[$identityQuestion['key']]['value']??''}}" @disabled(!$htmlConfiguration['editable'])></label>
@endif @endforeach</details>
@endif
<input type="hidden" name="operation"><input type="hidden" name="html_state">
</form>
<div data-iso-html-approvals hidden></div>
</section>
<script src="{{asset('js/iso-html-editor.js')}}" defer></script>
<script src="{{asset('js/iso-html-clipboard.js')}}" defer></script>
