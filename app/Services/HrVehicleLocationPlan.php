<?php

namespace App\Services;

use App\Models\HrVehicle;
use App\Models\HrVehicleLocationPeriod;
use Illuminate\Support\Facades\DB;

class HrVehicleLocationPlan
{
    public function save(HrVehicle $vehicle, array $periods, int $revision): void
    {
        DB::transaction(function () use ($vehicle, $periods, $revision) {
            $vehicle = HrVehicle::lockForUpdate()->findOrFail($vehicle->id);
            abort_unless((int) $vehicle->location_revision === $revision, 409, 'Lokalizacje zmieniły się. Odśwież okno.');
            $today = now('Europe/Warsaw')->toDateString();
            $yesterday = now('Europe/Warsaw')->subDay()->toDateString();
            $existing = $vehicle->locationPeriods()->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))->get();
            $kept = [];
            foreach ($existing as $old) {
                // An unchanged current interval keeps its original starting date.
                $match = collect($periods)->search(fn ($p) => (int) ($p['id'] ?? 0) === $old->id
                    && $p['location'] === $old->location && $p['starts_on'] === $old->starts_on->toDateString()
                );
                if ($match !== false) {
                    $old->update(['ends_on' => $periods[$match]['ends_on'] ?? null]);
                    $kept[] = $match;

                    continue;
                }
                if ($old->starts_on->toDateString() < $today) {
                    $replacement = collect($periods)->first(fn ($p) => (int) ($p['id'] ?? 0) === $old->id);
                    $cutoff = $replacement && $replacement['starts_on'] > $today
                        ? \Carbon\Carbon::parse($replacement['starts_on'])->subDay()->toDateString() : $yesterday;
                    $old->update(['ends_on' => $old->ends_on ? min($cutoff, $old->ends_on->toDateString()) : $cutoff]);
                } else {
                    $old->delete();
                }
            }
            foreach ($periods as $key => $period) {
                if (in_array($key, $kept, true)) {
                    continue;
                }
                $start = max($period['starts_on'], $today);
                // The initial plan may cover the current month retrospectively.
                if ((int) $vehicle->location_revision === 0) {
                    $start = $period['starts_on'];
                }
                if (! empty($period['ends_on']) && $period['ends_on'] < $start) {
                    continue;
                }
                HrVehicleLocationPeriod::create(['hr_vehicle_id' => $vehicle->id, 'location' => $period['location'],
                    'starts_on' => $start, 'ends_on' => $period['ends_on'] ?? null]);
            }
            $vehicle->forceFill(['location_revision' => $revision + 1])->save();
        });
    }
}
