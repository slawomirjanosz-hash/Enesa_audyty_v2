@php($linkPrefix = $owner instanceof \App\Models\Audit ? 'audits' : 'projects')
@once
<style>.workspace-link-form{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:16px}.workspace-link-form label{display:flex;flex-direction:column;gap:5px;flex:1;min-width:180px}.workspace-link-form input{padding:9px;border:1px solid #ccc;border-radius:7px;width:100%;box-sizing:border-box}</style>
@endonce
<div class="aw-card card">
<h2><i class="ti ti-link"></i> Dyski zewnętrzne</h2>
<p>Pliki pozostają na zewnętrznym dysku. Link otworzy nową kartę — zaloguj się u dostawcy i korzystaj z nadanych tam uprawnień. Nie wpisuj tu haseł ani kluczy dostępu.</p>
@if($canManageLinks)<p><small>Dodany link będzie widoczny dla osób mających dostęp do dokumentów tego {{$linkPrefix === 'audits' ? 'audytu, również klienta' : 'projektu'}}.</small></p>@endif
@if($canManageLinks)
<form class="workspace-link-form" method="POST" action="{{route($linkPrefix.'.document-links.store',$owner)}}">@csrf
<label>Nazwa dysku / folderu<input name="name" maxlength="160" placeholder="np. Dokumentacja klienta – OneDrive" required></label>
<label>Adres HTTPS<input type="url" name="url" maxlength="2048" placeholder="https://…" required></label>
<button class="aw-btn btn">Dodaj link</button>
</form>
@endif
<div style="overflow:auto"><table class="aw-table"><thead><tr><th>Nazwa</th><th>Adres dysku</th><th>Dodano</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($owner->documentLinks as $link)
<tr><td>{{$link->name}}</td><td style="overflow-wrap:anywhere">{{$link->url}}</td><td data-sort-value="{{$link->created_at->toIso8601String()}}">{{$link->created_at->format('d.m.Y H:i')}}</td><td>
<a class="aw-btn btn" href="{{$link->url}}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Otwórz dysk ↗</a>
@if($canManageLinks)<form style="display:inline" method="POST" action="{{route($linkPrefix.'.document-links.destroy',[$owner,$link])}}" onsubmit="return confirm('Usunąć tylko link z aplikacji? Pliki na dysku pozostaną bez zmian.')">@csrf @method('DELETE')<button class="aw-btn red btn btn-red">Usuń link</button></form>@endif
</td></tr>
@empty<tr><td colspan="4">Brak dodanych linków.</td></tr>@endforelse
</tbody></table></div>
</div>
