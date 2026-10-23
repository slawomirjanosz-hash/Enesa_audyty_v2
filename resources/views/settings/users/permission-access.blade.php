@extends('layouts.app')
@section('page-title', 'Ustawienia — Dostęp według uprawnień')
@push('styles')
<style>
    .permission-access{color:#1a1a1a;font-size:14px;line-height:1.5}
    .permission-access .page-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap}
    .permission-access h1{font-family:'Manrope',sans-serif;font-size:22px;color:var(--green);margin:0 0 6px;font-weight:700}
    .permission-access .muted{color:#6b7a70;font-size:13px}
    .permission-access .btn-primary,.permission-access .btn-secondary,.permission-access .btn-danger{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:38px;padding:9px 18px;border:1px solid transparent;border-radius:8px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;line-height:1.4;text-decoration:none;cursor:pointer;transition:background .15s,color .15s;white-space:normal}
    .permission-access .btn-primary{background:var(--green);color:#f5f0e8}.permission-access .btn-primary:hover{background:#153d2e}
    .permission-access .btn-secondary{background:#fff;border-color:#e5e1d8;color:#5a6a60}.permission-access .btn-secondary:hover{background:#f4f1ea;color:var(--green)}
    .permission-access .btn-danger{background:#fff;border-color:#fecaca;color:#b91c1c;text-align:left;justify-content:flex-start;padding:8px 12px;font-size:12px}.permission-access .btn-danger:hover{background:#fef2f2;border-color:#fca5a5}
    .permission-access :is(a,button,input,select):focus-visible{outline:2px solid var(--green);outline-offset:3px}
    .permission-access .card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;overflow:hidden;margin-bottom:20px}
    .permission-access .card-heading{padding:18px 24px;border-bottom:1px solid #f0ede6;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .permission-access h2{font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;margin:0 0 3px}
    .permission-access .filter-body{padding:20px 24px}.permission-access .field-label{display:block;font-size:12px;font-weight:700;color:#3a3a3a;margin-bottom:6px}
    .permission-access .field-input{box-sizing:border-box;width:100%;background:#fafaf6;border:1px solid #d0ccc0;border-radius:6px;padding:9px 12px;min-height:40px;font-size:14px;font-family:'Lato',sans-serif;color:#1a1a1a}.permission-access .field-input:focus{border-color:var(--green);background:#fff}
    .permission-access .search-field{margin-bottom:18px}.permission-access .permission-list{max-height:380px;overflow:auto;border:1px solid #e5e1d8;border-radius:8px;padding:16px}
    .permission-access fieldset{min-width:0;border:0;border-bottom:1px solid #f0ede6;margin:0 0 14px;padding:0 0 14px}.permission-access fieldset:last-child{border-bottom:0;margin-bottom:0;padding-bottom:0}
    .permission-access legend{font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:var(--green);margin-bottom:8px;padding:0}
    .permission-access .permission-option{display:flex;align-items:flex-start;gap:10px;padding:8px;border-radius:6px;cursor:pointer;font-size:13px}.permission-access .permission-option:hover{background:#fafaf6}.permission-access .permission-option:has(input:checked){background:#edf5ef}
    .permission-access input[type=checkbox]{width:16px;height:16px;margin:2px 0 0;flex-shrink:0;accent-color:var(--green)}.permission-access .permission-code{display:block;color:#88928b;font-size:11px;overflow-wrap:anywhere}
    .permission-access .filter-footer{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-top:20px}.permission-access .match-field{flex:1 1 280px;max-width:440px}.permission-access .filter-actions{display:flex;gap:8px;flex-wrap:wrap}
    .permission-access .alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px}.permission-access .alert i{font-size:18px;flex-shrink:0;margin-top:1px}
    .permission-access .alert-success{background:#f0fdf4;border:1px solid #86efac;color:#166534}.permission-access .alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}.permission-access .access-note{background:#f8faf8;border:1px solid #e5e1d8;color:#5a6a60}
    .permission-access .access-note p+p{margin-top:6px}.permission-access .empty-state{padding:32px 24px;text-align:center;color:#6b7a70;font-size:13px}.permission-access .empty-state>i{display:block;font-size:28px;margin-bottom:8px;color:#94a398}
    .permission-access .table-wrap{overflow-x:auto}.permission-access table{width:100%;border-collapse:collapse;font-size:13px}.permission-access th{padding:11px 16px;text-align:left;font-family:'Manrope',sans-serif;font-size:11px;font-weight:700;color:#888;background:#fafaf6;border-bottom:1px solid #f0ede6}.permission-access td{padding:14px 16px;vertical-align:top;border-bottom:1px solid #f0ede6}.permission-access tbody tr:last-child td{border-bottom:0}.permission-access tbody tr:hover td{background:#fafaf6}
    .permission-access .grant{margin-bottom:8px;min-width:210px}.permission-access .grant small{display:block;color:#6b7a70;font-size:11px}.permission-access .badge{display:inline-flex;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:700;background:#edf5ef;color:#1b5e20}.permission-access .badge-inactive{background:#f4f1ea;color:#777}.permission-access .full-access{margin:10px 0;font-size:12px}.permission-access .full-access .btn-secondary{font-size:11px;padding:6px 10px;margin-top:6px}.permission-access .revoke-form{margin-bottom:8px}
    @media(max-width:700px){.permission-access .filter-body,.permission-access .card-heading{padding:16px}.permission-access .filter-footer{align-items:stretch;flex-direction:column}.permission-access .match-field{flex-basis:auto;max-width:none}.permission-access .filter-actions>*{flex:1}.permission-access .permission-list{padding:10px}.permission-access .page-heading>.btn-secondary{width:100%}}
</style>
@endpush
@section('content')
<div class="permission-access">
<div class="page-heading">
    <div><h1>Dostęp według uprawnień</h1><p class="muted">Sprawdź, kto ma dostęp do wybranych funkcji, i zarządzaj uprawnieniami użytkowników.</p></div>
    <a class="btn-secondary" href="{{ route('settings.users.index') }}"><i class="ti ti-arrow-left" aria-hidden="true"></i> Wróć do użytkowników</a>
</div>
@if(session('success'))<div class="alert alert-success" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i><span>{{ session('success') }}</span></div>@endif
@if($errors->any())<div class="alert alert-error" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>@endif
<form method="GET" class="card">
    <div class="card-heading"><div><h2>Wybierz uprawnienia</h2><p class="muted">Zaznacz jedną lub więcej pozycji, a następnie pokaż użytkowników.</p></div></div>
    <div class="filter-body">
    <div class="search-field"><label class="field-label" for="permission-search">Szukaj uprawnienia</label>
    <input class="field-input" id="permission-search" type="search" placeholder="Wpisz nazwę lub kod uprawnienia…"></div>
    <div class="permission-list">
    @foreach($permissionGroups as $group)
        <fieldset data-permission-group>
            <legend>{{ $group['label'] }}</legend>
            @foreach($group['permissions'] as $name => $label)
            <label data-permission-option class="permission-option">
                <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, $selected, true))>
                <span>{{ $label }} <small class="permission-code">{{ $name }}</small></span>
            </label>
            @endforeach
        </fieldset>
    @endforeach
    </div>
    <div class="filter-footer">
    <div class="match-field"><label class="field-label" for="permission-match">Pokaż użytkowników mających</label>
    <select class="field-input" id="permission-match" name="match">
        <option value="any" @selected($match === 'any')>dowolne z wybranych uprawnień</option>
        <option value="all" @selected($match === 'all')>wszystkie wybrane uprawnienia</option>
    </select></div>
    <div class="filter-actions">
        <button type="submit" class="btn-primary"><i class="ti ti-search" aria-hidden="true"></i> Pokaż dostęp</button>
        <a class="btn-secondary" href="{{ route('settings.users.permission-access') }}"><i class="ti ti-filter-off" aria-hidden="true"></i> Wyczyść filtry</a>
    </div>
    </div>
    </div>
</form>
<div class="alert access-note"><i class="ti ti-info-circle" aria-hidden="true"></i><div>
    <p><strong>Pełny dostęp operacyjny</strong> omija ograniczenia modułów. Aby je przywrócić, odłącz również to uprawnienie.</p>
    <p>Odłączenie dotyczy tylko wskazanej osoby. Przydziały firm i dokumentów audytora zmienisz w edycji jego konta.</p>
</div></div>
@if($selected === [])
<div class="card empty-state"><i class="ti ti-users" aria-hidden="true"></i><p>Wybierz uprawnienia i kliknij „Pokaż dostęp”.</p></div>
@else
<div class="card">
<div class="card-heading"><div><h2>Użytkownicy z dostępem</h2><p class="muted">Lista obejmuje konta aktywne i nieaktywne, bez kont z archiwum.</p></div><span class="badge">Znaleziono użytkowników: {{ $rows->count() }}</span></div>
<div class="table-wrap">
<table id="permission-access-table">
    <thead><tr><th>Użytkownik</th><th>E-mail</th><th>Role</th><th>Status konta</th><th>Wybrane uprawnienia i źródło dostępu</th><th data-sortable="false">Akcje</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        @php($user = $row['user'])
        <tr>
            <td><strong>{{ $user->name }}</strong></td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->roles->pluck('display_name')->filter()->implode(', ') ?: $user->getRoleNames()->implode(', ') }}</td>
            <td><span class="badge {{ $user->is_active ? '' : 'badge-inactive' }}">{{ $user->is_active ? 'Aktywne' : 'Nieaktywne' }}</span></td>
            <td>
                @foreach($row['access'] as $name)
                <div class="grant">{{ $labels[$name] }}
                    <small>({{ $user->hasRole('superadmin') ? 'superadmin' : (in_array($name, $row['grants'], true) ? ($user->individual_permissions ? 'indywidualne' : 'indywidualne / z roli') : 'pełny dostęp operacyjny') }})</small>
                </div>
                @endforeach
                @if($row['full'])
                    <div class="full-access">
                    <strong>Pełny dostęp operacyjny</strong>
                    @if(isset($labels['system.full_access']))
                    <a class="btn-secondary" href="{{ route('settings.users.permission-access', ['permissions' => ['system.full_access']]) }}"><i class="ti ti-shield-lock" aria-hidden="true"></i> Zarządzaj pełnym dostępem</a>
                    @endif
                    </div>
                @endif
            </td>
            <td>
                @if(!$row['manageable'])
                    <span class="muted"><i class="ti ti-lock" aria-hidden="true"></i> Konto chronione hierarchią ról</span>
                @else
                    @foreach($row['access'] as $name)
                        @if(in_array($name, $row['grants'], true))
                        <form method="POST" action="{{ route('settings.users.permission-access.revoke', $user) }}" class="revoke-form" onsubmit="return confirm('Odłączyć wskazane uprawnienie od tego użytkownika?')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="permission" value="{{ $name }}">
                            <button class="btn-danger" type="submit"><i class="ti ti-unlink" aria-hidden="true"></i><span>Odłącz: {{ $labels[$name] }}</span></button>
                        </form>
                        @endif
                    @endforeach
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty-state">Brak użytkowników spełniających wybrane warunki.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
</div>
@endif
</div>
@endsection
@push('scripts')
<script>
document.getElementById('permission-search').addEventListener('input', function () {
    const query = this.value.toLocaleLowerCase('pl');
    document.querySelectorAll('[data-permission-group]').forEach(group => {
        let visible = false;
        group.querySelectorAll('[data-permission-option]').forEach(option => {
            const matches = option.textContent.toLocaleLowerCase('pl').includes(query);
            option.style.display = matches ? 'flex' : 'none';
            visible ||= matches;
        });
        group.hidden = !visible;
    });
});
</script>
@endpush
