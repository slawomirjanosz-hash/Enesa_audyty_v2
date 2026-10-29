<section class="iso-report-summary">@php($conclusionRows=old($reportRoot.'.WNIOSKI',$reportData['WNIOSKI']??[]))
<h3>Wnioski — cztery ogniwa</h3><p class="plant-help">Jeden wniosek może łączyć kilka ustaleń. Po zmianie odpowiedzi sprawdź aktualność wniosków.</p>
<div data-report-rows="wnioski">@foreach($conclusionRows as $reportIndex=>$reportRow)@include('audits.partials.iso-conclusion-row')@endforeach</div>
<template data-report-template="wnioski">@include('audits.partials.iso-conclusion-row',['reportIndex'=>'__INDEX__','reportRow'=>[]])</template>
<button type="button" data-report-add="wnioski">+ Dodaj wniosek</button>
@once('iso-report-summary-script')<script src="{{asset('js/iso-report-summary.js')}}" defer></script>@endonce
</section>
