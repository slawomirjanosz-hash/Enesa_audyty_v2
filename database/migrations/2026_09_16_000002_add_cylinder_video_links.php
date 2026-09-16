<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cylinder_videos', function (Blueprint $table) {
            $table->text('external_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cylinder_videos', fn (Blueprint $table) => $table->dropColumn('external_url'));
    }
};
