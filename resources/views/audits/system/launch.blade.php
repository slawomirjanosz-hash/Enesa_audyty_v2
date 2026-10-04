@if(isset($audit))
@php($systemCards=app(\App\Services\QuestionnaireCompletion::class)->audit($audit)[$item['id']])
<section style="grid-column:1/-1;padding:20px;border:1px solid #d8e3dc;border-radius:12px;background:#fff;margin:16px 0"><h3>{{\App\Models\IsoSystemReview::TITLES[$item['id']]}}</h3>
@forelse($systemCards as $card)<div style="padding:12px 0;border-top:1px solid #e4ebe6"><strong>{{$card['name']}}</strong> · {{$card['status']}} @include('partials.questionnaire-progress',['progress'=>$card['progress']])<a href="{{route(($clientView??false)?'client.audits.system.show':'audits.system.show',[$audit,$card['profile_id'],$item['id']])}}" class="iso-action-btn primary" style="display:inline-block;text-decoration:none">Otwórz ankietę {{str_replace('-','.',$item['id'])}}</a></div>@empty<p>Najpierw utwórz profil zakładu we Wstępie do ISO.</p>@endforelse</section>
@endif
