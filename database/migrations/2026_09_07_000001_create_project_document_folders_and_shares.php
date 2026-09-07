<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('project_document_folder_id')->nullable()->after('project_id')
                ->constrained('project_document_folders')->nullOnDelete();
        });

        Schema::create('project_document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_document_folder_id')->constrained('project_document_folders')->cascadeOnDelete();
            $table->enum('access_level', ['view', 'upload'])->default('view');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_document_shares');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_document_folder_id');
        });
        Schema::dropIfExists('project_document_folders');
    }
};
