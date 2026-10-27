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

    public static function columnFor(string $field): string
    {
        return match ($field) {
            'registration_document_present' => 'registration_document',
            'service_book_status' => 'service_book_entry_at',
            default => $field,
        };
    }
}
