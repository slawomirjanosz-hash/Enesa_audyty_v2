@props(['heading'])

@once
<style>
.document-help-heading{display:flex;align-items:center;gap:7px;position:relative;margin-bottom:12px}
.document-help-heading h2{margin:0}
.document-help summary{display:grid;place-items:center;width:24px;height:24px;color:#37644c;border-radius:50%;cursor:pointer;list-style:none}
.document-help summary::-webkit-details-marker{display:none}
.document-help summary:hover,.document-help[open] summary{background:#edf4ef}
.document-help summary:focus-visible{outline:2px solid #337d5a;outline-offset:2px}
.document-help summary .ti{font-size:18px}
.document-help-text{position:absolute;z-index:3;top:calc(100% + 4px);left:0;width:max-content;max-width:min(430px,100%);box-sizing:border-box;padding:12px 15px;border:1px solid #cbdcd0;border-radius:10px;background:#fff;color:#25372e;box-shadow:0 10px 35px #183d302b;font-size:13px;line-height:1.5}
.document-help-text p{margin:0}.document-help-text p+p{margin-top:6px}
</style>
@endonce
<div class="document-help-heading">
    <h2>{{ $heading }}</h2>
    <details class="document-help">
        <summary aria-label="Informacje: {{ strip_tags((string) $heading) }}"><i class="ti ti-info-circle" aria-hidden="true"></i></summary>
        <div class="document-help-text">{{ $slot }}</div>
    </details>
</div>
