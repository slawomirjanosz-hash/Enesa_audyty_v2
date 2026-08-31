<style>
.iso-nav-group>summary{list-style:none;grid-template-columns:62px minmax(0,1fr) 15px}.iso-nav-group>summary::-webkit-details-marker{display:none}.iso-nav-group>summary i{transition:transform .15s}.iso-nav-group[open]>summary i{transform:rotate(180deg)}
.audit-iso-layout.client-audit-iso-layout{grid-template-columns:minmax(0,1fr)}
.audit-iso-layout{display:grid;grid-template-columns:285px minmax(0,1fr);gap:16px;align-items:start}.audit-iso-content{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:22px;min-height:440px}.audit-iso-panel{display:none}.audit-iso-panel.active{display:block}.audit-iso-panel h2{font-size:20px;margin:5px 0 8px}.audit-iso-panel>p{color:#647169;font-size:13px;line-height:1.65}.iso-nav{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:10px;position:sticky;top:78px}.iso-nav-head{display:flex;align-items:center;gap:9px;padding:9px 8px 13px;border-bottom:1px solid #eee;margin-bottom:7px}.iso-nav-head>i{font-size:22px;color:var(--green)}.iso-nav-head div{display:flex;flex-direction:column}.iso-nav-head small{font-size:10px;color:#7a857e}.iso-nav-item{width:100%;border:0;background:transparent;border-radius:8px;padding:9px;display:grid;grid-template-columns:62px 1fr;gap:7px;text-align:left;font:700 11px Manrope;color:#56635b;cursor:pointer}.iso-nav-item.active{background:var(--green);color:#fff}.iso-nav-sub{padding-left:17px;font-size:10px}.iso-audit-note{margin-top:20px;padding:18px;border:1px dashed #ccd6cf;background:#fafbf8;border-radius:9px;color:#718078;text-align:center}.iso-clause{display:grid;grid-template-columns:170px 230px 1fr;margin-top:20px;border:1px solid #e4e1d9;border-radius:9px;overflow:hidden}.iso-clause>div{padding:14px;border-right:1px solid #e4e1d9}.iso-clause>div:last-child{border:0}.iso-clause p{font-size:12px;line-height:1.55;margin:0;color:#5d6a62}@media(max-width:850px){.audit-iso-layout{grid-template-columns:1fr}.iso-nav{position:static}.iso-clause{grid-template-columns:1fr}.iso-clause>div{border-right:0;border-bottom:1px solid #e4e1d9}}
</style>
<div class="audit-iso-layout {{ $clientView ? 'client-audit-iso-layout' : '' }}">@unless($clientView)@include('audit-types.partials.iso50001-menu',['side'=>'left'])@endunless<div class="audit-iso-content">
@foreach($chapters as $chapter)
<section class="audit-iso-panel {{$loop->first?'active':''}}" data-iso-panel="{{$chapter['id']}}"><small style="font-weight:900;color:var(--green)">{{$chapter['number']}}</small><h2>{{$chapter['title']}}</h2><p>{{$chapter['description']}}</p>@if($chapter['source_url'] ?? false)<a href="{{$chapter['source_url']}}" target="_blank" rel="noopener" style="display:inline-flex;margin-top:12px;color:var(--green);font-size:11px;font-weight:800;text-decoration:none"><i class="ti ti-external-link"></i>&nbsp; {{$chapter['source_label']}}</a>@endif @if($chapter['id']==='training')@include('audit-types.partials.iso50001-training',['canManageTraining'=>false])@elseif($chapter['id']!=='intro')<div class="iso-audit-note">Ten obszar audytu będzie uzupełniany kolejnymi uzgodnionymi polami.</div>@endif</section>
@foreach($chapter['items'] ?? [] as $item)<section class="audit-iso-panel" data-iso-panel="{{$item['id']}}"><small style="font-weight:900;color:var(--green)">{{$item['number']}}</small><h2>{{$item['title']}}</h2><p>{{$item['description']}}</p><div class="iso-clause"><div><strong>{{$item['number']}}</strong></div><div><strong>{{$item['title']}}</strong></div><div><p>{{$item['description']}}</p></div></div><div class="iso-audit-note">Tutaj pojawią się pola robocze tej części ISO 50001.</div></section>@endforeach
@endforeach
</div></div>
<script>
function showIsoSection(section) {
    const panels = document.querySelectorAll('#aw-iso50001 [data-iso-panel]');
    if (![...panels].some(panel => panel.dataset.isoPanel === section)) return;
    panels.forEach(panel => panel.classList.toggle('active', panel.dataset.isoPanel === section));
    document.querySelectorAll('#aw-iso50001 [data-iso-target]').forEach(item => item.classList.toggle('active', item.dataset.isoTarget === section));
}
document.querySelectorAll('#aw-iso50001 [data-iso-target]').forEach(button => button.addEventListener('click', () => showIsoSection(button.dataset.isoTarget)));
document.addEventListener('DOMContentLoaded', () => {
    const section = new URLSearchParams(location.search).get('section');
    if (section) showIsoSection(section);
});
</script>
