@php($guidance = config('iso50001-guidance.'.$item['id'], []))
@if($guidance)
<div class="iso-guidance">
    <section class="iso-guide-card iso-guide-verify">
        <h3><i class="ti ti-search"></i> Co sprawdzić podczas audytu</h3>
        <ul>@foreach($guidance['verify'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul>
    </section>
    <section class="iso-guide-card iso-guide-evidence">
        <h3><i class="ti ti-files"></i> Oczekiwane dowody i dokumenty</h3>
        <ul>@foreach($guidance['evidence'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul>
    </section>
    <section class="iso-guide-card iso-guide-actions">
        <h3><i class="ti ti-list-check"></i> Zalecany sposób wdrożenia</h3>
        <ol class="iso-action-list">@foreach($guidance['actions'] ?? [] as $actionIndex => $line)<li><div class="iso-action-line"><span>{{ $line }}</span>
            @if(in_array($item['id'], ['3-1', '4-1'], true) && ($workflow = collect(config('iso50001-workflows.'.$item['id']))->values()->get($actionIndex)))
                @php($actionKey = collect(config('iso50001-workflows.'.$item['id']))->keys()->get($actionIndex))
                <span class="iso-action-buttons"><button type="button" class="iso-action-btn" data-iso-toggle="sample-{{ $actionKey }}"><i class="ti ti-file-description"></i> Dokument przykładowy</button><button type="button" class="iso-action-btn primary" data-iso-toggle="form-{{ $actionKey }}"><i class="ti ti-clipboard-text"></i> Ankieta do wypełnienia</button></span>
                <div class="iso-action-panel" id="sample-{{ $actionKey }}"><strong>Wzór: {{ $workflow['title'] }}</strong><p>{{ $workflow['sample'] }}</p><div class="iso-sample-table">@foreach($workflow['sample_data'] ?? [] as $sampleLabel => $sampleValue)<div><span>{{ $sampleLabel }}</span><strong>{{ $sampleValue }}</strong></div>@endforeach</div><div class="iso-sample-note">To jest dokument wzorcowy. Nie zawiera danych żadnego klienta.</div></div>
                <div class="iso-action-panel" id="form-{{ $actionKey }}">@include('audit-types.partials.iso50001-action-form', ['workflow' => $workflow, 'actionKey' => $actionKey, 'sectionId' => $item['id']])</div>
            @endif
        </div></li>@endforeach</ol>
    </section>
    @if($guidance['pitfall'] ?? false)
    <aside class="iso-guide-warning"><i class="ti ti-alert-triangle"></i><div><strong>Typowa pułapka</strong><p>{{ $guidance['pitfall'] }}</p></div></aside>
    @endif
</div>
@once
<style>
.iso-action-list>li{margin-bottom:13px}.iso-action-line{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.iso-action-line>span:first-child{flex:1;min-width:260px}.iso-action-buttons{display:flex;gap:7px;flex-wrap:wrap}.iso-action-btn{border:1px solid #b9cbc0;border-radius:7px;background:#fff;color:var(--green);padding:7px 10px;font:800 12px Manrope;cursor:pointer}.iso-action-btn.primary{background:var(--green);color:#fff}.iso-action-panel{display:none;width:100%;margin:10px 0 4px;padding:15px;border:1px solid #dce5de;border-radius:9px;background:#fff}.iso-action-panel.open{display:block}.iso-action-panel p{font-size:13px;line-height:1.6}.iso-sample-note{font-size:12px;color:#647169;background:#f5f7f4;padding:8px 10px;border-radius:6px}.iso-response-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.iso-response-field{display:flex;flex-direction:column;gap:5px}.iso-response-field.full{grid-column:1/-1}.iso-response-field label{font-size:12px;font-weight:800}.iso-response-field input,.iso-response-field textarea{border:1px solid #cfd8d2;border-radius:7px;padding:9px 10px;font:13px Lato}.iso-response-field textarea{min-height:85px;resize:vertical}.iso-response-actions{display:flex;gap:8px;margin-top:12px}.iso-response-actions button{border:0;border-radius:7px;padding:9px 12px;font:800 12px Manrope;cursor:pointer}.iso-response-save{background:#e7f0ea;color:var(--green)}.iso-response-pdf{background:var(--green);color:#fff}.iso-template-banner{font-size:12px;color:#536158;margin-bottom:10px;padding:9px;background:#eef5f0;border-radius:7px}@media(max-width:700px){.iso-response-grid{grid-template-columns:1fr}.iso-response-field.full{grid-column:auto}}
</style>
<style>.iso-sample-table{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:#dfe6e1;border:1px solid #dfe6e1;border-radius:7px;overflow:hidden;margin:10px 0}.iso-sample-table>div{background:#fff;padding:9px 11px;display:flex;justify-content:space-between;gap:12px}.iso-sample-table span{color:#66736b}.iso-sample-table strong{text-align:right}</style>
<script>document.addEventListener('click',event=>{const button=event.target.closest('[data-iso-toggle]');if(!button)return;document.getElementById(button.dataset.isoToggle)?.classList.toggle('open')})</script>
@endonce
@endif
