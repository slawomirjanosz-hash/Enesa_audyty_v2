<tr><td class="doc-name">{{ $document->title }}</td><td class="doc-description">{{ $document->description ?: '—' }}</td><td class="doc-compact">{{ $document->document_year ?? '—' }}</td><td class="doc-compact">{{ $document->version_number }}</td><td class="doc-compact" data-sort-value="{{$document->size}}">{{ $document->formattedSize() }}</td><td class="doc-compact">{{ $document->uploader?->name ?? 'System' }}</td><td class="doc-compact" data-sort-value="{{$document->created_at->toIso8601String()}}">{{ $document->created_at->format('d.m.Y H:i') }}</td>
<td><div class="iso-doc-actions" style="flex-wrap:wrap">
@if($document->isAvailable())
<a class="iso-doc-btn" href="{{ route($clientView ? 'client.audits.iso-documents.download' : 'audits.iso-documents.download', [$audit, $document]) }}"><i class="ti ti-download"></i> Pobierz</a>
@if($clientView ? auth()->user()->hasRole('client_admin') : $canUpload)
<form method="POST" action="{{route($clientView ? 'client.audits.iso-documents.copy' : 'audits.iso-documents.copy',[$audit,$document])}}">@csrf<button class="iso-doc-btn" type="submit"><i class="ti ti-copy"></i> Kopiuj do Dokumentów</button></form>
@endif
@else<span class="iso-doc-unavailable" title="Plik jest niedostępny. Wygeneruj dokument ponownie z ankiety."><i class="ti ti-alert-triangle"></i> Wygeneruj ponownie</span>@endif
@if($canDeleteClientDocument)<form method="POST" action="{{ route($clientView ? 'client.audits.iso-documents.destroy' : 'audits.iso-documents.destroy', [$audit, $document]) }}">@csrf @method('DELETE')<button class="iso-doc-btn danger" onclick="return confirm('Usunąć tę wersję dokumentu?')"><i class="ti ti-trash"></i> Usuń</button></form>@endif
</div></td></tr>
