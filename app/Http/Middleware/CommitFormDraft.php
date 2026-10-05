<?php

namespace App\Http\Middleware;

use App\Models\FormDraft;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommitFormDraft
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $key = $request->input('_draft_key');
        $revision = $request->input('_draft_revision');
        // Only a successful business save clears a draft. Validation, review errors,
        // plain redirects and the draft endpoint itself must never consume it.
        if ($request->user() && ! $request->isMethod('GET') && is_string($key) && preg_match('/^[a-f0-9]{64}$/D', $key)
            && is_scalar($revision) && ctype_digit((string) $revision) && $response->isRedirection()
            && in_array('success', $request->session()->get('_flash.new', []), true)
            && ! in_array('errors', $request->session()->get('_flash.new', []), true)) {
            FormDraft::where('user_id', $request->user()->id)->where('form_key', $key)->where('revision', (int) $revision)
                ->update(['payload' => null, 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
        }

        return $response;
    }
}
