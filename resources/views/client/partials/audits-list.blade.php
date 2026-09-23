@php
    $statusLabels = ['draft'=>'Przygotowywany','in_progress'=>'W trakcie','done'=>'Zakończony','cancelled'=>'Anulowany'];
    $auditProgress = app(\App\Services\QuestionnaireCompletion::class)->auditCards($audits);
    $auditBrandColor = $appBrand?->primaryColor() ?: '#1A4D3A';
    $auditChannels = array_map(function ($offset) use ($auditBrandColor) {
        $value = hexdec(substr(ltrim($auditBrandColor, '#'), $offset, 2)) / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, [0, 2, 4]);
    $auditButtonText = (0.2126 * $auditChannels[0] + 0.7152 * $auditChannels[1] + 0.0722 * $auditChannels[2]) > 0.179 ? '#000' : '#fff';
@endphp
<style>
.client-audit-list{display:grid;gap:13px}.client-audit-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:19px 21px}.client-audit-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.client-audit-number{font-size:11px;font-weight:800;color:var(--green)}.client-audit-title{font-size:16px;margin:3px 0 7px}.client-audit-meta,.client-audit-stats{display:flex;flex-wrap:wrap;gap:7px 17px;color:#69766e;font-size:12px}.client-audit-status{padding:5px 10px;border-radius:999px;background:#e9f4ed;color:#20583e;font-size:10px;font-weight:800;white-space:nowrap}.client-audit-stats{border-top:1px solid #eee;margin-top:15px;padding-top:12px;font-size:11px}.client-audit-empty{text-align:center;background:#fff;border:1px dashed #d0ccc0;border-radius:12px;padding:55px 20px;color:#78847d}.client-audit-empty i{font-size:42px;display:block;margin-bottom:10px;color:#c8d5cf}@media(max-width:600px){.client-audit-top{flex-direction:column}}
.client-audit-stats{align-items:center}
.client-audit-stats .client-audit-open{display:inline-flex;align-items:center;justify-content:center;gap:8px;margin-left:auto;padding:10px 16px;min-height:40px;border:1px solid transparent;border-radius:8px;background:var(--green);color:#fff;font-size:13px;font-weight:700;line-height:1.2;white-space:nowrap;text-decoration:none;box-shadow:0 2px 4px #00000014;transition:box-shadow .15s,filter .15s}
.client-audit-open i{font-size:16px}.client-audit-stats .client-audit-open:hover{filter:brightness(.94);box-shadow:0 3px 7px #00000024}.client-audit-stats .client-audit-open:focus-visible{outline:3px solid #172c3a;outline-offset:3px}
.client-audit-stats .client-audit-open{color:{{$auditButtonText}}}
@media(max-width:420px){.client-audit-stats .client-audit-open{width:100%;margin-left:0;margin-top:5px}}
</style>
@if($audits->isEmpty())
<div class="client-audit-empty"><i class="ti ti-clipboard-off"></i>Nie ma jeszcze audytów przypisanych do tej firmy.</div>
@else
<div class="client-audit-list">
@foreach($audits as $audit)
<article class="client-audit-card">
    <div class="client-audit-top"><div>
        <span class="client-audit-number">{{$audit->number?:'AUDYT #'.$audit->id}}</span>
        <h2 class="client-audit-title">{{$audit->title}}</h2>
        <p style="margin:0 0 12px;font-size:13px">Rodzaj audytu: {{$audit->surveys->map(fn($survey)=>$survey->auditType?->name)->filter()->unique()->implode(', ') ?: 'Nie określono'}}</p>
        <div class="client-audit-meta">
            <span>Rozpoczęcie: {{$audit->start_date?->format('d.m.Y')??'Nie ustalono'}}</span>
            <span>Planowane zakończenie: {{$audit->end_date?->format('d.m.Y')??'Nie ustalono'}}</span>
            <span>Opiekun: {{$audit->manager?->name??'Nie przypisano'}}</span>
        </div>
    </div><span class="client-audit-status">{{$statusLabels[$audit->status]??'Nie określono'}}</span></div>
    @if($audit->description)<p style="font-size:13px;color:#58665e;line-height:1.55">{{$audit->description}}</p>@endif
    <div class="client-audit-stats">
        <div style="flex:1;min-width:180px" title="Wypełnienie dostępnych ankiet audytu, niezależnie od ich zatwierdzenia">
            <strong>Wypełnienie audytu: {{$auditProgress[$audit->id]['percent']}}%</strong>
            <progress aria-label="Wypełnienie audytu {{$audit->title}}" max="100" value="{{$auditProgress[$audit->id]['percent']}}" style="display:block;width:100%;max-width:420px;height:10px;margin-top:8px;accent-color:var(--green)"></progress>
        </div>
        @if(request()->routeIs('client.*'))<a href="{{route('client.audits.show',$audit)}}" class="client-audit-open" aria-label="Otwórz audyt: {{$audit->title}}">Otwórz audyt <i class="ti ti-arrow-right" aria-hidden="true"></i></a>@endif
    </div>
</article>
@endforeach
</div>
@endif
