<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\ConfigurationTestMail;
use App\Models\CompanySettings;
use App\Services\TaskReminderMailConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailController extends Controller
{
    public function __invoke(Request $request, TaskReminderMailConfiguration $configuration): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['superadmin', 'admin']), 403);

        if ($problem = $configuration->problem()) {
            return response()->json(['message' => $problem], 422);
        }

        $email = $request->user()->email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Uzupełnij poprawny adres e-mail w swoim profilu.'], 422);
        }

        try {
            Mail::to($email)->send(new ConfigurationTestMail(CompanySettings::first()?->name ?? config('app.name')));
        } catch (Throwable $exception) {
            // Provider messages can contain credentials or request contents: do not expose them.
            Log::warning('Test poczty nie powiódł się.', [
                'user_id' => $request->user()->id,
                'error_type' => get_class($exception),
            ]);

            return response()->json(['message' => 'Nie udało się przekazać wiadomości. Sprawdź konfigurację poczty, klucz API i zweryfikowanego nadawcę u dostawcy.'], 502);
        }

        return response()->json(['message' => 'Wiadomość testowa została przekazana do wysyłki na '.$email.'. Sprawdź skrzynkę odbiorczą i spam. Przyjęcie wiadomości przez usługę nie potwierdza jej dostarczenia.']);
    }
}
