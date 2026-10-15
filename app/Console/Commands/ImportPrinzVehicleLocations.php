<?php

namespace App\Console\Commands;

use App\Models\CompanySettings;
use App\Models\HrVehicleLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportPrinzVehicleLocations extends Command
{
    protected $signature = 'hr:import-prinz-vehicle-locations {--dry-run}';

    protected $description = 'Uzupełnia słownik lokalizacji PRINŻ wyłącznie z aut służbowych tej instalacji';

    public function handle(): int
    {
        $company = CompanySettings::first();
        if (! preg_match('/\bprinz\b/i', Str::ascii(($company?->name ?? '').' '.($company?->short_name ?? '')))) {
            $this->error('Import dozwolony tylko w instalacji PRINŻ. Nie zmieniono danych.');

            return self::FAILURE;
        }
        $names = DB::table('hr_vehicles')->where('type', 'company')->whereNotNull('usage_location')
            ->pluck('usage_location')->map(fn ($value) => trim($value))->filter()->unique();
        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($names) {
                foreach ($names as $name) {
                    HrVehicleLocation::firstOrCreate(['name' => $name]);
                }
                // Normalize surrounding whitespace without importing private locations.
                DB::table('hr_vehicles')->where('type', 'company')->whereNotNull('usage_location')
                    ->update(['usage_location' => DB::raw('TRIM(usage_location)')]);
            });
        }
        $this->info(($this->option('dry-run') ? 'Bez zapisu. ' : 'Uzupełniono słownik. ').'Liczba lokalizacji: '.$names->count());

        return self::SUCCESS;
    }
}
