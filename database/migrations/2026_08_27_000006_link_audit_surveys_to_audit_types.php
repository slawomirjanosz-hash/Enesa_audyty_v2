<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_surveys', function (Blueprint $table) {
            $table->foreignId('audit_type_id')->nullable()->after('audit_id')->constrained()->nullOnDelete();
            $table->foreignId('audit_type_version_id')->nullable()->after('audit_type_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audit_type_version_id');
            $table->dropConstrainedForeignId('audit_type_id');
        });
    }
};
