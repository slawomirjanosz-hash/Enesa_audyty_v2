<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cylinder_imports')) {
            Schema::create('cylinder_imports', function (Blueprint $table) {
                $table->id();
                $table->uuid('token')->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
                $table->longText('rows');
                $table->boolean('create_series')->default(false);
                $table->string('series_name', 160)->nullable();
                $table->foreignId('responsible_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('series_id')->nullable()->constrained('cylinder_inspection_series')->restrictOnDelete();
                $table->timestamp('expires_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_imports');
    }
};
