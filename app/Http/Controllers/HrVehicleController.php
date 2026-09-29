<?php

namespace App\Http\Controllers;

use App\Models\HrVehicle;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HrVehicleController extends Controller
{
    public function storeVehicle(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('vehicle', ['user_id' => ['nullable', 'exists:users,id'], 'type' => ['required', 'in:private,company'], 'name' => ['required', 'string', 'max:255'], 'registration_number' => ['required', 'string', 'max:30'], 'make_model' => ['nullable', 'string', 'max:255']]);
        $canTeam = HrAccess::canViewTeam($request->user());
        abort_if($data['type'] === 'company' && ! $canTeam, 403);
        $data['user_id'] = $data['type'] === 'company' ? null : ($canTeam && ! empty($data['user_id']) ? $data['user_id'] : $request->user()->id);
        HrVehicle::create($data);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Samochód został dodany.');
    }

    public function destroyVehicle(Request $request, HrVehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $request->user()->id || HrAccess::canViewTeam($request->user()), 403);
        $vehicle->update(['is_active' => false]);

        return redirect()->route('hr.index', ['tab' => 'vehicles'])->with('success', 'Samochód został usunięty z aktywnej listy.');
    }
}
