<?php

namespace App\Models;

use App\Services\IsoExecutionQuestionnaire;
use Illuminate\Database\Eloquent\Model;

class IsoSystemReview extends Model
{
    public const TITLES = [...IsoExecutionQuestionnaire::TITLES, '4-3' => '4.3 Zakres i granice systemu D-EnMS-ZAK-01', '4-4' => '4.4 System zarządzania energią D-EnMS-SYS-01', '5-1' => '5.1 Przywództwo i zaangażowanie D-EnMS-PRZ-01', '5-2' => '5.2 Polityka energetyczna D-EnMS-POL-01', '5-3' => '5.3 Role, odpowiedzialności i uprawnienia D-EnMS-ROL-01',
        '6-1' => '6.1 Ryzyka i szanse D-EnMS-RYZ-01', '6-2' => '6.2 Cele i plany działań D-EnMS-CEL-01', '6-3' => '6.3 Przegląd energetyczny D-EnMS-PEN-01', '6-4' => '6.4 Wskaźniki EnPI D-EnMS-WSK-01', '6-5' => '6.5 Linia bazowa EnB D-EnMS-BAZ-01', '6-6' => '6.6 Plan zbierania danych D-EnMS-DAN-01', '7-1' => '7.1 Zasoby D-EnMS-ZAS-01', '7-2' => '7.2 Kompetencje D-EnMS-KMP-01', '7-3' => '7.3 Świadomość D-EnMS-SWI-01'];

    protected $guarded = ['id'];

    protected $casts = ['answers' => 'array', 'consultant' => 'array', 'source_snapshot' => 'array', 'client_approval' => 'array', 'auditor_approval' => 'array', 'client_changes' => 'array', 'auditor_changes' => 'array', 'issuer' => 'array', 'lock_version' => 'integer'];
}
