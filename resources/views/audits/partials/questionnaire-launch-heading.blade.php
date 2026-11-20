@once<link rel="stylesheet" href="{{ asset('css/iso-questionnaire-overview.css') }}">@endonce
<div data-questionnaire-launch-heading class="iso-profile-entry-heading">
    <span class="iso-profile-number">{{ $launchNumber ?? ($loop->iteration ?? 1) }}</span>
    <div><strong>{{ $launchName }}</strong>@if(!empty($launchStatus)) · {{ $launchStatus }}@endif</div>
    @if(isset($launchUrl))
        <a href="{{ $launchUrl }}" class="iso-action-btn primary iso-profile-open" title="{{ $launchLabel }} — {{ $launchName }}">{{ $launchLabel }}</a>
    @else
        <button type="button" class="prim iso-profile-open" data-profile="{{ $launchProfileId }}" title="{{ $launchLabel }} — {{ $launchName }}">{{ $launchLabel }}</button>
    @endif
</div>
