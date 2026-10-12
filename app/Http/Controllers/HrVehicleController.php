<?php

namespace App\Http\Controllers;

use App\Models\HrVehicle;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrVehicleController extends Controller
{
    public function storeVehicle(Request $request): RedirectResponse
    {
        $data = $this->vehicleData($request);
        $canTeam = HrAccess::canViewTeam($request->user());
        abort_if($data['type'] === 'company' && ! $canTeam, 403);
        $data['user_id'] = $data['type'] === 'company' ? null : $request->user()->id;
        $data['created_by'] = $request->user()->id;
        HrVehicle::create($data);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Samochód został dodany.');
    }

    public function destroyVehicle(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->canEdit($request->user()), 403);
        $vehicle->update(['is_active' => false]);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Samochód został usunięty z aktywnej listy.');
    }

    public function edit(Request $request, HrVehicle $vehicle): View
    {
        abort_unless($vehicle->canEdit($request->user()), 403);
        abort_unless($vehicle->is_active, 404);

        return view('hr.vehicle-edit', ['vehicle' => $vehicle, 'canTeam' => HrAccess::canViewTeam($request->user())]);
    }

    public function update(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->canEdit($request->user()), 403);
        abort_unless($vehicle->is_active, 404);
        $data = $this->vehicleData($request, $vehicle);
        // Changing ownership/type of an existing record could expose private data.
        unset($data['type']);
        $vehicle->update($data);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Dane samochodu zostały zapisane.');
    }

    public function columns(Request $request): RedirectResponse
    {
        $data = $request->validate(['columns' => ['required', 'array', 'min:1'], 'columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys(HrVehicle::COLUMNS))]]);
        $request->user()->forceFill(['hr_vehicle_columns' => array_values(array_unique(array_merge(['name', 'registration_number'], $data['columns'])))])->save();

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Twój widok aut został zapisany.');
    }

    private function vehicleData(Request $request, ?HrVehicle $vehicle = null): array
    {
        $owner = $vehicle?->user_id ?? $request->user()->id;

        return $request->validateWithBag('vehicle', [
            'type' => [$vehicle ? 'sometimes' : 'required', Rule::in(['private', 'company'])],
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:30', Rule::unique('hr_vehicles')->where(fn ($q) => $q->where('user_id', ($vehicle?->type ?? $request->input('type')) === 'company' ? null : $owner))->ignore($vehicle)],
            'make_model' => ['nullable', 'string', 'max:255'],
            'fuel_card_number' => ['nullable', 'string', 'max:50'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'usage_location' => ['nullable', 'string', 'max:255'],
            'vat_percent' => ['nullable', Rule::in([50, 100])],
            'insurer' => ['nullable', 'string', 'max:150'],
            'insurance_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'vat_26' => ['nullable', 'boolean'],
            'cit_percent' => ['nullable', 'numeric', 'between:0,100'],
            'gps' => ['nullable', 'boolean'],
            'logbook_entry_at' => ['nullable', 'date_format:Y-m-d'],
            'service_book_entry_at' => ['nullable', 'date_format:Y-m-d'],
            'service_book_status' => ['nullable', Rule::in(['available', 'missing'])],
            'inspection_due_at' => ['nullable', 'date_format:Y-m-d'],
            'registration_document' => ['nullable', 'string', 'max:255'],
            'registration_document_present' => ['nullable', 'boolean'],
        ]);
    }
}
