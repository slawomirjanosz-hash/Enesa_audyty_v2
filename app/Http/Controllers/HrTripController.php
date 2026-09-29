<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\HrBusinessTrip;
use App\Models\HrVehicle;
use App\Support\HrAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HrTripController extends Controller
{
    public function storeTrip(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$data, $vehicle] = $this->tripData($request);
        $data['created_by'] = $user->id;
        HrBusinessTrip::create($data);

        if ($request->boolean('remember_vehicle') && ! $vehicle && ! empty($data['registration_number'])) {
            HrVehicle::firstOrCreate(['registration_number' => $data['registration_number'], 'user_id' => $data['vehicle_type'] === 'private' ? $data['user_id'] : null], ['type' => $data['vehicle_type'], 'name' => $data['vehicle_name'] ?: $data['registration_number']]);
        }

        return redirect()->route('hr.index', ['tab' => 'delegations'])->with('success', 'Delegacja została dodana.');
    }

    public function updateTrip(Request $request, HrBusinessTrip $trip): RedirectResponse
    {
        abort_unless($trip->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        [$data] = $this->tripData($request, $trip->user_id);
        $trip->update($data);

        return redirect()->route('hr.index', ['tab' => 'delegations'])->with('success', 'Delegacja została zaktualizowana.');
    }

    public function tripPdf(Request $request, HrBusinessTrip $trip): Response
    {
        abort_unless($trip->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $request->validate(['use_signature' => ['nullable', 'boolean']]);
        $employeeSignature = null;
        if ($request->boolean('use_signature')) {
            abort_unless($trip->user_id === $request->user()->id, 403);
            $employeeSignature = $request->user()->signatureDataUri();
            if (! $employeeSignature) {
                throw ValidationException::withMessages([
                    'use_signature' => 'Najpierw dodaj podpis w sekcji Mój profil.',
                ]);
            }
        }
        $trip->load(['user', 'vehicle']);

        $company = CompanySettings::query()->first();

        return Pdf::loadView('hr.trip-pdf', ['trip' => $trip, 'company' => $company, 'logo' => $company?->logoDataUri(), 'employeeSignature' => $employeeSignature])->setPaper('a4')
            ->download('delegacja-'.Str::slug($trip->user?->name ?: 'pracownik').'-'.$trip->departure_at->format('Y-m-d').'.pdf');
    }

    public function showTrip(Request $request, HrBusinessTrip $trip): View
    {
        abort_unless($trip->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);

        return view('hr.trip-show', ['trip' => $trip->load(['user', 'vehicle'])]);
    }

    public function destroyTrip(Request $request, HrBusinessTrip $trip): RedirectResponse
    {
        abort_unless($trip->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $trip->delete();

        return redirect()->route('hr.index', ['tab' => 'delegations'])->with('success', 'Delegacja została usunięta.');
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
        $targetUserId = $forcedUserId ?? (HrAccess::canViewTeam($user) && ! empty($data['user_id']) ? (int) $data['user_id'] : $user->id);
        $vehicle = ! empty($data['vehicle_id']) ? HrVehicle::find($data['vehicle_id']) : null;
        if ($vehicle) {
            abort_unless($vehicle->type === 'company' || $vehicle->user_id === $user->id || HrAccess::canViewAllVehicles($user), 403);
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
}
