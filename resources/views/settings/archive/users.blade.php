<section class="archive-card"><h2>{{$title}} ({{$users->count()}})</h2>
@if($users->isEmpty())<div class="empty">Brak zarchiwizowanych użytkowników.</div>
@else<div class="archive-scroll"><table class="archive-table"><thead><tr><th>Użytkownik</th><th>E-mail</th><th>{{$showCompany?'Firma':'Rola'}}</th><th>Data archiwizacji</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@foreach($users as $user)
<tr><td>{{$user->name}}</td><td>{{$user->email}}</td><td>@if($showCompany){{$user->allCompanies->pluck('name')->join(', ') ?: '—'}}@else{{$user->roles->first()?->display_name ?: \App\Support\RolePermissionCatalog::roleLabel($user->roles->first()?->name ?? '—')}}@endif</td><td data-sort-value="{{$user->deleted_at?->format('Y-m-d H:i:s')}}">{{$user->deleted_at?->format('d.m.Y')}}</td><td><div class="archive-actions">
@if(auth()->user()->hasRole('superadmin') || auth()->user()->can('settings.users.manage'))<form method="POST" action="{{route('settings.users.restore',$user)}}">@csrf<button class="restore">Przywróć</button></form>@endif
@if(in_array($user->id,$deletableUserIds,true))<form method="POST" action="{{route('settings.users.forceDestroy',$user)}}" onsubmit="return confirm('Trwale usunąć konto, jego dane HR (delegacje, urlopy, obecności i prywatne samochody) oraz przypisania do firm? Nie można tego cofnąć. Firmy i pozostałe konta zostaną zachowane, a adres e-mail będzie dostępny do ponownego użycia.')">@csrf @method('DELETE')<input type="hidden" name="confirm_delete" value="1"><button class="archive-delete">Usuń trwale</button></form>@endif
</div></td></tr>
@endforeach
</tbody></table></div>@endif
</section>
