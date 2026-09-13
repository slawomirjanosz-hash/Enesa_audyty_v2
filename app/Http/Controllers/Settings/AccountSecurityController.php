<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\DocumentQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function quota(Request $request, User $user)
    {
        $this->authorizeManager($request, $user);
        $data = $request->validate(['limit_mb' => ['required', 'integer', 'min:1', 'max:1048576']]);
        $bytes = $data['limit_mb'] * 1048576;
        DB::transaction(function () use ($user, $bytes) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($bytes < app(DocumentQuotaService::class)->used($locked->id), 422, 'Limit nie może być mniejszy niż zajęte miejsce.');
            $locked->forceFill(['document_limit_bytes' => $bytes])->save();
        });

        return back()->with('success', 'Limit dokumentów został zapisany.');
    }
}
