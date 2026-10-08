<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Http\Request;

class AccountSecurityController extends Controller
{
    private function authorizeManager(Request $request, User $user): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'superadmin']), 403);
        abort_if($user->hasRole('superadmin') && ! $request->user()->hasRole('superadmin'), 403);
    }

    public function unlock(Request $request, User $user)
    {
        $this->authorizeManager($request, $user);
        app(AccountSecurityService::class)->unlock($user);

        return back()->with('success', 'Konto odblokowano. Użytkownik może zalogować się ponownie.');
    }
}
