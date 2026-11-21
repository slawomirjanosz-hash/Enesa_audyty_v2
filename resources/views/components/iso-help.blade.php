@props(['label', 'submit' => false])
@php($helpId = 'iso-help-'.\Illuminate\Support\Str::uuid())
<div class="iso-help {{ $submit ? 'iso-upload-help' : '' }}">
    @if($submit)
        <button type="submit" class="iso-doc-submit" aria-describedby="{{ $helpId }}"><i class="ti ti-upload" aria-hidden="true"></i> {{ $label }}</button>
    @else
        <button type="button" class="iso-point-help" aria-label="{{ $label }}" aria-describedby="{{ $helpId }}">?</button>
    @endif
    <div class="iso-point-tooltip" role="tooltip" id="{{ $helpId }}">{{ $slot }}</div>
</div>
