@php
    $response = isset($audit) ? ($isoImplementationResponses->get($sectionId.'|'.$actionKey) ?? null) : null;
    $answers = old('answers', $response?->answers ?? []);
    $isTemplatePreview = !isset($audit);
    $storeRoute = !$isTemplatePreview ? (($clientView ?? false) ? route('client.audits.iso50001.responses.store', [$audit, $sectionId, $actionKey]) : route('audits.iso50001.responses.store', [$audit, $sectionId, $actionKey])) : null;
    $pdfRoute = !$isTemplatePreview ? (($clientView ?? false) ? route('client.audits.iso50001.responses.pdf', [$audit, $sectionId, $actionKey]) : route('audits.iso50001.responses.pdf', [$audit, $sectionId, $actionKey])) : null;
@endphp
<strong>{{ $workflow['title'] }}</strong>
@if($isTemplatePreview)<div class="iso-template-banner">Podgląd wzoru ankiety. Klient otrzyma własny formularz w swoim audycie, a wpisane tutaj dane nie są zapisywane.</div>@endif
<form method="POST" @if($storeRoute) action="{{ $storeRoute }}" @endif>@csrf
    <div class="iso-response-grid">
        @foreach($workflow['fields'] as $key => $field)<div class="iso-response-field {{ $field['type'] === 'textarea' ? 'full' : '' }}"><label for="iso-{{ $actionKey }}-{{ $key }}">{{ $field['label'] }}</label>
            @if($field['type'] === 'textarea')<textarea id="iso-{{ $actionKey }}-{{ $key }}" name="answers[{{ $key }}]" @disabled($isTemplatePreview)>{{ $answers[$key] ?? '' }}</textarea>@else<input id="iso-{{ $actionKey }}-{{ $key }}" type="{{ $field['type'] }}" name="answers[{{ $key }}]" value="{{ $answers[$key] ?? '' }}" placeholder="{{ $field['placeholder'] ?? '' }}" step="{{ $field['type'] === 'number' ? '0.01' : '' }}" @disabled($isTemplatePreview)>@endif
        </div>@endforeach
    </div>
    @unless($isTemplatePreview)<div class="iso-response-actions"><button class="iso-response-save" type="submit"><i class="ti ti-device-floppy"></i> Zapisz roboczo</button><button class="iso-response-pdf" type="submit" formaction="{{ $pdfRoute }}"><i class="ti ti-file-type-pdf"></i> Generuj PDF</button></div>@if($response?->generated_at)<div class="iso-template-banner" style="margin-top:10px">Ostatni PDF wygenerowano {{ $response->generated_at->format('d.m.Y H:i') }}. Każde kolejne wygenerowanie tworzy nową wersję w dokumentacji klienta.</div>@endif@endunless
</form>
