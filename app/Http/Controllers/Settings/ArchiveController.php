<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;

class ArchiveController extends Controller
{
    public function index()
    {
        $archivedCompanies = Company::archived()->orderBy('name')->get();
        $archivedStaff = User::onlyTrashed()->with('roles')
            ->whereHas('roles', fn ($query) => $query->whereNotIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')->get();
        $archivedClients = User::onlyTrashed()->with(['roles', 'companies'])
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['client_admin', 'client_user']))
            ->orderByDesc('deleted_at')->get();

        return view('settings.archive.index', compact('archivedCompanies', 'archivedStaff', 'archivedClients'));
    }
}
