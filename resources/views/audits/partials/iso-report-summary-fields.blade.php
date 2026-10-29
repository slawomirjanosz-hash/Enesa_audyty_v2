@php($summary=old($reportRoot.'.PODSUM',$reportData['PODSUM']??[]))
<section class="iso-report-summary"><h3>Część A — podsumowanie dla Zarządu</h3>
<label>Ocena sytuacji<textarea name="{{$reportRoot}}[PODSUM][ocena]" maxlength="10000">{{$summary['ocena']??''}}</textarea></label>
<label class="plant-check"><input type="hidden" name="{{$reportRoot}}[PODSUM][bezPodsumowania]" value="0"><input type="checkbox" name="{{$reportRoot}}[PODSUM][bezPodsumowania]" value="1" @checked($summary['bezPodsumowania']??false)>Raport szczegółowy bez podsumowania</label>
<label>Uzasadnienie pominięcia części A<textarea name="{{$reportRoot}}[PODSUM][uzasadnienie]" maxlength="3000">{{$summary['uzasadnienie']??''}}</textarea></label>
<h4>Najważniejsze ustalenia</h4><p class="plant-help">Wybierz 3–5 ustaleń z ankiety i opisz ich znaczenie dla decyzji Zarządu.</p>
@foreach(['ustalenia','decyzje'] as $reportCollection)
@if($reportCollection==='decyzje')<h4>Decyzje rekomendowane i zatwierdzone</h4>@endif
<div data-report-rows="{{$reportCollection}}">@foreach($summary[$reportCollection]??[] as $reportIndex=>$reportRow)@include('audits.partials.iso-report-summary-row')@endforeach</div>
<template data-report-template="{{$reportCollection}}">@include('audits.partials.iso-report-summary-row',['reportIndex'=>'__INDEX__','reportRow'=>[]])</template>
<button type="button" data-report-add="{{$reportCollection}}">+ Dodaj {{$reportCollection==='ustalenia'?'ustalenie':'decyzję'}}</button>
@endforeach
<label>Braki wpływające na decyzję<textarea name="{{$reportRoot}}[PODSUM][braki]" maxlength="10000">{{$summary['braki']??''}}</textarea></label>
</section>
@once('iso-report-summary-script')<script src="{{asset('js/iso-report-summary.js')}}" defer></script>@endonce
