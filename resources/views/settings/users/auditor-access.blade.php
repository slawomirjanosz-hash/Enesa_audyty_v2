@extends('layouts.app')

@section('page-title', 'Uprawnienia audytora')

@section('content')
@php
    $flags = [
        'can_view_dashboard' => 'Dashboard',
        'can_view_audits' => 'Audyty',
        'can_view_offer_requests' => 'Zapytania ofertowe',
        'can_view_offers' => 'Oferty',
        'can_view_offer_prices' => 'Ceny ofert',
        'can_view_documents' => 'Dokumenty',
        'can_view_chat' => 'Czat',
    ];
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;gap:16px;">
    <div>
        <h1 style="margin:0;font-size:22px;color:var(--green);">Uprawnienia audytora</h1>
        <div style="margin-top:6px;color:#555;">{{ $user->name }} · {{ $user->email }} · Audytor</div>
    </div>
    <a href="{{ route('settings.users.index') }}" style="color:var(--green);text-decoration:none;font-weight:700;">Wróć do użytkowników</a>
</div>

@if(session('success'))
    <div style="margin-bottom:16px;padding:12px 16px;border:1px solid #86efac;background:#f0fdf4;color:#166534;border-radius:8px;">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="margin-bottom:16px;padding:12px 16px;border:1px solid #fca5a5;background:#fef2f2;color:#b91c1c;border-radius:8px;">{{ session('error') }}</div>
@endif

<section style="background:#fff;border:1px solid #E5E1D8;border-radius:8px;overflow:hidden;margin-bottom:20px;">
    <div style="padding:16px 20px;border-bottom:1px solid #E5E1D8;font-weight:700;">Przydzielone firmy</div>
    @if($accesses->isEmpty())
        <div style="padding:24px;color:#777;">Nie przydzielono jeszcze żadnej firmy.</div>
    @else
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#FAFAF6;text-align:left;font-size:12px;color:#666;"><th style="padding:12px 20px;">Firma</th><th style="padding:12px 20px;">Zakres</th><th style="padding:12px 20px;text-align:right;">Akcje</th></tr></thead>
            <tbody>
            @foreach($accesses as $access)
                <tr style="border-top:1px solid #F0EDE6;">
                    <td style="padding:14px 20px;font-weight:700;vertical-align:top;">{{ $access->company->name }}</td>
                    <td style="padding:14px 20px;">
                        <form method="POST" action="{{ route('settings.users.auditor-access.update', [$user, $access]) }}" style="display:flex;flex-wrap:wrap;gap:12px;">
                            @csrf
                            @method('PATCH')
                            @foreach($flags as $field => $label)
                                <label style="font-size:12px;"><input type="checkbox" name="{{ $field }}" value="1" @checked($access->$field)> {{ $label }}</label>
                            @endforeach
                            <button type="submit" style="border:1px solid var(--green);background:#fff;color:var(--green);border-radius:6px;padding:5px 10px;font-weight:700;cursor:pointer;">Edytuj</button>
                        </form>
                    </td>
                    <td style="padding:14px 20px;text-align:right;vertical-align:top;">
                        <form method="POST" action="{{ route('settings.users.auditor-access.destroy', [$user, $access]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="border:1px solid #dc2626;background:#fff;color:#b91c1c;border-radius:6px;padding:5px 10px;font-weight:700;cursor:pointer;">Usuń</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>

<section style="background:#fff;border:1px solid #E5E1D8;border-radius:8px;padding:20px;">
    <h2 style="margin:0 0 16px;font-size:16px;color:var(--green);">Dodaj firmę</h2>
    <form method="POST" action="{{ route('settings.users.auditor-access.store', $user) }}" style="display:grid;gap:14px;">
        @csrf
        <select name="company_id" required style="max-width:520px;padding:9px 12px;border:1px solid #D0CCC0;border-radius:6px;">
            <option value="">Wybierz firmę</option>
            @foreach($availableCompanies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        <div style="display:flex;flex-wrap:wrap;gap:14px;">
            @foreach($flags as $field => $label)
                <label style="font-size:13px;"><input type="checkbox" name="{{ $field }}" value="1"> {{ $label }}</label>
            @endforeach
        </div>
        <button type="submit" style="width:max-content;background:var(--green);color:#fff;border:0;border-radius:6px;padding:9px 14px;font-weight:700;cursor:pointer;">Dodaj firmę</button>
    </form>
</section>

<section style="background:#fff;border:1px solid #E5E1D8;border-radius:8px;padding:20px;margin-top:20px;">
    <h2 style="margin:0 0 16px;font-size:16px;color:var(--green);">Przydziel pojedynczy dokument</h2>
    <form method="POST" action="{{ route('settings.users.auditor-documents', $user) }}" style="display:flex;gap:10px;max-width:720px;">
        @csrf
        <select name="document_id" required style="flex:1;padding:9px 12px;border:1px solid #D0CCC0;border-radius:6px;">
            <option value="">Wybierz dokument</option>
            @foreach($documents as $document)
                <option value="{{ $document->id }}">{{ $document->company?->name }}: {{ $document->original_filename }}</option>
            @endforeach
        </select>
        <button type="submit" style="background:var(--green);color:#fff;border:0;border-radius:6px;padding:9px 14px;font-weight:700;cursor:pointer;">Przydziel</button>
    </form>
</section>
@endsection