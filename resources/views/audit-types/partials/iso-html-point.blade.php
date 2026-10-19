@php
 $htmlClient=$clientView??false;
 $htmlCards=isset($audit)?(app(\App\Services\QuestionnaireCompletion::class)->audit($audit)[match($item['id']){'4-1'=>'factors','4-2'=>'stakeholders',default=>$item['id']}]??[]):[];
 $htmlUrls=[];
 foreach($htmlCards as $htmlCard) {
     $htmlRoute=match($item['id']){'4-1'=>'factors','4-2'=>'stakeholders',default=>'system'};
     $htmlArgs=[$audit,$htmlCard['profile_id']];
     if($htmlRoute==='system') $htmlArgs[]=$item['id'];
     $htmlUrls[(string)$htmlCard['profile_id']]=route(($htmlClient?'client.':'').'audits.'.$htmlRoute.'.show',$htmlArgs);
 }
 $htmlChannel=(string)\Illuminate\Support\Str::uuid();
 $htmlPreview=isset($auditType)?route('audit-types.questionnaire-preview',[$auditType,$item['id']]):null;
 $htmlProfileIndex=isset($audit)?route(($htmlClient?'client.':'').'audits.plant-profile.index',$audit):$htmlPreview;
 $htmlBack=isset($audit)?route($htmlClient?'client.audits.show':'audits.show',[$audit,'tab'=>'iso50001']):route('audit-types.show',$auditType);
 $htmlIdentity=isset($audit)?$audit->company->name:'Biblioteka ISO 50001 — bez danych klienta';
 $htmlCardsMarkup=view('audit-types.partials.iso-html-point-cards',['htmlCards'=>$htmlCards,'htmlUrls'=>$htmlUrls,'htmlTitle'=>'ENESA_SOURCE_QUESTIONNAIRE_TITLE','htmlPreview'=>$htmlPreview,'htmlAudit'=>isset($audit)])->render();
 $htmlPoint=app(\App\Services\IsoHtmlQuestionnaire::class)->point($item['id'],$htmlCardsMarkup,$htmlChannel,$htmlIdentity,$htmlClient);
@endphp
<section style="grid-column:1/-1;width:100%" data-iso-html-point data-channel="{{$htmlChannel}}" data-back="{{$htmlBack}}" data-empty="{{$htmlProfileIndex}}">
<script type="application/json" data-profile-urls>@json($htmlUrls, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
<script type="application/json" data-point-document>@json($htmlPoint)</script>
<iframe title="{{str_replace('-','.',$item['id'])}} — źródłowa strona punktu ENESA" data-clipboard-channel="{{$htmlChannel}}" sandbox="allow-scripts allow-modals" style="width:100%;height:850px;border:0"></iframe>
</section>
@once<script src="{{asset('js/iso-html-clipboard.js')}}" defer></script><script>
const initIsoHtmlPoints=()=>document.querySelectorAll('[data-iso-html-point]').forEach(host=>{
 const frame=host.querySelector('iframe');const urls=JSON.parse(host.querySelector('[data-profile-urls]').textContent);
 const source=host.querySelector('[data-point-document]');
 const observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)){frame.srcdoc=JSON.parse(source.textContent);source.remove();observer.disconnect();}});
 observer.observe(frame);
 window.addEventListener('message',event=>{
  if(event.source!==frame.contentWindow||event.data?.enesa!==host.dataset.channel)return;
  if(event.data.action==='back'){location.href=host.dataset.back;return;}
  if(event.data.action!=='open')return;
  const url=event.data.profile?urls[event.data.profile]:(Object.keys(urls).length===1?Object.values(urls)[0]:null);
  if(url){location.href=url;return;}
  if(!Object.keys(urls).length){location.href=host.dataset.empty;return;}
  frame.contentWindow.postMessage({enesa:host.dataset.channel,action:'choose'},'*');
 });
});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initIsoHtmlPoints);else initIsoHtmlPoints();
</script>@endonce
