<?php

namespace App\Http\Controllers;

use App\Models\HrVehicle;
use App\Models\HrVehicleColumnResponsibility;
use App\Models\User;
use App\Services\HrVehicleLocationPlan;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HrVehicleController extends Controller
{
    public function responsibility(Request $request, string $column): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['admin', 'superadmin']), 403);
        abort_unless(array_key_exists($column, HrVehicle::COLUMNS), 404);
        $eligible = HrVehicleColumnResponsibility::eligiblePeople()->pluck('id')->all();
        $data = $request->validate([
            'user_ids' => ['sometimes', 'array', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'distinct', Rule::in($eligible)],
            'revision' => ['required', 'integer', 'min:1'],
        ], ['user_ids.*.in' => 'Wybierz aktywną osobę z uprawnieniem odpowiedzialności za samochody.']);
        DB::transaction(function () use ($column, $data, $request): void {
            $assignment = HrVehicleColumnResponsibility::where('column_key', $column)->lockForUpdate()->firstOrFail();
            abort_unless($assignment->revision === (int) $data['revision'], 409, 'Przypisanie zostało zmienione. Odśwież tabelę samochodów.');
            $ids = array_map('intval', $data['user_ids'] ?? []);
            sort($ids);
            if ($assignment->user_ids !== $ids) {
                $assignment->forceFill(['user_ids' => $ids, 'updated_by' => $request->user()->id, 'revision' => $assignment->revision + 1])->save();
            }
        });

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Zapisano odpowiedzialność za kolumnę „'.HrVehicle::COLUMNS[$column].'”.');
    }

    private function delegatedColumns(Request $request, HrVehicle $vehicle): array
    {
        return $vehicle->type === 'company'
            ? HrVehicleColumnResponsibility::editableColumns($request->user(), HrVehicleColumnResponsibility::all()->keyBy('column_key'))
            : [];
    }

    public function locationPlan(Request $request, HrVehicle $vehicle): View
    {
        abort_unless($vehicle->type === 'private' ? $vehicle->canEdit($request->user()) :
            HrVehicle::visibleTo($request->user())->whereKey($vehicle->id)->exists(), 403);
        abort_unless($vehicle->is_active, 404);
        $canEditLocation = $vehicle->canEdit($request->user()) || in_array('usage_location', $this->delegatedColumns($request, $vehicle), true);

        $modal = $request->header('X-Vehicle-Location-Modal') === '1';

        return view($modal ? 'hr.partials.location-plan-content' : 'hr.vehicle-locations', compact('vehicle', 'canEditLocation', 'modal'));
    }

    public function saveLocationPlan(Request $request, HrVehicle $vehicle): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless($vehicle->canEdit($request->user()) || in_array('usage_location', $this->delegatedColumns($request, $vehicle), true), 403);
        abort_unless($vehicle->is_active, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'periods' => ['sometimes', 'array', 'max:100'],
            'periods.*.id' => ['nullable', 'integer'],
            'periods.*.location' => ['required', 'string', 'max:255'],
            'periods.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'periods.*.ends_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $periods = array_values($data['periods'] ?? []);
        usort($periods, fn ($a, $b) => strcmp($a['starts_on'], $b['starts_on']));
        $lastEnd = null;
        foreach ($periods as $i => $period) {
            if ((! empty($period['ends_on']) && $period['ends_on'] < max($period['starts_on'], now('Europe/Warsaw')->toDateString()))
                || ($i > 0 && ($lastEnd === null || $period['starts_on'] <= $lastEnd))) {
                throw ValidationException::withMessages(['periods' => 'Daty muszą być uporządkowane i nie mogą się nakładać.']);
            }
            $lastEnd = $period['ends_on'] ?? null;
        }
        app(HrVehicleLocationPlan::class)->save($vehicle, $periods, (int) $data['revision']);

        if ($request->expectsJson()) {
            return response()->json(['location' => $vehicle->fresh()->usage_location]);
        }

        return redirect()->route('hr.vehicles.location-plan', $vehicle)->with('success', 'Zapisano przypisania lokalizacji.');
    }

    public function location(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->canEdit($request->user()) || in_array('usage_location', $this->delegatedColumns($request, $vehicle), true), 403);
        abort_unless($vehicle->is_active, 404);
        $data = $request->validate(['usage_location' => ['nullable', 'string', Rule::when($request->input('usage_location') !== 'Nie dotyczy', 'exists:hr_vehicle_locations,name')]]);
        abort_if((int) $vehicle->location_revision > 0, 422, 'Zmień lokalizację w planie przypisań.');
        $vehicle->update(['usage_location' => $data['usage_location'] ?? null]);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Lokalizacja samochodu została zapisana.');
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $data = $this->vehicleData($request);
        $canTeam = HrAccess::canViewTeam($request->user());
        abort_if($data['type'] === 'company' && ! $canTeam, 403);
        $data['user_id'] = $data['type'] === 'company' ? null : $request->user()->id;
        $data['created_by'] = $request->user()->id;
        $responsibleUserId = $data['responsible_user_id'] ?? null;
        unset($data['responsible_user_id']);
        $vehicle = new HrVehicle($data);
        $vehicle->responsible_user_id = $responsibleUserId;
        $vehicle->save();

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
        $editableColumns = $vehicle->canEdit($request->user()) ? null : $this->delegatedColumns($request, $vehicle);
        abort_if($editableColumns === [], 403);
        abort_unless($vehicle->is_active, 404);

        return view('hr.vehicle-edit', ['vehicle' => $vehicle, 'editableColumns' => $editableColumns]);
    }

    public function update(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        $editableColumns = $vehicle->canEdit($request->user()) ? null : $this->delegatedColumns($request, $vehicle);
        abort_if($editableColumns === [], 403);
        abort_unless($vehicle->is_active, 404);
        $data = $this->vehicleData($request, $vehicle, $editableColumns);
        if ((int) $vehicle->location_revision > 0) {
            unset($data['usage_location']);
        }
        // Changing ownership/type of an existing record could expose private data.
        unset($data['type']);
        if (array_key_exists('responsible_user_id', $data)) {
            $vehicle->responsible_user_id = $data['responsible_user_id'];
            unset($data['responsible_user_id']);
        }
        $vehicle->update($data);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Dane samochodu zostały zapisane.');
    }

    public function columns(Request $request): RedirectResponse
    {
        $data = $request->validate(['columns' => ['required', 'array', 'min:1'], 'columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys(HrVehicle::COLUMNS))]]);
        $request->user()->forceFill(['hr_vehicle_columns' => array_values(array_unique(array_merge(['name', 'registration_number'], $data['columns'])))])->save();

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Twój widok aut został zapisany.');
    }

    private function vehicleData(Request $request, ?HrVehicle $vehicle = null, ?array $editableColumns = null): array
    {
        $owner = $vehicle?->user_id ?? $request->user()->id;

        $rules = [
            'type' => [$vehicle ? 'sometimes' : 'required', Rule::in(['private', 'company'])],
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:30', Rule::unique('hr_vehicles')->where(fn ($q) => $q->where('user_id', ($vehicle?->type ?? $request->input('type')) === 'company' ? null : $owner))->ignore($vehicle)],
            'make_model' => ['nullable', 'string', 'max:255'],
            'fuel_card_number' => ['nullable', 'string', 'max:50'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('id', HrVehicle::eligibleResponsibleUsers()->select('users.id')))],
            'usage_location' => ['nullable', 'string', 'max:255'],
            'vat_percent' => ['nullable', Rule::in([50, 100])],
            'insurer' => ['nullable', 'string', 'max:150'],
            'insurance_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'vat_26' => ['nullable', 'boolean'],
            'cit_percent' => ['nullable', 'numeric', 'between:0,100'],
            'gps' => ['nullable', 'boolean'],
            'inspection_due_at' => ['nullable', 'date_format:Y-m-d'],
            'registration_document' => ['nullable', 'string', 'max:255'],
            'registration_document_present' => ['nullable', 'boolean'],
        ];
        if ($editableColumns !== null) {
            foreach ($rules as $field => $rule) {
                if (! in_array(HrVehicleColumnResponsibility::columnFor($field), $editableColumns, true)) {
                    abort_if($request->exists($field), 403);
                    unset($rules[$field]);
                } else {
                    $rules[$field] = ['sometimes', ...$rule];
                }
            }
        }
        $data = $request->validateWithBag('vehicle', $rules);
        if (! empty($data['usage_location']) && $data['usage_location'] !== 'Nie dotyczy' && $data['usage_location'] !== $vehicle?->usage_location) {
            validator($data, ['usage_location' => ['exists:hr_vehicle_locations,name']])->validateWithBag('vehicle');
        }
        if (($vehicle?->type ?? ($data['type'] ?? null)) === 'private' && ! empty($data['responsible_user_id'])) {
            abort_unless((int) $data['responsible_user_id'] === $request->user()->id, 403);
        }
        if (! empty($data['responsible_user_id'])) {
            $data['responsible_person'] = User::findOrFail($data['responsible_user_id'])->name;
        }

        return $data;
    }
}
