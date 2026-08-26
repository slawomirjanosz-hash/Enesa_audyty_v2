<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_business_trips', function (Blueprint $table): void {
            $table->decimal('accommodation_cost', 12, 2)->default(0)->after('toll_cost');
            $table->decimal('other_cost', 12, 2)->default(0)->after('accommodation_cost');
        });
    }

    public function down(): void
    {
        Schema::table('hr_business_trips', fn (Blueprint $table) => $table->dropColumn(['accommodation_cost', 'other_cost']));
    }
};
