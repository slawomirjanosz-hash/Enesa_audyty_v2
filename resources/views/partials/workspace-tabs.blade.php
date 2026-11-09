@auth
@php
    // A separate random namespace, never an authentication token or session ID.
    if (!session()->has('workspace_tabs_scope')) {
        session()->put('workspace_tabs_scope', \Illuminate\Support\Str::random(24));
    }
    $workspaceTitle = trim(strip_tags($__env->yieldContent('page-title', 'Panel')));
    $workspaceCompany = request()->route('company');
    if ($workspaceCompany instanceof \App\Models\Company) {
        $workspaceTitle = $workspaceCompany->name;
    }
@endphp
<link rel="stylesheet" href="{{ asset('css/workspace-tabs.css') }}">
<nav class="workspace-tabs" aria-label="Otwarte strony" data-workspace-tabs
     data-scope="{{ auth()->id() }}:{{ session('workspace_tabs_scope') }}"
     data-title="{{ $workspaceTitle }}" data-section="{{ request()->routeIs('crm.index') ? ($currentTab ?? '') : (request()->routeIs('hr.index') ? ($tab ?? 'delegations') : '') }}" data-logout="{{ route('logout') }}" hidden>
    <div class="workspace-tabs-items" data-workspace-tabs-items></div>
</nav>
@push('scripts')<script src="{{ asset('js/workspace-tabs.js') }}" defer></script>@endpush
@endauth
