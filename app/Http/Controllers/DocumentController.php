<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Services\AuditorAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    private function ensureStaffAccess(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->can('documents.view') || app(AuditorAccessService::class)->hasFullAccess($user),
            403,
            'Brak uprawnień do dokumentów.'
        );
    }

    public function index(Request $request): View
    {
        $this->ensureStaffAccess();
        $access = app(AuditorAccessService::class);

        $query = $access->scopeDocumentsVisibleTo(
            Document::query(),
            $request->user()
        );
        $totalSize = Document::formatBytes((int) (clone $query)->sum('size'));
        $folderSizes = (clone $query)->selectRaw('company_id, SUM(size) AS total_size')->groupBy('company_id')->with('company')->get()
            ->groupBy(fn ($doc) => $doc->company?->name ?? 'Brak firmy')
            ->map(fn ($docs) => Document::formatBytes((int) $docs->sum('total_size')));
        $search = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:200']])['q'] ?? ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('original_filename', 'like', '%'.$search.'%')
                ->orWhere('type', 'like', '%'.$search.'%')
                ->orWhereHas('company', fn ($company) => $company->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('offer', fn ($offer) => $offer->where('number', 'like', '%'.$search.'%'))
                ->orWhereHas('uploader', fn ($user) => $user->where('name', 'like', '%'.$search.'%')));
        }
        $documentPage = $query->with(['company', 'offer', 'uploader'])->orderByDesc('updated_at')->orderByDesc('id')->paginate(50)->withQueryString();
        $docs = $documentPage->getCollection();

        // Group documents by company name
        $documents = $docs->groupBy(function ($doc) {
            return $doc->company?->name ?? 'Brak firmy';
        })->sortKeys();

        return view('documents.index', compact('documents', 'totalSize', 'folderSizes', 'documentPage', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeName = Str::uuid().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $companyFolder = $company->folderSlug();
        $relativePath = 'documents/'.$companyFolder.'/'.$safeName;

        abort_unless(Storage::disk('local')->put($relativePath, file_get_contents($file->getRealPath())), 500, 'Nie udało się zapisać pliku. Spróbuj ponownie.');

        Document::create([
            'company_id' => $company->id,
            'type' => 'upload',
            'original_filename' => $originalName,
            'stored_path' => $relativePath,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Plik został wgrany.');
    }

    public function download(Document $document)
    {
        $this->authorize('view', $document);

        if ($document->offer_id !== null && $document->offer?->company_id !== null) {
            $this->authorize('viewPrices', $document->offer);
        }

        if (! Storage::disk('local')->exists($document->stored_path)) {
            abort(404, 'Plik nie istnieje na dysku.');
        }

        return Storage::disk('local')->download($document->stored_path, $document->displayFilename());
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        if (Storage::disk('local')->exists($document->stored_path)) {
            Storage::disk('local')->delete($document->stored_path);
        }

        $document->delete();

        return redirect()->back()->with('success', 'Dokument został usunięty.');
    }
}
