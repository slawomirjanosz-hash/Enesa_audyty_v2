<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{$title}} — podgląd źródłowego HTML</title><style>body{margin:0;background:#f5f2ec;font:14px/1.5 "Segoe UI",sans-serif}.preview-header{padding:12px 24px;background:#14352a;color:white;display:flex;gap:20px;align-items:center;flex-wrap:wrap}.preview-header a{color:white}.preview-header select{padding:8px;max-width:100%}iframe{width:100%;height:calc(100vh - 110px);border:0;min-height:650px}.preview-note{padding:8px 24px}</style></head><body>
<header class="preview-header"><a href="{{route('audit-types.show',[$auditType,'section'=>$section])}}">← Wróć do ISO 50001</a><label>Punkt ankiety <select onchange="location.href=this.value">@foreach($titles as $key=>$label)<option value="{{route('audit-types.questionnaire-preview',[$auditType,$key])}}" @selected($key===$section)>{{$label}}</option>@endforeach</select></label><a href="{{request()->fullUrlWithQuery(['source_example'=>1])}}">Przykład AutoParts</a></header>
<div class="preview-note">Oryginalny układ HTML i specyfikacja dla Sławka. Podgląd bez zakładania klienta, zapisu i rzeczywistych zatwierdzeń.</div>
@php
 $previewData=array_replace(get_defined_vars(),['client'=>false]);
 if(request()->boolean('source_example')) {
     $previewData['exampleSwot']=app(\App\Services\IsoPlatformRelease::class)->swot();
     $previewData['exampleConsultant']=app(\App\Services\IsoPlatformRelease::class)->consultant($section);
 }
 $configuration=app(\App\Services\IsoHtmlQuestionnaire::class)->configuration($section,$previewData);
 $configuration=array_replace($configuration,['preview'=>true,'editable'=>true,'submit'=>false,'consultant'=>false,'client'=>true]);
 $frame=app(\App\Services\IsoHtmlQuestionnaire::class)->frame($section,$configuration);
@endphp
<iframe title="{{$title}} — źródłowy HTML" data-clipboard-channel="{{$configuration['channel']}}" sandbox="allow-scripts allow-modals allow-downloads" srcdoc="{{$frame}}"></iframe>
<script src="{{asset('js/iso-html-clipboard.js')}}" defer></script>
</body></html>
