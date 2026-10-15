<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_vehicle_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::table('company_settings', fn (Blueprint $table) => $table->json('hr_vehicle_manager_ids')->nullable());
        Schema::table('hr_vehicles', fn (Blueprint $table) => $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete());
        Schema::create('hr_vehicle_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('error_type')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
        // Only PRINŻ was authorised for importing existing fleet locations.
        $company = DB::table('company_settings')->first();
        if (preg_match('/\bprinz\b/i', Str::ascii(($company?->name ?? '').' '.($company?->short_name ?? '')))) {
            Artisan::call('hr:import-prinz-vehicle-locations');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_vehicle_reminder_deliveries');
        Schema::table('hr_vehicles', fn (Blueprint $table) => $table->dropConstrainedForeignId('responsible_user_id'));
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn('hr_vehicle_manager_ids'));
        Schema::dropIfExists('hr_vehicle_locations');
    }
};
