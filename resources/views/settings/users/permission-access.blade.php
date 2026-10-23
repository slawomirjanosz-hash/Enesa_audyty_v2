@extends('layouts.app')
@section('page-title', 'Ustawienia — Dostęp według uprawnień')
@section('content')
<a href="{{ route('settings.users.index') }}">← Użytkownicy</a>
<h1 style="font-size:24px;margin:16px 0">Dostęp według uprawnień</h1>
<p>Wybierz jedno lub więcej uprawnień. Lista obejmuje niezarchiwizowane konta, również nieaktywne. Odłączenie dotyczy wyłącznie wskazanego użytkownika.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<form method="GET" style="background:white;padding:20px;border-radius:12px;margin:20px 0">
    <label for="permission-search">Szukaj uprawnienia</label>
    <input id="permission-search" type="search" placeholder="Nazwa lub kod uprawnienia" style="width:100%;margin:8px 0">
    <div style="max-height:340px;overflow:auto;border:1px solid #ddd;padding:12px">
    @foreach($permissionGroups as $group)
        <fieldset style="margin-bottom:16px" data-permission-group>
            <legend style="font-weight:700">{{ $group['label'] }}</legend>
            @foreach($group['permissions'] as $name => $label)
            <label data-permission-option style="display:block;padding:5px">
                <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, $selected, true))>
                {{ $label }} <small style="color:#64748b">({{ $name }})</small>
            </label>
            @endforeach
        </fieldset>
    @endforeach
    </div>
    <label for="permission-match">Pokaż użytkowników mających</label>
    <select id="permission-match" name="match" style="margin:12px">
        <option value="any" @selected($match === 'any')>dowolne z wybranych uprawnień</option>
        <option value="all" @selected($match === 'all')>wszystkie wybrane uprawnienia</option>
    </select>
    <button type="submit" class="btn-sm">Pokaż dostęp</button>
    <a href="{{ route('settings.users.permission-access') }}" style="margin-left:12px">Wyczyść</a>
</form>
<p>Pełny dostęp operacyjny może omijać ograniczenia modułów. Samo odłączenie uprawnienia modułu nie odbierze wtedy dostępu — sprawdź uprawnienie „Pełny dostęp operacyjny”. Przydziały firm i dokumentów audytora pozostają zarządzane w jego koncie.</p>
@if($selected === [])
<p>Wybierz uprawnienia i kliknij „Pokaż dostęp”.</p>
@else
<p>Znaleziono użytkowników: {{ $rows->count() }}</p>
<div style="overflow-x:auto;background:white;padding:16px;border-radius:12px">
<table style="width:100%;border-collapse:collapse" id="permission-access-table">
    <thead><tr><th>Użytkownik</th><th>E-mail</th><th>Role</th><th>Status konta</th><th>Wybrane uprawnienia i źródło dostępu</th><th data-sortable="false">Akcje</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        @php($user = $row['user'])
        <tr>
            <td style="padding:12px">{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->roles->pluck('display_name')->filter()->implode(', ') ?: $user->getRoleNames()->implode(', ') }}</td>
            <td>{{ $user->is_active ? 'Aktywne' : 'Nieaktywne' }}</td>
            <td>
                @foreach($row['access'] as $name)
                <div style="padding:5px">{{ $labels[$name] }}
                    <small>({{ $user->hasRole('superadmin') ? 'superadmin' : (in_array($name, $row['grants'], true) ? ($user->individual_permissions ? 'indywidualne' : 'indywidualne / z roli') : 'pełny dostęp operacyjny') }})</small>
                </div>
                @endforeach
                @if($row['full'])
                    <strong>Pełny dostęp operacyjny</strong>
                    @if(isset($labels['system.full_access']))
                    <a href="{{ route('settings.users.permission-access', ['permissions' => ['system.full_access']]) }}">Sprawdź i zarządzaj pełnym dostępem</a>
                    @endif
                @endif
            </td>
            <td>
                @if(!$row['manageable'])
                    <small>Konto chronione hierarchią ról</small>
                @else
                    @foreach($row['access'] as $name)
                        @if(in_array($name, $row['grants'], true))
                        <form method="POST" action="{{ route('settings.users.permission-access.revoke', $user) }}" style="margin:6px 0" onsubmit="return confirm('Odłączyć wskazane uprawnienie od tego użytkownika?')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="permission" value="{{ $name }}">
                            <button class="btn-sm" type="submit">Odłącz: {{ $labels[$name] }}</button>
                        </form>
                        @endif
                    @endforeach
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" style="padding:20px">Brak użytkowników spełniających wybrane warunki.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endif
@endsection
@push('scripts')
<script>
document.getElementById('permission-search').addEventListener('input', function () {
    const query = this.value.toLocaleLowerCase('pl');
    document.querySelectorAll('[data-permission-group]').forEach(group => {
        let visible = false;
        group.querySelectorAll('[data-permission-option]').forEach(option => {
            const matches = option.textContent.toLocaleLowerCase('pl').includes(query);
            option.style.display = matches ? 'block' : 'none';
            visible ||= matches;
        });
        group.hidden = !visible;
    });
});
</script>
@endpush
