<?php

namespace App\Http\Controllers;

use App\Models\FormDraft;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormDraftController extends Controller
{
    public function show(Request $request, string $key)
    {
        $draft = FormDraft::where('user_id', $request->user()->id)->where('form_key', $key)->first();

        return response()->json(['revision' => $draft?->revision ?? 0, 'payload' => $draft?->payload, 'saved_at' => $draft?->updated_at?->toIso8601String()])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $key)
    {
        $data = $request->validate(['revision' => 'required|integer|min:0', 'payload' => 'present|nullable|array']);
        abort_if(strlen(json_encode($data['payload'])) > 2000000, 422, 'Kopia jest zbyt duża. Użyj przycisku Zapisz.');

        return DB::transaction(function () use ($request, $key, $data) {
            // Serializes first insert as well as updates and discard tombstones.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $draft = FormDraft::firstOrNew(['user_id' => $request->user()->id, 'form_key' => $key]);
            abort_unless(($draft->revision ?? 0) === (int) $data['revision'], 409, 'Kopia robocza zmieniła się w innej karcie. Odśwież formularz.');
            abort_if(! $draft->exists && FormDraft::where('user_id', $request->user()->id)->count() >= 1000, 422, 'Limit kopii roboczych. Użyj zwykłego zapisu.');
            $draft->fill(['revision' => ($draft->revision ?? 0) + 1, 'payload' => $data['payload']])->save();

            return response()->json(['revision' => $draft->revision, 'saved_at' => $draft->updated_at->toIso8601String()])->header('Cache-Control', 'no-store');
        });
    }
}
