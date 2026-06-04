<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySettings extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'email',
        'phone',
        'address',
        'city',
        'postcode',
        'nip',
        'website',
        'logo_path',
    ];
}
