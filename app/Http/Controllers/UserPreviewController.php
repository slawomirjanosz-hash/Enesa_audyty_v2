<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\UserPreviewService;
use Illuminate\Http\Request;

class UserPreviewController extends Controller
{
    public function store(Request $request, User $user, UserPreviewService $service)
    {
        abort_unless($request->user()->hasRole('superadmin'), 403);
        abort_if($request->session()->has('support_user_preview'), 409);
        abort_unless($service->eligible($user), 422, 'Podgląd wymaga aktywnego, niezablokowanego konta innego niż superadministrator.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $actor = $request->user();
        $state = ['actor_id' => $actor->id, 'actor_version' => (int) $actor->session_version, 'target_id' => $user->id, 'target_version' => (int) $user->session_version, 'expires_at' => now()->addMinutes(30)->timestamp, 'reason' => $data['reason']];
        ActivityLog::create([
            'user_id' => $actor->id, 'action' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $user->id,
            'subject_label' => 'Rozpoczęto podgląd serwisowy: '.$user->name,
            'changes' => ['support_preview' => $state], 'ip_address' => $request->ip(), 'route_name' => $request->route()?->getName(),
        ]);
        $request->session()->forget(['client_zone_company_id', 'client_zone_company_name']);
        $request->session()->regenerate();
        $request->session()->put('support_user_preview', $state);

        return redirect()->route($user->hasAnyRole(['client_admin', 'client_user']) ? 'client.dashboard' : 'board.mine');
    }

    public function destroy(Request $request, UserPreviewService $service)
    {
        $service->finish($request, 'manual');

        return redirect()->route('settings.users.index');
    }
}
