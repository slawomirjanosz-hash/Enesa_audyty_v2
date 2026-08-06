@extends('layouts.app')

@section('page-title', 'Archiwum')

@section('content')
<style>
    .archive-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; overflow:hidden; margin-bottom:20px; }
    .archive-card h2 { margin:0; padding:18px 22px; font-size:16px; border-bottom:1px solid #F0EDE6; }
    .archive-table { width:100%; border-collapse:collapse; } .archive-table th,.archive-table td { padding:12px 16px; text-align:left; border-bottom:1px solid #F0EDE6; font-size:13px; }
    .archive-table th { color:#777; background:#FAFAF6; font-size:11px; text-transform:uppercase; } .archive-table tr:last-child td { border:0; }
    .empty { padding:28px; color:#888; font-size:14px; } .restore { border:1px solid #b9d8c4; color:var(--green); background:#f0fbf3; border-radius:7px; padding:7px 10px; cursor:pointer; font-weight:700; }
</style>
<h1 style="margin:0 0 8px;">Archiwum</h1>
<p style="margin:0 0 24px;color:#6b7a70;">Zarchiwizowane firmy oraz konta użytkowników. Tutaj można je przywrócić.</p>

<section class="archive-card"><h2>Firmy ({{ $archivedCompanies->count() }})</h2>
@if($archivedCompanies->isEmpty()) <div class="empty">Brak zarchiwizowanych firm.</div> @else <table class="archive-table"><thead><tr><th>Firma</th><th>NIP</th><th></th></tr></thead><tbody>@foreach($archivedCompanies as $company)<tr><td><strong>{{ $company->name }}</strong></td><td>{{ $company->nip ?: '—' }}</td><td><a class="restore" href="{{ route('companies.show', $company) }}">Otwórz kartę</a></td></tr>@endforeach</tbody></table>@endif
</section>

<section class="archive-card"><h2>Pracownicy ({{ $archivedStaff->count() }})</h2>
@if($archivedStaff->isEmpty()) <div class="empty">Brak zarchiwizowanych pracowników.</div> @else <table class="archive-table"><thead><tr><th>Użytkownik</th><th>Rola</th><th>Data archiwizacji</th><th></th></tr></thead><tbody>@foreach($archivedStaff as $user)<tr><td>{{ $user->name }}<br><span style="color:#888">{{ $user->email }}</span></td><td>{{ str($user->roles->first()?->name ?? '')->replace('_', ' ')->title() }}</td><td>{{ $user->deleted_at?->format('d.m.Y') }}</td><td><form method="POST" action="{{ route('settings.users.restore', $user) }}">@csrf<button class="restore">Przywróć</button></form></td></tr>@endforeach</tbody></table>@endif
</section>

<section class="archive-card"><h2>Użytkownicy klientów ({{ $archivedClients->count() }})</h2>
@if($archivedClients->isEmpty()) <div class="empty">Brak zarchiwizowanych użytkowników klientów.</div> @else <table class="archive-table"><thead><tr><th>Użytkownik</th><th>Firma</th><th>Data archiwizacji</th><th></th></tr></thead><tbody>@foreach($archivedClients as $user)<tr><td>{{ $user->name }}<br><span style="color:#888">{{ $user->email }}</span></td><td>{{ $user->companies->pluck('name')->join(', ') ?: '—' }}</td><td>{{ $user->deleted_at?->format('d.m.Y') }}</td><td><form method="POST" action="{{ route('settings.users.restore', $user) }}">@csrf<button class="restore">Przywróć</button></form></td></tr>@endforeach</tbody></table>@endif
</section>
@endsection
