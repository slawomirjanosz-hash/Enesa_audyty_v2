@php
    $guidance = config('iso50001-guidance.'.$sectionId, []);
    $suggestedDocuments = $guidance['evidence'] ?? [];
    $sectionTemplates = collect($templateDocuments->get($sectionId, []));
    $sectionClientDocuments = isset($clientDocuments) ? collect($clientDocuments->get($sectionId, [])) : collect();
    $isAuditContext = isset($audit);
    $canUpload = $isAuditContext ? ($canManage ?? false) : ($canManageTraining ?? false);
    $canDeleteClientDocument = $isAuditContext && (($clientView ?? false)
        ? auth()->user()->hasRole('client_admin')
        : ($canUpload && auth()->user()->hasAnyRole(['admin', 'superadmin'])));
@endphp
@once
<style>
.iso-docs{margin-top:22px;border-top:1px solid #e7e5de;padding-top:18px}.iso-docs-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.iso-docs-head h3{margin:0;font-size:15px;color:#263b31}.iso-docs-head p{margin:4px 0 0;font-size:11px;color:#738078}.iso-doc-suggestions{margin:13px 0;padding:12px 14px;border-radius:9px;background:#f7f8f5;border:1px solid #e3e7e2}.iso-doc-suggestions strong{font-size:11px;color:#415148}.iso-doc-suggestions ul{margin:7px 0 0;padding-left:18px;columns:2}.iso-doc-suggestions li{font-size:10.5px;color:#657269;margin-bottom:5px}.iso-doc-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:13px}.iso-doc-box{border:1px solid #e1e5e1;border-radius:10px;background:#fff;overflow:hidden}.iso-doc-box>summary{list-style:none;padding:12px 14px;background:#fafbf9;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;font-weight:800;color:#34483d}.iso-doc-box>summary::-webkit-details-marker{display:none}.iso-doc-count{padding:2px 7px;border-radius:12px;background:#e9f2ed;color:var(--green);font-size:10px}.iso-doc-body{padding:12px}.iso-doc-row{padding:10px 0;border-bottom:1px solid #eee}.iso-doc-row:last-child{border-bottom:0}.iso-doc-title{font-size:11.5px;font-weight:800;color:#26372e;overflow-wrap:anywhere}.iso-doc-meta{font-size:9.5px;color:#7a857e;margin-top:3px}.iso-doc-description{font-size:10px;color:#647169;margin-top:5px}.iso-doc-actions{display:flex;gap:6px;margin-top:7px}.iso-doc-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 8px;border:1px solid #cfd8d2;border-radius:6px;background:#fff;color:var(--green);font:800 9.5px Manrope;text-decoration:none;cursor:pointer}.iso-doc-btn.danger{color:#b91c1c;border-color:#fecaca}.iso-doc-empty{padding:12px;text-align:center;color:#89938d;font-size:10.5px}.iso-doc-form{margin-top:11px;padding-top:11px;border-top:1px dashed #d7ddd8}.iso-doc-grid{display:grid;grid-template-columns:2fr 90px 90px;gap:7px}.iso-doc-form input,.iso-doc-form textarea{width:100%;padding:7px 8px;border:1px solid #d2d7d2;border-radius:6px;font:11px Lato;background:#fff}.iso-doc-form textarea{margin-top:7px;resize:vertical}.iso-doc-file{margin-top:7px}.iso-doc-submit{margin-top:7px;background:var(--green);color:#fff;border:0;border-radius:6px;padding:7px 10px;font:800 10px Manrope;cursor:pointer}@media(max-width:800px){.iso-doc-columns{grid-template-columns:1fr}.iso-doc-suggestions ul{columns:1}.iso-doc-grid{grid-template-columns:1fr 1fr}.iso-doc-grid input:first-child{grid-column:1/-1}}
</style>
<style>.iso-docs-head p,.iso-doc-suggestions strong,.iso-doc-suggestions li,.iso-doc-title,.iso-doc-description,.iso-doc-empty{font-size:13px}.iso-doc-meta,.iso-doc-btn,.iso-doc-submit{font-size:12px}.iso-doc-box>summary{font-size:14px}.iso-doc-form input,.iso-doc-form textarea{font-size:13px}.iso-doc-columns{grid-template-columns:minmax(0,1fr)}.iso-doc-box-client{order:1}.iso-doc-box-template{order:2}.iso-client-doc-table-wrap{overflow-x:auto}.iso-client-doc-table{width:100%;min-width:900px;border-collapse:collapse}.iso-client-doc-table th,.iso-client-doc-table td{padding:11px 10px;border-bottom:1px solid #eceeea;text-align:left;vertical-align:middle}.iso-client-doc-table th{background:#f7f9f7;color:#647169;font:800 11px Manrope;text-transform:uppercase;white-space:nowrap}.iso-client-doc-table td{font-size:13px;color:#536158}.iso-client-doc-table tbody tr:last-child td{border-bottom:0}.iso-client-doc-table .doc-name{min-width:210px;color:#26372e;font-weight:800}.iso-client-doc-table .doc-description{min-width:220px;max-width:360px}.iso-client-doc-table .doc-compact{white-space:nowrap}.iso-client-doc-table .iso-doc-actions{margin-top:0;white-space:nowrap}.iso-client-doc-table .iso-doc-actions form{display:inline-flex}.iso-doc-unavailable{display:inline-flex;align-items:center;gap:4px;padding:5px 8px;border-radius:6px;background:#fff7e6;color:#8a5a00;font:800 11px Manrope}</style>
@endonce

<div class="iso-docs" id="iso-documents-{{ $sectionId }}">
    <div class="iso-docs-head"><div><h3><i class="ti ti-folders"></i> Dokumentacja punktu</h3><p>Dokumenty pozostają przypisane do tego punktu, roku i wersji.</p></div></div>
    @if($suggestedDocuments)
        <div class="iso-doc-suggestions"><strong>Przewidywane dokumenty i dowody:</strong><ul>@foreach($suggestedDocuments as $suggestion)<li>{{ $suggestion }}</li>@endforeach</ul></div>
    @endif
    <div class="iso-doc-columns">
        <details class="iso-doc-box iso-doc-box-template" open><summary>Dokumentacja wzorcowa <span class="iso-doc-count">{{ $sectionTemplates->count() }}</span></summary><div class="iso-doc-body">
            @forelse($sectionTemplates as $document)
                <div class="iso-doc-row"><div class="iso-doc-title">{{ $document->title }}</div><div class="iso-doc-meta">Rok {{ $document->document_year ?? '—' }} · wersja {{ $document->version_number }} · {{ $document->formattedSize() }} · {{ $document->uploader?->name ?? 'System' }}</div>@if($document->description)<div class="iso-doc-description">{{ $document->description }}</div>@endif
                    <div class="iso-doc-actions">
                        <a class="iso-doc-btn" href="{{ ($clientView ?? false) ? route('client.audits.iso-documents.templates.download', [$audit, $document]) : route('audit-types.iso-documents.download', [$auditType ?? $auditTypes->firstWhere('slug', 'iso50001'), $document]) }}"><i class="ti ti-download"></i> Pobierz</a>
                        @if(!$isAuditContext && $canUpload)<form method="POST" action="{{ route('audit-types.iso-documents.destroy', [$auditType, $document]) }}">@csrf @method('DELETE')<button class="iso-doc-btn danger" onclick="return confirm('Usunąć dokument wzorcowy?')"><i class="ti ti-trash"></i> Usuń</button></form>@endif
                    </div>
                </div>
            @empty<div class="iso-doc-empty">Nie dodano jeszcze dokumentów wzorcowych.</div>@endforelse
            @if(!$isAuditContext && $canUpload)
                <form class="iso-doc-form" method="POST" enctype="multipart/form-data" action="{{ route('audit-types.iso-documents.store', $auditType) }}">@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}">@include('audit-types.partials.iso50001-document-form-fields')<button class="iso-doc-submit"><i class="ti ti-upload"></i> Dodaj dokumenty wzorcowe</button></form>
            @endif
        </div></details>
        @if($isAuditContext)
        <details class="iso-doc-box iso-doc-box-client" open><summary>Dokumentacja klienta <span class="iso-doc-count">{{ $sectionClientDocuments->count() }}</span></summary><div class="iso-doc-body">
            @if($sectionClientDocuments->isNotEmpty())
            <div class="iso-client-doc-table-wrap"><table class="iso-client-doc-table"><thead><tr><th>Nazwa dokumentu</th><th>Opis</th><th>Rok</th><th>Wersja</th><th>Rozmiar</th><th>Osoba</th><th>Data utworzenia</th><th>Akcje</th></tr></thead><tbody>
                @foreach($sectionClientDocuments as $document)<tr><td class="doc-name">{{ $document->title }}</td><td class="doc-description">{{ $document->description ?: '—' }}</td><td class="doc-compact">{{ $document->document_year ?? '—' }}</td><td class="doc-compact">{{ $document->version_number }}</td><td class="doc-compact">{{ $document->formattedSize() }}</td><td class="doc-compact">{{ $document->uploader?->name ?? 'System' }}</td><td class="doc-compact">{{ $document->created_at->format('d.m.Y H:i') }}</td><td><div class="iso-doc-actions">@if($document->isAvailable())<a class="iso-doc-btn" href="{{ $clientView ? route('client.audits.iso-documents.download', [$audit, $document]) : route('audits.iso-documents.download', [$audit, $document]) }}"><i class="ti ti-download"></i> Pobierz</a>@else<span class="iso-doc-unavailable" title="Plik został utracony przed uruchomieniem trwałej kopii. Wygeneruj dokument ponownie z ankiety."><i class="ti ti-alert-triangle"></i> Wygeneruj ponownie</span>@endif @if($canDeleteClientDocument)<form method="POST" action="{{ route(($clientView ?? false) ? 'client.audits.iso-documents.destroy' : 'audits.iso-documents.destroy', [$audit, $document]) }}">@csrf @method('DELETE')<button class="iso-doc-btn danger" onclick="return confirm('Usunąć tę wersję dokumentu?')"><i class="ti ti-trash"></i> Usuń</button></form>@endif</div></td></tr>@endforeach
            </tbody></table></div>
            @else<div class="iso-doc-empty">Nie dodano jeszcze dokumentacji klienta.</div>@endif
            @if($canUpload)
                <form class="iso-doc-form" method="POST" enctype="multipart/form-data" action="{{ route('audits.iso-documents.store', $audit) }}">@csrf<input type="hidden" name="section_id" value="{{ $sectionId }}">@include('audit-types.partials.iso50001-document-form-fields')<button class="iso-doc-submit"><i class="ti ti-upload"></i> Dodaj dokumentację klienta</button></form>
            @endif
        </div></details>
        @endif
    </div>
</div>
