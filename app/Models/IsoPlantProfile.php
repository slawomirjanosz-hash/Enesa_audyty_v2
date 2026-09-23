<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IsoPlantProfile extends Model
{
    /** Select current profiles without filesorting large JSON payloads in MySQL. */
    public function scopeLatestPerSite(Builder $query): Builder
    {
        return $query->whereNotExists(function ($newer) {
            $newer->selectRaw('1')->from('iso_plant_profiles as newer')
                ->whereColumn('newer.audit_id', 'iso_plant_profiles.audit_id')
                ->whereColumn('newer.site_id', 'iso_plant_profiles.site_id')
                ->where(function ($version) {
                    $version->whereColumn('newer.revision', '>', 'iso_plant_profiles.revision')
                        ->orWhere(function ($tie) {
                            $tie->whereColumn('newer.revision', 'iso_plant_profiles.revision')
                                ->whereColumn('newer.id', '>', 'iso_plant_profiles.id');
                        });
                });
        });
    }

    public const DOCUMENT_TITLE = '1 Wstęp ISO Profil zakładu';

    protected $guarded = ['id'];

    protected $casts = ['definition' => 'array', 'answers' => 'array', 'auditor_changes' => 'array', 'client_changes' => 'array', 'client_approval' => 'array', 'auditor_approval' => 'array', 'issuer' => 'array', 'as_of_date' => 'date', 'revision' => 'integer', 'lock_version' => 'integer'];

    public const STATUSES = ['editing' => 'Wypełnianie', 'auditor_corrected' => 'Poprawiony przez audytora', 'submitted' => 'Zatwierdzona przez klienta', 'returned' => 'Do uzupełnienia', 'approved' => 'Zatwierdzona przez audytora'];
}
