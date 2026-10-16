<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\UserAccountCreated;
use App\Models\AuditorCompanyAccess;
use App\Models\AuditorDocumentAccess;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Document;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\AuditorAccessService;
use App\Services\TaskReminderMailConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Throwable;

class UserController extends Controller
{
    private const ROLE_RANKS = [
        'client_user' => 10,
        'client_admin' => 10,
        'auditor' => 20,
        'auditor_senior' => 30,
        'admin' => 40,
        'superadmin' => 50,
    ];

    public function index()
    {
        abort_unless(
            app(AuditorAccessService::class)->hasFullAccess(auth()->user())
                || auth()->user()?->can('settings.users.view'),
            403
        );

        if (request()->has('tab')) {
            return redirect()->route('settings.archive.index');
        }

        $roles = Role::query()
            ->get()
            ->filter(fn (Role $role) => $this->canAssignRole(auth()->user(), $role->name))
            ->values();

        // Include every active account, also client accounts without a company.
        $allUsers = User::with(['roles', 'companies', 'leaveEntitlements'])
            ->orderBy('email')
            ->get();

        $archivedStaff = User::onlyTrashed()
            ->with('roles')
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')
            ->get();
        $archivedClients = User::onlyTrashed()
            ->with('roles')
            ->with('companies')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')
            ->get();
        $orphanUsers = User::whereDoesntHave('companies')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))
            ->with('roles')
            ->orderByDesc('created_at')
            ->get();
        $companies = Company::clients()->active()->orderBy('name')->get();
        $archivedCompanies = Company::archived()->orderBy('name')->get();

        return view('settings.users.index', compact('roles', 'archivedStaff', 'archivedClients', 'orphanUsers', 'companies', 'archivedCompanies', 'allUsers'));
    }

    public function showAuditorAccess(User $user)
    {
        $this->ensureAuditorAccessManager(auth()->user(), $user);

        $accesses = $user->auditorCompanyAccesses()
            ->with('company')
            ->orderBy('company_id')
            ->get();
        $availableCompanies = Company::clients()->active()
            ->whereNotIn('id', $accesses->pluck('company_id'))
            ->orderBy('name')
            ->get();

        $documents = Document::with('company')->orderByDesc('updated_at')->get();

        return view('settings.users.auditor-access', compact('user', 'accesses', 'availableCompanies', 'documents'));
    }

    public function storeAuditorAccess(Request $request, User $user)
    {
        $this->ensureAuditorAccessManager($request->user(), $user);

        $data = $this->validateAuditorAccess($request);

        if (AuditorCompanyAccess::where('auditor_id', $user->id)->where('company_id', $data['company_id'])->exists()) {
            return redirect()->back()->with('error', 'Ta firma jest już przydzielona. Użyj edycji istniejącego przydziału.');
        }

        AuditorCompanyAccess::create(array_merge(['auditor_id' => $user->id], $data));

        return redirect()->back()->with('success', 'Dostęp audytora do firmy został dodany.');
    }

    public function updateAuditorAccess(Request $request, User $user, AuditorCompanyAccess $access)
    {
        $this->ensureAuditorAccessManager($request->user(), $user);
        abort_unless($access->auditor_id === $user->id, 404);

        $data = $this->validateAuditorAccess($request, false);
        $access->update($data);

        return redirect()->back()->with('success', 'Zakres dostępu został zaktualizowany.');
    }

    public function destroyAuditorAccess(Request $request, User $user, AuditorCompanyAccess $access)
    {
        $this->ensureAuditorAccessManager($request->user(), $user);
        abort_unless($access->auditor_id === $user->id, 404);

        $access->delete();

        return redirect()->back()->with('success', 'Przydział firmy został usunięty.');
    }

    public function assignAuditorDocument(Request $request, User $user)
    {
        $this->ensureAuditorAccessManager($request->user(), $user);

        $data = $request->validate(['document_id' => ['required', 'exists:documents,id']]);
        AuditorDocumentAccess::firstOrCreate(['user_id' => $user->id, 'document_id' => $data['document_id']]);

        return redirect()->back()->with('success', 'Dokument został przydzielony audytorowi.');
    }

    private function validateAuditorAccess(Request $request, bool $includeCompany = true): array
    {
        $rules = [
            'can_view_dashboard' => ['nullable', 'boolean'],
            'can_view_audits' => ['nullable', 'boolean'],
            'can_view_offer_requests' => ['nullable', 'boolean'],
            'can_view_offers' => ['nullable', 'boolean'],
            'can_view_offer_prices' => ['nullable', 'boolean'],
            'can_view_documents' => ['nullable', 'boolean'],
            'can_view_chat' => ['nullable', 'boolean'],
        ];

        if ($includeCompany) {
            $rules['company_id'] = ['required', 'exists:companies,id'];
        }

        $data = $request->validate($rules);

        foreach (array_keys($rules) as $field) {
            if ($field !== 'company_id') {
                $data[$field] = $request->boolean($field);
            }
        }

        return $data;
    }

    private function ensureAuditorAccessManager(User $manager, User $auditor): void
    {
        abort_unless($auditor->hasRole('auditor'), 403);
        $this->ensureCanManageUser($manager, $auditor);
    }

    public function create()
    {
        return redirect()->route('settings.users.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'password' => ['nullable', 'string', 'min:8'],
            'send_email' => ['nullable', 'boolean'],
            'has_employment_contract' => ['nullable', 'boolean'],
            'leave_year' => ['nullable', 'required_if:has_employment_contract,1', 'integer', 'between:2020,2100'],
            'leave_entitled_days' => ['nullable', 'required_if:has_employment_contract,1', 'integer', 'between:0,366'],
        ], [
            'name.required' => 'Imię i nazwisko jest wymagane.',
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email' => 'Podaj prawidłowy adres e-mail.',
            'email.unique' => 'Ten adres e-mail jest już zajęty.',
            'role.required' => 'Wybierz rolę użytkownika.',
            'role.in' => 'Wybrana rola jest nieprawidłowa.',
            'password.min' => 'Hasło musi mieć co najmniej 8 znaków.',
        ]);

        $this->ensureCanAssignRole($request->user(), $data['role']);

        $generatedPassword = empty($data['password']);
        $temporaryPassword = $data['password'] ?? str()->random(16);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($temporaryPassword),
            'is_active' => true,
            'has_employment_contract' => $request->boolean('has_employment_contract'),
        ]);

        if ($user->has_employment_contract) {
            $user->leaveEntitlements()->updateOrCreate(
                ['year' => $data['leave_year']],
                ['entitled_days' => $data['leave_entitled_days']]
            );
        }

        $user->assignRole($data['role']);

        // Auto-assign owner (Enesa) company for all staff roles
        if ($this->isStaffRole($data['role'])) {
            $ownerCompany = Company::owner()->first() ?? $this->ensureOwnerCompany();
            if ($ownerCompany && ! $user->companies()->where('companies.id', $ownerCompany->id)->exists()) {
                $user->companies()->attach($ownerCompany->id);
            }
        }

        $response = redirect()->route('settings.users.index')
            ->with('success', 'Użytkownik został utworzony. Wiadomość e-mail nie została wysłana.');
        $mailSent = false;
        if ($request->boolean('send_email')) {
            $problem = app(TaskReminderMailConfiguration::class)->problem();
            if ($problem) {
                $response->with('error', 'Konto zostało utworzone, ale wiadomość nie została wysłana. Sprawdź konfigurację poczty.');
            } else {
                try {
                    $driver = config('mail.default');
                    if (config('mail.mailers.'.$driver.'.transport') === 'smtp' && ! config('mail.mailers.'.$driver.'.timeout')) {
                        config(['mail.mailers.'.$driver.'.timeout' => 30]);
                    }
                    Mail::to($user->email)->send(new UserAccountCreated(
                        $user->name, $user->email, $temporaryPassword,
                        CompanySettings::first()?->name ?? config('app.name'), route('login'),
                    ));
                    $mailSent = true;
                    $response->with('success', 'Użytkownik został utworzony. Wiadomość z danymi logowania została przekazana do wysyłki.');
                } catch (Throwable $error) {
                    // Provider errors may contain the message, including its password.
                    Log::warning('user_account_created.mail_failed', ['user_id' => $user->id, 'error_type' => get_class($error)]);
                    $response->with('success', 'Użytkownik został utworzony.')
                        ->with('error', 'Nie udało się potwierdzić wysyłki danych logowania. Sprawdź pocztę u dostawcy przed ponowieniem.');
                }
            }
        }
        if ($generatedPassword && ! $mailSent) {
            $response->with('new_user_credentials', ['login' => $user->email, 'password' => $temporaryPassword]);
        }

        return $response;
    }

    public function show(string $id)
    {
        return redirect()->route('settings.users.index');
    }

    public function edit(string $id)
    {
        return redirect()->route('settings.users.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $this->ensureCanManageUser($request->user(), $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'password' => ['nullable', 'string', 'min:8'],
            'has_employment_contract' => ['nullable', 'boolean'],
            'leave_year' => ['nullable', 'required_if:has_employment_contract,1', 'integer', 'between:2020,2100'],
            'leave_entitled_days' => ['nullable', 'required_if:has_employment_contract,1', 'integer', 'between:0,366'],
        ], [
            'name.required' => 'Imię i nazwisko jest wymagane.',
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email' => 'Podaj prawidłowy adres e-mail.',
            'email.unique' => 'Ten adres e-mail jest już zajęty.',
            'role.required' => 'Wybierz rolę użytkownika.',
            'role.in' => 'Wybrana rola jest nieprawidłowa.',
            'password.min' => 'Hasło musi mieć co najmniej 8 znaków.',
        ]);

        $this->ensureCanAssignRole($request->user(), $data['role']);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->has_employment_contract = $request->boolean('has_employment_contract');

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (! empty($data['password'])) {
            app(AccountSecurityService::class)->revoke($user);
        }

        if ($user->has_employment_contract) {
            $user->leaveEntitlements()->updateOrCreate(
                ['year' => $data['leave_year']],
                ['entitled_days' => $data['leave_entitled_days']]
            );
        }

        if (! $user->hasRole('superadmin')) {
            $user->syncRoles([$data['role']]);
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'Dane użytkownika zaktualizowane.');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();
        $targetRole = $user->roles->first()?->name ?? 'none';

        // Superadmin protection - cannot be deleted
        if ($user->hasRole('superadmin')) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można usunąć superadmina.');
        }

        $this->ensureCanManageUser($currentUser, $user);

        // Permission hierarchy check
        if ($currentUser->hasRole('admin') && ! $currentUser->hasRole('superadmin')) {
            // Admin cannot delete other admins or superadmins
            if (in_array($targetRole, ['admin', 'superadmin'])) {
                return redirect()->route('settings.users.index')
                    ->with('error', 'Nie masz uprawnień do usunięcia tego użytkownika.');
            }
        } elseif (! $currentUser->hasRole('admin') && ! $currentUser->hasRole('superadmin') && ! $currentUser->hasRole('auditor_senior')) {
            // Only admin and superadmin can delete users
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie masz uprawnień do usunięcia użytkowników.');
        }

        $user->delete();

        return redirect()->route('settings.users.index')
            ->with('success', 'Użytkownik został zarchiwizowany.');
    }

    public function restore(User $user)
    {
        $this->ensureCanManageUser(auth()->user(), $user);

        $user->restore();

        return redirect()->back()->with('success', 'Użytkownik został przywrócony.');
    }

    public function forceDestroy(Request $request, User $user)
    {
        $currentUser = auth()->user();
        $targetRole = $user->roles->first()?->name ?? 'none';

        // Superadmin protection
        if ($user->hasRole('superadmin')) {
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie można usunąć superadmina.');
        }

        $this->ensureCanManageUser($currentUser, $user);

        // Permission hierarchy check
        if ($currentUser->hasRole('admin') && ! $currentUser->hasRole('superadmin')) {
            // Admin cannot delete other admins
            if (in_array($targetRole, ['admin', 'superadmin'])) {
                return redirect()->route('settings.users.index')
                    ->with('error', 'Nie masz uprawnień do usunięcia tego użytkownika.');
            }
        } elseif (! $currentUser->hasRole('admin') && ! $currentUser->hasRole('superadmin') && ! $currentUser->hasRole('auditor_senior')) {
            // Only admin and superadmin can delete users
            return redirect()->route('settings.users.index')
                ->with('error', 'Nie masz uprawnień do usunięcia użytkowników.');
        }

        $name = $user->name;
        $request->validate(['confirm_delete' => ['required', 'accepted']]);
        try {
            DB::transaction(function () use ($user) {
                $user = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
                abort_unless($user->trashed(), 422, 'Najpierw przenieś konto użytkownika do archiwum.');
                abort_unless($this->canForceDestroy(auth()->user(), $user), 403);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                DB::table('hr_vehicles')->where('type', 'private')->where(fn ($query) => $query->where('created_by', $user->id)->orWhere(fn ($legacy) => $legacy->whereNull('created_by')->where('user_id', $user->id)))->delete();
                $user->forceDelete();
            });
        } catch (QueryException $error) {
            if (! in_array((string) $error->getCode(), ['23000', '23503'], true)) {
                throw $error;
            }

            return redirect()->back()->with('error', 'Nie można usunąć konta powiązanego z chronionymi dokumentami, np. wystawionymi protokołami. Konto i jego e-mail zostały zachowane.');
        }

        return redirect()->back()->with('success', "Użytkownik $name został trwale usunięty. Jego adres e-mail można ponownie wykorzystać.");
    }

    public function canForceDestroy(User $manager, User $target): bool
    {
        return $target->trashed() && ! $target->hasRole('superadmin')
            && ($manager->hasRole('superadmin') || $manager->can('settings.users.manage'))
            && $manager->hasAnyRole(['superadmin', 'admin', 'auditor_senior'])
            && $this->roleRank($manager) > $this->roleRank($target);
    }

    public function assignToCompany(Request $request, User $user)
    {
        $this->ensureCanManageUser($request->user(), $user);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'role' => ['required', Rule::in(['client_admin', 'client_user'])],
        ]);

        $company = Company::findOrFail($data['company_id']);

        if ($user->trashed()) {
            $user->restore();
        }

        Role::findOrCreate($data['role']);
        $user->syncRoles([$data['role']]);

        $company->users()->syncWithoutDetaching([
            $user->id => [
                'is_admin' => $data['role'] === 'client_admin',
            ],
        ]);

        return redirect()->back()->with('success', 'Użytkownik został przypisany do firmy.');
    }

    public function assignCompany(Request $request, User $user)
    {
        $this->ensureCanManageUser($request->user(), $user);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::findOrFail($data['company_id']);

        if (! $user->hasAnyRole(['client_admin', 'client_user'])) {
            Role::findOrCreate('client_user');
            $user->assignRole('client_user');
        }

        $company->users()->syncWithoutDetaching([
            $user->id => [
                'is_admin' => false,
            ],
        ]);

        return redirect()->back()->with('success', 'Użytkownik został przypisany do firmy.');
    }

    private function ensureOwnerCompany(): ?Company
    {
        $settings = CompanySettings::first();
        if (! $settings || ! $settings->name) {
            return null;
        }

        return Company::updateOrCreate(
            ['is_owner' => true],
            [
                'name' => $settings->name,
                'nip' => $settings->nip,
                'email' => $settings->email,
                'phone' => $settings->phone,
                'address' => $settings->address,
                'city' => $settings->city,
                'is_owner' => true,
            ]
        );
    }

    private function ensureCanManageUser(User $manager, User $target): void
    {
        abort_unless(
            $this->roleRank($manager) > $this->roleRank($target),
            403,
            'Nie masz uprawnień do zarządzania tym użytkownikiem.'
        );
    }

    private function ensureCanAssignRole(User $manager, string $role): void
    {
        abort_unless(
            $this->canAssignRole($manager, $role),
            403,
            'Nie masz uprawnień do nadania tej roli.'
        );
    }

    private function canAssignRole(User $manager, string $role): bool
    {
        // Only administrators may delegate roles created in the Roles screen.
        // A senior auditor must never be able to turn somebody into a full-access user.
        if (! array_key_exists($role, self::ROLE_RANKS)) {
            return $manager->hasAnyRole(['superadmin', 'admin']) || $manager->can('settings.users.manage');
        }

        return $this->roleRank($manager) > (self::ROLE_RANKS[$role] ?? PHP_INT_MAX);
    }

    private function roleRank(User $user): int
    {
        return collect($user->getRoleNames())
            ->map(fn (string $role) => self::ROLE_RANKS[$role] ?? 35)
            ->max() ?? 0;
    }

    private function isStaffRole(string $role): bool
    {
        return ! in_array($role, ['client_admin', 'client_user'], true);
    }
}
