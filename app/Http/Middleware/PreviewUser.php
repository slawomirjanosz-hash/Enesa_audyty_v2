<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserPreviewService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreviewUser
{
    public function handle(Request $request, Closure $next)
    {
        $state = $request->session()->get('support_user_preview');
        if (! is_array($state)) {
            return $next($request);
        }
        if (! isset($state['actor_id'], $state['actor_version'], $state['target_id'], $state['target_version'], $state['expires_at'], $state['reason'])) {
            $request->session()->forget('support_user_preview');
            abort(403);
        }
        // The authenticated session always belongs to the real administrator.
        // CheckAccountSecurity runs before this middleware and checks their MFA and session revocation.
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('superadmin') || $actor->id !== $state['actor_id'] || (int) $actor->session_version !== $state['actor_version']) {
            $request->session()->forget('support_user_preview');
            abort(403);
        }
        $service = app(UserPreviewService::class);
        if ($request->routeIs('user-preview.stop', 'logout')) {
            if ($request->routeIs('logout')) {
                $service->finish($request, 'logout');
            }

            return $next($request);
        }
        $target = User::find($state['target_id']);
        if ($state['expires_at'] <= now()->timestamp || ! $target || ! $service->eligible($target) || (int) $target->session_version !== $state['target_version']) {
            $service->finish($request, 'expired_or_revoked');

            return redirect()->route('settings.users.index')->with('success', 'Podgląd serwisowy został zakończony.');
        }
        if (! $request->isMethodSafe() || $request->routeIs('auth.*', 'password.*', 'verification.*', 'client-zone.*', 'support.mail-test')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Podgląd serwisowy jest tylko do odczytu. Zakończ podgląd, aby zapisywać zmiany.'], 403)
                : response()->view('errors.user-preview-readonly', ['previewUser' => $target], 403);
        }
        Auth::guard('web')->setUser($target);
        // GET pages can mark notifications/tasks as read. Do not persist those effects for the target.
        DB::beginTransaction();
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');
            // Deferred callbacks run after this request context is restored; do not run exports as the administrator.
            if ($response instanceof StreamedResponse) {
                return response()->view('errors.user-preview-readonly', ['previewUser' => $target], 403);
            }
            if (! $response->headers->has('Content-Disposition') && str_contains($response->headers->get('Content-Type', ''), 'text/html') && method_exists($response, 'getContent')) {
                $banner = view('partials.user-preview-banner', ['previewUser' => $target])->render();
                $response->setContent(preg_replace_callback('/<body\b[^>]*>/i', fn ($match) => $match[0].$banner, $response->getContent(), 1));
                $response->headers->remove('Content-Length');
            }

            return $response;
        } finally {
            DB::rollBack();
            Auth::guard('web')->setUser($actor);
        }
    }
}
