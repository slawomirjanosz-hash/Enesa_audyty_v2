<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\HrAttendance;
use App\Models\HrBusinessTrip;
use App\Models\HrVehicle;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
        $canAllVehicles = $this->canViewAllVehicles($user);
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
        $vehicles = $canDelegations ? HrVehicle::with('user')->where('is_active', true)->where(fn ($q) => $q->where('type', 'company')->orWhere('user_id', $user->id)->when($canAllVehicles, fn ($inner) => $inner->orWhereNotNull('user_id')))->orderBy('type')->orderBy('name')->get() : collect();
        $rateOwnerId = $canTeam ? $selectedUserId : $user->id;
        $hrSettings = CompanySettings::query()->first(['hr_km_rate', 'hr_diet_rate']);
        $defaultKmRate = (float) ($hrSettings?->hr_km_rate ?? 0);
        $defaultDietRate = (float) ($hrSettings?->hr_diet_rate ?? 45);
        $defaultOrigin = HrBusinessTrip::where('user_id', $rateOwnerId)->latest()->value('origin') ?? '';
        $canManageHrSettings = $user->hasRole(['superadmin', 'admin']);

        return view('hr.index', compact('tab', 'users', 'trips', 'attendances', 'vehicles', 'canTeam', 'canDelegations', 'canAttendance', 'canAllVehicles', 'selectedUserId', 'defaultKmRate', 'defaultDietRate', 'defaultOrigin', 'canManageHrSettings'));
    }

    public function storeTrip(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$data, $vehicle] = $this->tripData($request);
        $data['created_by'] = $user->id;
        HrBusinessTrip::create($data);

        if ($request->boolean('remember_vehicle') && ! $vehicle && ! empty($data['registration_number'])) {
            HrVehicle::firstOrCreate(['registration_number' => $data['registration_number'], 'user_id' => $data['vehicle_type'] === 'private' ? $data['user_id'] : null], ['type' => $data['vehicle_type'], 'name' => $data['vehicle_name'] ?: $data['registration_number']]);
        }

        return $this->back('delegations', 'Delegacja została dodana.');
    }

    public function updateTrip(Request $request, HrBusinessTrip $trip): RedirectResponse
    {
        abort_unless($trip->user_id === $request->user()->id || $this->canViewTeam($request->user()), 403);
        [$data] = $this->tripData($request, $trip->user_id);
        $trip->update($data);

        return $this->back('delegations', 'Delegacja została zaktualizowana.');
    }

    public function tripPdf(Request $request, HrBusinessTrip $trip): Response
    {
        abort_unless($trip->user_id === $request->user()->id || $this->canViewTeam($request->user()), 403);
        $trip->load(['user', 'vehicle']);

        $company = CompanySettings::query()->first();

        return Pdf::loadView('hr.trip-pdf', ['trip' => $trip, 'company' => $company, 'logo' => $company?->logoDataUri()])->setPaper('a4')
            ->download('delegacja-'.Str::slug($trip->user?->name ?: 'pracownik').'-'.$trip->departure_at->format('Y-m-d').'.pdf');
    }

    public function showTrip(Request $request, HrBusinessTrip $trip): View
    {
        abort_unless($trip->user_id === $request->user()->id || $this->canViewTeam($request->user()), 403);

        return view('hr.trip-show', ['trip' => $trip->load(['user', 'vehicle'])]);
    }

    public function calculateRoute(Request $request): JsonResponse
    {
        $data = $request->validate(['origin' => ['required', 'string', 'max:255'], 'destination' => ['required', 'string', 'max:255']]);
        $key = config('services.google.maps_key');
        if (! $key) {
            return response()->json(['message' => 'Brak klucza Google Maps API. Wpisz czas i kilometry ręcznie.'], 422);
        }
        $response = Http::timeout(12)->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration'])
            ->post('https://routes.googleapis.com/directions/v2:computeRoutes', ['origin' => ['address' => $data['origin']], 'destination' => ['address' => $data['destination']], 'travelMode' => 'DRIVE', 'routingPreference' => 'TRAFFIC_UNAWARE', 'languageCode' => 'pl-PL', 'units' => 'METRIC']);
        if (! $response->successful() || ! $response->json('routes.0')) {
            return response()->json(['message' => 'Nie udało się wyliczyć trasy. Wpisz wartości ręcznie.'], 422);
        }
        $seconds = (float) rtrim((string) $response->json('routes.0.duration', '0s'), 's');

        return response()->json(['distance_km' => round(((float) $response->json('routes.0.distanceMeters')) / 1000, 1), 'hours' => round($seconds / 3600, 2)]);
    }

    public function autocompletePlaces(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:150']]);
        $key = config('services.google.maps_key');
        if (! $key) {
            return response()->json(['suggestions' => []]);
        }

        $response = Http::timeout(8)->withHeaders([
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => 'suggestions.placePrediction.text.text',
        ])->post('https://places.googleapis.com/v1/places:autocomplete', [
            'input' => $data['q'],
            'includedRegionCodes' => ['pl'],
            'languageCode' => 'pl',
        ]);

        $suggestions = $response->successful()
            ? collect($response->json('suggestions', []))->pluck('placePrediction.text.text')->filter()->values()
            : collect();

        if ($suggestions->isEmpty()) {
            $legacyResponse = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input' => $data['q'],
                'components' => 'country:pl',
                'language' => 'pl',
                'key' => $key,
            ]);
            if ($legacyResponse->successful()) {
                $suggestions = collect($legacyResponse->json('predictions', []))->pluck('description')->filter()->values();
            }
        }

        return response()->json(['suggestions' => $suggestions->take(8)->all()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['superadmin', 'admin']), 403);
        $data = $request->validate([
            'hr_km_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'hr_diet_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
        ]);
        CompanySettings::query()->firstOrCreate(
            ['id' => 1],
            ['name' => config('app.name', 'Firma')]
        )->update($data);

        return $this->back('delegations', 'Ustawienia HR zostały zapisane.');
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

    private function canViewAllVehicles(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']) || $user->can('system.full_access') || $user->can('hr.vehicles.all.view');
    }

    private function tripData(Request $request, ?int $forcedUserId = null): array
    {
        if ($request->input('vehicle_id') === 'manual') {
            $request->merge(['vehicle_id' => null]);
        }
        $data = $request->validateWithBag('trip', [
            'user_id' => ['nullable', 'exists:users,id'], 'purpose' => ['required', 'string', 'max:500'],
            'departure_at' => ['required', 'date'], 'outbound_arrival_at' => ['required', 'date', 'after_or_equal:departure_at'],
            'return_departure_at' => ['required', 'date', 'after_or_equal:outbound_arrival_at'], 'return_at' => ['required', 'date', 'after_or_equal:return_departure_at'],
            'outbound_travel_hours' => ['required', 'numeric', 'min:0', 'max:999.99'], 'return_travel_hours' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'origin' => ['required', 'string', 'max:255'], 'destination' => ['required', 'string', 'max:255'], 'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'vehicle_id' => ['nullable', 'exists:hr_vehicles,id'], 'vehicle_type' => ['nullable', 'required_without:vehicle_id', 'in:private,company'],
            'vehicle_name' => ['nullable', 'string', 'max:255'], 'registration_number' => ['nullable', 'string', 'max:30'],
            'toll_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'accommodation_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'other_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'notes' => ['nullable', 'string', 'max:3000'], 'remember_vehicle' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $targetUserId = $forcedUserId ?? ($this->canViewTeam($user) && ! empty($data['user_id']) ? (int) $data['user_id'] : $user->id);
        $vehicle = ! empty($data['vehicle_id']) ? HrVehicle::find($data['vehicle_id']) : null;
        if ($vehicle) {
            abort_unless($vehicle->type === 'company' || $vehicle->user_id === $user->id || $this->canViewAllVehicles($user), 403);
            $data['vehicle_type'] = $vehicle->type;
            $data['vehicle_name'] = $vehicle->name;
            $data['registration_number'] = $vehicle->registration_number;
        }
        $departure = Carbon::parse($data['departure_at']);
        $return = Carbon::parse($data['return_at']);
        $hrSettings = CompanySettings::query()->first(['hr_km_rate', 'hr_diet_rate']);
        $data['km_rate'] = (float) ($hrSettings?->hr_km_rate ?? 0);
        $data['diet_rate'] = (float) ($hrSettings?->hr_diet_rate ?? 45);
        $durationMinutes = max(0, $departure->diffInMinutes($return));
        $data['days'] = max(1, (int) ceil($durationMinutes / 1440));
        $data['travel_hours'] = round((float) $data['outbound_travel_hours'] + (float) $data['return_travel_hours'], 2);
        $data['user_id'] = $targetUserId;
        $data['distance_source'] = 'manual';
        $data['mileage_amount'] = $data['vehicle_type'] === 'private'
            ? round((float) ($data['distance_km'] ?? 0) * (float) $data['km_rate'], 2)
            : 0;
        $data['diet_amount'] = $this->dietAmount($durationMinutes, (float) $data['diet_rate']);
        $data['toll_cost'] = (float) ($data['toll_cost'] ?? 0);
        $data['accommodation_cost'] = (float) ($data['accommodation_cost'] ?? 0);
        $data['other_cost'] = (float) ($data['other_cost'] ?? 0);
        $data['total_amount'] = round($data['mileage_amount'] + $data['diet_amount'] + $data['toll_cost'] + $data['accommodation_cost'] + $data['other_cost'], 2);
        unset($data['remember_vehicle']);

        return [$data, $vehicle];
    }

    private function dietAmount(int $minutes, float $rate): float
    {
        if ($minutes <= 1440) {
            return $minutes < 480 ? 0 : ($minutes <= 720 ? round($rate / 2, 2) : round($rate, 2));
        }
        $fullDays = intdiv($minutes, 1440);
        $remainder = $minutes % 1440;
        $multiplier = $fullDays + ($remainder === 0 ? 0 : ($remainder <= 480 ? .5 : 1));

        return round($rate * $multiplier, 2);
    }

    private function back(string $tab, string $message): RedirectResponse
    {
        return redirect()->route('hr.index', ['tab' => $tab])->with('success', $message);
    }
}
