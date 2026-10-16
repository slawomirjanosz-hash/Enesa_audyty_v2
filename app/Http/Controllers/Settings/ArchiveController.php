<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\ArchivedCompanyDeletion;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveController extends Controller
{
    public function index(UserController $userController)
    {
        $archivedCompanies = Company::archived()->orderBy('name')->get();
        $archivedStaff = User::onlyTrashed()->with('roles')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')->get();
        $archivedClients = User::onlyTrashed()->with(['roles', 'allCompanies'])
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')->get();

        $deletableUserIds = $archivedStaff->concat($archivedClients)
            ->filter(fn (User $user) => $userController->canForceDestroy(auth()->user(), $user))
            ->pluck('id')->all();

        return view('settings.archive.index', compact('archivedCompanies', 'archivedStaff', 'archivedClients', 'deletableUserIds'));
    }

    public function destroyCompany(Request $request, Company $company, ArchivedCompanyDeletion $deletion): RedirectResponse
    {
        $this->authorize('delete', $company);
        abort_unless($request->user()->hasAnyRole(['admin', 'superadmin']) || $request->user()->can('system.full_access'), 403);
        $request->validate(['confirm_delete' => ['required', 'accepted']]);
        try {
            return DB::transaction(function () use ($company, $deletion) {
                $company = Company::lockForUpdate()->findOrFail($company->id);
                if (! $company->archived_at || $company->is_owner) {
                    return redirect()->route('settings.archive.index')->with('error', 'Można usunąć wyłącznie zarchiwizowaną firmę klienta lub dostawcy. Firma właściciela jest chroniona.');
                }
                $deletion->purge($company);

                return redirect()->route('settings.archive.index')->with('success', 'Firma i jej historia zostały trwale usunięte. Konta użytkowników zostały zachowane.');
            });
        } catch (QueryException $error) {
            if (! in_array((string) $error->getCode(), ['23000', '23503'], true)) {
                throw $error;
            }

            return redirect()->route('settings.archive.index')->with('error', 'Firma ma chronione powiązania z innymi danymi. Nie została usunięta.');
        }
    }
}
