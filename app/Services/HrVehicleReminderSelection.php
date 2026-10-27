<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\HrVehicleColumnResponsibility;
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
        // Fleet responsibility is explicit; operational full access does not subscribe a user.
        $managerIds = $users->filter(fn (User $user) => $user->checkPermissionTo('hr.vehicles.responsible'))->keys();
        $assignments = HrVehicleColumnResponsibility::all()->keyBy('column_key');
        $receivesColumn = function (string $field, int $id) use ($assignments, $managerIds): bool {
            $assigned = $assignments->get(HrVehicleColumnResponsibility::columnFor($field))?->user_ids ?? [];

            // Revoked/inactive assignees never receive mail; an explicit assignment
            // does not silently subscribe other managers when its users lose access.
            return $managerIds->contains($id) && ($assigned === [] || in_array($id, $assigned, true));
        };
        foreach (HrVehicle::with('responsibleUser')->where('is_active', true)->get() as $vehicle) {
            $recipients = $vehicle->type === 'company' ? $managerIds->all() : [];
            $ownerId = null;
            if ($vehicle->type === 'company' && $vehicle->responsible_user_id) {
                $recipients[] = $ownerId = $vehicle->responsible_user_id;
            }
            if ($vehicle->type === 'private' && $vehicle->responsible_user_id === ($vehicle->created_by ?? $vehicle->user_id)) {
                $recipients[] = $ownerId = $vehicle->responsible_user_id;
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
                $events[] = ['field' => $field, 'identity' => $field.':'.$date->toDateString().':'.$stage,
                    'message' => HrVehicle::COLUMNS[$field].': '.$date->format('d.m.Y').' — '.($days < 0 ? 'po terminie ('.abs($days).' dni)' : ($days === 0 ? 'termin dzisiaj' : 'pozostało '.$days.' dni'))];
            }
            $missing = $today->isMonday() ? $vehicle->missingFields() : [];
            foreach (array_unique($recipients) as $id) {
                $user = $users->get($id);
                if (! $this->eligible($user)) {
                    continue;
                }
                $userEvents = array_values(array_filter($events, fn ($event) => $id === $ownerId || ($vehicle->type === 'company' && $receivesColumn($event['field'], $id))));
                $userMissing = array_filter($missing, fn ($field) => $id === $ownerId || ($vehicle->type === 'company' && $receivesColumn($field, $id)), ARRAY_FILTER_USE_KEY);
                if ($userMissing) {
                    $userEvents[] = ['identity' => 'missing:'.$today->toDateString(), 'message' => 'Brakujące pola: '.implode(', ', $userMissing)];
                }
                foreach ($userEvents as $event) {
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
