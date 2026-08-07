<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['planned', 'active', 'on_hold', 'completed', 'cancelled'])->default('planned');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('color', 7)->default('#2563EB');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('project_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cost', 'invoice']);
            $table->string('name');
            $table->string('document_number')->nullable();
            $table->date('entry_date');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['planned', 'issued', 'paid'])->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['material', 'service'])->default('material');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->enum('status', ['requested', 'ordered', 'in_progress', 'purchased', 'cancelled'])->default('requested');
            $table->date('needed_by')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('offer_id')->constrained()->nullOnDelete();
            $table->date('start_date')->nullable()->after('due_date');
            $table->unsignedTinyInteger('progress')->default(0)->after('priority');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
            $table->foreignId('project_id')->nullable()->after('audit_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['start_date', 'progress']);
        });
        Schema::dropIfExists('project_requirements');
        Schema::dropIfExists('project_financial_entries');
        Schema::dropIfExists('project_stages');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
