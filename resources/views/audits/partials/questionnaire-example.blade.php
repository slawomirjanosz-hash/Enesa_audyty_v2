@if($exampleAllowed && auth()->user()?->can('audits.manage'))
<div class="plant-notice" style="margin:14px 0;padding:14px;border:1px solid #d6dfda;border-radius:8px;background:#f3f7f4">
    <button type="button" data-questionnaire-example="{{ $exampleForm }}" data-example-scope="{{ $exampleScope ?? '' }}" style="padding:9px 14px;border:1px solid #1a4d3a;border-radius:7px;background:#1a4d3a;color:white;cursor:pointer">Wypełnij przykładowo</button>
    <span data-example-message role="status">Dane testowe: tylko puste odpowiedzi klienta. Bez zapisu i zatwierdzania.</span>
</div>
@once<script src="{{ asset('js/questionnaire-example.js') }}?v=1" defer></script>@endonce
@endif
