<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_type_id')->constrained()->cascadeOnDelete();
            $table->string('version_number');
            $table->longText('html_content');
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_type_versions');
    }
};
