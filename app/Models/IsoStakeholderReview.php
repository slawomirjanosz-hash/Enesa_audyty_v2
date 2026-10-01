<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoStakeholderReview extends Model
{
    public const DOCUMENT_TITLE = '4.2 Rejestr stron zainteresowanych D-EnMS-STR-01';

    public const COMPLIANCE_TITLE = '4.2 Wyciąg wymagań zgodności D-EnMS-STR-01';

    protected $guarded = ['id'];

    protected $casts = ['answers' => 'array', 'basis' => 'array', 'consultant' => 'array', 'analysis' => 'array', 'compliance_register' => 'array', 'auditor_changes' => 'array', 'client_changes' => 'array', 'client_approval' => 'array', 'auditor_approval' => 'array', 'issuer' => 'array', 'lock_version' => 'integer'];
}
