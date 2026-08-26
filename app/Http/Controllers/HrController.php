<?php

namespace App\Http\Controllers;

use App\Models\HrAttendance;
use App\Models\HrBusinessTrip;
use App\Models\HrVehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canTeam = $this->canViewTeam($user);
        $tab = in_array($request->string('tab')->toString(), ['delegations', 'attendance', 'vehicles'], true)
            ? $request->string('tab')->toString() : 'delegations';
        $canDelegations = $user->hasRole('superadmin') || $user->can('system.full_access') || $user->can('hr.delegations.view');
        $canAttendance = $user->hasRole('superadmin') || $user->can('system.full_access') || $user->can('hr.attendance.view');
        if (in_array($tab, ['delegations', 'vehicles'], true) && ! $canDelegations) {
            $tab = 'attendance';
        }
        if ($tab === 'attendance' && ! $canAttendance) {
            $tab = 'delegations';
        }
        $selectedUserId = $canTeam && $request->integer('user_id') ? $request->integer('user_id') : $user->id;

        $users = $canTeam ? User::query()->where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client_admin', 'client_user']))->orderBy('name')->get() : collect([$user]);
        $trips = $canDelegations ? HrBusinessTrip::with(['user', 'vehicle'])->when(! $canTeam, fn ($q) => $q->where('user_id', $user->id))->when($canTeam && $request->integer('user_id'), fn ($q) => $q->where('user_id', $selectedUserId))->latest('departure_at')->get() : collect();
        $attendances = $canAttendance ? HrAttendance::with('user')->when(! $canTeam, fn ($q) => $q->where('user_id', $user->id))->when($canTeam && $request->integer('user_id'), fn ($q) => $q->where('user_id', $selectedUserId))->latest('work_date')->get() : collect();
        $vehicles = $canDelegations ? HrVehicle::with('user')->where('is_active', true)->where(fn ($q) => $q->where('type', 'company')->orWhere('user_id', $user->id)->when($canTeam, fn ($inner) => $inner->orWhereNotNull('user_id')))->orderBy('type')->orderBy('name')->get() : collect();

        return view('hr.index', compact('tab', 'users', 'trips', 'attendances', 'vehicles', 'canTeam', 'canDelegations', 'canAttendance', 'selectedUserId'));
    }

    public function storeTrip(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('trip', [
            'user_id' => ['nullable', 'exists:users,id'], 'purpose' => ['required', 'string', 'max:500'],
            'departure_at' => ['required', 'date'], 'return_at' => ['nullable', 'date', 'after_or_equal:departure_at'],
            'travel_hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'], 'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'], 'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'vehicle_id' => ['nullable', 'exists:hr_vehicles,id'], 'vehicle_type' => ['required', 'in:private,company'],
            'vehicle_name' => ['nullable', 'string', 'max:255'], 'registration_number' => ['nullable', 'string', 'max:30'],
            'toll_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'notes' => ['nullable', 'string', 'max:3000'],
            'remember_vehicle' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $targetUserId = $this->canViewTeam($user) && ! empty($data['user_id']) ? (int) $data['user_id'] : $user->id;
        $vehicle = ! empty($data['vehicle_id']) ? HrVehicle::find($data['vehicle_id']) : null;
        if ($vehicle) {
            $data['vehicle_type'] = $vehicle->type;
            $data['vehicle_name'] = $vehicle->name;
            $data['registration_number'] = $vehicle->registration_number;
        }
        if (empty($data['return_at']) && ! empty($data['travel_hours'])) {
            $data['return_at'] = Carbon::parse($data['departure_at'])->addMinutes((int) round($data['travel_hours'] * 60));
        }
        $departure = Carbon::parse($data['departure_at']);
        $return = ! empty($data['return_at']) ? Carbon::parse($data['return_at']) : $departure;
        $data['days'] = max(1, $departure->startOfDay()->diffInDays($return->copy()->startOfDay()) + 1);
        $data['user_id'] = $targetUserId;
        $data['created_by'] = $user->id;
        $data['distance_source'] = 'manual';
        unset($data['remember_vehicle']);
        HrBusinessTrip::create($data);

        if ($request->boolean('remember_vehicle') && ! $vehicle && ! empty($data['registration_number'])) {
            HrVehicle::firstOrCreate(['registration_number' => $data['registration_number'], 'user_id' => $data['vehicle_type'] === 'private' ? $targetUserId : null], ['type' => $data['vehicle_type'], 'name' => $data['vehicle_name'] ?: $data['registration_number']]);
        }

        return $this->back('delegations', 'Delegacja została dodana.');
    }

    public function destroyTrip(Request $request, HrBusinessTrip $trip): RedirectResponse
    {
        abort_unless($trip->user_id === $request->user()->id || $this->canViewTeam($request->user()), 403);
        $trip->delete();

        return $this->back('delegations', 'Delegacja została usunięta.');
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('attendance', ['user_id' => ['nullable', 'exists:users,id'], 'work_date' => ['required', 'date'], 'started_at' => ['nullable', 'date_format:H:i'], 'finished_at' => ['nullable', 'date_format:H:i', 'after:started_at'], 'status' => ['required', 'in:present,remote,leave,sick,absent'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $userId = $this->canViewTeam($request->user()) && ! empty($data['user_id']) ? (int) $data['user_id'] : $request->user()->id;
        $data['user_id'] = $userId;
        HrAttendance::updateOrCreate(['user_id' => $userId, 'work_date' => $data['work_date']], $data);

        return $this->back('attendance', 'Wpis na liście obecności został zapisany.');
    }

    public function destroyAttendance(Request $request, HrAttendance $attendance): RedirectResponse
    {
        abort_unless($attendance->user_id === $request->user()->id || $this->canViewTeam($request->user()), 403);
        $attendance->delete();

        return $this->back('attendance', 'Wpis obecności został usunięty.');
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('vehicle', ['user_id' => ['nullable', 'exists:users,id'], 'type' => ['required', 'in:private,company'], 'name' => ['required', 'string', 'max:255'], 'registration_number' => ['required', 'string', 'max:30'], 'make_model' => ['nullable', 'string', 'max:255']]);
        $canTeam = $this->canViewTeam($request->user());
        abort_if($data['type'] === 'company' && ! $canTeam, 403);
        $data['user_id'] = $data['type'] === 'company' ? null : ($canTeam && ! empty($data['user_id']) ? $data['user_id'] : $request->user()->id);
        HrVehicle::create($data);

        return $this->back('vehicles', 'Samochód został dodany.');
    }

    public function destroyVehicle(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $request->user()->id || $this->canViewTeam($request->user()), 403);
        $vehicle->update(['is_active' => false]);

        return $this->back('vehicles', 'Samochód został usunięty z aktywnej listy.');
    }

    private function canViewTeam(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']) || $user->can('system.full_access') || $user->can('hr.team.view');
    }

    private function back(string $tab, string $message): RedirectResponse
    {
        return redirect()->route('hr.index', ['tab' => $tab])->with('success', $message);
    }
}
