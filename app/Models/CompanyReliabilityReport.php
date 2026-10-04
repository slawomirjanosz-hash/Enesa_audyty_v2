<?php

namespace App\Models;

use App\Models\Concerns\EnforcesDocumentQuota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyReliabilityReport extends Model
{
    use EnforcesDocumentQuota;

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];

    public const LABELS = ['unassessed' => 'Nie oceniono', 'green' => 'Wiarygodna', 'yellow' => 'Zachowaj ostrożność', 'red' => 'Zagrożenie'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function filename(): string
    {
        return 'Raport wiarygodności '.$this->company_id.'-'.$this->id.' '.$this->created_at->format('Y-m-d').'.pdf';
    }

    public function displayStatus(): string
    {
        // A historical green evaluation must not look like a current check indefinitely.
        return $this->status === 'green' && $this->created_at->lt(now()->subDays(30)) ? 'yellow' : $this->status;
    }
}
