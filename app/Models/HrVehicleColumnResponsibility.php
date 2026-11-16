<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class HrVehicleColumnResponsibility extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['user_ids' => 'array', 'revision' => 'integer'];

    public static function eligiblePeople(): Collection
    {
        return HrVehicle::eligibleResponsibleUsers()->with(['roles.permissions', 'permissions'])->orderBy('name')->get()
            ->filter(fn (User $user) => $user->checkPermissionTo('hr.vehicles.responsible'))->values();
    }

    public static function editableColumns(User $user, Collection $assignments): array
    {
        if (! $user->is_active || $user->roles->isEmpty() || $user->hasAnyRole(['client_admin', 'client_user'])
            || ! $user->checkPermissionTo('hr.vehicles.responsible')) {
            return [];
        }

        return array_values(array_filter(array_keys(HrVehicle::COLUMNS), function ($column) use ($user, $assignments) {
            $ids = $assignments->get($column)?->user_ids ?? [];

            return $column !== 'type' && ($ids === [] || in_array($user->id, $ids, true));
        }));
    }

    public static function columnFor(string $field): string
    {
        return match ($field) {
            'responsible_user_id' => 'responsible_person',
            'registration_document_present' => 'registration_document',
            'service_book_status' => 'service_book_entry_at',
            default => $field,
        };
    }
}
