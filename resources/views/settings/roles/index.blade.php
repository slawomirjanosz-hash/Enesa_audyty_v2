@extends('layouts.app')

@section('page-title', 'Ustawienia — role i uprawnienia')

@section('content')
<style>
    .settings-tabs{display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #e5e1d8}.settings-tab{padding:10px 20px;color:#6b7a70;text-decoration:none;font-size:13px;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px}.settings-tab.active,.settings-tab:hover{color:var(--green);border-bottom-color:var(--green)}
    .role-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;margin-bottom:14px;overflow:hidden}.role-summary{padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;cursor:pointer}.role-summary::-webkit-details-marker{display:none}.role-title{font-size:16px;font-weight:800}.role-body{padding:0 20px 20px;border-top:1px solid #eee}.role-meta,.muted{color:#6b7280;font-size:13px}.system-badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800;margin-left:8px}.input{width:100%;max-width:480px;padding:10px 12px;border:1px solid #cfc8bb;border-radius:7px;box-sizing:border-box}.btn{border:0;border-radius:7px;padding:9px 14px;font-weight:700;cursor:pointer;background:var(--green);color:#fff}.btn-danger{background:#b91c1c}.alert{padding:12px 14px;border-radius:8px;margin-bottom:18px;font-size:14px}.alert-success{background:#ecfdf5;color:#166534}.alert-error{background:#fef2f2;color:#991b1b}
    .permission-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:12px;margin:18px 0}.permission-group{border:1px solid #e5e1d8;border-radius:10px;padding:14px}.permission-group h4{margin:0 0 10px;font-size:14px}.permission{display:flex;gap:9px;align-items:flex-start;padding:7px 0;font-size:13px;line-height:1.35}.permission input{margin-top:2px}.role-actions{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap}.client-note{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:14px 0;color:#475569;font-size:13px}
    .roles-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.modal-overlay{display:none;position:fixed;inset:0;z-index:2000;background:rgba(15,23,42,.55);padding:24px;align-items:center;justify-content:center}.modal-overlay.open{display:flex}.modal-card{background:#fff;border-radius:14px;width:min(100%,960px);max-height:calc(100vh - 48px);overflow:auto;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.25)}.modal-head{display:flex;justify-content:space-between;align-items:center;gap:16px}.modal-close{border:0;background:#f1f5f9;border-radius:8px;width:36px;height:36px;cursor:pointer;font-size:20px}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px}@media(max-width:700px){.field-grid{grid-template-columns:1fr}.roles-heading{align-items:flex-start;flex-direction:column}}
</style>

<div class="settings-tabs">
    <a href="{{ route('settings.users.index') }}" class="settings-tab"><i class="ti ti-users"></i> Użytkownicy</a>
    <a href="{{ route('settings.roles.index') }}" class="settings-tab active"><i class="ti ti-shield-lock"></i> Role i uprawnienia</a>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error')) <div class="alert alert-error">{{ session('error') }}</div> @endif

<div class="roles-heading">
    <div><h2 style="margin:0 0 6px">Wszystkie role</h2><p class="muted" style="margin:0">Rozwiń rolę, aby zmienić nazwę wyświetlaną i uprawnienia.</p></div>
    <button type="button" class="btn" onclick="openCreateRoleModal()"><i class="ti ti-plus"></i> Utwórz rolę</button>
</div>

<div id="create-role-modal" class="modal-overlay {{ !old('role_id') && $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="create-role-title">
    <div class="modal-card">
        <div class="modal-head"><div><h2 id="create-role-title" style="margin:0 0 4px">Utwórz rolę</h2><p class="muted" style="margin:0">Nadaj nazwę i zaznacz dostęp do potrzebnych funkcji.</p></div><button type="button" class="modal-close" onclick="closeCreateRoleModal()" aria-label="Zamknij">×</button></div>
        <form method="POST" action="{{ route('settings.roles.store') }}">
            @csrf
            <label for="new-role-name" style="display:block;font-weight:700;margin:16px 0 6px">Nazwa roli</label>
            <input id="new-role-name" name="name" class="input" value="{{ old('role_id') ? '' : old('name') }}" placeholder="np. Kierownik Projektu" required>
            @if(!old('role_id')) @error('name') <div style="color:#b91c1c;font-size:13px;margin-top:5px">{{ $message }}</div> @enderror @endif
            @include('settings.roles.partials.permissions', ['selectedPermissions' => old('role_id') ? [] : old('permissions', []), 'inputPrefix' => 'new'])
            <div style="display:flex;justify-content:flex-end;gap:8px"><button type="button" class="btn" style="background:#64748b" onclick="closeCreateRoleModal()">Anuluj</button><button class="btn" type="submit">Utwórz rolę</button></div>
        </form>
    </div>
</div>

@foreach($roles as $role)
    @php
        $isSystem = in_array($role->name, $systemRoles, true);
        $isClient = in_array($role->name, ['client_admin', 'client_user'], true);
        $selected = (int) old('role_id') === $role->id ? old('permissions', []) : $role->permissions->pluck('name')->all();
    @endphp
    <details class="role-card" {{ (int) old('role_id') === $role->id ? 'open' : '' }}>
        <summary class="role-summary">
            <span>
                <span class="role-title">{{ $role->display_name ?: \App\Support\RolePermissionCatalog::roleLabel($role->name) }}</span>
                @if($isSystem)<span class="system-badge">SYSTEMOWA</span>@endif
                @if(!$isSystem && $role->name !== \App\Support\RolePermissionCatalog::roleLabel($role->name))<span class="role-meta">{{ $role->name }}</span>@endif
            </span>
            <span class="role-meta">{{ $role->users_count }} użytkowników · {{ $role->permissions->count() }} uprawnień <i class="ti ti-chevron-down"></i></span>
        </summary>
        <div class="role-body">
            <form method="POST" action="{{ route('settings.roles.update', $role) }}">
                @csrf @method('PUT')
                <input type="hidden" name="role_id" value="{{ $role->id }}">
                <div class="field-grid">
                    <div><label for="role-display-name-{{ $role->id }}" style="display:block;font-weight:700;margin:0 0 6px">Nazwa wyświetlana</label><input id="role-display-name-{{ $role->id }}" name="display_name" class="input" value="{{ (int) old('role_id') === $role->id ? old('display_name') : ($role->display_name ?: \App\Support\RolePermissionCatalog::roleLabel($role->name)) }}" required><div class="muted" style="margin-top:5px">Tę nazwę widzą użytkownicy, np. „Kierownik Projektu”.</div></div>
                    <div><label for="role-name-{{ $role->id }}" style="display:block;font-weight:700;margin:0 0 6px">Nazwa techniczna</label><input id="role-name-{{ $role->id }}" name="name" class="input" value="{{ (int) old('role_id') === $role->id ? old('name') : $role->name }}" {{ $isSystem ? 'readonly' : '' }} required><div class="muted" style="margin-top:5px">Identyfikator używany przez system. Dla ról systemowych pozostaje bez zmian.</div></div>
                </div>
                @if((int) old('role_id') === $role->id) @error('name') <div style="color:#b91c1c;font-size:13px;margin-top:5px">{{ $message }}</div> @enderror @endif

                @if($isClient)
                    <div class="client-note">Ta rola służy wyłącznie użytkownikom strefy klienta. Nie otrzymuje dostępu do wewnętrznych zakładek firmy.</div>
                @else
                    @include('settings.roles.partials.permissions', ['selectedPermissions' => $selected, 'inputPrefix' => 'role-'.$role->id, 'locked' => $role->name === 'superadmin'])
                @endif

                <div class="role-actions">
                    <button class="btn" type="submit">Zapisz rolę</button>
                </div>
            </form>
            @if($role->users_count === 0 && !in_array($role->name, $protectedRoles, true))
                <form method="POST" action="{{ route('settings.roles.destroy', $role) }}" style="margin-top:12px" onsubmit="return confirm('Usunąć rolę {{ addslashes($role->display_name ?: $role->name) }}?');">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">Usuń rolę</button>
                </form>
            @elseif($role->users_count > 0)
                <div class="muted" style="margin-top:12px">Usunięcie będzie dostępne po przypisaniu {{ $role->users_count }} użytkownikom innej roli.</div>
            @endif
        </div>
    </details>
@endforeach
<script>
function openCreateRoleModal(){document.getElementById('create-role-modal').classList.add('open');document.getElementById('new-role-name').focus()}
function closeCreateRoleModal(){document.getElementById('create-role-modal').classList.remove('open')}
</script>
@endsection
