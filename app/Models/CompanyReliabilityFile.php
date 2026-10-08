<?php

namespace App\Models;

use App\Models\Concerns\TracksDocumentStorage;
use Illuminate\Database\Eloquent\Model;

class CompanyReliabilityFile extends Model
{
    use TracksDocumentStorage;

    protected $guarded = ['id'];

    protected $casts = ['parsed_finances' => 'array'];
}
