@extends('layouts.app')

@section('page-title', 'Ustawienia — Role')

@section('content')
<style>
    .settings-tabs { display:flex; gap:4px; margin-bottom:28px; border-bottom:2px solid #E5E1D8; }
    .settings-tab { padding:10px 20px; color:#6b7a70; text-decoration:none; font-size:13px; font-weight:600; border-bottom:2px solid transparent; margin-bottom:-2px; }
    .settings-tab.active, .settings-tab:hover { color:var(--green); border-bottom-color:var(--green); }
    .roles-card { background:#fff; border:1px solid #E5E1D8; border-radius:12px; padding:24px; max-width:920px; }
    .role-row { display:flex; justify-content:space-between; gap:20px; padding:18px 0; border-top:1px solid #eee; }
    .role-row:first-of-type { border-top:0; }
    .permission { display:flex; gap:10px; align-items:flex-start; padding:12px; border:1px solid #E5E1D8; border-radius:8px; margin:12px 0; }
    .input { width:100%; max-width:460px; padding:10px 12px; border:1px solid #cfc8bb; border-radius:7px; box-sizing:border-box; }
    .btn { border:0; border-radius:7px; padding:9px 14px; font-weight:700; cursor:pointer; background:var(--green); color:#fff; }
    .btn-danger { background:#b91c1c; } .muted { color:#6b7280; font-size:13px; } .alert { padding:12px 14px; border-radius:8px; margin-bottom:18px; font-size:14px; }
    .alert-success { background:#ecfdf5; color:#166534; } .alert-error { background:#fef2f2; color:#991b1b; }
</style>

<div class="settings-tabs">
    <a href="{{ route('settings.users.index') }}" class="settings-tab"><i class="ti ti-users" style="margin-right:6px;"></i>Użytkownicy</a>
    <a href="{{ route('settings.roles.index') }}" class="settings-tab active"><i class="ti ti-shield-lock" style="margin-right:6px;"></i>Role i uprawnienia</a>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error')) <div class="alert alert-error">{{ session('error') }}</div> @endif

<div class="roles-card" style="margin-bottom:20px;">
    <h2 style="margin:0 0 8px;">Nowa rola</h2>
    <p class="muted">Nie tworzę gotowych stanowisk. Administrator sam nadaje nazwę roli, wybiera jej realny zakres i później przypisuje ją użytkownikowi.</p>
    <form method="POST" action="{{ route('settings.roles.store') }}">
        @csrf
        <label for="name" style="display:block;font-weight:700;margin:16px 0 6px;">Nazwa roli</label>
        <input id="name" name="name" class="input" value="{{ old('role_id') ? '' : old('name') }}" placeholder="np. Kierownik Projektu" required>
        @if(!old('role_id')) @error('name') <div style="color:#b91c1c;font-size:13px;margin-top:5px;">{{ $message }}</div> @enderror @endif
        @foreach($availablePermissions as $permission => $meta)
            <label class="permission">
                <input type="checkbox" name="permissions[]" value="{{ $permission }}" {{ in_array($permission, old('permissions', []), true) ? 'checked' : '' }}>
                <span><strong>{{ $meta['label'] }}</strong><br><span class="muted">{{ $meta['description'] }}</span></span>
            </label>
        @endforeach
        <button class="btn" type="submit"><i class="ti ti-plus"></i> Utwórz rolę</button>
    </form>
</div>

<div class="roles-card">
    <h2 style="margin:0 0 8px;">Twoje role</h2>
    @forelse($roles as $role)
        <div class="role-row">
            <div style="flex:1;">
                <div class="muted">Przypisani użytkownicy: {{ $role->users_count }}</div>
                <form method="POST" action="{{ route('settings.roles.update', $role) }}" style="margin-top:10px;">
                    @csrf @method('PUT')
                    <input type="hidden" name="role_id" value="{{ $role->id }}">
                    <label for="role-name-{{ $role->id }}" style="display:block;font-weight:700;margin:0 0 6px;">Nazwa roli</label>
                    <input id="role-name-{{ $role->id }}" name="name" class="input" value="{{ (int) old('role_id') === $role->id ? old('name') : $role->name }}" required>
                    @if((int) old('role_id') === $role->id) @error('name') <div style="color:#b91c1c;font-size:13px;margin-top:5px;">{{ $message }}</div> @enderror @endif
                    @foreach($availablePermissions as $permission => $meta)
                        <label class="permission">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" {{ $role->hasPermissionTo($permission) ? 'checked' : '' }}>
                            <span><strong>{{ $meta['label'] }}</strong><br><span class="muted">{{ $meta['description'] }}</span></span>
                        </label>
                    @endforeach
                    <button class="btn" type="submit">Zapisz zmiany</button>
                </form>
            </div>
            <form method="POST" action="{{ route('settings.roles.destroy', $role) }}" onsubmit="return confirm('Usunąć rolę {{ $role->name }}?{{ $role->users_count ? ' Przypisani użytkownicy pozostaną w systemie bez roli.' : '' }}');">
                @csrf @method('DELETE')
                <button class="btn btn-danger" type="submit">Usuń</button>
            </form>
        </div>
    @empty
        <p class="muted">Nie ma jeszcze własnych ról.</p>
    @endforelse
</div>
@endsection
