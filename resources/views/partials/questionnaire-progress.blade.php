<div class="questionnaire-progress" @if(isset($progressForm)) data-completion-form="{{ $progressForm }}" data-completion-mode="{{ $progressMode ?? 'fields' }}" data-completion-fields='@json($progressFields ?? [])' @endif>
    <div class="questionnaire-progress-text"><span>{{ $progressLabel ?? 'Wypełnienie ankiety' }}</span><strong data-completion-text>{{ $progress['answered'] }}/{{ $progress['total'] }} pytań · {{ $progress['percent'] }}%</strong></div>
    <progress max="100" value="{{ $progress['percent'] }}" aria-label="{{ $progressLabel ?? 'Wypełnienie ankiety' }}"></progress>
</div>
@once
<style>.questionnaire-progress{min-width:180px;margin:10px 0;color:#244638;font:14px/1.5 Arial,sans-serif}.questionnaire-progress-text{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.questionnaire-progress strong{white-space:nowrap}.questionnaire-progress progress{display:block;width:100%;height:8px;margin-top:7px;accent-color:var(--brand,var(--green,#1a4d3a));border:0;border-radius:8px;overflow:hidden;background:#e6ede8}.questionnaire-progress progress::-webkit-progress-bar{background:#e6ede8}.questionnaire-progress progress::-webkit-progress-value{background:var(--brand,var(--green,#1a4d3a))}</style>
<script src="{{ asset('js/questionnaire-progress.js') }}" defer></script>
@endonce
