<div style="overflow:auto"><table class="aw-table"><thead><tr><th>Plik</th><th>Rozmiar</th><th>Dodał</th><th>Data</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($documents as $document)
<tr><td><a href="{{route(($clientView?'client.':'').'audits.documents.download',[$audit,$document])}}">{{$document->original_filename}}</a></td>
<td data-sort-value="{{$document->size}}">{{$document->formattedSize()}}</td><td>{{$document->uploader?->name??'System'}}</td><td data-sort-value="{{$document->created_at->toIso8601String()}}">{{$document->created_at->format('d.m.Y H:i')}}</td>
<td>@if($canManage)
<form method="POST" action="{{route('audits.documents.move',[$audit,$document])}}">@csrf @method('PATCH')
<select name="audit_document_folder_id" aria-label="Folder dla {{$document->original_filename}}"><option value="">Bez folderu</option>@foreach($audit->documentFolders as $destination)<option value="{{$destination->id}}" @selected($document->audit_document_folder_id===$destination->id)>{{$destination->name}}</option>@endforeach</select><button class="aw-btn soft">Przenieś</button>
</form>
<form method="POST" action="{{route('audits.documents.destroy',[$audit,$document])}}" onsubmit="return confirm('Usunąć plik?')">@csrf @method('DELETE')<button class="aw-btn red">Usuń</button></form>
@endif</td></tr>
@empty<tr><td colspan="5">Brak dokumentów.</td></tr>@endforelse
</tbody></table></div>
