<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_leaves', function (Blueprint $table): void {
            $table->boolean('include_weekends')->default(false)->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('hr_leaves', function (Blueprint $table): void {
            $table->dropColumn('include_weekends');
        });
    }
};
