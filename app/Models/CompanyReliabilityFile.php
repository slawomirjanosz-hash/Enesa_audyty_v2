<?php

namespace App\Models;

use App\Models\Concerns\EnforcesDocumentQuota;
use Illuminate\Database\Eloquent\Model;

class CompanyReliabilityFile extends Model
{
    use EnforcesDocumentQuota;

    protected $guarded = ['id'];
}
