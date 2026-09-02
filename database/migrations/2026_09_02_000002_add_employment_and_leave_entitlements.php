<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_employment_contract')->default(false)->after('is_active');
        });

        Schema::create('hr_leave_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('entitled_days')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_entitlements');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('has_employment_contract');
        });
    }
};
