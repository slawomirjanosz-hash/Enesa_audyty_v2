<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HrVehicleReminderSelection
{
    public function groups(): Collection
    {
        $groups = collect();
        if (! CompanySettings::moduleIsEnabled('hr')) {
            return $groups;
        }
        $today = now('Europe/Warsaw')->startOfDay();
        $users = HrVehicle::eligibleResponsibleUsers()->get()->keyBy('id');
        $managers = $users->only(CompanySettings::first()?->hr_vehicle_manager_ids ?? []);
        foreach (HrVehicle::with('responsibleUser')->where('is_active', true)->get() as $vehicle) {
            $recipients = $vehicle->type === 'company' ? $managers->modelKeys() : [];
            if ($vehicle->type === 'company' && $vehicle->responsible_user_id) {
                $recipients[] = $vehicle->responsible_user_id;
            }
            if ($vehicle->type === 'private' && $vehicle->responsible_user_id === ($vehicle->created_by ?? $vehicle->user_id)) {
                $recipients[] = $vehicle->responsible_user_id;
            }
            $events = [];
            foreach (['insurance_expires_at', 'inspection_due_at'] as $field) {
                $date = $vehicle->$field;
                if (! $date) {
                    continue;
                }
                $deadline = Carbon::parse($date->toDateString(), 'Europe/Warsaw')->startOfDay();
                $days = (int) $today->diffInDays($deadline, false);
                if ($days > 14) {
                    continue;
                }
                $stage = $days < 0 ? 'overdue:'.$today->toDateString() : ($days <= 7 ? 'urgent' : 'soon');
                $events[] = ['identity' => $field.':'.$date->toDateString().':'.$stage,
                    'message' => HrVehicle::COLUMNS[$field].': '.$date->format('d.m.Y').' — '.($days < 0 ? 'po terminie ('.abs($days).' dni)' : ($days === 0 ? 'termin dzisiaj' : 'pozostało '.$days.' dni'))];
            }
            if ($today->isMonday() && ($missing = $vehicle->missingFields())) {
                $events[] = ['identity' => 'missing:'.$today->toDateString(), 'message' => 'Brakujące pola: '.implode(', ', $missing)];
            }
            foreach (array_unique($recipients) as $id) {
                $user = $users->get($id);
                if (! $this->eligible($user)) {
                    continue;
                }
                foreach ($events as $event) {
                    $key = hash('sha256', $id.':'.$vehicle->id.':'.$event['identity']);
                    $items = $groups->get($id, collect());
                    $items->push(['key' => $key, 'user' => $user, 'vehicle' => $vehicle, 'message' => $event['message']]);
                    $groups->put($id, $items);
                }
            }
        }

        return $groups;
    }

    private function eligible(?User $user): bool
    {
        return $user && filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }
}
