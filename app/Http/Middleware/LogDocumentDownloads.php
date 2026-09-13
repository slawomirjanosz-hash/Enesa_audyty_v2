<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LogDocumentDownloads
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $disposition = (string) $response->headers->get('Content-Disposition');
        if ($response->isSuccessful() && $request->route() && preg_match('/\b(attachment|inline)\b/i', $disposition)) {
            $model = $request->route('document') ?? $request->route('offer') ?? $request->route('audit') ?? $request->route('project') ?? $request->route('share');
            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'download',
                'auditable_type' => $model instanceof Model ? $model::class : null,
                'auditable_id' => $model instanceof Model ? $model->getKey() : null,
                'subject_label' => $model instanceof Model ? ($model->getAttribute('title') ?? $model->getAttribute('original_filename') ?? $model->getAttribute('name') ?? class_basename($model).' #'.$model->getKey()) : 'Pobranie dokumentu',
                'route_name' => $request->route()->getName(),
                'url' => $request->root().'/'.$request->route()->uri(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'changes' => $request->route('share') ? ['udostepnienie' => ['old' => null, 'new' => $request->route('share')->id]] : null,
            ]);
        }

        return $response;
    }
}
