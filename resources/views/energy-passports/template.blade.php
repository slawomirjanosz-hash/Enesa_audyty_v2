@extends('layouts.app')

@section('page-title', $template->name)

@push('styles')
<style>
.pt-back{display:inline-flex;align-items:center;gap:6px;color:var(--green);font-size:13px;font-weight:700;text-decoration:none;margin-bottom:18px}.pt-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:20px}.pt-title{font-size:23px;font-weight:750;color:#17251f}.pt-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}.pt-badge{padding:4px 9px;border-radius:20px;background:#eaf4ef;color:var(--green);font-size:10px;font-weight:700}.pt-btn{display:inline-flex;align-items:center;gap:7px;background:var(--green);color:#fff;border-radius:8px;padding:10px 15px;text-decoration:none;font-size:13px;font-weight:700}.pt-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:11px;margin-bottom:18px}.pt-stat{background:#fff;border:1px solid #e2ded5;border-radius:11px;padding:14px}.pt-stat strong{display:block;font-size:22px;color:var(--green)}.pt-stat span{font-size:10px;color:#76827a;text-transform:uppercase}.pt-tabs{display:flex;overflow-x:auto;background:#f8f7f3;border:1px solid #e2ded5;border-radius:11px 11px 0 0}.pt-tab{border:0;background:transparent;padding:13px 17px;white-space:nowrap;font:650 12px Manrope;color:#748078;cursor:pointer}.pt-tab.active{background:#fff;color:var(--green);border-bottom:2px solid var(--green)}.pt-stage{display:none;background:#fff;border:1px solid #e2ded5;border-top:0;border-radius:0 0 11px 11px;padding:17px}.pt-stage.active{display:block}.pt-stage-title{font-size:12px;color:#68756d;margin-bottom:14px}.pt-section{border:1px solid #e8e4dd;border-radius:9px;overflow:hidden;margin-bottom:12px}.pt-section h2{font-size:12px;padding:10px 13px;background:#f5f3ed;color:#315041}.pt-question{display:grid;grid-template-columns:75px minmax(250px,1fr) minmax(180px,.65fr);gap:12px;padding:12px 13px;border-top:1px solid #f0ede7}.pt-code{font:700 11px monospace;color:var(--green)}.pt-text{font-size:12px;line-height:1.45;white-space:pre-line}.pt-hint{font-size:10px;line-height:1.4;color:#79867e;white-space:pre-line}.pt-unit{font-size:10px;font-weight:700;color:#526259;margin-top:5px}@media(max-width:800px){.pt-head{flex-direction:column}.pt-summary{grid-template-columns:1fr}.pt-question{grid-template-columns:60px 1fr}.pt-hint{grid-column:1/-1}}
</style>
@endpush

@section('content')
@php
$stages=$template->sections ?? [];
$questionCount=collect($stages)->sum(fn($stage)=>collect($stage['sections']??[])->sum(fn($section)=>count($section['questions']??[])));
$sectionCount=collect($stages)->sum(fn($stage)=>count($stage['sections']??[]));
@endphp
<a class="pt-back" href="{{route('energy-passports.index')}}"><i class="ti ti-arrow-left"></i> Wróć do biblioteki</a>
<div class="pt-head"><div><div class="pt-title"><i class="ti ti-file-certificate"></i> {{$template->name}}</div><div class="pt-meta"><span class="pt-badge">{{$template->scope==='system'?'System':'Urządzenie'}}</span><span class="pt-badge">{{$template->category}}</span><span class="pt-badge">{{$template->code}}</span><span class="pt-badge">Wersja {{$template->version}}</span></div></div>@if($canManage)<a class="pt-btn" href="{{route('energy-passports.index',['template'=>$template->id,'create'=>1])}}"><i class="ti ti-plus"></i> Utwórz paszport z tego szablonu</a>@endif</div>
<div class="pt-summary"><div class="pt-stat"><strong>{{count($stages)}}</strong><span>Etapy formularza</span></div><div class="pt-stat"><strong>{{$sectionCount}}</strong><span>Sekcje tematyczne</span></div><div class="pt-stat"><strong>{{$questionCount}}</strong><span>Pytania i parametry</span></div></div>
<div class="pt-tabs">@foreach($stages as $index=>$stage)<button class="pt-tab {{$index===0?'active':''}}" type="button" onclick="showTemplateStage({{$index}},this)">{{Str::of($stage['name']??('Etap '.($index+1)))->replace('_',' ')}}</button>@endforeach</div>
@foreach($stages as $index=>$stage)<section class="pt-stage {{$index===0?'active':''}}" data-stage="{{$index}}"><div class="pt-stage-title">{{$stage['title']??''}}</div>@foreach($stage['sections']??[] as $section)<article class="pt-section"><h2>{{$section['title']}}</h2>@foreach($section['questions']??[] as $question)<div class="pt-question"><div class="pt-code">{{$question['code']}}</div><div><div class="pt-text">{{$question['question']}}</div>@if(filled($question['unit']??null))<div class="pt-unit">Jednostka: {{$question['unit']}}</div>@endif</div><div class="pt-hint">{{$question['hint']??''}}</div></div>@endforeach</article>@endforeach</section>@endforeach
@endsection

@push('scripts')
<script>function showTemplateStage(index,button){document.querySelectorAll('.pt-stage').forEach(stage=>stage.classList.toggle('active',Number(stage.dataset.stage)===index));document.querySelectorAll('.pt-tab').forEach(tab=>tab.classList.remove('active'));button.classList.add('active')}</script>
@endpush
