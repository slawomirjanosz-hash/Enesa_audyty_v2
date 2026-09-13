@if(auth()->user()->hasAnyRole(['admin','superadmin']))
@php($securityUsers = $allUsers ?? \App\Models\User::with('roles')->orderBy('name')->get())
@php($securityUsage = app(\App\Services\DocumentQuotaService::class)->usedMany($securityUsers->modelKeys()))
<style>.account-security th,.account-security td{text-align:left;padding:10px;vertical-align:middle}.account-security button{border:1px solid #d8dfdb;background:var(--green,#1a4d3a);color:white;border-radius:6px;padding:7px 10px;cursor:pointer;white-space:nowrap}.account-security input{border:1px solid #ccc;border-radius:6px;padding:7px;box-sizing:border-box}.account-security th{background:#f6f7f5;font-size:12px}.account-security table{min-width:760px}</style>
<section class="account-security" style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;margin:18px 0">
    <h3 style="margin:0 0 10px">Bezpieczeństwo kont i limity dokumentów</h3>
    @if(auth()->user()->hasRole('superadmin'))<p style="padding:10px;background:#f5f5f0;border-radius:6px;font-size:13px">Authenticator superadmina: <strong>{{config('security.totp.enabled') ? 'Wymagany — kody z aplikacji na telefonie' : 'Wyłączony'}}</strong><br>SMS superadmina: <strong>{{config('security.totp.enabled') ? 'Nieużywany — zastąpiony przez Authenticator' : (config('security.sms.enabled') ? 'Włączony' : 'Nieaktywny')}}</strong><br>Weryfikacja antybotowa: <strong>{{config('security.turnstile.enabled') ? 'Włączona warunkowo' : 'Nieaktywna — wymaga kluczy Turnstile w Railway'}}</strong></p>@endif
    <p style="font-size:13px">Blokada po 2 miesiącach bez aktywności. Blokada za 10 błędnych haseł w ciągu 15 minut wygasa po 15 minutach lub po odblokowaniu przez administratora. Limit domyślny: 200 MB.</p>
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr><th style="text-align:left">Użytkownik</th><th>Stan konta</th><th>Ostatnia aktywność</th><th>Miejsce / limit</th><th>Akcje administratora</th></tr></thead>
        <tbody>@foreach($securityUsers as $securityUser)
        @php($canSecure = !$securityUser->hasRole('superadmin') || auth()->user()->hasRole('superadmin'))
        <tr style="border-top:1px solid #eee">
            <td style="padding:10px">{{$securityUser->name}}<br><small>{{$securityUser->email}}</small></td>
            <td>{{$securityUser->security_block_reason === 'inactivity' ? 'Zablokowane — 2 miesiące bez aktywności' : (!$securityUser->is_active ? 'Nieaktywne' : ($securityUser->login_locked_until?->isFuture() ? 'Zablokowane — błędne logowania, do '.$securityUser->login_locked_until->format('d.m H:i') : 'Aktywne'))}}</td>
            <td>{{$securityUser->security_activity_at?->format('d.m.Y H:i') ?? 'Brak'}}</td>
            <td>{{\App\Models\Document::formatBytes((int)$securityUsage->get($securityUser->id,0))}} / {{\App\Models\Document::formatBytes((int)$securityUser->document_limit_bytes)}}</td>
            <td style="padding:10px">@if($canSecure)
                @if(!$securityUser->is_active || $securityUser->security_block_reason || $securityUser->login_locked_until?->isFuture())<form method="POST" action="{{route('settings.users.unlock',$securityUser)}}">@csrf<button type="submit">Odblokuj konto</button></form>@endif
                <form method="POST" action="{{route('settings.users.document-quota',$securityUser)}}" style="display:flex;gap:6px;align-items:center;margin-top:6px">@csrf @method('PATCH')<input aria-label="Limit MB dla {{$securityUser->name}}" type="number" name="limit_mb" min="1" max="1048576" value="{{(int)($securityUser->document_limit_bytes/1048576)}}" required style="width:90px"> MB <button type="submit">Zapisz limit</button></form>
            @endif</td>
        </tr>@endforeach</tbody>
    </table></div>
</section>
@endif
