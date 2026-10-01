<section id="aw-documents" class="aw-pane">
@include('documents.quota')
@include('documents.external-links',['owner'=>$audit,'canManageLinks'=>$canManage])
@if($canManage)
<div class="aw-card"><h2>Foldery dokumentów</h2>
<form method="POST" action="{{route('audits.document-folders.store',$audit)}}">@csrf
<input class="aw-input" name="name" maxlength="120" placeholder="Nazwa folderu" required> <button class="aw-btn">Dodaj folder</button>
</form></div>
<div class="aw-card"><h2>Dodaj dokumenty audytu</h2>
<form method="POST" enctype="multipart/form-data" action="{{route('audits.documents.store',$audit)}}">@csrf
<input type="file" name="files[]" multiple required>
<select class="aw-input" name="audit_document_folder_id"><option value="">Bez folderu</option>@foreach($audit->documentFolders as $folder)<option value="{{$folder->id}}">{{$folder->name}}</option>@endforeach</select>
<button class="aw-btn">Wgraj dokumenty</button>
<p>Jednorazowo do 20 plików, maks. 20 MB na plik.</p>
</form></div>
@endif
@foreach($audit->documentFolders as $folder)
<div class="aw-card"><div class="folder-head"><h2>📁 {{$folder->name}}</h2>
@if($canManage)<form method="POST" action="{{route('audits.document-folders.destroy',[$audit,$folder])}}" onsubmit="return confirm('Usunąć pusty folder?')">@csrf @method('DELETE')<button class="aw-btn red" @disabled($folder->documents->isNotEmpty())>Usuń folder</button></form>@endif</div>
@include('audits.partials.document-table',['documents'=>$folder->documents])
</div>
@endforeach
<div class="aw-card"><h2>Dokumenty bez folderu</h2>
@include('audits.partials.document-table',['documents'=>$audit->documents->whereNull('audit_document_folder_id')])
</div>
</section>
