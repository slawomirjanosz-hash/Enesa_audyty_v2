@if(isset($audit))
@php($systemCards=app(\App\Services\QuestionnaireCompletion::class)->audit($audit)[$item['id']])
<section style="grid-column:1/-1;padding:20px;border:1px solid #d8e3dc;border-radius:12px;background:#fff;margin:16px 0">@include('audits.partials.questionnaire-point-heading',['pointTitle'=>\App\Models\IsoSystemReview::TITLES[$item['id']],'pointHelp'=>'Wybierz zakład, aby otworzyć jego ankietę. Wypełnienie i status zatwierdzenia dotyczą danej lokalizacji.'])
@forelse($systemCards as $card)
<div class="iso-profile-entry">
    @if($item['id']==='5-1' && \App\Services\Enms\Rollout::enabled($audit->id))
        @include('audits.enms.launch')
    @else
        @include('audits.partials.questionnaire-launch-heading', ['launchName'=>$card['name'], 'launchStatus'=>$card['status'], 'launchLabel'=>'Otwórz ankietę '.str_replace('-','.',$item['id']), 'launchUrl'=>route(($clientView??false)?'client.audits.system.show':'audits.system.show',[$audit,$card['profile_id'],$item['id']])])

        <div class="iso-profile-meta">@include('audits.partials.questionnaire-status',['statusRecord'=>$card['review'],'statusStale'=>$card['stale']])<span class="iso-profile-responsible">Odpowiedzialny: {{$card['responsible_name']??'Nieprzypisana'}}</span></div>
@include('partials.questionnaire-progress',['progress'=>$card['progress']])
    @endif
</div>
@empty
<p>Najpierw utwórz profil zakładu we Wstępie do ISO.</p>
@endforelse
</section>
@endif
