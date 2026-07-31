<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'primary_color',
    ];

    public function primaryColor(): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $this->primary_color)
            ? strtoupper($this->primary_color)
            : '#1A4D3A';
    }

    public function logoUrl(): string
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset('Logo2.png');
    }
}
