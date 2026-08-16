<?php

namespace App\Http\Controllers;

use App\Mail\ClientAccepted;
use App\Mail\ClientRegistered;
use App\Mail\NewClientUser;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Document;
use App\Models\OfferRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditorAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function fetchGus(Request $request)
    {
        $nip = preg_replace('/[^0-9]/', '', $request->nip ?? '');

        if (strlen($nip) !== 10) {
            return response()->json(['error' => 'NIP musi mieć dokładnie 10 cyfr.'], 422);
        }

        try {
            $response = Http::timeout(10)
                ->get("https://wl-api.mf.gov.pl/api/search/nip/{$nip}", [
                    'date' => now()->format('Y-m-d'),
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Nie znaleziono firmy o podanym NIP w rejestrze MF.'], 404);
            }

            $subject = $response->json('result.subject');

            if (empty($subject)) {
                return response()->json(['error' => 'Brak danych dla podanego NIP.'], 404);
            }

            $name = $this->abbreviateCompanyForm($subject['name'] ?? '');
            $rawAddress = $subject['residenceAddress'] ?? $subject['workingAddress'] ?? '';
            $address = '';
            $city = '';
            $postcode = '';

            if ($rawAddress) {
                $lastComma = strrpos($rawAddress, ',');
                if ($lastComma !== false) {
                    $address = trim(substr($rawAddress, 0, $lastComma));
                    $cityPart = trim(substr($rawAddress, $lastComma + 1));
                    if (preg_match('/\b(\d{2}-\d{3})\b/', $cityPart, $postcodeMatch)) {
                        $postcode = $postcodeMatch[1];
                    }
                    $city = trim(preg_replace('/^\d{2}-\d{3}\s+/', '', $cityPart));
                } else {
                    $address = $rawAddress;
                }
            }

            return response()->json(compact('name', 'address', 'city', 'postcode'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Błąd połączenia z API. Spróbuj ponownie później.'], 503);
        }
    }

    private function abbreviateCompanyForm(string $name): string
    {
        if (empty($name)) {
            return $name;
        }

        // Wykryj czy nazwa jest zapisana WIELKIMI LITERAMI (porównujemy tylko litery,
        // ignorując cyfry, spacje i znaki specjalne, żeby nie dać się zmylić np. przez "sp.").
        $lettersOnly = preg_replace('/[^\p{L}]/u', '', $name);
        $isUpperCase = ! empty($lettersOnly) && $lettersOnly === mb_strtoupper($lettersOnly, 'UTF-8');

        // Kolejność ma znaczenie: dłuższe/złożone frazy muszą być zamieniane PRZED krótszymi,
        // żeby np. "spółka komandytowa" nie zostało podmienione zanim wykryjemy pełną frazę
        // "spółka z ograniczoną odpowiedzialnością spółka komandytowa".
        $replacements = [
            '/\bspółka\s+z\s+ograniczoną\s+odpowiedzialnością\s+spółka\s+komandytowo-akcyjna\b/iu' => 'sp. z o.o. S.K.A.',
            '/\bspółka\s+z\s+ograniczoną\s+odpowiedzialnością\s+spółka\s+komandytowa\b/iu' => 'sp. z o.o. sp.k.',
            '/\bspółka\s+z\s+ograniczoną\s+odpowiedzialnością\b/iu' => 'sp. z o.o.',
            '/\bspółka\s+komandytowo-akcyjna\b/iu' => 'S.K.A.',
            '/\bspółka\s+komandytowa\b/iu' => 'sp.k.',
            '/\bspółka\s+jawna\b/iu' => 'sp.j.',
            '/\bspółka\s+partnerska\b/iu' => 'sp.p.',
            '/\bspółka\s+akcyjna\b/iu' => 'S.A.',
            '/\bspółka\s+cywilna\b/iu' => 's.c.',
        ];

        foreach ($replacements as $pattern => $abbreviation) {
            $abbreviationToUse = $isUpperCase ? mb_strtoupper($abbreviation, 'UTF-8') : $abbreviation;
            $name = preg_replace($pattern, $abbreviationToUse, $name);
        }

        return trim($name);
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);
        if ($company->company_type === 'supplier') {
            return redirect()->route('suppliers.show', $company);
        }
        $access = app(AuditorAccessService::class);
        $user = auth()->user();
        $canManageCrm = $access->hasFullAccess($user);
        $auditsEnabled = CompanySettings::moduleIsEnabled('audits');
        $projectsEnabled = CompanySettings::moduleIsEnabled('projects');

        $relations = [
            'offers',
        ];
        if ($auditsEnabled) {
            $relations[] = 'audits.auditType';
        }

        if ($access->hasFullAccess($user)) {
            $relations[] = 'users.roles';
        }

        $company->load($relations);

        if (! $auditsEnabled || ! $access->canViewCompany($user, $company->id, 'can_view_audits')) {
            $company->setRelation('audits', collect());
        }
        if (! $access->canViewCompany($user, $company->id, 'can_view_offers')) {
            $company->setRelation('offers', collect());
        }
        if (! $access->hasFullAccess($user)) {
            $company->setRelation('users', collect());
        }

        $stats = [
            'audits_count' => $company->audits->count(),
            'offers_count' => $company->offers->count(),
            'users_count' => $company->users->count(),
        ];

        $projects = collect();
        if ($projectsEnabled) {
            $projectsQuery = Project::with(['manager', 'members'])
                ->where('company_id', $company->id)
                ->orderByDesc('created_at');
            if (! $access->hasFullAccess($user)) {
                $projectsQuery->where(fn ($query) => $query
                    ->where('manager_id', $user->id)
                    ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)));
            }
            $projects = $projectsQuery->get();
        }
        $stats['projects_count'] = $projects->count();

        $crmOpportunities = $access->scopeByCompanyAccess(
            CrmOpportunity::with(['assignedUser', 'relatedUsers', 'offers'])
                ->where('company_id', $company->id)
                ->orderByDesc('created_at'),
            $user,
            'can_view_dashboard'
        )->get();

        if (! $access->canViewCompany($user, $company->id, 'can_view_offers')) {
            $crmOpportunities->each(fn (CrmOpportunity $opportunity) => $opportunity->setRelation('offers', collect()));
        }

        $stats['crm_opportunities_count'] = $crmOpportunities->count();

        $crmTasks = $access->scopeByCompanyAccess(
            Task::with(['assignedUser', 'offer'])
                ->where('company_id', $company->id)
                ->whereNull('project_id')
                ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
                ->orderBy('due_date')
                ->orderByDesc('created_at'),
            $user,
            'can_view_dashboard'
        )->get();

        if (! $access->canViewCompany($user, $company->id, 'can_view_offers')) {
            $crmTasks->each(fn (Task $task) => $task->setRelation('offer', null));
        }

        $stats['crm_tasks_count'] = $crmTasks->count();

        $crmAssignableUsers = $canManageCrm
            ? User::where('is_active', true)
                ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['client_admin', 'client_user']))
                ->orderBy('name')
                ->get()
            : collect();

        $crmActivitiesQuery = CrmActivity::with(['user', 'crmOpportunity', 'offer'])
            ->where('company_id', $company->id)
            ->orderByDesc('created_at');

        if (! $access->canViewCompany($user, $company->id, 'can_view_offers')) {
            $crmActivitiesQuery->whereNull('offer_id');
        }

        $crmActivities = $access->scopeByCompanyAccess(
            $crmActivitiesQuery,
            $user,
            'can_view_dashboard'
        )->get();

        $offerRequests = $access->scopeByCompanyAccess(OfferRequest::with('offerFormTemplate', 'offers')
            ->where('company_id', $company->id)
            ->orderByDesc('created_at'), $user, 'can_view_offer_requests')
            ->get();

        $documents = $access->scopeDocumentsVisibleTo(Document::with('uploader', 'offer')
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at'), $user)
            ->get();

        return view('companies.show', compact(
            'company', 'stats', 'crmOpportunities', 'crmTasks', 'crmActivities', 'offerRequests', 'documents',
            'projects', 'auditsEnabled', 'projectsEnabled', 'canManageCrm', 'crmAssignableUsers'
        ));
    }

    public function update(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_type' => ['nullable', Rule::in(['client', 'supplier'])],
            'nip' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'supplier_capabilities' => ['nullable', 'string'],
            'supplier_materials' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path && Storage::disk('local')->exists($company->logo_path)) {
                Storage::disk('local')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'local');
        }

        unset($data['logo']);

        $company->update($data);

        return $company->company_type === 'supplier'
            ? redirect()->route('suppliers.show', $company)->with('success', 'Dane dostawcy zostały zaktualizowane.')
            : redirect()->back()->with('success', 'Dane firmy zostały zaktualizowane.');
    }

    public function accept(Request $request, Company $company)
    {
        $this->authorize('update', $company);
        $request->validate(['send_email' => ['nullable', 'boolean']]);

        if ($company->status === 'pending') {
            $company->update(['status' => 'active']);

            $company->loadMissing('users.roles');

            $clientAdmin = $company->users()->wherePivot('is_admin', true)->first();

            if ($clientAdmin && $request->boolean('send_email')) {
                Mail::to($clientAdmin->email)->send(new ClientAccepted($company));
            }
        }

        return redirect()->route('companies.show', $company)
            ->with('success', $request->boolean('send_email')
                ? 'Klient został zaakceptowany i otrzymał wiadomość e-mail.'
                : 'Klient został zaakceptowany bez wysyłania wiadomości e-mail.');
    }

    public function storeUser(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['client_admin', 'client_user'])],
            'password' => ['required', 'string', 'min:8'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        // Check for existing user (including soft-deleted)
        $existing = User::withTrashed()->where('email', $data['email'])->first();

        if ($existing) {
            if ($existing->trashed()) {
                // Restore soft-deleted user and attach to company
                DB::transaction(function () use ($existing, $data, $company) {
                    $existing->restore();
                    $existing->syncRoles([$data['role']]);
                    if (! $company->users()->where('user_id', $existing->id)->exists()) {
                        $company->users()->attach($existing->id, [
                            'is_admin' => $data['role'] === 'client_admin',
                        ]);
                    }
                });

                return redirect()->route('companies.show', $company)
                    ->with('success', 'Konto użytkownika zostało przywrócone i przypisane do firmy.');
            }

            // Active user exists — offer force-assign
            return redirect()->route('companies.show', $company)
                ->withErrors(['email' => 'Użytkownik z tym emailem już istnieje. Jeśli jesteś administratorem możesz go przypisać do tej firmy.'])
                ->with('can_force_assign', $data['email']);
        }

        $plainPassword = $data['password'];
        $user = null;

        DB::transaction(function () use ($data, $company, &$user, $plainPassword) {
            $user = User::create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'is_active' => true,
            ]);

            $user->assignRole($data['role']);

            $company->users()->attach($user->id, [
                'is_admin' => $data['role'] === 'client_admin',
            ]);
        });

        if ($user?->email && $request->boolean('send_email')) {
            Mail::to($user->email)->send(new NewClientUser($user, $company, $plainPassword));
        }

        return redirect()->route('companies.show', $company)
            ->with('success', $request->boolean('send_email')
                ? 'Użytkownik został dodany do firmy, a dane dostępowe wysłano mailem.'
                : 'Użytkownik został dodany do firmy bez wysyłania maila.');
    }

    public function assignExisting(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->firstOrFail();

        if (! $company->users()->where('user_id', $user->id)->exists()) {
            $company->users()->attach($user->id, ['is_admin' => false]);
        }

        return redirect()->route('companies.show', $company)
            ->with('success', 'Istniejący użytkownik został przypisany do firmy.');
    }

    public function destroyUser(Company $company, User $user)
    {
        $this->authorize('update', $company);
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);

        $company->users()->detach($user->id);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Użytkownik został odpięty od firmy. Konto użytkownika zostało zachowane.');
    }

    public function updateUser(Request $request, Company $company, User $user)
    {
        $this->authorize('update', $company);
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['client_admin', 'client_user'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // Update user data
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        // Only update password if provided
        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        // Sync role
        $user->syncRoles([$data['role']]);

        // Update company relationship is_admin flag
        $company->users()->updateExistingPivot($user->id, [
            'is_admin' => $data['role'] === 'client_admin',
        ]);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Dane użytkownika zostały zaktualizowane.');
    }

    public function store(Request $request)
    {
        abort_unless(app(AuditorAccessService::class)->hasFullAccess($request->user()), 403);

        $cleanNip = preg_replace('/[^0-9]/', '', $request->nip ?? '');
        $request->merge([
            'nip' => $cleanNip,
            'company_type' => $request->input('company_type', 'client'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_type' => ['required', Rule::in(['client', 'supplier'])],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'digits:10', Rule::unique('companies', 'nip')->whereNull('archived_at')],
            'supplier_capabilities' => ['nullable', 'string'],
            'supplier_materials' => ['nullable', 'string'],
        ]);

        $company = Company::create(array_merge($data, [
            'status' => $data['company_type'] === 'supplier' ? 'active' : 'pending',
        ]));

        if ($company->company_type === 'client') {
            Mail::to(config('mail.admin_email'))
                ->send(new ClientRegistered($company, $request->user() ?? auth()->user()));
        }

        return $company->company_type === 'supplier'
            ? redirect()->route('suppliers.show', $company)->with('success', 'Dostawca został dodany.')
            : redirect()->route('dashboard')->with('success', 'Klient został dodany.');
    }

    public function destroy(Company $company)
    {
        $this->authorize('delete', $company);

        // If company is already archived, hard-delete it
        if ($company->archived_at) {
            // First, hard-delete the pivot records
            DB::table('company_user')
                ->where('company_id', $company->id)
                ->delete();

            // Then hard-delete the company
            $company->delete();

            return redirect()->route('settings.users.index', ['tab' => 'firmy'])
                ->with('success', 'Zarchiwizowana firma została trwale usunięta.');
        }

        // Otherwise, archive the company
        $company->update([
            'archived_at' => now(),
            'status' => 'archived',
        ]);

        // Soft-delete all user-company relationships (mark pivot as deleted)
        DB::table('company_user')
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Firma została zarchiwizowana i przesunięta do archiwum.');
    }

    public function restore(Company $company)
    {
        $this->authorize('update', $company);

        $company->update([
            'archived_at' => null,
            'status' => 'active',
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Firma została przywrócona.');
    }
}
