@php($guidance = config('iso50001-guidance.'.$item['id'], []))
@if($guidance)
<div class="iso-guidance">
    <section class="iso-guide-card iso-guide-verify">
        <h3><i class="ti ti-search"></i> Co sprawdzić podczas audytu</h3>
        <ul>@foreach($guidance['verify'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul>
    </section>
    <section class="iso-guide-card iso-guide-evidence">
        <h3><i class="ti ti-files"></i> Oczekiwane dowody i dokumenty</h3>
        <ul>@foreach($guidance['evidence'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul>
    </section>
    <section class="iso-guide-card iso-guide-actions">
        <h3><i class="ti ti-list-check"></i> Zalecany sposób wdrożenia</h3>
        <ol>@foreach($guidance['actions'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ol>
    </section>
    @if($guidance['pitfall'] ?? false)
    <aside class="iso-guide-warning"><i class="ti ti-alert-triangle"></i><div><strong>Typowa pułapka</strong><p>{{ $guidance['pitfall'] }}</p></div></aside>
    @endif
</div>
@endif
