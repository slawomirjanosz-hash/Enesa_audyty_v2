<div style="overflow-x:auto">
    <table class="project-documents-table">
        <thead><tr>
            <th data-document-sort="name">Plik</th>
            <th data-document-sort="size">Rozmiar</th>
            <th data-document-sort="uploader">Dodał</th>
            <th data-document-sort="date">Data</th>
            <th data-sortable="false" style="width:60px;text-align:center">Akcje</th>
        </tr></thead>
        <tbody>
        @foreach($documents as $document)
            @php($uploaderName = $document->uploader?->name ?? $fallbackUploader)
            <tr data-document-name="{{$document->original_filename}}" data-document-size="{{$document->size}}" data-document-uploader="{{$uploaderName}}" data-document-date="{{$document->created_at->timestamp}}">
                <td><a href="{{route('projects.documents.download',[$project,$document])}}"><strong>{{$document->original_filename}}</strong></a></td>
                <td data-sort-value="{{$document->size}}">{{$document->formattedSize()}}</td>
                <td>{{$uploaderName}}</td>
                <td data-sort-value="{{$document->created_at->toIso8601String()}}">{{$document->created_at->format('d.m.Y H:i')}}</td>
                <td style="text-align:center">@if($canEdit)<form method="POST" action="{{route('projects.documents.destroy',[$project,$document])}}" onsubmit="return confirm('Usunąć dokument?')">@csrf @method('DELETE')<button class="workspace-delete" title="Usuń dokument" aria-label="Usuń dokument: {{$document->original_filename}}"><i class="ti ti-trash" aria-hidden="true"></i></button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
