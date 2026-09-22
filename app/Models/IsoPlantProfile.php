<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoPlantProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['definition' => 'array', 'answers' => 'array', 'client_approval' => 'array', 'auditor_approval' => 'array', 'issuer' => 'array', 'as_of_date' => 'date', 'revision' => 'integer', 'lock_version' => 'integer'];

    public const STATUSES = ['editing' => 'Wypełnianie', 'submitted' => 'Zatwierdzona przez klienta', 'returned' => 'Do uzupełnienia', 'approved' => 'Zatwierdzona przez audytora'];
}
