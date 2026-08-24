<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccessSupportController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requested_url' => ['required', 'string', 'max:2048'],
        ]);
        $user = $request->user();
        $requestedUrl = $data['requested_url'];
        $requestMarker = '[ACCESS_REQUEST:'.$user->id.':'.hash('sha256', $requestedUrl).']';

        $administrator = User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'superadmin'))
            ->orderBy('id')
            ->first()
            ?? User::query()
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'admin'))
                ->orderBy('id')
                ->first();

        if (! $administrator) {
            return back()->with('access_request_error', 'Nie znaleziono aktywnego administratora. Skontaktuj się z administratorem bezpośrednio.');
        }

        $alreadyReported = Task::crm()
            ->where('assigned_to', $administrator->id)
            ->where('status', '!=', 'done')
            ->where('description', 'like', '%'.$requestMarker.'%')
            ->exists();

        if (! $alreadyReported) {
            Task::create([
                'title' => 'Prośba o sprawdzenie dostępu — '.$user->name,
                'description' => implode("\n", [
                    $requestMarker,
                    'Użytkownik zgłosił brak dostępu do zasobu.',
                    'Użytkownik: '.$user->name.' <'.$user->email.'>',
                    'Role: '.($user->getRoleNames()->join(', ') ?: 'brak'),
                    'Adres: '.$requestedUrl,
                    'Data zgłoszenia: '.now()->format('d.m.Y H:i:s'),
                ]),
                'assigned_to' => $administrator->id,
                'created_by' => $user->id,
                'status' => 'todo',
                'priority' => 'high',
                'due_date' => today(),
            ]);
        }

        return back()->with(
            'access_request_success',
            $alreadyReported
                ? 'Administrator ma już otwarte zgłoszenie dotyczące tego zasobu.'
                : 'Zgłoszenie zostało przekazane administratorowi jako zadanie CRM.'
        );
    }
}
