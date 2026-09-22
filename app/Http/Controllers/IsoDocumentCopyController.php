<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Document;
use App\Models\IsoSectionDocument;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IsoDocumentCopyController extends Controller
{
    public function store(Request $request, Audit $audit, IsoSectionDocument $document)
    {
        $client = $request->routeIs('client.*');
        if ($client) {
            $request->user()->companies()->whereKey($audit->company_id)->firstOrFail();
            abort_unless($request->user()->hasRole('client_admin'), 403);
        } else {
            abort_unless(app(AuditorAccessService::class)->canViewCompany($request->user(), $audit->company_id, 'can_view_audits'), 403);
            abort_unless($request->user()->can('audits.manage'), 403);
        }
        abort_unless($document->audit_id === $audit->id && $document->scope === 'client', 404);
        DB::transaction(function () use ($audit, $document, $request) {
            $source = IsoSectionDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $path = 'audits/'.$audit->id.'/iso-copies/'.$source->id;
            if (Document::where('audit_id', $audit->id)->where('stored_path', $path)->exists()) {
                return;
            }
            $contents = $source->contents();
            abort_if($contents === null, 404, 'Plik źródłowy jest niedostępny. Wygeneruj go ponownie.');
            abort_unless(Storage::disk('local')->put($path, $contents), 500, 'Nie udało się zapisać kopii.');
            try {
                Document::create([
                    'company_id' => $audit->company_id, 'audit_id' => $audit->id, 'type' => $source->mime_type === 'application/pdf' ? 'audit_pdf' : 'other',
                    'original_filename' => $source->original_filename, 'stored_path' => $path,
                    'mime_type' => $source->mime_type, 'size' => strlen($contents), 'uploaded_by' => $request->user()->id,
                ]);
            } catch (\Throwable $exception) {
                if (! Document::where('stored_path', $path)->exists()) {
                    Storage::disk('local')->delete($path);
                }
                throw $exception;
            }
        });

        return back()->with('success', 'Dokument jest dostępny w głównej zakładce Dokumenty. Oryginał pozostał w dokumentacji punktu.');
    }
}
