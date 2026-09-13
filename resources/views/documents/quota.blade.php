@php
    $quotaUser = auth()->user();
    $quotaUsed = app(\App\Services\DocumentQuotaService::class)->used($quotaUser->id);
    $quotaLimit = (int) $quotaUser->document_limit_bytes;
@endphp
<section style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;margin-bottom:16px" aria-label="Limit dokumentów">
    <strong>Twoje miejsce na dokumenty: {{ \App\Models\Document::formatBytes($quotaUsed) }} z {{ \App\Models\Document::formatBytes($quotaLimit) }}</strong>
    <progress style="display:block;width:100%;height:14px;margin:10px 0" max="{{ max(1,$quotaLimit) }}" value="{{ min($quotaUsed,$quotaLimit) }}"></progress>
    <small>Limit obejmuje rozmiar przesłanych i zapisanych dokumentów, również wersje ISO — nie wielkość kopii zapasowych serwera. Starsze pliki bez przypisanego autora pozostają wspólne. Zmianę limitu zleć administratorowi.</small>
</section>
