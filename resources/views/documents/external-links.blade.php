@php($linkPrefix = $owner instanceof \App\Models\Audit ? 'audits' : 'projects')
@once
<style>
.workspace-links .workspace-help{font-size:12px;line-height:1.5;color:#66736b;margin:0 0 6px}
.workspace-link-form{display:grid;grid-template-columns:minmax(160px,260px) minmax(200px,1fr) 100px;gap:10px;align-items:end;margin:14px 0 16px}
.workspace-link-form label{display:flex;flex-direction:column;gap:5px;min-width:0;font-size:12px;font-weight:600}
.workspace-link-form input{height:36px;padding:8px 10px;border:1px solid #d8d3c8;border-radius:7px;width:100%;box-sizing:border-box;font-size:12px}
.workspace-link-form .btn{height:36px;white-space:nowrap;font-size:12px}
.workspace-links-table{table-layout:fixed;width:100%}.workspace-links-table .link-name{width:24%}.workspace-links-table .link-date{width:145px}.workspace-links-table .link-actions{width:100px;text-align:center}
.workspace-links-table a{color:var(--green,#1a4d3a);text-decoration:none}.workspace-links-table a:hover{text-decoration:underline}.workspace-links-table .drive-url{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}.workspace-links-table .drive-name{font-weight:600;overflow-wrap:anywhere}.workspace-links-table td{vertical-align:middle}.workspace-links-table .link-date{white-space:nowrap}
.workspace-delete{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;padding:0;border:1px solid #fecaca;border-radius:6px;background:#fff1f2;color:#b91c1c;cursor:pointer;font-size:16px}.workspace-delete:hover{background:#fee2e2}.workspace-delete:focus-visible{outline:2px solid #b91c1c;outline-offset:2px}.workspace-links-table form{display:flex;justify-content:center;margin:0}
#pane-documents form[enctype="multipart/form-data"]>div{display:grid!important;grid-template-columns:minmax(200px,1fr) minmax(180px,280px) auto;align-items:end!important;gap:12px!important}
#pane-documents form[enctype="multipart/form-data"] .field{min-width:0;margin:0}#pane-documents form[enctype="multipart/form-data"] input,#pane-documents form[enctype="multipart/form-data"] select{width:100%;box-sizing:border-box;min-height:38px;font-size:12px}#pane-documents form[enctype="multipart/form-data"] .btn{height:38px;white-space:nowrap;font-size:12px}#pane-documents form[enctype="multipart/form-data"] small{display:block;margin-top:10px;font-size:12px;color:#66736b}
@media(max-width:700px){.workspace-link-form,#pane-documents form[enctype="multipart/form-data"]>div{grid-template-columns:1fr}.workspace-link-form .btn{justify-self:end}.workspace-links-table{min-width:570px}}
</style>
@endonce
<div class="aw-card card workspace-links">
<h2><i class="ti ti-link"></i> Dyski zewnętrzne</h2>
<p class="workspace-help">Pliki pozostają na zewnętrznym dysku. Kliknij nazwę lub adres, aby otworzyć dysk w nowej karcie. Nie wpisuj tu haseł ani kluczy dostępu.</p>
@if($canManageLinks)<p class="workspace-help">Link będzie widoczny dla osób mających dostęp do dokumentów tego {{$linkPrefix === 'audits' ? 'audytu, również klienta' : 'projektu'}}.</p>@endif
@if($canManageLinks)
<form class="workspace-link-form" method="POST" action="{{route($linkPrefix.'.document-links.store',$owner)}}">@csrf
<label>Nazwa dysku / folderu<input name="name" maxlength="160" placeholder="np. Dokumentacja klienta – OneDrive" required></label>
<label>Adres HTTPS<input type="url" name="url" maxlength="2048" placeholder="https://…" required></label>
<button class="aw-btn btn">Dodaj link</button>
</form>
@endif
<div style="overflow:auto"><table class="aw-table workspace-links-table"><thead><tr><th class="link-name">Nazwa</th><th>Adres dysku</th><th class="link-date">Dodano</th><th class="link-actions" data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($owner->documentLinks as $link)
<tr><td><a class="drive-name" href="{{$link->url}}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{$link->name}}</a></td><td><a class="drive-url" href="{{$link->url}}" title="{{$link->url}}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{$link->url}}</a></td><td class="link-date" data-sort-value="{{$link->created_at->toIso8601String()}}">{{$link->created_at->format('d.m.Y H:i')}}</td><td class="link-actions">
@if($canManageLinks)<form method="POST" action="{{route($linkPrefix.'.document-links.destroy',[$owner,$link])}}" onsubmit="return confirm('Usunąć tylko link z aplikacji? Pliki na dysku pozostaną bez zmian.')">@csrf @method('DELETE')<button class="workspace-delete" title="Usuń link" aria-label="Usuń link: {{$link->name}}"><i class="ti ti-trash" aria-hidden="true"></i></button></form>@endif
</td></tr>
@empty<tr><td colspan="4">Brak dodanych linków.</td></tr>@endforelse
</tbody></table></div>
</div>
