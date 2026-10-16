@extends('layouts.app')
@section('page-title', 'Archiwum')
@section('content')
<style>
.archive-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;overflow:hidden;margin-bottom:20px}.archive-card h2{margin:0;padding:18px 22px;font-size:16px;border-bottom:1px solid #f0ede6}.archive-scroll{overflow-x:auto}.archive-table{width:100%;border-collapse:collapse}.archive-table th,.archive-table td{padding:12px 16px;text-align:left;border-bottom:1px solid #f0ede6;font-size:13px}.archive-table th{color:#777;background:#fafaf6;font-size:11px;text-transform:uppercase}.archive-table tr:last-child td{border:0}.empty{padding:28px;color:#888;font-size:14px}.archive-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.archive-actions form{margin:0}.restore,.archive-delete{display:inline-block;border:1px solid #b9d8c4;color:var(--green);background:#f0fbf3;border-radius:7px;padding:7px 10px;cursor:pointer;font-weight:700;text-decoration:none}.archive-delete{border-color:#fecaca;background:#fef2f2;color:#991b1b}.archive-flash{padding:14px 18px;border-radius:8px;margin-bottom:18px;background:#ecfdf5;color:#166534}.archive-flash.error{background:#fef2f2;color:#991b1b}
</style>
<h1 style="margin:0 0 8px">Archiwum</h1>
<p style="margin:0 0 24px;color:#6b7a70">Zarchiwizowane firmy i konta użytkowników możesz przywrócić lub trwale usunąć. Usunięcie konta zwalnia jego adres e-mail. Trwałego usunięcia nie można cofnąć.</p>
@if(session('success'))<div class="archive-flash" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="archive-flash error" role="alert">{{$errors->first()}}</div>@endif
@if(session('error'))<div class="archive-flash error" role="alert">{{session('error')}}</div>@endif
<section class="archive-card"><h2>Firmy ({{$archivedCompanies->count()}})</h2>
@if($archivedCompanies->isEmpty())<div class="empty">Brak zarchiwizowanych firm.</div>
@else
<p style="padding:0 22px;color:#6b7a70;font-size:13px">Trwałe usunięcie firmy obejmuje jej historię: audyty, dokumenty, oferty, projekty i powiązane pliki. Konta użytkowników pozostają w systemie i można je usunąć oddzielnie.</p>
<div class="archive-scroll"><table class="archive-table"><thead><tr><th>Firma</th><th>NIP</th><th>E-mail</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@foreach($archivedCompanies as $company)
<tr><td>{{$company->name}}</td><td data-sort-value="{{$company->nip}}">{{$company->nip ?: '—'}}</td><td>{{$company->email ?: '—'}}</td><td><div class="archive-actions">
<a class="restore" href="{{route('companies.show',$company)}}">Otwórz kartę</a>
@can('delete',$company)
@if(!$company->is_owner && (auth()->user()->hasAnyRole(['admin','superadmin']) || auth()->user()->can('system.full_access')))<form method="POST" action="{{route('settings.archive.companies.destroy',$company)}}" onsubmit="return confirm('Trwale usunąć firmę wraz z jej audytami, dokumentami, ofertami, projektami, przeglądami i powiązanymi plikami? Nie można tego cofnąć. Konta użytkowników zostaną zachowane.')">@csrf @method('DELETE')<input type="hidden" name="confirm_delete" value="1"><button class="archive-delete">Usuń trwale</button></form>@endif
@endcan
</div></td></tr>
@endforeach
</tbody></table></div>
@endif
</section>
@include('settings.archive.users', ['users'=>$archivedStaff,'title'=>'Pracownicy','showCompany'=>false])
@include('settings.archive.users', ['users'=>$archivedClients,'title'=>'Użytkownicy klientów','showCompany'=>true])
@endsection
