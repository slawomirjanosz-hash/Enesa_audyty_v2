<?php

namespace App\Http\Controllers;

use App\Models\OfferTemplateType;
use App\Models\OfferTemplateVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OfferTemplateController extends Controller
{
    public function index(): View
    {
        $types = OfferTemplateType::withCount('offerTemplateVersions')
            ->with(['offerTemplateVersions' => fn ($q) => $q->where('is_current', true)])
            ->orderBy('name')
            ->get();

        return view('offers.templates.index', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        OfferTemplateType::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Typ szablonu "' . $data['name'] . '" został dodany.');
    }

    public function storeVersion(Request $request, OfferTemplateType $type): RedirectResponse
    {
        $data = $request->validate([
            'html_content' => ['required', 'string'],
        ]);

        $maxVersion = $type->offerTemplateVersions()->max('version_number') ?? 0;

        $type->offerTemplateVersions()->update(['is_current' => false]);

        $type->offerTemplateVersions()->create([
            'version_number' => $maxVersion + 1,
            'html_content'   => $data['html_content'],
            'is_current'     => true,
            'uploaded_by'    => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Wersja v.' . ($maxVersion + 1) . ' została dodana i ustawiona jako aktywna.');
    }

    public function setAsCurrent(OfferTemplateVersion $version): RedirectResponse
    {
        $version->offerTemplateType->offerTemplateVersions()->update(['is_current' => false]);
        $version->update(['is_current' => true]);

        return redirect()->back()->with('success', 'Wersja v.' . $version->version_number . ' jest teraz aktywna.');
    }

    public function previewVersion(OfferTemplateVersion $version): Response
    {
        return response($version->html_content)->header('Content-Type', 'text/html');
    }

    public function destroy(OfferTemplateType $type): RedirectResponse
    {
        $type->delete();

        return redirect()->back()->with('success', 'Typ szablonu "' . $type->name . '" został usunięty.');
    }
}
