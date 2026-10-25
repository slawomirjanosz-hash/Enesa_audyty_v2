<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\HrAttendance;
use App\Models\HrBusinessTrip;
use App\Models\HrLeave;
use App\Models\HrVehicle;
use App\Models\HrVehicleLocation;
use App\Models\User;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $canTeam = HrAccess::canViewTeam($user);
        $tab = in_array($request->string('tab')->toString(), ['delegations', 'leaves', 'attendance', 'vehicles'], true)
            ? $request->string('tab')->toString() : 'delegations';
        $canDelegations = $user->hasRole('superadmin') || $user->can('system.full_access') || $user->can('hr.delegations.view');
        $canLeaves = $user->hasRole('superadmin') || $user->can('system.full_access') || $user->canAny(['hr.leaves.view', 'hr.leaves.accounting']);
        $canAttendance = $user->hasRole('superadmin') || $user->can('system.full_access') || $user->can('hr.attendance.view');
        if (in_array($tab, ['delegations', 'vehicles'], true) && ! $canDelegations) {
            $tab = 'attendance';
        }
        if ($tab === 'attendance' && ! $canAttendance) {
            $tab = 'delegations';
        }
        if ($tab === 'leaves' && ! $canLeaves) {
            $tab = $canDelegations ? 'delegations' : 'attendance';
        }
        if (! $canDelegations && ! $canAttendance && $canLeaves) {
            $tab = 'leaves';
        }
        if ($request->boolean('settings') && HrAccess::canManageSettings($user)) {
            return redirect()->route('hr.settings', ['section' => $tab]);
        }
        $selectedUserId = $canTeam && $request->integer('user_id') ? $request->integer('user_id') : $user->id;
        $leaveYear = min(2100, max(2020, $request->integer('leave_year', now()->year)));

        $users = $canTeam ? User::query()->where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get() : collect([$user]);
        $trips = $canDelegations ? HrBusinessTrip::with(['user', 'vehicle'])->when(! $canTeam, fn ($q) => $q->where('user_id', $user->id))->when($canTeam && $request->integer('user_id'), fn ($q) => $q->where('user_id', $selectedUserId))->latest('departure_at')->get() : collect();
        $canAllLeaves = HrAccess::canViewAllLeaves($user);
        $hasReports = HrLeave::where('approver_id', $user->id)->exists() || User::where('supervisor_id', $user->id)->exists();
        $leaveScope = $request->input('leave_scope', $canAllLeaves ? 'all' : 'own');
        abort_unless(in_array($leaveScope, ['own', 'team', 'all'], true) && ($leaveScope !== 'all' || $canAllLeaves) && ($leaveScope !== 'team' || $hasReports), 403);
        $leaves = $canLeaves ? HrLeave::with('user')
            ->when($leaveScope === 'own', fn ($q) => $q->where('user_id', $user->id))
            ->when($leaveScope === 'team', fn ($q) => $q->where('approver_id', $user->id))
            ->when($leaveScope === 'all' && $request->integer('user_id'), fn ($q) => $q->where('user_id', $selectedUserId))
            ->latest('start_date')->get() : collect();
        $leaveRecipients = $users->mapWithKeys(function (User $person) {
            $manager = $person->supervisor;
            $valid = $manager && $manager->id !== $person->id && $manager->is_active && ! $manager->hasAnyRole(['client_admin', 'client_user']) && ($manager->hasRole('superadmin') || $manager->canAny(['hr.leaves.view', 'system.full_access']));

            return [$person->id => $valid ? $manager->name : null];
        });

        $attendances = $canAttendance ? HrAttendance::with('user')->when(! $canTeam, fn ($q) => $q->where('user_id', $user->id))->when($canTeam && $request->integer('user_id'), fn ($q) => $q->where('user_id', $selectedUserId))->latest('work_date')->get() : collect();
        $vehicles = $canDelegations ? HrVehicle::with('user')->where('is_active', true)->visibleTo($user)->orderBy('type')->orderBy('name')->get() : collect();
        $rateOwnerId = $canTeam ? $selectedUserId : $user->id;
        $hrSettings = CompanySettings::query()->first(['hr_km_rate', 'hr_diet_rate']);
        $defaultKmRate = (float) ($hrSettings?->hr_km_rate ?? 0);
        $defaultDietRate = (float) ($hrSettings?->hr_diet_rate ?? 45);
        $defaultOrigin = HrBusinessTrip::where('user_id', $rateOwnerId)->latest()->value('origin') ?? '';
        $canManageHrSettings = HrAccess::canManageSettings($user);
        $vehicleLocations = HrVehicleLocation::orderBy('name')->get();
        $leaveBalanceUser = $canTeam && ! $request->integer('user_id') ? null : User::find($selectedUserId);
        $leaveBalance = $leaveBalanceUser?->has_employment_contract
            ? $leaveBalanceUser->annualLeaveBalance($leaveYear)
            : null;

        return view('hr.index', compact('canAllLeaves', 'hasReports', 'leaveScope', 'leaveRecipients', 'vehicleLocations', 'tab', 'users', 'trips', 'leaves', 'attendances', 'vehicles', 'canTeam', 'canDelegations', 'canLeaves', 'canAttendance', 'selectedUserId', 'defaultKmRate', 'defaultDietRate', 'defaultOrigin', 'canManageHrSettings', 'leaveYear', 'leaveBalance'));
    }

    public function settings(Request $request): View
    {
        abort_unless(HrAccess::canManageSettings($request->user()), 403);
        $sections = ['delegations' => 'Delegacje', 'leaves' => 'Urlopy', 'attendance' => 'Lista obecności', 'vehicles' => 'Samochody'];
        $section = array_key_exists($request->string('section')->toString(), $sections) ? $request->string('section')->toString() : 'delegations';
        $settings = CompanySettings::first();
        $vehicleLocations = HrVehicleLocation::orderBy('name')->get();

        return view('hr.settings', compact('sections', 'section', 'settings', 'vehicleLocations'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless(HrAccess::canManageSettings($request->user()), 403);
        $data = $request->validate([
            'hr_km_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'hr_diet_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
        ]);
        CompanySettings::query()->firstOrCreate(
            ['id' => 1],
            ['name' => config('app.name', 'Firma')]
        )->update($data);

        return redirect()->route('hr.settings', ['section' => 'delegations'])->with('success', 'Ustawienia HR zostały zapisane.');
    }
}
