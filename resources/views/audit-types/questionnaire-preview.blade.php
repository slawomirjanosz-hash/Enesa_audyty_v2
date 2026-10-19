<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }} — Podgląd ankiety</title>
<link rel="stylesheet" href="{{ asset('css/iso-plant-profile.css') }}">
<style>
:root{--brand:{{ $appBrand?->primaryColor() ?: '#1a4d3a' }}}
.system-row{border:1px solid #dce4df;border-radius:9px;padding:16px;margin:16px 0;min-width:0}.factor-hidden{display:none!important}.plant-question{overflow-wrap:anywhere}.preview-all [data-system-field][hidden],.preview-all [data-condition][hidden],.preview-all [data-energy-fields][hidden]{display:block!important}.preview-all [data-energy-locked]{display:none!important}.preview-condition{font-size:12px;color:#6b633f;background:#fff8df;padding:8px;border-radius:6px}.plant-nav{max-height:180px;overflow:auto}.plant-header select{max-width:450px;color:#142c24;background:white}.preview-tools{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
</style></head><body class="preview-all">
<header class="plant-header"><div><h1>{{ $title }}</h1><span>Podgląd ankiety · widok klienta</span></div><a href="{{ route('audit-types.show', [$auditType, 'section'=>$section]) }}">← Wróć do ISO 50001</a></header>
<main class="plant-main">
<div class="plant-notice"><strong>Podgląd bez danych klienta.</strong> Możesz sprawdzać pola i listy wyboru. Odpowiedzi nie są zapisywane, nie ma autozapisu ani rzeczywistych zatwierdzeń. Dane z wcześniejszych punktów pozostają puste; pokazujemy także warianty warunkowe, które u klienta zależą od jego odpowiedzi.</div>
<section class="plant-card preview-tools"><label>Punkt ankiety<select id="preview-section">@foreach($titles as $key=>$label)<option value="{{ route('audit-types.questionnaire-preview', [$auditType,$key]) }}" @selected($section===$key)>{{ $label }}</option>@endforeach</select></label><label class="plant-check"><input id="preview-conditions" type="checkbox" checked>Pokaż także pola warunkowe</label><button type="button" id="preview-reset">Wyczyść podgląd</button></section>
<nav class="plant-nav" aria-label="Działy ankiety" id="preview-nav"></nav>
<p><a class="plant-button" href="{{request()->fullUrlWithQuery(['source_example'=>1])}}">Wypełnij przykładem AutoParts — 18.10.2026</a></p>
@if(request()->boolean('source_example'))<div class="plant-notice">Przykład AutoParts z materiałów źródłowych. Dane demonstracyjne; bez zapisu i rzeczywistych zatwierdzeń.</div>@endif
<div class="plant-summary"><strong data-system-progress id="factor-progress">Podgląd pól ankiety</strong></div>
@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>false])
<form id="{{ $formId }}" data-questionnaire-form data-preview-form onsubmit="return false">
@if($kind==='plant-profile')<script type="application/json" id="plant-energy-factors">@json(\App\Services\IsoPlantCalculations::factors())</script><script src="{{asset('js/iso-plant-energy.js')}}" defer></script>@endif
@include('audits.'.$kind.'.fields')
</form>
<section class="plant-card"><h2>Zatwierdzenia i dokument</h2><p>W audycie klienta: zapis odpowiedzi → zatwierdzenie klienta → zatwierdzenie audytora → wygenerowanie dokumentu. W bibliotece te działania są wyłączone.</p><div class="plant-actions"><button disabled>Zapisz</button><button disabled>Zatwierdź jako klient</button><button disabled>Generuj dokument</button></div></section>
@include('partials.questionnaire-navigation')
</main>
<script src="{{ asset('js/iso-questionnaire-preview.js') }}"></script>
<script src="{{ asset('js/'.$script) }}" defer></script>
@if(str_starts_with($section,'7-'))<script src="{{asset('js/iso-support.js')}}" defer></script>@endif
@if(\App\Services\IsoExecutionQuestionnaire::handles($section))<script src="{{asset('js/iso-execution.js')}}" defer></script>@endif
<script type="module" src="{{asset('js/table-sort.js')}}"></script>
</body></html>
