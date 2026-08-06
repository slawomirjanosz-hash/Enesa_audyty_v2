<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanySettings extends Model
{
    public const APP_MODULES = [
        'dashboard' => 'Dashboard',
        'crm' => 'CRM i firmy',
        'offers' => 'Oferty i zapytania',
        'audits' => 'Audyty',
        'documents' => 'Dokumenty',
        'client_zone' => 'Strefa klienta',
    ];

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
        'welcome_page_mode',
        'enabled_modules',
    ];

    protected $casts = [
        'enabled_modules' => 'array',
    ];

    public function moduleEnabled(string $module): bool
    {
        return in_array($module, $this->enabled_modules ?? array_keys(self::APP_MODULES), true);
    }

    public static function moduleIsEnabled(string $module): bool
    {
        return static::query()->first()?->moduleEnabled($module) ?? true;
    }

    public static function staffLandingRoute(): string
    {
        $routes = [
            'dashboard' => 'dashboard',
            'crm' => 'crm.index',
            'offers' => 'offers.index',
            'audits' => 'audit-types.index',
            'documents' => 'documents.index',
            'client_zone' => 'client-zone.index',
        ];

        foreach ($routes as $module => $route) {
            if (static::moduleIsEnabled($module)) {
                return $route;
            }
        }

        return 'profile.edit';
    }

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
