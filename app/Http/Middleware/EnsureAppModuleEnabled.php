<?php

namespace App\Http\Middleware;

use App\Models\CompanySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(
            CompanySettings::moduleIsEnabled($module),
            403,
            'Ten moduł jest wyłączony w konfiguracji aplikacji.'
        );

        $user = $request->user();
        $permission = [
            'dashboard' => 'dashboard.view',
            'crm' => 'crm.view',
            'offers' => 'offers.view',
            'projects' => 'projects.view',
            'audits' => 'audits.view',
            'documents' => 'documents.view',
            'client_zone' => 'client_zone.view',
        ][$module] ?? null;

        $isStaff = $user?->getRoleNames()
            ->contains(fn (string $role) => ! in_array($role, ['client_admin', 'client_user'], true));

        if ($permission && $isStaff) {
            abort_unless(
                $user->hasRole('superadmin') || $user->can('system.full_access') || $user->can($permission),
                403,
                'Twoja rola nie ma dostępu do tej zakładki.'
            );
        }

        return $next($request);
    }
}
