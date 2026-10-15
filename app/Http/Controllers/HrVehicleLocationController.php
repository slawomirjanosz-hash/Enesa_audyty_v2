<?php

namespace App\Http\Controllers;

use App\Models\HrVehicleLocation;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrVehicleLocationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);
        HrVehicleLocation::create($request->validate(['name' => ['required', 'string', 'max:255', 'unique:hr_vehicle_locations,name']]));

        return $this->back();
    }

    public function update(Request $request, HrVehicleLocation $location): RedirectResponse
    {
        $this->authorizeSettings($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('hr_vehicle_locations')->ignore($location)]]);
        DB::transaction(function () use ($location, $data) {
            $location = HrVehicleLocation::lockForUpdate()->findOrFail($location->id);
            DB::table('hr_vehicles')->where('usage_location', $location->name)->update(['usage_location' => $data['name'], 'updated_at' => now()]);
            $location->update($data);
        });

        return $this->back();
    }

    public function destroy(Request $request, HrVehicleLocation $location): RedirectResponse
    {
        $this->authorizeSettings($request);
        DB::transaction(function () use ($location) {
            $location = HrVehicleLocation::lockForUpdate()->findOrFail($location->id);
            if (DB::table('hr_vehicles')->where('usage_location', $location->name)->exists()) {
                throw ValidationException::withMessages(['name' => 'Lokalizacja jest przypisana do samochodu. Najpierw zmień jego lokalizację (także w autach nieaktywnych).']);
            }
            $location->delete();
        });

        return $this->back();
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless(HrAccess::canManageSettings($request->user()), 403);
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('hr.settings', ['section' => 'vehicles'])->with('success', 'Słownik lokalizacji został zapisany.');
    }
}
