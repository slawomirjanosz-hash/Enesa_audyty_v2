<div class="permission-grid">
    @foreach($permissionGroups as $group)
        <section class="permission-group">
            <h4>{{ $group['label'] }}</h4>
            @foreach($group['permissions'] as $permission => $label)
                <label class="permission">
                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" {{ in_array($permission, $selectedPermissions, true) ? 'checked' : '' }} {{ ($locked ?? false) ? 'disabled' : '' }}>
                    <span>{{ $label }}</span>
                </label>
                @if(($locked ?? false) && in_array($permission, $selectedPermissions, true))
                    <input type="hidden" name="permissions[]" value="{{ $permission }}">
                @endif
            @endforeach
        </section>
    @endforeach
</div>
