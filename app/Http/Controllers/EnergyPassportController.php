<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EnergyPassport;
use App\Models\EnergyPassportTemplate;
use App\Services\AuditorAccessService;
use App\Services\EnergyPassportImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnergyPassportController extends Controller
{
    public function __construct(private readonly AuditorAccessService $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $passportsQuery = EnergyPassport::query()->with(['template', 'company', 'creator']);
        $this->access->scopeByCompanyAccess($passportsQuery, $user, 'can_view_audits');

        $passports = $passportsQuery->latest()->get();
        $templates = EnergyPassportTemplate::query()->withCount('passports')->orderBy('category')->orderBy('name')->get();
        $companiesQuery = Company::query()->clients()->active()->orderBy('name');
        $this->access->scopeByCompanyAccess($companiesQuery, $user, 'can_view_audits', 'id');

        return view('energy-passports.index', [
            'passports' => $passports,
            'templates' => $templates,
            'companies' => $companiesQuery->get(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePassport($request);
        $this->ensureCompanyAccess($request, $data['company_id'] ?? null);
        $passport = EnergyPassport::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('energy-passports.edit', $passport)
            ->with('success', 'Paszport energetyczny został utworzony. Możesz teraz uzupełnić dane techniczne.');
    }

    public function edit(Request $request, EnergyPassport $energyPassport): View
    {
        $this->ensurePassportAccess($request, $energyPassport);
        $energyPassport->load(['template', 'company']);
        $companiesQuery = Company::query()->clients()->active()->orderBy('name');
        $this->access->scopeByCompanyAccess($companiesQuery, $request->user(), 'can_view_audits', 'id');

        return view('energy-passports.edit', [
            'passport' => $energyPassport,
            'companies' => $companiesQuery->get(),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, EnergyPassport $energyPassport): RedirectResponse
    {
        $this->ensurePassportAccess($request, $energyPassport);
        $data = $this->validatePassport($request, false);
        $this->ensureCompanyAccess($request, $data['company_id'] ?? null);
        $data['responses'] = collect($request->validate([
            'responses' => ['nullable', 'array'],
            'responses.*' => ['nullable', 'string', 'max:5000'],
        ])['responses'] ?? [])->map(fn ($value) => trim((string) $value))->all();
        $energyPassport->update($data);

        return back()->with('success', 'Zmiany w paszporcie zostały zapisane.');
    }

    public function destroy(Request $request, EnergyPassport $energyPassport): RedirectResponse
    {
        $this->ensurePassportAccess($request, $energyPassport);
        $energyPassport->delete();

        return redirect()->route('energy-passports.index')->with('success', 'Paszport został usunięty.');
    }

    public function importTemplate(Request $request, EnergyPassportImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'xlsx_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);
        $template = $importer->import(
            $data['xlsx_file']->getRealPath(),
            $request->user()->id,
            false,
            $data['xlsx_file']->getClientOriginalName(),
        );

        return redirect()->route('energy-passports.index')
            ->with('success', 'Dodano szablon „'.$template->name.'”. Jest już dostępny przy tworzeniu paszportu.');
    }

    /** @return array<string,mixed> */
    private function validatePassport(Request $request, bool $withTemplate = true): array
    {
        $rules = [
            'company_id' => [Rule::requiredIf($this->access->isDelegatedAuditor($request->user())), 'nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'asset_identifier' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,in_progress,complete,archived'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
        if ($withTemplate) {
            $rules['template_id'] = ['nullable', 'integer', 'exists:energy_passport_templates,id'];
        }

        return $request->validate($rules);
    }

    private function ensurePassportAccess(Request $request, EnergyPassport $passport): void
    {
        if ($passport->company_id === null && $this->access->isDelegatedAuditor($request->user())) {
            abort_unless($passport->created_by === $request->user()->id, 403);
        }
        if ($passport->company_id !== null) {
            abort_unless($this->access->canViewCompany($request->user(), $passport->company_id, 'can_view_audits'), 403);
        }
    }

    private function ensureCompanyAccess(Request $request, mixed $companyId): void
    {
        if ($companyId !== null) {
            abort_unless($this->access->canViewCompany($request->user(), (int) $companyId, 'can_view_audits'), 403);
        }
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->hasRole('superadmin')
            || $request->user()->can('system.full_access')
            || $request->user()->can('audits.passports.manage');
    }
}
