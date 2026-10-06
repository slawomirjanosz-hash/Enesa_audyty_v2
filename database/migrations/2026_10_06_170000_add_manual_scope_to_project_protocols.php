<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_protocols', function (Blueprint $table) {
            $table->string('items_mode', 20)->default('detailed');
            $table->text('manual_items_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_protocols', fn (Blueprint $table) => $table->dropColumn(['items_mode', 'manual_items_description']));
    }
};
