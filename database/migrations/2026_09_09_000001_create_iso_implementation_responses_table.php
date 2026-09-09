<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_implementation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('section_id', 40);
            $table->string('action_key', 80);
            $table->json('answers')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['audit_id', 'section_id', 'action_key'], 'iso_response_audit_section_action_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_implementation_responses');
    }
};
