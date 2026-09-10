@php
    $contextUrl = isset($audit)
        ? route(($clientView ?? false) ? 'client.audits.iso50001.context.show' : 'audits.iso50001.context.show', $audit)
        : route('audit-types.iso50001.context', $auditType);
@endphp
<section style="grid-column:1/-1;padding:20px;border:1px solid #d8e3dc;border-radius:12px;background:#fff">
    <div class="iso-context-launch">
        <div><h3 style="margin:0">Ankieta kontekstu organizacji</h3><p style="margin:8px 0 0">D-EnMS-KON-01 · Dane o zakładzie → Wybór czynników → Uzupełnienie konsultanta → Dokument</p></div>
        <a href="{{ $contextUrl }}" style="display:inline-block;padding:11px 18px;border-radius:7px;background:var(--green);color:#fff;text-decoration:none;font-weight:700;text-align:center">{{ isset($audit) ? 'Otwórz ankietę na pełnym ekranie' : 'Otwórz podgląd formularza wzorcowego' }}</a>
    </div>
</section>
<style>.iso-context-launch{display:flex;align-items:center;justify-content:space-between;gap:20px}.iso-context-launch>div{min-width:0;flex:1}.iso-context-launch>a{flex-shrink:0;white-space:nowrap}@media(max-width:800px){.iso-context-launch{flex-direction:column;align-items:flex-start}.iso-context-launch>a{white-space:normal;max-width:100%}}</style>
