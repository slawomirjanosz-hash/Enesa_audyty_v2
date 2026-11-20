<div data-questionnaire-launch-heading style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
    <div><strong>{{ $launchName }}</strong>@if(!empty($launchStatus)) · {{ $launchStatus }}@endif</div>
    @if(isset($launchUrl))
        <a href="{{ $launchUrl }}" class="iso-action-btn primary" style="display:inline-block;text-decoration:none;white-space:nowrap" title="{{ $launchLabel }} — {{ $launchName }}">{{ $launchLabel }}</a>
    @else
        <button type="button" class="prim" data-profile="{{ $launchProfileId }}" style="white-space:nowrap" title="{{ $launchLabel }} — {{ $launchName }}">{{ $launchLabel }}</button>
    @endif
</div>
