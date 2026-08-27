<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->decimal('hr_km_rate', 8, 4)->default(0)->after('enabled_modules');
            $table->decimal('hr_diet_rate', 8, 2)->default(45)->after('hr_km_rate');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn(['hr_km_rate', 'hr_diet_rate']));
    }
};
