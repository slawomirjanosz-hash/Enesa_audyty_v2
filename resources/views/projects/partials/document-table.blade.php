<div style="overflow-x:auto">
    <table class="document-sortable-table">
        <thead><tr>
            <th><button type="button" class="document-sort-button" data-document-sort="name">Plik</button></th>
            <th><button type="button" class="document-sort-button" data-document-sort="size" data-sort-type="number">Rozmiar</button></th>
            <th><button type="button" class="document-sort-button" data-document-sort="uploader">Dodał</button></th>
            <th><button type="button" class="document-sort-button" data-document-sort="date" data-sort-type="number">Data</button></th>
            <th></th>
        </tr></thead>
        <tbody>
        @foreach($documents as $document)
            @php($uploaderName = $document->uploader?->name ?? $fallbackUploader)
            <tr data-document-name="{{$document->original_filename}}" data-document-size="{{$document->size}}" data-document-uploader="{{$uploaderName}}" data-document-date="{{$document->created_at->timestamp}}">
                <td><a href="{{route('projects.documents.download',[$project,$document])}}"><strong>{{$document->original_filename}}</strong></a></td>
                <td>{{$document->formattedSize()}}</td>
                <td>{{$uploaderName}}</td>
                <td>{{$document->created_at->format('d.m.Y H:i')}}</td>
                <td>@if($canEdit)<form method="POST" action="{{route('projects.documents.destroy',[$project,$document])}}">@csrf @method('DELETE')<button class="btn btn-red">Usuń</button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
