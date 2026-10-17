@if($permissionGroups !== [])
<details id="{{$prefix}}_permissions_panel" style="margin:18px 0;border:1px solid #d9dfe5;border-radius:8px;padding:12px">
    <summary style="font-weight:700;cursor:pointer">Uprawnienia i odpowiedzialności</summary>
    <input type="hidden" name="permissions_present" value="1">
    <p style="font-size:13px;color:#64748b">Rola to zestaw startowy. Zapisane uprawnienia dotyczą tylko tego użytkownika. Pełny dostęp operacyjny pomija ograniczenia modułów, ale nie włącza powiadomień o samochodach.</p>
    <p style="font-size:13px;color:#64748b">Wczytywanie i kopiowanie obejmuje uprawnienia, które możesz nadawać. Zmiana samej roli w edycji nie zastępuje uprawnień — użyj przycisku poniżej, jeśli chcesz wczytać jej zestaw.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <button type="button" class="btn-sm" data-load-role="{{$prefix}}">Wczytaj uprawnienia z roli</button>
        <select id="{{$prefix}}_copy_permissions" class="mf-select mf-input" style="flex:1;min-width:180px" aria-label="Kopiuj uprawnienia od użytkownika">
            <option value="">— kopiuj uprawnienia od —</option>
            @foreach($permissionSources as $source)<option value="{{$source->id}}">{{$source->name}} ({{$source->email}})</option>@endforeach
        </select>
        <button type="button" class="btn-sm" data-copy-permissions="{{$prefix}}">Kopiuj</button>
    </div>
    <p id="{{$prefix}}_permissions_status" role="status" style="font-size:13px;color:#64748b"></p>
    @foreach($permissionGroups as $group)
    <details style="border-top:1px solid #e2e8f0;padding:9px 0">
        <summary style="cursor:pointer;font-weight:600">{{$group['label']}}</summary>
        @foreach($group['permissions'] as $permission => $label)
        <label style="display:flex;align-items:flex-start;gap:8px;padding:7px 0;font-size:13px">
            <input type="checkbox" name="permissions[]" value="{{$permission}}" style="margin-top:3px" @checked($prefix === 'add' && in_array($permission, (array) old('permissions', []), true))>
            <span>{{$label}}</span>
        </label>
        @endforeach
    </details>
    @endforeach
</details>
@endif
