<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->mediumText('signature_data')->nullable()->after('avatar_mime');
            $table->string('signature_mime', 100)->nullable()->after('signature_data');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['signature_data', 'signature_mime']);
        });
    }
};
