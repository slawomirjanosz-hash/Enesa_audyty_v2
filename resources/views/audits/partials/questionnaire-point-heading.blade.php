@php($pointHelpId='iso-point-help-'.\Illuminate\Support\Str::uuid())
<div class="iso-point-title"><h3>{{ $pointTitle }}</h3><span class="iso-point-help" tabindex="0" role="button" aria-label="Opis punktu" aria-describedby="{{ $pointHelpId }}"><span aria-hidden="true">?</span><span class="iso-point-tooltip" role="tooltip" id="{{ $pointHelpId }}">{{ $pointHelp }}</span></span></div>
