<?php

namespace App\Http\Controllers;

use App\Models\ImportantContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImportantContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        ImportantContact::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return $this->redirect('Ważny kontakt został dodany.');
    }

    public function update(Request $request, ImportantContact $importantContact): RedirectResponse
    {
        $importantContact->update($this->validated($request));

        return $this->redirect('Kontakt został zaktualizowany.');
    }

    public function destroy(ImportantContact $importantContact): RedirectResponse
    {
        $importantContact->delete();

        return $this->redirect('Kontakt został usunięty.');
    }

    private function validated(Request $request): array
    {
        return $request->validateWithBag('importantContact', [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'activity_description' => ['nullable', 'string', 'max:3000'],
            'help_description' => ['required', 'string', 'max:3000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:500'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function redirect(string $message): RedirectResponse
    {
        return redirect()->route('crm.index', ['tab' => 'contacts'])->with('success', $message);
    }
}
