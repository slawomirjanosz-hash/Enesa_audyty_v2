<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HrVehicle;
use App\Models\HrVehicleServiceEntry;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrVehicleServiceController extends Controller
{
    public function index(Request $request, HrVehicle $vehicle): View
    {
        abort_unless($vehicle->is_active && HrVehicle::visibleTo($request->user())->whereKey($vehicle->id)->exists(), 404);

        return view('hr.vehicle-service', [
            'vehicle' => $vehicle,
            'canEdit' => $vehicle->canMaintainServiceBook($request->user()),
            'canDelete' => HrAccess::canViewTeam($request->user()),
            'entries' => $vehicle->serviceEntries()->with('author')->orderByDesc('performed_on')->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->canMaintainServiceBook($request->user()), 403);
        abort_unless($vehicle->is_active, 404);
        $data = $request->validate([
            'performed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'mileage' => ['required', 'integer', 'min:0', 'max:9999999'],
            'type' => ['required', Rule::in(array_keys(HrVehicleServiceEntry::TYPES))],
            'description' => ['nullable', 'required_if:type,other', 'string', 'max:5000'],
        ]);
        $entry = new HrVehicleServiceEntry($data);
        $entry->created_by = $request->user()->id;
        $vehicle->serviceEntries()->save($entry);

        return redirect()->route('hr.vehicles.service.index', $vehicle)->with('success', 'Wpis w książce serwisowej został zapisany.');
    }

    public function destroy(Request $request, HrVehicle $vehicle, int $entry): RedirectResponse
    {
        abort_unless(HrAccess::canViewTeam($request->user()), 403);
        abort_unless($vehicle->is_active && HrVehicle::visibleTo($request->user())->whereKey($vehicle->id)->exists(), 404);

        DB::transaction(function () use ($request, $vehicle, $entry) {
            $record = $vehicle->serviceEntries()->lockForUpdate()->findOrFail($entry);
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'deleted',
                'auditable_type' => HrVehicleServiceEntry::class,
                'auditable_id' => $record->id,
                'subject_label' => 'Usunięty wpis serwisowy: '.$vehicle->registration_number,
                'changes' => ['old' => $record->only(['hr_vehicle_id', 'performed_on', 'mileage', 'type', 'description', 'created_by'])],
            ]);
            $record->delete();
        });

        return redirect()->route('hr.vehicles.service.index', $vehicle)->with('success', 'Wpis w książce serwisowej został usunięty.');
    }
}
