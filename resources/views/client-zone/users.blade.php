@extends('layouts.client-zone')

@section('page-title', 'Użytkownicy')

@section('content')
<div style="background:#fff;border-radius:12px;padding:40px;box-shadow:0 2px 8px rgba(0,0,0,.07);">
    <h2 style="font-size:20px;font-weight:700;color:var(--green);margin-bottom:20px;font-family:'Manrope',sans-serif;">
        <i class="ti ti-users" style="vertical-align:middle;margin-right:6px;"></i>Użytkownicy — {{ $company->name }}
    </h2>

    @if($users->isEmpty())
        <div style="text-align:center;padding:40px 20px;color:#6b7a72;">
            <i class="ti ti-user-off" style="font-size:48px;color:#c8d5cf;display:block;margin-bottom:12px;"></i>
            <p style="font-size:14px;">Brak użytkowników przypisanych do tej firmy.</p>
        </div>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid #e8ede9;">
                    <th style="text-align:left;padding:10px 12px;color:#6b7a72;font-weight:600;">Imię i nazwisko</th>
                    <th style="text-align:left;padding:10px 12px;color:#6b7a72;font-weight:600;">E-mail</th>
                    <th style="text-align:left;padding:10px 12px;color:#6b7a72;font-weight:600;">Rola</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr style="border-bottom:1px solid #f0f3f1;">
                        <td style="padding:10px 12px;color:#1e1e1e;font-weight:500;">{{ $user->name }}</td>
                        <td style="padding:10px 12px;color:#4a5568;">{{ $user->email }}</td>
                        <td style="padding:10px 12px;">
                            @foreach($user->roles as $role)
                                <span style="background:#E8F4EE;color:var(--green);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">
                                    {{ $role->name }}
                                </span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
