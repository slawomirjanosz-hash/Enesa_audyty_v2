<div class="plant-excel-actions" aria-label="Ankieta Excel">
@if($excelReady ?? true)
<a class="plant-profile-icon" href="{{route($prefix.'excel',$excelParameters ?? [$audit,$profile])}}" title="Wypełnij Excela offline" aria-label="Wypełnij Excela offline"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6 3h9l4 4v14H6z M14 3v5h5 M9 12l7 6 M16 12l-7 6"/></svg></a>
@if($editable)<form method="post" enctype="multipart/form-data" action="{{route($prefix.'excel-import',$excelParameters ?? [$audit,$profile])}}" data-questionnaire-excel-import>@csrf
<input type="file" name="{{$excelFileField ?? 'excel'}}" accept=".xlsx" hidden required aria-label="Wypełniona ankieta Excel">
<button type="button" class="plant-profile-icon" title="Załaduj Excela" aria-label="Załaduj Excela" data-questionnaire-excel-open><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5 M4 15v6h16v-6"/></svg></button></form>@endif
@else<span class="plant-help" title="Eksport będzie dostępny po zatwierdzeniu profilu zakładu przez klienta i audytora.">Excel niedostępny ⓘ</span>@endif
</div>
@once
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.iso-questionnaire-summary{gap:12px;margin:12px 0;align-items:center}.iso-toolbar-progress{flex:1;min-width:220px;font:inherit}.iso-toolbar-progress strong{font-size:14px}.iso-toolbar-progress progress{display:block;width:100%;height:8px;margin-top:7px;accent-color:var(--brand);border:0;border-radius:8px;overflow:hidden;background:#e6ede8}.iso-toolbar-progress progress::-webkit-progress-bar{background:#e6ede8}.iso-toolbar-progress progress::-webkit-progress-value{background:var(--brand)}.iso-questionnaire-summary .questionnaire-status{margin:0!important;gap:5px!important}.iso-questionnaire-summary .questionnaire-status span{font-size:12px!important;padding:6px 8px!important}.iso-questionnaire-summary .plant-excel-actions{margin-left:auto}.iso-questionnaire-summary .plant-excel-actions svg{display:block}@media(max-width:650px){.iso-questionnaire-summary{display:grid;grid-template-columns:minmax(0,1fr) auto}.iso-toolbar-progress{grid-column:1/-1;min-width:0}.iso-questionnaire-summary .questionnaire-status{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))}.iso-questionnaire-summary .questionnaire-status span{text-align:center;white-space:normal}.iso-questionnaire-summary .plant-excel-actions{align-self:start}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.iso-toolbar-progress').forEach(box=>{
 const label=box.querySelector('strong'),bar=box.querySelector('progress');
 const update=()=>{const match=label.textContent.match(/([0-9]+(?:[.,][0-9]+)?)\s*%/);if(match)bar.value=Number(match[1].replace(',','.'))};
 new MutationObserver(update).observe(label,{childList:true,subtree:true,characterData:true});update();
 });
 document.querySelectorAll('[data-questionnaire-excel-import]').forEach(form=>{
 const file=form.querySelector('input[type=file]');
 form.querySelector('[data-questionnaire-excel-open]').addEventListener('click',()=>file.click());
 file.addEventListener('change',()=>{if(file.files.length)form.requestSubmit()});
 form.addEventListener('submit',()=>{window.formDraftLeaving=true});
 });
});
</script>
@endonce
