@php
    $contextUrl = isset($audit)
        ? route(($clientView ?? false) ? 'client.audits.iso50001.context.show' : 'audits.iso50001.context.show', $audit)
        : route('audit-types.iso50001.context', $auditType);
@endphp
<section style="grid-column:1/-1;padding:20px;border:1px solid #d8e3dc;border-radius:12px;background:#fff">
    <h3>Ankieta kontekstu organizacji</h3>
    <p style="margin:10px 0 16px">D-EnMS-KON-01 · Dane o zakładzie → Wybór czynników → Uzupełnienie konsultanta → Dokument</p>
    <a href="{{ $contextUrl }}" style="display:inline-block;padding:11px 18px;border-radius:7px;background:var(--green);color:#fff;text-decoration:none;font-weight:700">{{ isset($audit) ? 'Otwórz ankietę na pełnym ekranie' : 'Otwórz podgląd formularza wzorcowego' }}</a>
</section>
