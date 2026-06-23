<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old stub offers table (had only: id, company_id, title, status, timestamps)
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('offers');
        Schema::enableForeignKeyConstraints();

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_number')->unique();
            $table->string('offer_slug')->nullable();
            $table->string('offer_full_number')->nullable();
            $table->enum('status', ['w_toku', 'wygrana', 'przegrana', 'zarchiwizowana'])->default('w_toku');
            $table->enum('won_as', ['audyt', 'projekt', 'inne'])->nullable();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('offer_template_version_id')->nullable()->constrained('offer_template_versions')->nullOnDelete();
            $table->foreignId('offer_request_id')->nullable()->constrained('offer_requests')->nullOnDelete();
            $table->decimal('kwota_netto', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
