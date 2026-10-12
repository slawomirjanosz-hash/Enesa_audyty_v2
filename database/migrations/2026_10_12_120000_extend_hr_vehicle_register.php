<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_vehicles', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fuel_card_number', 50)->nullable();
            $table->string('responsible_person')->nullable();
            $table->string('usage_location')->nullable();
            $table->unsignedTinyInteger('vat_percent')->nullable();
            $table->string('insurer', 150)->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->boolean('vat_26')->nullable();
            $table->decimal('cit_percent', 5, 2)->nullable();
            $table->boolean('gps')->nullable();
            $table->date('logbook_entry_at')->nullable();
            $table->date('service_book_entry_at')->nullable();
            $table->string('service_book_status', 20)->nullable();
            $table->date('inspection_due_at')->nullable();
            $table->string('registration_document')->nullable();
        });
        // Historical authors were not tracked. Preserve the existing private owner.
        DB::table('hr_vehicles')->where('type', 'private')->update(['created_by' => DB::raw('user_id')]);
        Schema::table('users', fn (Blueprint $table) => $table->json('hr_vehicle_columns')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('hr_vehicle_columns'));
        Schema::table('hr_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['fuel_card_number', 'responsible_person', 'usage_location', 'vat_percent', 'insurer', 'insurance_expires_at', 'vat_26', 'cit_percent', 'gps', 'logbook_entry_at', 'service_book_entry_at', 'service_book_status', 'inspection_due_at', 'registration_document']);
        });
    }
};
