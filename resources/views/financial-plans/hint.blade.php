<span class="fp-hint">
    <button type="button" class="fp-hint-trigger {{ $hintClass ?? '' }}" data-fp-tip="{{ $hintId }}" aria-describedby="{{ $hintId }}" aria-label="{{ $hintAria ?? 'Wyjaśnienie: '.($hintTitle ?? $hintLabel) }}">{{ $hintLabel }}</button>
    <span id="{{ $hintId }}" class="fp-tooltip" popover="manual" role="tooltip">
        @if(!empty($hintTitle))<strong>{{ $hintTitle }}</strong>@endif
        @if(!empty($hintText))<span class="fp-tooltip-text">{{ $hintText }}</span>@endif
        @if(!empty($hintItems))<ul>@foreach($hintItems as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
    </span>
</span>
