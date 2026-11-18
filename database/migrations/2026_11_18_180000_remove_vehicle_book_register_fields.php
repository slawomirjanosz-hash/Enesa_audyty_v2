<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_vehicles', function (Blueprint $table) {
            $table->dropColumn(['logbook_entry_at', 'service_book_entry_at', 'service_book_status']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_vehicles', function (Blueprint $table) {
            $table->date('logbook_entry_at')->nullable();
            $table->date('service_book_entry_at')->nullable();
            $table->string('service_book_status', 20)->nullable();
        });
    }
};
