<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Harmonogram {{ $project->number }} — {{ $project->name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.css">
    <style>
        :root{--brand:{{ $appBrand?->primaryColor() ?? '#1A4D3A' }}}*{box-sizing:border-box}body{margin:0;background:#f4f5f1;color:#26332b;font-family:Inter,Arial,sans-serif}.wrap{max-width:1500px;margin:auto;padding:24px}.head{display:flex;justify-content:space-between;align-items:center;gap:20px;background:#fff;border:1px solid #e1e3dc;border-radius:13px;padding:18px 20px;margin-bottom:16px}.brand{display:flex;align-items:center;gap:12px}.brand img{width:50px;height:50px;object-fit:contain}.kicker{font-size:11px;font-weight:800;color:var(--brand);letter-spacing:.08em}.head h1{font-size:22px;margin:4px 0}.head p{margin:0;color:#6b766e;font-size:13px}.readonly{padding:7px 11px;background:#eef4ef;color:var(--brand);border-radius:999px;font-size:11px;font-weight:800}.card{background:#fff;border:1px solid #e1e3dc;border-radius:13px;padding:18px}.tools{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:12px}.tools button{border:1px solid #d8dcd5;background:#fff;border-radius:7px;padding:7px 11px;font-weight:700;cursor:pointer}.tools button.active{background:var(--brand);border-color:var(--brand);color:#fff}.legend{margin-left:auto;font-size:12px;color:#657168}.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin:0 5px 0 12px}.chart{min-height:300px;overflow-x:auto;border:1px solid #e4e6df;border-radius:9px}.chart .gantt-container{overflow:visible}.chart svg{min-width:100%;pointer-events:none}.chart .bar-wrapper.stage-row .bar{fill:#2563eb}.chart .bar-wrapper.stage-row .bar-progress{fill:#1d4ed8}.chart .bar-wrapper.task-row .bar{fill:#8b5cf6}.chart .bar-wrapper.task-row .bar-progress{fill:#6d28d9}.empty{padding:60px;text-align:center;color:#777}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{text-align:left;padding:10px;border-bottom:1px solid #eee;font-size:12px}th{font-size:10px;text-transform:uppercase;color:#758078;background:#fafaf7}@media(max-width:700px){.wrap{padding:10px}.head{align-items:flex-start}.readonly{display:none}.legend{width:100%;margin-left:0}}
    </style>
</head>
<body>
<main class="wrap">
    <header class="head"><div class="brand"><img src="{{ $appBrand?->logoUrl() ?? asset('Logo2.png') }}" alt="Logo"><div><div class="kicker">{{ $project->number }}</div><h1>{{ $project->name }}</h1><p>Publiczny harmonogram projektu · aktualizacja {{ now()->format('d.m.Y H:i') }}</p></div></div><span class="readonly">Tylko podgląd</span></header>
    <section class="card">
        <div class="tools"><strong>Widok:</strong>@foreach(['Day'=>'Dzień','Week'=>'Tydzień','Month'=>'Miesiąc'] as $mode=>$label)<button class="mode {{$mode==='Week'?'active':''}}" data-mode="{{$mode}}">{{$label}}</button>@endforeach<span class="legend"><i class="dot" style="background:#8b5cf6"></i>Zadania · strzałki oznaczają zależności</span></div>
        @if($timelineItems->isEmpty())<div class="empty">Harmonogram nie zawiera jeszcze zadań.</div>@else<div id="public-gantt" class="chart"></div>
        <div style="overflow-x:auto"><table><thead><tr><th>Pozycja</th><th>Osoba</th><th>Zależne od</th><th>Termin</th><th>Postęp</th></tr></thead><tbody>@foreach($timelineItems as $item)<tr><td><strong>{{$item['name']}}</strong><br><small>{{$item['kind']==='stage'?'Etap':'Zadanie'}}</small></td><td>{{$item['assignee']??'—'}}</td><td>{{$item['dependencies']?($timelineItems->firstWhere('id',$item['dependencies'])['name']??'—'):'—'}}</td><td>{{\Carbon\Carbon::parse($item['start'])->format('d.m.Y')}} – {{\Carbon\Carbon::parse($item['end'])->format('d.m.Y')}}</td><td>{{$item['progress']}}%</td></tr>@endforeach</tbody></table></div>@endif
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.min.js"></script>
<script>
const items=@json($timelineItems);let gantt=null;
function render(mode='Week'){if(!document.getElementById('public-gantt')||typeof Gantt==='undefined')return;document.getElementById('public-gantt').innerHTML='';gantt=new Gantt('#public-gantt',items.map(item=>({id:item.id,name:item.assignee?item.name+' · '+item.assignee:item.name,start:item.start,end:item.end,progress:item.progress,dependencies:item.dependencies||'',custom_class:item.kind==='stage'?'stage-row':'task-row'})),{view_mode:mode,language:'en',readonly:true});}
render();document.querySelectorAll('.mode').forEach(button=>button.addEventListener('click',()=>{render(button.dataset.mode);document.querySelectorAll('.mode').forEach(item=>item.classList.toggle('active',item===button));}));
</script>
</body>
</html>
